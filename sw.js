// Enhanced Service Worker for Dental EMR System
const CACHE_NAME = 'dentalemr-v3';
const OFFLINE_URL = '/dentalemr_system/html/offline.html';
const OFFLINE_DASHBOARD = '/dentalemr_system/html/offline-dashboard.html';

// Essential assets to cache
const ESSENTIAL_ASSETS = [
    '/dentalemr_system/',
    '/dentalemr_system/html/login/login.html',
    '/dentalemr_system/html/offline.html',
    '/dentalemr_system/html/offline-dashboard.html',
    '/dentalemr_system/css/style.css',
    '/dentalemr_system/js/offline-storage.js',
    '/dentalemr_system/js/local-auth.js',

    // Main application pages
    '/dentalemr_system/html/index.php',
    '/dentalemr_system/html/addpatient.php',
    '/dentalemr_system/html/archived.php',
    '/dentalemr_system/html/viewrecord.php',

    // Staff pages
    '/dentalemr_system/html/a_staff/addpatient.php',

    // Treatment pages
    '/dentalemr_system/html/treatmentrecords/treatmentrecords.php',
    '/dentalemr_system/html/treatmentrecords/view_info.php',
    '/dentalemr_system/html/treatmentrecords/view_oral.php',
    '/dentalemr_system/html/treatmentrecords/view_oralA.php',
    '/dentalemr_system/html/treatmentrecords/view_oralB.php',
    '/dentalemr_system/html/treatmentrecords/view_record.php',
    '/dentalemr_system/html/addpatienttreatment/patienttreatment.php',

    // Report pages
    '/dentalemr_system/html/reports/targetclientlist.php',
    '/dentalemr_system/html/reports/mho_ohp.php',
    '/dentalemr_system/html/reports/oralhygienefindings.php',

    // Manage users pages
    '/dentalemr_system/html/manageusers/manageuser.php',
    '/dentalemr_system/html/manageusers/activitylogs.php',
    '/dentalemr_system/html/manageusers/historylogs.php'
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
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Handle navigation requests
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    // When offline, serve appropriate pages
                    if (isAppPage(url.pathname)) {
                        return caches.match(OFFLINE_DASHBOARD)
                            .then(response => response || caches.match(OFFLINE_URL));
                    }

                    // For login page, serve cached version
                    if (url.pathname.includes('login.html')) {
                        return caches.match('/dentalemr_system/html/login/login.html');
                    }

                    return caches.match(OFFLINE_URL);
                })
        );
        return;
    }

    // Skip login-related PHP requests when offline
    if (!navigator.onLine && isLoginRequest(url)) {
        event.respondWith(
            new Response(JSON.stringify({
                error: 'offline',
                message: 'Cannot authenticate while offline. Please check your internet connection.'
            }), {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            })
        );
        return;
    }

    // For other requests, use cache-first strategy
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Return cached version if available
                if (response) {
                    return response;
                }

                // Clone the request
                const fetchRequest = event.request.clone();

                return fetch(fetchRequest)
                    .then((response) => {
                        // Check if valid response
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Clone the response
                        const responseToCache = response.clone();

                        caches.open(CACHE_NAME)
                            .then((cache) => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    })
                    .catch(() => {
                        // If request is for HTML
                        if (event.request.headers.get('accept')?.includes('text/html')) {
                            return caches.match(OFFLINE_URL);
                        }

                        // Generic offline response
                        return new Response('Offline', {
                            status: 503,
                            statusText: 'Service Unavailable'
                        });
                    });
            })
    );
});

// Helper functions
function isAppPage(pathname) {
    const appPages = [
        '/dentalemr_system/html/index.php',
        '/dentalemr_system/html/addpatient.php',
        '/dentalemr_system/html/archived.php',
        '/dentalemr_system/html/viewrecord.php',
        '/dentalemr_system/html/a_staff/',
        '/dentalemr_system/html/treatmentrecords/',
        '/dentalemr_system/html/reports/',
        '/dentalemr_system/html/manageusers/'
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