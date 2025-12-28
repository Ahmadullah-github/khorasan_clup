/**
 * کمپ خراسان Management System - Main Application JavaScript
 * API client, form validation, pagination, dark mode, utilities
 */

// API base path - uses relative path from public/ folder to api/ folder
// Works universally: just clone the repo anywhere in htdocs and it works!
const API_BASE = '../api/';

// API Client with Offline Support
class APIClient {
    static async request(endpoint, options = {}) {
        const url = API_BASE + endpoint;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        };

        const config = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, config);
            
            // Get response text first to handle empty responses
            const text = await response.text();
            
            // If response is empty, throw error
            if (!text || text.trim() === '') {
                throw new Error('پاسخ خالی از سرور');
            }
            
            // Try to parse JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', text);
                throw new Error('پاسخ نامعتبر از سرور. لطفاً کنسول را بررسی کنید.');
            }
            
            if (!response.ok && !data.success) {
                throw new Error(data.error || 'درخواست ناموفق');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            
            // If offline manager is available, try offline fallback
            if (window.offlineManager && !navigator.onLine) {
                return this.handleOfflineRequest(endpoint, options);
            }
            
            throw error;
        }
    }
    
    static async handleOfflineRequest(endpoint, options) {
        // Handle offline requests through offline manager
        if (endpoint.includes('students.php') && options.method === 'GET') {
            return await window.offlineManager.getStudents();
        }
        
        // For other requests, show offline error
        throw new Error('شبکه در دسترس نیست. برخی عملیات در حالت آفلاین امکان‌پذیر نیست.');
    }

    static get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    }

    static post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    static put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    static delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
}

// Dark Mode Management
let darkModeInitialized = false;

function initDarkMode() {
    const theme = localStorage.getItem('theme') || 'light';
    applyTheme(theme);
    
    // Prevent multiple event listeners from being attached
    if (darkModeInitialized) return;
    darkModeInitialized = true;
    
    // Support for new pill-style theme switch
    const themeSwitch = document.getElementById('themeSwitch');
    if (themeSwitch) {
        themeSwitch.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }
    
    // Support for old button-style toggle (backward compatibility)
    const toggle = document.getElementById('darkModeToggle');
    if (toggle && !themeSwitch) {
        toggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.setAttribute('data-bs-theme', theme);
    
    // Update old-style icon if exists
    const icon = document.getElementById('darkModeIcon');
    if (icon) {
        if (icon.classList.contains('bi')) {
            icon.classList.remove('bi-moon-fill', 'bi-sun-fill');
            icon.classList.add(theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill');
        } else {
            icon.textContent = theme === 'dark' ? '☀️' : '🌙';
        }
    }
}

// Pagination Component
class Pagination {
    constructor(containerId, currentPage, totalPages, onPageChange) {
        this.container = document.getElementById(containerId);
        this.currentPage = currentPage;
        this.totalPages = totalPages;
        this.onPageChange = onPageChange;
        this.render();
    }

    render() {
        if (!this.container) return;

        let html = '<nav><ul class="pagination justify-content-center">';
        
        // Previous button
        html += `<li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${this.currentPage - 1}">قبلی</a>
        </li>`;
        
        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(this.totalPages, this.currentPage + 2);
        
        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === this.currentPage ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }
        
        if (endPage < this.totalPages) {
            if (endPage < this.totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${this.totalPages}">${this.totalPages}</a></li>`;
        }
        
        // Next button
        html += `<li class="page-item ${this.currentPage === this.totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${this.currentPage + 1}">بعدی</a>
        </li>`;
        
        html += '</ul></nav>';
        this.container.innerHTML = html;
        
        // Attach event listeners
        this.container.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.getAttribute('data-page'));
                if (page && page !== this.currentPage && page >= 1 && page <= this.totalPages) {
                    this.onPageChange(page);
                }
            });
        });
    }
}

// Search with Debounce
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
            if (!firstInvalidField) {
                firstInvalidField = field;
            }
        } else {
            field.classList.remove('is-invalid');
        }
    });

    // Scroll to first invalid field and focus it
    if (firstInvalidField) {
        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => firstInvalidField.focus(), 300);
    }

    return isValid;
}

// ============================================
// Notification System
// ============================================

const notify = {
    /**
     * Success Snackbar - Bottom center, auto-dismiss 3s
     * Use for: successful operations (save, delete, update)
     */
    success(message) {
        this._showSnackbar(message, 'success');
    },

    /**
     * Validation Error - Inline near field or form
     * Use for: form validation errors
     * @param {string} message - Error message
     * @param {HTMLElement|string} target - Field element or form ID
     */
    validation(message, target) {
        const element = typeof target === 'string' ? document.getElementById(target) : target;
        if (!element) {
            // Fallback to snackbar if no target
            this._showSnackbar(message, 'warning');
            return;
        }
        
        // Add invalid state to field
        element.classList.add('is-invalid');
        
        // Find or create inline error element
        let errorEl = element.parentElement.querySelector('.notify-inline-error');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'notify-inline-error';
            element.parentElement.appendChild(errorEl);
        }
        
        errorEl.innerHTML = `<i class="bi bi-exclamation-circle"></i> ${message}`;
        errorEl.classList.add('notify-inline-show');
        
        // Scroll to field
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus();
        
        // Clear on input
        const clearHandler = () => {
            element.classList.remove('is-invalid');
            errorEl.classList.remove('notify-inline-show');
            element.removeEventListener('input', clearHandler);
        };
        element.addEventListener('input', clearHandler);
    },

    /**
     * API/Server Error - Top banner, stays until dismissed
     * Use for: network errors, server errors, loading failures
     */
    error(message) {
        // Remove existing error banner
        const existing = document.getElementById('notifyErrorBanner');
        if (existing) existing.remove();
        
        const banner = document.createElement('div');
        banner.id = 'notifyErrorBanner';
        banner.className = 'notify-banner notify-banner--error';
        banner.innerHTML = `
            <div class="notify-banner-content">
                <i class="bi bi-wifi-off notify-banner-icon"></i>
                <span class="notify-banner-message">${message}</span>
            </div>
            <button class="notify-banner-close" onclick="this.parentElement.remove()">
                <i class="bi bi-x-lg"></i>
            </button>
        `;
        
        // Insert at top of body (after navbar if exists)
        const navbar = document.querySelector('.navbar-app');
        if (navbar) {
            navbar.insertAdjacentElement('afterend', banner);
        } else {
            document.body.insertBefore(banner, document.body.firstChild);
        }
        
        // Animate in
        requestAnimationFrame(() => banner.classList.add('notify-banner-show'));
    },

    /**
     * Critical Error - Modal dialog, requires acknowledgment
     * Use for: session expiry, critical failures, destructive confirmations
     * @param {string} message - Error message
     * @param {Function} onConfirm - Callback when user acknowledges
     */
    critical(message, onConfirm = null) {
        // Remove existing modal
        const existing = document.getElementById('notifyCriticalModal');
        if (existing) existing.remove();
        
        const modal = document.createElement('div');
        modal.id = 'notifyCriticalModal';
        modal.className = 'notify-modal-overlay';
        modal.innerHTML = `
            <div class="notify-modal">
                <div class="notify-modal-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="notify-modal-title">خطای بحرانی</div>
                <div class="notify-modal-message">${message}</div>
                <button class="notify-modal-btn" id="notifyCriticalBtn">متوجه شدم</button>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Animate in
        requestAnimationFrame(() => modal.classList.add('notify-modal-show'));
        
        // Handle confirm
        document.getElementById('notifyCriticalBtn').addEventListener('click', () => {
            modal.classList.remove('notify-modal-show');
            setTimeout(() => {
                modal.remove();
                if (onConfirm) onConfirm();
            }, 300);
        });
    },

    /**
     * Internal: Show snackbar notification
     */
    _showSnackbar(message, type) {
        // Get or create snackbar container
        let container = document.getElementById('notifySnackbar');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifySnackbar';
            container.className = 'notify-snackbar-container';
            document.body.appendChild(container);
        }
        
        const icons = {
            success: 'bi-check-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            error: 'bi-x-circle-fill',
            info: 'bi-info-circle-fill'
        };
        
        const snackbar = document.createElement('div');
        snackbar.className = `notify-snackbar notify-snackbar--${type}`;
        snackbar.innerHTML = `
            <i class="bi ${icons[type] || icons.info} notify-snackbar-icon"></i>
            <span class="notify-snackbar-message">${message}</span>
        `;
        
        container.appendChild(snackbar);
        
        // Animate in
        requestAnimationFrame(() => snackbar.classList.add('notify-snackbar-show'));
        
        // Auto-dismiss after 3s
        setTimeout(() => {
            snackbar.classList.remove('notify-snackbar-show');
            snackbar.classList.add('notify-snackbar-hide');
            setTimeout(() => snackbar.remove(), 300);
        }, 3000);
    },

    /**
     * Clear all inline validation errors in a form
     */
    clearValidation(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.notify-inline-error').forEach(el => el.remove());
    }
};

// Legacy showAlert - maps to new system for backward compatibility
function showAlert(message, type = 'info') {
    if (type === 'success') {
        notify.success(message);
    } else if (type === 'danger') {
        notify.error(message);
    } else {
        notify._showSnackbar(message, type);
    }
}

// Format Number (Persian/Dari digits)
function formatNumber(num) {
    return new Intl.NumberFormat('fa-IR').format(num);
}

// Format Currency - Uses dynamic currency label from AppSettings
function formatCurrency(amount) {
    const currencyLabel = (typeof AppSettings !== 'undefined' && AppSettings.getCurrencyLabel) 
        ? AppSettings.getCurrencyLabel() 
        : 'افغانی';
    return formatNumber(amount) + ' ' + currencyLabel;
}

// Loading Spinner
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    }
}

// Check Authentication
function checkAuth() {
    const user = JSON.parse(localStorage.getItem('user') || 'null');
    if (!user) {
        window.location.href = 'login.html';
        return false;
    }
    return user;
}

// Logout
function logout() {
    APIClient.post('auth.php?action=logout')
        .then(() => {
            localStorage.removeItem('user');
            window.location.href = 'login.html';
        })
        .catch(() => {
            localStorage.removeItem('user');
            window.location.href = 'login.html';
        });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initDarkMode();
});

// Export utilities
window.APIClient = APIClient;
window.Pagination = Pagination;
window.formatNumber = formatNumber;
window.formatCurrency = formatCurrency;
window.showAlert = showAlert;
window.notify = notify;
window.checkAuth = checkAuth;
window.logout = logout;
window.debounce = debounce;
window.validateForm = validateForm;

