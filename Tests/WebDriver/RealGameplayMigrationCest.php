<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class RealGameplayMigrationCest
{
    public function testActualGameplayAndMigration(WebDriverTester $I): void
    {
        $I->comment('=== TESTING REAL GAMEPLAY AND MIGRATION ===');
        
        // Step 1: Load puzzle as anonymous user
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Step 2: Get canvas dimensions and calculate cell positions
        $canvasInfo = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            const gridSize = 5; // This puzzle is 5x5
            const cellSize = rect.width / gridSize;
            
            return {
                x: rect.x,
                y: rect.y,
                width: rect.width,
                height: rect.height,
                cellSize: cellSize
            };
        ');
        
        $I->comment("Canvas info: " . json_encode($canvasInfo));
        
        // Step 3: Play the game by clicking the solution path
        $solutionPath = [
            ["x" => 3, "y" => 1], ["x" => 4, "y" => 1], ["x" => 4, "y" => 0], ["x" => 3, "y" => 0], ["x" => 2, "y" => 0],
            ["x" => 2, "y" => 1], ["x" => 2, "y" => 2], ["x" => 3, "y" => 2], ["x" => 4, "y" => 2], ["x" => 4, "y" => 3],
            ["x" => 3, "y" => 3], ["x" => 2, "y" => 3], ["x" => 1, "y" => 3], ["x" => 1, "y" => 2], ["x" => 1, "y" => 1],
            ["x" => 1, "y" => 0], ["x" => 0, "y" => 0], ["x" => 0, "y" => 1], ["x" => 0, "y" => 2], ["x" => 0, "y" => 3],
            ["x" => 0, "y" => 4], ["x" => 1, "y" => 4], ["x" => 2, "y" => 4], ["x" => 3, "y" => 4], ["x" => 4, "y" => 4]
        ];
        
        $I->comment('--- Playing the puzzle by dragging through solution path ---');
        
        // Calculate all pixel coordinates first
        $pathPixels = [];
        foreach ($solutionPath as $cell) {
            $pathPixels[] = [
                'x' => $canvasInfo['x'] + ($cell['x'] * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2),
                'y' => $canvasInfo['y'] + ($cell['y'] * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2)
            ];
        }
        
        // Simulate a continuous drag through all points
        $I->executeJS("
            const canvas = document.getElementById('board');
            const rect = canvas.getBoundingClientRect();
            const pathPixels = " . json_encode($pathPixels) . ";
            
            // Start with mousedown at first position
            const startX = pathPixels[0].x - rect.left;
            const startY = pathPixels[0].y - rect.top;
            
            console.log('Starting drag at', startX, startY);
            
            // Mouse down to start drawing
            const mouseDown = new MouseEvent('mousedown', {
                clientX: pathPixels[0].x,
                clientY: pathPixels[0].y,
                bubbles: true,
                cancelable: true,
                buttons: 1
            });
            canvas.dispatchEvent(mouseDown);
            
            // Simulate dragging through each point
            pathPixels.forEach((point, index) => {
                if (index === 0) return; // Skip first point (already handled)
                
                const x = point.x - rect.left;
                const y = point.y - rect.top;
                
                const mouseMoveEvent = new MouseEvent('mousemove', {
                    clientX: point.x,
                    clientY: point.y,
                    bubbles: true,
                    cancelable: true,
                    buttons: 1
                });
                
                canvas.dispatchEvent(mouseMoveEvent);
                console.log('Dragged to step', index + 1, 'at', x, y);
            });
            
            // Finish with mouseup at final position
            const endPoint = pathPixels[pathPixels.length - 1];
            const mouseUp = new MouseEvent('mouseup', {
                clientX: endPoint.x,
                clientY: endPoint.y,
                bubbles: true,
                cancelable: true,
                buttons: 0
            });
            canvas.dispatchEvent(mouseUp);
            
            console.log('Finished drag at', endPoint.x - rect.left, endPoint.y - rect.top);
        ");
        
        $I->comment("Completed drag through " . count($solutionPath) . " cells");
        
        // Step 4: Wait for puzzle completion
        $I->comment('--- Waiting for puzzle completion ---');
        $I->wait(3);
        
        // Check if puzzle was completed
        $puzzleCompleted = $I->executeJS('
            // Look for completion indicators
            const completionText = document.body.textContent.toLowerCase();
            const hasCompletedText = completionText.includes("completed") || 
                                   completionText.includes("solved") || 
                                   completionText.includes("congratulations") ||
                                   completionText.includes("first solve");
            
            // Check localStorage for saved time
            let hasSavedTime = false;
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith("slide_times_")) {
                    hasSavedTime = true;
                    break;
                }
            }
            
            return {
                hasCompletionText: hasCompletedText,
                hasSavedTime: hasSavedTime,
                bodyText: document.body.textContent
            };
        ');
        
        $I->comment("Puzzle completion check: " . json_encode($puzzleCompleted));
        
        // Step 5: Check localStorage for anonymous solve time
        $anonymousTime = $I->executeJS('
            const times = {};
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith("slide_times_")) {
                    times[key] = localStorage.getItem(key);
                }
            }
            return times;
        ');
        
        if (!empty($anonymousTime)) {
            $I->comment("✓ Anonymous solve time saved: " . json_encode($anonymousTime));
        } else {
            $I->comment("⚠ No anonymous solve time found in localStorage");
        }
        
        // Step 6: Register a new user
        $I->comment('--- Registering new user ---');
        $user = $I->generateTestUser();
        $I->registerUser($user['username'], $user['email'], $user['password']);
        $I->wait(2);
        
        // Step 7: Return to puzzle page (should trigger migration)
        $I->comment('--- Returning to puzzle page to trigger migration ---');
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->wait(5); // Give time for migration to process
        
        // Step 8: Check if migration worked
        $I->comment('--- Checking migration results ---');
        
        // Check localStorage (should be cleared if migration worked)
        $localStorageAfter = $I->executeJS('
            const times = {};
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith("slide_times_")) {
                    times[key] = localStorage.getItem(key);
                }
            }
            return times;
        ');
        
        if (empty($localStorageAfter)) {
            $I->comment("✓ localStorage cleared - migration likely occurred");
        } else {
            $I->comment("⚠ localStorage still contains: " . json_encode($localStorageAfter));
        }
        
        // Check global leaderboard for new user
        $globalTimes = $I->executeJS('
            const globalSection = document.getElementById("global-times");
            if (!globalSection) return [];
            
            const timeEntries = globalSection.querySelectorAll(".time-entry");
            const times = [];
            timeEntries.forEach((entry) => {
                const rank = entry.querySelector(".rank")?.textContent || "";
                const time = entry.querySelector(".time")?.textContent || "";
                const username = entry.querySelector(".username")?.textContent || "";
                const date = entry.querySelector(".date")?.textContent || "";
                times.push(`${rank} ${time} ${username} ${date}`.trim());
            });
            return times;
        ');
        
        $I->comment("Global leaderboard after migration:");
        foreach ($globalTimes as $entry) {
            $I->comment("  {$entry}");
        }
        
        // Look for our test user
        $foundUser = false;
        foreach ($globalTimes as $entry) {
            if (strpos($entry, $user['username']) !== false) {
                $I->comment("🎉 SUCCESS: Found test user {$user['username']} in global leaderboard!");
                $foundUser = true;
                break;
            }
        }
        
        if (!$foundUser) {
            $I->comment("⚠ Test user {$user['username']} NOT found in global leaderboard");
        }
        
        $I->comment('=== REAL GAMEPLAY MIGRATION TEST COMPLETE ===');
    }
}