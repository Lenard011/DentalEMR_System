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
                        return caches.match('/DentalEMR_System/html/login/login.html');
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