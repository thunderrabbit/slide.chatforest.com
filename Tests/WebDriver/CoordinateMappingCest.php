<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class CoordinateMappingCest
{
    public function testPreciseCoordinateMapping(WebDriverTester $I): void
    {
        $I->comment('=== MAPPING PIXEL COORDINATES TO GAME COORDINATES ===');
        
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
        
        // Get canvas information
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
        
        $I->comment("Canvas - left: {$canvasInfo['left']}, top: {$canvasInfo['top']}, width: {$canvasInfo['width']}, height: {$canvasInfo['height']}");
        $I->comment("Cell size: {$canvasInfo['cellSize']}");
        
        // Test each corner systematically to understand coordinate mapping
        $testPoints = [
            ['name' => 'top-left (0,0)', 'gridX' => 0, 'gridY' => 0],
            ['name' => 'top-center (2,0)', 'gridX' => 2, 'gridY' => 0], 
            ['name' => 'top-right (4,0)', 'gridX' => 4, 'gridY' => 0],
            ['name' => 'center-left (0,2)', 'gridX' => 0, 'gridY' => 2],
            ['name' => 'center-center (2,2)', 'gridX' => 2, 'gridY' => 2],
            ['name' => 'center-right (4,2)', 'gridX' => 4, 'gridY' => 2],
            ['name' => 'bottom-left (0,4)', 'gridX' => 0, 'gridY' => 4],
            ['name' => 'bottom-center (2,4)', 'gridX' => 2, 'gridY' => 4],
            ['name' => 'bottom-right (4,4)', 'gridX' => 4, 'gridY' => 4],
        ];
        
        foreach ($testPoints as $point) {
            $I->comment("--- Testing {$point['name']} ---");
            
            // Calculate offset from canvas top-left corner
            $offsetX = (int)($point['gridX'] * $canvasInfo['cellSize'] + $canvasInfo['cellSize'] / 2);
            $offsetY = (int)($point['gridY'] * $canvasInfo['cellSize'] + $canvasInfo['cellSize'] / 2);
            
            $I->comment("Calculated offset from canvas: ({$offsetX}, {$offsetY})");
            
            // Clear logs
            $I->executeJS('window.gameConsoleLogs = [];');
            
            // Click at this position
            $I->moveMouseOver('#board', $offsetX, $offsetY);
            $I->click('#board');
            
            $I->wait(0.2);
            
            // Check what game coordinates were hit
            $consoleLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
            
            if (!empty($consoleLogs)) {
                $latestLog = end($consoleLogs);
                $I->comment("✓ Result: {$latestLog}");
                
                // Extract coordinates from log for verification
                if (preg_match('/tryAddCell called: (\d+) (\d+)/', $latestLog, $matches)) {
                    $actualR = $matches[1];
                    $actualC = $matches[2];
                    
                    if ($actualR == $point['gridY'] && $actualC == $point['gridX']) {
                        $I->comment("🎯 PERFECT MATCH! Expected ({$point['gridX']},{$point['gridY']}) = Actual ({$actualC},{$actualR})");
                    } else {
                        $I->comment("❌ MISMATCH: Expected ({$point['gridX']},{$point['gridY']}) ≠ Actual ({$actualC},{$actualR})");
                    }
                }
            } else {
                $I->comment("❌ No response - click may have missed canvas");
            }
            
            $I->comment('');
        }
        
        $I->comment('=== COORDINATE MAPPING TEST COMPLETE ===');
    }
    
    public function testSolutionPathWithCorrectCoordinates(WebDriverTester $I): void
    {
        $I->comment('=== TESTING SOLUTION PATH WITH CORRECTED COORDINATES ===');
        
        // Load puzzle page  
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Set up console logging
        $I->executeJS('
            window.gameConsoleLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                const logString = args.join(" ");
                if (logString.includes("tryAddCell") || 
                    logString.includes("🖱️") ||
                    logString.includes("path") ||
                    logString.includes("complete") ||
                    logString.includes("solved")) {
                    window.gameConsoleLogs.push(logString);
                }
                originalLog.apply(console, args);
            };
        ');
        
        $canvasInfo = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            return {
                cellSize: rect.width / 5
            };
        ');
        
        // Solution path - but now we know the coordinate system from the previous test
        // The original solution used (x,y) but the game uses (row,col) where row=y, col=x
        $solutionSteps = [
            ["col" => 3, "row" => 1], // Start at (3,1) 
            ["col" => 4, "row" => 1], // Move to (4,1)
            ["col" => 4, "row" => 0], // Move to (4,0)
            ["col" => 3, "row" => 0], // Move to (3,0)  
            ["col" => 2, "row" => 0], // Move to (2,0)
        ];
        
        $I->comment("Testing first 5 steps of solution path with proper coordinate mapping...");
        
        foreach ($solutionSteps as $index => $step) {
            $stepNum = $index + 1;
            
            // Calculate offset using correct coordinate system
            $offsetX = (int)($step['col'] * $canvasInfo['cellSize'] + $canvasInfo['cellSize'] / 2);
            $offsetY = (int)($step['row'] * $canvasInfo['cellSize'] + $canvasInfo['cellSize'] / 2);
            
            $I->comment("Step {$stepNum}: Clicking ({$step['col']},{$step['row']}) at offset ({$offsetX},{$offsetY})");
            
            // Move and click
            $I->moveMouseOver('#board', $offsetX, $offsetY); 
            $I->click('#board');
            
            $I->wait(0.3);
            
            // Check response
            $stepLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
            if (!empty($stepLogs)) {
                $latestLog = end($stepLogs);
                if (strpos($latestLog, 'tryAddCell') !== false) {
                    $I->comment("  ✓ {$latestLog}");
                }
            }
        }
        
        // Check final state
        $allLogs = $I->executeJS('return window.gameConsoleLogs');
        $I->comment('--- All captured logs ---');
        foreach ($allLogs as $log) {
            $I->comment("  {$log}");
        }
        
        $I->comment('=== SOLUTION PATH TEST COMPLETE ===');
    }
}