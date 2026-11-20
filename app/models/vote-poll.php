
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/models/Poll.php';
require_once __DIR__ . '/app/models/User.php';

function sendPollResponse(int $status, array $data): void
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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendPollResponse(405, [
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
    }

    if (!Session::isLoggedIn()) {
        sendPollResponse(401, [
            'success' => false,
            'message' => 'Please log in to vote.'
        ]);
    }

    $userId = (int) Session::userId();

    // CSRF validation
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        sendPollResponse(403, [
            'success' => false,
            'message' => 'Invalid security token. Refresh the page.'
        ]);
    }

    // Validate poll and option IDs
    $pollId = filter_var(
        $_POST['poll_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $optionId = filter_var(
        $_POST['option_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$pollId || $pollId < 1 || !$optionId || $optionId < 1) {
        sendPollResponse(422, [
            'success' => false,
            'message' => 'Invalid poll or option.'
        ]);
    }

    // Check user account
    $userModel = new User($pdo);
    $user = $userModel->findById($userId);

    if (
        !$user ||
        (isset($user['status']) && $user['status'] !== 'active')
    ) {
        sendPollResponse(403, [
            'success' => false,
            'message' => 'Your account is not active.'
        ]);
    }

    $pollModel = new Poll($pdo);

    // Check active poll
    $poll = $pollModel->findActiveById((int) $pollId);

    if (!$poll) {
        sendPollResponse(404, [
            'success' => false,
            'message' => 'This poll is unavailable.'
        ]);
    }

    // Prevent duplicate voting
    if ($pollModel->hasUserVoted((int) $pollId, $userId)) {
        sendPollResponse(409, [
            'success' => false,
            'message' => 'You have already voted in this poll.'
        ]);
    }

    // Save vote
    $success = $pollModel->vote(
        (int) $pollId,
        (int) $optionId,
        $userId
    );

    if (!$success) {
        sendPollResponse(422, [
            'success' => false,
            'message' => 'Unable to submit your vote.'
        ]);
    }

    // Get updated results
    $results = $pollModel->getResults((int) $pollId);

    sendPollResponse(200, [
        'success' => true,
        'message' => 'Your vote has been submitted.',
        'poll_id' => (int) $pollId,
        'results' => $results
    ]);

} catch (Throwable $e) {

    error_log('PULSE Poll Vote Error: ' . $e->getMessage());

    sendPollResponse(500, [
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}
