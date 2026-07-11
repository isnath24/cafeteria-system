<?php
/**
 * user/Html/Payments.php — Student Payment History
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

$user_id = $_SESSION['user_id'];

// ── SIMULATE PAYMENT (for Cash-on-pickup orders) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_order_id'])) {
    $pay_order_id = (int)$_POST['pay_order_id'];

    // Verify this order belongs to the student and is unpaid
    $chk = mysqli_prepare($conn,
        "SELECT p.id FROM payments p JOIN orders o ON o.id=p.order_id
         WHERE p.order_id=? AND o.user_id=? AND p.payment_status='Pending' LIMIT 1"
    );
    mysqli_stmt_bind_param($chk, 'ii', $pay_order_id, $user_id);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);

    if (mysqli_stmt_num_rows($chk) > 0) {
        $upd = mysqli_prepare($conn,
            "UPDATE payments SET payment_status='Paid', payment_date=NOW() WHERE order_id=?"
        );
        mysqli_stmt_bind_param($upd, 'i', $pay_order_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
    mysqli_stmt_close($chk);
    header('Location: Payments.php?msg=paid');
    exit;
}

$msg = isset($_GET['msg']) && $_GET['msg']==='paid' ? 'Payment marked as paid!' : '';

// ── Stats ─────────────────────────────────────────────────────
$total_spent = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(p.amount),0) AS n FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.user_id=$user_id AND p.payment_status='Paid'"))['n'];
$total_txn   = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS n FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.user_id=$user_id"))['n'];
$card_count  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS n FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.user_id=$user_id AND p.payment_method='Card'"))['n'];
$cash_count  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS n FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.user_id=$user_id AND p.payment_method='Cash'"))['n'];

// ── Payment history ───────────────────────────────────────────
$pay_result = mysqli_query($conn,
    "SELECT p.id AS pay_id, p.order_id, p.payment_method, p.amount, p.payment_status, p.payment_date
     FROM payments p JOIN orders o ON o.id=p.order_id
     WHERE o.user_id=$user_id ORDER BY p.payment_date DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payments</title>
  <link rel="stylesheet" href="../CSS/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<div class="dashboard-page">
  <?php include 'includes/sidebar.php'; ?>

  <main class="main-content">
    <header class="dashboard-header">
      <div><h1>Payments</h1><p>Your payment history and receipts</p></div>
      <div class="header-actions">
        <button class="icon-btn" type="button"><i class="fa-regular fa-bell"></i></button>
        <div class="user-chip">
          <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
          <span><?= e($_SESSION['name']) ?></span>
        </div>
      </div>
    </header>

    <?php if ($msg): ?>
      <p style="padding:6px 0;color:#16a34a;font-weight:bold;"><?= e($msg) ?></p>
    <?php endif; ?>

    <!-- Stats -->
    <div class="pay-summary-row">
      <div class="pay-stat-card">
        <div class="pay-stat-icon icon-green"><i class="fa-solid fa-money-bill-wave"></i></div>
        <div><p>Total Spent</p><h2>Rs.<?= number_format($total_spent, 2) ?></h2></div>
      </div>
      <div class="pay-stat-card">
        <div class="pay-stat-icon icon-purple"><i class="fa-solid fa-receipt"></i></div>
        <div><p>Transactions</p><h2><?= $total_txn ?></h2></div>
      </div>
      <div class="pay-stat-card">
        <div class="pay-stat-icon icon-blue"><i class="fa-solid fa-credit-card"></i></div>
        <div><p>Card Payments</p><h2><?= $card_count ?></h2></div>
      </div>
      <div class="pay-stat-card">
        <div class="pay-stat-icon icon-orange"><i class="fa-solid fa-coins"></i></div>
        <div><p>Cash Payments</p><h2><?= $cash_count ?></h2></div>
      </div>
    </div>

    <!-- Payment Table -->
    <div class="payment-history-section">
      <h2 style="font-size:14px;font-weight:700;margin-bottom:10px;">Payment History</h2>
      <div class="payment-table-wrapper">
        <table class="payment-table">
          <thead>
            <tr>
              <th>Pay ID</th><th>Order ID</th><th>Date</th>
              <th>Amount</th><th>Method</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (mysqli_num_rows($pay_result) === 0): ?>
              <tr><td colspan="7" style="padding:20px;text-align:center;color:#888;">No payment records found.</td></tr>
            <?php else: ?>
              <?php while ($row = mysqli_fetch_assoc($pay_result)):
                $sc = $row['payment_status']==='Paid' ? 'pay-success' : 'pay-pending';
                $mc = $row['payment_method']==='Card'  ? 'method-card'  : 'method-cash';
              ?>
                <tr class="payment-row">
                  <td class="pay-order-id">PAY<?= str_pad($row['pay_id'],4,'0',STR_PAD_LEFT) ?></td>
                  <td>#<?= $row['order_id'] ?></td>
                  <td class="pay-date"><?= date('d M Y', strtotime($row['payment_date'])) ?></td>
                  <td class="pay-amount">Rs.<?= number_format($row['amount'],2) ?></td>
                  <td><span class="pay-method <?= $mc ?>"><?= e($row['payment_method']) ?></span></td>
                  <td><span class="pay-status-badge <?= $sc ?>"><?= e($row['payment_status']) ?></span></td>
                  <td>
                    <button class="btn-receipt" onclick="showReceipt(<?= $row['pay_id'] ?>, '<?= e($row['order_id']) ?>', '<?= date('d M Y',strtotime($row['payment_date'])) ?>', 'Rs.<?= number_format($row['amount'],2) ?>', '<?= e($row['payment_method']) ?>', '<?= e($row['payment_status']) ?>')">
                      <i class="fa-solid fa-receipt"></i> Receipt
                    </button>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Receipt Modal -->
<div class="receipt-overlay" id="receiptOverlay">
  <div class="receipt-modal">
    <div class="receipt-header">
      <img src="../Images/logo.png" class="receipt-logo" alt="Logo" onerror="this.style.display='none'">
      <div><h3>UWU Cafeteria</h3><p>Pre-Order System</p></div>
      <button class="receipt-close" onclick="document.getElementById('receiptOverlay').classList.remove('active')">✕</button>
    </div>
    <div class="receipt-divider"></div>
    <p class="receipt-title">Payment Receipt</p>
    <div class="receipt-details">
      <div class="receipt-row"><span>Pay ID</span><strong id="rPayId">--</strong></div>
      <div class="receipt-row"><span>Order ID</span><strong id="rOrderId">--</strong></div>
      <div class="receipt-row"><span>Date</span><strong id="rDate">--</strong></div>
      <div class="receipt-row"><span>Method</span><strong id="rMethod">--</strong></div>
      <div class="receipt-row"><span>Status</span><strong id="rStatus">--</strong></div>
    </div>
    <div class="receipt-divider"></div>
    <div class="receipt-total-row">
      <span>Total Paid</span>
      <span class="receipt-total-amount" id="rAmount">--</span>
    </div>
    <p class="receipt-note">Thank you for your order!</p>
    <button class="receipt-print-btn" onclick="window.print()">
      <i class="fa-solid fa-print"></i> Print Receipt
    </button>
  </div>
</div>

<script>
function showReceipt(payId, orderId, date, amount, method, status) {
  document.getElementById('rPayId').textContent   = 'PAY' + String(payId).padStart(4,'0');
  document.getElementById('rOrderId').textContent = '#' + orderId;
  document.getElementById('rDate').textContent    = date;
  document.getElementById('rAmount').textContent  = amount;
  document.getElementById('rMethod').textContent  = method;
  
  var statusEl = document.getElementById('rStatus');
  if (status === 'Pending') {
    statusEl.style.color = '#ef4444';
    statusEl.textContent = 'UNPAID (Pending)';
  } else {
    statusEl.style.color = '#16a34a';
    statusEl.textContent = 'PAID';
  }
  document.getElementById('receiptOverlay').classList.add('active');
}
document.getElementById('receiptOverlay').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('active');
});
</script>
</body>
</html>
