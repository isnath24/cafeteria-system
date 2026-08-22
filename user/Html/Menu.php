<?php

/**
 * user/Html/Menu.php — Food Menu + Add to Cart
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

// ── Determine current meal period based on server time ─────────
// Breakfast: 6:00–11:59, Lunch: 12:00–17:59, Dinner: 18:00–20:59, else closed
$meal_period = current_meal_period();

// ── ADD TO CART (POST) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['food_id'])) {
  $food_id = (int)$_POST['food_id'];

  // Block entirely if cafeteria is closed, or if the item isn't part of the current meal period
  if ($meal_period) {
    $stmt = mysqli_prepare($conn, "SELECT id, food_name, price, image FROM food_items WHERE id=? AND availability_status='Available' AND FIND_IN_SET(?, category) > 0");
    mysqli_stmt_bind_param($stmt, 'is', $food_id, $meal_period);
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
  }
  header('Location: Cart.php');
  exit;
}

// ── FETCH menu items for the current meal period only ──────────
$search = trim($_GET['q'] ?? '');
$menu   = null;

if ($meal_period) {
  $sql    = "SELECT * FROM food_items WHERE availability_status='Available' AND FIND_IN_SET(?, category) > 0";
  $params = [$meal_period];
  $types  = 's';

  if ($search) {
    $sql .= " AND food_name LIKE ?";
    $types .= 's';
    $params[] = "%$search%";
  }
  $sql .= " ORDER BY food_name";

  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, $types, ...$params);
  mysqli_stmt_execute($stmt);
  $menu = mysqli_stmt_get_result($stmt);
}

// Meal period display info (label + serving window text)
$period_info = [
  'Breakfast' => ['label' => 'Breakfast', 'window' => '6:00 AM – 12:00 PM'],
  'Lunch'     => ['label' => 'Lunch',     'window' => '12:00 PM – 6:00 PM'],
  'Dinner'    => ['label' => 'Dinner',    'window' => '6:00 PM – 9:00 PM'],
];
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
        <?php if ($meal_period): ?>
          <form method="GET" action="Menu.php" style="display:contents;">
            <div class="search-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" name="q" placeholder="Search food items..." value="<?= e($search) ?>" />
            </div>
          </form>
        <?php else: ?>
          <div></div>
        <?php endif; ?>
        <button class="notification-btn" type="button"><i class="fa-regular fa-bell"></i></button>
      </header>

      <?php if ($meal_period): ?>
        <div style="display:flex; align-items:center; gap:10px; margin:16px 0 4px; padding:10px 16px; background:#f5f3ff; border:1px solid #ddd6fe; border-radius:10px; width:fit-content;">
          <span style="font-weight:700; color:#7047f2; font-size:14px;">Now serving: <?= e($period_info[$meal_period]['label']) ?></span>
          <span style="color:#6b7280; font-size:12.5px;">(<?= e($period_info[$meal_period]['window']) ?>)</span>
        </div>
      <?php endif; ?>

      <section class="food-list">
        <?php if (!$meal_period): ?>
          <div style="padding:60px 20px; text-align:center; color:#6b7280;">
            <i class="fa-regular fa-clock" style="font-size:34px; color:#a78bfa; margin-bottom:14px; display:block;"></i>
            <h2 style="font-size:18px; color:#374151; margin-bottom:6px;">Cafeteria is currently closed</h2>
            <p style="font-size:13.5px;">Ordering is available during:</p>
            <p style="font-size:13.5px; margin-top:6px;">
              🌅 Breakfast: 6:00 AM – 12:00 PM &nbsp;|&nbsp;
              🍛 Lunch: 12:00 PM – 6:00 PM &nbsp;|&nbsp;
              🌙 Dinner: 6:00 PM – 9:00 PM
            </p>
          </div>
        <?php elseif (mysqli_num_rows($menu) === 0): ?>
          <p style="padding:20px;color:#888;">No <?= e(strtolower($period_info[$meal_period]['label'])) ?> items found.</p>
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