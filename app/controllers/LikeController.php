<?php

require_once __DIR__ . '/../models/Like.php';

class LikeController
{
    private Like $likeModel;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->likeModel = new Like($pdo);
    }

    public function toggle(int $postId, int $userId): array
    {
        try {
            if ($postId < 1 || $userId < 1) {
                return [
                    'success' => false,
                    'message' => 'Invalid request.'
                ];
            }

            // Check whether the post exists and is published.
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM posts
                WHERE id = :id
                  AND status = 'published'
                LIMIT 1
            ");

            $stmt->execute([':id' => $postId]);

            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Post not found.'
                ];
            }

            // Check whether the user is active.
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM users
                WHERE id = :id
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([':id' => $userId]);

            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Account unavailable.'
                ];
            }

            $liked = $this->likeModel->toggle($postId, $userId);

            $count = $this->likeModel->countByPost($postId);

            return [
                'success' => true,
                'liked' => $liked,
                'count' => $count,
                'message' => $liked
                    ? 'Post liked successfully.'
                    : 'Like removed successfully.'
            ];

        } catch (PDOException $e) {
            error_log('Like error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Something went wrong.'
            ];
        }
    }
}