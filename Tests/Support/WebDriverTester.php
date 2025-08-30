<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
*/
class WebDriverTester extends \Codeception\Actor
{
    use _generated\WebDriverTesterActions;

    /**
     * Wait for JavaScript puzzle generation to complete
     */
    public function waitForPuzzleToLoad($timeout = 10)
    {
        // Wait for canvas and basic JS to be ready instead of puzzleData
        $this->waitForElement('canvas#board', $timeout);
        $this->wait(2); // Allow JS to initialize
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
     * Solve a puzzle by executing the solution path via JavaScript
     */
    public function solvePuzzleWithJS()
    {
        // Wait for puzzle to load
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
     * Generate a new puzzle and wait for it to load
     */
    public function generateNewPuzzle()
    {
        $this->waitAndClick('#puzzleBtn');
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
}