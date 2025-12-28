/**
 * Setup Wizard JavaScript
 * Multi-step wizard for first-time app configuration
 * With real-time validation and data persistence
 */

(function() {
    'use strict';

    // ============================================
    // DEFAULT VALUES (Current hardcoded values)
    // ============================================
    const DEFAULTS = {
        organization: {
            name_fa: 'کمپ خراسان',
            name_en: 'Khorasan Sports Camp',
            slogan: 'سیستم مدیریت باشگاه ورزشی',
            phone: '',
            city: '',
            address: ''
        },
        manager: {
            name_fa: 'کامران منصوری',
            name_en: 'Kamran Mansoori',
            title: 'مدیر باشگاه',
            phone: '',
            email: ''
        },
        financial: {
            currency: 'AFN',
            currency_label: 'افغانی',
            default_percentage: 50,
            default_hybrid_percentage: 25,
            fiscal_year_start: 1404
        },
        categories: [
            { key: 'Rent', label: 'اجاره' },
            { key: 'Equipment', label: 'تجهیزات' },
            { key: 'Taxes', label: 'مالیات' },
            { key: 'Services', label: 'خدمات' },
            { key: 'Other', label: 'سایر' }
        ]
    };

    // ============================================
    // STATE
    // ============================================
    let currentStep = 1;
    const totalSteps = 5;
    let wizardData = JSON.parse(JSON.stringify(DEFAULTS)); // Deep clone defaults
    let categories = [...DEFAULTS.categories];

    // ============================================
    // DOM ELEMENTS
    // ============================================
    const elements = {
        steps: document.querySelectorAll('.wizard-step'),
        progressSteps: document.querySelectorAll('.progress-step'),
        prevBtn: document.getElementById('prevBtn'),
        nextBtn: document.getElementById('nextBtn'),
        finishBtn: document.getElementById('finishBtn'),
        skipBtn: document.getElementById('skipBtn'),
        skipModal: document.getElementById('skipModal'),
        successModal: document.getElementById('successModal'),
        cancelSkipBtn: document.getElementById('cancelSkipBtn'),
        confirmSkipBtn: document.getElementById('confirmSkipBtn'),
        goToDashboardBtn: document.getElementById('goToDashboardBtn'),
        categoriesList: document.getElementById('categoriesList'),
        addCategoryBtn: document.getElementById('addCategoryBtn')
    };

    // ============================================
    // VALIDATION RULES
    // ============================================
    const validators = {
        // Required field
        required: (value, fieldName) => {
            if (!value || value.trim() === '') {
                return `${fieldName} الزامی است`;
            }
            return null;
        },

        // Persian/Dari text only
        persianText: (value, fieldName) => {
            if (!value) return null;
            // Allow Persian/Arabic characters, spaces, and common punctuation
            const persianRegex = /^[\u0600-\u06FF\u0750-\u077F\uFB50-\uFDFF\uFE70-\uFEFF\s\d\.\,\-\(\)]+$/;
            if (!persianRegex.test(value)) {
                return `${fieldName} باید فقط شامل حروف فارسی/دری باشد`;
            }
            return null;
        },

        // English text only
        englishText: (value, fieldName) => {
            if (!value) return null;
            const englishRegex = /^[a-zA-Z\s\d\.\,\-\(\)\']+$/;
            if (!englishRegex.test(value)) {
                return `${fieldName} باید فقط شامل حروف انگلیسی باشد`;
            }
            return null;
        },

        // Phone number (Afghan format)
        phone: (value) => {
            if (!value) return null;
            const phoneRegex = /^0[0-9]{9,10}$/;
            if (!phoneRegex.test(value)) {
                return 'شماره تماس باید با 0 شروع شود و 10-11 رقم باشد';
            }
            return null;
        },

        // Email
        email: (value) => {
            if (!value) return null;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                return 'فرمت ایمیل صحیح نیست';
            }
            return null;
        },

        // Percentage (0-100)
        percentage: (value) => {
            const num = parseFloat(value);
            if (isNaN(num) || num < 0 || num > 100) {
                return 'درصد باید بین 0 تا 100 باشد';
            }
            return null;
        },

        // Year (Jalali)
        jalaliYear: (value) => {
            const num = parseInt(value);
            if (isNaN(num) || num < 1400 || num > 1450) {
                return 'سال باید بین 1400 تا 1450 باشد';
            }
            return null;
        },

        // Category key (English, no spaces)
        categoryKey: (value) => {
            if (!value) return 'کلید دسته الزامی است';
            const keyRegex = /^[a-zA-Z][a-zA-Z0-9_]*$/;
            if (!keyRegex.test(value)) {
                return 'کلید باید با حرف انگلیسی شروع شود و فقط شامل حروف، اعداد و _ باشد';
            }
            // Check for duplicates
            if (categories.some(c => c.key.toLowerCase() === value.toLowerCase())) {
                return 'این کلید قبلاً استفاده شده است';
            }
            return null;
        }
    };

    // ============================================
    // VALIDATION FUNCTIONS
    // ============================================
    function validateField(input, validatorFns, fieldName) {
        const value = input.value.trim();
        const errorEl = document.getElementById(input.id + 'Error');
        
        for (const validate of validatorFns) {
            const error = validate(value, fieldName);
            if (error) {
                input.classList.add('error');
                input.classList.remove('success');
                if (errorEl) errorEl.textContent = error;
                return false;
            }
        }
        
        input.classList.remove('error');
        if (value) input.classList.add('success');
        if (errorEl) errorEl.textContent = '';
        return true;
    }

    function validateStep(step) {
        let isValid = true;

        switch (step) {
            case 1: // Organization
                const orgNameFa = document.getElementById('orgNameFa');
                if (!validateField(orgNameFa, [validators.required], 'نام سازمان')) {
                    isValid = false;
                }
                
                const orgPhone = document.getElementById('orgPhone');
                if (orgPhone.value && !validateField(orgPhone, [validators.phone], 'شماره تماس')) {
                    isValid = false;
                }
                break;

            case 2: // Manager
                const managerNameFa = document.getElementById('managerNameFa');
                if (!validateField(managerNameFa, [validators.required], 'نام مسئول')) {
                    isValid = false;
                }
                
                const managerTitle = document.getElementById('managerTitle');
                if (!validateField(managerTitle, [validators.required], 'سمت')) {
                    isValid = false;
                }
                
                const managerEmail = document.getElementById('managerEmail');
                if (managerEmail.value && !validateField(managerEmail, [validators.email], 'ایمیل')) {
                    isValid = false;
                }
                
                const managerPhone = document.getElementById('managerPhone');
                if (managerPhone.value && !validateField(managerPhone, [validators.phone], 'شماره تماس')) {
                    isValid = false;
                }
                break;

            case 3: // Financial
                const defaultPercentage = document.getElementById('defaultPercentage');
                if (!validateField(defaultPercentage, [validators.percentage], 'درصد')) {
                    isValid = false;
                }
                
                const defaultHybridPercentage = document.getElementById('defaultHybridPercentage');
                if (!validateField(defaultHybridPercentage, [validators.percentage], 'درصد ترکیبی')) {
                    isValid = false;
                }
                
                const fiscalYearStart = document.getElementById('fiscalYearStart');
                if (!validateField(fiscalYearStart, [validators.jalaliYear], 'سال مالی')) {
                    isValid = false;
                }
                break;

            case 4: // Categories
                if (categories.length === 0) {
                    document.getElementById('categoryError').textContent = 'حداقل یک دسته‌بندی باید وجود داشته باشد';
                    isValid = false;
                } else {
                    document.getElementById('categoryError').textContent = '';
                }
                break;
        }

        return isValid;
    }

    // ============================================
    // DATA COLLECTION
    // ============================================
    function collectStepData(step) {
        switch (step) {
            case 1:
                wizardData.organization = {
                    name_fa: document.getElementById('orgNameFa').value.trim() || DEFAULTS.organization.name_fa,
                    name_en: document.getElementById('orgNameEn').value.trim() || DEFAULTS.organization.name_en,
                    slogan: document.getElementById('orgSlogan').value.trim() || DEFAULTS.organization.slogan,
                    phone: document.getElementById('orgPhone').value.trim(),
                    city: document.getElementById('orgCity').value.trim(),
                    address: document.getElementById('orgAddress').value.trim()
                };
                break;

            case 2:
                wizardData.manager = {
                    name_fa: document.getElementById('managerNameFa').value.trim() || DEFAULTS.manager.name_fa,
                    name_en: document.getElementById('managerNameEn').value.trim() || DEFAULTS.manager.name_en,
                    title: document.getElementById('managerTitle').value.trim() || DEFAULTS.manager.title,
                    phone: document.getElementById('managerPhone').value.trim(),
                    email: document.getElementById('managerEmail').value.trim()
                };
                break;

            case 3:
                wizardData.financial = {
                    currency: document.getElementById('currency').value,
                    currency_label: document.getElementById('currencyLabel').value.trim() || DEFAULTS.financial.currency_label,
                    default_percentage: parseFloat(document.getElementById('defaultPercentage').value) || DEFAULTS.financial.default_percentage,
                    default_hybrid_percentage: parseFloat(document.getElementById('defaultHybridPercentage').value) || DEFAULTS.financial.default_hybrid_percentage,
                    fiscal_year_start: parseInt(document.getElementById('fiscalYearStart').value) || DEFAULTS.financial.fiscal_year_start
                };
                break;

            case 4:
                wizardData.categories = [...categories];
                break;
        }
    }

    // ============================================
    // UI FUNCTIONS
    // ============================================
    function updateProgress() {
        elements.progressSteps.forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.remove('active', 'completed');
            
            if (stepNum < currentStep) {
                step.classList.add('completed');
            } else if (stepNum === currentStep) {
                step.classList.add('active');
            }
        });
    }

    function showStep(step) {
        elements.steps.forEach(s => s.classList.remove('active'));
        document.querySelector(`.wizard-step[data-step="${step}"]`).classList.add('active');
        
        // Update buttons
        elements.prevBtn.disabled = step === 1;
        
        if (step === totalSteps) {
            elements.nextBtn.style.display = 'none';
            elements.finishBtn.style.display = 'inline-flex';
            renderSummary();
        } else {
            elements.nextBtn.style.display = 'inline-flex';
            elements.finishBtn.style.display = 'none';
        }
        
        updateProgress();
    }

    function renderCategories() {
        elements.categoriesList.innerHTML = categories.map((cat, index) => `
            <div class="category-item" data-index="${index}">
                <div class="category-item-info">
                    <span class="category-key">${cat.key}</span>
                    <span class="category-label">${cat.label}</span>
                </div>
                <button type="button" class="category-remove" onclick="removeCategory(${index})" 
                        ${categories.length <= 1 ? 'disabled title="حداقل یک دسته باید وجود داشته باشد"' : ''}>
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `).join('');
    }

    function renderSummary() {
        // Organization summary
        document.getElementById('summaryOrg').innerHTML = `
            <div class="summary-item">
                <span class="summary-label">نام (فارسی)</span>
                <span class="summary-value">${wizardData.organization.name_fa}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">نام (انگلیسی)</span>
                <span class="summary-value ${!wizardData.organization.name_en ? 'empty' : ''}">${wizardData.organization.name_en || 'تعیین نشده'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">شعار</span>
                <span class="summary-value ${!wizardData.organization.slogan ? 'empty' : ''}">${wizardData.organization.slogan || 'تعیین نشده'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">شماره تماس</span>
                <span class="summary-value ${!wizardData.organization.phone ? 'empty' : ''}">${wizardData.organization.phone || 'تعیین نشده'}</span>
            </div>
        `;

        // Manager summary
        document.getElementById('summaryManager').innerHTML = `
            <div class="summary-item">
                <span class="summary-label">نام (فارسی)</span>
                <span class="summary-value">${wizardData.manager.name_fa}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">نام (انگلیسی)</span>
                <span class="summary-value ${!wizardData.manager.name_en ? 'empty' : ''}">${wizardData.manager.name_en || 'تعیین نشده'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">سمت</span>
                <span class="summary-value">${wizardData.manager.title}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">ایمیل</span>
                <span class="summary-value ${!wizardData.manager.email ? 'empty' : ''}">${wizardData.manager.email || 'تعیین نشده'}</span>
            </div>
        `;

        // Financial summary
        document.getElementById('summaryFinancial').innerHTML = `
            <div class="summary-item">
                <span class="summary-label">واحد پول</span>
                <span class="summary-value">${wizardData.financial.currency_label} (${wizardData.financial.currency})</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">درصد پیش‌فرض مربی</span>
                <span class="summary-value">${wizardData.financial.default_percentage}%</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">درصد ترکیبی پیش‌فرض</span>
                <span class="summary-value">${wizardData.financial.default_hybrid_percentage}%</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">سال مالی شروع</span>
                <span class="summary-value">${wizardData.financial.fiscal_year_start}</span>
            </div>
        `;

        // Categories summary
        document.getElementById('summaryCategories').innerHTML = wizardData.categories.map(cat => 
            `<span class="summary-category-tag">${cat.label}</span>`
        ).join('');
    }

    // ============================================
    // CHARACTER COUNTERS
    // ============================================
    function setupCharCounters() {
        const counters = [
            { input: 'orgNameFa', counter: 'orgNameFaCount' },
            { input: 'orgNameEn', counter: 'orgNameEnCount' },
            { input: 'orgSlogan', counter: 'orgSloganCount' },
            { input: 'orgAddress', counter: 'orgAddressCount' }
        ];

        counters.forEach(({ input, counter }) => {
            const inputEl = document.getElementById(input);
            const counterEl = document.getElementById(counter);
            if (inputEl && counterEl) {
                inputEl.addEventListener('input', () => {
                    counterEl.textContent = inputEl.value.length;
                });
            }
        });
    }

    // ============================================
    // REAL-TIME VALIDATION SETUP
    // ============================================
    function setupRealTimeValidation() {
        // Organization fields
        const orgNameFa = document.getElementById('orgNameFa');
        orgNameFa.addEventListener('blur', () => validateField(orgNameFa, [validators.required], 'نام سازمان'));
        
        const orgPhone = document.getElementById('orgPhone');
        orgPhone.addEventListener('blur', () => {
            if (orgPhone.value) validateField(orgPhone, [validators.phone], 'شماره تماس');
        });

        // Manager fields
        const managerNameFa = document.getElementById('managerNameFa');
        managerNameFa.addEventListener('blur', () => validateField(managerNameFa, [validators.required], 'نام مسئول'));
        
        const managerTitle = document.getElementById('managerTitle');
        managerTitle.addEventListener('blur', () => validateField(managerTitle, [validators.required], 'سمت'));
        
        const managerEmail = document.getElementById('managerEmail');
        managerEmail.addEventListener('blur', () => {
            if (managerEmail.value) validateField(managerEmail, [validators.email], 'ایمیل');
        });
        
        const managerPhone = document.getElementById('managerPhone');
        managerPhone.addEventListener('blur', () => {
            if (managerPhone.value) validateField(managerPhone, [validators.phone], 'شماره تماس');
        });

        // Financial fields
        const defaultPercentage = document.getElementById('defaultPercentage');
        defaultPercentage.addEventListener('input', () => validateField(defaultPercentage, [validators.percentage], 'درصد'));
        
        const defaultHybridPercentage = document.getElementById('defaultHybridPercentage');
        defaultHybridPercentage.addEventListener('input', () => validateField(defaultHybridPercentage, [validators.percentage], 'درصد ترکیبی'));
        
        const fiscalYearStart = document.getElementById('fiscalYearStart');
        fiscalYearStart.addEventListener('input', () => validateField(fiscalYearStart, [validators.jalaliYear], 'سال مالی'));

        // Currency change updates label
        const currency = document.getElementById('currency');
        const currencyLabel = document.getElementById('currencyLabel');
        currency.addEventListener('change', () => {
            const labels = { AFN: 'افغانی', IRR: 'ریال', USD: 'دلار', EUR: 'یورو' };
            currencyLabel.value = labels[currency.value] || '';
        });
    }

    // ============================================
    // CATEGORY MANAGEMENT
    // ============================================
    window.removeCategory = function(index) {
        if (categories.length <= 1) {
            document.getElementById('categoryError').textContent = 'حداقل یک دسته‌بندی باید وجود داشته باشد';
            return;
        }
        categories.splice(index, 1);
        renderCategories();
        document.getElementById('categoryError').textContent = '';
    };

    function addCategory() {
        const keyInput = document.getElementById('newCategoryKey');
        const labelInput = document.getElementById('newCategoryLabel');
        const errorEl = document.getElementById('categoryError');
        
        const key = keyInput.value.trim();
        const label = labelInput.value.trim();
        
        // Validate key
        const keyError = validators.categoryKey(key);
        if (keyError) {
            errorEl.textContent = keyError;
            keyInput.classList.add('error');
            return;
        }
        
        // Validate label
        if (!label) {
            errorEl.textContent = 'نام دسته الزامی است';
            labelInput.classList.add('error');
            return;
        }
        
        // Add category
        categories.push({ key, label });
        renderCategories();
        
        // Clear inputs
        keyInput.value = '';
        labelInput.value = '';
        keyInput.classList.remove('error');
        labelInput.classList.remove('error');
        errorEl.textContent = '';
    }

    // ============================================
    // SAVE DATA
    // ============================================
    async function saveSettings() {
        elements.finishBtn.classList.add('loading');
        elements.finishBtn.disabled = true;

        try {
            const response = await fetch(API_BASE + 'settings.php?action=setup', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(wizardData)
            });

            const result = await response.json();

            if (result.success) {
                // Mark setup as complete
                localStorage.setItem('setup_complete', 'true');
                localStorage.setItem('app_settings', JSON.stringify(wizardData));
                
                // Show success modal
                elements.successModal.classList.add('show');
            } else {
                throw new Error(result.error || 'خطا در ذخیره تنظیمات');
            }
        } catch (error) {
            console.error('Save error:', error);
            alert('خطا در ذخیره تنظیمات: ' + error.message);
        } finally {
            elements.finishBtn.classList.remove('loading');
            elements.finishBtn.disabled = false;
        }
    }

    async function skipSetup() {
        // Use defaults
        wizardData = JSON.parse(JSON.stringify(DEFAULTS));
        
        try {
            const response = await fetch(API_BASE + 'settings.php?action=setup', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(wizardData)
            });

            const result = await response.json();

            if (result.success) {
                localStorage.setItem('setup_complete', 'true');
                localStorage.setItem('app_settings', JSON.stringify(wizardData));
                window.location.href = 'index.html';
            } else {
                throw new Error(result.error || 'خطا در ذخیره تنظیمات');
            }
        } catch (error) {
            console.error('Skip error:', error);
            // Even if API fails, allow proceeding with local storage
            localStorage.setItem('setup_complete', 'true');
            localStorage.setItem('app_settings', JSON.stringify(wizardData));
            window.location.href = 'index.html';
        }
    }

    // ============================================
    // EVENT LISTENERS
    // ============================================
    function setupEventListeners() {
        // Next button
        elements.nextBtn.addEventListener('click', () => {
            if (validateStep(currentStep)) {
                collectStepData(currentStep);
                currentStep++;
                showStep(currentStep);
            }
        });

        // Previous button
        elements.prevBtn.addEventListener('click', () => {
            collectStepData(currentStep);
            currentStep--;
            showStep(currentStep);
        });

        // Finish button
        elements.finishBtn.addEventListener('click', () => {
            collectStepData(currentStep);
            saveSettings();
        });

        // Skip button
        elements.skipBtn.addEventListener('click', () => {
            elements.skipModal.classList.add('show');
        });

        // Cancel skip
        elements.cancelSkipBtn.addEventListener('click', () => {
            elements.skipModal.classList.remove('show');
        });

        // Confirm skip
        elements.confirmSkipBtn.addEventListener('click', () => {
            elements.skipModal.classList.remove('show');
            skipSetup();
        });

        // Go to dashboard
        elements.goToDashboardBtn.addEventListener('click', () => {
            window.location.href = 'index.html';
        });

        // Add category
        elements.addCategoryBtn.addEventListener('click', addCategory);
        
        // Enter key for category inputs
        document.getElementById('newCategoryLabel').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addCategory();
            }
        });

        // Close modals on overlay click
        elements.skipModal.addEventListener('click', (e) => {
            if (e.target === elements.skipModal) {
                elements.skipModal.classList.remove('show');
            }
        });
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        // Check if user is logged in
        const user = JSON.parse(localStorage.getItem('user') || 'null');
        if (!user) {
            window.location.href = 'login.html';
            return;
        }

        // Check if setup already complete
        const setupComplete = localStorage.getItem('setup_complete');
        if (setupComplete === 'true') {
            // Redirect to dashboard if setup already done
            // Comment this out during development to test wizard
            // window.location.href = 'index.html';
            // return;
        }

        // Initialize UI
        renderCategories();
        setupCharCounters();
        setupRealTimeValidation();
        setupEventListeners();
        showStep(1);

        // Pre-fill with defaults for better UX
        document.getElementById('orgNameFa').placeholder = `مثال: ${DEFAULTS.organization.name_fa}`;
        document.getElementById('managerNameFa').placeholder = `مثال: ${DEFAULTS.manager.name_fa}`;
    }

    // Start
    init();
})();
