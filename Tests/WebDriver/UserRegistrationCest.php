<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class UserRegistrationCest
{
    public function testRegistrationFormLoads(WebDriverTester $I): void
    {
        $I->amOnPage('/login/register.php');
        
        // Check that registration form elements are present
        $I->waitForElement('input[name="username"], #username', 10);
        $I->seeElement('input[type="password"]');
        $I->seeElement('input[type="email"], input[name="email"]');
        $I->seeElement('input[type="submit"], button[type="submit"]');
    }

    public function testUserRegistration(WebDriverTester $I): void
    {
        $user = $I->generateTestUser();
        
        $I->amOnPage('/login/register.php');
        $I->waitForElement('input[name="username"], #username', 10);
        
        // Fill registration form
        $I->fillField('input[name="username"], #username', $user['username']);
        $I->fillField('input[name="email"], #email', $user['email']);
        $I->fillField('input[name="password"], #password', $user['password']);
        
        // Look for password confirmation field (common pattern)
        try {
            $I->seeElement('input[name="password_confirm"], input[name="password_confirmation"], #password_confirm');
            $I->fillField('input[name="password_confirm"], input[name="password_confirmation"], #password_confirm', $user['password']);
        } catch (\Exception $e) {
            // Password confirmation field might not exist
        }
        
        // Submit form
        $I->click('input[type="submit"], button[type="submit"]');
        
        // Wait for response
        $I->wait(2);
        
        // Check for success indicators (adjust based on actual registration flow)
        try {
            // Look for common success patterns
            $I->see('success');
        } catch (\Exception $e) {
            // Or check that we're not still on the registration page with errors
            $I->dontSee('error');
            $I->dontSee('failed');
        }
    }

    public function testLoginFormLoads(WebDriverTester $I): void
    {
        $I->amOnPage('/login/');
        
        // Check login form elements
        $I->waitForElement('input[name="username"], input[name="email"], #username', 10);
        $I->seeElement('input[type="password"]');
        $I->seeElement('input[type="submit"], button[type="submit"]');
    }

    public function testNavigationBetweenLoginAndRegister(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        
        // Navigate to register
        $I->click('a[href*="register"]');
        $I->waitForElement('input[name="username"], #username', 10);
        
        // Navigate to login
        $I->amOnPage('/');
        $I->click('a[href*="login"]');
        $I->waitForElement('input[type="password"]', 10);
    }
}