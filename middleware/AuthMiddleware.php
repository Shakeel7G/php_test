<?php
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';

class AuthMiddleware {
    private $jwt;
    private $responseHandler;

    public function __construct() {
        $this->jwt = new JWT();
        $this->responseHandler = new ResponseHandler();
    }

    public function verifyToken() {
        // Check session first
        if (isset($_SESSION['user']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            return $_SESSION['user'];
        }

        // Check Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        // Also check for standard header (some servers use different case)
        if (empty($authHeader)) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if (!$authHeader || !str_starts_with($authHeader, "Bearer ")) {
            // Check remember token cookie
            $token = $_COOKIE['remember_token'] ?? '';
            if (!$token) {
                $this->responseHandler->send([
                    'success' => false, 
                    'message' => 'No token provided'
                ], 401);
                exit;
            }
        } else {
            $token = substr($authHeader, 7); // Remove "Bearer " prefix
        }

        try {
            $decoded = $this->jwt->decode($token);
            
            if (!$decoded) {
                $this->responseHandler->send([
                    'success' => false, 
                    'message' => 'Invalid or expired token'
                ], 403);
                exit;
            }

            // Check if token is expired
            if (isset($decoded['exp']) && $decoded['exp'] < time()) {
                $this->responseHandler->send([
                    'success' => false, 
                    'message' => 'Token expired'
                ], 403);
                exit;
            }

            // Set user in session for subsequent requests - FIXED SESSION DATA
            $_SESSION['user'] = [
                'auth_id' => $decoded['id'],
                'employee_id' => $decoded['employee_id'],
                'email' => $decoded['email'],
                'first_name' => $decoded['first_name'] ?? '',
                'last_name' => $decoded['last_name'] ?? '',
                'is_admin' => $decoded['is_admin']
            ];
            $_SESSION['logged_in'] = true;

            return $_SESSION['user'];

        } catch (Exception $err) {
            error_log("Token verification failed: " . $err->getMessage());
            $this->responseHandler->send([
                'success' => false, 
                'message' => 'Invalid or expired token'
            ], 403);
            exit;
        }
    }

    // Static method for easy usage in routes
    public static function protect() {
        $middleware = new self();
        return $middleware->verifyToken();
    }

    // Check if user is authenticated (for views)
    public static function checkAuth() {
        return isset($_SESSION['user']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    // Get current user
    public static function getUser() {
        return $_SESSION['user'] ?? null;
    }
}
?>