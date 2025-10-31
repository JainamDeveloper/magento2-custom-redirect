#!/bin/bash

# Script to push Magento 2 Custom Redirect module to GitHub
# Make sure to update your email in composer.json first!

echo "=========================================="
echo "Magento 2 Custom Redirect - GitHub Push"
echo "=========================================="
echo ""
echo "This script will help you push to GitHub."
echo ""
echo "IMPORTANT: Make sure you have:"
echo "1. Created the repository on GitHub (https://github.com/new)"
echo "2. Updated your email in composer.json"
echo "3. Replaced YOUR_USERNAME and REPO_NAME in the commands below"
echo ""
read -p "Have you created the GitHub repository? (y/n): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Please create the repository on GitHub first:"
    echo "1. Go to: https://github.com/new"
    echo "2. Repository name: magento2-custom-redirect"
    echo "3. Make it PUBLIC (required for Packagist)"
    echo "4. Don't initialize with README/gitignore"
    echo "5. Click 'Create repository'"
    exit 1
fi

echo "Enter your GitHub username (default: JainamDeveloper):"
read -r GITHUB_USER
GITHUB_USER=${GITHUB_USER:-JainamDeveloper}

echo "Enter repository name (default: magento2-custom-redirect):"
read -r REPO_NAME
REPO_NAME=${REPO_NAME:-magento2-custom-redirect}

echo ""
echo "Setting up remote repository..."
git remote remove origin 2>/dev/null || true
git remote add origin "https://github.com/${GITHUB_USER}/${REPO_NAME}.git"

echo "Renaming branch to main..."
git branch -M main

echo ""
echo "Ready to push! Run this command:"
echo "  git push -u origin main"
echo ""
echo "If you need authentication, use a Personal Access Token:"
echo "  https://github.com/settings/tokens"
echo ""

read -p "Do you want to push now? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    git push -u origin main
    echo ""
    echo "✅ Success! Your code is now on GitHub!"
    echo ""
    echo "Next steps:"
    echo "1. Go to: https://packagist.org/packages/submit"
    echo "2. Sign in with GitHub"
    echo "3. Enter repository URL: https://github.com/${GITHUB_USER}/${REPO_NAME}.git"
    echo "4. Follow the instructions in PACKAGIST_GUIDE.md"
else
    echo "Run 'git push -u origin main' when ready!"
fi
