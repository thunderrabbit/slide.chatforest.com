<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class BasicPuzzleGameCest
{
    public function testHomepageLoads(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->seeResponseCodeIs(200);
        $I->seeIAmOnPuzzlePage();
        $I->see('Slide Practice');
    }

    public function testPuzzleElementsPresent(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->seePuzzlePageElements();
        
        // Check all the main game elements are present
        $I->see('Slide Practice');
        $I->seeElement('canvas#board');
        $I->see('Drag one finger to draw');
    }

    public function testPuzzleButtons(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->seeElement('#puzzleBtn');
        $I->seeElement('#solutionBtn');
        $I->see('New');
        $I->see('Solve');
    }

    public function testSpecificPuzzleLoads(AcceptanceTester $I): void
    {
        // Test accessing a puzzle via URL (using example code from CLAUDE.md)
        $I->amOnPage('/puzzle/abc12345');
        $I->seeResponseCodeIs(200);
        $I->seeIAmOnPuzzlePage();
    }

    public function testDifficultyAndGridControls(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        
        // Check if difficulty and grid size selects exist
        $I->seeElement('#gridSize');
        $I->seeElement('#difficulty');
        
        // Check option values
        $I->see('5×5');
        $I->see('6×6');
        $I->see('Easy');
        $I->see('Medium');
        $I->see('Hard');
    }
}