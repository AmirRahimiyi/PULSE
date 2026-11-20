
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/controllers/CommentController.php';

Session::start();

header('Content-Type: application/json; charset=utf-8');

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// Only POST requests are allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}

// Authentication
if (!Session::isLoggedIn()) {
    sendResponse([
        'success' => false,
        'message' => 'Please log in to comment.'
    ], 401);
}

// CSRF validation
$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    sendResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 403);
}

// Validate post ID
$postId = filter_var(
    $_POST['post_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$postId || $postId < 1) {
    sendResponse([
        'success' => false,
        'message' => 'Invalid post ID.'
    ], 400);
}

// Validate comment content
$content = $_POST['content'] ?? '';

if (!is_string($content)) {
    sendResponse([
        'success' => false,
        'message' => 'Invalid comment content.'
    ], 400);
}

// Validate optional parent ID
$parentId = null;

if (
    isset($_POST['parent_id']) &&
    $_POST['parent_id'] !== ''
) {
    $parentId = filter_var(
        $_POST['parent_id'],
        FILTER_VALIDATE_INT
    );

    if (!$parentId || $parentId < 1) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid parent comment ID.'
        ], 400);
    }
}

try {

    $controller = new CommentController($pdo);

    $result = $controller->create(
        (int) $postId,
        Session::userId(),
        $content,
        $parentId !== null ? (int) $parentId : null
    );

    sendResponse(
        $result,
        $result['success'] ? 200 : 422
    );

} catch (Throwable $e) {

    error_log(
        'Comment endpoint error: ' . $e->getMessage()
    );

    sendResponse([
        'success' => false,
        'message' => 'Something went wrong.'
    ], 500);
}
