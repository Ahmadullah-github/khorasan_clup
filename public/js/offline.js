/**
 * کمپ خراسان Management System - Offline Support
 * IndexedDB storage and sync queue management
 */

const DB_NAME = 'khorasan_club';
const DB_VERSION = 1;
const STORES = ['students', 'coaches', 'expenses', 'registrations', 'sync_queue'];

let db = null;

// Initialize IndexedDB
async function initDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            db = request.result;
            resolve(db);
        };

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            STORES.forEach(storeName => {
                if (!db.objectStoreNames.contains(storeName)) {
                    const store = db.createObjectStore(storeName, { keyPath: 'id', autoIncrement: true });
                    store.createIndex('local_id', 'local_id', { unique: false });
                    store.createIndex('server_id', 'server_id', { unique: false });
                }
            });

            // Sync queue store
            if (!db.objectStoreNames.contains('sync_queue')) {
                const store = db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                store.createIndex('table', 'table', { unique: false });
                store.createIndex('status', 'status', { unique: false });
            }
        };
    });
}

// Check if online
function isOnline() {
    return navigator.onLine;
}

// Store data locally
async function storeLocal(table, data) {
    if (!db) await initDB();
    
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([table], 'readwrite');
        const store = transaction.objectStore(table);
        const request = store.put({ ...data, timestamp: Date.now() });
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Get data from local storage
async function getLocal(table, id = null) {
    if (!db) await initDB();
    
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([table], 'readonly');
        const store = transaction.objectStore(table);
        const request = id ? store.get(id) : store.getAll();
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Add to sync queue
async function addToSyncQueue(table, action, data, localId) {
    if (!db) await initDB();
    
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['sync_queue'], 'readwrite');
        const store = transaction.objectStore('sync_queue');
        const request = store.add({
            table,
            action,
            data,
            local_id: localId,
            status: 'pending',
            timestamp: Date.now()
        });
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Get sync queue
async function getSyncQueue() {
    if (!db) await initDB();
    
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['sync_queue'], 'readonly');
        const store = transaction.objectStore('sync_queue');
        const index = store.index('status');
        const request = index.getAll('pending');
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Mark sync item as synced
async function markSynced(queueId) {
    if (!db) await initDB();
    
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['sync_queue'], 'readwrite');
        const store = transaction.objectStore('sync_queue');
        const getRequest = store.get(queueId);
        
        getRequest.onsuccess = () => {
            const item = getRequest.result;
            if (item) {
                item.status = 'synced';
                const putRequest = store.put(item);
                putRequest.onsuccess = () => resolve();
                putRequest.onerror = () => reject(putRequest.error);
            } else {
                resolve();
            }
        };
        getRequest.onerror = () => reject(getRequest.error);
    });
}

// Sync queue to server
async function syncToServer() {
    if (!isOnline()) {
        console.log('Offline - cannot sync');
        return;
    }

    try {
        const queue = await getSyncQueue();
        if (queue.length === 0) {
            return;
        }

        const changes = queue.map(item => ({
            table: item.table,
            action: item.action,
            data: item.data,
            local_id: item.local_id
        }));

        const response = await fetch('api/sync.php?action=push', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ changes })
        });

        const result = await response.json();

        if (result.success) {
            // Mark items as synced
            for (let i = 0; i < queue.length; i++) {
                if (result.data.results[i] && result.data.results[i].status === 'success') {
                    await markSynced(queue[i].id);
                }
            }
        }
    } catch (error) {
        console.error('Sync error:', error);
    }
}

// Pull changes from server
async function pullFromServer(lastSync = null) {
    if (!isOnline()) {
        return;
    }

    try {
        const params = lastSync ? { last_sync: lastSync } : {};
        const response = await fetch('api/sync.php?action=pull&' + new URLSearchParams(params));
        const result = await response.json();

        if (result.success) {
            // Update local storage
            for (const [table, items] of Object.entries(result.data.changes)) {
                for (const item of items) {
                    await storeLocal(table, item);
                }
            }
        }
    } catch (error) {
        console.error('Pull error:', error);
    }
}

// API wrapper with offline support
async function apiWithOffline(endpoint, options = {}) {
    if (isOnline()) {
        try {
            const response = await fetch(API_BASE + endpoint, options);
            const data = await response.json();
            
            // Store in local DB
            if (data.success && data.data) {
                // Determine table from endpoint
                const table = endpoint.split('/')[0].replace('.php', '');
                if (STORES.includes(table) && Array.isArray(data.data)) {
                    for (const item of data.data) {
                        await storeLocal(table, item);
                    }
                }
            }
            
            return data;
        } catch (error) {
            console.error('API error:', error);
            // Fallback to local storage
            return getFromLocal(endpoint);
        }
    } else {
        // Offline - use local storage
        return getFromLocal(endpoint);
    }
}

// Get from local storage based on endpoint
async function getFromLocal(endpoint) {
    const table = endpoint.split('/')[0].replace('.php', '');
    if (STORES.includes(table)) {
        const data = await getLocal(table);
        return {
            success: true,
            data: Array.isArray(data) ? data : [data]
        };
    }
    return { success: false, error: 'Not available offline' };
}

// Initialize offline support
async function initOfflineSupport() {
    await initDB();
    
    // Listen for online/offline events
    window.addEventListener('online', () => {
        console.log('Online - syncing...');
        syncToServer();
        pullFromServer();
    });

    window.addEventListener('offline', () => {
        console.log('Offline mode');
    });

    // Periodic sync when online
    if (isOnline()) {
        setInterval(syncToServer, 60000); // Every minute
        setInterval(() => pullFromServer(localStorage.getItem('last_sync')), 300000); // Every 5 minutes
    }
}

// Export
window.OfflineSupport = {
    init: initOfflineSupport,
    storeLocal,
    getLocal,
    addToSyncQueue,
    syncToServer,
    pullFromServer,
    isOnline,
    apiWithOffline
};

// Auto-initialize
if (typeof window !== 'undefined') {
    initOfflineSupport();
}

