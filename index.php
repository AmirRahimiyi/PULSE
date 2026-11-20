
<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';

require_once __DIR__ . '/app/models/User.php';
require_once __DIR__ . '/app/models/Like.php';
require_once __DIR__ . '/app/models/Bookmark.php';
require_once __DIR__ . '/app/models/Comment.php';

$pageTitle = 'PULSE — Discover. Participate. Connect.';

Session::start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$userModel = new User($pdo);
$likeModel = new Like($pdo);
$bookmarkModel = new Bookmark($pdo);
$commentModel = new Comment($pdo);

$currentUser = null;
$currentUserId = Session::isLoggedIn() ? (int) Session::userId() : 0;

if ($currentUserId > 0) {
    $currentUser = $userModel->findById($currentUserId);

    if (!$currentUser || (isset($currentUser['status']) && $currentUser['status'] !== 'active')) {
        Session::destroy();
        $currentUser = null;
        $currentUserId = 0;
        Session::start();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $csrfToken = $_SESSION['csrf_token'];
    }
}

function pulseEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pulseInitial(string $username): string
{
    return strtoupper(mb_substr($username, 0, 1, 'UTF-8'));
}

function pulseAvatar(?string $avatar, string $username, string $class = 'user-avatar'): string
{
    $initial = pulseEscape(pulseInitial($username));

    if (!empty($avatar)) {
        $src = pulseEscape($avatar);

        return '<img class="' . pulseEscape($class) . ' avatar-image" src="' . $src .
            '" alt="' . pulseEscape($username) . '" loading="lazy" ' .
            'onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">' .
            '<span class="' . pulseEscape($class) . ' avatar-fallback" style="display:none;">' .
            $initial . '</span>';
    }

    return '<span class="' . pulseEscape($class) . ' avatar-fallback">' . $initial . '</span>';
}

/* CATEGORIES */
$categories = $pdo->query("
    SELECT id, name, slug
    FROM categories
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* HOMEPAGE POLLS */
$homepagePollData = [];

$homepagePolls = $pdo->query("
    SELECT id, title, description, created_at
    FROM polls
    WHERE status = 'active'
    ORDER BY created_at DESC
    LIMIT 2
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($homepagePolls as $poll) {
    $pollId = (int) $poll['id'];

    $stmt = $pdo->prepare("
        SELECT
            po.id,
            po.option_text,
            COUNT(pv.id) AS votes
        FROM poll_options po
        LEFT JOIN poll_votes pv
            ON pv.option_id = po.id
            AND pv.poll_id = po.poll_id
        WHERE po.poll_id = ?
        GROUP BY po.id, po.option_text
        ORDER BY po.id ASC
    ");
    $stmt->execute([$pollId]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalVotes = array_sum(array_map(
        static fn($option) => (int) $option['votes'],
        $options
    ));

    $userVote = null;

    if ($currentUserId > 0) {
        $voteStmt = $pdo->prepare("
            SELECT option_id
            FROM poll_votes
            WHERE poll_id = ? AND user_id = ?
            LIMIT 1
        ");
        $voteStmt->execute([$pollId, $currentUserId]);
        $vote = $voteStmt->fetchColumn();
        $userVote = $vote !== false ? (int) $vote : null;
    }

    $homepagePollData[] = [
        'poll' => $poll,
        'options' => $options,
        'total_votes' => $totalVotes,
        'user_vote' => $userVote
    ];
}

/* POSTS */
$posts = $pdo->query("
    SELECT
        posts.id,
        posts.user_id,
        posts.title,
        posts.content,
        posts.link_url,
        posts.created_at,
        users.username,
        users.full_name,
        users.avatar,
        categories.name AS category_name,
        categories.slug AS category_slug
    FROM posts
    INNER JOIN users ON posts.user_id = users.id
    LEFT JOIN categories ON posts.category_id = categories.id
    WHERE posts.status = 'published'
      AND users.status = 'active'
    ORDER BY posts.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= pulseEscape($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">

<style>
.avatar-image,.avatar-fallback {
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    overflow:hidden;object-fit:cover;border-radius:50%;
}
.user-avatar.avatar-image,.user-avatar.avatar-fallback {width:38px;height:38px;}
.post-avatar.avatar-image,.post-avatar.avatar-fallback {width:42px;height:42px;}
.comment-avatar.avatar-image,.comment-avatar.avatar-fallback {width:34px;height:34px;min-width:34px;}
.profile-link {color:inherit;text-decoration:none;transition:color .2s;}
.profile-link:hover {color:#a78bfa;}
.post-author .profile-link,.comment-username.profile-link {font-weight:700;}
.post-author {min-width:0;}
.post-top {align-items:center;}
.post-actions .like-btn,.post-actions .bookmark-btn {cursor:pointer;transition:color .2s,background .2s;}
.post-actions .like-btn.liked {color:#ef4444;}
.post-actions .bookmark-btn.bookmarked {color:#a78bfa;}
.post-actions .like-btn:disabled,.post-actions .bookmark-btn:disabled {opacity:.6;cursor:wait;}
.like-count {margin-left:4px;font-size:12px;}
.like-error,.bookmark-error,.comment-error {color:#ef4444;font-size:12px;margin-top:8px;}
.comments-section {display:none;margin-top:18px;padding-top:18px;border-top:1px solid #292d38;}
.comments-section.open {display:block;}
.comments-heading {font-size:14px;font-weight:700;color:#f5f5f7;margin-bottom:16px;}
.comments-list {display:flex;flex-direction:column;gap:16px;}
.comment-item {display:flex;gap:10px;align-items:flex-start;}
.comment-avatar {width:34px;height:34px;min-width:34px;border-radius:50%;background:#29233e;color:#c4b5fd;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;}
.comment-body {flex:1;min-width:0;}
.comment-content {background:#171a22;border:1px solid #292d38;border-radius:10px;padding:11px 13px;}
.comment-meta {display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:6px;}
.comment-username {color:#f5f5f7;font-size:13px;font-weight:700;}
.comment-date {color:#9297a5;font-size:11px;}
.comment-text {color:#d1d5db;font-size:13px;line-height:1.7;overflow-wrap:anywhere;white-space:pre-wrap;}
.comment-reply-btn,.comment-delete-btn {background:none;border:none;font-size:12px;font-weight:600;cursor:pointer;padding:7px 2px 0;}
.comment-reply-btn {color:#a78bfa;}
.comment-reply-btn:hover {color:#c4b5fd;}
.comment-delete-btn {color:#ef4444;margin-left:8px;}
.comment-delete-btn:hover {color:#f87171;}
.comment-delete-btn:disabled {opacity:.5;cursor:wait;}
.comment-replies {margin-top:12px;margin-left:14px;padding-left:14px;border-left:2px solid #292d38;display:flex;flex-direction:column;gap:12px;}
.comment-form {margin-top:16px;display:flex;flex-direction:column;gap:9px;}
.comment-form textarea {width:100%;min-height:75px;resize:vertical;background:#171a22;border:1px solid #292d38;border-radius:10px;padding:12px;color:#f5f5f7;font-family:inherit;font-size:13px;outline:none;}
.comment-form textarea:focus {border-color:#8b5cf6;}
.comment-form textarea::placeholder {color:#777d8b;}
.comment-form-actions {display:flex;justify-content:space-between;align-items:center;gap:10px;}
.comment-submit {background:#8b5cf6;color:white;border:none;border-radius:8px;padding:9px 16px;font-size:12px;font-weight:600;cursor:pointer;transition:background .2s;}
.comment-submit:hover {background:#7c3aed;}
.comment-submit:disabled {opacity:.6;cursor:wait;}
.comment-char-count {color:#9297a5;font-size:11px;}
.comment-login-message {margin-top:16px;padding:12px;background:#171a22;border-radius:9px;color:#9297a5;font-size:13px;}
.comment-login-message a {color:#a78bfa;text-decoration:none;font-weight:600;}
.comment-empty {color:#9297a5;font-size:13px;padding:10px 0;}
.comments-btn {cursor:pointer;}
.comments-btn.active {color:#a78bfa;}
.reply-form {margin-top:10px;margin-bottom:10px;display:none;}
.reply-form.open {display:flex;}

/* HOMEPAGE POLLS */
.homepage-polls-widget {overflow:hidden;}
.homepage-poll {padding:14px 0;border-bottom:1px solid #292d38;}
.homepage-poll:last-child {border-bottom:none;}
.homepage-poll-description {font-size:12px;color:#9297a5;line-height:1.6;margin:6px 0 12px;}
.homepage-poll-options {display:flex;flex-direction:column;gap:9px;}
.homepage-poll-option {width:100%;text-align:left;background:transparent;border:1px solid #292d38;border-radius:9px;padding:10px;color:#d1d5db;cursor:pointer;transition:.2s ease;}
.homepage-poll-option:not(:disabled):hover {border-color:#8b5cf6;background:rgba(139,92,246,.08);}
.homepage-poll-option.selected {border-color:#8b5cf6;background:rgba(139,92,246,.10);}
.homepage-poll-option:disabled {cursor:default;}
.homepage-poll-option .poll-option-header {display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:12px;margin-bottom:8px;}
.homepage-poll-option .poll-option-header strong {color:#c4b5fd;}
.homepage-poll-option .poll-bar {height:5px;width:100%;background:#292d38;border-radius:10px;overflow:hidden;}
.homepage-poll-option .poll-bar span {display:block;height:100%;background:linear-gradient(90deg,#7c3aed,#a78bfa);border-radius:10px;transition:width .4s ease;}
.homepage-poll-message {font-size:12px;color:#ef4444;margin-top:8px;}
.homepage-polls-link {display:block;margin-top:12px;color:#a78bfa;text-decoration:none;font-size:12px;font-weight:600;}
.homepage-polls-link:hover {color:#c4b5fd;}
.poll-footer {color:#9297a5;font-size:11px;margin-top:10px;}
</style>
</head>

<body>
<div class="app-container">

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand">
        <span class="brand-icon">P</span>
        <span class="brand-name">PULSE</span>
    </div>

    <div class="sidebar-section">
        <span class="section-label">MENU</span>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item active"><span>⌂</span><span>Home</span></a>
            <a href="#" class="nav-item"><span>⌕</span><span>Explore</span></a>
            <a href="#" class="nav-item"><span>◉</span><span>Discussions</span></a>
            <a href="polls.php" class="nav-item"><span>▤</span><span>Polls</span></a>
            <a href="#" class="nav-item"><span>⚡</span><span>Challenges</span></a>
            <a href="#" class="nav-item"><span>♧</span><span>Notifications</span></a>
            <a href="saved.php" class="nav-item"><span>▣</span><span>Saved Posts</span></a>
        </nav>
    </div>

      <div class="sidebar-bottom">
        <div class="sidebar-user">
            <?php if ($currentUser): ?>
                <a href="profile.php?u=<?= urlencode($currentUser['username']) ?>" class="profile-link">
                    <?= pulseAvatar($currentUser['avatar'] ?? '', $currentUser['username'], 'user-avatar') ?>
                </a>

                <div class="user-info">
                    <strong>
                        <a href="profile.php?u=<?= urlencode($currentUser['username']) ?>" class="profile-link">
                            <?= pulseEscape($currentUser['username']) ?>
                        </a>
                    </strong>
                    <span>Member of PULSE</span>
                </div>
            <?php else: ?>
                <div class="user-avatar">G</div>
                <div class="user-info">
                    <strong>Guest User</strong>
                    <span>Welcome to PULSE</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</aside>


<!-- MAIN -->
<main class="main-content">
    <header class="topbar">
        <div class="topbar-title">
            <h2>Home</h2>
            <p>Discover what's happening in tech.</p>
        </div>

        <div class="topbar-actions">
            <button class="icon-button" type="button" id="topbarSearchButton" aria-label="Search" title="Search posts">⌕</button>
            <button class="icon-button" type="button" aria-label="Notifications">♧</button>

            <?php if ($currentUser): ?>
                <a href="profile.php?u=<?= urlencode($currentUser['username']) ?>" class="topbar-username profile-link"><?= pulseEscape($currentUser['username']) ?></a>
                <form action="logout.php" method="POST" class="logout-form">
                    <input type="hidden" name="csrf_token" value="<?= pulseEscape($csrfToken) ?>">
                    <button class="login-button" type="submit">Sign Out</button>
                </form>
            <?php else: ?>
                <a href="login.php" class="login-button">Sign In</a>
                <a href="register.php" class="login-button">Create Account</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="page-content">
        <div class="dashboard-grid">

            <!-- FEED -->
            <div class="feed-column">
                <div class="welcome-section">
                    <span class="welcome-label">WELCOME TO PULSE</span>
                    <h1>Discover. <span>Participate.</span> Connect.</h1>
                    <p>Your space to explore technology, share ideas and connect with curious minds.</p>
                </div>

                <div class="feed-header">
                    <div>
                        <h2>Community Feed</h2>
                        <p>Explore the latest conversations in tech.</p>
                    </div>
                    <a href="<?= $currentUser ? 'create-post.php' : 'login.php' ?>" class="create-post-btn">+ Create Post</a>
                </div>

                <div class="feed-search">
                    <span class="search-icon">⌕</span>
                    <input type="search" id="feedSearch" placeholder="Search posts, topics or users..." autocomplete="off">
                    <span class="search-shortcut">Search</span>
                </div>

                <div id="databaseSearchResults" class="database-search-results" style="display:none;">
                    <div id="searchResultsCount"></div>
                    <div id="searchResultsList"></div>
                </div>

                <div class="categories">
                    <button class="category-chip active" type="button" data-category="all">All Topics</button>
                    <?php foreach ($categories as $category): ?>
                        <button class="category-chip" type="button" data-category="<?= pulseEscape($category['slug']) ?>"><?= pulseEscape($category['name']) ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="feed-list">
                    <?php if (empty($posts)): ?>
                        <div class="post-card empty-feed">
                            <h3>No discussions yet</h3>
                            <p>Be the first to start a conversation in the PULSE community.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <?php
                            $postId = (int) $post['id'];
                            $likeCount = $likeModel->countByPost($postId);
                            $isLiked = $currentUser ? $likeModel->hasLiked($postId, $currentUserId) : false;
                            $isBookmarked = $currentUser ? $bookmarkModel->hasBookmarked($postId, $currentUserId) : false;
                            $comments = $commentModel->getByPost($postId);
                            $commentCount = count($comments);
                            $topLevelComments = [];
                            $repliesByParent = [];

                            foreach ($comments as $comment) {
                                if ($comment['parent_id'] === null) {
                                    $topLevelComments[] = $comment;
                                } else {
                                    $parentId = (int) $comment['parent_id'];
                                    $repliesByParent[$parentId][] = $comment;
                                }
                            }
                            ?>
                            <article class="post-card" id="post-<?= $postId ?>" data-post-id="<?= $postId ?>" data-category="<?= pulseEscape($post['category_slug'] ?? 'uncategorized') ?>">
                                <div class="post-top">
                                    <a href="profile.php?u=<?= urlencode($post['username']) ?>" class="profile-link"><?= pulseAvatar($post['avatar'] ?? '', $post['username'], 'post-avatar') ?></a>
                                    <div class="post-author">
                                        <strong><a href="profile.php?u=<?= urlencode($post['username']) ?>" class="profile-link"><?= pulseEscape($post['username']) ?></a></strong>
                                        <span><?= pulseEscape($post['category_name'] ?? 'General') ?> · <?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                                    </div>
                                    <button class="post-more" type="button" aria-label="More options">•••</button>
                                </div>

                                <h3><?= pulseEscape($post['title']) ?></h3>
                                <p><?= nl2br(pulseEscape($post['content'])) ?></p>

                                <?php if (!empty($post['link_url'])): ?>
                                    <a href="<?= pulseEscape($post['link_url']) ?>" target="_blank" rel="noopener noreferrer">Visit shared link →</a>
                                <?php endif; ?>

                                <div class="post-actions">
                                    <button type="button" class="like-btn <?= $isLiked ? 'liked' : '' ?>" data-post-id="<?= $postId ?>" aria-pressed="<?= $isLiked ? 'true' : 'false' ?>">
                                        <span class="like-icon"><?= $isLiked ? '♥' : '♡' ?></span>
                                        <span class="like-label">Like</span>
                                        <span class="like-count"><?= (int) $likeCount ?></span>
                                    </button>

                                    <button type="button" class="comments-btn" data-post-id="<?= $postId ?>" aria-expanded="false">
                                        ▢ Comments <span class="comment-count"><?= $commentCount ?></span>
                                    </button>

                                    <button type="button" class="bookmark-btn <?= $isBookmarked ? 'bookmarked' : '' ?>" data-post-id="<?= $postId ?>" aria-pressed="<?= $isBookmarked ? 'true' : 'false' ?>">
                                        <span class="bookmark-icon"><?= $isBookmarked ? '▣' : '♧' ?></span>
                                        <span class="bookmark-label"><?= $isBookmarked ? 'Saved' : 'Save' ?></span>
                                    </button>
                                </div>

                                <!-- COMMENTS -->
                                <div class="comments-section">
                                    <h4 class="comments-heading">Comments (<?= $commentCount ?>)</h4>
                                    <div class="comments-list">
                                        <?php if (empty($topLevelComments)): ?>
                                            <p class="comment-empty">No comments yet. Start the conversation!</p>
                                        <?php else: ?>
                                            <?php foreach ($topLevelComments as $comment): ?>
                                                <?php
                                                $commentId = (int) $comment['id'];
                                                $commentUserId = (int) $comment['user_id'];
                                                $isCommentOwner = $currentUserId > 0 && $commentUserId === $currentUserId;
                                                $commentReplies = $repliesByParent[$commentId] ?? [];
                                                $hasReplies = !empty($commentReplies);
                                                ?>
                                                <div class="comment-item" data-comment-id="<?= $commentId ?>" data-user-id="<?= $commentUserId ?>">
                                                    <a href="profile.php?u=<?= urlencode($comment['username']) ?>" class="profile-link"><?= pulseAvatar($comment['avatar'] ?? '', $comment['username'], 'comment-avatar') ?></a>
                                                    <div class="comment-body">
                                                        <div class="comment-content">
                                                            <div class="comment-meta">
                                                                <a href="profile.php?u=<?= urlencode($comment['username']) ?>" class="comment-username profile-link"><?= pulseEscape($comment['username']) ?></a>
                                                                <span class="comment-date"><?= date('M d, Y · H:i', strtotime($comment['created_at'])) ?></span>
                                                            </div>
                                                            <div class="comment-text"><?= pulseEscape($comment['content']) ?></div>
                                                        </div>

                                                        <?php if ($currentUser): ?>
                                                            <button type="button" class="comment-reply-btn" data-comment-id="<?= $commentId ?>">Reply</button>
                                                            <?php if ($isCommentOwner && !$hasReplies): ?>
                                                                <button type="button" class="comment-delete-btn" data-comment-id="<?= $commentId ?>" data-user-id="<?= $commentUserId ?>">Delete</button>
                                                            <?php endif; ?>
                                                            <form class="comment-form reply-form" data-post-id="<?= $postId ?>" data-parent-id="<?= $commentId ?>">
                                                                <textarea name="content" maxlength="2000" placeholder="Write a reply..." required></textarea>
                                                                <div class="comment-form-actions">
                                                                    <span class="comment-char-count">0 / 2000</span>
                                                                    <button type="submit" class="comment-submit">Send Reply</button>
                                                                </div>
                                                            </form>
                                                        <?php endif; ?>

                                                        <div class="comment-replies">
                                                            <?php foreach ($commentReplies as $reply): ?>
                                                                <?php
                                                                $replyId = (int) $reply['id'];
                                                                $replyUserId = (int) $reply['user_id'];
                                                                $isReplyOwner = $currentUserId > 0 && $replyUserId === $currentUserId;
                                                                ?>
                                                                <div class="comment-item" data-comment-id="<?= $replyId ?>" data-user-id="<?= $replyUserId ?>">
                                                                    <a href="profile.php?u=<?= urlencode($reply['username']) ?>" class="profile-link"><?= pulseAvatar($reply['avatar'] ?? '', $reply['username'], 'comment-avatar') ?></a>
                                                                    <div class="comment-body">
                                                                        <div class="comment-content">
                                                                            <div class="comment-meta">
                                                                                <a href="profile.php?u=<?= urlencode($reply['username']) ?>" class="comment-username profile-link"><?= pulseEscape($reply['username']) ?></a>
                                                                                <span class="comment-date"><?= date('M d, Y · H:i', strtotime($reply['created_at'])) ?></span>
                                                                            </div>
                                                                            <div class="comment-text"><?= pulseEscape($reply['content']) ?></div>
                                                                        </div>
                                                                        <?php if ($isReplyOwner): ?>
                                                                            <button type="button" class="comment-delete-btn" data-comment-id="<?= $replyId ?>" data-user-id="<?= $replyUserId ?>">Delete</button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($currentUser): ?>
                                        <form class="comment-form main-comment-form" data-post-id="<?= $postId ?>" data-parent-id="">
                                            <textarea name="content" maxlength="2000" placeholder="Share your thoughts..." required></textarea>
                                            <div class="comment-form-actions">
                                                <span class="comment-char-count">0 / 2000</span>
                                                <button type="submit" class="comment-submit">Post Comment</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="comment-login-message"><a href="login.php">Sign in</a> to join the conversation.</div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT SIDEBAR -->
            <aside class="right-sidebar">
                <div class="widget">
                    <div class="widget-header"><h3>Trending Topics</h3><span>•••</span></div>
                    <div class="trending-item"><span class="trend-number">01</span><div><strong>Artificial Intelligence</strong><p>2.4k discussions</p></div></div>
                    <div class="trending-item"><span class="trend-number">02</span><div><strong>Web Development</strong><p>1.8k discussions</p></div></div>
                    <div class="trending-item"><span class="trend-number">03</span><div><strong>Gaming Technology</strong><p>950 discussions</p></div></div>
                    <div class="trending-item"><span class="trend-number">04</span><div><strong>Cybersecurity</strong><p>720 discussions</p></div></div>
                </div>

                <!-- ACTIVE POLLS -->
                <div class="widget homepage-polls-widget">
                    <div class="widget-header">
                        <h3>Active Polls</h3>
                        <span class="live-indicator">LIVE</span>
                    </div>

                    <?php if (empty($homepagePollData)): ?>
                        <p class="widget-description">No active polls available right now.</p>
                    <?php else: ?>
                        <?php foreach ($homepagePollData as $pollData): ?>
                            <?php
                            $poll = $pollData['poll'];
                            $options = $pollData['options'];
                            $totalVotes = (int) $pollData['total_votes'];
                            $userVote = $pollData['user_vote'];
                            ?>
                            <div class="homepage-poll" data-poll-id="<?= (int) $poll['id'] ?>">
                                <p class="widget-description"><?= pulseEscape($poll['title']) ?></p>
                                <?php if (!empty($poll['description'])): ?>
                                    <p class="homepage-poll-description"><?= pulseEscape($poll['description']) ?></p>
                                <?php endif; ?>

                                <div class="homepage-poll-options">
                                    <?php foreach ($options as $option): ?>
                                        <?php
                                        $optionId = (int) $option['id'];
                                        $votes = (int) $option['votes'];
                                        $percentage = $totalVotes > 0 ? round(($votes / $totalVotes) * 100) : 0;
                                        $isSelected = $userVote !== null && (int) $userVote === $optionId;
                                        ?>
                                        <button type="button" class="homepage-poll-option <?= $isSelected ? 'selected' : '' ?>" data-option-id="<?= $optionId ?>" <?= $userVote !== null ? 'disabled' : '' ?>>
                                            <div class="poll-option-header">
                                                <span><?= pulseEscape($option['option_text']) ?></span>
                                                <strong><?= $userVote !== null ? $percentage . '%' : '' ?></strong>
                                            </div>
                                            <div class="poll-bar"><span style="width:<?= $userVote !== null ? $percentage : 0 ?>%"></span></div>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <p class="poll-footer">
                                    <?= $totalVotes ?> votes
                                    <?= $userVote !== null ? ' · You voted' : ' · Select an option to vote' ?>
                                </p>
                                <div class="homepage-poll-message" aria-live="polite"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <a href="polls.php" class="homepage-polls-link">View all polls →</a>
                </div>

                <!-- DAILY CHALLENGE -->
                <div class="widget challenge-widget">
                    <div class="challenge-icon">⚡</div>
                    <span class="challenge-label">DAILY CHALLENGE</span>
                    <h3>Test Your Tech Knowledge</h3>
                    <p>How well do you understand modern technology? Take today's challenge and earn points.</p>
                    <button class="challenge-button" type="button">Start Challenge <span>→</span></button>
                </div>
            </aside>
        </div>
    </section>
</main>
</div>

<script src="assets/js/main.js"></script>

<script>
const PULSE_CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const PULSE_LOGGED_IN = <?= $currentUserId > 0 ? 'true' : 'false' ?>;
const PULSE_USER_ID = <?= (int) $currentUserId ?>;
const PULSE_USERNAME = <?= json_encode($currentUser['username'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const PULSE_AVATAR = <?= json_encode($currentUser['avatar'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function pulseLoginRequired() {
    window.location.href = 'login.php';
}

async function pulseFetchJson(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    });

    let result;

    try {
        result = await response.json();
    } catch {
        throw new Error('The server returned an invalid response.');
    }

    if (!response.ok || !result || !result.success) {
        throw new Error(result?.message || 'The request could not be completed.');
    }

    return result;
}

function pulseCreateFormData(data = {}) {
    const formData = new FormData();

    Object.entries(data).forEach(([key, value]) => {
        formData.append(key, value ?? '');
    });

    formData.append('csrf_token', PULSE_CSRF_TOKEN);
    return formData;
}

/* LIKE */
document.addEventListener('click', async event => {
    const button = event.target.closest('.like-btn');
    if (!button) return;

    if (!PULSE_LOGGED_IN) return pulseLoginRequired();
    if (button.disabled) return;

    button.disabled = true;

    try {
        const result = await pulseFetchJson('like.php', pulseCreateFormData({
            post_id: button.dataset.postId
        }));

        button.classList.toggle('liked', !!result.liked);
        button.setAttribute('aria-pressed', result.liked ? 'true' : 'false');

        const icon = button.querySelector('.like-icon');
        const count = button.querySelector('.like-count');

        if (icon) icon.textContent = result.liked ? '♥' : '♡';
        if (count) count.textContent = result.count;
    } catch (error) {
        alert(error.message);
    } finally {
        button.disabled = false;
    }
});

/* BOOKMARK */
document.addEventListener('click', async event => {
    const button = event.target.closest('.bookmark-btn');
    if (!button) return;

    if (!PULSE_LOGGED_IN) return pulseLoginRequired();
    if (button.disabled) return;

    button.disabled = true;

    try {
        const result = await pulseFetchJson('bookmark.php', pulseCreateFormData({
            post_id: button.dataset.postId
        }));

        button.classList.toggle('bookmarked', !!result.bookmarked);
        button.setAttribute('aria-pressed', result.bookmarked ? 'true' : 'false');

        const icon = button.querySelector('.bookmark-icon');
        const label = button.querySelector('.bookmark-label');

        if (icon) icon.textContent = result.bookmarked ? '▣' : '♧';
        if (label) label.textContent = result.bookmarked ? 'Saved' : 'Save';
    } catch (error) {
        alert(error.message);
    } finally {
        button.disabled = false;
    }
});

/* COMMENT COUNT */
function pulseUpdateCommentCount(postCard, change) {
    if (!postCard) return;

    const countElement = postCard.querySelector('.comment-count');
    const heading = postCard.querySelector('.comments-heading');
    const commentsList = postCard.querySelector('.comments-list');

    if (!countElement || !heading || !commentsList) return;

    const newCount = Math.max(0, (Number(countElement.textContent) || 0) + change);
    countElement.textContent = newCount;
    heading.textContent = 'Comments (' + newCount + ')';

    if (newCount === 0 && !commentsList.querySelector('.comment-item')) {
        if (!commentsList.querySelector('.comment-empty')) {
            const empty = document.createElement('p');
            empty.className = 'comment-empty';
            empty.textContent = 'No comments yet. Start the conversation!';
            commentsList.appendChild(empty);
        }
    } else {
        commentsList.querySelector('.comment-empty')?.remove();
    }
}

/* AVATAR */
function pulseCreateAvatar(username, avatarPath, className = 'user-avatar') {
    const name = username || 'User';

    if (avatarPath) {
        const image = document.createElement('img');
        image.src = avatarPath;
        image.alt = name;
        image.loading = 'lazy';
        image.className = 'avatar-image ' + className;

        image.onerror = () => {
            const fallback = document.createElement('span');
            fallback.className = 'avatar-fallback ' + className;
            fallback.textContent = name.charAt(0).toUpperCase() || '?';
            image.replaceWith(fallback);
        };

        return image;
    }

    const fallback = document.createElement('span');
    fallback.className = 'avatar-fallback ' + className;
    fallback.textContent = name.charAt(0).toUpperCase() || '?';
    return fallback;
}

function pulseCreateProfileLink(username, className = '') {
    const link = document.createElement('a');
    link.href = 'profile.php?u=' + encodeURIComponent(username || '');
    link.className = className + ' profile-link';
    link.textContent = username || 'User';
    return link;
}

function pulseCreateDeleteButton(commentId, userId) {
    if (!PULSE_LOGGED_IN || Number(userId) !== PULSE_USER_ID || !commentId) return null;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'comment-delete-btn';
    button.dataset.commentId = commentId;
    button.dataset.userId = userId;
    button.textContent = 'Delete';
    return button;
}

function pulseCreateReplyForm(postId, parentId) {
    const form = document.createElement('form');
    form.className = 'comment-form reply-form';
    form.dataset.postId = postId;
    form.dataset.parentId = parentId;

    const textarea = document.createElement('textarea');
    textarea.name = 'content';
    textarea.maxLength = 2000;
    textarea.placeholder = 'Write a reply...';
    textarea.required = true;

    const actions = document.createElement('div');
    actions.className = 'comment-form-actions';

    const counter = document.createElement('span');
    counter.className = 'comment-char-count';
    counter.textContent = '0 / 2000';

    const submit = document.createElement('button');
    submit.type = 'submit';
    submit.className = 'comment-submit';
    submit.textContent = 'Send Reply';

    actions.append(counter, submit);
    form.append(textarea, actions);
    return form;
}

function pulseCreateCommentElement(username, content, postId, commentId, isReply, userId, avatarPath) {
    const item = document.createElement('div');
    item.className = 'comment-item';
    item.dataset.commentId = commentId;
    item.dataset.userId = userId;

    const profileLink = pulseCreateProfileLink(username, 'comment-avatar-link');
    profileLink.appendChild(pulseCreateAvatar(username, avatarPath, 'comment-avatar'));

    const body = document.createElement('div');
    body.className = 'comment-body';

    const contentBox = document.createElement('div');
    contentBox.className = 'comment-content';

    const meta = document.createElement('div');
    meta.className = 'comment-meta';

    const nameLink = pulseCreateProfileLink(username, 'comment-username');
    const date = document.createElement('span');
    date.className = 'comment-date';
    date.textContent = 'Just now';

    meta.append(nameLink, date);

    const text = document.createElement('div');
    text.className = 'comment-text';
    text.textContent = content;

    contentBox.append(meta, text);
    body.appendChild(contentBox);

    const deleteButton = pulseCreateDeleteButton(commentId, userId);
    if (deleteButton) body.appendChild(deleteButton);

    if (!isReply) {
        const replyButton = document.createElement('button');
        replyButton.type = 'button';
        replyButton.className = 'comment-reply-btn';
        replyButton.dataset.commentId = commentId;
        replyButton.textContent = 'Reply';

        body.append(replyButton, pulseCreateReplyForm(postId, commentId));

        const replies = document.createElement('div');
        replies.className = 'comment-replies';
        body.appendChild(replies);
    }

    item.append(profileLink, body);
    return item;
}

/* TOGGLE COMMENTS */
document.addEventListener('click', event => {
    const button = event.target.closest('.comments-btn');
    if (!button) return;

    const card = button.closest('.post-card');
    const section = card?.querySelector('.comments-section');
    if (!section) return;

    section.classList.toggle('open');
    const open = section.classList.contains('open');

    button.classList.toggle('active', open);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
});

/* REPLY */
document.addEventListener('click', event => {
    const button = event.target.closest('.comment-reply-btn');
    if (!button) return;

    if (!PULSE_LOGGED_IN) return pulseLoginRequired();

    const item = button.closest('.comment-item');
    const form = item?.querySelector(':scope > .comment-body > .reply-form');
    if (!form) return;

    form.classList.toggle('open');
    if (form.classList.contains('open')) form.querySelector('textarea')?.focus();
});

/* COUNTER */
document.addEventListener('input', event => {
    if (!event.target.matches('.comment-form textarea')) return;

    const form = event.target.closest('.comment-form');
    const counter = form?.querySelector('.comment-char-count');

    if (counter) counter.textContent = event.target.value.length + ' / 2000';
});

/* SUBMIT COMMENT */
document.addEventListener('submit', async event => {
    const form = event.target.closest('.comment-form');
    if (!form) return;

    event.preventDefault();

    if (!PULSE_LOGGED_IN) return pulseLoginRequired();

    const textarea = form.querySelector('textarea');
    const content = textarea?.value.trim();

    if (!content) {
        textarea?.focus();
        return;
    }

    if (content.length > 2000) {
        alert('Comment cannot exceed 2000 characters.');
        return;
    }

    const card = form.closest('.post-card');
    const commentsButton = card?.querySelector('.comments-btn');
    const postId = form.dataset.postId || commentsButton?.dataset.postId;
    const parentId = form.dataset.parentId || '';
    const submit = form.querySelector('.comment-submit');
    const list = card?.querySelector('.comments-list');

    if (!postId || !submit || !list || submit.disabled) return;

    submit.disabled = true;
    const originalText = parentId ? 'Send Reply' : 'Post Comment';
    submit.textContent = 'Sending...';
    form.querySelector('.comment-error')?.remove();

    try {
        const result = await pulseFetchJson('comment.php', pulseCreateFormData({
            post_id: postId,
            content,
            parent_id: parentId
        }));

        if (!result.comment_id) {
            throw new Error('The server did not return the comment ID.');
        }

        const isReply = parentId !== '';
        const username = result.username || PULSE_USERNAME || 'User';
        const avatar = result.avatar || PULSE_AVATAR || '';
        const userId = Number(result.user_id || PULSE_USER_ID);

        const newComment = pulseCreateCommentElement(
            username, content, postId, Number(result.comment_id),
            isReply, userId, avatar
        );

        if (isReply) {
            const parent = Array.from(card.querySelectorAll('.comment-item'))
                .find(item => Number(item.dataset.commentId) === Number(parentId));

            const replies = parent?.querySelector('.comment-replies');
            if (!replies) throw new Error('Reply container not found. Refresh the page.');

            replies.appendChild(newComment);
            form.classList.remove('open');
        } else {
            list.appendChild(newComment);
        }

        list.querySelector('.comment-empty')?.remove();
        form.reset();

        const counter = form.querySelector('.comment-char-count');
        if (counter) counter.textContent = '0 / 2000';

        pulseUpdateCommentCount(card, 1);
        card.querySelector('.comments-section')?.classList.add('open');
        commentsButton?.classList.add('active');
        commentsButton?.setAttribute('aria-expanded', 'true');

    } catch (error) {
        const errorElement = document.createElement('p');
        errorElement.className = 'comment-error';
        errorElement.textContent = error.message;
        form.appendChild(errorElement);
    } finally {
        submit.disabled = false;
        submit.textContent = originalText;
    }
});

/* DELETE COMMENT */
document.addEventListener('click', async event => {
    const button = event.target.closest('.comment-delete-btn');
    if (!button) return;

    if (!PULSE_LOGGED_IN) return pulseLoginRequired();
    if (button.disabled) return;

    const commentId = button.dataset.commentId;
    const ownerId = Number(button.dataset.userId);

    if (ownerId !== PULSE_USER_ID) {
        alert('You can only delete your own comments.');
        return;
    }

    if (!commentId) {
        alert('Comment ID is missing.');
        return;
    }

    if (!confirm('Are you sure you want to delete this comment?')) return;

    const item = button.closest('.comment-item');
    const card = button.closest('.post-card');
    if (!item || !card) return;

    button.disabled = true;
    button.textContent = 'Deleting...';

    try {
        await pulseFetchJson('delete-comment.php', pulseCreateFormData({
            comment_id: commentId
        }));

        item.remove();
        pulseUpdateCommentCount(card, -1);
    } catch (error) {
        alert(error.message);
        button.disabled = false;
        button.textContent = 'Delete';
    }
});

/* HOMEPAGE POLLS */
document.addEventListener('click', async event => {
    const optionButton = event.target.closest('.homepage-poll-option');
    if (!optionButton) return;

    if (!PULSE_LOGGED_IN) return pulseLoginRequired();
    if (optionButton.disabled) return;

    const poll = optionButton.closest('.homepage-poll');
    if (!poll) return;

    const pollId = poll.dataset.pollId;
    const optionId = optionButton.dataset.optionId;
    const message = poll.querySelector('.homepage-poll-message');
    const buttons = poll.querySelectorAll('.homepage-poll-option');

    buttons.forEach(button => button.disabled = true);

    if (message) {
        message.textContent = 'Submitting your vote...';
        message.style.color = '#a78bfa';
    }

    try {
        const result = await pulseFetchJson('vote-poll.php', pulseCreateFormData({
            poll_id: pollId,
            option_id: optionId
        }));

        if (message) {
            message.textContent = 'Vote submitted successfully!';
            message.style.color = '#22c55e';
        }

        window.location.reload();
    } catch (error) {
        if (message) {
            message.textContent = error.message;
            message.style.color = '#ef4444';
        }
        buttons.forEach(button => button.disabled = false);
    }
});

console.log('PULSE scripts initialized.');
</script>
</body>
</html>
