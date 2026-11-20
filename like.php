<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/controllers/LikeController.php';

Session::start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

if (!Session::isLoggedIn()) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Please sign in first.'
    ]);

    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token.'
    ]);

    exit;
}

$postId = filter_var(
    $_POST['post_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$postId || $postId < 1) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid post ID.'
    ]);

    exit;
}

$controller = new LikeController($pdo);

$result = $controller->toggle(
    $postId,
    Session::userId()
);

if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result);
exit;