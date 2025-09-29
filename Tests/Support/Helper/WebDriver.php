<?php

declare(strict_types=1);

namespace Helper;

/**
 * WebDriver Helper
 * Additional helper methods for WebDriver tests
 */
class WebDriver extends \Codeception\Module
{
    /**
     * Wait for JavaScript to complete
     */
    public function waitForJS(string $script, int $timeout = 10): void
    {
        $this->getModule('WebDriver')->waitForJS($script, $timeout);
    }

    /**
     * Execute JavaScript and return result
     */
    public function executeJS(string $script)
    {
        return $this->getModule('WebDriver')->executeJS($script);
    }

    /**
     * Get current URL
     */
    public function getCurrentUrl(): string
    {
        return $this->getModule('WebDriver')->executeJS('return window.location.href');
    }

    /**
     * Get page title
     */
    public function getPageTitle(): string
    {
        return $this->getModule('WebDriver')->executeJS('return document.title');
    }

    /**
     * Check if element exists
     */
    public function elementExists(string $selector): bool
    {
        try {
            $this->getModule('WebDriver')->seeElement($selector);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get element text content
     */
    public function getElementText(string $selector): string
    {
        return $this->getModule('WebDriver')->executeJS("return document.querySelector('{$selector}')?.textContent || ''");
    }

    /**
     * Scroll to element
     */
    public function scrollToElement(string $selector): void
    {
        $this->getModule('WebDriver')->executeJS("document.querySelector('{$selector}').scrollIntoView()");
    }

    /**
     * Take screenshot with timestamp
     */
    public function takeScreenshot(string $name = ''): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = $name ? "{$name}_{$timestamp}" : "screenshot_{$timestamp}";
        $this->getModule('WebDriver')->makeScreenshot($filename);
    }
}