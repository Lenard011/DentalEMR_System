<?php
// config.php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'dentalemr_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'lenardovinci64@gmail.com');
define('SMTP_PASS', 'khxm pyvx wdlq xmjg');
// define('SMTP_PASS', 'gvce aguf zgwa mgpp');
define('SMTP_FROM_EMAIL', 'lenardovinci64@gmail.com');
define('SMTP_FROM_NAME', 'MHO Dental Clinic System');

// Application settings
define('MFA_CODE_EXPIRY', 300); // 5 minutes in seconds
define('DAILY_VERIFICATION_RESET', '23:59:00');

// Debug settings
define('DEBUG_MODE', false); // Set to true for development, false for production
?>