
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/controllers/CommentController.php';

function sendDeleteResponse(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {
    Session::start();

    // Only POST requests are allowed.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendDeleteResponse(405, [
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
    }

    // Authentication.
    if (!Session::isLoggedIn()) {
        sendDeleteResponse(401, [
            'success' => false,
            'message' => 'Please log in first.'
        ]);
    }

    // CSRF protection.
    $csrfToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !is_string($sessionToken) ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $csrfToken)
    ) {
        sendDeleteResponse(403, [
            'success' => false,
            'message' => 'Invalid security token. Refresh the page.'
        ]);
    }

    // Validate comment ID.
    $commentId = filter_var(
        $_POST['comment_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($commentId === false || $commentId === null || $commentId < 1) {
        sendDeleteResponse(422, [
            'success' => false,
            'message' => 'Invalid comment ID.'
        ]);
    }

    // Delete comment through controller.
    $controller = new CommentController($pdo);

    $result = $controller->delete(
        (int) $commentId,
        (int) Session::userId()
    );

    sendDeleteResponse(
        !empty($result['success']) ? 200 : 422,
        $result
    );

} catch (Throwable $e) {

    error_log(
        'PULSE Delete Comment Error: ' . $e->getMessage()
    );

    sendDeleteResponse(500, [
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}
