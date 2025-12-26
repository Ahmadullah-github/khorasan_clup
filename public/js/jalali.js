/**
 * Jalali (Shamsi) Date Utilities
 * Dari month names and date conversion functions
 */

// Dari month names in Persian script
const jalaliMonths = {
    1: 'حمل', 2: 'ثور', 3: 'جوزا', 4: 'سرطان',
    5: 'اسد', 6: 'سنبله', 7: 'میزان', 8: 'عقرب',
    9: 'قوس', 10: 'جدی', 11: 'دلو', 12: 'حوت'
};

// English month names (for reference)
const jalaliMonthsEn = {
    1: 'Hamal', 2: 'Saur', 3: 'Jawza', 4: 'Saratan',
    5: 'Asad', 6: 'Sonbola', 7: 'Mizan', 8: 'Aqrab',
    9: 'Qaws', 10: 'Jadi', 11: 'Dalv', 12: 'Hoot'
};

/**
 * Convert Gregorian to Jalali (corrected algorithm)
 */
function gregorianToJalali(gYear, gMonth, gDay) {
    const g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    
    let gy = gYear - 1600;
    let gm = gMonth - 1;
    let gd = gDay - 1;
    
    let g_day_no = 365 * gy + Math.floor((gy + 3) / 4) - Math.floor((gy + 99) / 100) + Math.floor((gy + 399) / 400);
    
    for (let i = 0; i < gm; ++i) {
        g_day_no += g_days_in_month[i];
    }
    
    // Add leap day if applicable
    if (gm > 1 && ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0))) {
        g_day_no++;
    }
    
    g_day_no += gd;
    
    let j_day_no = g_day_no - 79;
    
    let j_np = Math.floor(j_day_no / 12053);
    j_day_no = j_day_no % 12053;
    
    let jy = 979 + 33 * j_np + 4 * Math.floor(j_day_no / 1461);
    j_day_no %= 1461;
    
    if (j_day_no >= 366) {
        jy += Math.floor((j_day_no - 1) / 365);
        j_day_no = (j_day_no - 1) % 365;
    }
    
    let jm;
    for (jm = 0; jm < 11 && j_day_no >= j_days_in_month[jm]; ++jm) {
        j_day_no -= j_days_in_month[jm];
    }
    
    let jd = j_day_no + 1;
    
    return {
        year: jy,
        month: jm + 1,
        day: jd
    };
}

/**
 * Convert Jalali to Gregorian
 */
function jalaliToGregorian(jYear, jMonth, jDay) {
    const gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    
    let jy = jYear - 979;
    let jm = jMonth - 1;
    let jd = jDay - 1;
    
    let jDayNo = 365 * jy + Math.floor(jy / 33) * 8 + Math.floor((jy % 33 + 3) / 4) + 78 + jd;
    
    for (let i = 0; i < jm; ++i) {
        jDayNo += jDaysInMonth[i];
    }
    
    let gDayNo = jDayNo + 79;
    let gy = 1600 + 400 * Math.floor(gDayNo / 146097);
    gDayNo = gDayNo % 146097;
    
    let leap = 1;
    if (gDayNo >= 36525) {
        gDayNo--;
        gy += 100 * Math.floor(gDayNo / 36524);
        gDayNo = gDayNo % 36524;
        if (gDayNo >= 365) {
            gDayNo++;
        } else {
            leap = 0;
        }
    }
    
    gy += 4 * Math.floor(gDayNo / 1461);
    gDayNo %= 1461;
    
    if (gDayNo >= 366) {
        leap = 0;
        gDayNo--;
        gy += Math.floor(gDayNo / 365);
        gDayNo = gDayNo % 365;
    }
    
    let gm = 0;
    for (let i = 0; gDayNo >= gDaysInMonth[i] + (i === 1 && leap); i++) {
        gDayNo -= gDaysInMonth[i] + (i === 1 && leap);
        gm = i + 1;
    }
    const gd = gDayNo + 1;
    
    return {
        year: gy,
        month: gm,
        day: gd
    };
}

/**
 * Get current Jalali date as string (YYYY-MM-DD)
 */
function getCurrentJalaliDate() {
    const now = new Date();
    const jalali = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    return `${jalali.year}-${String(jalali.month).padStart(2, '0')}-${String(jalali.day).padStart(2, '0')}`;
}

/**
 * Get current Jalali year
 */
function getCurrentJalaliYear() {
    const now = new Date();
    const jalali = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    return jalali.year;
}

/**
 * Format Jalali date for display
 * @param {string} jalaliDate - YYYY-MM-DD format
 * @returns {string} Formatted date (e.g., "15 حمل 1403")
 */
function formatJalaliDate(jalaliDate) {
    if (!jalaliDate || jalaliDate === '0000-00-00' || jalaliDate === '') {
        return '---';
    }
    
    // Handle different possible formats
    var parts = jalaliDate.toString().split('-');
    if (parts.length !== 3) {
        return jalaliDate;
    }
    
    var year = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10);
    var day = parseInt(parts[2], 10);
    
    // Validate
    if (isNaN(year) || isNaN(month) || isNaN(day)) {
        return jalaliDate;
    }
    
    var monthName = jalaliMonths[month] || month;
    
    // Return in format: "day monthName year" (e.g., "15 میزان 1404")
    return day + ' ' + monthName + ' ' + year;
}

/**
 * Validate Jalali date string
 */
function validateJalaliDate(date) {
    if (!date) return false;
    
    const parts = date.split('-');
    if (parts.length !== 3) return false;
    
    const year = parseInt(parts[0]);
    const month = parseInt(parts[1]);
    const day = parseInt(parts[2]);
    
    if (year < 1300 || year > 1500) return false;
    if (month < 1 || month > 12) return false;
    if (day < 1 || day > 31) return false;
    
    const daysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    if (day > daysInMonth[month - 1]) {
        if (month === 12 && day === 30) {
            // Check for leap year (simplified)
            return true;
        }
        return false;
    }
    
    return true;
}

/**
 * Get month name in Dari
 */
function getJalaliMonthName(month) {
    return jalaliMonths[month] || '';
}

/**
 * Parse Jalali date string to object
 */
function parseJalaliDate(jalaliDate) {
    const parts = jalaliDate.split('-');
    return {
        year: parseInt(parts[0]),
        month: parseInt(parts[1]),
        day: parseInt(parts[2])
    };
}

/**
 * Format Jalali date to string (YYYY-MM-DD)
 */
function formatJalaliDateString(year, month, day) {
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

// Export functions to window for global access
window.gregorianToJalali = gregorianToJalali;
window.jalaliToGregorian = jalaliToGregorian;
window.getCurrentJalaliDate = getCurrentJalaliDate;
window.getCurrentJalaliYear = getCurrentJalaliYear;
window.formatJalaliDate = formatJalaliDate;
window.validateJalaliDate = validateJalaliDate;
window.getJalaliMonthName = getJalaliMonthName;
window.parseJalaliDate = parseJalaliDate;
window.formatJalaliDateString = formatJalaliDateString;
window.jalaliMonths = jalaliMonths;

