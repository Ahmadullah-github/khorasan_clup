/**
 * PWA Installation and Management for کمپ خراسان
 * Handles install prompts, service worker registration, and PWA features
 */

class PWAManager {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.swRegistration = null;
        
        // Check if running in secure context
        if (!window.isSecureContext) {
            console.warn('PWAManager: Not running in secure context. PWA features may not work.');
            console.warn('PWAManager: Use HTTPS or localhost for full PWA functionality.');
        }
        
        this.init();
    }
    
    async init() {
        console.log('PWAManager: Starting initialization...');
        console.log('PWAManager: Current URL:', window.location.href);
        console.log('PWAManager: Current pathname:', window.location.pathname);
        
        // Check if manifest is accessible
        try {
            const manifestResponse = await fetch('./manifest.json');
            if (manifestResponse.ok) {
                console.log('PWAManager: Manifest accessible');
            } else {
                console.error('PWAManager: Manifest not accessible:', manifestResponse.status);
            }
        } catch (error) {
            console.error('PWAManager: Manifest fetch failed:', error);
        }
        
        // Check if already installed
        this.checkInstallStatus();
        
        // Register service worker
        await this.registerServiceWorker();
        
        // Setup install prompt
        this.setupInstallPrompt();
        
        // Setup update notifications
        this.setupUpdateNotifications();
        
        console.log('PWAManager: Initialization complete');
    }
    
    checkInstallStatus() {
        // Check if running in standalone mode (installed)
        this.isInstalled = window.matchMedia('(display-mode: standalone)').matches ||
                          window.navigator.standalone === true;
        
        if (this.isInstalled) {
            console.log('PWAManager: App is installed');
            document.body.classList.add('pwa-installed');
        }
    }
    
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.log('PWAManager: Service Worker not supported');
            return;
        }
        
        try {
            // Try different paths for service worker
            let swPath = './sw.js';
            
            // Check if we're in a subdirectory
            const currentPath = window.location.pathname;
            if (currentPath.includes('/public/')) {
                swPath = 'sw.js';
            }
            
            console.log('PWAManager: Attempting to register SW at:', swPath);
            
            this.swRegistration = await navigator.serviceWorker.register(swPath, {
                scope: './'
            });
            
            console.log('PWAManager: Service Worker registered successfully', this.swRegistration.scope);
            
            // Listen for updates
            this.swRegistration.addEventListener('updatefound', () => {
                const newWorker = this.swRegistration.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.showUpdateAvailable();
                    }
                });
            });
            
        } catch (error) {
            console.error('PWAManager: Service Worker registration failed', error);
            console.log('PWAManager: Trying alternative registration...');
            
            // Try alternative path
            try {
                this.swRegistration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/'
                });
                console.log('PWAManager: Service Worker registered with alternative path');
            } catch (altError) {
                console.error('PWAManager: Alternative SW registration also failed', altError);
            }
        }
    }
    
    setupInstallPrompt() {
        // Listen for beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('PWAManager: Install prompt available');
            
            // Prevent default mini-infobar
            e.preventDefault();
            
            // Store the event for later use
            this.deferredPrompt = e;
            
            // Show custom install prompt after a delay
            setTimeout(() => {
                this.showInstallPrompt();
            }, 5000); // Show after 5 seconds for testing
        });
        
        // Listen for app installed event
        window.addEventListener('appinstalled', () => {
            console.log('PWAManager: App installed');
            this.isInstalled = true;
            this.hideInstallPrompt();
            notify.success('اپلیکیشن با موفقیت نصب شد!');
        });
    }
    
    showInstallPrompt() {
        if (!this.deferredPrompt || this.isInstalled) return;
        
        // Check if user has dismissed the prompt recently
        const dismissed = localStorage.getItem('pwa-install-dismissed');
        if (dismissed) {
            const dismissedTime = new Date(dismissed);
            const now = new Date();
            const daysSinceDismissed = (now - dismissedTime) / (1000 * 60 * 60 * 24);
            
            if (daysSinceDismissed < 7) {
                console.log('PWAManager: Install prompt recently dismissed');
                return;
            }
        }
        
        const prompt = document.createElement('div');
        prompt.id = 'pwaInstallPrompt';
        prompt.className = 'pwa-install-prompt';
        prompt.innerHTML = `
            <div class="pwa-install-content">
                <div class="pwa-install-text">
                    <div class="pwa-install-title">
                        <i class="bi bi-download"></i>
                        نصب کمپ خراسان
                    </div>
                    <div class="pwa-install-description">
                        برای دسترسی سریع‌تر و استفاده آفلاین، اپلیکیشن را نصب کنید
                    </div>
                </div>
                <div class="pwa-install-actions">
                    <button class="pwa-install-btn pwa-install-btn--primary" id="pwaInstallBtn">
                        <i class="bi bi-plus-circle"></i>
                        نصب
                    </button>
                    <button class="pwa-install-btn" id="pwaInstallLater">
                        بعداً
                    </button>
                    <button class="pwa-install-close" id="pwaInstallClose">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(prompt);
        
        // Animate in
        setTimeout(() => prompt.classList.add('show'), 100);
        
        // Setup event listeners
        document.getElementById('pwaInstallBtn').addEventListener('click', () => {
            this.installApp();
        });
        
        document.getElementById('pwaInstallLater').addEventListener('click', () => {
            this.hideInstallPrompt();
            localStorage.setItem('pwa-install-dismissed', new Date().toISOString());
        });
        
        document.getElementById('pwaInstallClose').addEventListener('click', () => {
            this.hideInstallPrompt();
            localStorage.setItem('pwa-install-dismissed', new Date().toISOString());
        });
    }
    
    hideInstallPrompt() {
        const prompt = document.getElementById('pwaInstallPrompt');
        if (prompt) {
            prompt.classList.remove('show');
            setTimeout(() => prompt.remove(), 300);
        }
    }
    
    async installApp() {
        if (!this.deferredPrompt) {
            notify.error('امکان نصب در حال حاضر وجود ندارد');
            return;
        }
        
        try {
            // Show the install prompt
            this.deferredPrompt.prompt();
            
            // Wait for user choice
            const { outcome } = await this.deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('PWAManager: User accepted install');
            } else {
                console.log('PWAManager: User dismissed install');
                localStorage.setItem('pwa-install-dismissed', new Date().toISOString());
            }
            
            // Clear the prompt
            this.deferredPrompt = null;
            this.hideInstallPrompt();
            
        } catch (error) {
            console.error('PWAManager: Install failed', error);
            notify.error('خطا در نصب اپلیکیشن');
        }
    }
    
    setupUpdateNotifications() {
        if (!this.swRegistration) return;
        
        // Check for updates periodically
        setInterval(() => {
            this.swRegistration.update();
        }, 60000); // Check every minute
    }
    
    showUpdateAvailable() {
        const updateBanner = document.createElement('div');
        updateBanner.id = 'pwaUpdateBanner';
        updateBanner.className = 'pwa-install-prompt';
        updateBanner.innerHTML = `
            <div class="pwa-install-content">
                <div class="pwa-install-text">
                    <div class="pwa-install-title">
                        <i class="bi bi-arrow-clockwise"></i>
                        بروزرسانی جدید
                    </div>
                    <div class="pwa-install-description">
                        نسخه جدید اپلیکیشن آماده است
                    </div>
                </div>
                <div class="pwa-install-actions">
                    <button class="pwa-install-btn pwa-install-btn--primary" id="pwaUpdateBtn">
                        <i class="bi bi-download"></i>
                        بروزرسانی
                    </button>
                    <button class="pwa-install-close" id="pwaUpdateClose">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(updateBanner);
        setTimeout(() => updateBanner.classList.add('show'), 100);
        
        document.getElementById('pwaUpdateBtn').addEventListener('click', () => {
            this.updateApp();
        });
        
        document.getElementById('pwaUpdateClose').addEventListener('click', () => {
            updateBanner.classList.remove('show');
            setTimeout(() => updateBanner.remove(), 300);
        });
    }
    
    async updateApp() {
        if (!this.swRegistration || !this.swRegistration.waiting) return;
        
        try {
            // Tell the waiting service worker to skip waiting
            this.swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
            
            // Reload the page to activate the new service worker
            window.location.reload();
            
        } catch (error) {
            console.error('PWAManager: Update failed', error);
            notify.error('خطا در بروزرسانی اپلیکیشن');
        }
    }
    
    // ============================================
    // PWA Features
    // ============================================
    
    async requestNotificationPermission() {
        if (!('Notification' in window)) {
            console.log('PWAManager: Notifications not supported');
            return false;
        }
        
        if (Notification.permission === 'granted') {
            return true;
        }
        
        if (Notification.permission === 'denied') {
            return false;
        }
        
        const permission = await Notification.requestPermission();
        return permission === 'granted';
    }
    
    async showNotification(title, options = {}) {
        if (!await this.requestNotificationPermission()) {
            console.log('PWAManager: Notification permission denied');
            return;
        }
        
        const defaultOptions = {
            icon: './assets/icons/android-chrome-192x192.png',
            badge: './assets/icons/apple-touch-icon-72x72.png',
            vibrate: [200, 100, 200],
            tag: 'khorasan-camp'
        };
        
        const notificationOptions = { ...defaultOptions, ...options };
        
        if (this.swRegistration) {
            // Use service worker to show notification
            this.swRegistration.showNotification(title, notificationOptions);
        } else {
            // Fallback to regular notification
            new Notification(title, notificationOptions);
        }
    }
    
    // ============================================
    // Utility Methods
    // ============================================
    
    isOnline() {
        return navigator.onLine;
    }
    
    getInstallStatus() {
        return {
            isInstalled: this.isInstalled,
            canInstall: !!this.deferredPrompt,
            hasServiceWorker: !!this.swRegistration
        };
    }
    
    async getStorageUsage() {
        if ('storage' in navigator && 'estimate' in navigator.storage) {
            const estimate = await navigator.storage.estimate();
            return {
                used: estimate.usage,
                available: estimate.quota,
                usedMB: Math.round(estimate.usage / 1024 / 1024),
                availableMB: Math.round(estimate.quota / 1024 / 1024)
            };
        }
        return null;
    }
    
    async clearCache() {
        if ('caches' in window) {
            const cacheNames = await caches.keys();
            await Promise.all(
                cacheNames.map(cacheName => caches.delete(cacheName))
            );
            console.log('PWAManager: Cache cleared');
            notify.success('حافظه پنهان پاک شد');
        }
    }
}

// Initialize PWA Manager
const pwaManager = new PWAManager();

// Export for global use
window.pwaManager = pwaManager;