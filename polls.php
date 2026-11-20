
<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/Session.php';
require_once __DIR__ . '/app/models/Poll.php';

Session::start();

$isLoggedIn = Session::isLoggedIn();
$userId = $isLoggedIn ? (int) Session::userId() : null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$pollModel = new Poll($pdo);
$polls = $pollModel->getActivePolls($userId);

$pageTitle = 'PULSE — Community Polls';

function escapePoll(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapePoll($pageTitle) ?></title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: #09090f;
            color: #f4f1ff;
            font-family: Arial, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .navbar {
            height: 75px;
            padding: 0 8%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,.07);
            background: #0d0c15;
        }

        .logo {
            font-size: 25px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #a78bfa;
        }

        .nav-link {
            color: #c4bdd8;
            font-size: 14px;
            transition: .2s;
        }

        .nav-link:hover {
            color: #a78bfa;
        }

        .container {
            width: min(850px, 92%);
            margin: 65px auto;
        }

        .heading {
            margin-bottom: 35px;
        }

        .eyebrow {
            color: #a78bfa;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 3px;
            margin-bottom: 14px;
        }

        h1 {
            font-size: clamp(30px, 5vw, 42px);
            margin-bottom: 12px;
        }

        .subtitle {
            color: #918aa4;
            line-height: 1.7;
        }

        .poll-card {
            background: linear-gradient(145deg, #171522, #11101a);
            border: 1px solid rgba(167,139,250,.13);
            border-radius: 18px;
            padding: 27px;
            margin-bottom: 22px;
            transition: .25s;
        }

        .poll-card:hover {
            border-color: rgba(167,139,250,.4);
            transform: translateY(-2px);
        }

        .poll-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .poll-label {
            color: #a78bfa;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1.5px;
        }

        .vote-count {
            color: #918aa4;
            font-size: 12px;
        }

        .poll-question {
            font-size: 21px;
            line-height: 1.5;
            margin-bottom: 23px;
        }

        .poll-options {
            display: grid;
            gap: 12px;
        }

        .poll-option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 11px;
            background: rgba(255,255,255,.025);
            cursor: pointer;
            transition: .2s;
        }

        .poll-option:hover {
            border-color: #8b5cf6;
            background: rgba(139,92,246,.08);
        }

        .poll-option input {
            accent-color: #a78bfa;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .option-text {
            font-size: 14px;
            color: #e5e0f0;
        }

        .result-row {
            margin-bottom: 16px;
        }

        .result-info {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .result-name {
            color: #e5e0f0;
        }

        .result-percent {
            color: #c4b5fd;
            font-weight: bold;
        }

        .progress {
            height: 8px;
            background: #292536;
            border-radius: 20px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #7c3aed, #c084fc);
            border-radius: 20px;
            transition: width .4s;
        }

        .vote-button {
            margin-top: 20px;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(110deg, #7c3aed, #a855f7);
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: .2s;
        }

        .vote-button:hover {
            filter: brightness(1.15);
        }

        .vote-button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .poll-message {
            margin-top: 13px;
            font-size: 13px;
            color: #c4b5fd;
            min-height: 18px;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
            background: #14121e;
            border: 1px dashed #393149;
            border-radius: 16px;
        }

        .empty-state h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #918aa4;
            font-size: 14px;
            line-height: 1.7;
        }

        .login-notice {
            color: #c4b5fd;
            font-size: 13px;
            margin-top: 18px;
        }

        @media (max-width: 600px) {
            .navbar {
                padding: 0 5%;
            }

            .container {
                margin: 40px auto;
            }

            .poll-card {
                padding: 20px;
            }

            .poll-question {
                font-size: 18px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <a href="index.php" class="logo">PULSE</a>
    <a href="index.php" class="nav-link">← Back to Feed</a>
</header>

<main class="container">

    <section class="heading">
        <div class="eyebrow">COMMUNITY VOICE</div>
        <h1>Community Polls</h1>
        <p class="subtitle">
            Share your opinion, discover what others think,
            and be part of the conversation.
        </p>
    </section>

    <section id="pollContainer">

        <?php if (empty($polls)): ?>

            <div class="empty-state">
                <h2>No active polls yet</h2>
                <p>
                    There are no active community polls at the moment.
                    Check back later.
                </p>
            </div>

        <?php else: ?>

            <?php foreach ($polls as $poll): ?>

                <?php
                    $totalVotes = (int) $poll['total_votes'];
                    $hasVoted = (bool) $poll['user_voted'];

                    $results = $hasVoted
                        ? $pollModel->getResults((int) $poll['id'])
                        : null;
                ?>

                <article
                    class="poll-card"
                    data-poll-id="<?= (int) $poll['id'] ?>"
                >

                    <div class="poll-top">
                        <span class="poll-label">COMMUNITY POLL</span>

                        <span class="vote-count">
                            <?= $totalVotes ?>
                            <?= $totalVotes === 1 ? 'vote' : 'votes' ?>
                        </span>
                    </div>

                    <h2 class="poll-question">
                        <?= escapePoll($poll['question']) ?>
                    </h2>

                    <div class="poll-options">

                        <?php foreach ($poll['options'] as $option): ?>

                            <?php
                                $optionId = (int) $option['id'];
                                $optionVotes = (int) $option['votes'];

                                $percentage = $totalVotes > 0
                                    ? round(($optionVotes / $totalVotes) * 100, 1)
                                    : 0;
                            ?>

                            <?php if ($hasVoted): ?>

                                <div class="result-row">

                                    <div class="result-info">
                                        <span class="result-name">
                                            <?= escapePoll($option['option_text']) ?>
                                        </span>

                                        <span class="result-percent">
                                            <?= $percentage ?>%
                                        </span>
                                    </div>

                                    <div class="progress">
                                        <div
                                            class="progress-fill"
                                            style="width: <?= $percentage ?>%"
                                        ></div>
                                    </div>

                                </div>

                            <?php else: ?>

                                <label class="poll-option">
                                    <input
                                        type="radio"
                                        name="poll_<?= (int) $poll['id'] ?>"
                                        value="<?= $optionId ?>"
                                    >

                                    <span class="option-text">
                                        <?= escapePoll($option['option_text']) ?>
                                    </span>
                                </label>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>

                    <?php if (!$hasVoted && $isLoggedIn): ?>

                        <button
                            type="button"
                            class="vote-button"
                            data-vote-button
                        >
                            Submit Vote
                        </button>

                    <?php elseif (!$hasVoted): ?>

                        <p class="login-notice">
                            Please log in to participate in this poll.
                        </p>

                    <?php else: ?>

                        <p class="login-notice">
                            You have already voted in this poll.
                        </p>

                    <?php endif; ?>

                    <p class="poll-message" aria-live="polite"></p>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

<script>
const POLL_CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

document.querySelectorAll('[data-vote-button]').forEach(button => {

    button.addEventListener('click', async () => {

        const card = button.closest('[data-poll-id]');
        const message = card.querySelector('.poll-message');

        const pollId = card.dataset.pollId;

        const selectedOption = card.querySelector(
            'input[type="radio"]:checked'
        );

        if (!selectedOption) {
            message.textContent = 'Please select an option first.';
            return;
        }

        button.disabled = true;
        button.textContent = 'Submitting...';
        message.textContent = '';

        const formData = new FormData();

        formData.append('csrf_token', POLL_CSRF_TOKEN);
        formData.append('poll_id', pollId);
        formData.append('option_id', selectedOption.value);

        try {

            const response = await fetch('vote-poll.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Unable to submit your vote.'
                );
            }

            message.textContent = 'Vote submitted successfully!';

            // Refresh the poll to display the updated results.
            window.location.reload();

        } catch (error) {

            message.textContent = error.message;
            button.disabled = false;
            button.textContent = 'Submit Vote';

        }

    });

});
</script>

</body>
</html>
