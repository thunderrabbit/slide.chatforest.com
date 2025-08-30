<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class ErrorHandlingCest
{
    public function testInvalidPuzzleCodeHandling(AcceptanceTester $I): void
    {
        // Test with invalid puzzle code
        $I->amOnPage('/puzzle/invalid123');
        $I->seeResponseCodeIs(200); // Should handle gracefully, not 404
        $I->seeIAmOnPuzzlePage(); // Should still show game interface
    }

    public function testNonExistentPuzzleHandling(AcceptanceTester $I): void
    {
        // Test with non-existent puzzle code
        $I->amOnPage('/puzzle/xxxxxxxx');
        $I->seeResponseCodeIs(200);
        $I->seeIAmOnPuzzlePage();
    }

    public function testMalformedURLHandling(AcceptanceTester $I): void
    {
        // Test various malformed URLs
        $I->amOnPage('/puzzle/');
        $I->dontSeeResponseCodeIs(500);
        
        $I->amOnPage('/puzzle///');
        $I->dontSeeResponseCodeIs(500);
    }

    public function testPHPErrorsNotVisible(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->dontSeeInSource('Fatal error');
        $I->dontSeeInSource('Warning:');
        $I->dontSeeInSource('Notice:');
        $I->dontSeeInSource('Parse error');
    }
}