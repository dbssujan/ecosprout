<?php

// Environment settings
define('APP_NAME', 'EcoSprout Nursery');
define('APP_TAGLINE', 'Cultivating Sustainable Green Spaces in Kegalle');
define('APP_URL', 'http://localhost/ecosprout');
define('APP_CURRENCY', 'LKR');
define('APP_LOCATION', 'Colombo-Kandy Road, Kegalle, Sri Lanka');

// Database Credentials (XAMPP / Local MySQL default)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'ecosprout_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security Configurations
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Error reporting for development (disable display in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
