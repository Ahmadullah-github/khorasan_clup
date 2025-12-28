# PowerShell script to update all HTML files to use local dependencies
Write-Host "Updating HTML files to use local dependencies..." -ForegroundColor Green

# Get all HTML files in public directory
$htmlFiles = Get-ChildItem -Path "public" -Filter "*.html"

foreach ($file in $htmlFiles) {
    Write-Host "Updating $($file.Name)..." -ForegroundColor Cyan
    
    $content = Get-Content $file.FullName -Raw
    
    # Replace Bootstrap CSS
    $content = $content -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css'
    
    # Replace Bootstrap Icons CSS
    $content = $content -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css'
    
    # Replace Bootstrap JS
    $content = $content -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/js/bootstrap\.bundle\.min\.js', 'vendor/bootstrap/bootstrap.bundle.min.js'
    
    # Replace Chart.js (both versions)
    $content = $content -replace 'https://cdn\.jsdelivr\.net/npm/chart\.js', 'vendor/chartjs/chart.min.js'
    $content = $content -replace 'https://cdn\.jsdelivr\.net/npm/chart\.js@4\.4\.0/dist/chart\.min\.js', 'vendor/chartjs/chart.min.js'
    
    # Replace jQuery
    $content = $content -replace 'https://code\.jquery\.com/jquery-3\.6\.0\.min\.js', 'vendor/jquery/jquery.min.js'
    
    # Replace Persian DatePicker CSS
    $content = $content -replace 'https://cdn\.jsdelivr\.net/npm/persian-datepicker@1\.2\.0/dist/css/persian-datepicker\.min\.css', 'vendor/persian-datepicker/persian-datepicker.min.css'
    
    # Replace Persian Date JS
    $content = $content -replace 'https://cdn\.jsdelivr\.net/npm/persian-date@1\.0\.0/dist/persian-date\.min\.js', 'vendor/persian-datepicker/persian-date.min.js'
    
    # Replace Persian DatePicker JS
    $content = $content -replace 'https://cdn\.jsdelivr\.net/npm/persian-datepicker@1\.2\.0/dist/js/persian-datepicker\.min\.js', 'vendor/persian-datepicker/persian-datepicker.min.js'
    
    # Replace Jalali DatePicker CSS
    $content = $content -replace 'https://unpkg\.com/@majidh1/jalalidatepicker/dist/jalalidatepicker\.min\.css', 'vendor/persian-datepicker/jalalidatepicker.min.css'
    
    # Replace Jalali DatePicker JS
    $content = $content -replace 'https://unpkg\.com/@majidh1/jalalidatepicker/dist/jalalidatepicker\.min\.js', 'vendor/persian-datepicker/jalalidatepicker.min.js'
    
    # Write updated content back to file
    Set-Content -Path $file.FullName -Value $content -Encoding UTF8
    
    Write-Host "✓ Updated: $($file.Name)" -ForegroundColor Green
}

Write-Host "`nAll HTML files updated successfully!" -ForegroundColor Green
Write-Host "Next step: Update service worker to cache vendor files" -ForegroundColor Yellow