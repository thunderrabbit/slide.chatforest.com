<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class DirectActionsCest
{
    public function testDirectSeleniumActions(WebDriverTester $I): void
    {
        $I->comment('=== TESTING DIRECT SELENIUM ACTIONS ===');
        
        // Load puzzle page
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Set up console log monitoring
        $I->executeJS('
            window.gameConsoleLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                const logString = args.join(" ");
                if (logString.includes("tryAddCell") || logString.includes("🖱️")) {
                    window.gameConsoleLogs.push(logString);
                }
                originalLog.apply(console, args);
            };
        ');
        
        // Get canvas position info
        $canvasInfo = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            return {
                left: rect.left,
                top: rect.top,
                width: rect.width,
                height: rect.height,
                cellSize: rect.width / 5
            };
        ');
        
        $I->comment("Canvas: left={$canvasInfo['left']}, top={$canvasInfo['top']}, size={$canvasInfo['width']}x{$canvasInfo['height']}");
        $I->comment("Cell size: {$canvasInfo['cellSize']}");
        
        // Try using WebDriver's action chains directly
        $I->comment('--- Testing WebDriver Action Chains ---');
        
        // Calculate absolute screen coordinates for the "1" cell at (3,1)
        $targetScreenX = $canvasInfo['left'] + (3 * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2);
        $targetScreenY = $canvasInfo['top'] + (1 * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2);
        
        $I->comment("Target '1' cell absolute coordinates: ({$targetScreenX}, {$targetScreenY})");
        
        $I->executeJS('window.gameConsoleLogs = [];');
        
        // Use WebDriver's performOn method to get the WebDriver instance
        $webDriver = $I->webDriver;
        
        // Try direct action chain
        try {
            $action = $webDriver->action();
            $canvas = $webDriver->findElement(\Facebook\WebDriver\WebDriverBy::id('board'));
            
            // Move to specific coordinates within the canvas element
            $action->moveToElement($canvas, (int)(3 * $canvasInfo['cellSize'] / 5), (int)(1 * $canvasInfo['cellSize'] / 5))
                   ->click()
                   ->perform();
                   
            $I->comment("✓ Action chain executed");
            
        } catch (\Exception $e) {
            $I->comment("❌ Action chain failed: " . $e->getMessage());
        }
        
        $I->wait(0.5);
        
        $actionLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        if (!empty($actionLogs)) {
            foreach ($actionLogs as $log) {
                $I->comment("Action result: {$log}");
            }
        }
        
        // Try a completely different approach: dragging from the start position
        $I->comment('--- Testing Drag from Start Position ---');
        
        $I->executeJS('window.gameConsoleLogs = [];');
        
        // Solution path first few steps
        $solutionPath = [
            ["x" => 3, "y" => 1], // "1" 
            ["x" => 4, "y" => 1], // Next step
            ["x" => 4, "y" => 0], // Next step
        ];
        
        try {
            $action = $webDriver->action();
            $canvas = $webDriver->findElement(\Facebook\WebDriver\WebDriverBy::id('board'));
            
            // Start drag from "1" position
            $startOffsetX = (int)(3 * $canvasInfo['cellSize'] / 5);
            $startOffsetY = (int)(1 * $canvasInfo['cellSize'] / 5);
            
            // End drag at second position  
            $endOffsetX = (int)(4 * $canvasInfo['cellSize'] / 5);
            $endOffsetY = (int)(1 * $canvasInfo['cellSize'] / 5);
            
            $I->comment("Attempting drag from offset ({$startOffsetX},{$startOffsetY}) to ({$endOffsetX},{$endOffsetY})");
            
            $action->moveToElement($canvas, $startOffsetX, $startOffsetY)
                   ->clickAndHold()
                   ->moveToElement($canvas, $endOffsetX, $endOffsetY)
                   ->release()
                   ->perform();
                   
            $I->comment("✓ Drag action executed");
            
        } catch (\Exception $e) {
            $I->comment("❌ Drag action failed: " . $e->getMessage());
        }
        
        $I->wait(1);
        
        $dragLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        if (!empty($dragLogs)) {
            $I->comment("Drag results:");
            foreach ($dragLogs as $log) {
                $I->comment("  {$log}");
            }
        } else {
            $I->comment("❌ No drag results captured");
        }
        
        // Final attempt: Use touch events since we know the canvas has touch handlers
        $I->comment('--- Testing Touch Events via WebDriver ---');
        
        $I->executeJS('window.gameConsoleLogs = [];');
        
        try {
            // Use touch actions if available
            $touchAction = $webDriver->getTouch();
            $canvas = $webDriver->findElement(\Facebook\WebDriver\WebDriverBy::id('board'));
            
            $touchAction->down($targetScreenX, $targetScreenY)
                       ->up($targetScreenX, $targetScreenY);
                       
            $I->comment("✓ Touch action executed");
            
        } catch (\Exception $e) {
            $I->comment("Touch actions not available or failed: " . $e->getMessage());
        }
        
        $I->wait(0.5);
        
        $touchLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        if (!empty($touchLogs)) {
            foreach ($touchLogs as $log) {
                $I->comment("Touch result: {$log}");
            }
        }
        
        $I->comment('=== DIRECT ACTIONS TEST COMPLETE ===');
    }
}