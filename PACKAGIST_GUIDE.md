# Step-by-Step Guide: Upload to GitHub and Packagist

## Prerequisites
- Git installed (✅ Already done)
- GitHub account (✅ You have: https://github.com/JainamDeveloper/)
- Packagist account (You'll create this)

---

## Step 1: Update Your Email in composer.json

**IMPORTANT:** Before proceeding, update your email in `composer.json`:
- Open `composer.json`
- Replace `"your-email@example.com"` with your actual email address
- Save the file
- Commit the change:
  ```bash
  git add composer.json
  git commit -m "Update author email"
  ```

---

## Step 2: Create GitHub Repository

### Option A: Using GitHub Web Interface (Recommended for First Time)

1. **Go to GitHub**: Visit https://github.com/new
2. **Repository Settings**:
   - **Repository name**: `magento2-custom-redirect` (or any name you prefer)
   - **Description**: `Magento 2 module for custom redirect handling on NoRoute pages`
   - **Visibility**: Choose **Public** (Required for Packagist)
   - **DO NOT** initialize with README, .gitignore, or license (we already have these)
3. **Click "Create repository"**

### Option B: Using GitHub CLI (if installed)

```bash
gh repo create magento2-custom-redirect --public --description "Magento 2 module for custom redirect handling on NoRoute pages"
```

---

## Step 3: Connect Local Repository to GitHub

After creating the repository on GitHub, you'll see instructions. Run these commands:

```bash
cd /home/bytes-jainam/Downloads/CustomRedirect

# Add the remote repository (replace YOUR_USERNAME with JainamDeveloper if needed)
git remote add origin https://github.com/JainamDeveloper/magento2-custom-redirect.git

# Rename branch to main (GitHub's default)
git branch -M main

# Push to GitHub
git push -u origin main
```

**If you need authentication:**
- If GitHub asks for credentials, you can use a Personal Access Token
- Create one at: https://github.com/settings/tokens
- Use the token as your password when prompted

---

## Step 4: Create Packagist Account

1. **Go to Packagist**: https://packagist.org/register
2. **Sign up** using one of these methods:
   - **GitHub Login** (Recommended - easiest way)
   - Email registration
3. **Complete the registration**

---

## Step 5: Submit Package to Packagist

1. **Go to Packagist Submit Page**: https://packagist.org/packages/submit
2. **Sign in** to Packagist
3. **Enter Repository URL**: 
   ```
   https://github.com/JainamDeveloper/magento2-custom-redirect.git
   ```
   (Replace with your actual repository URL)
4. **Click "Check"**
5. **Review the package information** that Packagist extracts from your `composer.json`
6. **Click "Submit"**

---

## Step 6: Set Up GitHub Webhook (Important!)

After submitting to Packagist, you need to set up a webhook so Packagist automatically updates when you push changes:

1. **Go to your GitHub repository**: `https://github.com/JainamDeveloper/magento2-custom-redirect`
2. **Go to Settings** → **Webhooks** → **Add webhook**
3. **Webhook settings**:
   - **Payload URL**: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
     (Replace `YOUR_PACKAGIST_USERNAME` with your Packagist username)
   - **Content type**: `application/json`
   - **Secret**: (Leave empty or use the secret from Packagist if provided)
   - **Events**: Select **Just the push event**
4. **Click "Add webhook"**

**Alternative**: You can also find your webhook URL on Packagist:
- Go to your package page on Packagist
- Click on the "GitHub" link/icon
- Follow the instructions to connect GitHub

---

## Step 7: Install Your Package (Test It!)

Once your package is on Packagist, you can install it using:

```bash
composer require jainamdeveloper/magento2-custom-redirect
```

---

## Troubleshooting

### Issue: "Repository not found" when pushing
- **Solution**: Make sure you created the repository on GitHub first
- Check that the repository URL is correct
- Verify you have write access to the repository

### Issue: Packagist can't find the package
- **Solution**: Make sure:
  - Repository is **Public** (not private)
  - `composer.json` is valid JSON
  - Repository URL is correct and accessible

### Issue: Email update not showing
- **Solution**: Make sure you committed and pushed the changes after updating `composer.json`

### Issue: Webhook not working
- **Solution**: 
  - Verify webhook URL is correct
  - Check webhook delivery logs in GitHub
  - You can manually update the package on Packagist by clicking "Update" on your package page

---

## Updating Your Package

When you make changes to your code:

1. **Make your changes**
2. **Update version in `composer.json`** (e.g., `1.0.1`)
3. **Commit and push**:
   ```bash
   git add .
   git commit -m "Update to version 1.0.1"
   git tag 1.0.1
   git push origin main
   git push --tags
   ```
4. **Packagist will automatically update** (if webhook is set up) or you can manually update it

---

## Package URL Format

Once published, your package will be available at:
```
https://packagist.org/packages/jainamdeveloper/magento2-custom-redirect
```

Users can install it with:
```bash
composer require jainamdeveloper/magento2-custom-redirect
```

---

## Need Help?

- **Packagist Docs**: https://packagist.org/about
- **GitHub Docs**: https://docs.github.com
- **Composer Docs**: https://getcomposer.org/doc/

Good luck! 🚀
