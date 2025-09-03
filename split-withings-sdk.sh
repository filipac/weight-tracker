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

# Clone the current repository to temporary location with all branches and tags
echo "📋 Cloning repository to temporary location (with all branches and tags)..."
git clone --mirror . "$TEMP_DIR/.git"
cd "$TEMP_DIR"
git config --bool core.bare false
git reset --hard

# Use git filter-branch to extract only the package subdirectory from all branches and tags
echo "🔧 Filtering repository to extract $PACKAGE_DIR from all branches and tags..."
git filter-branch --force --prune-empty --subdirectory-filter "$PACKAGE_DIR" -- --all

# Clean up filter-branch backup refs
echo "🧹 Cleaning up filter-branch references..."
git for-each-ref --format="%(refname)" refs/original/ | xargs -n 1 git update-ref -d

# Remove any empty branches that may have been created
echo "🧹 Removing empty branches..."
for branch in $(git for-each-ref --format='%(refname:short)' refs/heads/); do
    if [ $(git rev-list --count $branch) -eq 0 ]; then
        git branch -D $branch
    fi
done

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