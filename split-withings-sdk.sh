#!/bin/bash

set -e

# Configuration
PACKAGE_DIR="packages/withings-sdk"
REPO_URL="https://github.com/filipac/withings-sdk"
TEMP_DIR="../withings-sdk-split"
CURRENT_DIR=$(pwd)

echo "🚀 Starting Withings SDK split and publish process..."

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo "❌ Error: Not in a git repository"
    exit 1
fi

# Check if package directory exists
if [ ! -d "$PACKAGE_DIR" ]; then
    echo "❌ Error: Package directory $PACKAGE_DIR does not exist"
    exit 1
fi

# Create temporary directory for the split
echo "📁 Creating temporary directory: $TEMP_DIR"
if [ -d "$TEMP_DIR" ]; then
    echo "🧹 Removing existing temporary directory..."
    rm -rf "$TEMP_DIR"
fi

# Clone the current repository to temporary location
echo "📋 Cloning repository to temporary location..."
git clone . "$TEMP_DIR"
cd "$TEMP_DIR"

# Check if git-filter-repo is available
if ! command -v git-filter-repo &> /dev/null; then
    echo "❌ Error: git-filter-repo is not installed"
    echo "💡 Install with: pip install git-filter-repo"
    echo "💡 Or on macOS: brew install git-filter-repo"
    exit 1
fi

# Use git-filter-repo to extract only the package subdirectory
echo "🔧 Filtering repository to extract $PACKAGE_DIR from all branches and tags..."
git filter-repo --subdirectory-filter "$PACKAGE_DIR" --force

# Add remote for the new repository
echo "🔗 Adding remote repository..."
git remote remove origin 2>/dev/null || true
git remote add origin "$REPO_URL"

# Push all branches to the new repository
echo "📤 Pushing all branches to $REPO_URL..."
git push --force --mirror origin

# Return to original directory
cd "$CURRENT_DIR"

# Clean up temporary directory
echo "🧹 Cleaning up temporary directory..."
rm -rf "$TEMP_DIR"

echo "✅ Successfully split and published withings-sdk package!"
echo "🌐 Repository URL: $REPO_URL"
echo ""
echo "📝 Next steps:"
echo "   1. Visit $REPO_URL to verify the repository"
echo "   2. Update repository description and README if needed"
echo "   3. Create releases/tags as needed"
echo "   4. Set up GitHub Actions for CI/CD if desired"