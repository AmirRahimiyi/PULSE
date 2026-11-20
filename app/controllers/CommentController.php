
<?php

require_once __DIR__ . '/../models/Comment.php';

class CommentController
{
    private PDO $pdo;
    private Comment $commentModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->commentModel = new Comment($pdo);
    }

    /**
     * Create a new comment or reply.
     */
    public function create(
        int $postId,
        int $userId,
        string $content,
        ?int $parentId = null
    ): array {

        try {
            if ($postId < 1 || $userId < 1) {
                return [
                    'success' => false,
                    'message' => 'Invalid request.'
                ];
            }

            $content = trim($content);

            if ($content === '') {
                return [
                    'success' => false,
                    'message' => 'Comment cannot be empty.'
                ];
            }

            $length = function_exists('mb_strlen')
                ? mb_strlen($content, 'UTF-8')
                : strlen($content);

            if ($length > 2000) {
                return [
                    'success' => false,
                    'message' => 'Comment cannot exceed 2000 characters.'
                ];
            }

            // Check published post.
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM posts
                WHERE id = :id
                  AND status = 'published'
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $postId
            ]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return [
                    'success' => false,
                    'message' => 'Post not found.'
                ];
            }

            // Check active user.
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM users
                WHERE id = :id
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $userId
            ]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return [
                    'success' => false,
                    'message' => 'Account unavailable.'
                ];
            }

            // Validate reply target.
            if ($parentId !== null) {

                if ($parentId < 1) {
                    return [
                        'success' => false,
                        'message' => 'Invalid parent comment.'
                    ];
                }

                $parentComment = $this->commentModel->findById(
                    $parentId
                );

                if (
                    !$parentComment ||
                    (int) $parentComment['post_id'] !== $postId ||
                    $parentComment['parent_id'] !== null
                ) {
                    return [
                        'success' => false,
                        'message' => 'Invalid reply target.'
                    ];
                }
            }

            // Create comment or reply.
            $commentId = $this->commentModel->create(
                $postId,
                $userId,
                $content,
                $parentId
            );

            return [
                'success' => true,
                'comment_id' => $commentId,
                'message' => $parentId === null
                    ? 'Comment added successfully.'
                    : 'Reply added successfully.'
            ];

        } catch (Throwable $e) {

            error_log(
                'Comment creation error: ' . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Something went wrong.'
            ];
        }
    }

    /**
     * Delete a comment owned by the current user.
     */
    public function delete(
        int $commentId,
        int $userId
    ): array {

        try {
            if ($commentId < 1 || $userId < 1) {
                return [
                    'success' => false,
                    'message' => 'Invalid request.'
                ];
            }

            $deleted = $this->commentModel->deleteOwnedByUser(
                $commentId,
                $userId
            );

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Comment not found, unauthorized, or has replies.'
                ];
            }

            return [
                'success' => true,
                'message' => 'Comment deleted successfully.'
            ];

        } catch (Throwable $e) {

            error_log(
                'Comment deletion error: ' . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Something went wrong.'
            ];
        }
    }
}
