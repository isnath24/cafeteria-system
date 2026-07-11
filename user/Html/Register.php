<?php
/**
 * Register.php — New Student Registration
 */
require_once '../../config.php';
require_once '../../db.php';

redirect_if_logged_in();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['fullName']   ?? '');
    $email      = trim($_POST['email']      ?? '');
    $student_id = trim($_POST['studentId']  ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $password   = $_POST['registerPassword']  ?? '';
    $confirm    = $_POST['confirmPassword']    ?? '';

    // Validation
    if (!$name || !$email || !$student_id || !$phone || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address (e.g. name@example.com).';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Phone number must be exactly 10 digits.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check email uniqueness
        $chk = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 's', $email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);

        if (mysqli_stmt_num_rows($chk) > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins    = mysqli_prepare($conn,
                'INSERT INTO users (name, email, password, student_id, phone, role) VALUES (?, ?, ?, ?, ?, "student")'
            );
            mysqli_stmt_bind_param($ins, 'sssss', $name, $email, $hashed, $student_id, $phone);

            if (mysqli_stmt_execute($ins)) {
                $success = 'Registration successful! Redirecting to login…';
                header('Refresh: 2; URL=login.php');
            } else {
                $error = 'Registration failed. Please try again.';
            }
            mysqli_stmt_close($ins);
        }
        mysqli_stmt_close($chk);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create New Account</title>
  <link rel="stylesheet" href="../CSS/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <main class="register-page">
    <section class="register-image-frame">
      <img src="../Images/register-food.jpg" alt="Cafeteria meal image" />
    </section>

    <section class="register-frame">
      <div class="register-box">
        <h1>Create New Account</h1>
        <p class="register-subtitle">Please fill in the details to register</p>

        <?php if ($error):   ?><p id="registerMessage" class="error"><?= e($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p id="registerMessage" class="success"><?= e($success) ?></p><?php endif; ?>

        <form id="registerForm" method="POST" action="Register.php">
          <label for="fullName">Full Name</label>
          <input type="text" id="fullName" name="fullName"
                 placeholder="Enter your full name"
                 value="<?= e($_POST['fullName'] ?? '') ?>" />

          <label for="email">Email</label>
          <input type="email" id="email" name="email"
                 placeholder="Enter your email"
                 value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" />
          <span class="field-hint" id="emailHint"></span>

          <label for="studentId">Student ID</label>
          <input type="text" id="studentId" name="studentId"
                 placeholder="Enter your student ID"
                 value="<?= e($_POST['studentId'] ?? '') ?>" />

          <label for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone"
                 placeholder="Enter your phone number (10 digits)"
                 maxlength="10"
                 value="<?= e($_POST['phone'] ?? '') ?>" />
          <span class="field-hint" id="phoneHint"></span>

          <label for="registerPassword">Password</label>
          <div class="register-password-box">
            <input type="password" id="registerPassword" name="registerPassword"
                   placeholder="Create a password" />
            <button type="button" class="register-eye-btn" data-target="registerPassword" aria-label="Toggle password">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>

          <label for="confirmPassword">Confirm Password</label>
          <div class="register-password-box">
            <input type="password" id="confirmPassword" name="confirmPassword"
                   placeholder="Confirm your password" />
            <button type="button" class="register-eye-btn" data-target="confirmPassword" aria-label="Toggle password">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>

          <button type="submit" class="register-btn">Register</button>

          <p class="login-link">
            Already have an account?
            <a href="login.php">Login Here</a>
          </p>
        </form>
      </div>
    </section>
  </main>

  <style>
    .field-hint {
      display: block;
      font-size: 12px;
      margin: -6px 0 10px;
      min-height: 16px;
      padding-left: 2px;
    }
    .field-hint.hint-error   { color: #dc2626; }
    .field-hint.hint-success { color: #16a34a; }
    input.input-error   { border-color: #dc2626 !important; }
    input.input-success { border-color: #16a34a !important; }
  </style>

  <script>
    /* ── Eye toggle ── */
    document.querySelectorAll('.register-eye-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var target = document.getElementById(btn.getAttribute('data-target'));
        var icon = btn.querySelector('i');
        if (target.type === 'password') {
          target.type = 'text';
          icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
          target.type = 'password';
          icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
      });
    });

    /* ── Real-time field validation ── */
    var emailInput = document.getElementById('email');
    var emailHint  = document.getElementById('emailHint');
    var phoneInput = document.getElementById('phone');
    var phoneHint  = document.getElementById('phoneHint');

    function validateEmail(val) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    }
    function validatePhone(val) {
      return /^[0-9]{10}$/.test(val);
    }

    emailInput.addEventListener('input', function() {
      var val = this.value.trim();
      if (!val) {
        setHint(emailInput, emailHint, '', '');
      } else if (validateEmail(val)) {
        setHint(emailInput, emailHint, 'success', '✓ Valid email address');
      } else {
        setHint(emailInput, emailHint, 'error', '✗ Enter a valid email (e.g. name@example.com)');
      }
    });

    phoneInput.addEventListener('input', function() {
      /* Strip non-digits as user types */
      this.value = this.value.replace(/[^0-9]/g, '');
      var val = this.value;
      if (!val) {
        setHint(phoneInput, phoneHint, '', '');
      } else if (validatePhone(val)) {
        setHint(phoneInput, phoneHint, 'success', '✓ Valid phone number');
      } else {
        var remaining = 10 - val.length;
        setHint(phoneInput, phoneHint, 'error',
          remaining > 0
            ? '✗ ' + remaining + ' more digit' + (remaining === 1 ? '' : 's') + ' needed'
            : '✗ Phone number must be exactly 10 digits');
      }
    });

    function setHint(input, hint, type, msg) {
      hint.textContent = msg;
      hint.className = 'field-hint' + (type ? ' hint-' + type : '');
      input.classList.remove('input-error', 'input-success');
      if (type) input.classList.add('input-' + type);
    }

    /* ── Block submission if fields are invalid ── */
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      var emailOk = validateEmail(emailInput.value.trim());
      var phoneOk = validatePhone(phoneInput.value.trim());
      if (!emailOk) {
        setHint(emailInput, emailHint, 'error', '✗ Enter a valid email address');
        emailInput.focus();
        e.preventDefault();
        return;
      }
      if (!phoneOk) {
        setHint(phoneInput, phoneHint, 'error', '✗ Phone number must be exactly 10 digits');
        phoneInput.focus();
        e.preventDefault();
      }
    });
  </script>
</body>
</html>
