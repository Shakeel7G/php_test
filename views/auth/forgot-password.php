<?php
$pageTitle = "Forgot Password - ClockIt";
require_once __DIR__ . '/../layouts/header.php';

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    header('Location: /attendance_tracker/dashboard');
    exit;
}
?>

<div class="forgot-container">
    <!-- Left Image -->
    <div class="image">
        <img src="/attendance_tracker/assets/images/Forgot password-bro (3).png" alt="Forgot Password Illustration" />
    </div>

    <!-- Right Form -->
    <div class="form">
        <h1 class="heading">Forgot Password?</h1>
        <p class="text">Enter your registered email address to receive a reset link.</p>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert <?php echo $_SESSION['success'] ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $_SESSION['message']; ?>
                <?php unset($_SESSION['message'], $_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/attendance_tracker/api/auth/forgot-password">
            <input type="email" name="email" placeholder="Enter your email address" 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required />

            <div class="checkbox-wrapper">
                <input type="checkbox" id="useBackup" name="useBackup" 
                       <?php echo isset($_POST['useBackup']) ? 'checked' : ''; ?> />
                <label for="useBackup">Send to backup email instead</label>
            </div>

            <button type="submit" class="primary">
                Send Reset Link
            </button>

            <a href="/attendance_tracker/" class="secondary">Back to Sign In</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>