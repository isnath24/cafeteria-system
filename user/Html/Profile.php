<?php
/**
 * user/Html/Profile.php — View & Edit Student Profile
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

$user_id = $_SESSION['user_id'];
$msg     = '';
$err     = '';

// ── UPDATE PROFILE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_profile') {
        $name       = trim($_POST['fullName']   ?? '');
        $email      = trim($_POST['email']      ?? '');
        $student_id = trim($_POST['studentId']  ?? '');
        $phone      = trim($_POST['phone']      ?? '');

        if (!$name || !$email) {
            $err = 'Name and Email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid email address (e.g. name@example.com).';
        } elseif (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
            $err = 'Phone number must be exactly 10 digits.';
        } else {
            $upd = mysqli_prepare($conn,
                'UPDATE users SET name=?, email=?, student_id=?, phone=? WHERE id=?'
            );
            mysqli_stmt_bind_param($upd, 'ssssi', $name, $email, $student_id, $phone, $user_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $_SESSION['name'] = $name;
            $msg = 'Profile updated successfully!';
        }
    }

    if ($_POST['action'] === 'change_password') {
        $current_pwd = $_POST['currentPassword'] ?? '';
        $new_pwd     = $_POST['newPassword']     ?? '';
        $confirm_pwd = $_POST['confirmNewPassword'] ?? '';

        // Fetch current hash
        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id=$user_id"));

        if (!password_verify($current_pwd, $row['password'])) {
            $err = 'Current password is incorrect.';
        } elseif (strlen($new_pwd) < 6) {
            $err = 'New password must be at least 6 characters.';
        } elseif ($new_pwd !== $confirm_pwd) {
            $err = 'New passwords do not match.';
        } else {
            $hashed = password_hash($new_pwd, PASSWORD_DEFAULT);
            $upd    = mysqli_prepare($conn, 'UPDATE users SET password=? WHERE id=?');
            mysqli_stmt_bind_param($upd, 'si', $hashed, $user_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $msg = 'Password updated successfully!';
        }
    }
}

// ── Fetch fresh user data ──────────────────────────────────────
$user = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT name, email, student_id, phone FROM users WHERE id=$user_id"
));

// Stats
$total_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE user_id=$user_id"))['n'];
$total_spent  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(p.amount),0) AS n FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.user_id=$user_id AND p.payment_status='Paid'"))['n'];
$completed    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE user_id=$user_id AND order_status='Completed'"))['n'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile</title>
  <link rel="stylesheet" href="../CSS/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<div class="dashboard-page">
  <?php include 'includes/sidebar.php'; ?>

  <main class="main-content">
    <header class="dashboard-header">
      <div><h1>My Profile</h1><p>Manage your personal information and account settings</p></div>
      <div class="header-actions">
        <button class="icon-btn" type="button"><i class="fa-regular fa-bell"></i></button>
        <div class="user-chip">
          <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
          <span><?= e($_SESSION['name']) ?></span>
        </div>
      </div>
    </header>

    <?php if ($msg): ?><p style="color:#16a34a;font-weight:700;padding:4px 0;"><?= e($msg) ?></p><?php endif; ?>
    <?php if ($err): ?><p style="color:#c00;font-weight:700;padding:4px 0;"><?= e($err) ?></p><?php endif; ?>

    <div class="profile-layout">

      <!-- Left Column -->
      <aside class="profile-card-col">
        <div class="profile-avatar-card">
          <div class="profile-avatar-circle">
            <img src="../Images/profile.jpg" class="profile-avatar-img" id="profileAvatarImg"
                 onerror="this.style.display='none'" alt="Avatar" />
          </div>
          <h3 class="profile-name"><?= e($user['name']) ?></h3>
          <p class="profile-student-id"><?= e($user['student_id'] ?? '--') ?></p>
          <p class="profile-role">Student</p>
        </div>

        <div class="profile-quick-stats">
          <div class="pqs-row">
            <span class="pqs-label"><i class="fa-solid fa-bag-shopping"></i> Total Orders</span>
            <span class="pqs-value"><?= $total_orders ?></span>
          </div>
          <div class="pqs-row">
            <span class="pqs-label"><i class="fa-solid fa-money-bill-wave"></i> Total Spent</span>
            <span class="pqs-value">Rs.<?= number_format($total_spent, 2) ?></span>
          </div>
          <div class="pqs-row">
            <span class="pqs-label"><i class="fa-solid fa-circle-check"></i> Completed</span>
            <span class="pqs-value"><?= $completed ?></span>
          </div>
        </div>
      </aside>

      <!-- Right Column -->
      <div class="profile-forms-col">

        <!-- Personal Info -->
        <section class="profile-section-card" id="editProfileSection">
          <div class="profile-section-header">
            <h2><i class="fa-solid fa-user-pen"></i> Personal Information</h2>
            <button class="btn-edit-toggle" id="editToggleBtn" type="button">
              <i class="fa-solid fa-pen"></i> Edit
            </button>
          </div>

          <form id="profileForm" method="POST" action="Profile.php">
            <input type="hidden" name="action" value="update_profile">
            <div class="profile-form-grid">
              <div class="profile-form-group">
                <label for="fullNameField">Full Name</label>
                <input type="text" id="fullNameField" name="fullName"
                       value="<?= e($user['name']) ?>" disabled />
              </div>
              <div class="profile-form-group">
                <label for="studentIdField">Student ID</label>
                <input type="text" id="studentIdField" name="studentId"
                       value="<?= e($user['student_id'] ?? '') ?>" disabled />
              </div>
              <div class="profile-form-group">
                <label for="emailField">Email Address</label>
                <input type="email" id="emailField" name="email"
                       value="<?= e($user['email']) ?>" disabled />
                <span class="field-hint" id="profileEmailHint"></span>
              </div>
              <div class="profile-form-group">
                <label for="phoneField">Phone Number</label>
                <input type="tel" id="phoneField" name="phone"
                       maxlength="10"
                       value="<?= e($user['phone'] ?? '') ?>" disabled />
                <span class="field-hint" id="profilePhoneHint"></span>
              </div>
            </div>

            <div class="profile-form-actions" id="profileFormActions" style="display:none;">
              <button type="submit" class="btn-save-profile">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
              </button>
              <button type="button" class="btn-cancel-edit" id="cancelEditBtn">Cancel</button>
            </div>
          </form>
        </section>

        <!-- Change Password -->
        <section class="profile-section-card">
          <div class="profile-section-header">
            <h2><i class="fa-solid fa-lock"></i> Change Password</h2>
          </div>
          <form id="passwordForm" method="POST" action="Profile.php">
            <input type="hidden" name="action" value="change_password">
            <div class="profile-form-grid">
              <div class="profile-form-group profile-form-full">
                <label for="currentPassword">Current Password</label>
                <div class="profile-pwd-field">
                  <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter current password" />
                  <button type="button" class="profile-eye-btn" data-target="currentPassword" aria-label="Toggle">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="profile-form-group">
                <label for="newPassword">New Password</label>
                <div class="profile-pwd-field">
                  <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password" />
                  <button type="button" class="profile-eye-btn" data-target="newPassword" aria-label="Toggle">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="profile-form-group">
                <label for="confirmNewPassword">Confirm New Password</label>
                <div class="profile-pwd-field">
                  <input type="password" id="confirmNewPassword" name="confirmNewPassword" placeholder="Confirm new password" />
                  <button type="button" class="profile-eye-btn" data-target="confirmNewPassword" aria-label="Toggle">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
              </div>
            </div>
            <div class="profile-form-actions">
              <button type="submit" class="btn-save-profile">
                <i class="fa-solid fa-key"></i> Update Password
              </button>
            </div>
          </form>
        </section>

      </div>
    </div>
  </main>
</div>

<style>
  .field-hint {
    display: block;
    font-size: 12px;
    margin-top: 4px;
    min-height: 16px;
  }
  .field-hint.hint-error   { color: #dc2626; }
  .field-hint.hint-success { color: #16a34a; }
  input.input-error   { border-color: #dc2626 !important; box-shadow: 0 0 0 2px rgba(220,38,38,0.12) !important; }
  input.input-success { border-color: #16a34a !important; box-shadow: 0 0 0 2px rgba(22,163,74,0.12)  !important; }
</style>

<script>
  // Edit toggle
  var editBtn     = document.getElementById('editToggleBtn');
  var cancelBtn   = document.getElementById('cancelEditBtn');
  var formActions = document.getElementById('profileFormActions');
  var inputs      = document.querySelectorAll('#profileForm input');

  editBtn.addEventListener('click', function() {
    inputs.forEach(function(inp) { inp.disabled = false; });
    formActions.style.display = 'flex';
    editBtn.style.display     = 'none';
  });

  cancelBtn.addEventListener('click', function() {
    inputs.forEach(function(inp) { inp.disabled = true; });
    formActions.style.display = 'none';
    editBtn.style.display     = '';
    // Clear hints on cancel
    clearHint(emailPF, profileEmailHint);
    clearHint(phonePF, profilePhoneHint);
  });

  // Password eye toggle
  document.querySelectorAll('.profile-eye-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var target = document.getElementById(btn.getAttribute('data-target'));
      var icon   = btn.querySelector('i');
      if (target.type === 'password') {
        target.type = 'text';
        icon.classList.replace('fa-eye','fa-eye-slash');
      } else {
        target.type = 'password';
        icon.classList.replace('fa-eye-slash','fa-eye');
      }
    });
  });

  /* ── Real-time validation for profile form ── */
  var emailPF          = document.getElementById('emailField');
  var phonePF          = document.getElementById('phoneField');
  var profileEmailHint = document.getElementById('profileEmailHint');
  var profilePhoneHint = document.getElementById('profilePhoneHint');

  function validateEmail(val) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
  }
  function validatePhone(val) {
    return /^[0-9]{10}$/.test(val);
  }
  function setHint(input, hint, type, msg) {
    hint.textContent = msg;
    hint.className = 'field-hint' + (type ? ' hint-' + type : '');
    input.classList.remove('input-error', 'input-success');
    if (type) input.classList.add('input-' + type);
  }
  function clearHint(input, hint) {
    setHint(input, hint, '', '');
  }

  emailPF.addEventListener('input', function() {
    if (this.disabled) return;
    var val = this.value.trim();
    if (!val) { clearHint(emailPF, profileEmailHint); return; }
    if (validateEmail(val)) {
      setHint(emailPF, profileEmailHint, 'success', '\u2713 Valid email address');
    } else {
      setHint(emailPF, profileEmailHint, 'error', '\u2717 Enter a valid email (e.g. name@example.com)');
    }
  });

  phonePF.addEventListener('input', function() {
    if (this.disabled) return;
    this.value = this.value.replace(/[^0-9]/g, '');
    var val = this.value;
    if (!val) { clearHint(phonePF, profilePhoneHint); return; }
    if (validatePhone(val)) {
      setHint(phonePF, profilePhoneHint, 'success', '\u2713 Valid phone number');
    } else {
      var remaining = 10 - val.length;
      setHint(phonePF, profilePhoneHint, 'error',
        remaining > 0
          ? '\u2717 ' + remaining + ' more digit' + (remaining === 1 ? '' : 's') + ' needed'
          : '\u2717 Phone number must be exactly 10 digits');
    }
  });

  /* ── Block submission if invalid ── */
  document.getElementById('profileForm').addEventListener('submit', function(e) {
    var emailOk = validateEmail(emailPF.value.trim());
    var phoneVal = phonePF.value.trim();
    var phoneOk  = !phoneVal || validatePhone(phoneVal); // phone is optional in profile
    if (!emailOk) {
      setHint(emailPF, profileEmailHint, 'error', '\u2717 Enter a valid email address');
      emailPF.focus();
      e.preventDefault();
      return;
    }
    if (!phoneOk) {
      setHint(phonePF, profilePhoneHint, 'error', '\u2717 Phone number must be exactly 10 digits');
      phonePF.focus();
      e.preventDefault();
    }
  });
</script>
</body>
</html>
