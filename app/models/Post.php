
<?php

class Post
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =====================================
       CREATE POST
    ===================================== */

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO posts (
                user_id,
                category_id,
                title,
                content,
                link_url,
                status
            )
            VALUES (
                :user_id,
                :category_id,
                :title,
                :content,
                :link_url,
                'published'
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':user_id'     => $data['user_id'],
            ':category_id' => $data['category_id'],
            ':title'      => $data['title'],
            ':content'     => $data['content'],
            ':link_url'   => !empty($data['link_url'])
                ? $data['link_url']
                : null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /* =====================================
       FIND POST BY ID
    ===================================== */

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                posts.*,
                users.username,
                users.avatar,
                categories.name AS category_name,
                categories.slug AS category_slug
            FROM posts
            INNER JOIN users
                ON posts.user_id = users.id
            LEFT JOIN categories
                ON posts.category_id = categories.id
            WHERE posts.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $post = $stmt->fetch();

        return $post ?: null;
    }

    /* =====================================
       GET PUBLISHED POSTS
    ===================================== */

    public function getPublished(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $sql = "
            SELECT
                posts.*,
                users.username,
                users.avatar,
                categories.name AS category_name,
                categories.slug AS category_slug
            FROM posts
            INNER JOIN users
                ON posts.user_id = users.id
            LEFT JOIN categories
                ON posts.category_id = categories.id
            WHERE posts.status = 'published'
              AND users.status = 'active'
            ORDER BY posts.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /* =====================================
       SEARCH PUBLISHED POSTS
    ===================================== */

    public function searchPublished(
        string $query,
        int $limit = 50
    ): array {

        $query = trim($query);

        if ($query === '') {
            return $this->getPublished($limit);
        }

        $limit = max(1, min($limit, 100));

        $sql = "
            SELECT
                posts.*,
                users.username,
                users.avatar,
                categories.name AS category_name,
                categories.slug AS category_slug
            FROM posts
            INNER JOIN users
                ON posts.user_id = users.id
            LEFT JOIN categories
                ON posts.category_id = categories.id
            WHERE posts.status = 'published'
              AND users.status = 'active'
              AND (
                    posts.title LIKE :title_query
                    OR posts.content LIKE :content_query
                    OR users.username LIKE :username_query
                    OR categories.name LIKE :category_query
              )
            ORDER BY posts.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);

        $searchTerm = '%' . $query . '%';

        $stmt->bindValue(':title_query', $searchTerm);
        $stmt->bindValue(':content_query', $searchTerm);
        $stmt->bindValue(':username_query', $searchTerm);
        $stmt->bindValue(':category_query', $searchTerm);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }
}
