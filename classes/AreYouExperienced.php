<?php

class AreYouExperienced
{
    public function __construct(
        private \PDO $di_pdo
    ) {
    }

    public function DesuKa(int $user_id): bool
    {
        $stmt = $this->di_pdo->prepare("SELECT COUNT(DISTINCT puzzle_id) as solve_count FROM solve_times WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $solve_count = $result['solve_count'] ?? 0;

        return $solve_count >= 3;
    }
}
