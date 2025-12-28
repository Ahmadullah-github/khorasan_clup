# PowerShell script to download external dependencies for offline use
Write-Host "Downloading external dependencies for offline use..." -ForegroundColor Green

# Create vendor directories
$directories = @(
    "public\vendor\bootstrap",
    "public\vendor\bootstrap-icons", 
    "public\vendor\chartjs",
    "public\vendor\jquery",
    "public\vendor\persian-datepicker"
)

foreach ($dir in $directories) {
    if (!(Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "Created directory: $dir" -ForegroundColor Yellow
    }
}

# Download files
$downloads = @(
    @{
        Url = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"
        Path = "public\vendor\bootstrap\bootstrap.rtl.min.css"
        Name = "Bootstrap RTL CSS"
    },
    @{
        Url = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        Path = "public\vendor\bootstrap\bootstrap.bundle.min.js"
        Name = "Bootstrap JS Bundle"
    },
    @{
        Url = "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
        Path = "public\vendor\bootstrap-icons\bootstrap-icons.css"
        Name = "Bootstrap Icons CSS"
    },
    @{
        Url = "https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"
        Path = "public\vendor\chartjs\chart.min.js"
        Name = "Chart.js"
    },
    @{
        Url = "https://code.jquery.com/jquery-3.6.0.min.js"
        Path = "public\vendor\jquery\jquery.min.js"
        Name = "jQuery"
    },
    @{
        Url = "https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css"
        Path = "public\vendor\persian-datepicker\persian-datepicker.min.css"
        Name = "Persian DatePicker CSS"
    },
    @{
        Url = "https://cdn.jsdelivr.net/npm/persian-date@1.0.0/dist/persian-date.min.js"
        Path = "public\vendor\persian-datepicker\persian-date.min.js"
        Name = "Persian Date JS"
    },
    @{
        Url = "https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"
        Path = "public\vendor\persian-datepicker\persian-datepicker.min.js"
        Name = "Persian DatePicker JS"
    },
    @{
        Url = "https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css"
        Path = "public\vendor\persian-datepicker\jalalidatepicker.min.css"
        Name = "Jalali DatePicker CSS"
    },
    @{
        Url = "https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js"
        Path = "public\vendor\persian-datepicker\jalalidatepicker.min.js"
        Name = "Jalali DatePicker JS"
    }
)

foreach ($download in $downloads) {
    try {
        Write-Host "Downloading $($download.Name)..." -ForegroundColor Cyan
        Invoke-WebRequest -Uri $download.Url -OutFile $download.Path -UseBasicParsing
        Write-Host "✓ Downloaded: $($download.Path)" -ForegroundColor Green
    }
    catch {
        Write-Host "✗ Failed to download $($download.Name): $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host "`nAll downloads completed!" -ForegroundColor Green
Write-Host "Next step: Update HTML files to use local dependencies" -ForegroundColor Yellow