<?php

class PuzzleManager
{
    public function __construct(
        private \PDO $pdo
    ) {
    }

    public function savePuzzle(array $puzzleData): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO puzzles (grid_size, barriers, numbered_positions, solution_path, difficulty)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $puzzleData['grid_size'],
            json_encode($puzzleData['barriers']),
            json_encode($puzzleData['numbered_positions']),
            json_encode($puzzleData['solution_path']),
            $puzzleData['difficulty']
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getPuzzle(int $puzzleId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM puzzles WHERE id = ? LIMIT 1");
        $stmt->execute([$puzzleId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        return [
            'id' => $result['id'],
            'grid_size' => (int)$result['grid_size'],
            'barriers' => json_decode($result['barriers'], true),
            'numbered_positions' => json_decode($result['numbered_positions'], true),
            'solution_path' => json_decode($result['solution_path'], true),
            'difficulty' => $result['difficulty'],
            'created_date' => $result['created_date']
        ];
    }
}
