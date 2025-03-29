<?php
// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === 'common.php') {
    header('HTTP/1.0 403 Forbidden');
    exit('You are not allowed to access this file directly.');
}

// Start or resume session with secure settings
function initSecureSession() {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        // Configure secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        
        session_set_cookie_params([
            'lifetime' => 3600,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start(); 
    }

    // Check if session exists and is active
    if (isset($_SESSION['created'])) {
        // Session exists, check if expired
        if ((time() - $_SESSION['created']) > 3600) {
            // Session expired, destroy it
            session_destroy();
            // Start new session
            session_start();
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    } else {
        // No session exists, create new one
        $_SESSION['created'] = time();
    }
}

function verifyAdminAccess() {
    
    if (!isset($_SESSION['user_id']) || $_SESSION['authlevel'] !== 'admin') {
        header("Location: /login.php?redirect=mask/admin.php");
        exit();
    }
    
    global $conn;
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['user'];
    
    $stmt = $conn->prepare("SELECT authlevel FROM users WHERE id = ? AND username = ? LIMIT 1");
    $stmt->bind_param("is", $user_id, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user || $user['authlevel'] !== 'admin') {
        logUserActivity($user_id, 'unauthorized_access', 'Attempted to access admin page');
        header("Location: /home.php");
        exit();
    }
}

// Database connection function using MySQLi
function getDbConnectionMysqli() {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        try {
            $host = DB_HOST;
            $username = DB_USERNAME;
            $password = DB_PASSWORD;
            $database = DB_NAME;
            $port = DB_PORT;

            $conn = new mysqli($host, $username, $password, $database, $port);
            
            // Set charset and collation
            $conn->set_charset("utf8mb4");
            $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Enable strict mode
            $conn->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            
        } catch (mysqli_sql_exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Error connecting to database");
        }
    }
    return $conn;
}

// CSRF token management
function initCSRFToken() {
    // Initialize secure session if not already done
    
    // Only generate new token if none exists or current one has expired
    if (empty($_SESSION['csrf_token']) || (time() - $_SESSION['csrf_token_time'] > 3600)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }  
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new Exception('CSRF token validation failed');
    }
}
function redirectToSite($url) {
    $site_root = "http://" . $_SERVER['HTTP_HOST']  . "/"; // Get the site URL dynamically
   

    // Fallback to JavaScript redirect if header fails
    echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($site_root . $url, ENT_QUOTES, 'UTF-8') . "';</script>";
}


// Authentication check
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        redirectToSite("login.php" . ($currentPage !== 'login.php' ? "?redirect=" . urlencode($currentPage) : ''));
        exit();
    }
}

// Sanitization helpers
function sanitizeString($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($email) {
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

// Common validation functions
function validatePassword($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
}

function validateUsername($username) {
    return preg_match('/^[A-Za-z0-9_]{3,50}$/', $username);
}

// Logging function
function logUserActivity($userId, $action, $description) {
    try {
        $conn = getDbConnectionMysqli();
        
        $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, description, ip_address, browser, referer, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $browser = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        $stmt->bind_param("isssss", $userId, $action, $description, $ip, $browser, $referer);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Error logging user activity: " . $e->getMessage());
        throw new Exception("Error logging user activity");
    }
}

// Rate limiting function
function checkRateLimit($action, $timeframe, $max_attempts) {
    try {
        $conn = getDbConnectionMysqli();
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM user_logs 
                               WHERE action = ? 
                               AND ip_address = ? 
                               AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("ssi", $action, $ip, $timeframe);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_array(MYSQLI_NUM);
        
        if ($row[0] >= $max_attempts) {
            throw new Exception("Too many attempts. Please try again later.");
        }
        
        $stmt->close();
        return true;
        
    } catch (Exception $e) {
        error_log("Error checking rate limit: " . $e->getMessage());
        throw new Exception("Error checking rate limit");
    }
}

// Common header inclusion
function includeHeader() {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/header.php';
}

// Common footer inclusion
function includeFooter() {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/footer.php';
}

// Initialize session and connections
initSecureSession();
require_once $_SERVER['DOCUMENT_ROOT'] . '/variables.php';
global $conn;
$conn = getDbConnectionMysqli();
$csrf_token = initCSRFToken();

?>