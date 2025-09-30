<?php

declare(strict_types=1);

namespace Tests\Support;

use Tests\Support\CanvasCoordinateHelper;
use Tests\WebDriverTester as BaseWebDriverTester;

/**
 * Enhanced WebDriverTester with canvas interaction capabilities
 * Based on successful approaches from the codec branch
 */
class WebDriverTester extends BaseWebDriverTester
{
    private $canvasHelper;
    private $currentGridSize = 5; // Default to 5x5

    /**
     * Wait for JavaScript puzzle generation to complete
     */
    public function waitForPuzzleToLoad($timeout = 10)
    {
        // Wait for canvas and basic JS to be ready
        $this->waitForElement('canvas#board', $timeout);
        $this->wait(2); // Allow JS to initialize
        
        // Initialize canvas helper with current grid size
        $this->initializeCanvasHelper();
    }

    /**
     * Initialize canvas helper with current grid size
     */
    private function initializeCanvasHelper(): void
    {
        // Get current grid size from JavaScript
        $gridSize = $this->executeJS('return typeof N !== "undefined" ? N : 5');
        $this->currentGridSize = (int)$gridSize;
        
        // Create canvas helper (will be used for JS generation)
        $this->canvasHelper = new CanvasCoordinateHelper('board', $this->currentGridSize);
    }

    /**
     * Wait for element to be clickable and then click it
     */
    public function waitAndClick($selector, $timeout = 10)
    {
        $this->waitForElement($selector, $timeout);
        $this->wait(0.5); // Small delay to ensure element is ready
        $this->click($selector);
    }

    /**
     * Click a specific cell on the canvas by row/col coordinates
     */
    public function clickCanvasCell(int $row, int $col): void
    {
        $this->waitForPuzzleToLoad();
        
        if (!$this->canvasHelper->isValidCell($row, $col)) {
            $this->fail("Invalid cell coordinates: ({$row}, {$col}) for {$this->currentGridSize}x{$this->currentGridSize} grid");
        }
        
        $jsCode = $this->canvasHelper->getClickCellJS($row, $col);
        $result = $this->executeJS($jsCode);
        
        $this->comment("Clicked cell ({$row}, {$col}) at pixel coordinates ({$result['x']}, {$result['y']})");
        
        // Small delay to allow the click to be processed
        $this->wait(0.2);
    }

    /**
     * Click a sequence of cells (path) on the canvas
     */
    public function clickCanvasPath(array $path): void
    {
        $this->waitForPuzzleToLoad();
        
        // Validate all cells in path
        foreach ($path as $cell) {
            if (!$this->canvasHelper->isValidCell($cell['row'], $cell['col'])) {
                $this->fail("Invalid cell in path: ({$cell['row']}, {$cell['col']}) for {$this->currentGridSize}x{$this->currentGridSize} grid");
            }
        }
        
        $jsCode = $this->canvasHelper->getClickPathJS($path);
        $result = $this->executeJS($jsCode);
        
        $this->comment("Clicked path of {$result['pathLength']} cells from ({$result['firstCell']['row']}, {$result['firstCell']['col']}) to ({$result['lastCell']['row']}, {$result['lastCell']['col']})");
        
        // Wait for path completion
        $this->wait(count($path) * 0.1 + 1);
    }

    /**
     * Get canvas dimensions and cell information
     */
    public function getCanvasInfo(): array
    {
        $this->waitForPuzzleToLoad();
        
        $jsCode = $this->canvasHelper->getCanvasDimensionsJS();
        return $this->executeJS($jsCode);
    }

    /**
     * Solve a puzzle by executing the solution path via JavaScript (legacy method)
     * This bypasses actual user interaction - use clickCanvasPath() for real testing
     */
    public function solvePuzzleWithJS()
    {
        $this->waitForPuzzleToLoad();
        
        // Execute the solution path
        $this->executeJS('
            if (typeof solutionPath !== "undefined" && solutionPath.length > 0) {
                solutionPath.forEach((cell, index) => {
                    setTimeout(() => {
                        tryAddCell(cell.r, cell.c);
                    }, index * 100);
                });
            }
        ');
    }

    /**
     * Solve a puzzle by clicking the actual solution path
     */
    public function solvePuzzleByClicking(): void
    {
        $this->waitForPuzzleToLoad();
        
        // Get solution path from JavaScript - try multiple ways to access it
        $solutionPath = $this->executeJS('
            // Try different ways to access the solution path
            if (typeof solutionPath !== "undefined" && solutionPath.length > 0) {
                return solutionPath.map(cell => ({row: cell.r, col: cell.c}));
            }
            
            // Try accessing from game object
            if (typeof game !== "undefined" && game.solution && game.solution.length > 0) {
                return game.solution.map(cell => ({row: cell.r, col: cell.c}));
            }
            
            // Try accessing from puzzle data
            if (typeof puzzleData !== "undefined" && puzzleData.solution_path && puzzleData.solution_path.length > 0) {
                return puzzleData.solution_path.map(cell => ({row: cell.r, col: cell.c}));
            }
            
            return [];
        ');
        
        if (empty($solutionPath)) {
            $this->comment('⚠ No solution path available - puzzle may not be fully loaded or solution path not accessible');
            return;
        }
        
        $this->comment('Solving puzzle by clicking solution path of ' . count($solutionPath) . ' cells');
        $this->clickCanvasPath($solutionPath);
    }

    /**
     * Generate a new puzzle and wait for it to load
     */
    public function generateNewPuzzle()
    {
        $this->waitAndClick('#puzzleBtn');
        
        // Wait for puzzle generation to complete
        $this->wait(5);
        
        $this->waitForPuzzleToLoad();
    }

    /**
     * Check if we're on the puzzle game page
     */
    public function seeIAmOnPuzzlePage()
    {
        $this->seeElement('canvas#board');
        $this->see('New');
        $this->see('Solve');
    }

    /**
     * Fill registration form and submit (based on actual form structure)
     */
    public function registerUser($username, $email, $password)
    {
        $this->amOnPage('/login/register.php');
        $this->waitForElement('input[name="username"]', 10);
        
        $this->fillField('input[name="username"]', $username);
        $this->fillField('input[name="pass"]', $password);
        $this->fillField('input[name="pass_verify"]', $password);
        
        $this->click('input[type="submit"]');
    }

    /**
     * Generate a test user with unique credentials
     */
    public function generateTestUser(): array
    {
        $timestamp = time();
        return [
            'username' => "testuser_{$timestamp}",
            'email' => "test_{$timestamp}@example.com",
            'password' => "testpass_{$timestamp}"
        ];
    }

    /**
     * Verify that a puzzle is solved (check for completion indicators)
     */
    public function seePuzzleSolved(): void
    {
        // Check for various completion indicators
        $isSolved = $this->executeJS('
            return (
                (typeof puzzleSolved !== "undefined" && puzzleSolved === true) ||
                (typeof showingSolution !== "undefined" && showingSolution === true) ||
                document.body.textContent.includes("Puzzle Complete") ||
                document.body.textContent.includes("Congratulations")
            );
        ');
        
        if (!$isSolved) {
            $this->fail('Puzzle should be solved but completion indicators not found');
        }
        
        $this->comment('✓ Puzzle solved successfully');
    }

    /**
     * Verify that a puzzle is NOT solved
     */
    public function seePuzzleNotSolved(): void
    {
        $isSolved = $this->executeJS('
            return (
                (typeof puzzleSolved !== "undefined" && puzzleSolved === true) ||
                document.body.textContent.includes("Puzzle Complete") ||
                document.body.textContent.includes("Congratulations")
            );
        ');
        
        if ($isSolved) {
            $this->fail('Puzzle should not be solved but completion indicators found');
        }
        
        $this->comment('✓ Puzzle correctly not solved');
    }
}