#!/bin/bash

# City Tour - GitHub Repository Setup Script
# This script helps you push the code to a new GitHub repository

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║   City Tour - GitHub Repository Setup                     ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "❌ Error: Must be run from city-tour project root"
    exit 1
fi

# Display current remote
echo "📍 Current repository remote:"
git remote -v
echo ""

# Ask for new repository URL
echo "Please create a new GitHub repository first:"
echo "  1. Go to https://github.com/new"
echo "  2. Create a private repository"
echo "  3. Copy the SSH URL (git@github.com:username/repo.git)"
echo ""
read -p "Enter your new repository URL: " NEW_REPO_URL

# Validate URL
if [ -z "$NEW_REPO_URL" ]; then
    echo "❌ Error: Repository URL cannot be empty"
    exit 1
fi

if [[ ! "$NEW_REPO_URL" =~ ^git@github\.com:.+\.git$ ]]; then
    echo "⚠️  Warning: URL doesn't look like a GitHub SSH URL"
    echo "   Expected format: git@github.com:username/repo.git"
    read -p "Continue anyway? (y/n): " CONTINUE
    if [ "$CONTINUE" != "y" ]; then
        exit 1
    fi
fi

echo ""
echo "🔄 Setting up new remote..."

# Add new remote
if git remote add github-new "$NEW_REPO_URL" 2>/dev/null; then
    echo "✅ Added new remote: github-new"
else
    echo "⚠️  Remote 'github-new' already exists, updating URL..."
    git remote set-url github-new "$NEW_REPO_URL"
fi

echo ""
echo "📤 Pushing code to new repository..."

# Push main branch
echo "  → Pushing main branch..."
if git push github-new main; then
    echo "✅ Main branch pushed"
else
    echo "❌ Failed to push main branch"
    exit 1
fi

# Ask if they want to push all branches
read -p "Push all branches? (y/n): " PUSH_ALL

if [ "$PUSH_ALL" = "y" ]; then
    echo "  → Pushing all branches..."
    git push github-new --all
    echo "✅ All branches pushed"
fi

# Ask if they want to push tags
read -p "Push tags? (y/n): " PUSH_TAGS

if [ "$PUSH_TAGS" = "y" ]; then
    echo "  → Pushing tags..."
    git push github-new --tags
    echo "✅ Tags pushed"
fi

echo ""
echo "🔀 Switching remotes..."

# Rename remotes
git remote rename origin origin-old 2>/dev/null
git remote rename github-new origin

echo "✅ Remotes updated:"
git remote -v

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║   ✅ Setup Complete!                                      ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""
echo "Your new repository is now set as 'origin'"
echo "Old repository is available as 'origin-old' (backup)"
echo ""
echo "Next steps:"
echo "  1. Go to your GitHub repository"
echo "  2. Add collaborators (Settings → Collaborators)"
echo "  3. Set up branch protection (Settings → Branches)"
echo "  4. Update your server's git remote (if deployed)"
echo ""
echo "Server update command:"
echo "  git remote set-url origin $NEW_REPO_URL"
echo ""
echo "Repository URL: $NEW_REPO_URL"
echo ""
