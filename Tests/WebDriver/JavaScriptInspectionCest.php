<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class JavaScriptInspectionCest
{
    public function testInspectAvailableJavaScriptFunctions(WebDriverTester $I): void
    {
        // Load puzzle page while logged in to see all available functions
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        $I->comment('=== INSPECTING JAVASCRIPT FUNCTIONS ===');
        
        // Get all global functions
        $allFunctions = $I->executeJS('
            const functions = [];
            for (const key in window) {
                if (typeof window[key] === "function" && !key.startsWith("_")) {
                    functions.push(key);
                }
            }
            return functions.sort();
        ');
        
        $I->comment('--- All Global Functions ---');
        foreach ($allFunctions as $func) {
            $I->comment("  {$func}()");
        }
        
        // Look for solve/time/migration related functions specifically
        $I->comment('--- Functions containing "solve", "time", or "migrate" ---');
        $relevantFunctions = array_filter($allFunctions, function($func) {
            return (stripos($func, 'solve') !== false || 
                    stripos($func, 'time') !== false || 
                    stripos($func, 'migrate') !== false ||
                    stripos($func, 'record') !== false ||
                    stripos($func, 'save') !== false ||
                    stripos($func, 'check') !== false);
        });
        
        if (empty($relevantFunctions)) {
            $I->comment('No relevant functions found');
        } else {
            foreach ($relevantFunctions as $func) {
                $I->comment("  ⭐ {$func}()");
            }
        }
        
        // Check what variables exist related to timing/solving
        $I->comment('--- Solve/Time Related Variables ---');
        $variables = $I->executeJS('
            const vars = {};
            const checkVars = [
                "puzzleSolved", "puzzleStartTime", "solveTimeRecorded", 
                "puzzleAlreadySolvedByUser", "puzzleId", "puzzleCode",
                "solutionPath", "puzzleData"
            ];
            
            checkVars.forEach(varName => {
                try {
                    if (typeof window[varName] !== "undefined") {
                        vars[varName] = typeof window[varName] + " = " + window[varName];
                    }
                } catch(e) {
                    // Variable might be in different scope
                }
            });
            
            return vars;
        ');
        
        foreach ($variables as $varName => $value) {
            $I->comment("  {$varName}: {$value}");
        }
        
        // Check if puzzle completion logic exists by looking for event handlers
        $I->comment('--- Event Handlers and Puzzle Logic ---');
        $puzzleLogic = $I->executeJS('
            const info = [];
            
            // Check for canvas event listeners
            const canvas = document.getElementById("board");
            if (canvas) {
                info.push("Canvas exists with " + canvas.getEventListeners?.()?.length || "unknown" + " event listeners");
            }
            
            // Check for common puzzle-related patterns in code
            const scripts = document.scripts;
            let hasOnComplete = false;
            for (let script of scripts) {
                if (script.innerHTML.includes("onComplete") || 
                    script.innerHTML.includes("complete") ||
                    script.innerHTML.includes("finished") ||
                    script.innerHTML.includes("solved")) {
                    hasOnComplete = true;
                    break;
                }
            }
            info.push("Has completion logic in scripts: " + hasOnComplete);
            
            return info;
        ');
        
        foreach ($puzzleLogic as $info) {
            $I->comment("  {$info}");
        }
        
        $I->comment('=== INSPECTION COMPLETE ===');
    }
}