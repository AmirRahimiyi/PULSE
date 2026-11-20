
<?php

require_once __DIR__ . '/../models/Bookmark.php';

class BookmarkController
{
    private PDO $pdo;
    private Bookmark $bookmarkModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->bookmarkModel = new Bookmark($pdo);
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

            $bookmarked = $this->bookmarkModel->toggle(
                $postId,
                $userId
            );

            return [
                'success' => true,
                'bookmarked' => $bookmarked,
                'message' => $bookmarked
                    ? 'Post saved successfully.'
                    : 'Bookmark removed successfully.'
            ];

        } catch (PDOException $e) {

            error_log('Bookmark error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Something went wrong.'
            ];
        }
    }
}
