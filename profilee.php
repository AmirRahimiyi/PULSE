```php
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';

Session::start();

function profileEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$username = trim($_GET['username'] ?? '');

if ($username === '') {
    http_response_code(404);
    exit('User not found.');
}

/* Get user */
$stmt = $pdo->prepare("
    SELECT id, username, full_name, bio, avatar, role, created_at
    FROM users
    WHERE username = :username
      AND status = 'active'
    LIMIT 1
");

$stmt->execute(['username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

/* Get published posts */
$postStmt = $pdo->prepare("
    SELECT 
        p.id,
        p.title,
        p.content,
        p.created_at,
        p.link_url,
        c.name AS category_name,
        c.slug AS category_slug
    FROM posts p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.user_id = :user_id
      AND p.status = 'published'
    ORDER BY p.created_at DESC
");

$postStmt->execute([
    'user_id' => $user['id']
]);

$userPosts = $postStmt->fetchAll(PDO::FETCH_ASSOC);

$postCount = count($userPosts);

$displayName = $user['full_name'] ?: $user['username'];

$avatar = !empty($user['avatar'])
    ? $user['avatar']
    : null;

$initial = mb_strtoupper(
    mb_substr($displayName, 0, 1, 'UTF-8'),
    'UTF-8'
);

$joinedDate = $user['created_at']
    ? date('M Y', strtotime($user['created_at']))
    : 'Unknown';

$pageTitle = $displayName . ' — PULSE';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= profileEscape($pageTitle) ?></title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body {
            background: #0D0F14;
            color: #F5F5F7;
        }

        .profile-page {
            max-width: 1000px;
            margin: 50px auto;
            padding: 0 24px 60px;
        }

        .profile-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #9297A5;
            text-decoration: none;
            margin-bottom: 25px;
            transition: .2s;
        }

        .profile-back:hover {
            color: #A78BFA;
        }

        .profile-card {
            background: #171A22;
            border: 1px solid #292D38;
            border-radius: 22px;
            padding: 35px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .profile-avatar {
            width: 105px;
            height: 105px;
            flex-shrink: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, #8B5CF6, #5B21B6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            font-weight: 800;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 8px;
        }

        .profile-username {
            color: #9297A5;
            font-size: 14px;
        }

        .profile-bio {
            color: #C3C6D0;
            line-height: 1.8;
            margin-top: 24px;
            max-width: 650px;
            overflow-wrap: anywhere;
        }

        .profile-stats {
            display: flex;
            gap: 35px;
            margin-top: 25px;
            padding-top: 22px;
            border-top: 1px solid #292D38;
        }

        .profile-stat strong {
            display: block;
            font-size: 20px;
            margin-bottom: 5px;
        }

        .profile-stat span {
            color: #9297A5;
            font-size: 13px;
        }

        .profile-posts-title {
            margin: 35px 0 20px;
            font-size: 21px;
        }

        .profile-posts {
            display: grid;
            gap: 18px;
        }

        .profile-post {
            background: #171A22;
            border: 1px solid #292D38;
            border-radius: 16px;
            padding: 23px;
            transition: border-color .2s, transform .2s;
        }

        .profile-post:hover {
            border-color: #6D4BC3;
            transform: translateY(-2px);
        }

        .profile-post-category {
            color: #A78BFA;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .profile-post h3 {
            font-size: 18px;
            margin: 0 0 12px;
            overflow-wrap: anywhere;
        }

        .profile-post-content {
            color: #B4B8C4;
            font-size: 14px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .profile-post-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            color: #9297A5;
            font-size: 12px;
            flex-wrap: wrap;
        }

        .profile-post-link {
            color: #A78BFA;
            text-decoration: none;
        }

        .profile-post-link:hover {
            color: #C4B5FD;
        }

        .profile-empty {
            background: #171A22;
            border: 1px solid #292D38;
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            color: #9297A5;
        }

        @media (max-width: 600px) {

            .profile-page {
                margin-top: 25px;
                padding: 0 14px 40px;
            }

            .profile-card {
                padding: 22px;
            }

            .profile-header {
                align-items: flex-start;
                gap: 16px;
            }

            .profile-avatar {
                width: 75px;
                height: 75px;
                font-size: 28px;
            }

            .profile-name {
                font-size: 22px;
            }

            .profile-stats {
                gap: 24px;
            }

            .profile-post {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<main class="profile-page">

    <a href="index.php" class="profile-back">
        ← Back to PULSE
    </a>

    <section class="profile-card">

        <div class="profile-header">

            <div class="profile-avatar">

                <?php if ($avatar): ?>

                    <img
                        src="<?= profileEscape($avatar) ?>"
                        alt="<?= profileEscape($displayName) ?>"
                    >

                <?php else: ?>

                    <?= profileEscape($initial) ?>

                <?php endif; ?>

            </div>

            <div>

                <h1 class="profile-name">
                    <?= profileEscape($displayName) ?>
                </h1>

                <div class="profile-username">
                    @<?= profileEscape($user['username']) ?>
                </div>

            </div>

        </div>

        <?php if (!empty($user['bio'])): ?>

            <div class="profile-bio">
                <?= nl2br(profileEscape($user['bio'])) ?>
            </div>

        <?php endif; ?>

        <div class="profile-stats">

            <div class="profile-stat">
                <strong><?= $postCount ?></strong>
                <span>Posts</span>
            </div>

            <div class="profile-stat">
                <strong><?= profileEscape($joinedDate) ?></strong>
                <span>Joined</span>
            </div>

        </div>

    </section>

    <h2 class="profile-posts-title">
        Posts by <?= profileEscape($displayName) ?>
    </h2>

    <section class="profile-posts">

        <?php if (empty($userPosts)): ?>

            <div class="profile-empty">
                This user hasn't published any posts yet.
            </div>

        <?php else: ?>

            <?php foreach ($userPosts as $post): ?>

                <article class="profile-post">

                    <div class="profile-post-category">
                        <?= profileEscape($post['category_name'] ?? 'General') ?>
                    </div>

                    <h3>
                        <?= profileEscape($post['title']) ?>
                    </h3>

                    <div class="profile-post-content">
                        <?= nl2br(profileEscape($post['content'])) ?>
                    </div>

                    <div class="profile-post-footer">

                        <span>
                            <?= profileEscape(date('M d, Y', strtotime($post['created_at']))) ?>
                        </span>

                        <?php if (!empty($post['link_url'])): ?>

                            <a
                                class="profile-post-link"
                                href="<?= profileEscape($post['link_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open Link ↗
                            </a>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
```