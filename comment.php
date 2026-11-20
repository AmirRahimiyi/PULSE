
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';

require_once __DIR__ . '/app/controllers/CommentController.php';
require_once __DIR__ . '/app/models/User.php';

function sendResponse(int $status, array $data): void
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

    /* =====================================
       REQUEST METHOD
    ====================================== */

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, [
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
    }

    /* =====================================
       AUTHENTICATION
    ====================================== */

    if (!Session::isLoggedIn()) {
        sendResponse(401, [
            'success' => false,
            'message' => 'Please log in first.'
        ]);
    }

    $userId = (int) Session::userId();

    /* =====================================
       CSRF VALIDATION
    ====================================== */

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        sendResponse(403, [
            'success' => false,
            'message' => 'Invalid security token. Refresh the page.'
        ]);
    }

    /* =====================================
       INPUT VALIDATION
    ====================================== */

    $postId = filter_input(
        INPUT_POST,
        'post_id',
        FILTER_VALIDATE_INT
    );

    $content = trim($_POST['content'] ?? '');

    $parentId = (
        isset($_POST['parent_id']) &&
        $_POST['parent_id'] !== ''
    )
        ? filter_var(
            $_POST['parent_id'],
            FILTER_VALIDATE_INT
        )
        : null;

    if (!$postId || $postId < 1) {
        sendResponse(422, [
            'success' => false,
            'message' => 'Invalid post.'
        ]);
    }

    if (!is_string($content) || $content === '') {
        sendResponse(422, [
            'success' => false,
            'message' => 'Comment cannot be empty.'
        ]);
    }

    if (mb_strlen($content, 'UTF-8') > 2000) {
        sendResponse(422, [
            'success' => false,
            'message' => 'Comment cannot exceed 2000 characters.'
        ]);
    }

    if (
        $parentId !== null &&
        (!$parentId || $parentId < 1)
    ) {
        sendResponse(422, [
            'success' => false,
            'message' => 'Invalid parent comment.'
        ]);
    }

    /* =====================================
       VERIFY USER
    ====================================== */

    $userModel = new User($pdo);

    $currentUser = $userModel->findById($userId);

    if (
        !$currentUser ||
        (
            isset($currentUser['status']) &&
            $currentUser['status'] !== 'active'
        )
    ) {
        sendResponse(403, [
            'success' => false,
            'message' => 'Your account is not active.'
        ]);
    }

    /* =====================================
       CREATE COMMENT
    ====================================== */

    $controller = new CommentController($pdo);

    $result = $controller->create(
        (int) $postId,
        $userId,
        $content,
        $parentId === null
            ? null
            : (int) $parentId
    );

    if (empty($result['success'])) {
        sendResponse(422, $result);
    }

    /* =====================================
       RESPONSE WITH USER PROFILE
    ====================================== */

    $result['username'] = $currentUser['username'] ?? '';
    $result['full_name'] = $currentUser['full_name'] ?? '';
    $result['avatar'] = $currentUser['avatar'] ?? '';
    $result['user_id'] = $userId;

    sendResponse(200, $result);

} catch (Throwable $e) {

    error_log(
        'PULSE Comment Error: ' . $e->getMessage()
    );

    sendResponse(500, [
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}
