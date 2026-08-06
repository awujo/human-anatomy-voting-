<?php
// --------------------------------------------------------------------
// Database connection settings.
// EDIT these four values to match your Hostinger MySQL database
// (hPanel -> Databases -> MySQL Databases).
// --------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'u355928399_humananatomy');
define('DB_USER', 'u355928399_humananatomy');
define('DB_PASS', 'CHANGE_ME');

// Base URL of the site (no trailing slash), used to build links/redirects.
// Example: 'https://yourdomain.com' or 'https://yourdomain.com/voting'
define('BASE_URL', 'https://darkgreen-lyrebird-781181.hostingersite.com');

// Max upload size for candidate photos, in bytes (2MB default).
define('MAX_PHOTO_BYTES', 2 * 1024 * 1024);

// --------------------------------------------------------------------
// Session + error display
// --------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '0'); // set to '1' temporarily while debugging on a dev host
