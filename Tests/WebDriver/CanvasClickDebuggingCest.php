<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class CanvasClickDebuggingCest
{
    public function testCanvasClickDebugging(WebDriverTester $I): void
    {
        $I->comment('=== DEBUGGING CANVAS CLICKS ===');
        
        // Load puzzle page
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Get canvas info and setup console log capture
        $canvasInfo = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            const gridSize = 5;
            const cellSize = rect.width / gridSize;
            
            // Store original console.log to capture our logs
            window.gameConsoleLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                // Only capture tryAddCell logs
                if (args[0] && args[0].includes("tryAddCell")) {
                    window.gameConsoleLogs.push(args.join(" "));
                }
                originalLog.apply(console, args);
            };
            
            return {
                x: rect.x,
                y: rect.y,
                width: rect.width,
                height: rect.height,
                cellSize: cellSize
            };
        ');
        
        $I->comment("Canvas info: " . json_encode($canvasInfo));
        
        // Test clicking specific cells to verify coordinates
        $testCells = [
            ['row' => 0, 'col' => 0, 'desc' => 'top-left'],
            ['row' => 0, 'col' => 4, 'desc' => 'top-right'],
            ['row' => 4, 'col' => 0, 'desc' => 'bottom-left'],
            ['row' => 4, 'col' => 4, 'desc' => 'bottom-right'],
            ['row' => 2, 'col' => 2, 'desc' => 'center'],
            ['row' => 3, 'col' => 1, 'desc' => 'start position (3,1)']
        ];
        
        foreach ($testCells as $cell) {
            $row = $cell['row'];
            $col = $cell['col'];
            $desc = $cell['desc'];
            
            // Calculate pixel coordinates (remember: canvas uses x,y but game logic uses row,col)
            $pixelX = $canvasInfo['x'] + ($col * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2);
            $pixelY = $canvasInfo['y'] + ($row * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2);
            
            $I->comment("Testing click on {$desc} cell: row={$row}, col={$col}");
            $I->comment("  Calculated pixel coordinates: x={$pixelX}, y={$pixelY}");
            
            // Clear previous logs
            $I->executeJS('window.gameConsoleLogs = [];');
            
            // Click the cell
            $I->executeJS("
                const canvas = document.getElementById('board');
                const rect = canvas.getBoundingClientRect();
                const x = {$pixelX} - rect.left;
                const y = {$pixelY} - rect.top;
                
                console.log('Clicking at canvas coordinates:', x, y);
                
                // Try different event approaches
                const mouseDown = new MouseEvent('mousedown', {
                    clientX: {$pixelX},
                    clientY: {$pixelY},
                    bubbles: true,
                    cancelable: true
                });
                canvas.dispatchEvent(mouseDown);
                
                const mouseUp = new MouseEvent('mouseup', {
                    clientX: {$pixelX},
                    clientY: {$pixelY},
                    bubbles: true,
                    cancelable: true
                });
                canvas.dispatchEvent(mouseUp);
                
                // Also try a click event
                const clickEvent = new MouseEvent('click', {
                    clientX: {$pixelX},
                    clientY: {$pixelY},
                    bubbles: true,
                    cancelable: true
                });
                canvas.dispatchEvent(clickEvent);
            ");
            
            $I->wait(0.5); // Allow time for logging
            
            // Check what was logged
            $consoleLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
            
            if (!empty($consoleLogs)) {
                foreach ($consoleLogs as $log) {
                    $I->comment("  ✓ Console: {$log}");
                }
            } else {
                $I->comment("  ❌ No tryAddCell logs captured");
            }
            
            $I->comment(''); // Empty line for readability
        }
        
        // Test the first few steps of the actual solution
        $I->comment('--- Testing actual solution path first 5 steps ---');
        $solutionSteps = [
            ["x" => 3, "y" => 1],  // Start
            ["x" => 4, "y" => 1],  // Step 2
            ["x" => 4, "y" => 0],  // Step 3
            ["x" => 3, "y" => 0],  // Step 4
            ["x" => 2, "y" => 0]   // Step 5
        ];
        
        // Clear logs and path
        $I->executeJS('
            window.gameConsoleLogs = [];
            // Try to clear any existing path
            if (typeof path !== "undefined") {
                path.length = 0;
            }
        ');
        
        foreach ($solutionSteps as $index => $step) {
            $stepNum = $index + 1;
            
            // Note: solution uses x,y but our game uses row,col where y=row, x=col
            $row = $step['y']; 
            $col = $step['x'];
            
            $pixelX = $canvasInfo['x'] + ($col * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2);
            $pixelY = $canvasInfo['y'] + ($row * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2);
            
            $I->comment("Solution step {$stepNum}: ({$col},{$row}) -> pixel ({$pixelX},{$pixelY})");
            
            // Click with a slight delay
            $I->executeJS("
                const canvas = document.getElementById('board');
                const rect = canvas.getBoundingClientRect();
                
                const mouseDown = new MouseEvent('mousedown', {
                    clientX: {$pixelX},
                    clientY: {$pixelY},
                    bubbles: true,
                    cancelable: true
                });
                const mouseUp = new MouseEvent('mouseup', {
                    clientX: {$pixelX},
                    clientY: {$pixelY},
                    bubbles: true,
                    cancelable: true
                });
                
                canvas.dispatchEvent(mouseDown);
                canvas.dispatchEvent(mouseUp);
            ");
            
            $I->wait(0.3);
            
            // Check logs after this step
            $stepLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
            if (!empty($stepLogs)) {
                $latestLog = end($stepLogs);
                $I->comment("  ✓ {$latestLog}");
            } else {
                $I->comment("  ❌ No tryAddCell response");
            }
        }
        
        // Final check - see current path state
        $pathState = $I->executeJS('
            return {
                pathLength: typeof path !== "undefined" ? path.length : "undefined",
                allLogs: window.gameConsoleLogs
            };
        ');
        
        $I->comment('--- Final Results ---');
        $I->comment("Path length: " . ($pathState['pathLength'] ?? 'N/A'));
        $I->comment("All console logs:");
        if (!empty($pathState['allLogs'])) {
            foreach ($pathState['allLogs'] as $log) {
                $I->comment("  {$log}");
            }
        } else {
            $I->comment("  No logs captured");
        }
        
        $I->comment('=== DEBUGGING COMPLETE ===');
    }
}