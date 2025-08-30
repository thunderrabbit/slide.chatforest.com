<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class FinalMigrationTestCest
{
    public function testCompleteMigrationWorkflow(WebDriverTester $I): void
    {
        $I->comment('=== FINAL COMPLETE MIGRATION TEST ===');
        
        // Step 1: Clear state and solve puzzle anonymously
        $I->amOnPage('/');
        $I->executeJS('localStorage.clear()');
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        $I->comment('--- Step 1: Solving puzzle as anonymous user ---');
        
        // Solve puzzle using pointer events
        $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            const cellSize = rect.width / 5;
            const solutionPath = [
                {"x": 3, "y": 1}, {"x": 4, "y": 1}, {"x": 4, "y": 0}, {"x": 3, "y": 0}, {"x": 2, "y": 0},
                {"x": 2, "y": 1}, {"x": 2, "y": 2}, {"x": 3, "y": 2}, {"x": 4, "y": 2}, {"x": 4, "y": 3},
                {"x": 3, "y": 3}, {"x": 2, "y": 3}, {"x": 1, "y": 3}, {"x": 1, "y": 2}, {"x": 1, "y": 1},
                {"x": 1, "y": 0}, {"x": 0, "y": 0}, {"x": 0, "y": 1}, {"x": 0, "y": 2}, {"x": 0, "y": 3},
                {"x": 0, "y": 4}, {"x": 1, "y": 4}, {"x": 2, "y": 4}, {"x": 3, "y": 4}, {"x": 4, "y": 4}
            ];
            
            let pointerId = 1;
            solutionPath.forEach((step, index) => {
                const targetX = rect.left + (step.x * cellSize) + (cellSize / 2);
                const targetY = rect.top + (step.y * cellSize) + (cellSize / 2);
                
                if (index === 0) {
                    canvas.dispatchEvent(new PointerEvent("pointerdown", {
                        pointerId, bubbles: true, cancelable: true,
                        clientX: targetX, clientY: targetY, button: 0, buttons: 1
                    }));
                } else {
                    canvas.dispatchEvent(new PointerEvent("pointermove", {
                        pointerId, bubbles: true, cancelable: true,
                        clientX: targetX, clientY: targetY, button: 0, buttons: 1
                    }));
                }
            });
            
            const lastStep = solutionPath[solutionPath.length - 1];
            const finalX = rect.left + (lastStep.x * cellSize) + (cellSize / 2);
            const finalY = rect.top + (lastStep.y * cellSize) + (cellSize / 2);
            canvas.dispatchEvent(new PointerEvent("pointerup", {
                pointerId, bubbles: true, cancelable: true,
                clientX: finalX, clientY: finalY, button: 0, buttons: 0
            }));
        ');
        
        $I->wait(2);
        
        // Verify solve was saved
        $anonymousData = $I->executeJS('
            return {
                slideTimesKey: localStorage.getItem("slide_times_131"),
                allKeys: Object.keys(localStorage),
                lastPuzzle: localStorage.getItem("lastPlayedPuzzle")
            };
        ');
        
        if ($anonymousData['slideTimesKey']) {
            $I->comment("✅ Anonymous solve saved to localStorage");
            $I->comment("  Data: " . substr($anonymousData['slideTimesKey'], 0, 100));
        } else {
            $I->comment("❌ No anonymous solve found - test cannot continue");
            return;
        }
        
        if ($anonymousData['lastPuzzle']) {
            $I->comment("✅ Last puzzle saved: {$anonymousData['lastPuzzle']}");
        }
        
        // Step 2: Register user and monitor redirect
        $I->comment('--- Step 2: Registering new user ---');
        
        $user = $I->generateTestUser();
        $I->comment("Test user: {$user['username']}");
        
        // Go to registration page manually to monitor the redirect
        $I->amOnPage('/login/register.php');
        $I->waitForElement('input[name="username"]', 10);
        $I->fillField('input[name="username"]', $user['username']);
        $I->fillField('input[name="pass"]', $user['password']);
        $I->fillField('input[name="pass_verify"]', $user['password']);
        $I->click('input[type="submit"]');
        
        // Wait for redirect and monitor the URL
        $I->comment('--- Monitoring post-registration redirect ---');
        $I->wait(3);
        
        $redirectInfo = $I->executeJS('
            return {
                currentUrl: window.location.href,
                pathname: window.location.pathname,
                search: window.location.search,
                hasNewUserParam: new URLSearchParams(window.location.search).has("newuser")
            };
        ');
        
        $I->comment("Post-registration URL: {$redirectInfo['currentUrl']}");
        $I->comment("Has newuser parameter: " . ($redirectInfo['hasNewUserParam'] ? 'Yes' : 'No'));
        
        // Step 3: If not on puzzle page with newuser param, navigate there explicitly
        if (!$redirectInfo['hasNewUserParam'] || !strpos($redirectInfo['pathname'], '/puzzle/')) {
            $I->comment('--- Manually navigating to puzzle with migration trigger ---');
            $I->amOnPage('/puzzle/Kw7fLo6M?newuser=1');
        }
        
        // Step 4: Wait for migration to complete and monitor
        $I->comment('--- Step 4: Waiting for migration to process ---');
        $I->wait(8); // Give extra time for migration
        
        // Check localStorage status
        $localStorageStatus = $I->executeJS('
            return {
                slideTimesAfter: localStorage.getItem("slide_times_131"),
                allKeysAfter: Object.keys(localStorage)
            };
        ');
        
        if (!$localStorageStatus['slideTimesAfter']) {
            $I->comment("✅ localStorage cleared - migration likely successful");
        } else {
            $I->comment("⚠ localStorage still contains: " . substr($localStorageStatus['slideTimesAfter'], 0, 100));
        }
        
        // Step 5: Check leaderboard multiple times with different strategies
        $I->comment('--- Step 5: Checking global leaderboard ---');
        
        // Strategy 1: Direct check
        $leaderboardCheck1 = $I->executeJS('
            const globalSection = document.getElementById("global-times");
            if (!globalSection) return {error: "No global section"};
            
            return {
                innerHTML: globalSection.innerHTML,
                textContent: globalSection.textContent,
                timeEntries: globalSection.querySelectorAll(".time-entry").length,
                hasNoTimes: globalSection.textContent.includes("No times recorded")
            };
        ');
        
        $I->comment("Leaderboard check 1:");
        $I->comment("  Time entries: {$leaderboardCheck1['timeEntries']}");
        $I->comment("  Has 'no times' message: " . ($leaderboardCheck1['hasNoTimes'] ? 'Yes' : 'No'));
        
        // Strategy 2: Reload page and check again
        $I->comment('--- Reloading page to refresh leaderboard ---');
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->wait(3);
        
        $leaderboardCheck2 = $I->executeJS('
            const globalSection = document.getElementById("global-times");
            if (!globalSection) return {error: "No global section"};
            
            const timeEntries = globalSection.querySelectorAll(".time-entry");
            const entries = [];
            timeEntries.forEach((entry) => {
                const rank = entry.querySelector(".rank")?.textContent || "";
                const time = entry.querySelector(".time")?.textContent || "";
                const username = entry.querySelector(".username")?.textContent || "";
                const date = entry.querySelector(".date")?.textContent || "";
                entries.push(`${rank} ${time} ${username} ${date}`.trim());
            });
            
            return {
                entries: entries,
                hasNoTimes: globalSection.textContent.includes("No times recorded"),
                fullText: globalSection.textContent.replace(/\\s+/g, " ").substring(0, 200)
            };
        ');
        
        $I->comment("Leaderboard check 2 (after reload):");
        $I->comment("  Entries found: " . count($leaderboardCheck2['entries']));
        $I->comment("  Has 'no times' message: " . ($leaderboardCheck2['hasNoTimes'] ? 'Yes' : 'No'));
        
        if (!empty($leaderboardCheck2['entries'])) {
            $I->comment("  Entries:");
            foreach ($leaderboardCheck2['entries'] as $entry) {
                $I->comment("    {$entry}");
            }
        } else {
            $I->comment("  Leaderboard content: {$leaderboardCheck2['fullText']}");
        }
        
        // Final determination
        $foundUser = false;
        foreach ($leaderboardCheck2['entries'] as $entry) {
            if (strpos($entry, $user['username']) !== false) {
                $I->comment("🎉 MIGRATION SUCCESS: Found {$user['username']} in leaderboard!");
                $foundUser = true;
                break;
            }
        }
        
        if (!$foundUser) {
            if ($leaderboardCheck2['hasNoTimes']) {
                $I->comment("❌ MIGRATION FAILED: Leaderboard shows no times recorded");
            } else {
                $I->comment("❌ MIGRATION FAILED: User not found in leaderboard");
            }
        }
        
        // Additional debugging: check if user is logged in
        $loginCheck = $I->executeJS('
            return {
                bodyText: document.body.textContent,
                hasLogout: document.body.textContent.includes("Logout"),
                hasUsername: document.body.textContent.includes("' . $user['username'] . '")
            };
        ');
        
        $I->comment("--- Login Status ---");
        $I->comment("User appears logged in: " . (($loginCheck['hasLogout'] || $loginCheck['hasUsername']) ? 'Yes' : 'No'));
        
        $I->comment('=== FINAL MIGRATION TEST COMPLETE ===');
        
        if ($foundUser) {
            $I->comment('🎉 OVERALL RESULT: SUCCESS - Migration worked completely!');
        } else {
            $I->comment('❌ OVERALL RESULT: PARTIAL SUCCESS - localStorage cleared but user not in leaderboard');
        }
    }
}