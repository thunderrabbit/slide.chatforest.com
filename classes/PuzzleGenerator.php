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
            $expectedCells = $this->gridSize * $this->gridSize;
            $actualCells = count($this->solution);
            error_log("Path validation failed: expected $expectedCells cells, got $actualCells");
            error_log("Path length: " . count($this->solution));
            if (!empty($this->solution)) {
                error_log("First few path cells: " . json_encode(array_slice($this->solution, 0, 5)));
                error_log("Last few path cells: " . json_encode(array_slice($this->solution, -5)));
            }
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

        // Try multiple strategies for all grid sizes
        $strategies = [
            'fast' => [$this, 'generateHamiltonianPathFast'],
            'backtrack' => [$this, 'generateHamiltonianPathBacktrack'],
            'improved_backtrack' => [$this, 'generateImprovedBacktrack']
        ];

        foreach ($strategies as $strategyName => $strategyMethod) {
            error_log("Trying $strategyName strategy for {$this->gridSize}x{$this->gridSize} grid");

            for ($attempt = 0; $attempt < 3; $attempt++) {
                if (microtime(true) - $startTime > $this->timeoutSeconds) {
                    break 2; // Break out of both loops
                }

                $path = $strategyMethod($startTime);

                if (count($path) === $totalCells) {
                    error_log("$strategyName strategy succeeded on attempt " . ($attempt + 1));
                    return $path;
                }

                error_log("$strategyName attempt " . ($attempt + 1) . " generated " . count($path) . " cells out of $totalCells");
            }
        }

        // Last resort: use improved spiral pattern
        error_log("All strategies failed, using improved spiral pattern");
        return $this->generateImprovedSpiralPath();
    }

    private function generateHamiltonianPathFast(float $startTime): array
    {
        $totalCells = $this->gridSize * $this->gridSize;

        // Try multiple attempts with different strategies
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $path = $this->attemptHamiltonianPathImproved($startTime, $attempt);
            if (count($path) === $totalCells) {
                error_log("Fast Hamiltonian path successful on attempt " . ($attempt + 1));
                return $path;
            }
            error_log("Attempt " . ($attempt + 1) . " generated " . count($path) . " cells out of " . $totalCells);
        }

        // If all attempts fail, fall back to backtracking
        error_log("Fast algorithm failed, falling back to backtracking");
        return $this->generateHamiltonianPathBacktrack($startTime);
    }

    private function attemptHamiltonianPathImproved(float $startTime, int $strategy): array
    {
        $totalCells = $this->gridSize * $this->gridSize;
        $path = [];
        $visited = [];

        // Different starting strategies with better distribution
        $startingPositions = [
            [0, 0], // Corner
            [intval($this->gridSize / 2), intval($this->gridSize / 2)], // Center
            [0, intval($this->gridSize / 2)], // Edge
            [intval($this->gridSize / 2), 0], // Edge
            [random_int(0, $this->gridSize - 1), random_int(0, $this->gridSize - 1)] // Random
        ];

        $startPos = $startingPositions[$strategy % count($startingPositions)];
        $r = $startPos[0];
        $c = $startPos[1];

        $path[] = ['x' => $c, 'y' => $r];
        $visited[$this->key($r, $c)] = true;

        // Generate path with improved bridging and less spiral-like movement
        while (count($path) < $totalCells) {
            if (microtime(true) - $startTime > $this->timeoutSeconds) {
                break;
            }

            $neighbors = $this->getUnvisitedNeighborsImproved($r, $c, $visited);

            if (empty($neighbors)) {
                // Try multiple bridging strategies
                $bridged = false;

                // Strategy 1: Find nearest unvisited with better heuristics
                $unvisited = $this->findNearestUnvisitedImproved($r, $c, $visited);
                if ($unvisited) {
                    $bridge = $this->createBridgeImproved($r, $c, $unvisited['r'], $unvisited['c'], $visited);
                    if (!empty($bridge)) {
                        foreach ($bridge as $cell) {
                            $path[] = ['x' => $cell['c'], 'y' => $cell['r']];
                            $visited[$this->key($cell['r'], $cell['c'])] = true;
                        }
                        $r = $bridge[count($bridge) - 1]['r'];
                        $c = $bridge[count($bridge) - 1]['c'];
                        $bridged = true;
                    }
                }

                // Strategy 2: If bridging failed, try to find any unvisited cell
                if (!$bridged) {
                    $unvisited = $this->findAnyUnvisited($visited);
                    if ($unvisited) {
                        $bridge = $this->createBridgeImproved($r, $c, $unvisited['r'], $unvisited['c'], $visited);
                        if (!empty($bridge)) {
                            foreach ($bridge as $cell) {
                                $path[] = ['x' => $cell['c'], 'y' => $cell['r']];
                                $visited[$this->key($cell['r'], $cell['c'])] = true;
                            }
                            $r = $bridge[count($bridge) - 1]['r'];
                            $c = $bridge[count($bridge) - 1]['c'];
                            $bridged = true;
                        }
                    }
                }

                if (!$bridged) {
                    break; // Can't continue
                }
            } else {
                // Choose best neighbor based on improved heuristics
                $next = $neighbors[0]; // Already sorted by getUnvisitedNeighborsImproved
                $path[] = ['x' => $next['c'], 'y' => $next['r']];
                $visited[$this->key($next['r'], $next['c'])] = true;
                $r = $next['r'];
                $c = $next['c'];
            }
        }

        return $path;
    }

    private function attemptHamiltonianPath(float $startTime, int $strategy): array
    {
        $totalCells = $this->gridSize * $this->gridSize;
        $path = [];
        $visited = [];

        // Different starting strategies
        if ($strategy === 0) {
            // Random start
            $r = random_int(0, $this->gridSize - 1);
            $c = random_int(0, $this->gridSize - 1);
        } elseif ($strategy === 1) {
            // Corner start
            $r = 0;
            $c = 0;
        } else {
            // Center start
            $r = intval($this->gridSize / 2);
            $c = intval($this->gridSize / 2);
        }

        $path[] = ['x' => $c, 'y' => $r];
        $visited[$this->key($r, $c)] = true;

        // Generate path with improved bridging
        while (count($path) < $totalCells) {
            if (microtime(true) - $startTime > $this->timeoutSeconds) {
                break;
            }

            $neighbors = $this->getUnvisitedNeighbors($r, $c, $visited);

            if (empty($neighbors)) {
                // Try multiple bridging strategies
                $bridged = false;

                // Strategy 1: Find nearest unvisited
                $unvisited = $this->findNearestUnvisited($r, $c, $visited);
                if ($unvisited) {
                    $bridge = $this->createBridgeImproved($r, $c, $unvisited['r'], $unvisited['c'], $visited);
                    if (!empty($bridge)) {
                        foreach ($bridge as $cell) {
                            $path[] = ['x' => $cell['c'], 'y' => $cell['r']];
                            $visited[$this->key($cell['r'], $cell['c'])] = true;
                        }
                        $r = $bridge[count($bridge) - 1]['r'];
                        $c = $bridge[count($bridge) - 1]['c'];
                        $bridged = true;
                    }
                }

                // Strategy 2: If bridging failed, try to find any unvisited cell
                if (!$bridged) {
                    $unvisited = $this->findAnyUnvisited($visited);
                    if ($unvisited) {
                        $bridge = $this->createBridgeImproved($r, $c, $unvisited['r'], $unvisited['c'], $visited);
                        if (!empty($bridge)) {
                            foreach ($bridge as $cell) {
                                $path[] = ['x' => $cell['c'], 'y' => $cell['r']];
                                $visited[$this->key($cell['r'], $cell['c'])] = true;
                            }
                            $r = $bridge[count($bridge) - 1]['r'];
                            $c = $bridge[count($bridge) - 1]['c'];
                            $bridged = true;
                        }
                    }
                }

                if (!$bridged) {
                    break; // Can't continue
                }
            } else {
                // Choose random neighbor
                $next = $neighbors[array_rand($neighbors)];
                $path[] = ['x' => $next['c'], 'y' => $next['r']];
                $visited[$this->key($next['r'], $next['c'])] = true;
                $r = $next['r'];
                $c = $next['c'];
            }
        }

        return $path;
    }

    private function generateHamiltonianPathBacktrack(float $startTime): array
    {
        // Use the original backtracking algorithm for smaller grids
        $totalCells = $this->gridSize * $this->gridSize;
        $path = [];
        $visited = [];

        // Start from a random position
        $r = random_int(0, $this->gridSize - 1);
        $c = random_int(0, $this->gridSize - 1);

        $path[] = ['x' => $c, 'y' => $r];
        $visited[$this->key($r, $c)] = true;

        // Backtracking algorithm
        if ($this->backtrack($r, $c, $path, $visited, $startTime)) {
            return $path;
        }

        // If backtracking fails, return spiral
        return $this->generateSpiralPath();
    }

    private function backtrack(int $r, int $c, array &$path, array &$visited, float $startTime): bool
    {
        if (count($path) === $this->gridSize * $this->gridSize) {
            return true; // Complete path found
        }

        if (microtime(true) - $startTime > $this->timeoutSeconds) {
            return false; // Timeout
        }

        $neighbors = $this->getUnvisitedNeighbors($r, $c, $visited);

        // Shuffle neighbors for randomness
        shuffle($neighbors);

        foreach ($neighbors as $neighbor) {
            $nr = $neighbor['r'];
            $nc = $neighbor['c'];

            $path[] = ['x' => $nc, 'y' => $nr];
            $visited[$this->key($nr, $nc)] = true;

            if ($this->backtrack($nr, $nc, $path, $visited, $startTime)) {
                return true;
            }

            // Backtrack
            array_pop($path);
            unset($visited[$this->key($nr, $nc)]);
        }

        return false;
    }

    private function findNearestUnvisitedImproved(int $r, int $c, array $visited): ?array
    {
        // Find the nearest unvisited cell using BFS with better heuristics
        $queue = [['r' => $r, 'c' => $c, 'dist' => 0]];
        $seen = [];
        $seen[$this->key($r, $c)] = true;
        $candidates = [];

        while (!empty($queue)) {
            $current = array_shift($queue);

            if (!isset($visited[$this->key($current['r'], $current['c'])])) {
                $candidates[] = ['r' => $current['r'], 'c' => $current['c'], 'dist' => $current['dist']];
                // Continue searching for more candidates within same distance
                if (count($candidates) >= 3) break;
            }

            $directions = [
                ['r' => -1, 'c' => 0], ['r' => 1, 'c' => 0],
                ['r' => 0, 'c' => -1], ['r' => 0, 'c' => 1]
            ];

            foreach ($directions as $dir) {
                $nr = $current['r'] + $dir['r'];
                $nc = $current['c'] + $dir['c'];
                $key = $this->key($nr, $nc);

                if ($this->inBounds($nr, $nc) && !isset($seen[$key])) {
                    $seen[$key] = true;
                    $queue[] = ['r' => $nr, 'c' => $nc, 'dist' => $current['dist'] + 1];
                }
            }
        }

        if (empty($candidates)) return null;

        // Choose candidate that's least likely to create spiral patterns
        // Prefer candidates that are not in corners or edges
        usort($candidates, function($a, $b) {
            $centerR = intval($this->gridSize / 2);
            $centerC = intval($this->gridSize / 2);

            $distA = abs($a['r'] - $centerR) + abs($a['c'] - $centerC);
            $distB = abs($b['r'] - $centerR) + abs($b['c'] - $centerC);

            return $distA - $distB;
        });

        return ['r' => $candidates[0]['r'], 'c' => $candidates[0]['c']];
    }

    private function findNearestUnvisited(int $r, int $c, array $visited): ?array
    {
        // Find the nearest unvisited cell using BFS
        $queue = [['r' => $r, 'c' => $c, 'dist' => 0]];
        $seen = [];
        $seen[$this->key($r, $c)] = true;

        while (!empty($queue)) {
            $current = array_shift($queue);

            if (!isset($visited[$this->key($current['r'], $current['c'])])) {
                return ['r' => $current['r'], 'c' => $current['c']];
            }

            $directions = [
                ['r' => -1, 'c' => 0], ['r' => 1, 'c' => 0],
                ['r' => 0, 'c' => -1], ['r' => 0, 'c' => 1]
            ];

            foreach ($directions as $dir) {
                $nr = $current['r'] + $dir['r'];
                $nc = $current['c'] + $dir['c'];
                $key = $this->key($nr, $nc);

                if ($this->inBounds($nr, $nc) && !isset($seen[$key])) {
                    $seen[$key] = true;
                    $queue[] = ['r' => $nr, 'c' => $nc, 'dist' => $current['dist'] + 1];
                }
            }
        }

        return null;
    }

    private function findAnyUnvisited(array $visited): ?array
    {
        // Find any unvisited cell (fallback when nearest search fails)
        for ($r = 0; $r < $this->gridSize; $r++) {
            for ($c = 0; $c < $this->gridSize; $c++) {
                if (!isset($visited[$this->key($r, $c)])) {
                    return ['r' => $r, 'c' => $c];
                }
            }
        }
        return null;
    }

    private function createBridgeImproved(int $fromR, int $fromC, int $toR, int $toC, array $visited): array
    {
        // Improved bridge creation with multiple pathfinding strategies
        $path = [];
        $r = $fromR;
        $c = $fromC;
        $maxSteps = $this->gridSize * 2; // Prevent infinite loops
        $steps = 0;

        while (($r !== $toR || $c !== $toC) && $steps < $maxSteps) {
            $steps++;

            // Try direct path first
            $dr = $toR > $r ? 1 : ($toR < $r ? -1 : 0);
            $dc = $toC > $c ? 1 : ($toC < $c ? -1 : 0);

            $moved = false;

            // Try primary direction
            if ($dr !== 0 && $this->inBounds($r + $dr, $c) && !isset($visited[$this->key($r + $dr, $c)])) {
                $r += $dr;
                $moved = true;
            } elseif ($dc !== 0 && $this->inBounds($r, $c + $dc) && !isset($visited[$this->key($r, $c + $dc)])) {
                $c += $dc;
                $moved = true;
            }

            // If primary direction failed, try alternative directions
            if (!$moved) {
                $directions = [
                    ['r' => -1, 'c' => 0], ['r' => 1, 'c' => 0],
                    ['r' => 0, 'c' => -1], ['r' => 0, 'c' => 1]
                ];
                shuffle($directions);

                foreach ($directions as $dir) {
                    $nr = $r + $dir['r'];
                    $nc = $c + $dir['c'];
                    if ($this->inBounds($nr, $nc) && !isset($visited[$this->key($nr, $nc)])) {
                        $r = $nr;
                        $c = $nc;
                        $moved = true;
                        break;
                    }
                }
            }

            if (!$moved) {
                // Can't find a path, return what we have
                break;
            }

            $path[] = ['r' => $r, 'c' => $c];
        }

        return $path;
    }

    private function createBridge(int $fromR, int $fromC, int $toR, int $toC, array $visited): array
    {
        // Create a simple path from 'from' to 'to' avoiding visited cells
        $path = [];
        $r = $fromR;
        $c = $fromC;

        while ($r !== $toR || $c !== $toC) {
            $dr = $toR > $r ? 1 : ($toR < $r ? -1 : 0);
            $dc = $toC > $c ? 1 : ($toC < $c ? -1 : 0);

            // Try to move in the direction of the target
            if ($dr !== 0 && $this->inBounds($r + $dr, $c) && !isset($visited[$this->key($r + $dr, $c)])) {
                $r += $dr;
            } elseif ($dc !== 0 && $this->inBounds($r, $c + $dc) && !isset($visited[$this->key($r, $c + $dc)])) {
                $c += $dc;
            } else {
                // Try alternative directions
                $directions = [
                    ['r' => -1, 'c' => 0], ['r' => 1, 'c' => 0],
                    ['r' => 0, 'c' => -1], ['r' => 0, 'c' => 1]
                ];
                shuffle($directions);

                $moved = false;
                foreach ($directions as $dir) {
                    $nr = $r + $dir['r'];
                    $nc = $c + $dir['c'];
                    if ($this->inBounds($nr, $nc) && !isset($visited[$this->key($nr, $nc)])) {
                        $r = $nr;
                        $c = $nc;
                        $moved = true;
                        break;
                    }
                }

                if (!$moved) break; // Can't find a path
            }

            $path[] = ['r' => $r, 'c' => $c];
        }

        return $path;
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

    private function generateImprovedBacktrack(float $startTime): array
    {
        $totalCells = $this->gridSize * $this->gridSize;

        // Try multiple starting positions with improved backtracking
        $startingPositions = [
            [0, 0], // Corner
            [intval($this->gridSize / 2), intval($this->gridSize / 2)], // Center
            [0, intval($this->gridSize / 2)], // Edge
            [intval($this->gridSize / 2), 0], // Edge
        ];

        foreach ($startingPositions as $pos) {
            $path = $this->backtrackPathImproved($pos[0], $pos[1], $startTime);
            if (count($path) === $totalCells) {
                return $path;
            }
        }

        return [];
    }

    private function backtrackPathImproved(int $startR, int $startC, float $startTime): array
    {
        $path = [];
        $visited = [];
        $totalCells = $this->gridSize * $this->gridSize;

        // Use iterative approach with better heuristics
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

            // Get unvisited neighbors with improved ordering
            $neighbors = $this->getUnvisitedNeighborsImproved($r, $c, $visited);

            if (empty($neighbors)) {
                // Backtrack: remove current cell from path and visited
                if (count($path) > 1) {
                    $removed = array_pop($path);
                    unset($visited[$this->key($removed['y'], $removed['x'])]);
                }
                continue;
            }

            // Try first neighbor (best heuristic)
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

    private function getUnvisitedNeighborsImproved(int $r, int $c, array $visited): array
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

        // Sort neighbors by distance from center (prefer center moves)
        $centerR = intval($this->gridSize / 2);
        $centerC = intval($this->gridSize / 2);

        usort($neighbors, function($a, $b) use ($centerR, $centerC) {
            $distA = abs($a['r'] - $centerR) + abs($a['c'] - $centerC);
            $distB = abs($b['r'] - $centerR) + abs($b['c'] - $centerC);
            return $distA - $distB;
        });

        return $neighbors;
    }

    private function generateImprovedSpiralPath(): array
    {
        // Create a more natural-looking spiral that's less obvious
        $path = [];
        $visited = [];
        $totalCells = $this->gridSize * $this->gridSize;

        // Start from a random position instead of corner
        $startR = random_int(0, $this->gridSize - 1);
        $startC = random_int(0, $this->gridSize - 1);

        $path[] = ['x' => $startC, 'y' => $startR];
        $visited[$this->key($startR, $startC)] = true;

        $currentR = $startR;
        $currentC = $startC;

        // Use a more complex pattern that's less spiral-like
        $directions = [
            ['r' => 0, 'c' => 1],   // right
            ['r' => 1, 'c' => 0],   // down
            ['r' => 0, 'c' => -1],  // left
            ['r' => -1, 'c' => 0]   // up
        ];

        $directionIndex = 0;
        $stepsInDirection = 1;
        $stepsTaken = 0;

        while (count($path) < $totalCells) {
            $direction = $directions[$directionIndex];

            for ($i = 0; $i < $stepsInDirection && count($path) < $totalCells; $i++) {
                $newR = $currentR + $direction['r'];
                $newC = $currentC + $direction['c'];

                if ($this->inBounds($newR, $newC) && !isset($visited[$this->key($newR, $newC)])) {
                    $currentR = $newR;
                    $currentC = $newC;
                    $path[] = ['x' => $currentC, 'y' => $currentR];
                    $visited[$this->key($currentR, $currentC)] = true;
                } else {
                    break;
                }
            }

            // Change direction
            $directionIndex = ($directionIndex + 1) % 4;

            // Increase steps every two direction changes
            if ($stepsTaken % 2 === 1) {
                $stepsInDirection++;
            }
            $stepsTaken++;
        }

        return $path;
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
