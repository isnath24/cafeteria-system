<?php

/**
 * admin/verify_qr.php — Scan/enter a student's pickup QR code to verify and complete an order
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

$result_state = null; // null | 'success' | 'already' | 'not_ready' | 'invalid'
$order_details = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_token'])) {
    $token = trim($_POST['qr_token']);

    $stmt = mysqli_prepare($conn, "SELECT qc.id AS qr_id, qc.order_id, qc.verified_at, o.order_status, o.total_amount, u.name AS student_name
                                    FROM qr_codes qc
                                    JOIN orders o ON o.id = qc.order_id
                                    JOIN users u  ON u.id = o.user_id
                                    WHERE qc.qr_token = ?");
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        $result_state = 'invalid';
    } elseif ($row['verified_at']) {
        $result_state = 'already';
        $order_details = $row;
    } elseif ($row['order_status'] !== 'Ready') {
        $result_state = 'not_ready';
        $order_details = $row;
    } else {
        // Valid, unverified, and Ready — confirm pickup
        $upd1 = mysqli_prepare($conn, "UPDATE qr_codes SET verified_at = NOW(), verified_by = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd1, 'ii', $_SESSION['user_id'], $row['qr_id']);
        mysqli_stmt_execute($upd1);
        mysqli_stmt_close($upd1);

        $upd2 = mysqli_prepare($conn, "UPDATE orders SET order_status = 'Completed' WHERE id = ?");
        mysqli_stmt_bind_param($upd2, 'i', $row['order_id']);
        mysqli_stmt_execute($upd2);
        mysqli_stmt_close($upd2);

        // Fetch order items for the receipt
        $items_stmt = mysqli_prepare($conn, "SELECT f.food_name, oi.quantity, oi.subtotal FROM order_items oi JOIN food_items f ON f.id = oi.food_item_id WHERE oi.order_id = ?");
        mysqli_stmt_bind_param($items_stmt, 'i', $row['order_id']);
        mysqli_stmt_execute($items_stmt);
        $items_res = mysqli_stmt_get_result($items_stmt);
        $items = [];
        while ($item = mysqli_fetch_assoc($items_res)) $items[] = $item;

        $result_state = 'success';
        $order_details = $row;
        $order_details['items'] = $items;
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Verify Pickup QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>

<body>
    <div class="page">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/topbar.php'; ?>

            <div class="food-page-header">
                <div>
                    <h1>Verify Pickup</h1>
                    <p>Scan or enter a student's QR code to confirm order pickup</p>
                </div>
            </div>

            <div style="max-width:480px; margin:20px auto; background:white; border-radius:12px; padding:24px; box-shadow:0 3px 10px rgba(0,0,0,.08);">

                <?php if ($result_state === 'success'): ?>
                    <div style="text-align:center; color:#16a34a;">
                        <div style="font-size:40px;">✅</div>
                        <h2>Pickup Confirmed</h2>
                    </div>
                    <div style="margin-top:16px; border-top:1px solid #eee; padding-top:16px;">
                        <p><strong>Order:</strong> #<?= $order_details['order_id'] ?></p>
                        <p><strong>Student:</strong> <?= e($order_details['student_name']) ?></p>
                        <p><strong>Total:</strong> Rs.<?= number_format($order_details['total_amount'], 2) ?></p>
                        <hr style="margin:12px 0;border:none;border-top:1px solid #eee;">
                        <?php foreach ($order_details['items'] as $it): ?>
                            <div style="display:flex; justify-content:space-between; font-size:14px; padding:4px 0;">
                                <span><?= e($it['food_name']) ?> × <?= $it['quantity'] ?></span>
                                <span>Rs.<?= number_format($it['subtotal'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($result_state === 'already'): ?>
                    <div style="text-align:center; color:#dc2626;">
                        <div style="font-size:40px;">⚠️</div>
                        <h2>Already Picked Up</h2>
                        <p>Order #<?= $order_details['order_id'] ?> was already verified for pickup.</p>
                    </div>
                <?php elseif ($result_state === 'not_ready'): ?>
                    <div style="text-align:center; color:#dc2626;">
                        <div style="font-size:40px;">⏳</div>
                        <h2>Order Not Ready Yet</h2>
                        <p>Order #<?= $order_details['order_id'] ?> is currently <strong><?= e($order_details['order_status']) ?></strong>. It must be marked Ready before pickup can be confirmed.</p>
                    </div>
                <?php elseif ($result_state === 'invalid'): ?>
                    <div style="text-align:center; color:#dc2626;">
                        <div style="font-size:40px;">❌</div>
                        <h2>Invalid QR Code</h2>
                        <p>This code doesn't match any order.</p>
                    </div>
                <?php endif; ?>

                <?php if ($result_state): ?>
                    <div style="text-align:center; margin-top:20px;">
                        <a href="verify_qr.php" style="display:inline-block; padding:10px 20px; background:#7047f2; color:white; border-radius:8px; text-decoration:none; font-weight:bold;">Scan Next</a>
                    </div>
                <?php else: ?>
                    <h3 style="margin-bottom:10px;">Scan with camera</h3>
                    <div id="qr-reader" style="width:100%;"></div>
                    <p id="scanStatus" style="color:#6b7280; font-size:13px; margin-top:8px;"></p>

                    <hr style="margin:20px 0; border:none; border-top:1px solid #eee;">

                    <h3 style="margin-bottom:10px;">Or enter code manually</h3>
                    <form method="POST" action="verify_qr.php">
                        <input type="text" name="qr_token" placeholder="Paste QR token here" required
                            style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; margin-bottom:12px;">
                        <button type="submit" style="width:100%; padding:10px; background:#7047f2; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Verify</button>
                    </form>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php if (!$result_state): ?>
        <script>
            function onScanSuccess(decodedText) {
                document.getElementById('scanStatus').textContent = 'Code detected — verifying...';
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'verify_qr.php';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'qr_token';
                input.value = decodedText;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }

            try {
                const scanner = new Html5Qrcode("qr-reader");
                scanner.start({
                        facingMode: "environment"
                    }, {
                        fps: 10,
                        qrbox: 220
                    },
                    onScanSuccess
                ).catch(function(err) {
                    document.getElementById('scanStatus').textContent = 'Camera unavailable — use manual entry below.';
                });
            } catch (e) {
                document.getElementById('scanStatus').textContent = 'Camera unavailable — use manual entry below.';
            }
        </script>
    <?php endif; ?>

</body>

</html>