/**
 * کمپ خراسان Management System - Main Application JavaScript
 * API client, form validation, pagination, dark mode, utilities
 */

// Dynamically determine API base path
// Works for both /sports-camp/public/ and /khorasan/public/ or direct access
const API_BASE = (() => {
    const path = window.location.pathname;
    // Extract base path (e.g., /sports-camp/ or /khorasan/)
    const match = path.match(/^(\/[^\/]+\/)/);
    if (match) {
        return match[1] + 'api/';
    }
    // Fallback to relative path
    return '../api/';
})();

// API Client
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
                throw new Error('Empty response from server');
            }
            
            // Try to parse JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', text);
                throw new Error('Invalid response from server. Please check the console for details.');
            }
            
            if (!response.ok && !data.success) {
                throw new Error(data.error || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
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

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
        } else {
            field.classList.remove('is-invalid');
        }
    });

    return isValid;
}

// Show Alert/Notification
function showAlert(message, type = 'info', containerId = 'alertContainer') {
    const container = document.getElementById(containerId) || document.body;
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Format Number (Persian/Dari digits)
function formatNumber(num) {
    return new Intl.NumberFormat('fa-IR').format(num);
}

// Format Currency
function formatCurrency(amount) {
    return formatNumber(amount) + ' افغانی';
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
window.checkAuth = checkAuth;
window.logout = logout;
window.debounce = debounce;
window.validateForm = validateForm;

