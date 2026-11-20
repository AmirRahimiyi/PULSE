<?php

class Like
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check whether a user has liked a post.
     */
    public function hasLiked(int $postId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM post_likes
            WHERE post_id = :post_id
              AND user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':post_id' => $postId,
            ':user_id' => $userId
        ]);

        return (bool) $stmt->fetch();
    }

    /**
     * Get total likes for a post.
     */
    public function countByPost(int $postId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM post_likes
            WHERE post_id = :post_id
        ");

        $stmt->execute([
            ':post_id' => $postId
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Like or unlike a post.
     */
    public function toggle(int $postId, int $userId): bool
    {
        if ($this->hasLiked($postId, $userId)) {
            $stmt = $this->pdo->prepare("
                DELETE FROM post_likes
                WHERE post_id = :post_id
                  AND user_id = :user_id
            ");

            $stmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId
            ]);

            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO post_likes (post_id, user_id)
            VALUES (:post_id, :user_id)
        ");

        $stmt->execute([
            ':post_id' => $postId,
            ':user_id' => $userId
        ]);

        return true;
    }
}