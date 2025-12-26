# 🆓 Free Deployment Guide for کمپ خراسان PWA

## Option 1: Railway (Recommended - Easiest)

### Step 1: Prepare Your Code
✅ Files already created: `railway.json`, `nixpacks.toml`
✅ Database config updated for production

### Step 2: Deploy to Railway
1. Go to [railway.app](https://railway.app)
2. Sign up with GitHub (free)
3. Click "New Project" → "Deploy from GitHub repo"
4. Connect your GitHub account
5. Upload your project to GitHub first:
   ```bash
   git init
   git add .
   git commit -m "Initial commit"
   git branch -M main
   git remote add origin https://github.com/yourusername/khorasan-camp.git
   git push -u origin main
   ```
6. Select your repository in Railway
7. Railway will auto-deploy your app
8. Add MySQL database: Click "New" → "Database" → "MySQL"
9. Your app will be live at: `https://yourapp.railway.app`

### Step 3: Setup Database
1. In Railway dashboard, click on MySQL service
2. Go to "Connect" tab, copy connection details
3. Use phpMyAdmin or MySQL client to run your `database/install.sql`
4. Or use Railway's built-in database browser

---

## Option 2: InfinityFree (Traditional Hosting)

### Step 1: Sign Up
1. Go to [infinityfree.net](https://infinityfree.net)
2. Create free account
3. Create new hosting account

### Step 2: Upload Files
1. Use File Manager or FTP
2. Upload all files to `htdocs` folder
3. Create MySQL database in control panel
4. Import `database/install.sql`
5. Update `api/config.php` with database credentials

### Step 3: Configure
- Your app will be at: `http://yoursite.epizy.com`
- Enable HTTPS in control panel (free SSL)

---

## Option 3: Render

### Step 1: Prepare
1. Push code to GitHub
2. Go to [render.com](https://render.com)
3. Sign up with GitHub

### Step 2: Deploy
1. New → "Web Service"
2. Connect GitHub repo
3. Settings:
   - Build Command: `echo "No build needed"`
   - Start Command: `php -S 0.0.0.0:$PORT -t public`
4. Add PostgreSQL database (free)
5. Convert MySQL queries to PostgreSQL (if needed)

---

## Option 4: GitHub Pages + Supabase (Frontend Only)

### Step 1: Convert to Static
1. Convert PHP API to JavaScript functions
2. Use Supabase for database (free tier)
3. Deploy frontend to GitHub Pages

### Step 2: Setup
1. Push to GitHub
2. Enable GitHub Pages in repo settings
3. Create Supabase project
4. Update API calls to use Supabase client

---

## 🎯 Recommended Path: Railway

**Why Railway?**
- ✅ Your PHP code works without changes
- ✅ Free MySQL database included
- ✅ HTTPS automatic
- ✅ Custom domain support
- ✅ Easy deployment from GitHub
- ✅ 500 hours/month (enough for small apps)

**Total Cost: $0/month**

## 🚀 Quick Start (Railway)

1. **Push to GitHub** (if not already)
2. **Sign up at railway.app**
3. **Deploy from GitHub**
4. **Add MySQL database**
5. **Import your SQL file**
6. **Your PWA is live!**

Need help with any step? Let me know!