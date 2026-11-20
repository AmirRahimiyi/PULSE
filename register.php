
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/controllers/AuthController.php';

/* =====================================
   SESSION & CSRF
===================================== */

Session::start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =====================================
   REGISTRATION
===================================== */

$auth = new AuthController($pdo);

$errors = [];
$success = false;

$username = '';
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

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        $result = $auth->register($_POST);

        if (!empty($result['success'])) {

            $success = true;

            // Generate a new CSRF token after successful registration
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        } else {

            $errors[] = $result['message'] ?? 'Registration failed. Please try again.';

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | PULSE</title>

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

            <span class="auth-label">JOIN THE COMMUNITY</span>

            <h1>Create your account</h1>

            <p>
                Discover technology, share ideas and connect with people.
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

        <?php if ($success): ?>

            <div class="message success-message">

                <h3>Account created successfully!</h3>

                <p>
                    Your PULSE account is ready.
                    You can now sign in to your account.
                </p>

                <a href="login.php" class="auth-button">
                    Sign In
                </a>

            </div>

        <?php else: ?>

            <form method="POST" action="register.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-group">

                    <label for="username">Username</label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Choose a username"
                        value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                        minlength="3"
                        maxlength="30"
                        autocomplete="username"
                        required
                    >

                </div>

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
                        placeholder="At least 8 characters"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="confirm_password">Confirm Password</label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Enter your password again"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <button type="submit" class="auth-button">

                    Create Account

                    <span>→</span>

                </button>

            </form>

        <?php endif; ?>

        <div class="auth-footer">

            <p>
                Already have an account?
                <a href="login.php">Sign In</a>
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
