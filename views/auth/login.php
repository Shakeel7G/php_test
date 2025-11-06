<?php
$pageTitle = "Login - ClockIt";
require_once __DIR__ . '/../layouts/header.php';

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    header('Location: /attendance_tracker/dashboard');
    exit;
}
?>

<div class="login-wrapper">
    <!-- LEFT SIDE -->
    <section class="login-left">
        <div class="left-content">
            <h1 class="left-title">Welcome Back</h1>
            <p class="left-subtitle">Login to continue to your account</p>

            <img src="/attendance_tracker/assets/images/<?php echo isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark' ? 'login-logo-dark.png' : 'login-logo-lg.png'; ?>" 
                 alt="Company Logo" class="login-logo" />

            <blockquote class="login-quote">
                "Login effortlessly and leave the stress behind — secure, simple, and reliable every single time."
                <span>— Development Team</span>
            </blockquote>
        </div>
    </section>

    <!-- RIGHT SIDE -->
    <section class="login-right">
        <div class="form-card">
            <h2 class="form-title">User Verification</h2>
            <p class="form-subtitle">Enter your credentials to access your account</p>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="login-alert <?php echo $_SESSION['success'] ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo $_SESSION['message']; ?>
                    <?php unset($_SESSION['message'], $_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/attendance_tracker/api/auth/login" class="login-form">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" required 
                    class="form-input" placeholder="Enter your email" 
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />

                <label for="password" class="form-label">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required 
                        class="form-input" placeholder="Enter your password" />
                    <span class="toggle-password" onclick="togglePasswordVisibility()">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember_me" />
                        Remember Me
                    </label>
                    <a href="/attendance_tracker/forgot-password" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="login-btn">
                    Sign In
                </button>

                <div class="register-link">
                    <span>Don't have an account?</span>
                    <a href="/attendance_tracker/register">Sign up here</a>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const icon = document.querySelector('.toggle-password i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>