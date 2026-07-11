<?php
/**
 * login.php — Student & Admin Login Page
 */
require_once '../../config.php';
require_once '../../db.php';

redirect_if_logged_in(); // Send already-logged-in users away

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['username'] ?? '');   // field is named "username" in the HTML form
    $password = $_POST['password'] ?? '';

    // Basic validation
    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        // Look up user by email
        $stmt = mysqli_prepare($conn,
            'SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            // Credentials OK — store session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            // Redirect by role
            if ($user['role'] === 'admin') {
                header('Location: ../../admin/index.php');
            } else {
                header('Location: Dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cafeteria Pre-Order System</title>
  <link rel="stylesheet" href="../CSS/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <main class="login-page">
    <section class="image-frame">
      <img src="../Images/food.jpg" alt="Cafeteria food image" />
    </section>

    <section class="login-frame">
      <div class="login-card">
        <img class="logo" src="../Images/logo.png" alt="Cafeteria logo" />
        <h3>Uva Wellassa University</h3>
        <h1>Cafeteria<br/>Pre-Order System</h1>
        <p class="subtitle">Please sign in to continue</p>

        <?php if ($error): ?>
          <p id="message" class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php">
          <label for="username">Email / Username</label>
          <input type="text" id="username" name="username"
                 placeholder="Enter your email or username"
                 autocomplete="username"
                 value="<?= e($_POST['username'] ?? '') ?>" />

          <div class="password-heading">
            <label for="password">Password</label>
            <a href="#">Forgot Password?</a>
          </div>

          <div class="password-field">
            <input type="password" id="password" name="password"
                   placeholder="Enter your password" />
            <button type="button" id="togglePassword" class="eye-btn" aria-label="Show password">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>

          <label class="remember">
            <input type="checkbox" id="remember" />
            <span>Remember me</span>
          </label>

          <button class="login-button" type="submit">Login</button>

          <p class="register-text">
            Don't have an account?
            <a href="Register.php">Register Here</a>
          </p>
        </form>
      </div>
    </section>
  </main>

  <script>
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function () {
      var pwd  = document.getElementById('password');
      var icon = this.querySelector('i');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        pwd.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });
  </script>
</body>
</html>
