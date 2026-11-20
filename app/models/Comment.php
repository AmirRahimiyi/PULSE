
<?php

class Comment
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a comment or reply.
     */
    public function create(
        int $postId,
        int $userId,
        string $content,
        ?int $parentId = null
    ): int {

        if ($postId < 1 || $userId < 1) {
            throw new InvalidArgumentException('Invalid comment data.');
        }

        $content = trim($content);

        if ($content === '') {
            throw new InvalidArgumentException('Comment cannot be empty.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO comments (
                post_id,
                user_id,
                parent_id,
                content
            )
            VALUES (
                :post_id,
                :user_id,
                :parent_id,
                :content
            )
        ");

        $stmt->execute([
            ':post_id' => $postId,
            ':user_id' => $userId,
            ':parent_id' => $parentId,
            ':content' => $content
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get all comments for a post with user profile information.
     */
    public function getByPost(int $postId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                comments.id,
                comments.post_id,
                comments.user_id,
                comments.parent_id,
                comments.content,
                comments.created_at,

                users.username,
                users.full_name,
                users.avatar

            FROM comments

            INNER JOIN users
                ON comments.user_id = users.id

            WHERE comments.post_id = :post_id
              AND users.status = 'active'

            ORDER BY
                comments.created_at ASC,
                comments.id ASC
        ");

        $stmt->execute([
            ':post_id' => $postId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a comment by ID.
     */
    public function findById(int $commentId): ?array
    {
        if ($commentId < 1) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM comments
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $commentId
        ]);

        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $comment ?: null;
    }

    /**
     * Delete an owned comment only if it has no replies.
     */
    public function deleteOwnedByUser(
        int $commentId,
        int $userId
    ): bool {

        if ($commentId < 1 || $userId < 1) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                SELECT id
                FROM comments
                WHERE id = :comment_id
                  AND user_id = :user_id
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                ':comment_id' => $commentId,
                ':user_id' => $userId
            ]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->pdo->rollBack();
                return false;
            }

            // Do not delete a comment that has replies.
            $replyStmt = $this->pdo->prepare("
                SELECT id
                FROM comments
                WHERE parent_id = :parent_id
                LIMIT 1
            ");

            $replyStmt->execute([
                ':parent_id' => $commentId
            ]);

            if ($replyStmt->fetch(PDO::FETCH_ASSOC)) {
                $this->pdo->rollBack();
                return false;
            }

            $deleteStmt = $this->pdo->prepare("
                DELETE FROM comments
                WHERE id = :comment_id
                  AND user_id = :user_id
            ");

            $deleteStmt->execute([
                ':comment_id' => $commentId,
                ':user_id' => $userId
            ]);

            $deleted = $deleteStmt->rowCount() > 0;

            $this->pdo->commit();

            return $deleted;

        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Count comments and replies for a post.
     */
    public function countByPost(int $postId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM comments
            WHERE post_id = :post_id
        ");

        $stmt->execute([
            ':post_id' => $postId
        ]);

        return (int) $stmt->fetchColumn();
    }
}
