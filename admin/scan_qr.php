<?php
/**
 * admin/scan_qr.php — QR Code Pickup Verification
 */

require_once '../config.php';
require_once '../db.php';
require_admin();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| VERIFY QR CODE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $qr_token = trim($_POST['qr_token'] ?? '');

    if ($qr_token === '') {

        $error = 'Please scan a QR code.';

    } else {

        // Find QR code and related order
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                q.id AS qr_id,
                q.order_id,
                q.qr_token,
                q.verified_at,
                o.order_status,
                o.user_id
             FROM qr_codes q
             JOIN orders o ON o.id = q.order_id
             WHERE q.qr_token = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 's', $qr_token);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $qr_data = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        // QR not found
        if (!$qr_data) {

            $error = 'Invalid QR code. Order not found.';

        // QR already used
        } elseif ($qr_data['verified_at']) {

            $error = 'This order has already been picked up.';

        // Order is not ready
        } elseif ($qr_data['order_status'] !== 'Ready') {

            $error = 'This order is not ready for pickup yet.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Mark QR as verified
            |--------------------------------------------------------------------------
            */

            $update = mysqli_prepare(
                $conn,
                "UPDATE qr_codes
                 SET verified_at = NOW()
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $update,
                'i',
                $qr_data['qr_id']
            );

            mysqli_stmt_execute($update);
            mysqli_stmt_close($update);

            /*
            |--------------------------------------------------------------------------
            | Change order status to Completed
            |--------------------------------------------------------------------------
            */

            $complete = mysqli_prepare(
                $conn,
                "UPDATE orders
                 SET order_status = 'Completed'
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $complete,
                'i',
                $qr_data['order_id']
            );

            mysqli_stmt_execute($complete);
            mysqli_stmt_close($complete);

            $message =
                '✅ Order #' .
                $qr_data['order_id'] .
                ' verified successfully. The meal can now be given to the student.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Scan Pickup QR</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

    <style>

        .scan-page {
            padding: 40px;
            text-align: center;
        }

        .scan-box {
            max-width: 550px;
            margin: 30px auto;
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .scan-icon {
            font-size: 55px;
            margin-bottom: 15px;
        }

        .scan-box h1 {
            margin-bottom: 10px;
        }

        .scan-box p {
            color: #6b7280;
            margin-bottom: 25px;
        }

        #reader {
            width: 100%;
            max-width: 400px;
            margin: 20px auto;
        }

        .qr-input {
            width: 100%;
            padding: 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        .verify-btn {
            width: 100%;
            padding: 13px;
            background: #7047f2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .verify-btn:hover {
            background: #5d38d6;
        }

        .success-message {
            background: #ecfdf5;
            color: #15803d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #7047f2;
            text-decoration: none;
            font-weight: 600;
        }

    </style>

</head>

<body>

<div class="page">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include 'includes/topbar.php'; ?>

        <div class="scan-page">

            <div class="scan-box">

                <div class="scan-icon">
                    📷
                </div>

                <h1>
                    Scan Pickup QR
                </h1>

                <p>
                    Scan the student's QR code to verify the order.
                </p>


                <!-- SUCCESS MESSAGE -->

                <?php if ($message): ?>

                    <div class="success-message">
                        <?= e($message) ?>
                    </div>

                <?php endif; ?>


                <!-- ERROR MESSAGE -->

                <?php if ($error): ?>

                    <div class="error-message">
                        <?= e($error) ?>
                    </div>

                <?php endif; ?>


                <!-- QR CAMERA SCANNER -->

                <div id="reader"></div>


                <!-- QR TOKEN FORM -->

                <form
                    method="POST"
                    id="qrForm"
                >

                    <input
                        type="text"
                        name="qr_token"
                        id="qr_token"
                        class="qr-input"
                        placeholder="QR token will appear here"
                        required
                        readonly
                    >


                    <button
                        type="submit"
                        class="verify-btn"
                    >
                        ✅ Verify Order
                    </button>

                </form>


                <a
                    href="orders.php"
                    class="back-link"
                >
                    ← Back to Orders
                </a>

            </div>

        </div>

    </div>

</div>


<!-- QR SCANNER LIBRARY -->

<script
    src="https://unpkg.com/html5-qrcode"
    type="text/javascript"
></script>


<script>

let html5QrCode = null;


/*
|--------------------------------------------------------------------------
| QR SCAN SUCCESS
|--------------------------------------------------------------------------
*/

function onScanSuccess(decodedText, decodedResult) {

    // Put scanned QR token into input
    document.getElementById('qr_token').value = decodedText;


    // Stop camera
    if (html5QrCode) {

        html5QrCode
            .stop()
            .then(function () {

                console.log('Camera stopped.');

            })
            .catch(function (error) {

                console.log('Camera stop error:', error);

            });

    }


    // Automatically submit form
    document.getElementById('qrForm').submit();

}


/*
|--------------------------------------------------------------------------
| QR SCAN FAILURE
|--------------------------------------------------------------------------
*/

function onScanFailure(error) {

    // Ignore unsuccessful scans

}


/*
|--------------------------------------------------------------------------
| START CAMERA
|--------------------------------------------------------------------------
*/

function startScanner() {

    html5QrCode = new Html5Qrcode("reader");


    html5QrCode
        .start(

            {
                facingMode: "environment"
            },

            {
                fps: 10,

                qrbox: {
                    width: 250,
                    height: 250
                }

            },

            onScanSuccess,

            onScanFailure

        )
        .catch(function(error) {

            console.log('Camera error:', error);

            document.getElementById('reader').innerHTML =

                '<p style="color:#dc2626; font-weight:600;">' +

                '📷 Camera could not be started.<br>' +

                'Please allow camera permission and try again.' +

                '</p>';

        });

}


/*
|--------------------------------------------------------------------------
| START WHEN PAGE LOADS
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'load',
    function() {

        startScanner();

    }
);

</script>

</body>

</html>