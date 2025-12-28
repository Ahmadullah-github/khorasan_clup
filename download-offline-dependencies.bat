@echo off
echo Downloading external dependencies for offline use...

REM Create vendor directories
mkdir public\vendor\bootstrap 2>nul
mkdir public\vendor\bootstrap-icons 2>nul
mkdir public\vendor\chartjs 2>nul
mkdir public\vendor\jquery 2>nul
mkdir public\vendor\persian-datepicker 2>nul

echo.
echo Downloading Bootstrap CSS and JS...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css' -OutFile 'public\vendor\bootstrap\bootstrap.rtl.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js' -OutFile 'public\vendor\bootstrap\bootstrap.bundle.min.js'"

echo.
echo Downloading Bootstrap Icons...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css' -OutFile 'public\vendor\bootstrap-icons\bootstrap-icons.css'"

echo.
echo Downloading Chart.js...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js' -OutFile 'public\vendor\chartjs\chart.min.js'"

echo.
echo Downloading jQuery...
powershell -Command "Invoke-WebRequest -Uri 'https://code.jquery.com/jquery-3.6.0.min.js' -OutFile 'public\vendor\jquery\jquery.min.js'"

echo.
echo Downloading Persian Date Picker...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css' -OutFile 'public\vendor\persian-datepicker\persian-datepicker.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/persian-date@1.0.0/dist/persian-date.min.js' -OutFile 'public\vendor\persian-datepicker\persian-date.min.js'"
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js' -OutFile 'public\vendor\persian-datepicker\persian-datepicker.min.js'"

echo.
echo Downloading Jalali Date Picker (alternative)...
powershell -Command "Invoke-WebRequest -Uri 'https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css' -OutFile 'public\vendor\persian-datepicker\jalalidatepicker.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js' -OutFile 'public\vendor\persian-datepicker\jalalidatepicker.min.js'"

echo.
echo All dependencies downloaded successfully!
echo Now updating HTML files to use local dependencies...
pause