<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class InvestigateEventsCest
{
    public function testInvestigateCanvasEventHandling(WebDriverTester $I): void
    {
        $I->comment('=== INVESTIGATING CANVAS EVENT HANDLING ===');
        
        // Load puzzle page
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Investigate canvas event setup
        $eventInfo = $I->executeJS('
            const canvas = document.getElementById("board");
            const info = {
                hasCanvas: !!canvas,
                canvasEvents: [],
                documentEvents: [],
                windowEvents: [],
                touchSupport: "ontouchstart" in window,
                pointerSupport: "onpointerdown" in window
            };
            
            // Try to examine event listeners (limited access)
            try {
                // Check common event properties
                const eventTypes = [
                    "click", "mousedown", "mouseup", "mousemove", "mouseover", "mouseout",
                    "touchstart", "touchend", "touchmove", "touchcancel",
                    "pointerdown", "pointerup", "pointermove", "pointercancel"
                ];
                
                eventTypes.forEach(eventType => {
                    const handlerProp = "on" + eventType;
                    if (canvas[handlerProp] !== null) {
                        info.canvasEvents.push(eventType + " (property)");
                    }
                });
                
                // Check if canvas has data attributes or special properties
                info.canvasAttributes = {};
                for (let attr of canvas.attributes) {
                    info.canvasAttributes[attr.name] = attr.value;
                }
                
            } catch(e) {
                info.error = e.message;
            }
            
            return info;
        ');
        
        $I->comment('Event investigation results:');
        $I->comment('Has canvas: ' . ($eventInfo['hasCanvas'] ? 'Yes' : 'No'));
        $I->comment('Touch support: ' . ($eventInfo['touchSupport'] ? 'Yes' : 'No'));
        $I->comment('Pointer support: ' . ($eventInfo['pointerSupport'] ? 'Yes' : 'No'));
        $I->comment('Canvas events found: ' . implode(', ', $eventInfo['canvasEvents']));
        $I->comment('Canvas attributes: ' . json_encode($eventInfo['canvasAttributes']));
        
        // Try different event types to see which ones work
        $I->comment('--- Testing Different Event Types ---');
        
        $I->executeJS('
            window.gameConsoleLogs = [];
            const originalLog = console.log;
            console.log = function(...args) {
                const logString = args.join(" ");
                if (logString.includes("tryAddCell") || 
                    logString.includes("🖱️") ||
                    logString.includes("mouse") ||
                    logString.includes("touch") ||
                    logString.includes("pointer") ||
                    logString.includes("event")) {
                    window.gameConsoleLogs.push(logString);
                }
                originalLog.apply(console, args);
            };
        ');
        
        $eventTypes = ['mousedown', 'mouseup', 'click', 'touchstart', 'touchend', 'pointerdown', 'pointerup'];
        
        foreach ($eventTypes as $eventType) {
            $I->comment("Testing {$eventType}...");
            
            $I->executeJS('window.gameConsoleLogs = [];');
            
            $result = $I->executeJS("
                const canvas = document.getElementById('board');
                const rect = canvas.getBoundingClientRect();
                
                // Try to click on the '1' cell
                const targetX = rect.left + (3.5 * (rect.width / 5));
                const targetY = rect.top + (1.5 * (rect.height / 5));
                
                const event = new MouseEvent('{$eventType}', {
                    bubbles: true,
                    cancelable: true,
                    clientX: targetX,
                    clientY: targetY,
                    button: 0
                });
                
                return canvas.dispatchEvent(event);
            ");
            
            $I->wait(0.2);
            
            $logs = $I->executeJS('return window.gameConsoleLogs.slice()');
            if (!empty($logs)) {
                $I->comment("✓ {$eventType}: " . implode(', ', $logs));
            } else {
                $I->comment("❌ {$eventType}: No response");
            }
        }
        
        // Test if the issue is with the WebDriver click location
        $I->comment('--- Testing WebDriver vs Manual Coordinate Calculation ---');
        
        // Get the exact screen coordinates where WebDriver thinks it's clicking
        $webdriverCoords = $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            
            // This is where WebDriver is trying to click based on our calculation
            const webdriverOffsetX = 520;
            const webdriverOffsetY = 222;
            
            // Convert to screen coordinates
            const screenX = rect.left + webdriverOffsetX;
            const screenY = rect.top + webdriverOffsetY;
            
            // Calculate which cell this would actually hit
            const cellSize = rect.width / 5;
            const hitCol = Math.floor(webdriverOffsetX / cellSize);
            const hitRow = Math.floor(webdriverOffsetY / cellSize);
            
            return {
                offsetX: webdriverOffsetX,
                offsetY: webdriverOffsetY,
                screenX: screenX,
                screenY: screenY,
                calculatedCell: [hitCol, hitRow],
                cellSize: cellSize,
                canvasWidth: rect.width,
                canvasHeight: rect.height
            };
        ');
        
        $I->comment('WebDriver coordinate analysis:');
        $I->comment('Offset: (' . $webdriverCoords['offsetX'] . ', ' . $webdriverCoords['offsetY'] . ')');
        $I->comment('Calculated cell hit: (' . $webdriverCoords['calculatedCell'][0] . ', ' . $webdriverCoords['calculatedCell'][1] . ')');
        $I->comment('Cell size: ' . $webdriverCoords['cellSize']);
        
        $I->comment('=== EVENT INVESTIGATION COMPLETE ===');
    }
}