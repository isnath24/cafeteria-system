<?php
/**
 * admin/food_items.php — Food Items CRUD
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

$msg = '';

// ── DELETE ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $del = mysqli_prepare($conn, 'DELETE FROM food_items WHERE id = ?');
    mysqli_stmt_bind_param($del, 'i', $id);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
    header('Location: food_items.php?msg=deleted');
    exit;
}

// ── ADD (POST) ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $food_name   = trim($_POST['foodName']     ?? '');
    $category    = trim($_POST['foodCategory'] ?? '');
    $price       = (float)($_POST['foodPrice'] ?? 0);
    $stock       = (int)($_POST['foodStock']   ?? 0);
    $description = trim($_POST['description']  ?? '');
    $image_name  = 'default_food.jpg';

    // Handle image upload
    if (!empty($_FILES['foodImage']['name'])) {
        $upload_dir = dirname(__DIR__) . '/user/Images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext        = strtolower(pathinfo($_FILES['foodImage']['name'], PATHINFO_EXTENSION));
        $allowed    = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed)) {
            $image_filename = 'food_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['foodImage']['tmp_name'], $upload_dir . $image_filename)) {
                $image_name = $image_filename;
            }
        }
    }

    if ($food_name && $category && $price > 0) {
        $ins = mysqli_prepare($conn,
            'INSERT INTO food_items (food_name, description, price, category, image) VALUES (?,?,?,?,?)'
        );
        mysqli_stmt_bind_param($ins, 'ssdss', $food_name, $description, $price, $category, $image_name);
        mysqli_stmt_execute($ins);
        $new_food_id = mysqli_insert_id($conn);
        mysqli_stmt_close($ins);

        // Insert inventory record
        $inv = mysqli_prepare($conn,
            'INSERT INTO inventory (food_item_id, quantity, low_stock_alert) VALUES (?,?,10)'
        );
        mysqli_stmt_bind_param($inv, 'ii', $new_food_id, $stock);
        mysqli_stmt_execute($inv);
        mysqli_stmt_close($inv);

        header('Location: food_items.php?msg=added');
        exit;
    } else {
        $msg = 'Please fill all required fields.';
    }
}

// ── EDIT (POST) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id          = (int)$_POST['editId'];
    $food_name   = trim($_POST['editName']        ?? '');
    $category    = trim($_POST['editCategory']    ?? '');
    $price       = (float)$_POST['editPrice']     ?? 0;
    $stock       = (int)$_POST['editStock']       ?? 0;
    $status      = $_POST['editStatus']           ?? 'Available';
    $description = trim($_POST['editDescription'] ?? '');

    // Handle image upload if provided
    $image_update_sql = "";
    if (!empty($_FILES['foodImage']['name'])) {
        $upload_dir = dirname(__DIR__) . '/user/Images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext        = strtolower(pathinfo($_FILES['foodImage']['name'], PATHINFO_EXTENSION));
        $allowed    = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed)) {
            $image_filename = 'food_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['foodImage']['tmp_name'], $upload_dir . $image_filename)) {
                $image_update_sql = ", image = '" . mysqli_real_escape_string($conn, $image_filename) . "'";
            }
        }
    }

    $query = "UPDATE food_items SET food_name=?, description=?, category=?, price=?, availability_status=?" . $image_update_sql . " WHERE id=?";
    $upd = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($upd, 'sssdsi', $food_name, $description, $category, $price, $status, $id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    // Update inventory quantity
    $updInv = mysqli_prepare($conn,
        'UPDATE inventory SET quantity=? WHERE food_item_id=?'
    );
    mysqli_stmt_bind_param($updInv, 'ii', $stock, $id);
    mysqli_stmt_execute($updInv);
    mysqli_stmt_close($updInv);

    header('Location: food_items.php?msg=updated');
    exit;
}

// Flash messages
$flash_type = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added')   { $msg = 'Food item added successfully.';   $flash_type = 'success'; }
    if ($_GET['msg'] === 'updated') { $msg = 'Food item updated successfully.'; $flash_type = 'success'; }
    if ($_GET['msg'] === 'deleted') { $msg = 'Food item deleted successfully.'; $flash_type = 'success'; }
}

// ── FETCH all food items with inventory ───────────────────────
$foods_sql = "SELECT f.*, COALESCE(i.quantity,0) AS stock, COALESCE(i.low_stock_alert,10) AS low_alert
              FROM food_items f
              LEFT JOIN inventory i ON i.food_item_id = f.id
              ORDER BY f.id DESC";
$foods = mysqli_query($conn, $foods_sql);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Food Items</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Styling to vertically align elements in table rows nicely */
    td {
      vertical-align: middle;
    }
    .food-modal-box select {
      width: 100%;
      padding: 11px;
      margin-bottom: 12px;
      border: 1px solid #ddd;
      outline: none;
      background: white;
    }
  </style>
</head>
<body data-flash-type="<?= e($flash_type) ?>" data-flash-msg="<?= e($msg) ?>">
<div class="page">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="food-page-header">
      <div><h1>Food Items</h1><p>Manage all food items</p></div>
      <button id="addFoodBtn">+ Add New Food Item</button>
    </div>



    <!-- Add Food Modal -->
    <div class="food-modal" id="foodModal">
      <div class="food-modal-box">
        <h2>Add New Food Item</h2>
        <form method="POST" action="food_items.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add">
          <input type="text"   name="foodName"     placeholder="Food Name"   required>
          <select name="foodCategory" required>
            <option value="" disabled selected>Select Category</option>
            <option value="Breakfast">Breakfast</option>
            <option value="Lunch">Lunch</option>
            <option value="Dinner">Dinner</option>
            <option value="Anytime">Anytime</option>
          </select>
          <input type="number" name="foodPrice"    placeholder="Price (Rs.)" step="0.01" required>
          <input type="number" name="foodStock"    placeholder="Initial Stock" required>
          <input type="text"   name="description"  placeholder="Description (optional)">
          <input type="file"   name="foodImage"    accept="image/*">
          <div class="modal-buttons">
            <button type="button" id="closeFoodModal">Cancel</button>
            <button type="submit">Add Food Item</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Food Modal -->
    <div class="food-modal" id="editFoodModal">
      <div class="food-modal-box">
        <h2>Edit Food Item</h2>
        <form method="POST" action="food_items.php" enctype="multipart/form-data">
          <input type="hidden" name="action"  id="editIdField" value="edit">
          <input type="hidden" name="editId"  id="editIdVal">
          <input type="text"   name="editName"     id="editNameField"     placeholder="Food Name"  required>
          <select name="editCategory" id="editCategoryField" required>
            <option value="Breakfast">Breakfast</option>
            <option value="Lunch">Lunch</option>
            <option value="Dinner">Dinner</option>
            <option value="Anytime">Anytime</option>
          </select>
          <input type="number" name="editPrice"    id="editPriceField"    placeholder="Price"      step="0.01" required>
          <input type="number" name="editStock"    id="editStockField"    placeholder="Stock"      required>
          <select name="editStatus" id="editStatusField">
            <option value="Available">Available</option>
            <option value="Unavailable">Unavailable</option>
          </select>
          <textarea name="editDescription" id="editDescriptionField" placeholder="Description (optional)" rows="3" style="width:100%;padding:10px;margin-bottom:12px;border:1px solid #ddd;border-radius:6px;resize:vertical;font-family:inherit;font-size:14px;outline:none;"></textarea>
          <label style="font-size: 13px; color: #555; display: block; margin-bottom: 5px; margin-top: 2px;">Update Image (optional)</label>
          <input type="file"   name="foodImage"    accept="image/*">
          <div class="modal-buttons">
            <button type="button" id="closeEditModal">Cancel</button>
            <button type="submit">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Search -->
    <div class="food-search-area">
      <div class="food-search-box">
        <img src="images/search.png" alt="Search">
        <input type="text" id="foodSearchInput" placeholder="Search food items...">
      </div>
    </div>

    <!-- Table -->
    <div class="food-table-section">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Photo</th><th>Food Name</th><th>Category</th>
              <th>Price</th><th>Stock</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody id="foodTableBody">
            <?php $food_rows = mysqli_fetch_all($foods, MYSQLI_ASSOC); ?>
            <?php if (empty($food_rows)): ?>
              <tr><td colspan="8">
                <div class="empty-state">
                  <div class="empty-state-icon">🍽️</div>
                  <div class="empty-state-title">No food items yet</div>
                  <div class="empty-state-text">Click "+ Add New Food Item" to add your first item to the menu.</div>
                </div>
              </td></tr>
            <?php else: ?>
            <?php foreach ($food_rows as $f): ?>
              <?php
                if ($f['availability_status'] === 'Unavailable') {
                  $status_class = 'status-cancelled'; // Red badge
                } elseif ($f['stock'] <= $f['low_alert']) {
                  $status_class = 'low-stock'; // Orange badge
                } else {
                  $status_class = 'available'; // Green badge
                }
                $status_label = $f['availability_status'];
              ?>
              <tr>
                <td><?= $f['id'] ?></td>
                <td>
                  <img src="../user/Images/<?= e($f['image']) ?>" onerror="this.src='../user/Images/food.jpg'" style="width: 45px; height: 45px; object-fit: cover; border-radius: 8px;">
                </td>
                <td><?= e($f['food_name']) ?></td>
                <td><?= e($f['category']) ?></td>
                <td>Rs.<?= number_format($f['price'], 2) ?></td>
                <td><?= $f['stock'] ?></td>
                <td><span class="food-status <?= $status_class ?>"><?= $status_label ?></span></td>
                <td>
                  <div class="action-buttons">
                    <button class="edit-btn" onclick="openEditModal(<?= $f['id'] ?>, '<?= e(addslashes($f['food_name'])) ?>', '<?= e(addslashes($f['category'])) ?>', <?= $f['price'] ?>, <?= $f['stock'] ?>, '<?= $f['availability_status'] ?>', '<?= e(addslashes($f['description'] ?? '')) ?>')">
                      <img src="images/edit.png" alt="Edit">
                    </button>
                    <button class="delete-btn" onclick="confirmDialog({icon:'🗑️',title:'Delete Food Item',message:'Delete &quot;<?= e(addslashes($f['food_name'])) ?>&quot;? This will also remove its inventory record.',okText:'Yes, Delete',onConfirm:function(){window.location='food_items.php?delete=<?= $f['id'] ?>';}})"><img src="images/trash.png" alt="Delete"></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
<script src="script.js?v=<?= time() ?>"></script>
<script>
  // Add modal
  document.getElementById('addFoodBtn').onclick   = () => document.getElementById('foodModal').classList.add('show');
  document.getElementById('closeFoodModal').onclick = () => document.getElementById('foodModal').classList.remove('show');
  document.getElementById('closeEditModal').onclick = () => document.getElementById('editFoodModal').classList.remove('show');

  // Edit modal populate
  function openEditModal(id, name, cat, price, stock, status, description) {
    document.getElementById('editIdVal').value       = id;
    document.getElementById('editNameField').value   = name;
    
    // Select category dropdown
    const select = document.getElementById('editCategoryField');
    // Check if category option exists
    let exists = false;
    for (let i = 0; i < select.options.length; i++) {
      if (select.options[i].value === cat) {
        exists = true;
        break;
      }
    }
    // If not exists dynamically add it
    if (!exists && cat) {
      const opt = document.createElement('option');
      opt.value = cat;
      opt.innerHTML = cat;
      select.appendChild(opt);
    }
    select.value = cat;

    document.getElementById('editPriceField').value       = price;
    document.getElementById('editStockField').value       = stock;
    document.getElementById('editStatusField').value      = status;
    document.getElementById('editDescriptionField').value = description || '';
    document.getElementById('editFoodModal').classList.add('show');
  }

  // Search filter
  document.getElementById('foodSearchInput').addEventListener('input', function() {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#foodTableBody tr').forEach(function(row) {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
</script>
</body>
</html>
