# 🚀 Deploy to Railway - Step by Step Guide

Your Khorasan Club app is ready for Railway deployment! Follow these steps:

## Prerequisites
- GitHub account
- Railway account (free at railway.app)

## Step 1: Push to GitHub (if not already done)

```bash
# Initialize git if not already done
git init

# Add all files
git add .

# Commit changes
git commit -m "Ready for Railway deployment"

# Add your GitHub repository
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git

# Push to GitHub
git push -u origin main
```

## Step 2: Deploy to Railway

1. Go to [railway.app](https://railway.app)
2. Sign up/login with GitHub
3. Click "New Project"
4. Select "Deploy from GitHub repo"
5. Choose your repository
6. Railway will automatically detect the configuration and start building

## Step 3: Add MySQL Database

1. In your Railway project dashboard
2. Click "New" → "Database" → "MySQL"
3. Wait for the database to be created
4. Railway will automatically set the `DATABASE_URL` environment variable

## Step 4: Import Database Schema

1. Click on your MySQL service in Railway
2. Go to "Connect" tab
3. Use the connection details to connect with a MySQL client
4. Import your `database/install.sql` file

**Or use Railway's built-in database browser:**
1. Click "Data" tab in your MySQL service
2. Use the query editor to run your SQL schema

## Step 5: Configure Environment Variables (Optional)

Railway automatically provides `DATABASE_URL`, but you can also set individual variables:

- `DB_HOST` - MySQL host
- `DB_NAME` - Database name  
- `DB_USER` - Database user
- `DB_PASS` - Database password

## Step 6: Access Your App

Your app will be available at: `https://YOUR_PROJECT_NAME.railway.app`

## ✅ What's Already Configured

- ✅ `railway.json` - Railway deployment config
- ✅ `nixpacks.toml` - Build configuration with PHP extensions
- ✅ `api/config.php` - Environment variable support
- ✅ `.railwayignore` - Excludes unnecessary files
- ✅ `public/.htaccess` - Apache configuration
- ✅ Health check endpoint

## 🔧 Troubleshooting

### Database Connection Issues
- Make sure MySQL service is running in Railway
- Check that `DATABASE_URL` environment variable is set
- Verify database schema is imported

### Build Failures
- Check Railway build logs
- Ensure all PHP extensions are listed in `nixpacks.toml`

### File Upload Issues
- Railway has ephemeral storage - uploaded files are lost on restart
- Consider using external storage (AWS S3, Cloudinary) for production

## 🎯 Next Steps After Deployment

1. **Change default password** - Login with admin/admin123 and change it
2. **Test all features** - Verify students, coaches, expenses work
3. **Set up custom domain** (optional) - Available in Railway Pro
4. **Configure backups** - Export database regularly

## 💡 Tips

- Railway free tier gives you 500 hours/month (enough for small apps)
- Your app will sleep after 30 minutes of inactivity (free tier)
- First request after sleep takes ~10 seconds to wake up
- Consider Railway Pro ($5/month) for always-on apps

## 🆘 Need Help?

If you encounter issues:
1. Check Railway build/deploy logs
2. Verify all environment variables are set
3. Test database connection
4. Check the Railway documentation

Your app is production-ready! 🎉