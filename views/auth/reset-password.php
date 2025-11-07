<?php
$pageTitle = "Reset Password - ClockIt";
require_once __DIR__ . '/../layouts/header.php';

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    header('Location: /attendance_tracker/dashboard');
    exit;
}

// Get token and email from URL
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (!$token || !$email) {
    $_SESSION['message'] = "Invalid or missing reset token/email.";
    $_SESSION['success'] = false;
    header('Location: /attendance_tracker/forgot-password');
    exit;
}
?>

<style>
    .reset-container {
        background: linear-gradient(180deg, rgba(6, 195, 167, 0.15) 0%, rgba(6, 195, 167, 0.05) 100%);
    }

    .password-field {
        position: relative;
        width: 100%;
        margin: 8px 0 16px 0;
    }

    .password-field input {
        width: 100%;
        padding: 12px 15px;
        padding-right: 45px;
        box-sizing: border-box;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        background: white;
        margin: 0;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        line-height: 1.5;
    }

    /* Force same styling for both password and text types */
    .password-field input[type="password"],
    .password-field input[type="text"] {
        font-family: 'Inter', sans-serif !important;
        font-size: 14px !important;
        padding: 12px 15px !important;
        padding-right: 45px !important;
        border: 1px solid #ccc !important;
        border-radius: 4px !important;
        background: white !important;
        line-height: 1.5 !important;
        height: auto !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    /* Focus states for both types */
    .password-field input[type="password"]:focus,
    .password-field input[type="text"]:focus {
        outline: none;
        border-color: #06c3a7;
        box-shadow: 0 0 0 2px rgba(6, 195, 167, 0.1);
    }

    .eye {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        margin: 0;
        height: 20px;
        width: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
    }

    .eye:hover {
        opacity: 0.7;
    }

    .strength {
        width: 100%;
        height: 4px;
        background: #e0e0e0;
        border-radius: 2px;
        margin: 8px 0;
        overflow: hidden;
    }

    .strength-bar {
        height: 100%;
        width: 0%;
        transition: all 0.3s ease;
        border-radius: 2px;
    }

    .strength-label {
        display: block;
        font-size: 12px;
        color: #666;
        margin-bottom: 16px;
    }

    .password-hints {
        list-style: none;
        padding: 0;
        margin: 8px 0 16px 0;
        font-size: 12px;
    }

    .password-hints li {
        margin: 4px 0;
        color: #666;
        transition: color 0.3s ease;
    }

    .password-hints li.valid {
        color: #00C853;
    }

    .warning {
        color: #FF4D4D;
        font-size: 12px;
        display: block;
        margin-top: -8px;
        margin-bottom: 8px;
    }
</style>

<div class="reset-container">
    <!-- Illustration -->
    <div class="illustration">
        <img src="/attendance_tracker/assets/images/Security On-bro.png" alt="Security Illustration" class="image" />
    </div>

    <!-- Form -->
    <div class="form">
        <h1 class="heading">Create Your New Password</h1>
        <p class="text">Enter your new password below to access your account</p>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert <?php echo $_SESSION['success'] ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $_SESSION['message']; ?>
                <?php unset($_SESSION['message'], $_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/attendance_tracker/api/auth/reset-password">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>" />

            <div class="password-field">
                <input id="newPassword" name="newPassword" type="password" placeholder="Enter new password" 
                       maxlength="15" oninput="updatePasswordHints()" required />
                <button type="button" class="eye" onclick="togglePasswordVisibility('newPassword')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" stroke="var(--accent-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="12" r="3" stroke="var(--accent-color)" stroke-width="1.5" />
                    </svg>
                </button>
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

            <div class="password-field">
                <input id="confirmPassword" name="confirmPassword" type="password" placeholder="Confirm password" maxlength="15" required />
                <button type="button" class="eye" onclick="togglePasswordVisibility('confirmPassword')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" stroke="var(--accent-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="12" r="3" stroke="var(--accent-color)" stroke-width="1.5" />
                    </svg>
                </button>
            </div>

            <button type="submit" class="primary" onclick="return validateForm()">
                Reset Password
            </button>

            <a href="/attendance_tracker/" class="secondary">Back to Login</a>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const eyeButton = passwordInput.parentNode.querySelector('.eye');
    
    // Prevent default button behavior
    event.preventDefault();
    
    // Store current value and selection
    const currentValue = passwordInput.value;
    const currentSelectionStart = passwordInput.selectionStart;
    const currentSelectionEnd = passwordInput.selectionEnd;
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeButton.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a22.3 22.3 0 0 1 5.29-4.69" stroke="var(--accent-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M1 1l22 22" stroke="var(--accent-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        `;
    } else {
        passwordInput.type = 'password';
        eyeButton.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" stroke="var(--accent-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="12" cy="12" r="3" stroke="var(--accent-color)" stroke-width="1.5" />
            </svg>
        `;
    }
    
    // Restore value and selection
    passwordInput.value = currentValue;
    passwordInput.setSelectionRange(currentSelectionStart, currentSelectionEnd);
    
    // Force re-application of styles
    passwordInput.style.fontFamily = 'Inter, sans-serif';
    passwordInput.style.fontSize = '14px';
    passwordInput.style.padding = '12px 15px';
    passwordInput.style.paddingRight = '45px';
    passwordInput.style.border = '1px solid #ccc';
    passwordInput.style.borderRadius = '4px';
    passwordInput.style.background = 'white';
    passwordInput.style.lineHeight = '1.5';
    passwordInput.style.width = '100%';
    passwordInput.style.boxSizing = 'border-box';
}

function updatePasswordHints() {
    const password = document.getElementById('newPassword').value;
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
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    const hasUppercase = /[A-Z]/.test(newPassword);
    const hasNumber = /[0-9]/.test(newPassword);
    const hasSpecial = /[^A-Za-z0-9]/.test(newPassword);
    const hasMinLength = newPassword.length >= 8 && newPassword.length <= 15;
    
    const score = [hasUppercase, hasNumber, hasSpecial, hasMinLength].filter(Boolean).length;
    
    if (!hasMinLength) {
        alert("Password must be between 8–15 characters.");
        return false;
    }
    
    if (newPassword !== confirmPassword) {
        alert("Passwords do not match.");
        return false;
    }
    
    if (score < 3) {
        alert("Please make your password stronger before continuing.");
        return false;
    }
    
    return true;
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>