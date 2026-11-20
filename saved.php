
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/models/User.php';
require_once __DIR__ . '/app/models/Bookmark.php';

Session::start();

// Authentication
if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Current user
$userModel = new User($pdo);
$currentUser = $userModel->findById(Session::userId());

if (!$currentUser || $currentUser['status'] !== 'active') {
    Session::destroy();
    header('Location: login.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// Get saved posts
$bookmarkModel = new Bookmark($pdo);
$savedPosts = $bookmarkModel->getByUser(
    Session::userId()
);

function escape(string|null $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Saved Posts — PULSE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0D0F14;
            color: #F5F5F7;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .topbar {
            height: 75px;
            border-bottom: 1px solid #292D38;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 7%;
        }

        .logo {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #A78BFA;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .username {
            color: #9297A5;
            font-size: 13px;
        }

        .logout-btn {
            background: #171A22;
            border: 1px solid #292D38;
            color: #F5F5F7;
            padding: 9px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
        }

        .logout-btn:hover {
            border-color: #8B5CF6;
        }

        .container {
            max-width: 950px;
            margin: 55px auto;
            padding: 0 22px;
        }

        .back-link {
            display: inline-block;
            color: #A78BFA;
            font-size: 13px;
            margin-bottom: 28px;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #9297A5;
            font-size: 14px;
            line-height: 1.7;
        }

        .saved-count {
            display: inline-block;
            background: #211B32;
            color: #A78BFA;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 16px;
        }

        .post-card {
            background: #171A22;
            border: 1px solid #292D38;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 18px;
            transition: border-color 0.2s ease;
        }

        .post-card:hover {
            border-color: #514078;
        }

        .post-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 17px;
            font-size: 12px;
            color: #9297A5;
        }

        .category {
            background: #211B32;
            color: #A78BFA;
            padding: 5px 9px;
            border-radius: 6px;
        }

        .post-card h2 {
            font-size: 19px;
            margin-bottom: 13px;
            line-height: 1.5;
        }

        .post-content {
            color: #B4B7C2;
            font-size: 14px;
            line-height: 1.9;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .post-footer {
            border-top: 1px solid #292D38;
            margin-top: 20px;
            padding-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 12px;
            color: #9297A5;
        }

        .empty-state {
            background: #171A22;
            border: 1px dashed #393344;
            border-radius: 14px;
            padding: 65px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 38px;
            margin-bottom: 18px;
            color: #A78BFA;
        }

        .empty-state h2 {
            font-size: 19px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #9297A5;
            font-size: 13px;
            line-height: 1.8;
            margin-bottom: 22px;
        }

        .explore-btn {
            display: inline-block;
            background: #8B5CF6;
            color: white;
            padding: 11px 19px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s ease;
        }

        .explore-btn:hover {
            background: #7545DD;
        }
.remove-bookmark-btn {
    background: #211B32;
    border: 1px solid #393044;
    color: #A78BFA;
    padding: 9px 13px;
    border-radius: 7px;
    font-family: inherit;
    font-size: 12px;
    cursor: pointer;
    transition: 0.2s ease;
}

.remove-bookmark-btn:hover {
    background: #352044;
    border-color: #8B5CF6;
}

.remove-bookmark-btn:disabled {
    opacity: 0.5;
    cursor: wait;
}
        @media (max-width: 600px) {
            .topbar {
                padding: 0 18px;
            }

            .username {
                display: none;
            }

            .container {
                margin: 35px auto;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .post-card {
                padding: 19px;
            }
        }
    </style>
</head>

<body>

    <header class="topbar">

        <a href="index.php" class="logo">PULSE</a>

        <div class="topbar-right">

            <span class="username">
                @<?= escape($currentUser['username']) ?>
            </span>

            <form action="logout.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($csrfToken) ?>"
                >

                <button type="submit" class="logout-btn">
                    Sign out
                </button>

            </form>

        </div>

    </header>

    <main class="container">

        <a href="index.php" class="back-link">
            ← Back to Home
        </a>

        <section class="page-header">

            <h1>Saved Posts</h1>

            <p>
                Your collection of interesting ideas,
                discussions and discoveries.
            </p>

            <span class="saved-count">
                <?= count($savedPosts) ?> Saved Posts
            </span>

        </section>

        <?php if (empty($savedPosts)): ?>

            <div class="empty-state">

                <div class="empty-icon">♧</div>

                <h2>No saved posts yet</h2>

                <p>
                    Posts you save will appear here.
                    Explore PULSE and find something interesting.
                </p>

                <a href="index.php" class="explore-btn">
                    Explore Posts
                </a>

            </div>

        <?php else: ?>

            <section class="saved-posts">

                <?php foreach ($savedPosts as $post): ?>

                    <article class="post-card">

                        <div class="post-meta">

                            <span class="category">
                                <?= escape($post['category_name'] ?? 'General') ?>
                            </span>

                            <span>
                                By @<?= escape($post['username']) ?>
                            </span>

                        </div>

                        <h2>
                            <?= escape($post['title']) ?>
                        </h2>

                        <div class="post-content">
                            <?= nl2br(escape($post['content'])) ?>
                        </div>

                       <div class="post-footer">

    <span>
        Published:
        <?= escape($post['created_at']) ?>
    </span>

    <span>
        Saved:
        <?= escape($post['bookmarked_at']) ?>
    </span>

    <button
        type="button"
        class="remove-bookmark-btn"
        data-post-id="<?= (int) $post['id'] ?>"
    >
        Remove Saved Post
    </button>

</div>

                    </article>

                <?php endforeach; ?>

            </section>

        <?php endif; ?>

    </main>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const removeButtons = document.querySelectorAll(
        '.remove-bookmark-btn'
    );

    removeButtons.forEach(function (button) {

        button.addEventListener('click', async function () {

            if (button.disabled) return;

            const formData = new FormData();

            formData.append('post_id', button.dataset.postId);

            formData.append(
                'csrf_token',
                <?= json_encode($csrfToken) ?>
            );

            button.disabled = true;
            button.textContent = 'Removing...';

            try {

                const response = await fetch('bookmark.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(
                        result.message || 'Unable to remove bookmark.'
                    );
                }

                if (result.bookmarked !== false) {
                    throw new Error('Unexpected server response.');
                }

                const postCard = button.closest('.post-card');

                postCard.remove();

                const remainingPosts = document.querySelectorAll(
                    '.post-card'
                ).length;

                if (remainingPosts === 0) {
                    window.location.reload();
                    return;
                }

                const countElement = document.querySelector(
                    '.saved-count'
                );

                countElement.textContent =
                    remainingPosts + ' Saved Posts';

            } catch (error) {

                console.error('Remove bookmark error:', error);

                alert(error.message);

                button.disabled = false;
                button.textContent = 'Remove Saved Post';

            }

        });

    });

});
</script>
</body>
</html>
