<?php
/**
 * user/Html/Menu.php — Food Menu + Add to Cart
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

// ── ADD TO CART (POST) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['food_id'])) {
    $food_id = (int)$_POST['food_id'];

    // Fetch food from DB
    $stmt = mysqli_prepare($conn, "SELECT id, food_name, price, image FROM food_items WHERE id=? AND availability_status='Available'");
    mysqli_stmt_bind_param($stmt, 'i', $food_id);
    mysqli_stmt_execute($stmt);
    $food = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($food) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        if (isset($_SESSION['cart'][$food_id])) {
            $_SESSION['cart'][$food_id]['qty']++;
        } else {
            $_SESSION['cart'][$food_id] = [
                'id'    => $food['id'],
                'name'  => $food['food_name'],
                'price' => $food['price'],
                'image' => $food['image'],
                'qty'   => 1,
            ];
        }
    }
    header('Location: Cart.php');
    exit;
}

// ── FETCH menu items ──────────────────────────────────────────
$category = $_GET['category'] ?? '';
$search   = trim($_GET['q']   ?? '');

$sql    = "SELECT * FROM food_items WHERE availability_status='Available'";
$params = [];
$types  = '';

if ($category) { $sql .= " AND category = ?"; $types .= 's'; $params[] = $category; }
if ($search)   { $sql .= " AND food_name LIKE ?"; $types .= 's'; $params[] = "%$search%"; }
$sql .= " ORDER BY food_name";

if ($params) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $menu = mysqli_stmt_get_result($stmt);
} else {
    $menu = mysqli_query($conn, $sql);
}

// Categories for dropdown
$cats = mysqli_query($conn, "SELECT DISTINCT category FROM food_items WHERE availability_status='Available' ORDER BY category");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cafeteria Menu</title>
  <link rel="stylesheet" href="../CSS/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<div class="menu-page">
  <?php include 'includes/sidebar.php'; ?>

  <main class="menu-content">
    <header class="menu-header">
      <form method="GET" action="Menu.php" style="display:contents;">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="q" placeholder="Search food items..." value="<?= e($search) ?>" />
        </div>
        <select class="category-select" name="category" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php while ($c = mysqli_fetch_assoc($cats)): ?>
            <option value="<?= e($c['category']) ?>" <?= $category===$c['category']?'selected':'' ?>>
              <?= e($c['category']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </form>
      <button class="notification-btn" type="button"><i class="fa-regular fa-bell"></i></button>
    </header>

    <section class="food-list">
      <?php if (mysqli_num_rows($menu) === 0): ?>
        <p style="padding:20px;color:#888;">No food items found.</p>
      <?php else: ?>
        <?php while ($f = mysqli_fetch_assoc($menu)): ?>
          <article class="food-card">
            <img src="../Images/<?= e($f['image']) ?>" alt="<?= e($f['food_name']) ?>"
                 onerror="this.src='../Images/food.jpg'" />
            <div class="food-card-body">
              <h3><?= e($f['food_name']) ?></h3>
              <?php if (!empty($f['description'])): ?>
                <p class="food-card-desc"><?= e($f['description']) ?></p>
              <?php endif; ?>
              <p class="price">Rs.<?= number_format($f['price'], 2) ?></p>
            </div>
            <form method="POST" action="Menu.php">
              <input type="hidden" name="food_id" value="<?= $f['id'] ?>">
              <button type="submit" class="order-btn" style="width:100%;cursor:pointer;">Add to Cart</button>
            </form>
          </article>
        <?php endwhile; ?>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
