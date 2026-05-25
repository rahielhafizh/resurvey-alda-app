<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'resurvey_alda');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// App Config
define('APP_NAME', 'Resurvey Alda');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8000');

// Session Config
define('SESSION_LIFETIME', 3600);
define('SESSION_NAME', 'resurvey_alda_session');

define('ENABLE_HTTPS', false);
define('ENABLE_CSRF_PROTECTION', true);
define('DEBUG_MODE', true);
date_default_timezone_set('Asia/Jakarta');

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php-errors.log');
}

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (ENABLE_HTTPS) {
    ini_set('session.cookie_secure', 1);
}

session_name(SESSION_NAME);

// Database Connection
function getDBConnection()
{
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die('Database Connection Failed: ' . $e->getMessage());
        } else {
            die('Database Connection Failed');
        }
    }
}

// Helper Functions
function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit();
}

function isLoggedIn()
{
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('index.php');
    }
}
?>