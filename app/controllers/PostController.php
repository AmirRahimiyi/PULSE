
<?php

require_once __DIR__ . '/../models/Post.php';

class PostController
{
    private PDO $pdo;
    private Post $postModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->postModel = new Post($pdo);
    }

    /* =====================================
       CREATE POST
    ===================================== */

    public function create(array $data, int $userId): array
    {
        try {

            // Get and sanitize inputs
            $title = trim($data['title'] ?? '');
            $content = trim($data['content'] ?? '');
            $categoryId = filter_var(
                $data['category_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $linkUrl = trim($data['link_url'] ?? '');

            // Validate title
            if ($title === '') {
                return [
                    'success' => false,
                    'message' => 'Please enter a post title.'
                ];
            }

            if (mb_strlen($title) > 180) {
                return [
                    'success' => false,
                    'message' => 'The title cannot exceed 180 characters.'
                ];
            }

            // Validate content
            if ($content === '') {
                return [
                    'success' => false,
                    'message' => 'Please enter some content.'
                ];
            }

            // Validate category
            if (!$categoryId || $categoryId < 1) {
                return [
                    'success' => false,
                    'message' => 'Please select a valid category.'
                ];
            }

            $categoryQuery = $this->pdo->prepare("
                SELECT id
                FROM categories
                WHERE id = :id
                LIMIT 1
            ");

            $categoryQuery->execute([
                ':id' => $categoryId
            ]);

            if (!$categoryQuery->fetch()) {
                return [
                    'success' => false,
                    'message' => 'The selected category does not exist.'
                ];
            }

            // Validate optional URL
            if ($linkUrl !== '') {

                if (
                    !filter_var($linkUrl, FILTER_VALIDATE_URL) ||
                    !in_array(
                        strtolower((string) parse_url($linkUrl, PHP_URL_SCHEME)),
                        ['http', 'https'],
                        true
                    )
                ) {
                    return [
                        'success' => false,
                        'message' => 'Please enter a valid HTTP or HTTPS URL.'
                    ];
                }

                if (strlen($linkUrl) > 2048) {
                    return [
                        'success' => false,
                        'message' => 'The link is too long.'
                    ];
                }
            }

            // Verify that the user is active
            $userQuery = $this->pdo->prepare("
                SELECT id
                FROM users
                WHERE id = :id
                  AND status = 'active'
                LIMIT 1
            ");

            $userQuery->execute([
                ':id' => $userId
            ]);

            if (!$userQuery->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Your account is not available.'
                ];
            }

            // Create post
            $postId = $this->postModel->create([
                'user_id' => $userId,
                'category_id' => $categoryId,
                'title' => $title,
                'content' => $content,
                'link_url' => $linkUrl
            ]);

            return [
                'success' => true,
                'message' => 'Your post has been published successfully.',
                'post_id' => $postId
            ];

        } catch (PDOException $e) {

            error_log('Post creation error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Something went wrong while publishing your post.'
            ];
        }
    }
}
