/**
 * App Settings Manager
 * Loads and provides access to dynamic app settings
 * Replaces hardcoded values throughout the application
 */

(function() {
    'use strict';

    // Default settings (fallback if API fails)
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

    // Settings cache
    let settingsCache = null;
    let settingsLoaded = false;
    let loadPromise = null;

    /**
     * AppSettings - Global settings manager
     */
    window.AppSettings = {
        /**
         * Load settings from API or cache
         * @returns {Promise<Object>} Settings object
         */
        async load() {
            // Return cached settings if available
            if (settingsLoaded && settingsCache) {
                return settingsCache;
            }

            // Return existing promise if loading
            if (loadPromise) {
                return loadPromise;
            }

            // Try to load from localStorage first (for offline support)
            const cached = localStorage.getItem('app_settings');
            if (cached) {
                try {
                    settingsCache = JSON.parse(cached);
                    settingsLoaded = true;
                } catch (e) {
                    console.warn('Failed to parse cached settings');
                }
            }

            // Load from API
            loadPromise = this._fetchSettings();
            return loadPromise;
        },

        /**
         * Fetch settings from API
         * @private
         */
        async _fetchSettings() {
            try {
                const response = await fetch(API_BASE + 'settings.php?action=all');
                const result = await response.json();

                if (result.success && result.data) {
                    settingsCache = this._transformSettings(result.data);
                    settingsLoaded = true;
                    
                    // Cache in localStorage
                    localStorage.setItem('app_settings', JSON.stringify(settingsCache));
                    
                    return settingsCache;
                }
            } catch (error) {
                console.warn('Failed to load settings from API:', error);
            }

            // Fall back to defaults
            settingsCache = DEFAULTS;
            settingsLoaded = true;
            return settingsCache;
        },

        /**
         * Transform API response to settings object
         * @private
         */
        _transformSettings(data) {
            const settings = JSON.parse(JSON.stringify(DEFAULTS));

            // Organization
            if (data.organization) {
                settings.organization = {
                    name_fa: data.organization.org_name_fa || DEFAULTS.organization.name_fa,
                    name_en: data.organization.org_name_en || DEFAULTS.organization.name_en,
                    slogan: data.organization.org_slogan || DEFAULTS.organization.slogan,
                    phone: data.organization.org_phone || '',
                    city: data.organization.org_city || '',
                    address: data.organization.org_address || ''
                };
            }

            // Manager
            if (data.manager) {
                settings.manager = {
                    name_fa: data.manager.manager_name_fa || DEFAULTS.manager.name_fa,
                    name_en: data.manager.manager_name_en || DEFAULTS.manager.name_en,
                    title: data.manager.manager_title || DEFAULTS.manager.title,
                    phone: data.manager.manager_phone || '',
                    email: data.manager.manager_email || ''
                };
            }

            // Financial
            if (data.financial) {
                settings.financial = {
                    currency: data.financial.currency || DEFAULTS.financial.currency,
                    currency_label: data.financial.currency_label || DEFAULTS.financial.currency_label,
                    default_percentage: parseFloat(data.financial.default_percentage) || DEFAULTS.financial.default_percentage,
                    default_hybrid_percentage: parseFloat(data.financial.default_hybrid_percentage) || DEFAULTS.financial.default_hybrid_percentage,
                    fiscal_year_start: parseInt(data.financial.fiscal_year_start) || DEFAULTS.financial.fiscal_year_start
                };
            }

            // Categories
            if (data.categories && data.categories.expense_categories) {
                const cats = data.categories.expense_categories;
                settings.categories = Array.isArray(cats) ? cats : DEFAULTS.categories;
            }

            return settings;
        },

        /**
         * Get organization settings
         */
        getOrganization() {
            return settingsCache?.organization || DEFAULTS.organization;
        },

        /**
         * Get manager settings
         */
        getManager() {
            return settingsCache?.manager || DEFAULTS.manager;
        },

        /**
         * Get financial settings
         */
        getFinancial() {
            return settingsCache?.financial || DEFAULTS.financial;
        },

        /**
         * Get expense categories
         */
        getCategories() {
            return settingsCache?.categories || DEFAULTS.categories;
        },

        /**
         * Get organization name (primary language)
         */
        getOrgName() {
            return this.getOrganization().name_fa;
        },

        /**
         * Get organization name (English)
         */
        getOrgNameEn() {
            return this.getOrganization().name_en;
        },

        /**
         * Get currency label
         */
        getCurrencyLabel() {
            return this.getFinancial().currency_label;
        },

        /**
         * Get manager name (primary language)
         */
        getManagerName() {
            return this.getManager().name_fa;
        },

        /**
         * Get manager name (English)
         */
        getManagerNameEn() {
            return this.getManager().name_en;
        },

        /**
         * Get default coach percentage
         */
        getDefaultPercentage() {
            return this.getFinancial().default_percentage;
        },

        /**
         * Get default hybrid percentage
         */
        getDefaultHybridPercentage() {
            return this.getFinancial().default_hybrid_percentage;
        },

        /**
         * Format currency with label
         * @param {number} amount 
         */
        formatCurrency(amount) {
            return formatNumber(amount) + ' ' + this.getCurrencyLabel();
        },

        /**
         * Check if setup is complete
         */
        async isSetupComplete() {
            try {
                const response = await fetch(API_BASE + 'settings.php?action=check-setup');
                const result = await response.json();
                return result.success && result.data.setup_complete;
            } catch (error) {
                // Check localStorage fallback
                return localStorage.getItem('setup_complete') === 'true';
            }
        },

        /**
         * Clear cached settings (force reload)
         */
        clearCache() {
            settingsCache = null;
            settingsLoaded = false;
            loadPromise = null;
            localStorage.removeItem('app_settings');
        },

        /**
         * Get all settings
         */
        getAll() {
            return settingsCache || DEFAULTS;
        },

        /**
         * Get defaults
         */
        getDefaults() {
            return DEFAULTS;
        }
    };

    /**
     * Update page title with organization name
     */
    function updatePageTitle() {
        const orgName = AppSettings.getOrgName();
        const currentTitle = document.title;
        
        // Replace default name in title
        if (currentTitle.includes('کمپ خراسان')) {
            document.title = currentTitle.replace('کمپ خراسان', orgName);
        }
    }

    /**
     * Update navbar brand with organization name
     */
    function updateNavbarBrand() {
        const orgName = AppSettings.getOrgName();
        const brandElements = document.querySelectorAll('.navbar-brand');
        
        brandElements.forEach(el => {
            // Find text node and update
            const textNodes = Array.from(el.childNodes).filter(n => n.nodeType === Node.TEXT_NODE);
            if (textNodes.length > 0) {
                textNodes.forEach(node => {
                    if (node.textContent.includes('کمپ خراسان')) {
                        node.textContent = node.textContent.replace('کمپ خراسان', orgName);
                    }
                });
            }
        });
        
        // Also update any span inside navbar-brand
        document.querySelectorAll('.navbar-brand span:not(.navbar-brand-icon)').forEach(span => {
            if (span.textContent.includes('کمپ خراسان')) {
                span.textContent = span.textContent.replace('کمپ خراسان', orgName);
            }
        });
    }

    /**
     * Update PWA meta tags
     */
    function updatePWAMeta() {
        const orgName = AppSettings.getOrgName();
        
        // Update apple-mobile-web-app-title
        const appleTitleMeta = document.querySelector('meta[name="apple-mobile-web-app-title"]');
        if (appleTitleMeta) {
            appleTitleMeta.setAttribute('content', orgName);
        }
    }

    /**
     * Initialize settings on page load
     */
    async function initSettings() {
        await AppSettings.load();
        
        // Update UI elements
        updatePageTitle();
        updateNavbarBrand();
        updatePWAMeta();
        
        // Dispatch event for other scripts
        window.dispatchEvent(new CustomEvent('settingsLoaded', { 
            detail: AppSettings.getAll() 
        }));
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSettings);
    } else {
        initSettings();
    }

})();
