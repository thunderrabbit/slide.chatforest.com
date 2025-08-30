#!/bin/bash

# Test runner script for Codeception tests
# Usage: ./run_tests.sh [acceptance|webdriver|all] [test_name]

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if codeception is installed
if [ ! -f "vendor/bin/codecept" ]; then
    echo -e "${RED}Error: Codeception not found. Please install it first:${NC}"
    echo "composer require --dev codeception/codeception"
    exit 1
fi

# Build the test suite (generates support classes)
echo -e "${YELLOW}Building test suites...${NC}"
vendor/bin/codecept build

# Determine which suite to run
SUITE=""
TEST_NAME=""

if [ $# -eq 0 ]; then
    SUITE="acceptance"
elif [ "$1" = "acceptance" ] || [ "$1" = "webdriver" ] || [ "$1" = "all" ]; then
    SUITE="$1"
    TEST_NAME="$2"
else
    # If first argument is not a suite name, assume it's a test name for acceptance
    SUITE="acceptance"
    TEST_NAME="$1"
fi

echo -e "${BLUE}Available test suites:${NC}"
echo -e "  ${YELLOW}acceptance${NC}  - Basic HTTP tests (fast, no JavaScript)"
echo -e "  ${YELLOW}webdriver${NC}   - Interactive browser tests (slower, JavaScript enabled)"
echo -e "  ${YELLOW}all${NC}         - Run both suites"
echo ""

# Run tests based on suite selection
if [ "$SUITE" = "all" ]; then
    echo -e "${YELLOW}Running all test suites...${NC}"
    echo -e "${BLUE}=== Running Acceptance Tests ===${NC}"
    vendor/bin/codecept run Acceptance --steps
    ACCEPTANCE_RESULT=$?
    
    echo -e "\n${BLUE}=== Running WebDriver Tests ===${NC}"
    vendor/bin/codecept run WebDriver --steps
    WEBDRIVER_RESULT=$?
    
    # Overall result
    if [ $ACCEPTANCE_RESULT -eq 0 ] && [ $WEBDRIVER_RESULT -eq 0 ]; then
        echo -e "${GREEN}All tests passed!${NC}"
        exit 0
    else
        echo -e "${RED}Some tests failed.${NC}"
        exit 1
    fi
elif [ "$SUITE" = "webdriver" ]; then
    echo -e "${YELLOW}Running WebDriver tests (JavaScript enabled)...${NC}"
    if [ -n "$TEST_NAME" ]; then
        vendor/bin/codecept run WebDriver "$TEST_NAME" --steps
    else
        vendor/bin/codecept run WebDriver --steps
    fi
else
    echo -e "${YELLOW}Running Acceptance tests (HTTP only)...${NC}"
    if [ -n "$TEST_NAME" ]; then
        vendor/bin/codecept run Acceptance "$TEST_NAME" --steps
    else
        vendor/bin/codecept run Acceptance --steps
    fi
fi

# Show results
if [ $? -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
else
    echo -e "${RED}Some tests failed.${NC}"
    echo -e "${YELLOW}Check Tests/_output/ for detailed error logs.${NC}"
fi