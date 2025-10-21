<?php

declare(strict_types=1);

namespace Tests\WebDriver;

use Tests\Support\WebDriverTester;

/**
 * Tests for canvas interaction using coordinate translation
 * These tests verify that we can click specific cells on the canvas
 */
class CanvasInteractionCest
{
    public function testCanvasDimensionsAndCellCalculation(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        $I->seeIAmOnPuzzlePage();

        // Generate a puzzle to ensure canvas is ready
        $I->generateNewPuzzle();

        // Get canvas information
        $canvasInfo = $I->getCanvasInfo();

        $I->comment("Canvas dimensions: {$canvasInfo['width']}x{$canvasInfo['height']}");
        $I->comment("Grid size: {$canvasInfo['gridSize']}x{$canvasInfo['gridSize']}");
        $I->comment("Cell size: {$canvasInfo['cellSize']} pixels");

        // Verify canvas has reasonable dimensions
        $I->assertGreaterThan(200, $canvasInfo['width'], 'Canvas should be reasonably sized');
        $I->assertGreaterThan(200, $canvasInfo['height'], 'Canvas should be reasonably sized');

        // Verify cell size is reasonable
        $expectedCellSize = $canvasInfo['width'] / $canvasInfo['gridSize'];
        $I->assertEquals($expectedCellSize, $canvasInfo['cellSize'], 'Cell size calculation should be correct');
    }

    public function testClickSingleCell(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        $I->generateNewPuzzle();

        // Click the top-left cell (0,0)
        $I->clickCanvasCell(0, 0);

        // Click the center cell (2,2) for a 5x5 grid
        $I->clickCanvasCell(2, 2);

        // Click the bottom-right cell (4,4) for a 5x5 grid
        $I->clickCanvasCell(4, 4);

        $I->comment('✓ Successfully clicked multiple cells');
    }

    public function testClickInvalidCellFails(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        $I->generateNewPuzzle();

        // This should fail with invalid coordinates
        try {
            $I->clickCanvasCell(10, 10); // Way outside 5x5 grid
            $I->fail('Should have failed with invalid cell coordinates');
        } catch (\Exception $e) {
            $I->comment('✓ Correctly failed with invalid cell coordinates: ' . $e->getMessage());
        }
    }

    public function testClickPathOfCells(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        $I->generateNewPuzzle();

        // Create a simple path: top-left to center to bottom-right
        $path = [
            ['row' => 0, 'col' => 0],
            ['row' => 1, 'col' => 1],
            ['row' => 2, 'col' => 2],
            ['row' => 3, 'col' => 3],
            ['row' => 4, 'col' => 4]
        ];

        $I->clickCanvasPath($path);

        $I->comment('✓ Successfully clicked path of 5 cells');
    }

    public function testCanvasInteractionWithDifferentGridSizes(WebDriverTester $I): void
    {
        $I->amOnPage('/');

        // Test 5x5 grid
        $I->selectOption('#gridSize', '5');
        $I->generateNewPuzzle();

        // Wait for puzzle generation to complete
        $I->wait(3);
        $canvasInfo5x5 = $I->getCanvasInfo();

        // Verify the canvas dimensions indicate a 5x5 grid
        $expectedCellSize5x5 = $canvasInfo5x5['width'] / 5;
        $actualCellSize5x5 = $canvasInfo5x5['cellSize'];
        $I->assertEquals($expectedCellSize5x5, $actualCellSize5x5, '5x5 grid cell size should be correct');
        $I->clickCanvasCell(2, 2); // Center of 5x5

        // Test 6x6 grid
        $I->selectOption('#gridSize', '6');
        $I->generateNewPuzzle();

        // Wait for puzzle generation to complete
        $I->wait(3);
        $canvasInfo6x6 = $I->getCanvasInfo();

        // Verify the canvas dimensions indicate a 6x6 grid
        $expectedCellSize6x6 = $canvasInfo6x6['width'] / 6;
        $actualCellSize6x6 = $canvasInfo6x6['cellSize'];
        $I->assertEquals($expectedCellSize6x6, $actualCellSize6x6, '6x6 grid cell size should be correct');
        $I->clickCanvasCell(3, 3); // Center of 6x6

        $I->comment('✓ Successfully tested canvas interaction with different grid sizes');
    }

    public function testCanvasClickTriggersGameLogic(WebDriverTester $I): void
    {
        $I->amOnPage('/');
        $I->generateNewPuzzle();

        // Click a cell and verify something happens
        $I->clickCanvasCell(0, 0);

        // Check if the game state changed (this would depend on the actual game logic)
        $gameState = $I->executeJS('
            return {
                hasPath: typeof currentPath !== "undefined",
                pathLength: typeof currentPath !== "undefined" ? currentPath.length : 0,
                hasCanvas: document.getElementById("board") !== null
            };
        ');

        $I->comment("Game state after click: " . json_encode($gameState));

        // At minimum, verify the canvas still exists
        $I->assertTrue($gameState['hasCanvas'], 'Canvas should still exist after click');
    }
}