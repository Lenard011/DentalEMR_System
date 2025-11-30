// Local Authentication Management for Offline Mode
class LocalAuth {
    constructor() {
        this.currentUserKey = 'dentalemr_current_user';
        this.offlineUsersKey = 'dentalemr_local_users';
        this.init();
    }

    init() {
        if (!localStorage.getItem(this.offlineUsersKey)) {
            localStorage.setItem(this.offlineUsersKey, JSON.stringify([]));
        }
    }

    // Validate if user has an active offline session
    validateOfflineSession() {
        try {
            const userData = sessionStorage.getItem(this.currentUserKey);
            if (!userData) {
                console.log('No session data found in sessionStorage');
                return false;
            }

            const user = JSON.parse(userData);

            // Check if session is still valid (less than 24 hours old)
            if (user.loginTime) {
                const loginTime = new Date(user.loginTime);
                const now = new Date();
                const hoursDiff = (now - loginTime) / (1000 * 60 * 60);

                if (hoursDiff > 24) {
                    console.log('Session expired (more than 24 hours)');
                    this.logout();
                    return false;
                }
            }

            // Verify user exists in local storage
            const offlineUsers = JSON.parse(localStorage.getItem(this.offlineUsersKey) || '[]');
            const userExists = offlineUsers.some(u =>
                u.email === user.email &&
                u.type === user.type &&
                u.isActive === true
            );

            if (!userExists) {
                console.log('User not found in offline users list');
                this.logout();
                return false;
            }

            console.log('Offline session validated successfully for:', user.email);
            return true;

        } catch (error) {
            console.error('Error validating offline session:', error);
            this.logout();
            return false;
        }
    }

    // Get current user data
    getCurrentUser() {
        try {
            const userData = sessionStorage.getItem(this.currentUserKey);
            return userData ? JSON.parse(userData) : null;
        } catch (error) {
            console.error('Error getting current user:', error);
            return null;
        }
    }

    // Create offline session after successful login
    createOfflineSession(userData) {
        try {
            const sessionData = {
                ...userData,
                loginTime: new Date().toISOString(),
                isOffline: true,
                sessionId: 'offline_' + Date.now()
            };

            sessionStorage.setItem(this.currentUserKey, JSON.stringify(sessionData));
            console.log('Offline session created for:', userData.email);
            return true;
        } catch (error) {
            console.error('Error creating offline session:', error);
            return false;
        }
    }

    // Logout user
    logout() {
        try {
            sessionStorage.removeItem(this.currentUserKey);
            console.log('User logged out from offline session');
        } catch (error) {
            console.error('Error during logout:', error);
        }
    }

    // Authenticate user offline
    authenticateOffline(email, password, userType) {
        try {
            const offlineUsers = JSON.parse(localStorage.getItem(this.offlineUsersKey) || '[]');
            console.log('Available offline users:', offlineUsers);

            const user = offlineUsers.find(u =>
                u.email === email &&
                u.type === userType &&
                u.offlinePassword === password &&
                u.isActive === true
            );

            if (user) {
                // Create session using the same format as localAuth
                const sessionCreated = this.createOfflineSession(user);
                if (sessionCreated) {
                    console.log('Offline authentication successful for:', email);
                    return user;
                }
            }

            console.log('Offline authentication failed for:', email);
            return null;
        } catch (error) {
            console.error('Offline auth error:', error);
            return null;
        }
    }

    // Register user for offline access
    registerUserForOffline(userData) {
        try {
            const users = JSON.parse(localStorage.getItem(this.offlineUsersKey) || '[]');
            const offlinePassword = this.generateOfflinePassword();

            const userRecord = {
                ...userData,
                offlinePassword: offlinePassword,
                lastOnlineSync: new Date().toISOString(),
                isActive: true,
                registeredAt: new Date().toISOString()
            };

            // Update or add user
            const existingIndex = users.findIndex(u => u.email === userData.email && u.type === userData.type);
            if (existingIndex !== -1) {
                users[existingIndex] = userRecord;
            } else {
                users.push(userRecord);
            }

            localStorage.setItem(this.offlineUsersKey, JSON.stringify(users));
            console.log('User registered for offline access:', userData.email);
            return offlinePassword;

        } catch (error) {
            console.error('Error registering user for offline access:', error);
            return null;
        }
    }

    // Add this method to the LocalAuth class
    checkSimpleOfflineSession() {
        try {
            // Simple check - just see if we have any session data
            const sessionData = sessionStorage.getItem(this.currentUserKey);
            if (sessionData) {
                const user = JSON.parse(sessionData);
                return !!(user && user.isOffline);
            }

            // Alternative check - see if we have offline users registered
            const offlineUsers = localStorage.getItem(this.offlineUsersKey);
            if (offlineUsers) {
                const users = JSON.parse(offlineUsers);
                return users && users.length > 0;
            }

            return false;
        } catch (error) {
            console.error('Simple offline session check failed:', error);
            return false;
        }
    }

    generateOfflinePassword() {
        return Math.random().toString(36).slice(-8) + Math.random().toString(36).slice(-8);
    }
}

// Create global instance
const localAuth = new LocalAuth();