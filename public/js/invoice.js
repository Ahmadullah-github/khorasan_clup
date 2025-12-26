/**
 * Invoice Page JavaScript
 * Handles invoice loading, rendering, and sharing
 */

(function() {
    'use strict';
    
    var invoiceId = new URLSearchParams(window.location.search).get('id');
    var invoiceData = null;

    var DARI_MONTHS = ['حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله', 'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'];

    function formatJalaliDateFormal(dateStr) {
        if (!dateStr) return '—';
        var parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        var day = parseInt(parts[2]);
        var month = parseInt(parts[1]);
        var year = parseInt(parts[0]);
        return day + ' ' + DARI_MONTHS[month - 1] + ' ' + year;
    }

    function getGregorianDate() {
        var now = new Date();
        var options = { year: 'numeric', month: 'long', day: 'numeric' };
        return now.toLocaleDateString('en-US', options);
    }

    function toPersianDigits(num) {
        var persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return num.toString().replace(/\d/g, function(d) { return persianDigits[d]; });
    }

    function formatCurrencyFormal(amount) {
        var formatted = Number(amount).toLocaleString('en-US');
        return toPersianDigits(formatted) + ' افغانی';
    }

    function numberToWords(num) {
        var ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
        var tens = ['', 'ده', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
        var teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
        var hundreds = ['', 'یکصد', 'دوصد', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
        
        num = Math.floor(Number(num));
        if (num === 0) return 'صفر';
        if (num < 0) return 'منفی ' + numberToWords(-num);
        
        var words = '';
        if (num >= 1000000) { words += numberToWords(Math.floor(num / 1000000)) + ' میلیون '; num %= 1000000; }
        if (num >= 1000) { var t = Math.floor(num / 1000); words += (t === 1 ? 'یک هزار ' : numberToWords(t) + ' هزار '); num %= 1000; }
        if (num >= 100) { words += hundreds[Math.floor(num / 100)] + ' '; num %= 100; }
        if (num >= 20) { words += tens[Math.floor(num / 10)]; if (num % 10 > 0) words += ' و ' + ones[num % 10]; words += ' '; }
        else if (num >= 10) { words += teens[num - 10] + ' '; }
        else if (num > 0) { words += ones[num] + ' '; }
        return words.trim();
    }


    function loadInvoice() {
        if (!invoiceId) { showError('شناسه فاکتور مشخص نشده است'); return; }
        
        APIClient.get('invoices.php?id=' + invoiceId)
            .then(function(data) {
                if (data.success) {
                    invoiceData = data.data;
                    renderInvoice(invoiceData);
                    enableButtons();
                } else {
                    showError(data.error || 'خطا در بارگذاری فاکتور');
                }
            })
            .catch(function(error) {
                console.error('Invoice load error:', error);
                showError('خطا در بارگذاری فاکتور');
            });
    }

    function showError(message) {
        var html = '<div class="invoice-error">';
        html += '<div class="invoice-error-icon"><i class="bi bi-exclamation-triangle"></i></div>';
        html += '<p class="invoice-error-text">' + message + '</p>';
        html += '<a href="students.html" class="btn-app btn-app--primary mt-3">';
        html += '<i class="bi bi-arrow-right"></i> بازگشت به لیست</a></div>';
        document.getElementById('invoiceContent').innerHTML = html;
    }

    function enableButtons() {
        document.getElementById('printBtn').disabled = false;
        document.getElementById('whatsappBtn').disabled = false;
    }

    window.shareWhatsApp = function() {
        if (!invoiceData) return;
        var btn = document.getElementById('whatsappBtn');
        var originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        APIClient.post('invoices.php?id=' + invoiceId + '&action=whatsapp', { method: 'link' })
            .then(function(result) {
                if (result.success && result.data.whatsapp_link) {
                    window.open(result.data.whatsapp_link, '_blank');
                } else {
                    notify.error('خطا در ایجاد لینک واتساپ');
                }
            })
            .catch(function() { notify.error('خطا در ایجاد لینک واتساپ'); })
            .finally(function() { btn.disabled = false; btn.innerHTML = originalHTML; });
    };


    function renderInvoice(inv) {
        var jalaliDate = formatJalaliDateFormal(inv.issued_date_jalali);
        var gregorianDate = getGregorianDate();
        var periodStart = formatJalaliDateFormal(inv.start_date_jalali);
        var periodEnd = formatJalaliDateFormal(inv.end_date_jalali);
        var amountWords = numberToWords(inv.total_amount);
        var amountFormatted = formatCurrencyFormal(inv.total_amount);
        var currentYear = toPersianDigits(new Date().getFullYear());
        
        var html = '<div class="invoice-border"><div class="invoice-border-inner">';
        
        // Header
        html += '<header class="invoice-header-official">';
        html += '<div class="header-side header-right"><div class="official-seal">';
        html += '<div class="seal-outer"><div class="seal-inner"><span class="seal-icon">🤸‍♂️</span></div></div>';
        html += '<span class="seal-text">مهر رسمی</span></div></div>';
        
        html += '<div class="header-center">';
        html += '<div class="header-emblem">⚜️</div>';
        html += '<h1 class="header-title">کمپ ورزشی خراسان</h1>';
        html += '<p class="header-subtitle">KHORASAN SPORTS CAMP</p>';
        html += '<div class="header-divider"></div>';
        html += '<h2 class="document-title">فاکتور رسمی</h2>';
        html += '<p class="document-subtitle">OFFICIAL INVOICE</p></div>';
        
        html += '<div class="header-side header-left"><div class="invoice-meta-box">';
        html += '<div class="meta-row"><span class="meta-label">شماره فاکتور:</span>';
        html += '<span class="meta-value">' + inv.invoice_number + '</span></div>';
        html += '<div class="meta-row"><span class="meta-label">تاریخ شمسی:</span>';
        html += '<span class="meta-value">' + jalaliDate + '</span></div>';
        html += '<div class="meta-row"><span class="meta-label">تاریخ میلادی:</span>';
        html += '<span class="meta-value" dir="ltr">' + gregorianDate + '</span></div>';
        html += '</div></div></header>';
        
        // Main content
        html += '<main class="invoice-main">';
        
        // Student info section
        html += '<section class="invoice-section"><div class="section-header">';
        html += '<span class="section-icon">👤</span>';
        html += '<h3 class="section-title">مشخصات دانش‌آموز / Student Information</h3></div>';
        html += '<div class="info-grid">';
        html += '<div class="info-item"><span class="info-label">نام و نام خانوادگی:</span>';
        html += '<span class="info-value">' + inv.first_name + ' ' + inv.last_name + '</span></div>';
        html += '<div class="info-item"><span class="info-label">نام پدر:</span>';
        html += '<span class="info-value">' + (inv.father_name || '—') + '</span></div>';
        html += '<div class="info-item"><span class="info-label">شماره تماس:</span>';
        html += '<span class="info-value" dir="ltr">' + (inv.contact_number || '—') + '</span></div>';
        html += '<div class="info-item"><span class="info-label">مربی:</span>';
        html += '<span class="info-value">' + inv.coach_first_name + ' ' + inv.coach_last_name + '</span></div>';
        html += '</div></section>';

        
        // Billing table section
        html += '<section class="invoice-section"><div class="section-header">';
        html += '<span class="section-icon">📋</span>';
        html += '<h3 class="section-title">جزئیات صورتحساب / Billing Details</h3></div>';
        html += '<table class="billing-table"><thead><tr>';
        html += '<th class="col-num">ردیف</th>';
        html += '<th class="col-desc">شرح خدمات</th>';
        html += '<th class="col-period">دوره</th>';
        html += '<th class="col-qty">تعداد</th>';
        html += '<th class="col-price">مبلغ واحد</th>';
        html += '<th class="col-total">مبلغ کل</th>';
        html += '</tr></thead><tbody><tr>';
        html += '<td class="col-num">' + toPersianDigits(1) + '</td>';
        html += '<td class="col-desc"><strong>حق‌الاشتراک ماهانه</strong><br>';
        html += '<small>زمان کلاس: ' + inv.time_slot_name + '</small></td>';
        html += '<td class="col-period">' + periodStart + '<br>الی ' + periodEnd + '</td>';
        html += '<td class="col-qty">' + toPersianDigits(1) + ' ماه</td>';
        html += '<td class="col-price">' + amountFormatted + '</td>';
        html += '<td class="col-total">' + amountFormatted + '</td>';
        html += '</tr></tbody><tfoot>';
        html += '<tr class="subtotal-row"><td colspan="5" class="text-left">جمع جزء (Subtotal):</td>';
        html += '<td>' + amountFormatted + '</td></tr>';
        html += '<tr class="discount-row"><td colspan="5" class="text-left">تخفیف (Discount):</td>';
        html += '<td>' + toPersianDigits(0) + ' افغانی</td></tr>';
        html += '<tr class="grand-total-row"><td colspan="5" class="text-left"><strong>مبلغ قابل پرداخت (Total Due):</strong></td>';
        html += '<td><strong>' + amountFormatted + '</strong></td></tr>';
        html += '</tfoot></table></section>';
        
        // Amount in words
        html += '<div class="amount-words">';
        html += '<span class="amount-words-label">مبلغ به حروف:</span>';
        html += '<span class="amount-words-value">' + amountWords + ' افغانی</span></div>';
        html += '</main>';

        
        // Footer
        html += '<footer class="invoice-footer-official">';
        html += '<div class="footer-notes"><h4>توضیحات / Notes:</h4><ul>';
        html += '<li>این فاکتور به منزله رسید پرداخت می‌باشد.</li>';
        html += '<li>This invoice serves as proof of payment.</li></ul></div>';
        
        // Signatures
        html += '<div class="signatures-row">';
        html += '<div class="signature-box"><div class="signature-line"></div>';
        html += '<p class="signature-title">امضای دریافت کننده</p>';
        html += '<p class="signature-subtitle">Recipient Signature</p></div>';
        
        html += '<div class="signature-box signature-box--filled">';
        html += '<div class="digital-signature">';
        html += '<span class="signature-text">کامران منصوری</span>';
        html += '<span class="signature-text-en">Kamran Mansoori</span></div>';
        html += '<div class="signature-line"></div>';
        html += '<p class="signature-title">امضای مسئول / مدیر</p>';
        html += '<p class="signature-subtitle">Authorized Signature</p></div></div>';
        
        // Footer bottom
        html += '<div class="footer-bottom">';
        html += '<div class="footer-brand"><span>🤸‍♂️</span><span>کمپ ورزشی خراسان</span></div>';
        html += '<div class="footer-copy">© ' + currentYear + ' - تمامی حقوق محفوظ است</div>';
        html += '</div></footer>';
        
        html += '</div></div>';
        
        document.getElementById('invoiceContent').innerHTML = html;
    }

    // Initialize
    loadInvoice();
})();
