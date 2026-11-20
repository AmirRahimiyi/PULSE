
<?php

class Bookmark
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Check whether a post is bookmarked by a user.
    public function hasBookmarked(int $postId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM bookmarks
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

    // Get all bookmarks belonging to a user.
    public function getByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                bookmarks.id AS bookmark_id,
                bookmarks.created_at AS bookmarked_at,
                posts.id,
                posts.title,
                posts.content,
                posts.created_at,
                users.username,
                categories.name AS category_name
            FROM bookmarks
            INNER JOIN posts
                ON bookmarks.post_id = posts.id
            INNER JOIN users
                ON posts.user_id = users.id
            LEFT JOIN categories
                ON posts.category_id = categories.id
            WHERE bookmarks.user_id = :user_id
              AND posts.status = 'published'
            ORDER BY bookmarks.created_at DESC
        ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

        return $stmt->fetchAll();
    }

    // Save or remove a bookmark.
    public function toggle(int $postId, int $userId): bool
    {
        if ($this->hasBookmarked($postId, $userId)) {

            $stmt = $this->pdo->prepare("
                DELETE FROM bookmarks
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
            INSERT INTO bookmarks (post_id, user_id)
            VALUES (:post_id, :user_id)
        ");

        $stmt->execute([
            ':post_id' => $postId,
            ':user_id' => $userId
        ]);

        return true;
    }
}
