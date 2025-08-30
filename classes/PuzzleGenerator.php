<?php

class PuzzleGenerator
{
    private int $gridSize;
    private array $solution = [];
    private array $visited = [];
    private int $maxAttempts = 50;
    private float $timeoutSeconds = 10.0;

    public function __construct(int $gridSize = 7)
    {
        $this->gridSize = $gridSize;
    }

    public function generatePuzzle(string $difficulty = 'medium'): array
    {
        $startTime = microtime(true);

        // Generate Hamiltonian path with timeout
        $this->solution = $this->generateHamiltonianPath($startTime);

        if (empty($this->solution)) {
            throw new \Exception("Failed to generate valid Hamiltonian path within time limit");
        }

        // Validate the solution path
        if (!$this->validateSolutionPath($this->solution)) {
            throw new \Exception("Generated path is invalid");
        }

        // Generate barriers
        $barriers = $this->generateBarriers($difficulty);

        // Place numbered hints
        $numberedPositions = $this->placeNumberedHints($difficulty);

        return [
            'grid_size' => $this->gridSize,
            'barriers' => $barriers,
            'numbered_positions' => $numberedPositions,
            'solution_path' => $this->solution,
            'difficulty' => $difficulty
        ];
    }

    private function generateHamiltonianPath(float $startTime): array
    {
        $totalCells = $this->gridSize * $this->gridSize;

        // Try multiple starting positions
        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            if (microtime(true) - $startTime > $this->timeoutSeconds) {
                break;
            }

            // Random starting position
            $startR = random_int(0, $this->gridSize - 1);
            $startC = random_int(0, $this->gridSize - 1);

            $path = $this->backtrackPath($startR, $startC, $startTime);

            if (count($path) === $totalCells) {
                return $path;
            }
        }

        // Fallback: use spiral pattern if backtracking fails
        return $this->generateSpiralPath();
    }

    private function backtrackPath(int $startR, int $startC, float $startTime): array
    {
        $path = [];
        $visited = [];
        $totalCells = $this->gridSize * $this->gridSize;

        // Stack for iterative backtracking (to avoid recursion depth issues)
        $stack = [['r' => $startR, 'c' => $startC, 'pathIndex' => 0]];
        $path[] = ['x' => $startC, 'y' => $startR];
        $visited[$this->key($startR, $startC)] = true;

        while (!empty($stack)) {
            // Check timeout
            if (microtime(true) - $startTime > $this->timeoutSeconds) {
                return [];
            }

            if (count($path) === $totalCells) {
                return $path; // Found complete path
            }

            $current = array_pop($stack);
            $r = $current['r'];
            $c = $current['c'];

            // Get unvisited neighbors in random order
            $neighbors = $this->getUnvisitedNeighbors($r, $c, $visited);

            if (empty($neighbors)) {
                // Backtrack: remove current cell from path and visited
                if (count($path) > 1) {
                    $removed = array_pop($path);
                    unset($visited[$this->key($removed['y'], $removed['x'])]);
                }
                continue;
            }

            // Try first neighbor
            $next = $neighbors[0];
            $nextKey = $this->key($next['r'], $next['c']);

            // Add to path and mark as visited
            $path[] = ['x' => $next['c'], 'y' => $next['r']];
            $visited[$nextKey] = true;

            // Push current position back to stack for potential backtracking
            $stack[] = $current;

            // Push next position to stack
            $stack[] = ['r' => $next['r'], 'c' => $next['c'], 'pathIndex' => count($path) - 1];
        }

        return $path;
    }

    private function getUnvisitedNeighbors(int $r, int $c, array $visited): array
    {
        $directions = [
            ['r' => -1, 'c' => 0], // up
            ['r' => 1, 'c' => 0],  // down
            ['r' => 0, 'c' => -1], // left
            ['r' => 0, 'c' => 1]   // right
        ];

        $neighbors = [];

        foreach ($directions as $dir) {
            $newR = $r + $dir['r'];
            $newC = $c + $dir['c'];

            if ($this->inBounds($newR, $newC) && !isset($visited[$this->key($newR, $newC)])) {
                $neighbors[] = ['r' => $newR, 'c' => $newC];
            }
        }

        // Shuffle for randomness
        shuffle($neighbors);

        return $neighbors;
    }

    private function generateSpiralPath(): array
    {
        $path = [];
        $r = 0;
        $c = 0;
        $dr = 0;
        $dc = 1;

        for ($i = 0; $i < $this->gridSize * $this->gridSize; $i++) {
            $path[] = ['x' => $c, 'y' => $r];

            // Calculate next position
            $nr = $r + $dr;
            $nc = $c + $dc;

            // If next position is out of bounds or already visited, turn right
            if (!$this->inBounds($nr, $nc) || $this->positionInPath($nr, $nc, $path)) {
                // Turn right: (0,1) -> (1,0) -> (0,-1) -> (-1,0) -> (0,1)
                $newDr = $dc;
                $newDc = -$dr;
                $dr = $newDr;
                $dc = $newDc;
                $nr = $r + $dr;
                $nc = $c + $dc;
            }

            // Update position only if we're not at the last cell
            if ($i < $this->gridSize * $this->gridSize - 1) {
                $r = $nr;
                $c = $nc;
            }
        }

        return $path;
    }

    private function positionInPath(int $r, int $c, array $path): bool
    {
        foreach ($path as $pos) {
            if ($pos['y'] === $r && $pos['x'] === $c) {
                return true;
            }
        }
        return false;
    }

    private function validateSolutionPath(array $path): bool
    {
        $expectedCells = $this->gridSize * $this->gridSize;

        // Check length
        if (count($path) !== $expectedCells) {
            return false;
        }

        // Check bounds and uniqueness
        $visited = [];
        foreach ($path as $pos) {
            if (!$this->inBounds($pos['y'], $pos['x'])) {
                return false;
            }

            $key = $this->key($pos['y'], $pos['x']);
            if (isset($visited[$key])) {
                return false; // Duplicate position
            }
            $visited[$key] = true;
        }

        // Check adjacency
        for ($i = 1; $i < count($path); $i++) {
            $prev = $path[$i - 1];
            $curr = $path[$i];

            if (!$this->areAdjacent($prev['y'], $prev['x'], $curr['y'], $curr['x'])) {
                return false;
            }
        }

        return true;
    }

    private function generateBarriers(string $difficulty): array
    {
        $barriers = [];
        $solutionEdges = $this->getSolutionEdges();

        // Barrier density based on difficulty
        $densityMap = [
            'easy' => 0.08,
            'medium' => 0.12,
            'hard' => 0.16
        ];

        $density = $densityMap[$difficulty] ?? 0.12;
        $maxBarriers = (int)floor(($this->gridSize * $this->gridSize - 1) * $density);

        $attempts = 0;
        while (count($barriers) < $maxBarriers && $attempts < 500) {
            $r1 = random_int(0, $this->gridSize - 1);
            $c1 = random_int(0, $this->gridSize - 1);

            // Pick random adjacent cell
            $directions = [
                ['r' => -1, 'c' => 0],
                ['r' => 1, 'c' => 0],
                ['r' => 0, 'c' => -1],
                ['r' => 0, 'c' => 1]
            ];

            $validDirections = array_filter($directions, function($dir) use ($r1, $c1) {
                return $this->inBounds($r1 + $dir['r'], $c1 + $dir['c']);
            });

            if (!empty($validDirections)) {
                $direction = $validDirections[array_rand($validDirections)];
                $r2 = $r1 + $direction['r'];
                $c2 = $c1 + $direction['c'];

                $edgeKey = $this->edgeKey($r1, $c1, $r2, $c2);

                // Don't block solution path edges
                if (!in_array($edgeKey, $solutionEdges)) {
                    $barriers[] = [
                        'x1' => $c1, 'y1' => $r1,
                        'x2' => $c2, 'y2' => $r2,
                        'type' => ($r1 === $r2) ? 'horizontal' : 'vertical'
                    ];
                }
            }

            $attempts++;
        }

        return $barriers;
    }

    private function getSolutionEdges(): array
    {
        $edges = [];

        for ($i = 0; $i < count($this->solution) - 1; $i++) {
            $curr = $this->solution[$i];
            $next = $this->solution[$i + 1];
            $edges[] = $this->edgeKey($curr['y'], $curr['x'], $next['y'], $next['x']);
        }

        return $edges;
    }

    private function placeNumberedHints(string $difficulty): array
    {
        $numberedPositions = [];
        $pathLength = count($this->solution);

        // Number of hints by difficulty
        $hintMap = [
            'easy' => ['min' => 6, 'max' => 8],
            'medium' => ['min' => 4, 'max' => 6],
            'hard' => ['min' => 3, 'max' => 5]
        ];

        $hintConfig = $hintMap[$difficulty] ?? $hintMap['medium'];
        $maxHints = min($hintConfig['max'], (int)floor($pathLength / 3));
        $hintCount = random_int($hintConfig['min'], $maxHints);

        // Always include start (position 0) and end (last position)
        $hintPositions = [0, $pathLength - 1];

        // Add random positions in between
        while (count($hintPositions) < $hintCount) {
            $randomPos = random_int(1, $pathLength - 2);
            if (!in_array($randomPos, $hintPositions)) {
                $hintPositions[] = $randomPos;
            }
        }

        sort($hintPositions);

        // Place consecutive numbers at these positions
        $hintNumber = 1;
        foreach ($hintPositions as $position) {
            $cell = $this->solution[$position];
            $numberedPositions[(string)$hintNumber] = ['x' => $cell['x'], 'y' => $cell['y']];
            $hintNumber++;
        }

        return $numberedPositions;
    }

    private function inBounds(int $r, int $c): bool
    {
        return $r >= 0 && $r < $this->gridSize && $c >= 0 && $c < $this->gridSize;
    }

    private function areAdjacent(int $r1, int $c1, int $r2, int $c2): bool
    {
        $dr = abs($r1 - $r2);
        $dc = abs($c1 - $c2);
        return ($dr === 1 && $dc === 0) || ($dr === 0 && $dc === 1);
    }

    private function key(int $r, int $c): string
    {
        return "$r,$c";
    }

    private function edgeKey(int $r1, int $c1, int $r2, int $c2): string
    {
        // Normalize edge key so (1,1)-(1,2) is same as (1,2)-(1,1)
        if ($r1 > $r2 || ($r1 === $r2 && $c1 > $c2)) {
            [$r1, $c1, $r2, $c2] = [$r2, $c2, $r1, $c1];
        }
        return "$r1,$c1|$r2,$c2";
    }
}
