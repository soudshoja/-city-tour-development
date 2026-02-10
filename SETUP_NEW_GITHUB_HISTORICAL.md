# Setup New GitHub Repository for City Tour

## Current Status
- **Current Remote:** `git@github.com:ShobakMoha/city-tour.git`
- **Branch:** main
- **Latest Commit:** Documentation added
- **Deployment:** development.citycommerce.group

---

## Quick Setup Guide

### Step 1: Create New GitHub Repository

1. Go to: https://github.com/new
2. **Repository name:** Choose one:
   - `city-tour-development`
   - `citycommerce-platform`
   - `city-tour-production`
3. **Visibility:** Private (recommended)
4. **Initialize:** ❌ Don't check any boxes (no README, .gitignore, or license)
5. Click **"Create repository"**
6. Copy the SSH URL: `git@github.com:YOUR_USERNAME/REPO_NAME.git`

---

### Step 2: Push Code to New Repository

Once you have the new repository URL, run these commands:

```bash
cd ~/city-tour

# Add new remote (replace with your actual URL)
git remote add github-new git@github.com:YOUR_USERNAME/REPO_NAME.git

# Push main branch
git push github-new main

# Push all branches (optional - includes all feature branches)
git push github-new --all

# Push tags (optional)
git push github-new --tags

# Set new remote as default
git remote rename origin origin-old
git remote rename github-new origin

# Verify new remote
git remote -v
```

---

### Step 3: Update Repository Settings on GitHub

After pushing:

1. Go to your new repository on GitHub
2. **Settings → General:**
   - Add description: "City Tour - Travel Agency Management Platform"
   - Add website: https://development.citycommerce.group
   - Add topics: `laravel`, `travel`, `booking`, `ai`, `amadeus`, `gds`

3. **Settings → Branches:**
   - Set default branch to `main`
   - Add branch protection rules:
     - ✅ Require pull request reviews before merging
     - ✅ Require status checks to pass

4. **Settings → Collaborators:**
   - Add collaborators with appropriate permissions

---

### Step 4: Update Deployment Configuration

Update your deployment server to pull from the new repository:

```bash
# SSH into your server
ssh user@development.citycommerce.group

# Navigate to project
cd /path/to/city-tour

# Update git remote
git remote set-url origin git@github.com:YOUR_USERNAME/REPO_NAME.git

# Test connection
git fetch origin
```

---

## Alternative: Clone Repository to New Location

If you want to start fresh:

```bash
# Clone the new repository
git clone git@github.com:YOUR_USERNAME/REPO_NAME.git city-tour-new

# Copy environment file
cp ~/city-tour/.env city-tour-new/.env

# Install dependencies
cd city-tour-new
composer install
npm install

# Generate key
php artisan key:generate

# Run migrations (optional - use existing database)
php artisan migrate
```

---

## Repository Structure After Setup

```
NEW REPOSITORY (git@github.com:YOUR_USERNAME/REPO_NAME.git)
├── main (default branch)
├── dev
├── feature branches (optional)
└── tags

OLD REPOSITORY (git@github.com:ShobakMoha/city-tour.git)
└── origin-old (kept as backup)
```

---

## Collaboration Workflow

### For Team Members:

1. **Clone the repository:**
   ```bash
   git clone git@github.com:YOUR_USERNAME/REPO_NAME.git
   cd city-tour
   ```

2. **Set up environment:**
   ```bash
   cp .env.example .env
   composer install
   npm install
   php artisan key:generate
   ```

3. **Create feature branch:**
   ```bash
   git checkout -b feature/your-feature-name
   ```

4. **Make changes and commit:**
   ```bash
   git add .
   git commit -m "feat: your feature description"
   ```

5. **Push and create pull request:**
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Create PR on GitHub** and request review

---

## Branch Strategy

### Main Branches:
- **main** - Production-ready code (development.citycommerce.group)
- **dev** - Development branch for testing
- **staging** - Staging environment (optional)

### Feature Branches:
- `feature/` - New features
- `fix/` - Bug fixes
- `enhance/` - Enhancements
- `refactor/` - Code refactoring
- `docs/` - Documentation

### Example:
```bash
feature/payment-gateway-integration
fix/invoice-calculation-error
enhance/dashboard-performance
refactor/ai-service-layer
docs/api-documentation
```

---

## Important Files to Keep Secret

These files are already in `.gitignore` and should **NEVER** be committed:

- `.env` - Environment variables (passwords, API keys)
- `.env.production` - Production environment
- `.env.staging` - Staging environment
- `storage/*.key` - Encryption keys
- `auth.json` - Composer credentials
- `/vendor` - Composer dependencies
- `/node_modules` - NPM dependencies

---

## Deployment Configuration

### Environment Variables to Set:

**Database:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_testing
DB_USERNAME=your_username
DB_PASSWORD=your_password

DB_CONNECTION_MAP=mysql
DB_HOST_MAP=127.0.0.1
DB_PORT_MAP=3306
DB_DATABASE_MAP=map_data_citytour
DB_USERNAME_MAP=your_username
DB_PASSWORD_MAP=your_password
```

**AI Providers:**
```env
AI_PROVIDER=openwebui
OPENWEBUI_API_KEY=your_api_key
OPENWEBUI_API_URL=http://localhost:3000
OPENWEBUI_MODEL=llama3.1:latest

# Fallback OpenAI
OPENAI_API_KEY=your_openai_key
```

**Payment Gateways:**
```env
# MyFatoorah
MYFATOORAH_API_KEY=your_key
MYFATOORAH_API_URL=https://apitest.myfatoorah.com

# Other gateways...
```

---

## Automated Deployment Setup

### Using GitHub Actions (Optional):

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Development

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
    - name: Deploy to Server
      uses: appleboy/ssh-action@master
      with:
        host: development.citycommerce.group
        username: ${{ secrets.SSH_USERNAME }}
        key: ${{ secrets.SSH_PRIVATE_KEY }}
        script: |
          cd /path/to/city-tour
          git pull origin main
          composer install --no-dev --optimize-autoloader
          php artisan migrate --force
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache
```

---

## Team Access & Permissions

### Recommended GitHub Teams:

1. **Admin** - Full access (you + lead developers)
2. **Developers** - Write access (push to feature branches)
3. **Reviewers** - Can review and approve PRs
4. **Viewers** - Read-only access

---

## Next Steps After Setup

1. ✅ Create new GitHub repository
2. ✅ Push code to new repository
3. ✅ Update deployment server
4. ✅ Add collaborators
5. ✅ Set up branch protection
6. ✅ Configure webhooks (if needed)
7. ✅ Update documentation links
8. ✅ Test deployment pipeline

---

## Support & Questions

If you encounter any issues:
1. Check git remote: `git remote -v`
2. Check SSH access: `ssh -T git@github.com`
3. Check credentials: `cat ~/.ssh/id_rsa.pub`

For SSH key setup:
```bash
# Generate new SSH key (if needed)
ssh-keygen -t ed25519 -C "your_email@example.com"

# Add to SSH agent
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519

# Copy public key
cat ~/.ssh/id_ed25519.pub
# Add to GitHub: Settings → SSH Keys → New SSH Key
```

---

## Repository URL Format

Once created, your repository will be accessible at:

- **SSH:** `git@github.com:YOUR_USERNAME/REPO_NAME.git`
- **HTTPS:** `https://github.com/YOUR_USERNAME/REPO_NAME.git`
- **Web:** `https://github.com/YOUR_USERNAME/REPO_NAME`

Use SSH format for easier authentication without passwords.

---

**Ready to proceed? Create the repository and share the URL!**
