<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Acceptance extends \Codeception\Module
{
    /**
     * Custom helper method to generate a test puzzle code
     */
    public function generateTestPuzzleCode(): string
    {
        $chars = 'abcdefghjkmnopqrstuvwxyzACDEFHJKLMNPQRTUVWXY34679';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /**
     * Helper to wait for specific text to appear (with retry logic)
     */
    public function waitForTextWithRetry(string $text, int $timeout = 30, int $retries = 3): void
    {
        $attempt = 0;
        while ($attempt < $retries) {
            try {
                $this->getModule('PhpBrowser')->waitForText($text, $timeout);
                return;
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt >= $retries) {
                    throw $e;
                }
                $this->getModule('PhpBrowser')->wait(1);
            }
        }
    }
}