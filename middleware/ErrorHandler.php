<?php
class ErrorHandler {
    public static function handle($errno, $errstr, $errfile, $errline) {
        error_log("Error: {$errstr} in {$errfile} on line {$errline}");
        
        // Show detailed errors in development
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $errstr,
                'file' => $errfile,
                'line' => $errline
            ]);
        } else {
            http_response_code(500);
            echo "<h2>Error: {$errstr}</h2>";
            echo "<p>File: {$errfile} on line {$errline}</p>";
        }
        
        exit;
    }

    public static function handleException($exception) {
        error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
        
        // Show detailed errors in development
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace()
            ]);
        } else {
            http_response_code(500);
            echo "<h2>Exception: {$exception->getMessage()}</h2>";
            echo "<p>File: {$exception->getFile()} on line {$exception->getLine()}</p>";
            echo "<pre>Trace: " . $exception->getTraceAsString() . "</pre>";
        }
        
        exit;
    }

    public static function register() {
        set_error_handler([self::class, 'handle']);
        set_exception_handler([self::class, 'handleException']);
    }
}
?>