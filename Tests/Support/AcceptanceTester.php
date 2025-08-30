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
class AcceptanceTester extends \Codeception\Actor
{
    use _generated\AcceptanceTesterActions;

    /**
     * Helper method to check if user is on a puzzle page
     */
    public function seeIAmOnPuzzlePage()
    {
        $this->seeElement('canvas#board');
        $this->see('New');
        $this->see('Solve');
    }

    /**
     * Helper method to check basic puzzle page elements without JavaScript
     */
    public function seePuzzlePageElements()
    {
        $this->seeElement('canvas#board');
        $this->seeElement('#puzzleBtn');
        $this->seeElement('#solutionBtn');
        $this->seeElement('#gridSize');
        $this->seeElement('#difficulty');
    }
}