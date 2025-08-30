<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class RealUserInteractionCest
{
    public function testRealCanvasClicksAndDrag(WebDriverTester $I): void
    {
        $I->comment('=== TESTING REAL CANVAS INTERACTIONS ===');
        
        // Load puzzle page
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Set up console log monitoring to capture tryAddCell calls
        $I->executeJS('
            window.gameConsoleLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                const logString = args.join(" ");
                // Capture both tryAddCell logs and any other significant logs
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
        
        // Get canvas element and its position
        $canvasInfo = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            return {
                x: rect.x,
                y: rect.y,
                width: rect.width,
                height: rect.height,
                cellSize: rect.width / 5
            };
        ');
        
        $I->comment("Canvas info: " . json_encode($canvasInfo));
        
        // Try single real clicks on specific cells first
        $I->comment('--- Testing Real WebDriver Clicks ---');
        
        // Test click on start position (3,1) - should be the "1" cell
        $startX = (int)($canvasInfo['x'] + (3 * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2));
        $startY = (int)($canvasInfo['y'] + (1 * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2));
        
        $I->comment("Clicking start position (3,1) at pixel ({$startX}, {$startY})");
        
        // Clear previous logs
        $I->executeJS('window.gameConsoleLogs = [];');
        
        // Use WebDriver's real click action
        $I->click('#board');  // First focus on canvas
        $I->wait(0.2);
        
        // Move to specific coordinates and click
        $I->executeJS("
            const canvas = document.getElementById('board');
            canvas.focus();
        ");
        
        // Try clicking using moveToElement with offset  
        try {
            $offsetX = (int)($startX - $canvasInfo['x']);
            $offsetY = (int)($startY - $canvasInfo['y']);
            $I->moveMouseOver('#board', $offsetX, $offsetY);
            $I->click('#board');
        } catch (\Exception $e) {
            $I->comment("Move mouse failed: " . $e->getMessage());
        }
        
        $I->wait(0.5);
        
        // Check what was logged
        $consoleLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        
        if (!empty($consoleLogs)) {
            foreach ($consoleLogs as $log) {
                $I->comment("✓ Captured: {$log}");
            }
        } else {
            $I->comment("❌ No logs captured from real click");
        }
        
        // Try drag interaction - this is more likely to work for this type of game
        $I->comment('--- Testing Drag Interaction ---');
        
        // Solution path for puzzle Kw7fLo6M (x,y coordinates in game terms)
        $solutionPath = [
            ["x" => 3, "y" => 1], ["x" => 4, "y" => 1], ["x" => 4, "y" => 0], 
            ["x" => 3, "y" => 0], ["x" => 2, "y" => 0]  // First 5 steps only
        ];
        
        $I->executeJS('window.gameConsoleLogs = [];');
        
        // Try using WebDriver's drag functionality
        $firstCell = $solutionPath[0];
        $startPixelX = (int)($canvasInfo['x'] + ($firstCell['x'] * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2));
        $startPixelY = (int)($canvasInfo['y'] + ($firstCell['y'] * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2));
        
        $secondCell = $solutionPath[1];
        $endPixelX = (int)($canvasInfo['x'] + ($secondCell['x'] * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2));
        $endPixelY = (int)($canvasInfo['y'] + ($secondCell['y'] * $canvasInfo['cellSize']) + ($canvasInfo['cellSize'] / 2));
        
        $I->comment("Attempting drag from ({$firstCell['x']},{$firstCell['y']}) to ({$secondCell['x']},{$secondCell['y']})");
        $I->comment("Pixel coordinates: ({$startPixelX},{$startPixelY}) to ({$endPixelX},{$endPixelY})");
        
        // Focus canvas first
        $I->click('#board');
        
        // Try using Selenium's action chains for precise drag
        $I->executeJS("
            const canvas = document.getElementById('board');
            const rect = canvas.getBoundingClientRect();
            
            // Calculate relative coordinates within canvas
            const startX = {$startPixelX} - rect.left;
            const startY = {$startPixelY} - rect.top;
            const endX = {$endPixelX} - rect.left;
            const endY = {$endPixelY} - rect.top;
            
            console.log('Attempting drag from', startX, startY, 'to', endX, endY);
            
            // Create a more realistic sequence of events
            const events = [];
            
            // Mouse down at start
            events.push({
                type: 'mousedown',
                x: startX, y: startY,
                clientX: {$startPixelX}, clientY: {$startPixelY}
            });
            
            // Several mousemove events to simulate drag
            const steps = 5;
            for (let i = 1; i <= steps; i++) {
                const progress = i / steps;
                const currentX = startX + (endX - startX) * progress;
                const currentY = startY + (endY - startY) * progress;
                const currentClientX = {$startPixelX} + ({$endPixelX} - {$startPixelX}) * progress;
                const currentClientY = {$startPixelY} + ({$endPixelY} - {$startPixelY}) * progress;
                
                events.push({
                    type: 'mousemove',
                    x: currentX, y: currentY,
                    clientX: currentClientX, clientY: currentClientY
                });
            }
            
            // Mouse up at end
            events.push({
                type: 'mouseup',
                x: endX, y: endY,
                clientX: {$endPixelX}, clientY: {$endPixelY}
            });
            
            // Dispatch all events with small delays
            events.forEach((eventData, index) => {
                setTimeout(() => {
                    const event = new MouseEvent(eventData.type, {
                        bubbles: true,
                        cancelable: true,
                        clientX: eventData.clientX,
                        clientY: eventData.clientY,
                        buttons: eventData.type === 'mousedown' ? 1 : (eventData.type === 'mousemove' ? 1 : 0)
                    });
                    
                    canvas.dispatchEvent(event);
                    console.log('Dispatched', eventData.type, 'at', eventData.x, eventData.y);
                }, index * 50);
            });
        ");
        
        $I->wait(2); // Allow drag sequence to complete
        
        // Check final results
        $finalLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        
        $I->comment('--- Final Results ---');
        if (!empty($finalLogs)) {
            foreach ($finalLogs as $log) {
                $I->comment("✓ {$log}");
            }
        } else {
            $I->comment("❌ No game logs captured from drag interaction");
        }
        
        // Check if any path was created
        $pathStatus = $I->executeJS('
            // Try to access path variable in various ways
            let pathInfo = "Path variable not accessible";
            
            try {
                // Check if there are any global variables that might contain path info
                const possibleVars = ["path", "currentPath", "gamePath", "solutionPath"];
                for (const varName of possibleVars) {
                    if (typeof window[varName] !== "undefined") {
                        pathInfo = varName + " exists: " + JSON.stringify(window[varName]);
                        break;
                    }
                }
            } catch(e) {
                pathInfo = "Error checking path: " + e.message;
            }
            
            return pathInfo;
        ');
        
        $I->comment("Path status: {$pathStatus}");
        
        $I->comment('=== REAL INTERACTION TEST COMPLETE ===');
    }
}