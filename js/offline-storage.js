// Enhanced Universal Offline Storage for Dental EMR
class UniversalOfflineStorage {
    constructor() {
        this.supported = this.checkSupport();
        this.dbName = 'DentalEMR';
        this.dbVersion = 2;
        this.init();
    }

    checkSupport() {
        if (!('indexedDB' in window)) {
            console.warn('IndexedDB not supported, falling back to localStorage');
            return 'localStorage';
        }
        return 'indexedDB';
    }

    async init() {
        if (this.supported === 'indexedDB') {
            await this.initIndexedDB();
        }
        // localStorage doesn't need initialization
    }

    async initIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => {
                console.warn('IndexedDB failed, falling back to localStorage');
                this.supported = 'localStorage';
                reject(request.error);
            };

            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Patients store
                if (!db.objectStoreNames.contains('offlinePatients')) {
                    const patientStore = db.createObjectStore('offlinePatients', {
                        keyPath: 'localId',
                        autoIncrement: true
                    });
                    patientStore.createIndex('timestamp', 'timestamp', { unique: false });
                    patientStore.createIndex('synced', 'synced', { unique: false });
                    patientStore.createIndex('patientId', 'patientId', { unique: false });
                }

                // Treatments store
                if (!db.objectStoreNames.contains('offlineTreatments')) {
                    const treatmentStore = db.createObjectStore('offlineTreatments', {
                        keyPath: 'localId',
                        autoIncrement: true
                    });
                    treatmentStore.createIndex('timestamp', 'timestamp', { unique: false });
                    treatmentStore.createIndex('synced', 'synced', { unique: false });
                    treatmentStore.createIndex('patientId', 'patientId', { unique: false });
                }

                // Sync queue store
                if (!db.objectStoreNames.contains('syncQueue')) {
                    const syncStore = db.createObjectStore('syncQueue', {
                        keyPath: 'id',
                        autoIncrement: true
                    });
                    syncStore.createIndex('type', 'type', { unique: false });
                    syncStore.createIndex('timestamp', 'timestamp', { unique: false });
                }
            };
        });
    }

    // Patient methods
    async savePatient(patientData) {
        const patientRecord = {
            ...patientData,
            timestamp: new Date().toISOString(),
            synced: false,
            localId: Date.now() + Math.random()
        };

        if (this.supported === 'indexedDB' && this.db) {
            return await this.saveToIndexedDB('offlinePatients', patientRecord);
        } else {
            return this.saveToLocalStorage('offlinePatients', patientRecord);
        }
    }

    async getPatients() {
        if (this.supported === 'indexedDB' && this.db) {
            return await this.getAllFromIndexedDB('offlinePatients');
        } else {
            return this.getAllFromLocalStorage('offlinePatients');
        }
    }

    async getUnsyncedPatients() {
        const patients = await this.getPatients();
        return patients.filter(patient => !patient.synced);
    }

    // Treatment methods
    async saveTreatment(treatmentData) {
        const treatmentRecord = {
            ...treatmentData,
            timestamp: new Date().toISOString(),
            synced: false,
            localId: Date.now() + Math.random()
        };

        if (this.supported === 'indexedDB' && this.db) {
            return await this.saveToIndexedDB('offlineTreatments', treatmentRecord);
        } else {
            return this.saveToLocalStorage('offlineTreatments', treatmentRecord);
        }
    }

    async getTreatments() {
        if (this.supported === 'indexedDB' && this.db) {
            return await this.getAllFromIndexedDB('offlineTreatments');
        } else {
            return this.getAllFromLocalStorage('offlineTreatments');
        }
    }

    async getUnsyncedTreatments() {
        const treatments = await this.getTreatments();
        return treatments.filter(treatment => !treatment.synced);
    }

    // Core storage methods
    async saveToIndexedDB(storeName, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.add(data);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    saveToLocalStorage(key, data) {
        const items = JSON.parse(localStorage.getItem(key) || '[]');
        items.push(data);
        localStorage.setItem(key, JSON.stringify(items));
        return data.localId;
    }

    async getAllFromIndexedDB(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    getAllFromLocalStorage(key) {
        return JSON.parse(localStorage.getItem(key) || '[]');
    }

    // Sync methods
    async syncOfflineData() {
        if (!navigator.onLine) {
            console.log('Cannot sync - offline');
            return false;
        }

        let success = true;

        try {
            // Sync patients
            const unsyncedPatients = await this.getUnsyncedPatients();
            for (const patient of unsyncedPatients) {
                const syncSuccess = await this.syncPatient(patient);
                if (!syncSuccess) success = false;
            }

            // Sync treatments
            const unsyncedTreatments = await this.getUnsyncedTreatments();
            for (const treatment of unsyncedTreatments) {
                const syncSuccess = await this.syncTreatment(treatment);
                if (!syncSuccess) success = false;
            }

            return success;
        } catch (error) {
            console.error('Sync error:', error);
            return false;
        }
    }

    async syncPatient(patient) {
        try {
            const formData = new FormData();
            Object.keys(patient).forEach(key => {
                if (!['localId', 'timestamp', 'synced'].includes(key)) {
                    formData.append(key, patient[key]);
                }
            });

            const response = await fetch('/DentalEMR_System/php/register_patients/addpatient.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                await this.markAsSynced('offlinePatients', patient.localId);
                return true;
            }
            return false;
        } catch (error) {
            console.error('Patient sync failed:', error);
            return false;
        }
    }

    async syncTreatment(treatment) {
        try {
            const formData = new FormData();
            Object.keys(treatment).forEach(key => {
                if (!['localId', 'timestamp', 'synced'].includes(key)) {
                    formData.append(key, treatment[key]);
                }
            });

            const response = await fetch('/DentalEMR_System/php/treatment/add_treatment.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                await this.markAsSynced('offlineTreatments', treatment.localId);
                return true;
            }
            return false;
        } catch (error) {
            console.error('Treatment sync failed:', error);
            return false;
        }
    }

    async markAsSynced(storeName, localId) {
        if (this.supported === 'indexedDB' && this.db) {
            return await this.markAsSyncedIndexedDB(storeName, localId);
        } else {
            return this.markAsSyncedLocalStorage(storeName, localId);
        }
    }

    async markAsSyncedIndexedDB(storeName, localId) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);

            const getRequest = store.get(localId);
            getRequest.onsuccess = () => {
                const record = getRequest.result;
                record.synced = true;
                const updateRequest = store.put(record);
                updateRequest.onsuccess = () => resolve();
                updateRequest.onerror = () => reject(updateRequest.error);
            };
            getRequest.onerror = () => reject(getRequest.error);
        });
    }

    markAsSyncedLocalStorage(key, localId) {
        const items = JSON.parse(localStorage.getItem(key) || '[]');
        const itemIndex = items.findIndex(item => item.localId === localId);
        if (itemIndex !== -1) {
            items[itemIndex].synced = true;
            localStorage.setItem(key, JSON.stringify(items));
        }
    }

    // Statistics
    async getStorageStats() {
        const patients = await this.getPatients();
        const treatments = await this.getTreatments();

        return {
            totalPatients: patients.length,
            unsyncedPatients: patients.filter(p => !p.synced).length,
            totalTreatments: treatments.length,
            unsyncedTreatments: treatments.filter(t => !t.synced).length,
            storageType: this.supported,
            lastUpdate: new Date().toISOString()
        };
    }

    // Clear all data
    async clearAllData() {
        if (this.supported === 'indexedDB' && this.db) {
            await this.clearIndexedDBStore('offlinePatients');
            await this.clearIndexedDBStore('offlineTreatments');
            await this.clearIndexedDBStore('syncQueue');
        } else {
            localStorage.removeItem('offlinePatients');
            localStorage.removeItem('offlineTreatments');
        }
    }

    async clearIndexedDBStore(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.clear();

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }
}

// Global instance with error handling
let offlineStorage;
try {
    offlineStorage = new UniversalOfflineStorage();
} catch (error) {
    console.error('Offline storage initialization failed:', error);
    // Fallback object
    offlineStorage = {
        savePatient: () => Promise.resolve(null),
        getPatients: () => Promise.resolve([]),
        saveTreatment: () => Promise.resolve(null),
        getTreatments: () => Promise.resolve([]),
        syncOfflineData: () => Promise.resolve(false),
        getStorageStats: () => Promise.resolve({
            totalPatients: 0,
            unsyncedPatients: 0,
            totalTreatments: 0,
            unsyncedTreatments: 0,
            storageType: 'none',
            lastUpdate: new Date().toISOString()
        })
    };
}