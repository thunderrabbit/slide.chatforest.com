<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class PointerEventsCest
{
    public function testUsingPointerEvents(WebDriverTester $I): void
    {
        $I->comment('=== TESTING WITH ACTUAL POINTER EVENTS ===');
        
        // Load puzzle page
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Set up console log monitoring
        $I->executeJS('
            window.gameConsoleLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                const logString = args.join(" ");
                if (logString.includes("tryAddCell") || 
                    logString.includes("🖱️") ||
                    logString.includes("complete") ||
                    logString.includes("solved")) {
                    window.gameConsoleLogs.push(logString);
                }
                originalLog.apply(console, args);
            };
        ');
        
        // Test clicking on the "1" cell using pointer events
        $I->comment('--- Testing Pointer Events on "1" Cell ---');
        
        $result = $I->executeJS('
            window.gameConsoleLogs = [];
            
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            
            // Based on the screenshot, "1" is at grid position (3,1)
            // Calculate the screen coordinates for this position
            const cellSize = rect.width / 5;  // 5x5 grid
            const targetX = rect.left + (3 * cellSize) + (cellSize / 2);  // Column 3
            const targetY = rect.top + (1 * cellSize) + (cellSize / 2);   // Row 1
            
            console.log("Calculated target coordinates:", targetX, targetY);
            console.log("Cell size:", cellSize);
            
            // Create pointer events (the actual events the game listens for)
            const pointerDown = new PointerEvent("pointerdown", {
                pointerId: 1,
                bubbles: true,
                cancelable: true,
                clientX: targetX,
                clientY: targetY,
                button: 0,
                buttons: 1
            });
            
            const pointerUp = new PointerEvent("pointerup", {
                pointerId: 1,
                bubbles: true,
                cancelable: true,
                clientX: targetX,
                clientY: targetY,
                button: 0,
                buttons: 0
            });
            
            // Dispatch the events
            console.log("Dispatching pointerdown...");
            canvas.dispatchEvent(pointerDown);
            
            console.log("Dispatching pointerup...");
            canvas.dispatchEvent(pointerUp);
            
            return {
                targetX: targetX,
                targetY: targetY,
                cellSize: cellSize,
                canvasLeft: rect.left,
                canvasTop: rect.top
            };
        ');
        
        $I->comment("Target coordinates: ({$result['targetX']}, {$result['targetY']})");
        $I->comment("Canvas position: ({$result['canvasLeft']}, {$result['canvasTop']})");
        
        $I->wait(0.5);
        
        // Check results
        $logs = $I->executeJS('return window.gameConsoleLogs.slice()');
        
        if (!empty($logs)) {
            $I->comment("✅ SUCCESS! Pointer events are working:");
            foreach ($logs as $log) {
                $I->comment("  {$log}");
            }
            
            // Check if we hit the right cell
            $lastLog = end($logs);
            if (strpos($lastLog, 'tryAddCell called: 1 3') !== false) {
                $I->comment("🎯 PERFECT! Hit the '1' cell at (3,1)!");
            }
        } else {
            $I->comment("❌ No logs - pointer events may not be working");
        }
        
        // Now test the complete solution path using pointer events
        $I->comment('--- Testing Complete Solution Path ---');
        
        // Solution path for this puzzle  
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
            
            // Simulate dragging through the entire solution
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
                
                // Small delay to simulate realistic drawing speed
                // Note: setTimeout won\'t work in this context, events are dispatched immediately
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
        
        // Give the game time to process and check for completion
        $I->wait(3);
        
        $solutionLogs = $I->executeJS('return window.gameConsoleLogs.slice()');
        
        if (!empty($solutionLogs)) {
            $I->comment("Solution attempt results:");
            foreach ($solutionLogs as $log) {
                $I->comment("  {$log}");
            }
        }
        
        // Check if puzzle was completed
        $completionCheck = $I->executeJS('
            // Check for completion indicators
            const bodyText = document.body.textContent.toLowerCase();
            const hasCompletionText = bodyText.includes("completed") || 
                                    bodyText.includes("solved") || 
                                    bodyText.includes("congratulations") ||
                                    bodyText.includes("first solve");
                                    
            // Check localStorage for saved time
            let hasSavedTime = false;
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith("slide_times_")) {
                    hasSavedTime = true;
                    break;
                }
            }
            
            return {
                hasCompletionText: hasCompletionText,
                hasSavedTime: hasSavedTime,
                bodySnippet: document.body.textContent.substring(0, 500)
            };
        ');
        
        $I->comment('--- Completion Check ---');
        if ($completionCheck['hasCompletionText']) {
            $I->comment("🎉 SUCCESS: Puzzle completion text found!");
        }
        if ($completionCheck['hasSavedTime']) {
            $I->comment("✅ SUCCESS: Solve time saved to localStorage!");
        }
        
        if (!$completionCheck['hasCompletionText'] && !$completionCheck['hasSavedTime']) {
            $I->comment("❌ Puzzle may not have been completed");
            $I->comment("Body text snippet: " . substr($completionCheck['bodySnippet'], 0, 200) . "...");
        }
        
        $I->comment('=== POINTER EVENTS TEST COMPLETE ===');
    }
}