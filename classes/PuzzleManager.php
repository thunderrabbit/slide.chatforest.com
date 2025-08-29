<?php

class PuzzleManager
{
    public function __construct(
        private \PDO $pdo
    ) {
    }

    public function savePuzzle(array $puzzleData): array
    {
        $codeGenerator = new \PuzzleCodeGenerator($this->pdo);
        $puzzleCode = $codeGenerator->generateUniqueCode();

        $stmt = $this->pdo->prepare("
            INSERT INTO puzzles (puzzle_code, grid_size, barriers, numbered_positions, solution_path, difficulty)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $puzzleCode,
            $puzzleData['grid_size'],
            json_encode($puzzleData['barriers']),
            json_encode($puzzleData['numbered_positions']),
            json_encode($puzzleData['solution_path']),
            $puzzleData['difficulty']
        ]);

        return [
            'id' => (int)$this->pdo->lastInsertId(),
            'puzzle_code' => $puzzleCode
        ];
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
            'puzzle_code' => $result['puzzle_code'],
            'grid_size' => (int)$result['grid_size'],
            'barriers' => json_decode($result['barriers'], true),
            'numbered_positions' => json_decode($result['numbered_positions'], true),
            'solution_path' => json_decode($result['solution_path'], true),
            'difficulty' => $result['difficulty'],
            'created_date' => $result['created_date']
        ];
    }

    public function getPuzzleByCode(string $puzzleCode): ?array
    {
        if (!\PuzzleCodeGenerator::isValidCode($puzzleCode)) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM puzzles WHERE puzzle_code = ? LIMIT 1");
        $stmt->execute([$puzzleCode]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        return [
            'id' => $result['id'],
            'puzzle_code' => $result['puzzle_code'],
            'grid_size' => (int)$result['grid_size'],
            'barriers' => json_decode($result['barriers'], true),
            'numbered_positions' => json_decode($result['numbered_positions'], true),
            'solution_path' => json_decode($result['solution_path'], true),
            'difficulty' => $result['difficulty'],
            'created_date' => $result['created_date']
        ];
    }

    public function getPuzzleByIdOrCode(string $identifier): ?array
    {
        // Try as puzzle code first (8 chars, clean charset)
        if (\PuzzleCodeGenerator::isValidCode($identifier)) {
            return $this->getPuzzleByCode($identifier);
        }

        // Try as numeric ID (for backwards compatibility)
        if (is_numeric($identifier)) {
            return $this->getPuzzle((int)$identifier);
        }

        return null;
    }
}
