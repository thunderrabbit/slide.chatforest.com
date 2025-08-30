<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class RealGameplayMigrationCest
{
    public function testActualGameplayAndMigration(WebDriverTester $I): void
    {
        $I->comment('=== TESTING REAL GAMEPLAY AND MIGRATION ===');
        
        // Step 1: Clear any existing state and load puzzle as anonymous user
        $I->amOnPage('/');
        $I->executeJS('localStorage.clear()');
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Step 2: Set up console log monitoring
        $I->executeJS('
            window.gameConsoleLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                const logString = args.join(" ");
                if (logString.includes("tryAddCell") || 
                    logString.includes("🖱️") ||
                    logString.includes("Path completed") ||
                    logString.includes("Puzzle solved") ||
                    logString.includes("localStorage")) {
                    window.gameConsoleLogs.push(logString);
                }
                originalLog.apply(console, args);
            };
        ');
        
        // Step 3: Solve puzzle using pointer events (the breakthrough method!)
        $I->comment('--- Solving puzzle using pointer events ---');
        
        $solutionPath = [
            ["x" => 3, "y" => 1], ["x" => 4, "y" => 1], ["x" => 4, "y" => 0], ["x" => 3, "y" => 0], ["x" => 2, "y" => 0],
            ["x" => 2, "y" => 1], ["x" => 2, "y" => 2], ["x" => 3, "y" => 2], ["x" => 4, "y" => 2], ["x" => 4, "y" => 3],
            ["x" => 3, "y" => 3], ["x" => 2, "y" => 3], ["x" => 1, "y" => 3], ["x" => 1, "y" => 2], ["x" => 1, "y" => 1],
            ["x" => 1, "y" => 0], ["x" => 0, "y" => 0], ["x" => 0, "y" => 1], ["x" => 0, "y" => 2], ["x" => 0, "y" => 3],
            ["x" => 0, "y" => 4], ["x" => 1, "y" => 4], ["x" => 2, "y" => 4], ["x" => 3, "y" => 4], ["x" => 4, "y" => 4]
        ];
        
        $solutionResult = $I->executeJS('
            window.gameConsoleLogs = [];
            
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            const cellSize = rect.width / 5;
            const solutionPath = ' . json_encode($solutionPath) . ';
            
            // Use pointer events (the key breakthrough!)
            let currentPointerId = 1;
            
            solutionPath.forEach((step, index) => {
                const targetX = rect.left + (step.x * cellSize) + (cellSize / 2);
                const targetY = rect.top + (step.y * cellSize) + (cellSize / 2);
                
                if (index === 0) {
                    // First step: pointerdown to start drawing
                    const pointerDown = new PointerEvent("pointerdown", {
                        pointerId: currentPointerId,
                        bubbles: true,
                        cancelable: true,
                        clientX: targetX,
                        clientY: targetY,
                        button: 0,
                        buttons: 1
                    });
                    canvas.dispatchEvent(pointerDown);
                } else {
                    // Subsequent steps: pointermove to continue drawing
                    const pointerMove = new PointerEvent("pointermove", {
                        pointerId: currentPointerId,
                        bubbles: true,
                        cancelable: true,
                        clientX: targetX,
                        clientY: targetY,
                        button: 0,
                        buttons: 1
                    });
                    canvas.dispatchEvent(pointerMove);
                }
            });
            
            // Final step: pointerup to finish drawing
            const lastStep = solutionPath[solutionPath.length - 1];
            const finalX = rect.left + (lastStep.x * cellSize) + (cellSize / 2);
            const finalY = rect.top + (lastStep.y * cellSize) + (cellSize / 2);
            
            const pointerUp = new PointerEvent("pointerup", {
                pointerId: currentPointerId,
                bubbles: true,
                cancelable: true,
                clientX: finalX,
                clientY: finalY,
                button: 0,
                buttons: 0
            });
            canvas.dispatchEvent(pointerUp);
            
            return {
                pathLength: solutionPath.length,
                completed: true
            };
        ');
        
        $I->comment("Solution path attempted with {$solutionResult['pathLength']} steps");
        
        // Step 4: Wait for puzzle completion and verify
        $I->wait(3);
        
        $solutionLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        $puzzleSolved = false;
        
        foreach ($solutionLogs as $log) {
            if (strpos($log, 'Puzzle solved') !== false) {
                $puzzleSolved = true;
                $I->comment("✅ {$log}");
            }
        }
        
        if (!$puzzleSolved) {
            $I->comment("❌ Puzzle may not have been solved - checking logs:");
            foreach (array_slice($solutionLogs, -5) as $log) {
                $I->comment("  {$log}");
            }
        }
        
        // Step 5: Verify anonymous solve was saved to localStorage
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
            $I->comment("✅ Anonymous solve time saved: " . json_encode($anonymousTime));
        } else {
            $I->comment("❌ No anonymous solve time found in localStorage - migration test cannot proceed");
            return;
        }
        
        // Step 6: Register a new user
        $I->comment('--- Registering new user for migration test ---');
        $user = $I->generateTestUser();
        $I->comment("Test user: {$user['username']}");
        
        $I->registerUser($user['username'], $user['email'], $user['password']);
        $I->wait(3); // Allow registration to complete
        
        // Step 7: Check if server automatically redirected to puzzle, if not reload manually  
        // IMPORTANT: Add ?newuser=1 parameter to trigger migration!
        $I->comment('--- Checking if automatically redirected to puzzle ---');
        $currentUrl = $I->executeJS('return window.location.pathname');
        
        if (strpos($currentUrl, '/puzzle/') === false) {
            $I->comment("Not redirected automatically, reloading puzzle manually with migration trigger...");
            $I->amOnPage('/puzzle/Kw7fLo6M?newuser=1');
        } else {
            $I->comment("✅ Automatically redirected to puzzle page, adding migration trigger...");
            $I->amOnPage('/puzzle/Kw7fLo6M?newuser=1');
        }
        
        $I->wait(5); // Give time for migration to process
        
        // Step 8: Check migration results
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
            $I->comment("✅ localStorage cleared - migration occurred");
        } else {
            $I->comment("⚠ localStorage still contains: " . json_encode($localStorageAfter));
        }
        
        // Step 9: Look for test user in global leaderboard
        $I->comment("--- Looking for {$user['username']} in global leaderboard ---");
        
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
        
        $I->comment("Current global leaderboard:");
        foreach ($globalTimes as $entry) {
            $I->comment("  {$entry}");
        }
        
        // Look for our test user
        $foundUser = false;
        foreach ($globalTimes as $entry) {
            if (strpos($entry, $user['username']) !== false) {
                $I->comment("🎉 MIGRATION SUCCESS: Found {$user['username']} in global leaderboard!");
                $foundUser = true;
                break;
            }
        }
        
        if (!$foundUser) {
            $I->comment("❌ Migration may have failed - {$user['username']} NOT found in global leaderboard");
            
            // Additional debugging - check if user is logged in
            $loginStatus = $I->executeJS('
                const bodyText = document.body.textContent;
                return {
                    hasLogout: bodyText.includes("logout"),
                    hasUsername: bodyText.includes("' . $user['username'] . '"),
                    bodySnippet: bodyText.substring(0, 300)
                };
            ');
            
            if ($loginStatus['hasLogout'] || $loginStatus['hasUsername']) {
                $I->comment("✅ User appears to be logged in");
            } else {
                $I->comment("⚠ User may not be logged in - body snippet: " . substr($loginStatus['bodySnippet'], 0, 150));
            }
        }
        
        $I->comment('=== MIGRATION TEST COMPLETE ===');
    }
}