<?php

class BackgroundPuzzleGenerator
{
    private PDO $pdo;
    private int $targetBuffer = 100; // Keep 100 puzzles in buffer
    private int $minBuffer = 20;     // Generate more when below this threshold
    private int $maxGenerationTime = 30; // Max seconds per generation session

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Main method to maintain the puzzle buffer
     * Can be called from cron job or triggered by low buffer
     */
    public function maintainBuffer(): array
    {
        $stats = [];
        $startTime = time();

        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            $generated = $this->generateForDifficulty($difficulty, $startTime);
            $stats[$difficulty] = $generated;

            // Check if we're running out of time
            if (time() - $startTime > $this->maxGenerationTime) {
                break;
            }
        }

        return $stats;
    }

    /**
     * Generate puzzles for a specific difficulty until buffer is full
     */
    private function generateForDifficulty(string $difficulty, int $startTime): int
    {
        $currentCount = $this->getBufferCount($difficulty);

        // If buffer is already full for this difficulty, don't generate
        if ($currentCount >= $this->targetBuffer) {
            return 0;
        }

        // Check if we still have time
        if ((time() - $startTime) >= $this->maxGenerationTime) {
            return 0;
        }

        try {
            $success = $this->generateAndStorePuzzle($difficulty);
            return $success ? 1 : 0;
        } catch (Exception $e) {
            error_log("Background puzzle generation error: " . $e->getMessage());
            $this->recordGenerationStats(7, $difficulty, 0, false, $e->getMessage());
            return 0;
        }
    }

    /**
     * Generate a single puzzle and store it in unseen_sevens table
     */
    private function generateAndStorePuzzle(string $difficulty): bool
    {
        $generator = new PuzzleGenerator(7); // Always 7x7 for now
        $startTime = microtime(true);

        try {
            $puzzleData = $generator->generatePuzzle($difficulty);
            $generationTimeMs = (int)((microtime(true) - $startTime) * 1000);


            // Store in unseen_sevens table
            $stmt = $this->pdo->prepare("
                INSERT INTO unseen_sevens (grid_size, barriers, numbered_positions, solution_path, difficulty, generation_time_ms)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $success = $stmt->execute([
                $puzzleData['grid_size'],
                json_encode($puzzleData['barriers']),
                json_encode($puzzleData['numbered_positions']),
                json_encode($puzzleData['solution_path']),
                $difficulty,
                $generationTimeMs
            ]);

            if ($success) {
                $this->recordGenerationStats(7, $difficulty, $generationTimeMs, true);
                return true;
            }

        } catch (Exception $e) {
            $generationTimeMs = (int)((microtime(true) - $startTime) * 1000);
            $this->recordGenerationStats(7, $difficulty, $generationTimeMs, false, $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * Get current count of puzzles in buffer for a difficulty
     */
    public function getBufferCount(string $difficulty): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM unseen_sevens WHERE difficulty = ?");
        $stmt->execute([$difficulty]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if buffer needs refilling
     */
    public function needsRefill(string $difficulty = null): bool
    {
        if ($difficulty) {
            return $this->getBufferCount($difficulty) < $this->minBuffer;
        }

        // Check if any difficulty is low
        foreach (['easy', 'medium', 'hard'] as $diff) {
            if ($this->getBufferCount($diff) < $this->minBuffer) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a pre-generated puzzle and move it to main puzzles table
     */
    public function getPreGeneratedPuzzle(string $difficulty): ?array
    {
        $this->pdo->beginTransaction();

        try {
            // Get oldest puzzle of this difficulty
            $stmt = $this->pdo->prepare("
                SELECT * FROM unseen_sevens
                WHERE difficulty = ?
                ORDER BY created_at ASC
                LIMIT 1
            ");
            $stmt->execute([$difficulty]);
            $puzzle = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$puzzle) {
                $this->pdo->rollback();
                return null;
            }

            // Generate puzzle code
            $puzzleCode = $this->generatePuzzleCode();

            // Move to main puzzles table
            $stmt = $this->pdo->prepare("
                INSERT INTO puzzles (puzzle_code, grid_size, barriers, numbered_positions, solution_path, difficulty)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $puzzleCode,
                $puzzle['grid_size'],
                $puzzle['barriers'],
                $puzzle['numbered_positions'],
                $puzzle['solution_path'],
                $puzzle['difficulty']
            ]);

            $puzzleId = $this->pdo->lastInsertId();

            // Remove from unseen_sevens
            $deleteStmt = $this->pdo->prepare("DELETE FROM unseen_sevens WHERE unseen_7_id = ?");
            $deleteStmt->execute([$puzzle['unseen_7_id']]);

            $this->pdo->commit();

            // Trigger async refill if buffer is getting low
            if ($this->getBufferCount($difficulty) < $this->minBuffer) {
                $this->triggerAsyncRefill();
            }

            return [
                'puzzle_id' => $puzzleId,
                'puzzle_code' => $puzzleCode,
                'puzzle_data' => [
                    'grid_size' => $puzzle['grid_size'],
                    'barriers' => json_decode($puzzle['barriers'], true),
                    'numbered_positions' => json_decode($puzzle['numbered_positions'], true),
                    'solution_path' => json_decode($puzzle['solution_path'], true),
                    'difficulty' => $puzzle['difficulty']
                ]
            ];

        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Error getting pre-generated puzzle: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Record generation statistics
     */
    private function recordGenerationStats(int $gridSize, string $difficulty, int $timeMs, bool $success, string $error = null): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO puzzle_generation_stats (grid_size, difficulty, generation_time_ms, success, error_message)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$gridSize, $difficulty, $timeMs, $success ? 1 : 0, $error]);
        } catch (Exception $e) {
            error_log("Failed to record generation stats: " . $e->getMessage());
        }
    }

    /**
     * Generate a unique puzzle code using the same algorithm as the main system
     */
    private function generatePuzzleCode(): string
    {
        $chars = 'abcdefghjkmnopqrstuvwxyzACDEFHJKLMNPQRTUVWXY34679';
        $maxAttempts = 100;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }

            // Check if code already exists
            $stmt = $this->pdo->prepare("SELECT puzzle_id FROM puzzles WHERE puzzle_code = ?");
            $stmt->execute([$code]);

            if (!$stmt->fetch()) {
                return $code;
            }
        }

        throw new Exception("Failed to generate unique puzzle code after {$maxAttempts} attempts");
    }

    /**
     * Trigger async refill (would typically use a queue system in production)
     * For now, just log that refill is needed
     */
    private function triggerAsyncRefill(): void
    {
        error_log("Puzzle buffer refill needed - consider running background generation");
        // In production, this could trigger a queue job, webhook, or other async process
    }

    /**
     * Get buffer status for all difficulties
     */
    public function getBufferStatus(): array
    {
        $status = [];
        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            $status[$difficulty] = $this->getBufferCount($difficulty);
        }
        return $status;
    }
}
