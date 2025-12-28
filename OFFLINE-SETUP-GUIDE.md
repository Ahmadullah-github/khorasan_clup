# Making کمپ خراسان App Fully Offline

## Current External Dependencies

Your app currently loads these external resources that prevent offline functionality:

### 1. Bootstrap Framework (ALL pages)
- **CSS**: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css`
- **JS**: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js`

### 2. Bootstrap Icons (ALL pages)
- **CSS**: `https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css`

### 3. Chart.js (Dashboard & Accounting)
- **JS**: `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js`

### 4. Persian Date Picker (Students & Expenses)
- **CSS**: `https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css`
- **JS**: `https://cdn.jsdelivr.net/npm/persian-date@1.0.0/dist/persian-date.min.js`
- **JS**: `https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js`

### 5. jQuery (Expenses page)
- **JS**: `https://code.jquery.com/jquery-3.6.0.min.js`

### 6. Jalali Date Picker (Coach form)
- **CSS**: `https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css`
- **JS**: `https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js`

## Step 1: Download Dependencies

### Option A: Run the PowerShell script (Recommended)
```powershell
.\download-offline-dependencies.ps1
```

### Option B: Run the Batch script
```cmd
download-offline-dependencies.bat
```

### Option C: Manual Download
Create these folders and download files manually:
- `public/vendor/bootstrap/`
- `public/vendor/bootstrap-icons/`
- `public/vendor/chartjs/`
- `public/vendor/jquery/`
- `public/vendor/persian-datepicker/`

## Step 2: Update HTML Files

After downloading, you need to replace ALL external CDN links with local paths in these files:

### Files to Update:
- `public/index.html`
- `public/login.html`
- `public/admin.html`
- `public/students.html`
- `public/coaches.html`
- `public/coach-detail.html`
- `public/coach-form.html`
- `public/expenses.html`
- `public/expense-detail.html`
- `public/rent.html`
- `public/accounting.html`
- `public/reports.html`
- `public/breakdown.html`
- `public/invoice.html`

### Replace These Lines:

**Bootstrap CSS:**
```html
<!-- REPLACE THIS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

<!-- WITH THIS -->
<link href="vendor/bootstrap/bootstrap.rtl.min.css" rel="stylesheet">
```

**Bootstrap Icons:**
```html
<!-- REPLACE THIS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

<!-- WITH THIS -->
<link href="vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
```

**Bootstrap JS:**
```html
<!-- REPLACE THIS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- WITH THIS -->
<script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
```

**Chart.js:**
```html
<!-- REPLACE THIS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- OR -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>

<!-- WITH THIS -->
<script src="vendor/chartjs/chart.min.js"></script>
```

**jQuery:**
```html
<!-- REPLACE THIS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- WITH THIS -->
<script src="vendor/jquery/jquery.min.js"></script>
```

**Persian Date Picker:**
```html
<!-- REPLACE THIS -->
<link href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/persian-date@1.0.0/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>

<!-- WITH THIS -->
<link href="vendor/persian-datepicker/persian-datepicker.min.css" rel="stylesheet">
<script src="vendor/persian-datepicker/persian-date.min.js"></script>
<script src="vendor/persian-datepicker/persian-datepicker.min.js"></script>
```

**Jalali Date Picker:**
```html
<!-- REPLACE THIS -->
<link href="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css" rel="stylesheet">
<script src="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js"></script>

<!-- WITH THIS -->
<link href="vendor/persian-datepicker/jalalidatepicker.min.css" rel="stylesheet">
<script src="vendor/persian-datepicker/jalalidatepicker.min.js"></script>
```

## Step 3: Update Service Worker

Update `public/sw.js` to cache the vendor files:

Add these to the `STATIC_CACHE_FILES` array:
```javascript
// Add these lines to STATIC_CACHE_FILES array
'./vendor/bootstrap/bootstrap.rtl.min.css',
'./vendor/bootstrap/bootstrap.bundle.min.js',
'./vendor/bootstrap-icons/bootstrap-icons.css',
'./vendor/chartjs/chart.min.js',
'./vendor/jquery/jquery.min.js',
'./vendor/persian-datepicker/persian-datepicker.min.css',
'./vendor/persian-datepicker/persian-date.min.js',
'./vendor/persian-datepicker/persian-datepicker.min.js',
'./vendor/persian-datepicker/jalalidatepicker.min.css',
'./vendor/persian-datepicker/jalalidatepicker.min.js'
```

## Step 4: Test Offline Functionality

1. Run the app with internet connection first
2. Open browser DevTools → Application → Service Workers
3. Check "Offline" checkbox
4. Refresh the page
5. App should work completely offline!

## What Will Work Offline After Setup:

✅ **Full UI functionality** - All Bootstrap components and icons
✅ **Charts and graphs** - Chart.js will work offline  
✅ **Date pickers** - Persian/Jalali date selection
✅ **All cached data** - Students, coaches, expenses from previous sessions
✅ **Offline indicators** - Shows when offline/online
✅ **Sync queue** - Actions queued for when connection returns
✅ **PWA installation** - Can be installed as standalone app

## Database Requirement

Note: The PHP backend and MySQL database still need to be running locally for data operations. This setup makes the frontend completely offline-capable, but you'll need:

- Local web server (Apache/Nginx)
- PHP runtime
- MySQL database
- All running locally without internet

The app will then work completely offline as a local system!