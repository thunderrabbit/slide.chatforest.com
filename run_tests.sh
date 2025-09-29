#!/bin/bash

# Codeception Test Runner for Slide Practice Game
# This script runs the canvas interaction tests

set -e  # Exit on any error

echo "🎮 Slide Practice Game - Codeception Test Runner"
echo "================================================"

# Check if Codeception is installed
if ! command -v vendor/bin/codecept &> /dev/null; then
    echo "❌ Codeception not found. Please install it first:"
    echo "   composer install"
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "codeception.yml" ]; then
    echo "❌ Please run this script from the project root directory"
    exit 1
fi

# Set up test environment
echo "🔧 Setting up test environment..."

# Create output directory
mkdir -p Tests/_output

# Set fixed browser window size for consistent coordinate calculations
export BROWSER_WINDOW_SIZE="1920x1080"

echo "📏 Browser window size: $BROWSER_WINDOW_SIZE"

# Run specific test suites
echo ""
echo "🧪 Running Canvas Interaction Tests..."
echo "--------------------------------------"

# Run canvas interaction tests
vendor/bin/codecept run Tests/WebDriver/CanvasInteractionCest.php --verbose

echo ""
echo "🧩 Running Puzzle Solving Tests..."
echo "----------------------------------"

# Run puzzle solving tests
vendor/bin/codecept run Tests/WebDriver/PuzzleSolvingCest.php --verbose

echo ""
echo "📊 Test Summary"
echo "==============="
echo "✅ Canvas interaction tests completed"
echo "✅ Puzzle solving tests completed"
echo ""
echo "🎯 Key Features Tested:"
echo "   • Coordinate translation (cell indices → pixel coordinates)"
echo "   • Canvas cell clicking"
echo "   • Path-based puzzle solving"
echo "   • Different grid sizes (5x5, 6x6)"
echo "   • Solution button functionality"
echo "   • Solve time tracking"
echo ""
echo "📁 Test results saved in: Tests/_output/"
echo "🖼️  Screenshots saved in: Tests/_output/debug/"

# Optional: Run all WebDriver tests
if [ "$1" = "--all" ]; then
    echo ""
    echo "🚀 Running All WebDriver Tests..."
    echo "--------------------------------"
    vendor/bin/codecept run Tests/WebDriver --verbose
fi

echo ""
echo "🎉 Test run completed!"