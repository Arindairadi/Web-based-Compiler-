<?php
// includes/auth.php - Authentication and Logging System

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Login user
 */
function login($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        logActivity($user['id'], 'login', 'User logged in');
        return true;
    }
    return false;
}

/**
 * Register new user
 */
function register($username, $email, $password) {
    global $pdo;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $hash]);
        $userId = $pdo->lastInsertId();
        logActivity($userId, 'register', 'User registered');
        return true;
    } catch (PDOException $e) {
        // Duplicate entry (username or email)
        return false;
    }
}

/**
 * Logout user
 */
function logout() {
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    $_SESSION = [];
    session_destroy();
}

/**
 * Require login (redirect if not authenticated)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Log generic user activity
 */
function logActivity($userId, $action, $details = null) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $details, $ip, $ua]);
}

/**
 * Log compilation event
 */
function logCompilation($userId, $language, $sessionId, $sourceCode, $success, $errorsCount, $compilationTimeMs) {
    global $pdo;
    $hash = hash('sha256', $sourceCode);
    $preview = substr($sourceCode, 0, 500);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO compilation_logs (user_id, language, session_id, source_code_hash, source_code_preview, success, errors_count, compilation_time_ms, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $language, $sessionId, $hash, $preview, $success, $errorsCount, $compilationTimeMs, $ip, $ua]);
}
?>