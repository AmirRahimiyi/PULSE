
<?php

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =====================================
       FIND USER BY EMAIL
    ====================================== */

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    /* =====================================
       FIND USER BY USERNAME
    ====================================== */

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    /* =====================================
       FIND PUBLIC USER PROFILE
    ====================================== */

    public function findPublicByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                username,
                full_name,
                bio,
                avatar,
                role,
                created_at
            FROM users
            WHERE username = :username
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    /* =====================================
       CREATE NEW USER
    ====================================== */

    public function create(
        string $username,
        string $email,
        string $password
    ): bool {

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                username,
                email,
                password
            )
            VALUES (
                :username,
                :email,
                :password
            )
        ");

        return $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password' => $passwordHash
        ]);
    }

    /* =====================================
       VERIFY USER PASSWORD
    ====================================== */

    public function verifyPassword(
        string $password,
        string $passwordHash
    ): bool {

        return password_verify(
            $password,
            $passwordHash
        );
    }

    /* =====================================
       GET USER BY ID
    ====================================== */

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                username,
                email,
                full_name,
                bio,
                avatar,
                role,
                status,
                created_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    /* =====================================
       UPDATE USER PROFILE
    ====================================== */

    public function updateProfile(
        int $userId,
        string $fullName,
        string $bio,
        ?string $avatar = null
    ): bool {

        if ($avatar !== null) {

            $stmt = $this->pdo->prepare("
                UPDATE users
                SET
                    full_name = :full_name,
                    bio = :bio,
                    avatar = :avatar
                WHERE id = :id
            ");

            return $stmt->execute([
                'full_name' => $fullName,
                'bio' => $bio,
                'avatar' => $avatar,
                'id' => $userId
            ]);
        }

        $stmt = $this->pdo->prepare("
            UPDATE users
            SET
                full_name = :full_name,
                bio = :bio
            WHERE id = :id
        ");

        return $stmt->execute([
            'full_name' => $fullName,
            'bio' => $bio,
            'id' => $userId
        ]);
    }

    /* =====================================
       GET USER POST COUNT
    ====================================== */

    public function getPostCount(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM posts
            WHERE user_id = :user_id
              AND status = 'published'
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return (int) $stmt->fetchColumn();
    }

    /* =====================================
       GET USER PUBLISHED POSTS
    ====================================== */

    public function getPublishedPostsByUser(
        int $userId,
        int $limit = 20
    ): array {

        $limit = max(1, min($limit, 100));

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM posts
            WHERE user_id = :user_id
              AND status = 'published'
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchAll();
    }

}
