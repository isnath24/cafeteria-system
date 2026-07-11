<?php
/**
 * admin/payments.php — View All Payments
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

// ── MARK PAID (POST) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paid') {
    $pay_id = (int)$_POST['pay_id'];
    
    $upd = mysqli_prepare($conn, "UPDATE payments SET payment_status = 'Paid', payment_date = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'i', $pay_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
    
    header('Location: payments.php?msg=marked_paid');
    exit;
}

$msg = '';
$flash_type = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'marked_paid') {
    $msg = 'Payment marked as Paid successfully.';
    $flash_type = 'success';
}

$sql = "SELECT p.id AS pay_id, p.order_id, u.name AS user_name, p.payment_method,
               p.amount, p.payment_status, p.payment_date
        FROM payments p
        JOIN orders  o ON o.id = p.order_id
        JOIN users   u ON u.id = o.user_id
        ORDER BY p.payment_status DESC, p.payment_date DESC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Payments</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
</head>
<body data-flash-type="<?= e($flash_type) ?>" data-flash-msg="<?= e($msg) ?>">
<div class="page">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="payments-page-header">
      <div><h1>Payments</h1><p>View all payment transactions</p></div>
    </div>

    <div class="payments-search-area">
      <div class="payments-search-box">
        <img src="images/search.png" alt="Search">
        <input type="text" id="paymentSearchInput" placeholder="Search payments...">
      </div>
    </div>



    <div class="payments-table-section">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Pay ID</th><th>Order ID</th><th>Student</th>
              <th>Method</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th>
            </tr>
          </thead>
          <tbody id="paymentsTableBody">
            <?php if (mysqli_num_rows($result) === 0): ?>
              <tr><td colspan="8">
                <div class="empty-state">
                  <div class="empty-state-icon">💳</div>
                  <div class="empty-state-title">No payment records</div>
                  <div class="empty-state-text">Payment transactions will appear here once students place and pay for orders.</div>
                </div>
              </td></tr>
            <?php else: ?>
              <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php
                  $sc = '';
                  if ($row['payment_status'] === 'Paid')   $sc = 'payment-paid';
                  if ($row['payment_status'] === 'Failed') $sc = 'payment-failed';
                ?>
                <tr>
                  <td>PAY<?= str_pad($row['pay_id'], 4, '0', STR_PAD_LEFT) ?></td>
                  <td>#<?= $row['order_id'] ?></td>
                  <td><?= e($row['user_name']) ?></td>
                  <td><?= e($row['payment_method']) ?></td>
                  <td>Rs.<?= number_format($row['amount'], 2) ?></td>
                  <td><span class="payment-status <?= $sc ?>"><?= e($row['payment_status']) ?></span></td>
                  <td><?= date('d M Y h:iA', strtotime($row['payment_date'])) ?></td>
                  <td>
                    <?php if ($row['payment_status'] === 'Pending' && $row['payment_method'] === 'Cash'): ?>
                      <form method="POST" style="display:inline;" id="pay-form-<?= $row['pay_id'] ?>">
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="pay_id" value="<?= $row['pay_id'] ?>">
                        <button type="button" style="background:#16a34a; color:white; border:none; padding:6px 12px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px;"
                          onclick="markAsPaid(<?= $row['pay_id'] ?>, 'Rs.<?= number_format($row['amount'],2) ?>', '<?= e(addslashes($row['user_name'])) ?>')">
                          Mark as Paid
                        </button>
                      </form>
                    <?php else: ?>
                      <span style="color:#6b7280; font-size:12px;">--</span>
                    <?php endif; ?>
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
<script src="script.js?v=<?= time() ?>"></script>
<script>
  document.getElementById('paymentSearchInput').addEventListener('input', function() {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#paymentsTableBody tr').forEach(function(row) {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });

  function markAsPaid(payId, amount, studentName) {
    confirmDialog({
      icon: '💰',
      title: 'Mark as Paid',
      message: 'Confirm cash payment of ' + amount + ' from ' + studentName + ' as Paid?',
      okText: 'Yes, Mark Paid',
      onConfirm: function () {
        document.getElementById('pay-form-' + payId).submit();
      }
    });
  }
</script>
</body>
</html>
