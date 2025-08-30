<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class ClickNumberedCellsCest
{
    public function testClickOnNumberedCells(WebDriverTester $I): void
    {
        $I->comment('=== TESTING CLICKS ON VISIBLE NUMBERED CELLS ===');
        
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
        
        // From the screenshot, I can see the numbered positions:
        // "1" is at (3,1) - that should be our start
        // "2" is at (3,3) 
        // "3" is at (1,4)
        // "4" is at (3,4)
        
        $I->comment('First, let\'s click directly in the center of the canvas to establish baseline...');
        
        $I->executeJS('window.gameConsoleLogs = [];');
        $I->click('#board'); // Simple center click
        $I->wait(0.3);
        
        $centerLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        if (!empty($centerLogs)) {
            $I->comment("Center click result: " . end($centerLogs));
        }
        
        $I->comment('Now testing clicks on the "1" cell which should be at (3,1)...');
        
        // Get more precise canvas info
        $canvasInfo = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            const style = window.getComputedStyle(canvas);
            
            return {
                left: rect.left,
                top: rect.top,
                width: rect.width,
                height: rect.height,
                clientWidth: canvas.clientWidth,
                clientHeight: canvas.clientHeight,
                offsetWidth: canvas.offsetWidth,
                offsetHeight: canvas.offsetHeight,
                cellSize: rect.width / 5
            };
        ');
        
        $I->comment("Canvas details: " . json_encode($canvasInfo));
        
        // Try multiple approaches to click on the "1" cell at (3,1)
        $approaches = [
            [
                'name' => 'Direct offset calculation',
                'offsetX' => (int)(3 * $canvasInfo['cellSize'] + $canvasInfo['cellSize'] / 2),
                'offsetY' => (int)(1 * $canvasInfo['cellSize'] + $canvasInfo['cellSize'] / 2)
            ],
            [
                'name' => 'Conservative center-right',
                'offsetX' => (int)($canvasInfo['width'] * 0.7),
                'offsetY' => (int)($canvasInfo['height'] * 0.25)
            ],
            [
                'name' => 'Smaller offsets',
                'offsetX' => (int)(3 * ($canvasInfo['width'] / 5) + ($canvasInfo['width'] / 10)),
                'offsetY' => (int)(1 * ($canvasInfo['height'] / 5) + ($canvasInfo['height'] / 10))
            ]
        ];
        
        foreach ($approaches as $approach) {
            $I->comment("--- Trying: {$approach['name']} ---");
            $I->comment("Offset: ({$approach['offsetX']}, {$approach['offsetY']})");
            
            $I->executeJS('window.gameConsoleLogs = [];');
            
            try {
                $I->moveMouseOver('#board', $approach['offsetX'], $approach['offsetY']);
                $I->click('#board');
                $I->wait(0.3);
                
                $logs = $I->executeJS('return window.gameConsoleLogs.slice()');
                if (!empty($logs)) {
                    $I->comment("✓ Result: " . end($logs));
                } else {
                    $I->comment("❌ No response");
                }
            } catch (\Exception $e) {
                $I->comment("❌ Error: " . $e->getMessage());
            }
        }
        
        // Try an alternative approach - use JavaScript to simulate click at exact pixel coordinates
        $I->comment('--- JavaScript-based click simulation ---');
        
        $I->executeJS('window.gameConsoleLogs = [];');
        
        $clickResult = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            
            // Calculate where cell (3,1) should be - the "1" cell
            const cellSize = rect.width / 5;
            const targetX = 3 * cellSize + cellSize / 2;  // Column 3 (0-indexed)
            const targetY = 1 * cellSize + cellSize / 2;  // Row 1 (0-indexed)
            
            // Convert to absolute screen coordinates
            const screenX = rect.left + targetX;
            const screenY = rect.top + targetY;
            
            console.log("Attempting click at canvas coords:", targetX, targetY);
            console.log("Screen coords:", screenX, screenY);
            
            // Create and dispatch click event
            const event = new MouseEvent("click", {
                bubbles: true,
                cancelable: true,
                clientX: screenX,
                clientY: screenY,
                button: 0
            });
            
            const dispatched = canvas.dispatchEvent(event);
            
            return {
                canvasX: targetX,
                canvasY: targetY, 
                screenX: screenX,
                screenY: screenY,
                dispatched: dispatched
            };
        ');
        
        $I->comment("JS click info: " . json_encode($clickResult));
        $I->wait(0.5);
        
        $finalLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        if (!empty($finalLogs)) {
            foreach ($finalLogs as $log) {
                $I->comment("✓ JS click result: {$log}");
            }
        } else {
            $I->comment("❌ JS click produced no logs");
        }
        
        $I->comment('=== NUMBERED CELLS CLICK TEST COMPLETE ===');
    }
}