<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class WebDriver extends \Codeception\Module
{
    /**
     * Wait for JavaScript condition with better error handling
     */
    public function waitForJSCondition($condition, $timeout = 10): void
    {
        $webDriver = $this->getModule('WebDriver');
        $webDriver->waitForJS($condition, $timeout);
    }

    /**
     * Generate a random test user
     */
    public function generateTestUser(): array
    {
        $timestamp = time();
        return [
            'username' => "testuser_{$timestamp}",
            'email' => "test_{$timestamp}@example.com",
            'password' => 'TestPassword123!'
        ];
    }

    /**
     * Take a screenshot for debugging (saved to Tests/_output/)
     */
    public function takeDebugScreenshot(string $name): void
    {
        $webDriver = $this->getModule('WebDriver');
        $webDriver->makeScreenshot($name);
    }
}