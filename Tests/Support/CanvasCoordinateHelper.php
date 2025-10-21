<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Helper class for converting canvas cell coordinates to pixel coordinates
 * Based on the successful approach found in FinalMigrationTestCest.php
 */
class CanvasCoordinateHelper
{
    private $canvasElement;
    private $gridSize;
    private $canvasRect;
    private $cellSize;

    public function __construct($canvasElement, int $gridSize)
    {
        $this->canvasElement = $canvasElement;
        $this->gridSize = $gridSize;
        $this->refreshCanvasDimensions();
    }

    /**
     * Refresh canvas dimensions (call this if canvas size changes)
     */
    public function refreshCanvasDimensions(): void
    {
        $this->canvasRect = $this->getCanvasBoundingRect();
        $this->cellSize = $this->canvasRect['width'] / $this->gridSize;
    }

    /**
     * Get canvas bounding rectangle from browser
     */
    private function getCanvasBoundingRect(): array
    {
        // This would be called from WebDriverTester context
        return [
            'left' => 0,   // Will be set by JavaScript execution
            'top' => 0,     // Will be set by JavaScript execution
            'width' => 0,  // Will be set by JavaScript execution
            'height' => 0  // Will be set by JavaScript execution
        ];
    }

    /**
     * Convert cell coordinates (row, col) to pixel coordinates
     * Returns center point of the cell
     */
    public function cellToPixel(int $row, int $col): array
    {
        $pixelX = $this->canvasRect['left'] + ($col * $this->cellSize) + ($this->cellSize / 2);
        $pixelY = $this->canvasRect['top'] + ($row * $this->cellSize) + ($this->cellSize / 2);

        return [
            'x' => $pixelX,
            'y' => $pixelY
        ];
    }

    /**
     * Get JavaScript code to calculate canvas dimensions and cell positions
     * This will be executed in the browser context
     */
    public function getCanvasDimensionsJS(): string
    {
        return "
            const canvas = document.getElementById('board');
            if (!canvas) {
                throw new Error('Canvas element not found');
            }

            const rect = canvas.getBoundingClientRect();
            const gridSize = " . $this->gridSize . ";
            const cellSize = rect.width / gridSize;

            return {
                left: rect.left,
                top: rect.top,
                width: rect.width,
                height: rect.height,
                cellSize: cellSize,
                gridSize: gridSize
            };
        ";
    }

    /**
     * Get JavaScript code to click a specific cell
     */
    public function getClickCellJS(int $row, int $col): string
    {
        return "
            const canvas = document.getElementById('board');
            if (!canvas) {
                throw new Error('Canvas element not found');
            }

            const rect = canvas.getBoundingClientRect();
            const gridSize = " . $this->gridSize . ";
            const cellSize = rect.width / gridSize;

            // Calculate center point of the cell
            const targetX = rect.left + (" . $col . " * cellSize) + (cellSize / 2);
            const targetY = rect.top + (" . $row . " * cellSize) + (cellSize / 2);

            // Simulate pointer events (touch/click)
            const pointerId = 1;

            // Pointer down
            canvas.dispatchEvent(new PointerEvent('pointerdown', {
                pointerId: pointerId,
                bubbles: true,
                cancelable: true,
                clientX: targetX,
                clientY: targetY,
                button: 0,
                buttons: 1
            }));

            // Small delay then pointer up
            setTimeout(() => {
                canvas.dispatchEvent(new PointerEvent('pointerup', {
                    pointerId: pointerId,
                    bubbles: true,
                    cancelable: true,
                    clientX: targetX,
                    clientY: targetY,
                    button: 0,
                    buttons: 0
                }));
            }, 50);

            return { x: targetX, y: targetY, row: " . $row . ", col: " . $col . " };
        ";
    }

    /**
     * Get JavaScript code to simulate a path (sequence of cell clicks)
     */
    public function getClickPathJS(array $path): string
    {
        $pathJS = json_encode($path);

        return "
            const canvas = document.getElementById('board');
            if (!canvas) {
                throw new Error('Canvas element not found');
            }

            const rect = canvas.getBoundingClientRect();
            const gridSize = " . $this->gridSize . ";
            const cellSize = rect.width / gridSize;
            const path = " . $pathJS . ";

            const pointerId = 1;

            // Start with pointer down on first cell
            const firstCell = path[0];
            const firstX = rect.left + (firstCell.col * cellSize) + (cellSize / 2);
            const firstY = rect.top + (firstCell.row * cellSize) + (cellSize / 2);

            canvas.dispatchEvent(new PointerEvent('pointerdown', {
                pointerId: pointerId,
                bubbles: true,
                cancelable: true,
                clientX: firstX,
                clientY: firstY,
                button: 0,
                buttons: 1
            }));

            // Move through the path
            path.forEach((cell, index) => {
                setTimeout(() => {
                    const targetX = rect.left + (cell.col * cellSize) + (cellSize / 2);
                    const targetY = rect.top + (cell.row * cellSize) + (cellSize / 2);

                    if (index === path.length - 1) {
                        // Last cell - pointer up
                        canvas.dispatchEvent(new PointerEvent('pointerup', {
                            pointerId: pointerId,
                            bubbles: true,
                            cancelable: true,
                            clientX: targetX,
                            clientY: targetY,
                            button: 0,
                            buttons: 0
                        }));
                    } else {
                        // Intermediate cell - pointer move
                        canvas.dispatchEvent(new PointerEvent('pointermove', {
                            pointerId: pointerId,
                            bubbles: true,
                            cancelable: true,
                            clientX: targetX,
                            clientY: targetY,
                            button: 0,
                            buttons: 1
                        }));
                    }
                }, index * 100); // 100ms delay between moves
            });

            return { pathLength: path.length, firstCell: path[0], lastCell: path[path.length - 1] };
        ";
    }

    /**
     * Validate that cell coordinates are within grid bounds
     */
    public function isValidCell(int $row, int $col): bool
    {
        return $row >= 0 && $row < $this->gridSize && $col >= 0 && $col < $this->gridSize;
    }

    /**
     * Get grid size
     */
    public function getGridSize(): int
    {
        return $this->gridSize;
    }

    /**
     * Get cell size in pixels
     */
    public function getCellSize(): float
    {
        return $this->cellSize;
    }
}