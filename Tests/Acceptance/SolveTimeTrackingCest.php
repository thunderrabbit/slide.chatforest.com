<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class SolveTimeTrackingCest
{
    public function testLeaderboardSectionsPresent(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        
        // Check that leaderboard sections are present
        $I->see('Your Times (Local)');
        $I->see('Global Leaderboard');
        $I->seeElement('#anonymous-times');
        $I->seeElement('#global-times');
    }

    public function testSolveTimeAPIEndpointsExist(AcceptanceTester $I): void
    {
        // Test the save_solve_time.php endpoint exists (expects 400 without params, which is fine)
        $I->amOnPage('/save_solve_time.php');
        $I->dontSeeResponseCodeIs(404);
        $I->dontSeeResponseCodeIs(500);
    }

    public function testCheckSolvedAPIEndpointExists(AcceptanceTester $I): void
    {
        // Test the check_solved.php endpoint exists (expects 401/400 without proper auth/params)
        $I->amOnPage('/check_solved.php');
        $I->dontSeeResponseCodeIs(404);
        $I->dontSeeResponseCodeIs(500);
    }

    public function testGetUserTimesAPIEndpointExists(AcceptanceTester $I): void
    {
        // Test the get_user_times.php endpoint exists (expects 400 without params)
        $I->amOnPage('/get_user_times.php');
        $I->dontSeeResponseCodeIs(404);
        $I->dontSeeResponseCodeIs(500);
    }
}