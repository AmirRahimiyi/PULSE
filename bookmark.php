
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/controllers/BookmarkController.php';

Session::start();

header('Content-Type: application/json; charset=utf-8');

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Only POST requests are allowed.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}

// User must be logged in.
if (!Session::isLoggedIn()) {
    sendResponse([
        'success' => false,
        'message' => 'Please log in to save posts.'
    ], 401);
}

// Validate CSRF token.
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

// Validate post ID.
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

try {
    $controller = new BookmarkController($pdo);

    $result = $controller->toggle(
        (int) $postId,
        Session::userId()
    );

    sendResponse($result);

} catch (Throwable $e) {

    error_log('Bookmark endpoint error: ' . $e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'Something went wrong.'
    ], 500);
}
