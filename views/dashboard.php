<?php
// ============================================
// Dashboard - ClockIt - SIMPLE WORKING VERSION
// ============================================

$pageTitle = "Dashboard - ClockIt";
require_once __DIR__ . '/../views/layouts/header.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

// ============================================
// Check authentication
// ============================================
if (!AuthMiddleware::checkAuth()) {
    header('Location: /attendance_tracker/login');
    exit;
}

$user = AuthMiddleware::getUser();

// Fallback in case $user is null
if (!$user) {
    header('Location: /attendance_tracker/login');
    exit;
}
?>

<div class="dashboard-container">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">
                            Welcome, <?php echo htmlspecialchars($user['first_name'] ?? $user['email'] ?? 'User'); ?>!
                        </h1>
                        <p class="auth-subtitle">Here's your profile info:</p>
                    </div>

                    <div class="user-info mt-4">
                        <div class="info-item">
                            <strong>Name:</strong> 
                            <?php 
                                $firstName = $user['first_name'] ?? '';
                                $lastName = $user['last_name'] ?? '';
                                $email = $user['email'] ?? '';
                                
                                if ($firstName && $lastName) {
                                    echo htmlspecialchars($firstName . ' ' . $lastName);
                                } else {
                                    echo htmlspecialchars($email);
                                }
                            ?>
                        </div>
                        <div class="info-item">
                            <strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                        </div>
                        <div class="info-item">
                            <strong>Employee ID:</strong> <?php echo htmlspecialchars($user['employee_id'] ?? ''); ?>
                        </div>
                        <div class="info-item">
                            <strong>Role:</strong> <?php echo ($user['is_admin'] ?? false) ? "Administrator" : "Employee"; ?>
                        </div>
                        
                        <?php if (AdminMiddleware::isAdmin()): ?>
                            <div class="info-item">
                                <strong>Status:</strong> <span class="text-success">Admin Access</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 text-center">
                        <a href="/attendance_tracker/api/auth/logout" class="btn btn-primary">Sign Out</a>
                        
                        <?php if (AdminMiddleware::isAdmin()): ?>
                            <a href="/attendance_tracker/admin" class="btn btn-secondary ms-2">Admin Panel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>