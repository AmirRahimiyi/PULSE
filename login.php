
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/controllers/AuthController.php';

/* =====================================
   SESSION & CSRF
===================================== */

Session::start();

if (Session::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =====================================
   LOGIN
===================================== */

$auth = new AuthController($pdo);

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $submittedToken)
    ) {

        $errors[] = 'Invalid request. Please refresh the page and try again.';

    } else {

        $email = trim($_POST['email'] ?? '');

        $result = $auth->login($_POST);

        if (!empty($result['success'])) {

            header('Location: index.php');
            exit;

        } else {

            $errors[] = $result['message'] ?? 'Login failed. Please try again.';

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In | PULSE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/auth.css">

</head>

<body>

<div class="auth-container">

    <div class="auth-brand">

        <a href="index.php" class="brand">
            <span class="brand-icon">P</span>
            <span>PULSE</span>
        </a>

    </div>

    <main class="auth-card">

        <div class="auth-heading">

            <span class="auth-label">WELCOME BACK</span>

            <h1>Sign in to PULSE</h1>

            <p>
                Continue discovering technology and connecting with the community.
            </p>

        </div>

        <?php if (!empty($errors)): ?>

            <div class="message error-message">

                <?php foreach ($errors as $error): ?>

                    <p>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </p>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="form-group">

                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="email"
                    required
                >

            </div>

            <div class="form-group">

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <button type="submit" class="auth-button">

                Sign In

                <span>→</span>

            </button>

        </form>

        <div class="auth-footer">

            <p>
                Don't have an account?
                <a href="register.php">Create Account</a>
            </p>

        </div>

    </main>

    <div class="auth-bottom">

        <span>© <?= date('Y') ?> PULSE</span>

        <span>Discover. Participate. Connect.</span>

    </div>

</div>

</body>
</html>
