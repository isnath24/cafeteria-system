<?php
/**
 * email_templates.php — HTML email templates
 */

function order_ready_email($student_name, $order_id, $food_items, $pickup_time, $qr_image_url = null)
{
    $items_html = '';
    $total = 0;
    foreach ($food_items as $item) {
        $items_html .= "<tr>
            <td style='padding:10px 8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($item['food_name']) . "</td>
            <td style='padding:10px 8px;border-bottom:1px solid #eee;text-align:center;'>x{$item['quantity']}</td>
            <td style='padding:10px 8px;border-bottom:1px solid #eee;text-align:right;'>Rs. " . number_format($item['subtotal'], 2) . "</td>
        </tr>";
        $total += $item['subtotal'];
    }
    
    $qr_section = '';
    if ($qr_image_url) {
        $qr_section = "
        <div style='text-align:center;margin:24px 0;'>
            <p style='font-size:14px;color:#555;margin-bottom:10px;'>Show this QR code at the counter for pickup:</p>
            <img src='{$qr_image_url}' alt='Pickup QR' style='border:1px solid #ddd;border-radius:8px;padding:10px;background:white;'>
        </div>";
    }
    
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;padding:20px;margin:0;'>
        <div style='max-width:560px;margin:0 auto;background:white;border-radius:14px;padding:32px;'>
            <div style='text-align:center;margin-bottom:24px;'>
                <h1 style='color:#7047f2;margin:0;font-size:22px;'>Your Order is Ready!</h1>
                <p style='color:#6b7280;margin-top:6px;font-size:13px;'>UWU Cafeteria Pre-Order System</p>
            </div>
            
            <p style='font-size:15px;color:#111;'>Hi <strong>" . htmlspecialchars($student_name) . "</strong>,</p>
            
            <p style='font-size:15px;color:#111;'>
                Great news! Your order is <strong style='color:#16a34a;'>ready for pickup</strong>.
            </p>
            
            <div style='background:#f5f3ff;border-left:4px solid #7047f2;padding:14px 16px;border-radius:8px;margin:18px 0;'>
                <p style='margin:0 0 6px;font-size:14px;'><strong>Order ID:</strong> #{$order_id}</p>
                <p style='margin:0;font-size:14px;'><strong>Pickup Window:</strong> {$pickup_time}</p>
            </div>
            
            <table style='width:100%;border-collapse:collapse;margin-top:14px;font-size:14px;'>
                <thead>
                    <tr style='background:#f9fafb;'>
                        <th style='padding:10px 8px;text-align:left;font-size:12px;color:#6b7280;'>Item</th>
                        <th style='padding:10px 8px;text-align:center;font-size:12px;color:#6b7280;'>Qty</th>
                        <th style='padding:10px 8px;text-align:right;font-size:12px;color:#6b7280;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    {$items_html}
                    <tr>
                        <td colspan='2' style='padding:12px 8px;text-align:right;font-weight:bold;'>Total:</td>
                        <td style='padding:12px 8px;text-align:right;font-weight:bold;color:#7047f2;'>Rs. " . number_format($total, 2) . "</td>
                    </tr>
                </tbody>
            </table>
            
            {$qr_section}
            
            <p style='font-size:13px;color:#6b7280;margin-top:24px;'>
                Please collect your order at the cafeteria counter during the pickup window.
            </p>
            
            <hr style='border:none;border-top:1px solid #eee;margin:24px 0;'>
            
            <p style='font-size:11px;color:#9ca3af;text-align:center;margin:0;'>
                UWU Cafeteria - Uva Wellassa University<br>
                This is an automated message - please do not reply.
            </p>
        </div>
    </body>
    </html>";
}