<?php
/**
 * Authentication System for eTranzact Downtime Tracker
 * Handles user login, logout, session management, and role-based access control
 */

require_once 'config.php';

class Auth {
    private $pdo;
    
    public function __construct($database) {
        $this->pdo = $database;
    }
    
    /**
     * Authenticate user login
     * @param string $username Username or email
     * @param string $password Plain text password
     * @return array|bool User data on success, false on failure
     */
    public function login($username, $password) {
        try {
            // Check if input is email or username
            $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            
            $stmt = $this->pdo->prepare("
                SELECT user_id, username, email, password_hash, full_name, role, is_active, changed_password
                FROM users 
                WHERE $field = ? AND is_active = 1
                LIMIT 1
            ");
            
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $updateStmt = $this->pdo->prepare("
                    UPDATE users 
                    SET last_login = NOW() 
                    WHERE user_id = ?
                ");
                $updateStmt->execute([$user['user_id']]);
                
                // Log the login activity
                $this->logActivity($user['user_id'], 'user.login', 'User logged in successfully');
                
                return $user;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Auth login error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is logged in
     * @return bool
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user data
     * @return array|null
     */
    public static function getUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'changed_password' => $_SESSION['changed_password'] ?? false
        ];
    }
    
    /**
     * Check if user has admin role
     * @return bool
     */
    public static function isAdmin() {
        $user = self::getUser();
        return $user && $user['role'] === 'admin';
    }
    
    /**
     * Check if user has specific role
     * @param string $role Role to check
     * @return bool
     */
    public static function hasRole($role) {
        $user = self::getUser();
        return $user && $user['role'] === $role;
    }
    
    /**
     * Require authentication - redirect to login if not logged in
     * @param string|null $redirect_url URL to redirect after login
     */
    public static function requireLogin($redirect_url = null) {
        if (!self::isLoggedIn()) {
            if ($redirect_url === null) {
                $redirect_url = $_SERVER['REQUEST_URI'];
            }
            $_SESSION['login_redirect'] = $redirect_url;
            header('Location: login.php');
            exit;
        }
    }
    
    /**
     * Require admin role - redirect to unauthorized page if not admin
     */
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: unauthorized.php');
            exit;
        }
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        // Log the logout activity if user was logged in
        if (self::isLoggedIn()) {
            global $pdo;
            $auth = new Auth($pdo);
            $auth->logActivity($_SESSION['user_id'], 'user.logout', 'User logged out');
        }
        
        // Destroy session
        session_destroy();
        session_start(); // Start new session for flash messages
    }
    
    /**
     * Log user activity
     * @param int $user_id
     * @param string $action
     * @param string $description
     */
    public function logActivity($user_id, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
    
    /**
     * Hash password securely
     * @param string $password
     * @return string
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Get all active users (admin only)
     * @return array
     */
    public function getAllUsers() {
        try {
            $stmt = $this->pdo->query("
                SELECT user_id, username, email, full_name, role, is_active, last_login, created_at
                FROM users 
                WHERE is_active = 1
                ORDER BY created_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create new user (admin only)
     * @param array $userData
     * @return bool|int User ID on success, false on failure
     */
    public function createUser($userData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $userData['username'],
                $userData['email'],
                self::hashPassword($userData['password']),
                $userData['full_name'],
                $userData['role'] ?? 'user',
                $userData['is_active'] ?? 1
            ]);
            
            return $result ? $this->pdo->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize auth system
$auth = new Auth($pdo);

// Auto-logout after inactivity (30 minutes)
if (Auth::isLoggedIn() && isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    $timeout = 30 * 60; // 30 minutes
    
    if ($inactive_time > $timeout) {
        Auth::logout();
        $_SESSION['error'] = 'Session expired due to inactivity. Please log in again.';
        header('Location: login.php');
        exit;
    }
}

// Update last activity time
if (Auth::isLoggedIn()) {
    $_SESSION['last_activity'] = time();
}