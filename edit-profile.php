
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/models/User.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = (int) Session::userId();
$userModel = new User($pdo);
$user = $userModel->findById($userId);

if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

$error = '';

if (empty($_SESSION['pulse_profile_csrf'])) {
    $_SESSION['pulse_profile_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['pulse_profile_csrf'];

function editProfileEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $submittedToken)) {

        $error = 'Invalid security token. Please refresh the page.';

    } else {

        $fullName = trim($_POST['full_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if (mb_strlen($fullName) > 100) {

            $error = 'Full name cannot exceed 100 characters.';

        } elseif (mb_strlen($bio) > 250) {

            $error = 'Bio cannot exceed 250 characters.';

        } else {

            $avatarPath = null;

            if (
                isset($_FILES['avatar']) &&
                $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                $file = $_FILES['avatar'];

                if ($file['error'] !== UPLOAD_ERR_OK) {

                    $error = 'Image upload failed.';

                } elseif ($file['size'] > 2 * 1024 * 1024) {

                    $error = 'Image must be smaller than 2MB.';

                } else {

                    $allowedMimeTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($file['tmp_name']);

                    if (!isset($allowedMimeTypes[$mimeType])) {

                        $error = 'Only JPG, PNG and WEBP images are allowed.';

                    } else {

                        $imageInfo = getimagesize($file['tmp_name']);

                        if (!$imageInfo) {

                            $error = 'Invalid image file.';

                        } elseif (
                            $imageInfo[0] > 5000 ||
                            $imageInfo[1] > 5000
                        ) {

                            $error = 'Image dimensions cannot exceed 5000x5000.';

                        } else {

                            $uploadDirectory = __DIR__ . '/assets/images/avatars/';

                            if (
                                !is_dir($uploadDirectory) &&
                                !mkdir($uploadDirectory, 0755, true)
                            ) {

                                $error = 'Unable to create avatar directory.';

                            } else {

                                $extension = $allowedMimeTypes[$mimeType];

                                $fileName = bin2hex(
                                    random_bytes(16)
                                ) . '.' . $extension;

                                $destination = $uploadDirectory . $fileName;

                                if (
                                    move_uploaded_file(
                                        $file['tmp_name'],
                                        $destination
                                    )
                                ) {

                                    $avatarPath = 'assets/images/avatars/' . $fileName;

                                } else {

                                    $error = 'Unable to save image.';
                                }
                            }
                        }
                    }
                }
            }

            if ($error === '') {

                try {

                    $updated = $userModel->updateProfile(
                        $userId,
                        $fullName,
                        $bio,
                        $avatarPath
                    );

                    if ($updated) {

                        header('Location: edit-profile.php?updated=1');
                        exit;

                    } else {

                        $error = 'Unable to update profile.';
                    }

                } catch (Throwable $e) {

                    error_log('PULSE profile update error: ' . $e->getMessage());

                    $error = 'A database error occurred while updating your profile.';
                }
            }
        }
    }

    $user = $userModel->findById($userId) ?? $user;
}

$avatar = $user['avatar'] ?? '';
$fullName = $user['full_name'] ?? '';
$bio = $user['bio'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Profile | PULSE</title>

    <style>
        :root {
            --bg: #0D0F14;
            --surface: #171A22;
            --surface-soft: #1D202A;
            --primary: #8B5CF6;
            --primary-soft: #A78BFA;
            --text: #F5F5F7;
            --muted: #9297A5;
            --border: #292D38;
            --danger: #F87171;
            --success: #4ADE80;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: Inter, Arial, sans-serif;
            min-height: 100vh;
        }

        button, input, textarea {
            font: inherit;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .edit-page {
            width: min(100% - 32px, 760px);
            margin: 40px auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 25px;
            font-weight: 900;
            letter-spacing: 3px;
        }

        .logo span {
            color: var(--primary-soft);
        }

        .back-link {
            padding: 10px 15px;
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: 12px;
            color: var(--muted);
            transition: .2s;
        }

        .back-link:hover {
            color: var(--text);
            border-color: var(--primary);
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-heading p {
            color: var(--muted);
            line-height: 1.7;
        }

        .edit-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 30px;
        }

        .avatar-section {
            display: flex;
            align-items: center;
            gap: 22px;
            padding-bottom: 28px;
            margin-bottom: 28px;
            border-bottom: 1px solid var(--border);
        }

        .avatar-preview {
            width: 105px;
            height: 105px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #8B5CF6, #4C1D95);
            border: 3px solid rgba(167,139,250,.3);
            font-size: 38px;
            font-weight: 800;
        }

        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-upload h3 {
            margin-bottom: 8px;
        }

        .avatar-upload p {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .upload-button {
            display: inline-block;
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-soft);
            cursor: pointer;
            font-size: 13px;
        }

        .upload-button:hover {
            border-color: var(--primary);
        }

        #avatarInput {
            display: none;
        }

        .field {
            margin-bottom: 23px;
        }

        .field label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .field input,
        .field textarea {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #11141B;
            color: var(--text);
            outline: none;
            transition: .2s;
        }

        .field input:focus,
        .field textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139,92,246,.1);
        }

        .field textarea {
            min-height: 125px;
            resize: vertical;
            line-height: 1.7;
        }

        .field small {
            display: block;
            color: var(--muted);
            margin-top: 8px;
        }

        .readonly-field {
            opacity: .75;
        }

        .message {
            padding: 13px 15px;
            border-radius: 11px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .message.error {
            background: rgba(248,113,113,.08);
            border: 1px solid rgba(248,113,113,.25);
            color: var(--danger);
        }

        .message.success {
            background: rgba(74,222,128,.08);
            border: 1px solid rgba(74,222,128,.2);
            color: var(--success);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 10px;
        }

        .button {
            border: 1px solid var(--border);
            border-radius: 11px;
            padding: 12px 18px;
            cursor: pointer;
            background: var(--surface-soft);
            color: var(--text);
            transition: .2s;
        }

        .button:hover {
            opacity: .85;
        }

        .button.primary {
            background: var(--primary);
            border-color: var(--primary);
            font-weight: 700;
        }

        @media (max-width: 550px) {
            .edit-page {
                margin: 22px auto;
            }

            .edit-card {
                padding: 20px;
            }

            .avatar-section {
                align-items: flex-start;
                gap: 15px;
            }

            .avatar-preview {
                width: 80px;
                height: 80px;
                font-size: 30px;
            }

            .page-heading h1 {
                font-size: 24px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .form-actions .button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<main class="edit-page">

    <header class="topbar">
        <a href="index.php" class="logo">PULSE<span>.</span></a>

        <a href="profile.php?u=<?= urlencode($user['username']) ?>" class="back-link">
            ← View Profile
        </a>
    </header>

    <div class="page-heading">
        <h1>Edit Profile</h1>
        <p>Personalize your identity and how people see you on PULSE.</p>
    </div>

    <section class="edit-card">

        <?php if ($error !== ''): ?>
            <div class="message error">
                <?= editProfileEscape($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
            <div class="message success">
                Your profile has been updated successfully.
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= editProfileEscape($csrfToken) ?>"
            >

            <div class="avatar-section">

                <div class="avatar-preview" id="avatarPreview">

                    <?php if ($avatar): ?>

                        <img
                            src="<?= editProfileEscape($avatar) ?>"
                            alt="Profile avatar"
                        >

                    <?php else: ?>

                        <?= strtoupper(substr($user['username'], 0, 1)) ?>

                    <?php endif; ?>

                </div>

                <div class="avatar-upload">
                    <h3>Profile Picture</h3>
                    <p>JPG, PNG or WEBP. Maximum size: 2MB.</p>

                    <label for="avatarInput" class="upload-button">
                        Choose Image
                    </label>

                    <input
                        type="file"
                        name="avatar"
                        id="avatarInput"
                        accept="image/jpeg,image/png,image/webp"
                    >
                </div>

            </div>

            <div class="field">
                <label for="full_name">Display Name</label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    maxlength="100"
                    value="<?= editProfileEscape($fullName) ?>"
                    placeholder="Your name"
                >

                <small>Your display name is shown on your profile and posts.</small>
            </div>

            <div class="field">
                <label for="username">Username</label>

                <input
                    type="text"
                    id="username"
                    value="<?= editProfileEscape($user['username']) ?>"
                    readonly
                    class="readonly-field"
                >

                <small>Username cannot be changed right now.</small>
            </div>

            <div class="field">
                <label for="email">Email</label>

                <input
                    type="email"
                    id="email"
                    value="<?= editProfileEscape($user['email']) ?>"
                    readonly
                    class="readonly-field"
            >
            </div>

            <div class="field">
                <label for="bio">Bio</label>

                <textarea
                    id="bio"
                    name="bio"
                    maxlength="250"
                    placeholder="Tell the community a little about yourself..."
                ><?= editProfileEscape($bio) ?></textarea>

                <small>Maximum 250 characters.</small>
            </div>

            <div class="form-actions">

                <a
                    href="profile.php?u=<?= urlencode($user['username']) ?>"
                    class="button"
                >
                    Cancel
                </a>

                <button type="submit" class="button primary">
                    Save Changes
                </button>

            </div>

        </form>

    </section>

</main>

<script>
document.addEventListener("DOMContentLoaded", () => {

    const avatarInput = document.getElementById("avatarInput");
    const avatarPreview = document.getElementById("avatarPreview");

    if (!avatarInput || !avatarPreview) return;

    avatarInput.addEventListener("change", () => {

        const file = avatarInput.files[0];

        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            alert("Image must be smaller than 2MB.");
            avatarInput.value = "";
            return;
        }

        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
            alert("Please choose a JPG, PNG or WEBP image.");
            avatarInput.value = "";
            return;
        }

        const reader = new FileReader();

        reader.onload = event => {
            avatarPreview.innerHTML = "";

            const img = document.createElement("img");
            img.src = event.target.result;
            img.alt = "Avatar preview";

            avatarPreview.appendChild(img);
        };

        reader.readAsDataURL(file);
    });

});
</script>

</body>
</html>
