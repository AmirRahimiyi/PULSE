
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/Post.php';

header('Content-Type: application/json; charset=utf-8');

try {

    $query = trim($_GET['q'] ?? '');

    if (mb_strlen($query) > 100) {
        $query = mb_substr($query, 0, 100);
    }

    if ($query === '') {
        echo json_encode([
            'success' => true,
            'posts' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $postModel = new Post($pdo);
    $posts = $postModel->searchPublished($query, 50);

    $results = [];

    foreach ($posts as $post) {
        $results[] = [
            'id' => (int) $post['id'],
            'title' => $post['title'],
            'content' => $post['content'],
            'username' => $post['username'],
            'category_name' => $post['category_name'] ?? '',
            'category_slug' => $post['category_slug'] ?? '',
            'created_at' => $post['created_at'],
            'link_url' => $post['link_url'] ?? null
        ];
    }

    echo json_encode([
        'success' => true,
        'posts' => $results
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Search failed.'
    ], JSON_UNESCAPED_UNICODE);
}
