@echo off
echo Updating remaining HTML files...

REM Update admin.html
powershell -Command "(Get-Content 'public\admin.html') -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/js/bootstrap\.bundle\.min\.js', 'vendor/bootstrap/bootstrap.bundle.min.js' | Set-Content 'public\admin.html'"

REM Update coaches.html
powershell -Command "(Get-Content 'public\coaches.html') -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/js/bootstrap\.bundle\.min\.js', 'vendor/bootstrap/bootstrap.bundle.min.js' | Set-Content 'public\coaches.html'"

REM Update coach-detail.html
powershell -Command "(Get-Content 'public\coach-detail.html') -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/js/bootstrap\.bundle\.min\.js', 'vendor/bootstrap/bootstrap.bundle.min.js' | Set-Content 'public\coach-detail.html'"

REM Update student-detail.html
powershell -Command "(Get-Content 'public\student-detail.html') -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/js/bootstrap\.bundle\.min\.js', 'vendor/bootstrap/bootstrap.bundle.min.js' | Set-Content 'public\student-detail.html'"

REM Update reports.html
powershell -Command "(Get-Content 'public\reports.html') -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/js/bootstrap\.bundle\.min\.js', 'vendor/bootstrap/bootstrap.bundle.min.js' | Set-Content 'public\reports.html'"

REM Update rent.html
powershell -Command "(Get-Content 'public\rent.html') -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/js/bootstrap\.bundle\.min\.js', 'vendor/bootstrap/bootstrap.bundle.min.js' | Set-Content 'public\rent.html'"

REM Update expense-detail.html
powershell -Command "(Get-Content 'public\expense-detail.html') -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/js/bootstrap\.bundle\.min\.js', 'vendor/bootstrap/bootstrap.bundle.min.js' | Set-Content 'public\expense-detail.html'"

REM Update breakdown.html
powershell -Command "(Get-Content 'public\breakdown.html') -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/js/bootstrap\.bundle\.min\.js', 'vendor/bootstrap/bootstrap.bundle.min.js' | Set-Content 'public\breakdown.html'"

REM Update invoice.html
powershell -Command "(Get-Content 'public\invoice.html') -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.0/dist/css/bootstrap\.rtl\.min\.css', 'vendor/bootstrap/bootstrap.rtl.min.css' -replace 'https://cdn\.jsdelivr\.net/npm/bootstrap-icons@1\.11\.0/font/bootstrap-icons\.css', 'vendor/bootstrap-icons/bootstrap-icons.css' | Set-Content 'public\invoice.html'"

echo All remaining HTML files updated!
echo Now updating service worker...
pause