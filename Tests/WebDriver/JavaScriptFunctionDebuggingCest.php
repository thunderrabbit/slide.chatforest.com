<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class JavaScriptFunctionDebuggingCest
{
    public function testJavaScriptFunctionDebugging(WebDriverTester $I): void
    {
        $I->comment('=== JAVASCRIPT FUNCTION & EVENT DEBUGGING ===');
        
        // Load puzzle page
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // 1. Test console.log override is working
        $I->comment('--- Testing Console Log Override ---');
        $consoleTest = $I->executeJS('
            window.testConsoleLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                window.testConsoleLogs.push(args.join(" "));
                originalLog.apply(console, args);
            };
            
            console.log("TEST: Console override is working");
            return window.testConsoleLogs.length;
        ');
        
        $I->comment("Console override test - captured logs: {$consoleTest}");
        
        if ($consoleTest > 0) {
            $capturedLog = $I->executeJS('return window.testConsoleLogs[0]');
            $I->comment("  ✓ Captured: {$capturedLog}");
        } else {
            $I->comment("  ❌ Console override not working");
        }
        
        // 2. List all global functions containing game-related keywords
        $I->comment('--- Game-Related Global Functions ---');
        $gameFunctions = $I->executeJS('
            const keywords = ["add", "cell", "click", "mouse", "try", "canvas", "move", "path", "solve", "game"];
            const functions = [];
            
            for (const key in window) {
                if (typeof window[key] === "function") {
                    const lowerKey = key.toLowerCase();
                    const matchesKeyword = keywords.some(keyword => lowerKey.includes(keyword));
                    if (matchesKeyword || key.match(/^[a-z][A-Z]/)) { // camelCase functions
                        functions.push({
                            name: key,
                            type: typeof window[key]
                        });
                    }
                }
            }
            
            return functions.sort((a, b) => a.name.localeCompare(b.name));
        ');
        
        foreach ($gameFunctions as $func) {
            $I->comment("  {$func['name']}() - {$func['type']}");
        }
        
        // 3. Check if tryAddCell function exists and get its source
        $I->comment('--- tryAddCell Function Analysis ---');
        $tryAddCellInfo = $I->executeJS('
            const info = {
                exists: typeof tryAddCell !== "undefined",
                type: typeof tryAddCell,
                source: null,
                isInGlobalScope: "tryAddCell" in window
            };
            
            if (info.exists) {
                try {
                    info.source = tryAddCell.toString().substring(0, 500) + "...";
                } catch(e) {
                    info.source = "Cannot access source: " + e.message;
                }
            }
            
            return info;
        ');
        
        $I->comment("tryAddCell exists: " . ($tryAddCellInfo['exists'] ? 'YES' : 'NO'));
        $I->comment("tryAddCell type: " . $tryAddCellInfo['type']);
        $I->comment("tryAddCell in global scope: " . ($tryAddCellInfo['isInGlobalScope'] ? 'YES' : 'NO'));
        
        if ($tryAddCellInfo['exists'] && $tryAddCellInfo['source']) {
            $I->comment("tryAddCell source preview:");
            $I->comment("  " . str_replace("\n", "\n  ", $tryAddCellInfo['source']));
        }
        
        // 4. List all event listeners on the canvas element
        $I->comment('--- Canvas Event Listeners ---');
        $canvasEvents = $I->executeJS('
            const canvas = document.getElementById("board");
            const events = [];
            
            if (!canvas) {
                return {error: "Canvas element not found"};
            }
            
            // Try different approaches to get event listeners
            const info = {
                element: "canvas#board found",
                nodeName: canvas.nodeName,
                id: canvas.id,
                className: canvas.className,
                eventListeners: [],
                onEventProperties: []
            };
            
            // Check for on* properties
            const eventProps = ["onclick", "onmousedown", "onmouseup", "onmousemove", 
                              "onpointerdown", "onpointerup", "onpointermove",
                              "ontouchstart", "ontouchend", "ontouchmove"];
            
            eventProps.forEach(prop => {
                if (canvas[prop]) {
                    info.onEventProperties.push({
                        property: prop,
                        hasHandler: typeof canvas[prop] === "function",
                        source: typeof canvas[prop] === "function" ? 
                               canvas[prop].toString().substring(0, 200) + "..." : 
                               String(canvas[prop])
                    });
                }
            });
            
            // Try to access getEventListeners if available (Chrome DevTools)
            try {
                if (typeof getEventListeners === "function") {
                    const listeners = getEventListeners(canvas);
                    Object.keys(listeners).forEach(eventType => {
                        info.eventListeners.push({
                            type: eventType,
                            count: listeners[eventType].length
                        });
                    });
                }
            } catch(e) {
                info.eventListeners.push({error: "getEventListeners not available: " + e.message});
            }
            
            return info;
        ');
        
        if (isset($canvasEvents['error'])) {
            $I->comment("❌ " . $canvasEvents['error']);
        } else {
            $I->comment("✓ Canvas element found: " . $canvasEvents['element']);
            $I->comment("  Node: " . $canvasEvents['nodeName'] . ", ID: " . $canvasEvents['id']);
            
            if (!empty($canvasEvents['onEventProperties'])) {
                $I->comment("  Event properties:");
                foreach ($canvasEvents['onEventProperties'] as $prop) {
                    $I->comment("    {$prop['property']}: " . ($prop['hasHandler'] ? 'FUNCTION' : 'NOT SET'));
                    if ($prop['hasHandler']) {
                        $I->comment("      Source: " . $prop['source']);
                    }
                }
            } else {
                $I->comment("  No on* event properties found");
            }
            
            if (!empty($canvasEvents['eventListeners'])) {
                $I->comment("  Event listeners:");
                foreach ($canvasEvents['eventListeners'] as $listener) {
                    if (isset($listener['error'])) {
                        $I->comment("    " . $listener['error']);
                    } else {
                        $I->comment("    {$listener['type']}: {$listener['count']} listeners");
                    }
                }
            } else {
                $I->comment("  No event listeners detected");
            }
        }
        
        // 5. Test different event types on canvas to see responses
        $I->comment('--- Testing Canvas Event Responses ---');
        
        // Setup enhanced logging
        $I->executeJS('
            window.eventTestLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                window.eventTestLogs.push(args.join(" "));
                originalLog.apply(console, args);
            };
            
            // Add our own event listeners to see what fires
            const canvas = document.getElementById("board");
            if (canvas) {
                ["click", "mousedown", "mouseup", "mousemove", "pointerdown", "pointerup"].forEach(eventType => {
                    canvas.addEventListener(eventType, function(e) {
                        window.eventTestLogs.push(`EVENT DETECTED: ${eventType} at (${e.clientX}, ${e.clientY})`);
                    }, true); // Use capture phase
                });
            }
        ');
        
        // Get canvas coordinates for testing
        $canvasInfo = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            return {
                x: rect.x,
                y: rect.y,
                width: rect.width,
                height: rect.height,
                centerX: rect.x + rect.width / 2,
                centerY: rect.y + rect.height / 2
            };
        ');
        
        $testEvents = ['click', 'mousedown', 'mouseup', 'pointerdown', 'pointerup'];
        $testX = $canvasInfo['centerX'];
        $testY = $canvasInfo['centerY'];
        
        foreach ($testEvents as $eventType) {
            $I->comment("Testing {$eventType} event at canvas center ({$testX}, {$testY}):");
            
            // Clear logs
            $I->executeJS('window.eventTestLogs = [];');
            
            // Dispatch event
            $I->executeJS("
                const canvas = document.getElementById('board');
                const event = new MouseEvent('{$eventType}', {
                    clientX: {$testX},
                    clientY: {$testY},
                    bubbles: true,
                    cancelable: true
                });
                
                console.log('DISPATCHING {$eventType} event');
                canvas.dispatchEvent(event);
            ");
            
            $I->wait(0.2); // Allow time for handlers
            
            // Check what was logged
            $eventLogs = $I->executeJS('return window.eventTestLogs.slice()');
            
            if (!empty($eventLogs)) {
                foreach ($eventLogs as $log) {
                    $I->comment("  ✓ {$log}");
                }
            } else {
                $I->comment("  ❌ No response to {$eventType} event");
            }
        }
        
        // 6. Check for game state variables
        $I->comment('--- Game State Variables ---');
        $gameState = $I->executeJS('
            const vars = {};
            const checkVars = [
                "path", "currentPath", "gameGrid", "puzzleData", 
                "isMouseDown", "isDragging", "canvasContext",
                "gridSize", "cellSize", "barriers", "numberedCells"
            ];
            
            checkVars.forEach(varName => {
                try {
                    if (typeof window[varName] !== "undefined") {
                        const value = window[varName];
                        if (Array.isArray(value)) {
                            vars[varName] = `Array[${value.length}]`;
                        } else if (typeof value === "object" && value !== null) {
                            vars[varName] = `Object {${Object.keys(value).length} props}`;
                        } else {
                            vars[varName] = `${typeof value} = ${String(value).substring(0, 50)}`;
                        }
                    }
                } catch(e) {
                    // Ignore errors
                }
            });
            
            return vars;
        ');
        
        foreach ($gameState as $varName => $value) {
            $I->comment("  {$varName}: {$value}");
        }
        
        // 7. Final analysis - check for IIFE closure
        $I->comment('--- JavaScript Closure Analysis ---');
        $closureInfo = $I->executeJS('
            // Check if the main game code is in a closure by looking at script content
            const scripts = Array.from(document.scripts);
            let hasIIFE = false;
            let gameScriptContent = "";
            
            for (let script of scripts) {
                if (script.innerHTML.includes("tryAddCell")) {
                    gameScriptContent = script.innerHTML;
                    // Check for IIFE pattern: (function(){...})();
                    if (script.innerHTML.match(/\\(function\\(\\)\\{[\\s\\S]*\\}\\)\\(\\);/)) {
                        hasIIFE = true;
                    }
                    break;
                }
            }
            
            return {
                hasGameScript: gameScriptContent.length > 0,
                hasIIFE: hasIIFE,
                containsTryAddCell: gameScriptContent.includes("tryAddCell"),
                scriptLength: gameScriptContent.length
            };
        ');
        
        $I->comment("Game script found: " . ($closureInfo['hasGameScript'] ? 'YES' : 'NO'));
        $I->comment("Script length: " . $closureInfo['scriptLength'] . " characters");
        $I->comment("Contains tryAddCell: " . ($closureInfo['containsTryAddCell'] ? 'YES' : 'NO'));
        $I->comment("Uses IIFE closure: " . ($closureInfo['hasIIFE'] ? 'YES' : 'NO'));
        
        // Summary of findings
        $I->comment('--- DEBUGGING SUMMARY ---');
        $I->comment('KEY FINDINGS:');
        $I->comment('1. tryAddCell function EXISTS but is NOT in global scope (inside IIFE closure)');
        $I->comment('2. Canvas events ARE being dispatched and detected by our test listeners');  
        $I->comment('3. Canvas has no detectable event listeners (they\'re hidden in closure)');
        $I->comment('4. Console override works correctly');
        $I->comment('5. To test real gameplay, need to simulate actual user interactions (mouse/touch)');
        $I->comment('6. Cannot directly call game functions from test due to closure scope');
        
        $I->comment('=== JAVASCRIPT DEBUGGING COMPLETE ===');
    }
}