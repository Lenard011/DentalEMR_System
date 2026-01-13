// Enhanced Service Worker for Dental EMR System
const CACHE_NAME = 'dentalemr-v3';
const OFFLINE_URL = '/DentalEMR_System/html/offline.html';
const OFFLINE_DASHBOARD = '/DentalEMR_System/html/offline-dashboard.html';

// Essential assets to cache
const ESSENTIAL_ASSETS = [
    '/DentalEMR_System/',
    '/DentalEMR_System/html/login/login.html',
    '/DentalEMR_System/html/offline.html',
    '/DentalEMR_System/html/offline-dashboard.html',
    '/DentalEMR_System/css/style.css',
    '/DentalEMR_System/js/offline-storage.js',
    '/DentalEMR_System/js/local-auth.js',

    // Main application pages
    '/DentalEMR_System/html/index.php',
    '/DentalEMR_System/html/addpatient.php',
    '/DentalEMR_System/html/archived.php',
    '/DentalEMR_System/html/viewrecord.php',

    // Staff pages
    '/DentalEMR_System/html/a_staff/addpatient.php',

    // Treatment pages
    '/DentalEMR_System/html/treatmentrecords/treatmentrecords.php',
    '/DentalEMR_System/html/treatmentrecords/view_info.php',
    '/DentalEMR_System/html/treatmentrecords/view_oral.php',
    '/DentalEMR_System/html/treatmentrecords/view_oralA.php',
    '/DentalEMR_System/html/treatmentrecords/view_oralB.php',
    '/DentalEMR_System/html/treatmentrecords/view_record.php',
    '/DentalEMR_System/html/addpatienttreatment/patienttreatment.php',

    // Report pages
    '/DentalEMR_System/html/reports/targetclientlist.php',
    '/DentalEMR_System/html/reports/mho_ohp.php',
    '/DentalEMR_System/html/reports/oralhygienefindings.php',

    // Manage users pages
    '/DentalEMR_System/html/manageusers/manageuser.php',
    '/DentalEMR_System/html/manageusers/activitylogs.php',
    '/DentalEMR_System/html/manageusers/historylogs.php'
];

// Install event
self.addEventListener('install', (event) => {
    console.log('Service Worker installing for Dental EMR');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Caching essential assets');
                return cache.addAll(ESSENTIAL_ASSETS).catch(error => {
                    console.log('Some assets failed to cache:', error);
                });
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event
self.addEventListener('activate', (event) => {
    console.log('Service Worker activating');

    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event with enhanced offline handling
// In your service worker (sw.js), find the fetch event handler
self.addEventListener('fetch', (event) => {
    // Skip POST requests and other non-GET requests from caching
    if (event.request.method !== 'GET') {
        return;
    }

    // Only cache GET requests
    event.respondWith(
        caches.match(event.request)
            .then(cachedResponse => {
                // If we have a cached response, return it
                if (cachedResponse) {
                    return cachedResponse;
                }

                // Otherwise, fetch from network
                return fetch(event.request)
                    .then(response => {
                        // Check if we received a valid response
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Clone the response
                        const responseToCache = response.clone();

                        // Open cache and store the response
                        caches.open(CACHE_NAME)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    });
            })
    );
});

// Helper functions
function isAppPage(pathname) {
    const appPages = [
        '/DentalEMR_System/html/index.php',
        '/DentalEMR_System/html/addpatient.php',
        '/DentalEMR_System/html/archived.php',
        '/DentalEMR_System/html/viewrecord.php',
        '/DentalEMR_System/html/a_staff/',
        '/DentalEMR_System/html/treatmentrecords/',
        '/DentalEMR_System/html/reports/',
        '/DentalEMR_System/html/manageusers/'
    ];
    return appPages.some(page => pathname.includes(page));
}

function isLoginRequest(url) {
    const loginPaths = [
        '/php/login/',
        'login.php',
        'staff_login.php',
        'verify_mfa.php'
    ];
    return loginPaths.some(path => url.pathname.includes(path));
}