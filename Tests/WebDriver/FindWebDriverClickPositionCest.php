<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class FindWebDriverClickPositionCest
{
    public function testFindWhereWebDriverActuallyClicks(WebDriverTester $I): void
    {
        $I->comment('=== FINDING WHERE WEBDRIVER ACTUALLY CLICKS ===');
        
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
        
        // Get canvas info
        $canvasInfo = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            return {
                cellSize: rect.width / 5,
                width: rect.width,
                height: rect.height
            };
        ');
        
        $cellSize = $canvasInfo['cellSize'];
        
        // Test systematic grid positions to see where WebDriver is actually clicking
        $I->comment("Cell size: {$cellSize}px");
        $I->comment('Testing systematic positions to find where WebDriver clicks...');
        
        // Test the actual positions where each numbered cell should be
        $testPositions = [
            // Start cell "1" should be at (3,1)
            [
                'name' => 'Position for "1" cell (3,1)', 
                'offsetX' => (int)(3 * $cellSize + $cellSize / 2),
                'offsetY' => (int)(1 * $cellSize + $cellSize / 2),
                'expected' => '3,1'
            ],
            // "2" cell should be at (3,3) 
            [
                'name' => 'Position for "2" cell (3,3)',
                'offsetX' => (int)(3 * $cellSize + $cellSize / 2), 
                'offsetY' => (int)(3 * $cellSize + $cellSize / 2),
                'expected' => '3,3'
            ],
            // "3" cell should be at (1,4)
            [
                'name' => 'Position for "3" cell (1,4)',
                'offsetX' => (int)(1 * $cellSize + $cellSize / 2),
                'offsetY' => (int)(4 * $cellSize + $cellSize / 2), 
                'expected' => '1,4'
            ],
            // Test some corners to triangulate
            [
                'name' => 'Top-left corner (0,0)',
                'offsetX' => (int)($cellSize / 2),
                'offsetY' => (int)($cellSize / 2),
                'expected' => '0,0'
            ],
            [
                'name' => 'Top-right corner (4,0)',
                'offsetX' => (int)(4 * $cellSize + $cellSize / 2),
                'offsetY' => (int)($cellSize / 2),
                'expected' => '4,0'
            ],
            [
                'name' => 'Bottom-left corner (0,4)', 
                'offsetX' => (int)($cellSize / 2),
                'offsetY' => (int)(4 * $cellSize + $cellSize / 2),
                'expected' => '0,4'
            ],
        ];
        
        foreach ($testPositions as $pos) {
            $I->comment("--- {$pos['name']} ---");
            $I->comment("Offset: ({$pos['offsetX']}, {$pos['offsetY']}) - expecting cell {$pos['expected']}");
            
            $I->executeJS('window.gameConsoleLogs = [];');
            
            try {
                $I->moveMouseOver('#board', $pos['offsetX'], $pos['offsetY']);
                $I->click('#board');
                $I->wait(0.3);
                
                $logs = $I->executeJS('return window.gameConsoleLogs.slice()');
                if (!empty($logs)) {
                    $log = end($logs);
                    $I->comment("✓ WebDriver result: {$log}");
                    
                    // Extract actual coordinates
                    if (preg_match('/tryAddCell called: (\d+) (\d+)/', $log, $matches)) {
                        $actualR = $matches[1];
                        $actualC = $matches[2];
                        $I->comment("  Actual hit: ({$actualC},{$actualR})");
                        
                        if ($actualC . ',' . $actualR === $pos['expected']) {
                            $I->comment("  🎯 PERFECT MATCH!");
                        } else {
                            $I->comment("  ❌ Expected {$pos['expected']}, got {$actualC},{$actualR}");
                        }
                    }
                } else {
                    $I->comment("❌ No response from WebDriver click");
                }
            } catch (\Exception $e) {
                $I->comment("❌ Error: " . $e->getMessage());
            }
            
            $I->comment('');
        }
        
        // If we can find the pattern, try clicking the actual sequence
        $I->comment('--- Testing if we can click the start cell correctly ---');
        
        // The "1" cell should be at (3,1) - this is our starting position
        $startOffsetX = (int)(3 * $cellSize + $cellSize / 2);
        $startOffsetY = (int)(1 * $cellSize + $cellSize / 2);
        
        $I->comment("Attempting to click start cell '1' at offset ({$startOffsetX}, {$startOffsetY})...");
        
        $I->executeJS('window.gameConsoleLogs = [];');
        
        $I->moveMouseOver('#board', $startOffsetX, $startOffsetY);
        $I->click('#board');
        $I->wait(0.5);
        
        $finalLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        
        if (!empty($finalLogs)) {
            $finalLog = end($finalLogs);
            $I->comment("Final test result: {$finalLog}");
            
            // Check if we hit the start position correctly
            if (strpos($finalLog, 'tryAddCell called: 1 3') !== false) {
                $I->comment("🎉 SUCCESS! We hit the start cell (3,1)!");
            } else {
                $I->comment("❌ Still not hitting the right cell");
            }
        }
        
        $I->comment('=== WEBDRIVER CLICK POSITION TEST COMPLETE ===');
    }
}