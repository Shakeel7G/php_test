<?php
$pageTitle = "Register - ClockIt";
require_once __DIR__ . '/../layouts/header.php';

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    header('Location: /attendance_tracker/dashboard');
    exit;
}
?>

<div class="register-wrapper">
    <div class="register-right">
        <div class="form-card">
            <h1 class="form-title">Create Account</h1>
            <p class="form-subtitle">Join ClockIt and track your attendance</p>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="login-alert <?php echo $_SESSION['success'] ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo $_SESSION['message']; ?>
                    <?php unset($_SESSION['message'], $_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/attendance_tracker/api/auth/register" class="form-grid" onsubmit="return validateForm()">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input name="first_name" class="form-input" type="text" placeholder="Enter first name" 
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input name="last_name" class="form-input" type="text" placeholder="Enter last name" 
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input name="email" class="form-input" type="email" placeholder="Enter your email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input name="contact_no" class="form-input" type="tel" placeholder="Enter contact number" 
                               value="<?php echo isset($_POST['contact_no']) ? htmlspecialchars($_POST['contact_no']) : ''; ?>" />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-input" rows="2" placeholder="Enter your address" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- PASSWORD FIELD WITH VALIDATOR -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-field">
                            <input name="password" id="password" type="password" placeholder="Create a strong password" 
                                   maxlength="15" oninput="updatePasswordHints()" required class="form-input" />
                            <span class="toggle-password" onclick="togglePasswordVisibility('password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>

                        <small id="charWarning" class="warning"></small>

                        <div class="strength">
                            <div id="strengthBar" class="strength-bar"></div>
                        </div>
                        <small id="strengthLabel" class="strength-label">Weak</small>

                        <ul id="passwordHints" class="password-hints">
                            <li id="hintUppercase">Contains an uppercase letter (A–Z)</li>
                            <li id="hintNumber">Contains a number (0–9)</li>
                            <li id="hintSpecial">Contains a special character (!@#$%)</li>
                            <li id="hintLength">8–15 characters long</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Backup Email (Optional)</label>
                        <input name="backup_email" class="form-input" type="email" placeholder="Enter backup email" 
                               value="<?php echo isset($_POST['backup_email']) ? htmlspecialchars($_POST['backup_email']) : ''; ?>" />
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    Create Account
                </button>

                <div class="register-link">
                    <span>Already have an account? </span>
                    <a href="/attendance_tracker/">Sign in here</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const icon = passwordInput.parentNode.querySelector('.toggle-password i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function updatePasswordHints() {
    const password = document.getElementById('password').value;
    const hasUppercase = /[A-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);
    const hasMinLength = password.length >= 8 && password.length <= 15;
    
    // Update hints
    document.getElementById('hintUppercase').className = hasUppercase ? 'valid' : '';
    document.getElementById('hintNumber').className = hasNumber ? 'valid' : '';
    document.getElementById('hintSpecial').className = hasSpecial ? 'valid' : '';
    document.getElementById('hintLength').className = hasMinLength ? 'valid' : '';
    
    // Update warning
    const charWarning = document.getElementById('charWarning');
    charWarning.textContent = password.length > 15 ? 'Password must be between 8–15 characters.' : '';
    
    // Update strength meter
    let score = 0;
    if (hasUppercase) score++;
    if (hasNumber) score++;
    if (hasSpecial) score++;
    if (hasMinLength) score++;
    
    const strengthPercent = (score / 4) * 100;
    const strengthBar = document.getElementById('strengthBar');
    const strengthLabel = document.getElementById('strengthLabel');
    
    let strengthText, strengthColor;
    switch (score) {
        case 0:
        case 1: 
            strengthText = "Weak"; 
            strengthColor = "#FF4D4D"; 
            break;
        case 2: 
            strengthText = "Fair"; 
            strengthColor = "#FFA500"; 
            break;
        case 3: 
            strengthText = "Good"; 
            strengthColor = "#00BFFF"; 
            break;
        case 4: 
            strengthText = "Strong"; 
            strengthColor = "#00C853"; 
            break;
        default: 
            strengthText = "Weak"; 
            strengthColor = "#CCCCCC";
    }
    
    strengthBar.style.width = strengthPercent + '%';
    strengthBar.style.background = strengthColor;
    strengthLabel.textContent = strengthText;
    strengthLabel.style.color = strengthColor;
}

function validateForm() {
    const password = document.getElementById('password').value;
    const hasUppercase = /[A-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);
    const hasMinLength = password.length >= 8 && password.length <= 15;
    
    const score = [hasUppercase, hasNumber, hasSpecial, hasMinLength].filter(Boolean).length;
    
    if (score < 3) {
        alert('Please make your password stronger before continuing.');
        return false;
    }
    
    return true;
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>