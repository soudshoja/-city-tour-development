#!/bin/bash

# Load Test Setup Verification Script
# Checks that all files are in place and environment is ready

set -e

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       Load Testing Harness - Setup Verification           ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

cd "$PROJECT_ROOT"

ERRORS=0

# Function to check file existence
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} $1"
    else
        echo -e "${RED}✗${NC} $1 ${RED}(MISSING)${NC}"
        ((ERRORS++))
    fi
}

# Function to check directory
check_dir() {
    if [ -d "$1" ]; then
        echo -e "${GREEN}✓${NC} $1/"
    else
        echo -e "${RED}✗${NC} $1/ ${RED}(MISSING)${NC}"
        ((ERRORS++))
    fi
}

echo -e "${YELLOW}Checking Core Files...${NC}"
check_file "tests/Load/LoadTestHelper.php"
check_file "tests/Load/DocumentProcessingLoadTest.php"
check_file "tests/Load/run-load-test.sh"
check_file "app/Console/Commands/RunLoadTest.php"
echo ""

echo -e "${YELLOW}Checking Documentation...${NC}"
check_file "tests/Load/README.md"
check_file "tests/Load/USAGE_EXAMPLES.md"
check_file "tests/Load/QUICKSTART.md"
check_file "tests/Load/TEST_IMPLEMENTATION_SUMMARY.md"
check_file "tests/Load/sample-report.json"
echo ""

echo -e "${YELLOW}Checking Required Models...${NC}"
check_file "app/Models/DocumentProcessingLog.php"
check_file "app/Models/Company.php"
check_file "database/factories/DocumentProcessingLogFactory.php"
echo ""

echo -e "${YELLOW}Checking Test Infrastructure...${NC}"
check_file "tests/TestCase.php"
check_file "phpunit.xml"
echo ""

echo -e "${YELLOW}Checking Environment...${NC}"
if [ -f ".env" ]; then
    echo -e "${GREEN}✓${NC} .env file exists"
else
    echo -e "${YELLOW}⚠${NC}  .env file not found (may need to copy from .env.example)"
fi

if [ -d "vendor" ]; then
    echo -e "${GREEN}✓${NC} vendor/ directory exists"
else
    echo -e "${YELLOW}⚠${NC}  vendor/ not found - run: composer install"
fi
echo ""

echo -e "${YELLOW}Checking Report Directory...${NC}"
if [ -d "storage/app/load-test-reports" ]; then
    echo -e "${GREEN}✓${NC} storage/app/load-test-reports/ exists"
else
    echo -e "${YELLOW}⚠${NC}  Creating storage/app/load-test-reports/"
    mkdir -p storage/app/load-test-reports
fi
echo ""

echo -e "${YELLOW}Checking Artisan Command Registration...${NC}"
if grep -q "load(__DIR__" app/Console/Kernel.php; then
    echo -e "${GREEN}✓${NC} Commands auto-loaded in Kernel.php"
else
    echo -e "${RED}✗${NC} Commands not auto-loaded"
    ((ERRORS++))
fi
echo ""

echo -e "${YELLOW}Checking Script Permissions...${NC}"
if [ -x "tests/Load/run-load-test.sh" ]; then
    echo -e "${GREEN}✓${NC} run-load-test.sh is executable"
else
    echo -e "${YELLOW}⚠${NC}  Making run-load-test.sh executable"
    chmod +x tests/Load/run-load-test.sh
fi

if [ -x "tests/Load/verify-setup.sh" ]; then
    echo -e "${GREEN}✓${NC} verify-setup.sh is executable"
else
    echo -e "${YELLOW}⚠${NC}  Making verify-setup.sh executable"
    chmod +x tests/Load/verify-setup.sh
fi
echo ""

# Summary
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ VERIFICATION PASSED${NC}"
    echo ""
    echo "All files are in place and ready for testing!"
    echo ""
    echo -e "${BLUE}Next Steps:${NC}"
    echo "1. Prepare database: php artisan migrate:fresh --seed --env=testing"
    echo "2. Run first test:   php artisan test:load --type=sustained"
    echo "3. Check results:    ls -l storage/app/load-test-reports/"
    echo ""
    echo -e "${BLUE}Documentation:${NC}"
    echo "• Quick Start:  tests/Load/QUICKSTART.md"
    echo "• Full Guide:   tests/Load/README.md"
    echo "• Examples:     tests/Load/USAGE_EXAMPLES.md"
    echo ""
    exit 0
else
    echo -e "${RED}✗ VERIFICATION FAILED${NC}"
    echo ""
    echo -e "${RED}Found $ERRORS error(s)${NC}"
    echo "Please fix the issues above before running load tests."
    echo ""
    exit 1
fi
