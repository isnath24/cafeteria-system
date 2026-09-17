<?php

/**
 * admin/verify_qr.php
 * Scan a student's pickup QR code containing the Order ID
 * or enter the Order ID manually.
 */

require_once '../config.php';
require_once '../db.php';
require_admin();

$result_state = null;
$order_details = null;

/*
|--------------------------------------------------------------------------
| VERIFY QR / ORDER ID
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['qr_token'])) {

    $token = trim($_POST['qr_token']);

    /*
     * QR code contains the Order ID.
     * Example:
     * QR Code → "1025"
     */
    $numeric_id = is_numeric($token) ? (int)$token : 0;

    /*
     * Find order using Order ID.
     */
    $stmt = mysqli_prepare($conn, "
        SELECT
            o.id AS order_id,
            o.order_status,
            o.total_amount,
            u.name AS student_name,
            qc.id AS qr_id,
            qc.qr_token,
            qc.verified_at
        FROM orders o
        JOIN users u ON u.id = o.user_id
        LEFT JOIN qr_codes qc ON qc.order_id = o.id
        WHERE o.id = ?
           OR (qc.qr_token IS NOT NULL AND qc.qr_token = ?)
        LIMIT 1
    ");

    mysqli_stmt_bind_param($stmt, 'is', $numeric_id, $token);
    mysqli_stmt_execute($stmt);

    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);

    mysqli_stmt_close($stmt);

    /*
     * No matching order.
     */
    if (!$row) {

        $result_state = 'invalid';

        /*
     * Order has already been collected.
     */
    } elseif (
        $row['verified_at'] ||
        $row['order_status'] === 'Completed'
    ) {

        $result_state = 'already';
        $order_details = $row;

        /*
     * Order is not ready.
     */
    } elseif ($row['order_status'] !== 'Ready') {

        $result_state = 'not_ready';
        $order_details = $row;

        /*
     * Order is Ready → confirm pickup.
     */
    } else {

        /*
         * Mark QR as verified.
         */
        if (!empty($row['qr_id'])) {

            $upd1 = mysqli_prepare(
                $conn,
                "UPDATE qr_codes
                 SET verified_at = NOW(),
                     verified_by = ?
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $upd1,
                'ii',
                $_SESSION['user_id'],
                $row['qr_id']
            );

            mysqli_stmt_execute($upd1);
            mysqli_stmt_close($upd1);
        }

        /*
         * Mark order as Completed.
         */
        $upd2 = mysqli_prepare(
            $conn,
            "UPDATE orders
             SET order_status = 'Completed'
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $upd2,
            'i',
            $row['order_id']
        );

        mysqli_stmt_execute($upd2);
        mysqli_stmt_close($upd2);

        /*
         * Get food items belonging to this order.
         */
        $items_stmt = mysqli_prepare($conn, "
            SELECT
                f.food_name,
                oi.quantity,
                oi.subtotal
            FROM order_items oi
            JOIN food_items f
                ON f.id = oi.food_item_id
            WHERE oi.order_id = ?
        ");

        mysqli_stmt_bind_param(
            $items_stmt,
            'i',
            $row['order_id']
        );

        mysqli_stmt_execute($items_stmt);

        $items_res = mysqli_stmt_get_result($items_stmt);

        $items = [];

        while ($item = mysqli_fetch_assoc($items_res)) {
            $items[] = $item;
        }

        mysqli_stmt_close($items_stmt);

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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <link
        rel="stylesheet"
        href="style.css">

    <!-- QR SCANNER LIBRARY -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

</head>


<body>

    <div class="page">

        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">

            <?php include 'includes/topbar.php'; ?>


            <!-- HEADER -->
            <div class="food-page-header">

                <div>

                    <h1>Verify Pickup</h1>

                    <p>
                        Scan the student's QR code to verify
                        and complete the order.
                    </p>

                </div>

            </div>


            <!-- MAIN CARD -->
            <div
                style="
                max-width:480px;
                margin:20px auto;
                background:white;
                border-radius:12px;
                padding:24px;
                box-shadow:0 3px 10px rgba(0,0,0,.08);
            ">


                <?php if ($result_state === 'success'): ?>

                    <!-- SUCCESS -->

                    <div
                        style="
                    text-align:center;
                    color:#16a34a;
                ">

                        <div style="font-size:50px;">
                            ✅
                        </div>

                        <h2>
                            Pickup Confirmed
                        </h2>

                        <p>
                            Order has been successfully collected.
                        </p>

                    </div>


                    <div
                        style="
                    margin-top:16px;
                    border-top:1px solid #eee;
                    padding-top:16px;
                ">

                        <p>
                            <strong>Order:</strong>
                            #<?= $order_details['order_id'] ?>
                        </p>

                        <p>
                            <strong>Student:</strong>
                            <?= e($order_details['student_name']) ?>
                        </p>

                        <p>
                            <strong>Total:</strong>
                            Rs.<?= number_format(
                                    $order_details['total_amount'],
                                    2
                                ) ?>
                        </p>


                        <hr
                            style="
                        margin:12px 0;
                        border:none;
                        border-top:1px solid #eee;
                    ">


                        <?php foreach (
                            $order_details['items']
                            as $it
                        ): ?>

                            <div
                                style="
                            display:flex;
                            justify-content:space-between;
                            font-size:14px;
                            padding:4px 0;
                        ">

                                <span>
                                    <?= e($it['food_name']) ?>
                                    × <?= $it['quantity'] ?>
                                </span>

                                <span>
                                    Rs.<?= number_format(
                                            $it['subtotal'],
                                            2
                                        ) ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>


                <?php elseif ($result_state === 'already'): ?>

                    <!-- ALREADY PICKED UP -->

                    <div
                        style="
                    text-align:center;
                    color:#dc2626;
                ">

                        <div style="font-size:50px;">
                            ⚠️
                        </div>

                        <h2>
                            Already Picked Up
                        </h2>

                        <p>
                            Order #<?= $order_details['order_id'] ?>
                            was already verified or completed.
                        </p>

                    </div>


                <?php elseif ($result_state === 'not_ready'): ?>

                    <!-- NOT READY -->

                    <div
                        style="
                    text-align:center;
                    color:#dc2626;
                ">

                        <div style="font-size:50px;">
                            ⏳
                        </div>

                        <h2>
                            Order Not Ready Yet
                        </h2>

                        <p>

                            Order #<?= $order_details['order_id'] ?>

                            is currently

                            <strong>
                                <?= e($order_details['order_status']) ?>
                            </strong>.

                        </p>

                        <p>
                            The order must be marked
                            <strong>Ready</strong>
                            before pickup.
                        </p>

                    </div>


                <?php elseif ($result_state === 'invalid'): ?>

                    <!-- INVALID -->

                    <div
                        style="
                    text-align:center;
                    color:#dc2626;
                ">

                        <div style="font-size:50px;">
                            ❌
                        </div>

                        <h2>
                            Invalid QR Code
                        </h2>

                        <p>
                            This QR code does not match
                            any order in the system.
                        </p>

                    </div>


                <?php endif; ?>


                <!-- NEXT SCAN -->

                <?php if ($result_state): ?>

                    <div
                        style="
                    text-align:center;
                    margin-top:20px;
                ">

                        <a
                            href="verify_qr.php"
                            style="
                        display:inline-block;
                        padding:10px 20px;
                        background:#7047f2;
                        color:white;
                        border-radius:8px;
                        text-decoration:none;
                        font-weight:bold;
                    ">
                            Scan / Enter Next
                        </a>

                    </div>


                <?php else: ?>


                    <!-- CAMERA SCANNER -->

                    <h3 style="margin-bottom:10px;">
                        📷 Scan QR Code
                    </h3>


                    <div
                        id="qr-reader"
                        style="width:100%;"></div>


                    <p
                        id="scanStatus"
                        style="
                    color:#6b7280;
                    font-size:13px;
                    margin-top:8px;
                    text-align:center;
                ">
                        Allow camera access to scan.
                    </p>


                    <hr
                        style="
                    margin:20px 0;
                    border:none;
                    border-top:1px solid #eee;
                ">


                    <!-- MANUAL ENTRY -->

                    <h3 style="margin-bottom:10px;">
                        Or Enter Order ID
                    </h3>


                    <form
                        method="POST"
                        action="verify_qr.php">

                        <input
                            type="text"
                            name="qr_token"
                            placeholder="Enter Order ID (e.g. 1025)"
                            required
                            autocomplete="off"
                            style="
                        width:100%;
                        padding:10px;
                        border:1px solid #ddd;
                        border-radius:6px;
                        margin-bottom:12px;
                        box-sizing:border-box;
                    ">


                        <button
                            type="submit"
                            style="
                        width:100%;
                        padding:10px;
                        background:#7047f2;
                        color:white;
                        border:none;
                        border-radius:6px;
                        font-weight:bold;
                        cursor:pointer;
                    ">
                            Verify Order
                        </button>

                    </form>


                <?php endif; ?>


            </div>

        </div>

    </div>



    <!-- QR SCANNER JAVASCRIPT -->

    <?php if (!$result_state): ?>

        <script>
            function onScanSuccess(decodedText) {

                const status =
                    document.getElementById('scanStatus');

                status.textContent =
                    '✅ QR detected — verifying Order...';


                /*
                 * Stop scanning after detecting
                 * the QR code.
                 */
                if (window.qrScanner) {

                    window.qrScanner
                        .stop()
                        .catch(function(error) {
                            console.log(error);
                        });

                }


                /*
                 * Send QR value to PHP.
                 *
                 * Example:
                 *
                 * QR contains → 1025
                 *
                 * PHP receives:
                 *
                 * $_POST['qr_token'] = "1025"
                 */

                const form =
                    document.createElement('form');

                form.method = 'POST';

                form.action = 'verify_qr.php';


                const input =
                    document.createElement('input');

                input.type = 'hidden';

                input.name = 'qr_token';

                input.value = decodedText;


                form.appendChild(input);

                document.body.appendChild(form);

                form.submit();

            }



            try {

                window.qrScanner =
                    new Html5Qrcode("qr-reader");


                window.qrScanner.start(

                    {
                        facingMode: "environment"
                    },

                    {
                        fps: 10,
                        qrbox: 220
                    },

                    onScanSuccess

                ).catch(function(error) {

                    console.log(error);

                    document
                        .getElementById('scanStatus')
                        .textContent =
                        '⚠️ Camera unavailable. Please allow camera access or use manual Order ID entry.';

                });


            } catch (error) {

                console.log(error);

                document
                    .getElementById('scanStatus')
                    .textContent =
                    '⚠️ Camera unavailable. Please use manual Order ID entry.';

            }
        </script>

    <?php endif; ?>


</body>

</html>