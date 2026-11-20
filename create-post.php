
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/controllers/PostController.php';

/* =====================================
   SESSION & AUTHENTICATION
===================================== */

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verify that the account is still active
$userQuery = $pdo->prepare("
    SELECT id, username, status
    FROM users
    WHERE id = :id
    LIMIT 1
");

$userQuery->execute([
    ':id' => Session::userId()
]);

$currentUser = $userQuery->fetch();

if (!$currentUser || $currentUser['status'] !== 'active') {
    Session::destroy();
    header('Location: login.php');
    exit;
}

/* =====================================
   CSRF PROTECTION
===================================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =====================================
   LOAD CATEGORIES
===================================== */

$categoryQuery = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
");

$categories = $categoryQuery->fetchAll();

/* =====================================
   POST CREATION
===================================== */

$postController = new PostController($pdo);

$errors = [];
$success = false;
$publishedPostId = null;

$title = '';
$content = '';
$categoryId = '';
$linkUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $linkUrl = trim($_POST['link_url'] ?? '');

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $submittedToken)
    ) {

        $errors[] = 'Invalid request. Please refresh the page and try again.';

    } else {

        $result = $postController->create(
            $_POST,
            (int) $currentUser['id']
        );

        if (!empty($result['success'])) {

            $success = true;
            $publishedPostId = $result['post_id'];

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $title = '';
            $content = '';
            $categoryId = '';
            $linkUrl = '';

        } else {

            $errors[] = $result['message'] ?? 'Unable to publish your post.';

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Post | PULSE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/create-post.css">

</head>

<body>

<div class="create-page">

    <!-- HEADER -->

    <header class="create-header">

        <a href="index.php" class="brand">
            <span class="brand-icon">P</span>
            <span>PULSE</span>
        </a>

        <div class="header-user">

            <span class="user-avatar">
                <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
            </span>

            <span class="username">
                <?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?>
            </span>

            <a href="index.php" class="back-link">
                Back to Home
            </a>

        </div>

    </header>


    <!-- MAIN CONTENT -->

    <main class="create-main">

        <div class="create-heading">

            <span class="eyebrow">COMMUNITY CONTRIBUTION</span>

            <h1>Create a post</h1>

            <p>
                Share your thoughts, discoveries and ideas with the PULSE community.
            </p>

        </div>


        <!-- ERRORS -->

        <?php if (!empty($errors)): ?>

            <div class="form-message error-message">

                <?php foreach ($errors as $error): ?>

                    <p>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </p>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- SUCCESS -->

        <?php if ($success): ?>

            <div class="form-message success-message">

                <h3>Post published successfully!</h3>

                <p>
                    Your post is now part of the PULSE community.
                </p>

                <a href="index.php" class="success-link">
                    Back to Home →
                </a>

            </div>

        <?php endif; ?>


        <!-- CREATE FORM -->

        <section class="create-card">

            <div class="card-heading">

                <div>
                    <h2>Post details</h2>
                    <p>Fill in the information below to publish your post.</p>
                </div>

                <span class="post-indicator">
                    NEW POST
                </span>

            </div>


            <form method="POST" action="create-post.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >


                <!-- TITLE -->

                <div class="form-group">

                    <label for="title">Post title</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        placeholder="Give your post a clear title..."
                        value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="180"
                        required
                    >

                    <span class="field-hint">
                        Maximum 180 characters.
                    </span>

                </div>


                <!-- CATEGORY -->

                <div class="form-group">

                    <label for="category_id">Category</label>

                    <select
                        id="category_id"
                        name="category_id"
                        required
                    >

                        <option value="">Select a category</option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= (string) $categoryId === (string) $category['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CONTENT -->

                <div class="form-group">

                    <label for="content">Your content</label>

                    <textarea
                        id="content"
                        name="content"
                        rows="9"
                        placeholder="What's on your mind? Share something interesting..."
                        required
                    ><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <span class="field-hint">
                        Share useful ideas, ask questions or start a discussion.
                    </span>

                </div>


                <!-- OPTIONAL LINK -->

                <div class="form-group">

                    <label for="link_url">
                        External link
                        <span class="optional-label">OPTIONAL</span>
                    </label>

                    <input
                        type="url"
                        id="link_url"
                        name="link_url"
                        placeholder="https://example.com"
                        value="<?= htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') ?>"
                    >

                </div>


                <!-- ACTIONS -->

                <div class="form-actions">

                    <a href="index.php" class="cancel-button">
                        Cancel
                    </a>

                    <button type="submit" class="publish-button">
                        Publish Post
                        <span>→</span>
                    </button>

                </div>

            </form>

        </section>


        <!-- FOOTER NOTE -->

        <div class="create-note">
            <span>✦</span>
            Be respectful. Share knowledge. Build the community.
        </div>

    </main>

</div>

</body>
</html>
