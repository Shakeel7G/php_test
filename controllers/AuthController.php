<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';
require_once __DIR__ . '/../utils/HashPassword.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/EmailSender.php';

class AuthController {
    private $db;
    private $hashPassword;
    private $responseHandler;
    private $jwt;
    private $emailSender;

    const MAX_FAILED_ATTEMPTS = 3;
    const LOCK_DURATION_SECONDS = 30;

    public function __construct() {
        $this->db = new Database();
        $this->hashPassword = new HashPassword();
        $this->responseHandler = new ResponseHandler();
        $this->jwt = new JWT();
        $this->emailSender = new EmailSender();
    }

    private function isStrongPassword($password) {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]).{8,}$/', $password);
    }

    private function generateAccessToken($user) {
        $payload = [
            'id' => $user['auth_id'],
            'employee_id' => $user['employee_id'],
            'email' => $user['username'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'is_admin' => $user['is_admin'],
            'iat' => time(),
            'exp' => time() + (15 * 24 * 60 * 60) // 15 days
        ];
        
        return $this->jwt->encode($payload);
    }

    // =============================================
    // LOGIN USER
    // =============================================
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['message'] = "Method not allowed";
            $_SESSION['success'] = false;
            header('Location: /attendance_tracker/login');
            exit;
        }

        try {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $rememberMe = isset($_POST['remember_me']);

            if (!$email || !$password) {
                $_SESSION['message'] = "Email and password are required.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/login');
                exit;
            }

            // Check if account is locked in session
            if (isset($_SESSION['locked_until']) && $_SESSION['locked_until'] > time()) {
                $secondsLeft = $_SESSION['locked_until'] - time();
                $_SESSION['message'] = "Account locked. Try again in {$secondsLeft} seconds.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/login');
                exit;
            }

            $stmt = $this->db->query(
                "SELECT aa.*, e.first_name, e.last_name, e.is_admin, e.employee_id
                 FROM account_auth aa 
                 JOIN employees e ON aa.employee_id = e.employee_id 
                 WHERE aa.username = ?",
                [$email]
            );
            $user = $stmt->fetch();

            if (!$user) {
                $this->handleFailedLogin($email);
                $_SESSION['message'] = "Invalid credentials.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/login');
                exit;
            }

            // Check if account is locked in database
            if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
                $minutesLeft = ceil((strtotime($user['lock_until']) - time()) / 60);
                $_SESSION['message'] = "Account locked. Try again in {$minutesLeft} minutes.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/login');
                exit;
            }

            $isMatch = $this->hashPassword->compare($password, $user['password']);
            if (!$isMatch) {
                $this->handleFailedLogin($email, $user);
                $_SESSION['message'] = "Invalid email or password.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/login');
                exit;
            }

            // Reset failed attempts on successful login
            $this->db->query(
                "UPDATE account_auth SET failed_login_attempts = 0, lock_until = NULL WHERE auth_id = ?",
                [$user['auth_id']]
            );

            // Clear any session lock
            unset($_SESSION['locked_until']);

            // Create session
            $userInfo = [
                'auth_id' => $user['auth_id'],
                'employee_id' => $user['employee_id'],
                'email' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'is_admin' => $user['is_admin']
            ];

            $_SESSION['user'] = $userInfo;
            $_SESSION['logged_in'] = true;

            if ($rememberMe) {
                // Set long-lasting cookie (15 days)
                $token = $this->generateAccessToken($user);
                setcookie('remember_token', $token, time() + (15 * 24 * 60 * 60), '/');
            }

            $_SESSION['message'] = "Login successful!";
            $_SESSION['success'] = true;
            header('Location: /attendance_tracker/dashboard');
            exit;
            
        } catch (Exception $err) {
            error_log("Login Error: " . $err->getMessage());
            $_SESSION['message'] = "Login failed. Please try again.";
            $_SESSION['success'] = false;
            header('Location: /attendance_tracker/login');
            exit;
        }
    }

    private function handleFailedLogin($email, $user = null) {
        if ($user) {
            $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
            $lockUntil = null;

            if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + self::LOCK_DURATION_SECONDS);
                $_SESSION['locked_until'] = time() + self::LOCK_DURATION_SECONDS;
            }

            $this->db->query(
                "UPDATE account_auth SET failed_login_attempts = ?, lock_until = ? WHERE auth_id = ?",
                [$attempts, $lockUntil, $user['auth_id']]
            );
        } else {
            // For non-existent users, we don't update the database but still show generic message
            $_SESSION['locked_until'] = time() + self::LOCK_DURATION_SECONDS;
        }
    }

    // =============================================
    // REGISTER USER
    // =============================================
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['message'] = "Method not allowed";
            $_SESSION['success'] = false;
            header('Location: /attendance_tracker/register');
            exit;
        }

        $connection = $this->db->getConnection();
        
        try {
            $first_name = $_POST['first_name'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $contact_no = $_POST['contact_no'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $backup_email = $_POST['backup_email'] ?? '';
            $address = $_POST['address'] ?? '';

            // Validation
            if (!$email || !$password || !$first_name || !$last_name || !$address) {
                $_SESSION['message'] = "All required fields must be provided.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/register');
                exit;
            }

            if (!$this->isStrongPassword($password)) {
                $_SESSION['message'] = "Password must include uppercase, lowercase, number, and special character.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/register');
                exit;
            }

            $connection->beginTransaction();

            // Check existing email
            $stmt = $connection->prepare("SELECT employee_id FROM employees WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $connection->rollBack();
                $_SESSION['message'] = "Employee with this email already exists.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/register');
                exit;
            }

            $stmt = $connection->prepare("SELECT auth_id FROM account_auth WHERE username = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $connection->rollBack();
                $_SESSION['message'] = "User already exists.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/register');
                exit;
            }

            // Generate South African ID
            $saId = substr(str_pad(mt_rand(), 13, '0', STR_PAD_LEFT), 0, 13);

            // Insert employee
            $stmt = $connection->prepare(
                "INSERT INTO employees (first_name, last_name, contact_no, email, address, id, date_hired, supervisor_name, leave_balance, classification_id) 
                 VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'System Administrator', 0.00, 2)"
            );
            $stmt->execute([$first_name, $last_name, $contact_no ?: null, $email, $address, $saId]);
            $employeeId = $connection->lastInsertId();

            // Create auth record
            $hashedPassword = $this->hashPassword->hash($password);
            $stmt = $connection->prepare(
                "INSERT INTO account_auth (employee_id, username, password, backup_email, created_at, lock_until, reset_token_hash, reset_expires, failed_login_attempts) 
                 VALUES (?, ?, ?, ?, NOW(), NULL, NULL, NULL, 0)"
            );
            $stmt->execute([$employeeId, $email, $hashedPassword, $backup_email ?: '']);

            $connection->commit();

            $_SESSION['message'] = "User registered successfully.";
            $_SESSION['success'] = true;
            header('Location: /attendance_tracker/login');
            exit;

        } catch (Exception $err) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log("Signup Error: " . $err->getMessage());
            $_SESSION['message'] = "Registration failed. Please try again.";
            $_SESSION['success'] = false;
            header('Location: /attendance_tracker/register');
            exit;
        }
    }

    // =============================================
    // FORGOT PASSWORD
    // =============================================
    public function forgotPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['message'] = "Method not allowed";
            $_SESSION['success'] = false;
            header('Location: /attendance_tracker/forgot-password');
            exit;
        }

        try {
            $email = $_POST['email'] ?? '';
            $useBackup = isset($_POST['useBackup']);

            if (!$email) {
                $_SESSION['message'] = "Email is required.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/forgot-password');
                exit;
            }

            $stmt = $this->db->query(
                "SELECT aa.*, e.email as employee_email, e.first_name
                 FROM account_auth aa 
                 JOIN employees e ON aa.employee_id = e.employee_id 
                 WHERE aa.username = ? OR e.email = ?",
                [$email, $email]
            );
            $user = $stmt->fetch();

            // Prevent info leakage - always return success
            if (!$user) {
                $_SESSION['message'] = "If that email exists, a reset link was sent.";
                $_SESSION['success'] = true;
                header('Location: /attendance_tracker/forgot-password');
                exit;
            }

            // Generate secure reset token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiry = date('Y-m-d H:i:s', time() + 30 * 60); // 30 mins

            error_log('🔐 RESET TOKEN FOR TESTING: ' . $token);
            error_log('📧 For email: ' . $email);
            error_log('⏰ Expires: ' . $expiry);

            $this->db->query(
                "UPDATE account_auth SET reset_token_hash = ?, reset_expires = ? WHERE auth_id = ?",
                [$tokenHash, $expiry, $user['auth_id']]
            );

            $resetUrl = $_ENV['FRONTEND_ORIGIN'] . "/reset-password?token=" . $token . "&email=" . urlencode($email);
            $targetEmail = ($useBackup && $user['backup_email']) ? $user['backup_email'] : $user['username'];

            $htmlContent = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; color: #222;'>
                    <h2 style='color:#2C3E50;'>🔐 Password Reset Request</h2>
                    <p>Hi " . ($user['first_name'] ?? '') . ",</p>
                    <p>We received a request to reset your password for your <b>Clock It</b> account.</p>
                    
                    <p>Click below to reset your password (expires in 30 minutes):</p>
                    
                    <a href='{$resetUrl}'
                       style='display:inline-block;background:#007bff;color:#fff;text-decoration:none;
                              padding:10px 18px;border-radius:6px;margin:16px 0;'>
                        Reset My Password
                    </a>

                    <p>If the button doesn't work, copy and paste this link in your browser:</p>
                    <p style='word-break:break-all;'>{$resetUrl}</p>

                    <hr style='margin:24px 0;border:none;border-top:1px solid #ccc;' />
                </div>
            ";

            // Send email
            $this->emailSender->send(
                $targetEmail,
                "Clock It - Password Reset",
                $htmlContent,
                "Reset your password here: {$resetUrl}"
            );

            $_SESSION['message'] = "Password reset link sent to {$targetEmail}.";
            $_SESSION['success'] = true;
            header('Location: /attendance_tracker/forgot-password');
            exit;

        } catch (Exception $err) {
            error_log("Forgot Password Error: " . $err->getMessage());
            $_SESSION['message'] = "Failed to send reset link. Please try again.";
            $_SESSION['success'] = false;
            header('Location: /attendance_tracker/forgot-password');
            exit;
        }
    }

    // =============================================
    // RESET PASSWORD
    // =============================================
    public function resetPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['message'] = "Method not allowed";
            $_SESSION['success'] = false;
            header('Location: /attendance_tracker/reset-password');
            exit;
        }

        try {
            $email = $_POST['email'] ?? '';
            $token = $_POST['token'] ?? '';
            $newPassword = $_POST['newPassword'] ?? '';
            $confirmPassword = $_POST['confirmPassword'] ?? '';

            if (!$email || !$token || !$newPassword || !$confirmPassword) {
                $_SESSION['message'] = "All fields are required.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
                exit;
            }

            if ($newPassword !== $confirmPassword) {
                $_SESSION['message'] = "Passwords do not match.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
                exit;
            }

            if (!$this->isStrongPassword($newPassword)) {
                $_SESSION['message'] = "Password must meet strength requirements.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
                exit;
            }

            $stmt = $this->db->query(
                "SELECT aa.* 
                 FROM account_auth aa 
                 JOIN employees e ON aa.employee_id = e.employee_id 
                 WHERE aa.username = ? OR e.email = ?",
                [$email, $email]
            );
            $user = $stmt->fetch();

            if (!$user) {
                $_SESSION['message'] = "Invalid or expired reset link.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
                exit;
            }

            $tokenHash = hash('sha256', $token);

            // Verify token and expiry
            if (!$user['reset_token_hash'] || 
                !hash_equals($user['reset_token_hash'], $tokenHash) ||
                strtotime($user['reset_expires']) < time()) {
                $_SESSION['message'] = "Invalid or expired reset token.";
                $_SESSION['success'] = false;
                header('Location: /attendance_tracker/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
                exit;
            }

            $hashedPassword = $this->hashPassword->hash($newPassword);

            $this->db->query(
                "UPDATE account_auth SET password = ?, reset_token_hash = NULL, reset_expires = NULL WHERE auth_id = ?",
                [$hashedPassword, $user['auth_id']]
            );

            $_SESSION['message'] = "Password reset successful.";
            $_SESSION['success'] = true;
            header('Location: /attendance_tracker/login');
            exit;

        } catch (Exception $err) {
            error_log("Reset Password Error: " . $err->getMessage());
            $_SESSION['message'] = "Failed to reset password. Please try again.";
            $_SESSION['success'] = false;
            header('Location: /attendance_tracker/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
            exit;
        }
    }

    // =============================================
    // UNLOCK ACCOUNT
    // =============================================
    public function unlockAccount() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responseHandler->send(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? '';

            if (!$email) {
                $this->responseHandler->send(['success' => false, 'message' => 'Email is required.'], 400);
                return;
            }

            $this->db->query(
                "UPDATE account_auth 
                 SET failed_login_attempts = 0, lock_until = NULL 
                 WHERE username = ?",
                [$email]
            );

            $this->responseHandler->send(['success' => true, 'message' => 'Account unlocked successfully.']);

        } catch (Exception $err) {
            error_log("Unlock Account Error: " . $err->getMessage());
            $this->responseHandler->send(['success' => false, 'message' => 'Failed to unlock account.'], 500);
        }
    }

    // =============================================
    // GET USER PROFILE
    // =============================================
    public function getUserProfile() {
        if (!isset($_SESSION['user'])) {
            $this->responseHandler->send(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        try {
            $authId = $_SESSION['user']['auth_id'];

            $stmt = $this->db->query(
                "SELECT 
                    e.employee_id, e.first_name, e.last_name, e.contact_no, e.email, 
                    e.date_hired, e.supervisor_name, e.leave_balance, e.address, 
                    e.is_admin, e.id as employee_code, e.classification_id,
                    ec.department, ec.position, ec.role, ec.employment_type, ec.employee_level,
                    aa.username, aa.backup_email, aa.created_at, aa.lock_until, aa.failed_login_attempts
                 FROM account_auth aa
                 JOIN employees e ON aa.employee_id = e.employee_id
                 LEFT JOIN emp_classification ec ON e.classification_id = ec.classification_id
                 WHERE aa.auth_id = ?",
                [$authId]
            );
            $userData = $stmt->fetch();

            if (!$userData) {
                $this->responseHandler->send(['success' => false, 'message' => 'Profile not found.'], 404);
                return;
            }

            // Check if account is currently locked
            $isLocked = $userData['lock_until'] && strtotime($userData['lock_until']) > time();

            // Generate initials for frontend
            $initials = $userData['first_name'] && $userData['last_name']
                ? strtoupper(substr($userData['first_name'], 0, 1) . substr($userData['last_name'], 0, 1))
                : strtoupper(substr($userData['email'], 0, 1));

            // Return user data with additional info
            $profileData = array_merge($userData, [
            'is_locked' => $isLocked,
            'initials' => $initials
            ]);

            $this->responseHandler->send([
                'success' => true, 
                'message' => 'Profile fetched successfully.', 
                'data' => $profileData
            ]);

        } catch (Exception $err) {
            error_log("Profile Fetch Error: " . $err->getMessage());
            $this->responseHandler->send(['success' => false, 'message' => 'Failed to fetch profile.'], 500);
        }
    }

    // =============================================
    // LOGOUT
    // =============================================
    public function logout() {
        // Clear session
        session_unset();
        session_destroy();
        
        // Clear remember token cookie
        setcookie('remember_token', '', time() - 3600, '/');
        
        header('Location: /attendance_tracker/');
        exit;
    }

    // =============================================
    // THEME SWITCHING
    // =============================================
    public function setTheme() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->responseHandler->send(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }

        $theme = $_GET['theme'] ?? 'light';
        if (in_array($theme, ['light', 'dark'])) {
            $_SESSION['theme'] = $theme;
            $this->responseHandler->send(['success' => true, 'message' => 'Theme updated']);
        } else {
            $this->responseHandler->send(['success' => false, 'message' => 'Invalid theme'], 400);
        }
    }
}
?>