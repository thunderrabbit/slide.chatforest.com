<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class InteractivePuzzleGameCest
{
    public function testPuzzleGenerationWithJS(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        $I->seeIAmOnPuzzlePage();
        
        // Generate new puzzle and wait for JS to complete
        $I->generateNewPuzzle();
        
        // Verify puzzle data exists in JavaScript
        $puzzleExists = $I->executeJS('return typeof puzzleData !== "undefined"');
        $I->assertTrue($puzzleExists, 'Puzzle data should be generated');
    }

    public function testSolutionButtonToggle(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        $I->waitForPuzzleToLoad();
        
        // Click solution button and verify it changes
        $I->waitAndClick('#solutionBtn');
        $I->wait(1); // Allow solution animation to start
        
        // Check if solution is being shown (button text or state change)
        $showingSolution = $I->executeJS('return showingSolution');
        $I->assertTrue($showingSolution, 'Solution should be visible after clicking Solve button');
    }

    public function testDifficultyChange(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        $I->waitForPuzzleToLoad();
        
        // Change difficulty and generate new puzzle
        $I->selectOption('#difficulty', 'hard');
        $I->generateNewPuzzle();
        
        // Verify difficulty was applied
        $difficulty = $I->executeJS('return document.getElementById("difficulty").value');
        $I->assertEquals('hard', $difficulty);
    }

    public function testGridSizeChange(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        $I->waitForPuzzleToLoad();
        
        // Change grid size
        $I->selectOption('#gridSize', '6');
        $I->generateNewPuzzle();
        
        // Verify grid size was applied
        $gridSize = $I->executeJS('return N'); // N is the grid size variable
        $I->assertEquals(6, $gridSize);
    }

    public function testSpecificPuzzleLoadingWithJS(WebDriverTester $I): void
    {
        $I->amOnPage('/puzzle/abc12345');
        $I->waitForPuzzleToLoad();
        
        // Verify we're on a puzzle page with JS working
        $I->seeIAmOnPuzzlePage();
        
        // Verify puzzle code is set
        $puzzleCode = $I->executeJS('return puzzleCode');
        $I->assertEquals('abc12345', $puzzleCode);
    }
}