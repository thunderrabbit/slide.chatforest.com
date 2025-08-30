<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

class InspectLeaderboardAfterMigrationCest
{
    public function testLeaderboardInspectionAfterMigration(WebDriverTester $I): void
    {
        $I->comment('=== INSPECTING LEADERBOARD AFTER MIGRATION ===');
        
        // Quickly solve puzzle as anonymous user and register
        $I->amOnPage('/');
        $I->executeJS('localStorage.clear()');
        $I->amOnPage('/puzzle/Kw7fLo6M');
        $I->waitForPuzzleToLoad();
        
        // Quick solve using pointer events
        $I->executeJS('
            const canvas = document.getElementById("board");
            const rect = canvas.getBoundingClientRect();
            const cellSize = rect.width / 5;
            const solutionPath = [
                {"x": 3, "y": 1}, {"x": 4, "y": 1}, {"x": 4, "y": 0}, {"x": 3, "y": 0}, {"x": 2, "y": 0},
                {"x": 2, "y": 1}, {"x": 2, "y": 2}, {"x": 3, "y": 2}, {"x": 4, "y": 2}, {"x": 4, "y": 3},
                {"x": 3, "y": 3}, {"x": 2, "y": 3}, {"x": 1, "y": 3}, {"x": 1, "y": 2}, {"x": 1, "y": 1},
                {"x": 1, "y": 0}, {"x": 0, "y": 0}, {"x": 0, "y": 1}, {"x": 0, "y": 2}, {"x": 0, "y": 3},
                {"x": 0, "y": 4}, {"x": 1, "y": 4}, {"x": 2, "y": 4}, {"x": 3, "y": 4}, {"x": 4, "y": 4}
            ];
            
            let pointerId = 1;
            solutionPath.forEach((step, index) => {
                const targetX = rect.left + (step.x * cellSize) + (cellSize / 2);
                const targetY = rect.top + (step.y * cellSize) + (cellSize / 2);
                
                if (index === 0) {
                    canvas.dispatchEvent(new PointerEvent("pointerdown", {
                        pointerId, bubbles: true, cancelable: true,
                        clientX: targetX, clientY: targetY, button: 0, buttons: 1
                    }));
                } else {
                    canvas.dispatchEvent(new PointerEvent("pointermove", {
                        pointerId, bubbles: true, cancelable: true,
                        clientX: targetX, clientY: targetY, button: 0, buttons: 1
                    }));
                }
            });
            
            const lastStep = solutionPath[solutionPath.length - 1];
            const finalX = rect.left + (lastStep.x * cellSize) + (cellSize / 2);
            const finalY = rect.top + (lastStep.y * cellSize) + (cellSize / 2);
            canvas.dispatchEvent(new PointerEvent("pointerup", {
                pointerId, bubbles: true, cancelable: true,
                clientX: finalX, clientY: finalY, button: 0, buttons: 0
            }));
        ');
        
        $I->wait(2);
        
        // Register user
        $user = $I->generateTestUser();
        $I->comment("Registering user: {$user['username']}");
        $I->registerUser($user['username'], $user['email'], $user['password']);
        $I->wait(3);
        
        // Should be back on puzzle page
        $I->wait(3);
        
        // Now inspect the entire page structure
        $pageStructure = $I->executeJS('
            return {
                title: document.title,
                url: window.location.href,
                globalTimesExists: !!document.getElementById("global-times"),
                anonymousTimesExists: !!document.getElementById("anonymous-times"),
                allSections: Array.from(document.querySelectorAll("section, div[id], div[class*=\"time\"]")).map(el => ({
                    tag: el.tagName,
                    id: el.id,
                    className: el.className,
                    textContent: el.textContent ? el.textContent.substring(0, 100) : ""
                })),
                bodyHTML: document.body.innerHTML.substring(0, 2000)
            };
        ');
        
        $I->comment("Page title: {$pageStructure['title']}");
        $I->comment("Current URL: {$pageStructure['url']}");
        $I->comment("Global times section exists: " . ($pageStructure['globalTimesExists'] ? 'Yes' : 'No'));
        $I->comment("Anonymous times section exists: " . ($pageStructure['anonymousTimesExists'] ? 'Yes' : 'No'));
        
        $I->comment("--- All sections found on page ---");
        foreach ($pageStructure['allSections'] as $section) {
            if (!empty($section['textContent'])) {
                $I->comment("  {$section['tag']}#{$section['id']}.{$section['className']}: " . substr($section['textContent'], 0, 80));
            }
        }
        
        // Look specifically for any time-related content
        $timeContent = $I->executeJS('
            const timeElements = document.querySelectorAll("*");
            const timeRelated = [];
            
            for (const el of timeElements) {
                const text = el.textContent || "";
                const id = el.id || "";
                const className = el.className || "";
                
                if (text.includes("time") || text.includes("score") || text.includes("leaderboard") ||
                    text.includes("' . $user['username'] . '") ||
                    id.includes("time") || id.includes("score") || id.includes("global") ||
                    className.includes("time") || className.includes("score")) {
                    
                    timeRelated.push({
                        tag: el.tagName,
                        id: id,
                        className: className,
                        textContent: text.substring(0, 150),
                        innerHTML: el.innerHTML.substring(0, 200)
                    });
                }
            }
            
            return timeRelated;
        ');
        
        $I->comment("--- Time-related elements found ---");
        foreach ($timeContent as $element) {
            $I->comment("  {$element['tag']}#{$element['id']}.{$element['className']}");
            if (!empty($element['textContent'])) {
                $I->comment("    Text: " . substr($element['textContent'], 0, 100));
            }
            if (!empty($element['innerHTML']) && $element['innerHTML'] !== $element['textContent']) {
                $I->comment("    HTML: " . substr($element['innerHTML'], 0, 100));
            }
        }
        
        // Check if there are any JavaScript errors
        $jsErrors = $I->executeJS('
            return {
                consoleErrors: window.console._errors || [],
                hasJQuery: typeof $ !== "undefined",
                hasFetch: typeof fetch !== "undefined"
            };
        ');
        
        $I->comment("--- JavaScript Environment ---");
        $I->comment("Has jQuery: " . ($jsErrors['hasJQuery'] ? 'Yes' : 'No'));
        $I->comment("Has fetch: " . ($jsErrors['hasFetch'] ? 'Yes' : 'No'));
        
        $I->comment('=== LEADERBOARD INSPECTION COMPLETE ===');
    }
}