<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class LeaderboardInspectionCest
{
    public function testInspectPuzzleLeaderboards(WebDriverTester $I): void
    {
        // Load the specific puzzle we've been testing with
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        $I->comment('=== INSPECTING PUZZLE Kw7fLo6M LEADERBOARDS ===');
        
        // Check Global Leaderboard section
        $I->comment('--- Global Leaderboard ---');
        try {
            $I->seeElement('#global-times');
            
            // Get all the time entries from global leaderboard
            $globalTimes = $I->executeJS('
                const globalSection = document.getElementById("global-times");
                const timeEntries = globalSection.querySelectorAll(".time-entry");
                const times = [];
                timeEntries.forEach((entry, index) => {
                    const rank = entry.querySelector(".rank")?.textContent || "N/A";
                    const time = entry.querySelector(".time")?.textContent || "N/A"; 
                    const username = entry.querySelector(".username")?.textContent || "N/A";
                    const date = entry.querySelector(".date")?.textContent || "N/A";
                    times.push(`${rank} ${time} ${username} ${date}`);
                });
                return times;
            ');
            
            if (empty($globalTimes)) {
                $I->comment('No global times found');
            } else {
                foreach ($globalTimes as $index => $timeEntry) {
                    $entryNum = $index + 1;
                    $I->comment("Global #{$entryNum}: {$timeEntry}");
                }
            }
            
        } catch (\Exception $e) {
            $I->comment('Global leaderboard section not found or empty');
        }
        
        // Check Anonymous Times section  
        $I->comment('--- Anonymous Times ---');
        try {
            $I->seeElement('#anonymous-times');
            
            // Check if there are any anonymous times displayed
            $anonymousContent = $I->executeJS('
                const anonymousSection = document.getElementById("anonymous-times");
                return anonymousSection.innerText;
            ');
            
            $I->comment("Anonymous section content: " . trim($anonymousContent));
            
            // Look for specific time entries in anonymous section
            $anonymousTimes = $I->executeJS('
                const anonymousSection = document.getElementById("anonymous-times");
                const timeEntries = anonymousSection.querySelectorAll(".time-entry");
                const times = [];
                timeEntries.forEach((entry, index) => {
                    const rank = entry.querySelector(".rank")?.textContent || "N/A";
                    const time = entry.querySelector(".time")?.textContent || "N/A";
                    times.push(`${rank} ${time}`);
                });
                return times;
            ');
            
            if (empty($anonymousTimes)) {
                $I->comment('No anonymous time entries found');
            } else {
                foreach ($anonymousTimes as $index => $timeEntry) {
                    $entryNum = $index + 1;
                    $I->comment("Anonymous #{$entryNum}: {$timeEntry}");
                }
            }
            
        } catch (\Exception $e) {
            $I->comment('Anonymous times section not found');
        }
        
        // Check what's in localStorage for this puzzle
        $I->comment('--- localStorage Check ---');
        $localStorageTime = $I->executeJS('
            return localStorage.getItem("solve_time_Kw7fLo6M");
        ');
        
        if ($localStorageTime) {
            $I->comment("localStorage has solve time: {$localStorageTime} seconds");
        } else {
            $I->comment('No solve time found in localStorage for this puzzle');
        }
        
        // Check all solve_time_ keys in localStorage
        $allLocalTimes = $I->executeJS('
            const times = {};
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith("solve_time_")) {
                    times[key] = localStorage.getItem(key);
                }
            }
            return times;
        ');
        
        if (!empty($allLocalTimes)) {
            $I->comment('All localStorage solve times:');
            foreach ($allLocalTimes as $key => $time) {
                $I->comment("  {$key}: {$time}s");
            }
        } else {
            $I->comment('No solve times found in localStorage');
        }
        
        $I->comment('=== INSPECTION COMPLETE ===');
    }
}