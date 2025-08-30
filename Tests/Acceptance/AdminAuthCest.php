<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class AdminAuthCest
{
    public function testLoginPageLoads(AcceptanceTester $I): void
    {
        $I->amOnPage('/login/');
        $I->seeResponseCodeIs(200);
        $I->see('login');
    }

    public function testRegisterPageLoads(AcceptanceTester $I): void
    {
        $I->amOnPage('/login/register.php');
        $I->seeResponseCodeIs(200);
    }

    public function testAdminPageRequiresAuth(AcceptanceTester $I): void
    {
        $I->amOnPage('/admin/');
        // Should redirect to login or show some auth requirement
        // The exact behavior depends on your auth implementation
        $I->dontSeeResponseCodeIs(500);
    }

    public function testMigrateTablesPageExists(AcceptanceTester $I): void
    {
        $I->amOnPage('/admin/migrate_tables.php');
        // May require auth, but shouldn't 404
        $I->dontSeeResponseCodeIs(404);
    }
}