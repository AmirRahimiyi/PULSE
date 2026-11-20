
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/models/User.php';

Session::start();

$userModel = new User($pdo);

$currentUserId = Session::userId();
$username = trim($_GET['u'] ?? '');

if ($username === '' && $currentUserId) {
    $currentUser = $userModel->findById((int) $currentUserId);
    $username = $currentUser['username'] ?? '';
}

$profile = $username !== ''
    ? $userModel->findPublicByUsername($username)
    : null;

if (!$profile) {
    http_response_code(404);
    $pageTitle = 'Profile Not Found | PULSE';
}

$isOwnProfile = $profile &&
    $currentUserId &&
    (int) $currentUserId === (int) $profile['id'];

$posts = [];
$postCount = 0;

if ($profile) {
    $postCount = $userModel->getPostCount((int) $profile['id']);

    $posts = $userModel->getPublishedPostsByUser(
        (int) $profile['id'],
        50
    );
}

function profileEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function profileInitial(string $name): string
{
    return strtoupper(substr($name, 0, 1));
}

$pageTitle = $profile
    ? profileEscape($profile['username']) . ' | PULSE'
    : 'Profile Not Found | PULSE';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $pageTitle ?></title>

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
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: Inter, Arial, sans-serif;
            min-height: 100vh;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font: inherit;
        }

        .profile-page {
            width: min(100% - 32px, 1000px);
            margin: 40px auto;
        }

        .profile-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-logo {
            font-size: 25px;
            font-weight: 900;
            letter-spacing: 3px;
        }

        .profile-logo span {
            color: var(--primary-soft);
        }

        .back-button {
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            padding: 10px 16px;
            border-radius: 12px;
            transition: .2s;
        }

        .back-button:hover {
            border-color: var(--primary);
            background: var(--surface-soft);
        }

        .profile-header {
            background:
                radial-gradient(
                    circle at 80% 0%,
                    rgba(139, 92, 246, .16),
                    transparent 40%
                ),
                var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 35px;
            margin-bottom: 25px;
        }

        .profile-main {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .profile-avatar {
            width: 115px;
            height: 115px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            background: linear-gradient(
                135deg,
                #8B5CF6,
                #4C1D95
            );
            border: 3px solid rgba(167, 139, 250, .4);
            font-size: 42px;
            font-weight: 800;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
            min-width: 0;
        }

        .profile-name {
            font-size: 27px;
            font-weight: 800;
            margin-bottom: 6px;
            overflow-wrap: anywhere;
        }

        .profile-username {
            color: var(--muted);
            margin-bottom: 15px;
        }

        .profile-bio {
            color: #C7C9D2;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 25px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }

        .profile-stat {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .profile-stat strong {
            font-size: 21px;
        }

        .profile-stat span {
            font-size: 13px;
            color: var(--muted);
        }

        .profile-actions {
            display: flex;
            gap: 10px;
            margin-top: 22px;
        }

        .profile-button {
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface-soft);
            padding: 10px 16px;
            color: var(--text);
            cursor: pointer;
        }

        .profile-button.primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .profile-button:hover {
            opacity: .88;
        }

        .profile-section-title {
            font-size: 20px;
            margin: 30px 0 18px;
        }

        .profile-posts {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .profile-post {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px;
            transition: border-color .2s;
        }

        .profile-post:hover {
            border-color: rgba(139, 92, 246, .4);
        }

        .profile-post-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .profile-post-category {
            color: var(--primary-soft);
            background: rgba(139, 92, 246, .12);
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .profile-post-date {
            font-size: 12px;
            color: var(--muted);
        }

        .profile-post h3 {
            font-size: 18px;
            margin-bottom: 12px;
            overflow-wrap: anywhere;
        }

        .profile-post p {
            color: #B6BAC6;
            line-height: 1.8;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .profile-post-link {
            display: inline-block;
            margin-top: 15px;
            color: var(--primary-soft);
        }

        .profile-empty {
            background: var(--surface);
            border: 1px dashed var(--border);
            border-radius: 18px;
            padding: 50px 20px;
            text-align: center;
            color: var(--muted);
        }

        .profile-empty h3 {
            color: var(--text);
            margin-bottom: 10px;
        }

        .not-found {
            max-width: 500px;
            margin: 100px auto;
            text-align: center;
            padding: 30px;
        }

        .not-found h1 {
            font-size: 35px;
            margin-bottom: 15px;
        }

        .not-found p {
            color: var(--muted);
            margin-bottom: 25px;
        }

        @media (max-width: 600px) {

            .profile-page {
                margin: 20px auto;
            }

            .profile-header {
                padding: 22px;
            }

            .profile-main {
                flex-direction: column;
                align-items: flex-start;
                gap: 18px;
            }

            .profile-avatar {
                width: 90px;
                height: 90px;
                font-size: 34px;
            }

            .profile-name {
                font-size: 23px;
            }

            .profile-stats {
                gap: 22px;
            }

            .profile-post {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<?php if (!$profile): ?>

    <div class="not-found">
        <h1>404</h1>
        <p>This PULSE profile doesn't exist or is unavailable.</p>

        <a href="index.php" class="back-button">
            Back to PULSE
        </a>
    </div>

<?php else: ?>

    <main class="profile-page">

        <header class="profile-topbar">

            <a href="index.php" class="profile-logo">
                PULSE<span>.</span>
            </a>

            <a href="index.php" class="back-button">
                ← Back to Feed
            </a>

        </header>

        <section class="profile-header">

            <div class="profile-main">

                <div class="profile-avatar">

                    <?php if (!empty($profile['avatar'])): ?>

                        <img
                            src="<?= profileEscape($profile['avatar']) ?>"
                            alt="<?= profileEscape($profile['username']) ?>"
                        >

                    <?php else: ?>

                        <?= profileEscape(
                            profileInitial(
                                $profile['full_name']
                                ?: $profile['username']
                            )
                        ) ?>

                    <?php endif; ?>

                </div>

                <div class="profile-info">

                    <h1 class="profile-name">
                        <?= profileEscape(
                            $profile['full_name']
                            ?: $profile['username']
                        ) ?>
                    </h1>

                    <div class="profile-username">
                        @<?= profileEscape($profile['username']) ?>
                    </div>

                    <div class="profile-bio">
                        <?= !empty($profile['bio'])
                            ? nl2br(profileEscape($profile['bio']))
                            : 'No bio added yet.' ?>
                    </div>

                </div>

            </div>

            <div class="profile-stats">

                <div class="profile-stat">
                    <strong><?= $postCount ?></strong>
                    <span>Posts</span>
                </div>

                <div class="profile-stat">
                    <strong>
                        <?= date(
                            'Y',
                            strtotime($profile['created_at'])
                        ) ?>
                    </strong>
                    <span>Joined</span>
                </div>

            </div>

            <?php if ($isOwnProfile): ?>

                <div class="profile-actions">

                    <a
                        href="edit-profile.php"
                        class="profile-button primary"
                    >
                        Edit Profile
                    </a>

                </div>

            <?php endif; ?>

        </section>

        <section>

            <h2 class="profile-section-title">
                Posts
            </h2>

            <div class="profile-posts">

                <?php if (empty($posts)): ?>

                    <div class="profile-empty">

                        <h3>No posts yet</h3>

                        <p>
                            <?= $isOwnProfile
                                ? 'Your posts will appear here when you publish something.'
                                : 'This user has not published any posts yet.' ?>
                        </p>

                        <?php if ($isOwnProfile): ?>

                            <a
                                href="index.php"
                                class="profile-button primary"
                                style="display:inline-block;margin-top:20px"
                            >
                                Create Your First Post
                            </a>

                        <?php endif; ?>

                    </div>

                <?php else: ?>

                    <?php foreach ($posts as $post): ?>

                        <article class="profile-post">

                            <div class="profile-post-meta">

                                <span class="profile-post-category">
                                    <?= profileEscape(
                                        $post['category_name']
                                        ?? 'Community'
                                    ) ?>
                                </span>

                                <span class="profile-post-date">
                                    <?= !empty($post['created_at'])
                                        ? date(
                                            'M d, Y',
                                            strtotime($post['created_at'])
                                        )
                                        : '' ?>
                                </span>

                            </div>

                            <h3>
                                <?= profileEscape(
                                    $post['title'] ?? 'Untitled Post'
                                ) ?>
                            </h3>

                            <p>
                                <?= profileEscape(
                                    $post['content'] ?? ''
                                ) ?>
                            </p>

                            <?php if (!empty($post['link_url'])): ?>

                                <a
                                    class="profile-post-link"
                                    href="<?= profileEscape($post['link_url']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Visit Link ↗
                                </a>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>

    </main>

<?php endif; ?>

</body>
</html>
