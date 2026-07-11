<?php
/**
 * admin/users.php — View & Delete Students
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

$msg = '';
$err = '';

// ── ADD ADMIN (POST) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    $name = trim($_POST['adminName'] ?? '');
    $email = trim($_POST['adminEmail'] ?? '');
    $password = $_POST['adminPassword'] ?? '';
    
    if (!$name || !$email || !$password) {
        header('Location: users.php?msg=missing_fields');
        exit;
    }
    
    // Check email uniqueness
    $chk = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($chk, 's', $email);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    $exists = mysqli_stmt_num_rows($chk) > 0;
    mysqli_stmt_close($chk);
    
    if ($exists) {
        header('Location: users.php?msg=email_exists');
        exit;
    }
    
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $ins = mysqli_prepare($conn, 'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "admin")');
    mysqli_stmt_bind_param($ins, 'sss', $name, $email, $hashed);
    mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);
    
    header('Location: users.php?msg=admin_added');
    exit;
}

// ── DELETE USER ───────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Prevent self-deletion
    if ($del_id !== (int)$_SESSION['user_id']) {
        $del = mysqli_prepare($conn, 'DELETE FROM users WHERE id=? AND role="student"');
        mysqli_stmt_bind_param($del, 'i', $del_id);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
    }
    header('Location: users.php?msg=deleted');
    exit;
}

$flash_type = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted')        { $msg = 'Student deleted successfully.';          $flash_type = 'success'; }
    if ($_GET['msg'] === 'admin_added')    { $msg = 'Admin account created successfully!';    $flash_type = 'success'; }
    if ($_GET['msg'] === 'email_exists')   { $err = 'Error: Email address already registered.'; $flash_type = 'error';   }
    if ($_GET['msg'] === 'missing_fields') { $err = 'Error: All fields are required.';          $flash_type = 'error';   }
}

// ── FETCH students ────────────────────────────────────────────
$result = mysqli_query($conn,
    "SELECT id, name, email, student_id, phone, created_at FROM users WHERE role='student' ORDER BY created_at DESC"
);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Users</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
</head>
<body data-flash-type="<?= e($flash_type) ?>" data-flash-msg="<?= e($msg ?: $err) ?>">
<div class="page">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="users-page-header" style="display:flex; justify-content:space-between; align-items:center; width:88%; margin:0 auto;">
      <div><h1>Students</h1><p>Manage registered student accounts</p></div>
      <button id="addAdminBtn" style="cursor:pointer; background-color:#7047f2; color:white; border:none; padding:10px 18px; border-radius:5px; font-weight:bold;">+ Add Admin</button>
    </div>



    <div class="users-table-section" style="width:88%;margin:20px auto;">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Name</th><th>Email</th>
              <th>Student ID</th><th>Phone</th><th>Registered</th><th>Action</th>
            </tr>
          </thead>
          <tbody id="usersTableBody">
            <?php if (mysqli_num_rows($result) === 0): ?>
              <tr><td colspan="7">
                <div class="empty-state">
                  <div class="empty-state-icon">👥</div>
                  <div class="empty-state-title">No students registered yet</div>
                  <div class="empty-state-text">Students will appear here after they register through the student portal.</div>
                </div>
              </td></tr>
            <?php else: ?>
              <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                  <td><?= $row['id'] ?></td>
                  <td><?= e($row['name']) ?></td>
                  <td><?= e($row['email']) ?></td>
                  <td><?= e($row['student_id'] ?? '--') ?></td>
                  <td><?= e($row['phone'] ?? '--') ?></td>
                  <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                  <td>
                    <div class="user-actions">
                      <button class="delete-btn user-delete-btn" onclick="confirmDialog({icon:'⚠️',title:'Delete Student',message:'Delete student &quot;<?= e(addslashes($row['name'])) ?>&quot;? Their orders and payments will also be removed.',okText:'Yes, Delete',onConfirm:function(){window.location='users.php?delete=<?= $row['id'] ?>';}})"><img src="images/trash.png" alt="Delete"></button>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Add Admin Modal -->
<div class="food-modal" id="adminModal">
  <div class="food-modal-box">
    <h2>Add Admin Account</h2>
    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="add_admin">
      <label style="font-weight:bold; font-size:12px; margin-bottom:4px; display:block;">Full Name</label>
      <input type="text" name="adminName" placeholder="Enter full name" required>
      <label style="font-weight:bold; font-size:12px; margin-bottom:4px; display:block;">Email</label>
      <input type="email" name="adminEmail" placeholder="Enter email" required>
      <label style="font-weight:bold; font-size:12px; margin-bottom:4px; display:block;">Password</label>
      <input type="password" name="adminPassword" placeholder="Enter password" required>
      <div class="modal-buttons">
        <button type="button" id="closeAdminModal">Cancel</button>
        <button type="submit">Create Admin</button>
      </div>
    </form>
  </div>
</div>

<script src="script.js?v=<?= time() ?>"></script>
<script>
  document.getElementById('addAdminBtn').onclick   = () => document.getElementById('adminModal').classList.add('show');
  document.getElementById('closeAdminModal').onclick = () => document.getElementById('adminModal').classList.remove('show');
</script>
</body>
</html>
