
<?php

class Poll
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get active polls with their options and vote counts.
     */
    public function getActivePolls(?int $userId = null): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                p.id,
                p.title AS question,
                p.description,
                p.created_at,
                po.id AS option_id,
                po.option_text,
                COUNT(DISTINCT pv.id) AS vote_count,
                MAX(
                    CASE
                        WHEN pv.user_id = :user_id THEN 1
                        ELSE 0
                    END
                ) AS user_voted
            FROM polls p
            INNER JOIN poll_options po
                ON po.poll_id = p.id
            LEFT JOIN poll_votes pv
                ON pv.option_id = po.id
                AND pv.poll_id = p.id
            WHERE p.status = 'active'
            GROUP BY
                p.id,
                p.title,
                p.description,
                p.created_at,
                po.id,
                po.option_text
            ORDER BY p.created_at DESC, po.id ASC
        ");

        $stmt->execute([
            'user_id' => $userId ?? 0
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $polls = [];

        foreach ($rows as $row) {

            $pollId = (int) $row['id'];

            if (!isset($polls[$pollId])) {
                $polls[$pollId] = [
                    'id' => $pollId,
                    'question' => $row['question'],
                    'description' => $row['description'],
                    'created_at' => $row['created_at'],
                    'options' => [],
                    'total_votes' => 0,
                    'user_voted' => false
                ];
            }

            $voteCount = (int) $row['vote_count'];

            $polls[$pollId]['options'][] = [
                'id' => (int) $row['option_id'],
                'option_text' => $row['option_text'],
                'votes' => $voteCount
            ];

            $polls[$pollId]['total_votes'] += $voteCount;

            if ((int) $row['user_voted'] === 1) {
                $polls[$pollId]['user_voted'] = true;
            }
        }

        return array_values($polls);
    }

    /**
     * Find one active poll.
     */
    public function findActiveById(int $pollId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                title,
                description,
                status,
                created_at,
                ends_at
            FROM polls
            WHERE id = :id
              AND status = 'active'
              AND (ends_at IS NULL OR ends_at > NOW())
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $pollId
        ]);

        $poll = $stmt->fetch(PDO::FETCH_ASSOC);

        return $poll ?: null;
    }

    /**
     * Get options belonging to a poll.
     */
    public function getOptions(int $pollId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                poll_id,
                option_text
            FROM poll_options
            WHERE poll_id = :poll_id
            ORDER BY id ASC
        ");

        $stmt->execute([
            'poll_id' => $pollId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check whether a user has already voted.
     */
    public function hasUserVoted(int $pollId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM poll_votes
            WHERE poll_id = :poll_id
              AND user_id = :user_id
        ");

        $stmt->execute([
            'poll_id' => $pollId,
            'user_id' => $userId
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Register a vote.
     */
    public function vote(
        int $pollId,
        int $optionId,
        int $userId
    ): bool {

        if ($this->hasUserVoted($pollId, $userId)) {
            return false;
        }

        $poll = $this->findActiveById($pollId);

        if (!$poll) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM poll_options
            WHERE id = :option_id
              AND poll_id = :poll_id
        ");

        $stmt->execute([
            'option_id' => $optionId,
            'poll_id' => $pollId
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            return false;
        }

        try {

            $stmt = $this->pdo->prepare("
                INSERT INTO poll_votes (
                    poll_id,
                    option_id,
                    user_id
                )
                VALUES (
                    :poll_id,
                    :option_id,
                    :user_id
                )
            ");

            return $stmt->execute([
                'poll_id' => $pollId,
                'option_id' => $optionId,
                'user_id' => $userId
            ]);

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Get poll results and percentages.
     */
    public function getResults(int $pollId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                po.id,
                po.option_text,
                COUNT(pv.id) AS votes
            FROM poll_options po
            LEFT JOIN poll_votes pv
                ON pv.option_id = po.id
                AND pv.poll_id = po.poll_id
            WHERE po.poll_id = :poll_id
            GROUP BY
                po.id,
                po.option_text
            ORDER BY po.id ASC
        ");

        $stmt->execute([
            'poll_id' => $pollId
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalVotes = 0;

        foreach ($results as $result) {
            $totalVotes += (int) $result['votes'];
        }

        foreach ($results as &$result) {

            $votes = (int) $result['votes'];

            $result['votes'] = $votes;

            $result['percentage'] = $totalVotes > 0
                ? round(($votes / $totalVotes) * 100, 1)
                : 0;
        }

        unset($result);

        return [
            'total_votes' => $totalVotes,
            'options' => $results
        ];
    }
}
