<?php
// ============================================
// ENABLE ERROR REPORTING - ADD THIS AT TOP
// ============================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Use absolute paths with __DIR__
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/utils/ResponseHandler.php';
require_once __DIR__ . '/utils/JWT.php';
require_once __DIR__ . '/utils/HashPassword.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/middleware/AdminMiddleware.php';
require_once __DIR__ . '/middleware/ErrorHandler.php';

// Register error handlers
ErrorHandler::register();

session_start();

// Simple routing with middleware support
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base directory if exists
$base_dir = '/attendance_tracker';
if (strpos($path, $base_dir) === 0) {
    $path = substr($path, strlen($base_dir));
}

// Ensure path ends with / for root
if ($path === '') {
    $path = '/';
}

$routes = [
    // Public routes
    '/' => ['view' => 'views/auth/login.php'],
    '/login' => ['view' => 'views/auth/login.php'],
    '/register' => ['view' => 'views/auth/register.php'],
    '/forgot-password' => ['view' => 'views/auth/forgot-password.php'],
    '/reset-password' => ['view' => 'views/auth/reset-password.php'],
    
    // Protected routes
    '/dashboard' => [
        'view' => 'views/dashboard.php', 
        'middleware' => 'AuthMiddleware::protect'
    ],
    
    // API routes - Auth
    '/api/auth/login' => ['controller' => 'AuthController@login'],
    '/api/auth/register' => ['controller' => 'AuthController@register'],
    '/api/auth/forgot-password' => ['controller' => 'AuthController@forgotPassword'],
    '/api/auth/reset-password' => ['controller' => 'AuthController@resetPassword'],
    '/api/auth/logout' => ['controller' => 'AuthController@logout'],
    '/api/auth/unlock-account' => [
        'controller' => 'AuthController@unlockAccount',
        'middleware' => 'AdminMiddleware::requireAdmin'
    ],
    '/api/auth/profile' => [
        'controller' => 'AuthController@getUserProfile',
        'middleware' => 'AuthMiddleware::protect'
    ],
    '/api/theme' => ['controller' => 'AuthController@setTheme'],
];

// Handle the request
if (isset($routes[$path])) {
    $route = $routes[$path];
    
    // Apply middleware if specified
    if (isset($route['middleware'])) {
        try {
            call_user_func($route['middleware']);
        } catch (Exception $e) {
            error_log("Middleware error: " . $e->getMessage());
            http_response_code(500);
            echo "Middleware Error: " . $e->getMessage();
            exit;
        }
    }
    
    // Handle controller routes
    if (isset($route['controller'])) {
        try {
            list($controller, $method) = explode('@', $route['controller']);
            $controllerPath = __DIR__ . '/controllers/' . $controller . '.php';
            
            if (!file_exists($controllerPath)) {
                throw new Exception("Controller file not found: " . $controllerPath);
            }
            
            require_once $controllerPath;
            
            if (!class_exists($controller)) {
                throw new Exception("Controller class not found: " . $controller);
            }
            
            $controllerInstance = new $controller();
            
            if (!method_exists($controllerInstance, $method)) {
                throw new Exception("Method not found: " . $controller . "->" . $method);
            }
            
            $controllerInstance->$method();
        } catch (Exception $e) {
            error_log("Controller error: " . $e->getMessage());
            http_response_code(500);
            echo "Controller Error: " . $e->getMessage();
            exit;
        }
    } 
    // Handle view routes
    elseif (isset($route['view'])) {
        $isAuthPage = in_array($path, ['/', '/login', '/register', '/forgot-password', '/reset-password']);
        $isProtectedPage = strpos($path, '/dashboard') === 0;
        
        // Redirect logged-in users away from auth pages
        if ($isAuthPage && AuthMiddleware::checkAuth()) {
            header('Location: /attendance_tracker/dashboard');
            exit;
        }
        
        // Redirect non-logged-in users away from protected pages
        if ($isProtectedPage && !AuthMiddleware::checkAuth()) {
            header('Location: /attendance_tracker/login');
            exit;
        }
        
        $viewPath = __DIR__ . '/' . $route['view'];
        if (!file_exists($viewPath)) {
            http_response_code(404);
            echo "View not found: " . $viewPath;
            exit;
        }
        
        require_once $viewPath;
    }
} else {
    http_response_code(404);
    
    if (strpos($path, '/api/') === 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found. Please check the API documentation at the root endpoint.'
        ]);
    } else {
        // For unknown pages, redirect to login
        header('Location: /attendance_tracker/login');
        exit;
    }
}
?>