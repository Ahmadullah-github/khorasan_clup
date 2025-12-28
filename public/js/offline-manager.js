/**
 * Offline Data Manager for کمپ خراسان PWA
 * Handles IndexedDB storage, sync queue, and offline-first data operations
 */

class OfflineManager {
    constructor() {
        this.dbName = 'KhorasanCampDB';
        this.dbVersion = 1;
        this.db = null;
        this.syncQueue = [];
        this.isOnline = navigator.onLine;
        
        this.init();
        this.setupEventListeners();
    }
    
    async init() {
        try {
            this.db = await this.openDB();
            console.log('OfflineManager: Database initialized');
            
            // Process any pending sync items
            if (this.isOnline) {
                await this.processSyncQueue();
            }
        } catch (error) {
            console.error('OfflineManager: Failed to initialize', error);
        }
    }
    
    openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Students table
                if (!db.objectStoreNames.contains('students')) {
                    const studentsStore = db.createObjectStore('students', { keyPath: 'id' });
                    studentsStore.createIndex('status', 'status', { unique: false });
                    studentsStore.createIndex('updated_at', 'updated_at', { unique: false });
                }
                
                // Coaches table
                if (!db.objectStoreNames.contains('coaches')) {
                    const coachesStore = db.createObjectStore('coaches', { keyPath: 'id' });
                    coachesStore.createIndex('updated_at', 'updated_at', { unique: false });
                }
                
                // Expenses table
                if (!db.objectStoreNames.contains('expenses')) {
                    const expensesStore = db.createObjectStore('expenses', { keyPath: 'id' });
                    expensesStore.createIndex('expense_date', 'expense_date', { unique: false });
                }
                
                // Sync Queue - stores offline actions
                if (!db.objectStoreNames.contains('sync_queue')) {
                    const syncStore = db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                    syncStore.createIndex('timestamp', 'timestamp', { unique: false });
                    syncStore.createIndex('type', 'type', { unique: false });
                }
                
                // App Settings
                if (!db.objectStoreNames.contains('settings')) {
                    db.createObjectStore('settings', { keyPath: 'key' });
                }
                
                console.log('OfflineManager: Database schema created');
            };
        });
    }
    
    setupEventListeners() {
        // Online/offline status
        window.addEventListener('online', () => {
            this.isOnline = true;
            console.log('OfflineManager: Back online');
            this.processSyncQueue();
            this.showOnlineStatus();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            console.log('OfflineManager: Gone offline');
            this.showOfflineStatus();
        });
        
        // Service Worker messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data.type === 'SYNC_COMPLETE') {
                    console.log(`OfflineManager: Synced ${event.data.synced} items`);
                    notify.success(`${event.data.synced} مورد همگام‌سازی شد`);
                }
            });
        }
    }
    
    // ============================================
    // Data Operations (Offline-First)
    // ============================================
    
    async getStudents(params = {}) {
        if (this.isOnline) {
            try {
                const data = await APIClient.get('students.php', params);
                if (data.success) {
                    // Cache in IndexedDB
                    await this.cacheStudents(data.data.students);
                    return data;
                }
            } catch (error) {
                console.log('OfflineManager: API failed, falling back to cache');
            }
        }
        
        // Fallback to cached data
        const cachedStudents = await this.getCachedStudents(params);
        return {
            success: true,
            data: {
                students: cachedStudents,
                pagination: { total: cachedStudents.length, page: 1, per_page: cachedStudents.length }
            },
            _offline: true
        };
    }
    
    async saveStudent(studentData, isUpdate = false) {
        const action = {
            id: Date.now(),
            type: isUpdate ? 'update_student' : 'create_student',
            url: '../api/students.php',
            method: isUpdate ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(studentData),
            timestamp: new Date().toISOString(),
            data: studentData
        };
        
        if (this.isOnline) {
            try {
                const response = await fetch(action.url, {
                    method: action.method,
                    headers: action.headers,
                    body: action.body
                });
                
                const result = await response.json();
                if (result.success) {
                    // Update local cache
                    await this.updateStudentCache(result.data);
                    return result;
                }
            } catch (error) {
                console.log('OfflineManager: Save failed, queuing for sync');
            }
        }
        
        // Queue for later sync
        await this.addToSyncQueue(action);
        
        // Update local cache optimistically
        const tempId = isUpdate ? studentData.id : `temp_${Date.now()}`;
        const studentWithId = { ...studentData, id: tempId, _pending: true };
        await this.updateStudentCache(studentWithId);
        
        return {
            success: true,
            data: studentWithId,
            _offline: true,
            _pending: true
        };
    }
    
    // ============================================
    // Cache Management
    // ============================================
    
    async cacheStudents(students) {
        const transaction = this.db.transaction(['students'], 'readwrite');
        const store = transaction.objectStore('students');
        
        for (const student of students) {
            await store.put({ ...student, cached_at: new Date().toISOString() });
        }
        
        return transaction.complete;
    }
    
    async getCachedStudents(params = {}) {
        const transaction = this.db.transaction(['students'], 'readonly');
        const store = transaction.objectStore('students');
        const students = await store.getAll();
        
        // Apply basic filtering
        let filtered = students;
        
        if (params.status) {
            filtered = filtered.filter(s => s.status === params.status);
        }
        
        if (params.search) {
            const search = params.search.toLowerCase();
            filtered = filtered.filter(s => 
                s.first_name?.toLowerCase().includes(search) ||
                s.last_name?.toLowerCase().includes(search) ||
                s.phone?.includes(search)
            );
        }
        
        return filtered;
    }
    
    async updateStudentCache(student) {
        const transaction = this.db.transaction(['students'], 'readwrite');
        const store = transaction.objectStore('students');
        await store.put({ ...student, cached_at: new Date().toISOString() });
    }
    
    // ============================================
    // Sync Queue Management
    // ============================================
    
    async addToSyncQueue(action) {
        const transaction = this.db.transaction(['sync_queue'], 'readwrite');
        const store = transaction.objectStore('sync_queue');
        await store.add(action);
        
        console.log('OfflineManager: Added to sync queue', action.type);
        
        // Register background sync if available
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            const registration = await navigator.serviceWorker.ready;
            await registration.sync.register('sync-offline-data');
        }
    }
    
    async processSyncQueue() {
        if (!this.isOnline) return;
        
        const transaction = this.db.transaction(['sync_queue'], 'readwrite');
        const store = transaction.objectStore('sync_queue');
        const queueItems = await store.getAll();
        
        console.log(`OfflineManager: Processing ${queueItems.length} sync items`);
        
        for (const item of queueItems) {
            try {
                const response = await fetch(item.url, {
                    method: item.method,
                    headers: item.headers,
                    body: item.body
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update cache with server response
                    if (item.type.includes('student')) {
                        await this.updateStudentCache(result.data);
                    }
                    
                    // Remove from sync queue
                    await store.delete(item.id);
                    console.log('OfflineManager: Synced item', item.id);
                } else {
                    console.error('OfflineManager: Sync failed for item', item.id, result.error);
                }
            } catch (error) {
                console.error('OfflineManager: Network error syncing item', item.id, error);
                break; // Stop processing if network fails
            }
        }
        
        // Show sync completion notification
        const remainingItems = await store.getAll();
        const syncedCount = queueItems.length - remainingItems.length;
        
        if (syncedCount > 0) {
            notify.success(`${syncedCount} مورد همگام‌سازی شد`);
        }
    }
    
    async getSyncQueueCount() {
        const transaction = this.db.transaction(['sync_queue'], 'readonly');
        const store = transaction.objectStore('sync_queue');
        const count = await store.count();
        return count;
    }
    
    // ============================================
    // UI Status Indicators
    // ============================================
    
    showOfflineStatus() {
        // Remove existing status
        const existing = document.getElementById('offlineStatus');
        if (existing) existing.remove();
        
        const status = document.createElement('div');
        status.id = 'offlineStatus';
        status.className = 'offline-status';
        status.innerHTML = `
            <i class="bi bi-wifi-off"></i>
            <span>حالت آفلاین</span>
        `;
        
        document.body.appendChild(status);
        
        // Show sync queue count if any
        this.updateSyncQueueIndicator();
    }
    
    showOnlineStatus() {
        const existing = document.getElementById('offlineStatus');
        if (existing) {
            existing.classList.add('online');
            existing.innerHTML = `
                <i class="bi bi-wifi"></i>
                <span>آنلاین</span>
            `;
            
            setTimeout(() => existing.remove(), 2000);
        }
    }
    
    async updateSyncQueueIndicator() {
        const count = await this.getSyncQueueCount();
        
        if (count > 0) {
            let indicator = document.getElementById('syncQueueIndicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'syncQueueIndicator';
                indicator.className = 'sync-queue-indicator';
                document.body.appendChild(indicator);
            }
            
            indicator.innerHTML = `
                <i class="bi bi-cloud-upload"></i>
                <span>${count} مورد در انتظار همگام‌سازی</span>
            `;
            
            indicator.onclick = () => {
                if (this.isOnline) {
                    this.processSyncQueue();
                } else {
                    notify.error('برای همگام‌سازی به اینترنت متصل شوید');
                }
            };
        } else {
            const indicator = document.getElementById('syncQueueIndicator');
            if (indicator) indicator.remove();
        }
    }
    
    // ============================================
    // Settings Management
    // ============================================
    
    async getSetting(key, defaultValue = null) {
        const transaction = this.db.transaction(['settings'], 'readonly');
        const store = transaction.objectStore('settings');
        const result = await store.get(key);
        return result ? result.value : defaultValue;
    }
    
    async setSetting(key, value) {
        const transaction = this.db.transaction(['settings'], 'readwrite');
        const store = transaction.objectStore('settings');
        await store.put({ key, value });
    }
}

// Initialize offline manager
const offlineManager = new OfflineManager();

// Export for global use
window.offlineManager = offlineManager;