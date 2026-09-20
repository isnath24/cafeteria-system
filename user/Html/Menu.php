<?php

/**
 * user/Html/Menu.php — Food Menu + Add to Cart
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

// ── Determine current meal period ─────────────────────────────
// current_meal_period() is defined in config.php
$meal_period = current_meal_period();


// ── ADD TO CART (POST) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['food_id'])) {

  $food_id = (int)$_POST['food_id'];

  // Only allow adding to cart if cafeteria is open and item matches current meal period
  if ($meal_period) {

    $stmt = mysqli_prepare(
      $conn,
      "SELECT id, food_name, price, image
       FROM food_items
       WHERE id=?
       AND availability_status='Available'
       AND FIND_IN_SET(?, category) > 0"
    );

    mysqli_stmt_bind_param($stmt, 'is', $food_id, $meal_period);
    mysqli_stmt_execute($stmt);

    $food = mysqli_fetch_assoc(
      mysqli_stmt_get_result($stmt)
    );

    if ($food) {

      if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
      }

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


// ── 1. FETCH ALL MENU ITEMS ───────────────────────────────────
$search   = trim($_GET['q'] ?? '');

$all_sql  = "SELECT * FROM food_items WHERE availability_status='Available'";
$all_params = [];
$all_types  = '';

if ($search) {
  $all_sql .= " AND food_name LIKE ?";
  $all_types .= 's';
  $all_params[] = "%$search%";
}

$all_sql .= " ORDER BY food_name";

$stmt_all = mysqli_prepare($conn, $all_sql);

if ($search) {
  mysqli_stmt_bind_param($stmt_all, $all_types, ...$all_params);
}

mysqli_stmt_execute($stmt_all);
$all_menu = mysqli_stmt_get_result($stmt_all);


// ── 2. FETCH MEAL PERIOD ITEMS (NOW SERVING) ─────────────────
$now_serving_menu = null;

if ($meal_period) {

  $now_sql = "
    SELECT *
    FROM food_items
    WHERE availability_status='Available'
    AND FIND_IN_SET(?, category) > 0
  ";

  $now_params = [$meal_period];
  $now_types  = 's';

  if ($search) {
    $now_sql .= " AND food_name LIKE ?";
    $now_types .= 's';
    $now_params[] = "%$search%";
  }

  $now_sql .= " ORDER BY food_name";

  $stmt_now = mysqli_prepare($conn, $now_sql);

  mysqli_stmt_bind_param(
    $stmt_now,
    $now_types,
    ...$now_params
  );

  mysqli_stmt_execute($stmt_now);

  $now_serving_menu = mysqli_stmt_get_result($stmt_now);
}


// ── Meal period display information ───────────────────────────
$period_info = [

  'Breakfast' => [
    'label'  => 'Breakfast',
    'window' => '6:00 AM – 12:00 PM'
  ],

  'Lunch' => [
    'label'  => 'Lunch',
    'window' => '12:00 PM – 6:00 PM'
  ],

  'Dinner' => [
    'label'  => 'Dinner',
    'window' => '6:00 PM – 9:00 PM'
  ],

];

?>

<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="UTF-8" />

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0" />

  <title>Cafeteria Menu</title>

  <link
    rel="stylesheet"
    href="../CSS/style.css" />

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

</head>


<body>

  <div class="menu-page">

    <?php include 'includes/sidebar.php'; ?>


    <main class="menu-content">


      <!-- ── HEADER & SEARCH ──────────────────────────────── -->

      <header class="menu-header">

        <form
          method="GET"
          action="Menu.php"
          style="display:contents;">

          <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
              type="text"
              name="q"
              placeholder="Search food items..."
              value="<?= e($search) ?>" />

          </div>

        </form>

        <button
          class="notification-btn"
          type="button">

          <i class="fa-regular fa-bell"></i>

        </button>

      </header>


      <!-- =================================================== -->
      <!-- SECTION 1: ALL MENU ITEMS (VIEW / BROWSING ONLY)    -->
      <!-- =================================================== -->

      <div style="margin:20px 0 10px;">
        <h2 style="font-size:20px; font-weight:700; color:#111827; margin:0 0 4px;">
          All Menu Items
        </h2>
        <p style="font-size:13.5px; color:#6b7280; margin:0;">
          Browse full menu catalog (Only items under "Now Serving" can be ordered right now)
        </p>
      </div>

      <section class="food-list">

        <?php if (mysqli_num_rows($all_menu) === 0): ?>

          <p style="padding:20px; color:#888;">
            No menu items found.
          </p>

        <?php else: ?>

          <?php while ($f = mysqli_fetch_assoc($all_menu)): ?>

            <?php
            // Check if item belongs to current active meal period
            $categories = array_map('trim', explode(',', $f['category'] ?? ''));
            $is_orderable = $meal_period && in_array($meal_period, $categories);
            ?>

            <article class="food-card">

              <!-- FOOD IMAGE -->

              <img
                src="../Images/<?= e($f['image']) ?>"
                alt="<?= e($f['food_name']) ?>"
                onerror="this.src='../Images/food.jpg'" />


              <!-- FOOD DETAILS -->

              <div class="food-card-body">

                <h3>
                  <?= e($f['food_name']) ?>
                </h3>

                <?php if (!empty($f['description'])): ?>

                  <p class="food-card-desc">

                    <?= e($f['description']) ?>

                  </p>

                <?php endif; ?>

                <p class="price">

                  Rs.<?= number_format($f['price'], 2) ?>

                </p>

              </div>


              <!-- ADD TO CART / ORDER STATUS -->

              <?php if ($is_orderable): ?>

                <form
                  method="POST"
                  action="Menu.php">

                  <input
                    type="hidden"
                    name="food_id"
                    value="<?= $f['id'] ?>">

                  <button
                    type="submit"
                    class="order-btn"
                    style="
                      width:100%;
                      cursor:pointer;
                    ">

                    Add to Cart

                  </button>

                </form>

              <?php else: ?>

                <button
                  type="button"
                  class="order-btn"
                  disabled
                  style="
                    width:100%;
                    background:#d1d5db !important;
                    color:#6b7280 !important;
                    cursor:not-allowed;
                    border:none;
                  ">

                  Not Serving Now

                </button>

              <?php endif; ?>

            </article>

          <?php endwhile; ?>

        <?php endif; ?>

      </section>


      <!-- ── DIVIDER ───────────────────────────────────────── -->

      <hr style="border:0; border-top:1px solid #e5e7eb; margin:35px 0 25px;" />


      <!-- =================================================== -->
      <!-- SECTION 2: NOW SERVING MEAL PERIOD (ORDERABLE)      -->
      <!-- =================================================== -->

      <!-- CURRENT MEAL PERIOD BADGE / HEADER -->

      <?php if ($meal_period): ?>

        <div
          style="
            display:flex;
            align-items:center;
            gap:10px;
            margin:0 0 16px;
            padding:10px 16px;
            background:#f5f3ff;
            border:1px solid #ddd6fe;
            border-radius:10px;
            width:fit-content;
          ">

          <span
            style="
              font-weight:700;
              color:#7047f2;
              font-size:14px;
            ">

            Now serving:
            <?= e($period_info[$meal_period]['label']) ?>

          </span>

          <span
            style="
              color:#6b7280;
              font-size:12.5px;
            ">

            (
            <?= e($period_info[$meal_period]['window']) ?>
            )

          </span>

        </div>

      <?php endif; ?>


      <section class="food-list">

        <!-- CAFETERIA CLOSED VIEW -->

        <?php if (!$meal_period): ?>

          <div
            style="
              padding:40px 20px;
              text-align:center;
              color:#6b7280;
              width:100%;
            ">

            <i
              class="fa-regular fa-clock"
              style="
                font-size:34px;
                color:#a78bfa;
                margin-bottom:14px;
                display:block;
              "></i>

            <h2
              style="
                font-size:18px;
                color:#374151;
                margin-bottom:6px;
              ">

              Cafeteria is currently closed

            </h2>

            <p style="font-size:13.5px;">

              Ordering for specific meal periods is available during:

            </p>

            <p
              style="
                font-size:13.5px;
                margin-top:6px;
              ">

              🌅 Breakfast: 6:00 AM – 12:00 PM

              &nbsp;|&nbsp;

              🍛 Lunch: 12:00 PM – 6:00 PM

              &nbsp;|&nbsp;

              🌙 Dinner: 6:00 PM – 9:00 PM

            </p>

          </div>


          <!-- NO MEAL PERIOD ITEMS FOUND -->

        <?php elseif (mysqli_num_rows($now_serving_menu) === 0): ?>

          <p
            style="
              padding:20px;
              color:#888;
            ">

            No
            <?= e(strtolower($period_info[$meal_period]['label'])) ?>
            items found.

          </p>


          <!-- DISPLAY NOW SERVING ITEMS -->

        <?php else: ?>

          <?php while ($f = mysqli_fetch_assoc($now_serving_menu)): ?>

            <article class="food-card">

              <!-- FOOD IMAGE -->

              <img
                src="../Images/<?= e($f['image']) ?>"
                alt="<?= e($f['food_name']) ?>"
                onerror="this.src='../Images/food.jpg'" />

              <!-- FOOD DETAILS -->

              <div class="food-card-body">

                <h3>
                  <?= e($f['food_name']) ?>
                </h3>

                <?php if (!empty($f['description'])): ?>

                  <p class="food-card-desc">

                    <?= e($f['description']) ?>

                  </p>

                <?php endif; ?>

                <p class="price">

                  Rs.<?= number_format($f['price'], 2) ?>

                </p>

              </div>

              <!-- ADD TO CART -->

              <form
                method="POST"
                action="Menu.php">

                <input
                  type="hidden"
                  name="food_id"
                  value="<?= $f['id'] ?>">

                <button
                  type="submit"
                  class="order-btn"
                  style="
                    width:100%;
                    cursor:pointer;
                  ">

                  Add to Cart

                </button>

              </form>

            </article>

          <?php endwhile; ?>

        <?php endif; ?>

      </section>

    </main>

  </div>

</body>

</html>