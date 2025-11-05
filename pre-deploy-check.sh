#!/bin/bash

echo "======================================"
echo "Pre-Deployment Checks for GCP Cloud Run"
echo "======================================"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Track errors
ERRORS=0

# Check 1: Required files
echo "1. Checking required files..."
FILES=("Dockerfile" ".env.example" "composer.json" "composer.lock" ".dockerignore")
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file exists"
    else
        echo -e "${RED}✗${NC} $file is missing!"
        ERRORS=$((ERRORS + 1))
    fi
done
echo ""

# Check 2: No merge conflicts
echo "2. Checking for merge conflict markers..."
if grep -r "<<<<<<< HEAD" --exclude-dir={vendor,node_modules,.git} . > /dev/null 2>&1; then
    echo -e "${RED}✗${NC} Merge conflict markers found!"
    echo "   Files with conflicts:"
    grep -r "<<<<<<< HEAD" --exclude-dir={vendor,node_modules,.git} . -l
    ERRORS=$((ERRORS + 1))
else
    echo -e "${GREEN}✓${NC} No merge conflicts"
fi
echo ""

# Check 3: Composer.lock validity
echo "3. Checking composer.lock validity..."
if composer validate --no-check-all --no-check-publish 2>/dev/null; then
    echo -e "${GREEN}✓${NC} composer.lock is valid"
else
    echo -e "${YELLOW}⚠${NC} composer.lock may have issues"
fi
echo ""

# Check 4: .env.example has required keys
echo "4. Checking .env.example..."
REQUIRED_KEYS=("APP_NAME" "APP_ENV" "APP_KEY" "APP_URL" "DB_CONNECTION")
for key in "${REQUIRED_KEYS[@]}"; do
    if grep -q "^$key=" .env.example 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $key exists"
    else
        echo -e "${YELLOW}⚠${NC} $key is missing from .env.example"
    fi
done
echo ""

# Check 5: Docker build context size
echo "5. Checking Docker build context size..."
SIZE=$(du -sh . 2>/dev/null | cut -f1)
echo "   Build context size: $SIZE"
if [ -d "vendor" ]; then
    echo -e "${YELLOW}⚠${NC} vendor/ directory exists (should be in .dockerignore)"
fi
if [ -d "node_modules" ]; then
    echo -e "${YELLOW}⚠${NC} node_modules/ directory exists (should be in .dockerignore)"
fi
echo ""

# Check 6: GCloud authentication
echo "6. Checking GCloud configuration..."
if command -v gcloud &> /dev/null; then
    echo -e "${GREEN}✓${NC} gcloud CLI is installed"
    PROJECT=$(gcloud config get-value project 2>/dev/null)
    if [ -n "$PROJECT" ]; then
        echo -e "${GREEN}✓${NC} Active project: $PROJECT"
    else
        echo -e "${RED}✗${NC} No active GCloud project set!"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo -e "${RED}✗${NC} gcloud CLI not found!"
    ERRORS=$((ERRORS + 1))
fi
echo ""

# Summary
echo "======================================"
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ All checks passed! Ready to deploy.${NC}"
    echo ""
    echo "To deploy, run:"
    echo "  gcloud builds submit --config cloudbuild.yaml"
    exit 0
else
    echo -e "${RED}✗ Found $ERRORS error(s). Please fix them before deploying.${NC}"
    exit 1
fi

