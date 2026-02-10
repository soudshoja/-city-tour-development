#!/bin/bash

# Load Test Runner Script
# Usage: ./tests/Load/run-load-test.sh [sustained|burst|stress|mixed|error|daily|all]

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# Default test type
TEST_TYPE="${1:-sustained}"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         Document Processing Load Test Runner              ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Change to project root
cd "$PROJECT_ROOT"

# Check if .env exists
if [ ! -f .env ]; then
    echo -e "${RED}❌ Error: .env file not found${NC}"
    echo "Please create .env file before running tests"
    exit 1
fi

# Check if vendor directory exists
if [ ! -d vendor ]; then
    echo -e "${YELLOW}⚠️  vendor directory not found. Running composer install...${NC}"
    composer install --no-interaction
fi

# Create storage directory if it doesn't exist
mkdir -p storage/app/load-test-reports

echo -e "${GREEN}📋 Test Configuration:${NC}"
echo "  Type: $TEST_TYPE"
echo "  Project Root: $PROJECT_ROOT"
echo ""

# Display test type description
case $TEST_TYPE in
    sustained)
        echo -e "${BLUE}ℹ️  Sustained Load:${NC} 100 documents over simulated day (batches of 10)"
        ;;
    burst)
        echo -e "${BLUE}ℹ️  Burst Load:${NC} 50 documents submitted simultaneously"
        ;;
    stress)
        echo -e "${BLUE}ℹ️  Stress Test:${NC} 500 documents rapidly to find breaking point"
        ;;
    mixed)
        echo -e "${BLUE}ℹ️  Mixed Types:${NC} Load test with mix of PDF, image, email, AIR"
        ;;
    error)
        echo -e "${BLUE}ℹ️  Error Handling:${NC} 100 documents with 10% simulated failures"
        ;;
    daily)
        echo -e "${BLUE}ℹ️  Daily Throughput:${NC} Validate 100+ docs/day capability"
        ;;
    all)
        echo -e "${BLUE}ℹ️  All Tests:${NC} Run complete test suite"
        ;;
    *)
        echo -e "${RED}❌ Invalid test type: $TEST_TYPE${NC}"
        echo ""
        echo "Valid types:"
        echo "  sustained  - 100 documents in batches (simulated daily load)"
        echo "  burst      - 50 documents in parallel (spike handling)"
        echo "  stress     - 500 documents rapidly (breaking point)"
        echo "  mixed      - Mixed document types (pdf, image, email, air)"
        echo "  error      - Error handling with 10% failure rate"
        echo "  daily      - Daily throughput capability validation"
        echo "  all        - Run all tests in sequence"
        echo ""
        echo "Usage: $0 [test_type]"
        echo "Example: $0 sustained"
        exit 1
        ;;
esac

echo ""
echo -e "${YELLOW}🔧 Preparing test environment...${NC}"

# Migrate fresh database (SQLite for testing)
php artisan migrate:fresh --seed --env=testing --force 2>/dev/null || {
    echo -e "${YELLOW}⚠️  Fresh migration failed, trying regular migrate...${NC}"
    php artisan migrate --env=testing --force
}

echo -e "${GREEN}✅ Database ready${NC}"
echo ""

# Run the load test using PHPUnit directly or via artisan command
echo -e "${GREEN}🚀 Starting load test...${NC}"
echo ""

# Use artisan command for better output
php artisan test:load --type="$TEST_TYPE"

TEST_EXIT_CODE=$?

echo ""
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                 ✅ TEST COMPLETED SUCCESSFULLY              ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BLUE}📁 Reports saved to:${NC} storage/app/load-test-reports/"
    echo ""

    # List recent reports
    if [ -d storage/app/load-test-reports ]; then
        RECENT_REPORTS=$(ls -t storage/app/load-test-reports/*.json 2>/dev/null | head -n 3)
        if [ -n "$RECENT_REPORTS" ]; then
            echo -e "${BLUE}📊 Recent reports:${NC}"
            echo "$RECENT_REPORTS" | while read -r report; do
                echo "  - $report"
            done
            echo ""
        fi
    fi

    exit 0
else
    echo -e "${RED}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                    ❌ TEST FAILED                          ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo "Check the output above for error details"
    exit 1
fi
