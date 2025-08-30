<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class DebugMigrationCest
{
    public function testMigrationWithDetailedLogging(WebDriverTester $I): void
    {
        $I->comment('=== DEBUGGING MIGRATION PROCESS ===');
        
        // Step 1: Clear state and solve puzzle anonymously
        $I->amOnPage('/');
        $I->executeJS('localStorage.clear()');
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Quick solve with enhanced logging
        $I->executeJS('
            // Enhanced console logging
            window.migrationLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                const logString = args.join(" ");
                originalLog.apply(console, args);
                
                // Capture migration-related logs
                if (logString.includes("Migrating") || 
                    logString.includes("migration") ||
                    logString.includes("saveAnonymousTime") ||
                    logString.includes("save_solve_time") ||
                    logString.includes("localStorage") ||
                    logString.includes("newuser") ||
                    logString.includes("💾") ||
                    logString.includes("🔄")) {
                    window.migrationLogs.push(logString);
                }
            };
        ');
        
        $solutionResult = $I->executeJS('
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
            
            return true;
        ');
        
        $I->wait(2);
        
        // Verify anonymous time saved
        $anonymousTime = $I->executeJS('
            return localStorage.getItem("slide_times_131");
        ');
        
        $I->comment("Anonymous time saved: " . ($anonymousTime ?: 'NONE'));
        
        // Step 2: Register user
        $user = $I->generateTestUser();
        $I->comment("Registering user: {$user['username']}");
        
        $I->registerUser($user['username'], $user['email'], $user['password']);
        $I->wait(2);
        
        // Step 3: Load puzzle with migration trigger and monitor the process
        $I->comment('--- Loading puzzle with ?newuser=1 to trigger migration ---');
        $I->amOnPage('/puzzle/Kw7fLo6M?newuser=1');
        
        // Wait a moment for the page to load, then check migration logs
        $I->wait(3);
        
        $migrationLogs = $I->executeJS('return window.migrationLogs || []');
        
        $I->comment('--- Migration Process Logs ---');
        if (!empty($migrationLogs)) {
            foreach ($migrationLogs as $log) {
                $I->comment("  {$log}");
            }
        } else {
            $I->comment("  No migration logs captured");
        }
        
        // Check what functions exist for migration
        $migrationFunctions = $I->executeJS('
            return {
                migrateAnonymousTimes: typeof migrateAnonymousTimes !== "undefined",
                username: "' . $user['username'] . '",
                currentUrl: window.location.href,
                urlParams: new URLSearchParams(window.location.search).toString(),
                hasNewUserParam: new URLSearchParams(window.location.search).has("newuser")
            };
        ');
        
        $I->comment('--- Migration Environment ---');
        $I->comment("Migration function exists: " . ($migrationFunctions['migrateAnonymousTimes'] ? 'Yes' : 'No'));
        $I->comment("Current URL: {$migrationFunctions['currentUrl']}");
        $I->comment("URL params: {$migrationFunctions['urlParams']}");
        $I->comment("Has newuser param: " . ($migrationFunctions['hasNewUserParam'] ? 'Yes' : 'No'));
        
        // Try to manually trigger migration if it didn\'t happen
        if ($migrationFunctions['migrateAnonymousTimes']) {
            $I->comment('--- Manually triggering migration ---');
            
            $manualMigrationResult = $I->executeJS('
                // Clear logs and trigger migration manually
                window.migrationLogs = [];
                
                // Temporarily add some fake localStorage data to test migration
                localStorage.setItem("slide_times_131", "[{\\"solve_time_ms\\":999,\\"completed_at\\":\\"2025-01-30T12:00:00.000Z\\"}]");
                
                // Call migration function
                migrateAnonymousTimes();
                
                return {
                    called: true,
                    localStorageAfter: localStorage.getItem("slide_times_131")
                };
            ');
            
            $I->comment("Manual migration triggered: " . ($manualMigrationResult['called'] ? 'Yes' : 'No'));
            $I->comment("LocalStorage after manual migration: " . ($manualMigrationResult['localStorageAfter'] ?: 'CLEARED'));
            
            // Wait for manual migration to process
            $I->wait(5);
            
            $manualMigrationLogs = $I->executeJS('return window.migrationLogs || []');
            
            $I->comment('--- Manual Migration Logs ---');
            foreach ($manualMigrationLogs as $log) {
                $I->comment("  {$log}");
            }
        }
        
        // Final check of leaderboard
        $I->wait(2);
        
        $finalLeaderboard = $I->executeJS('
            const globalSection = document.getElementById("global-times");
            if (!globalSection) return {error: "No global-times section found"};
            
            const timeEntries = globalSection.querySelectorAll(".time-entry");
            const times = [];
            timeEntries.forEach((entry) => {
                const rank = entry.querySelector(".rank")?.textContent || "";
                const time = entry.querySelector(".time")?.textContent || "";
                const username = entry.querySelector(".username")?.textContent || "";
                const date = entry.querySelector(".date")?.textContent || "";
                times.push(`${rank} ${time} ${username} ${date}`.trim());
            });
            
            return {
                innerHTML: globalSection.innerHTML.substring(0, 500),
                timeEntries: times,
                hasNoTimesMessage: globalSection.textContent.includes("No times recorded")
            };
        ');
        
        $I->comment('--- Final Leaderboard State ---');
        $I->comment("Has 'No times recorded' message: " . ($finalLeaderboard['hasNoTimesMessage'] ? 'Yes' : 'No'));
        $I->comment("Number of time entries found: " . count($finalLeaderboard['timeEntries']));
        
        if (!empty($finalLeaderboard['timeEntries'])) {
            foreach ($finalLeaderboard['timeEntries'] as $entry) {
                $I->comment("  Entry: {$entry}");
            }
        }
        
        if ($finalLeaderboard['hasNoTimesMessage']) {
            $I->comment("Leaderboard HTML: " . substr($finalLeaderboard['innerHTML'], 0, 200));
        }
        
        $I->comment('=== MIGRATION DEBUG COMPLETE ===');
    }
}