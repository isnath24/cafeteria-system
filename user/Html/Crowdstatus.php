<?php

/**
 * user/Html/CrowdStatus.php — Live crowd level per pickup slot (auto-calculated)
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

// ── Fetch today's remaining slots with live booking counts ─────
$sql = "SELECT ps.id, ps.start_time, ps.end_time, ps.meal_period, ps.max_orders,
               COALESCE(booked.n, 0) AS booked_count
        FROM pickup_slots ps
        LEFT JOIN (
            SELECT pickup_slot_id, COUNT(*) AS n FROM orders
            WHERE DATE(created_at) = CURDATE() AND pickup_slot_id IS NOT NULL
            GROUP BY pickup_slot_id
        ) booked ON booked.pickup_slot_id = ps.id
        WHERE ps.is_active = 1
        ORDER BY ps.start_time";
$result = mysqli_query($conn, $sql);

$slots = [];
$now_slot = null; // the slot covering right now, if any

while ($s = mysqli_fetch_assoc($result)) {
    $pct = $s['max_orders'] > 0 ? ($s['booked_count'] / $s['max_orders']) : 0;
    if ($pct >= 0.8) {
        $s['crowd_color'] = '🔴';
        $s['crowd_label'] = 'High';
        $s['crowd_class'] = 'high';
    } elseif ($pct >= 0.5) {
        $s['crowd_color'] = '🟡';
        $s['crowd_label'] = 'Medium';
        $s['crowd_class'] = 'medium';
    } else {
        $s['crowd_color'] = '🟢';
        $s['crowd_label'] = 'Low';
        $s['crowd_class'] = 'low';
    }
    $s['is_past'] = strtotime($s['end_time']) < strtotime(date('H:i:s'));
    $s['is_now']  = !$s['is_past'] && strtotime($s['start_time']) <= strtotime(date('H:i:s'));
    if ($s['is_now']) $now_slot = $s;
    $slots[] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Crowd Status</title>
    <link rel="stylesheet" href="../CSS/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .crowd-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
            margin-top: 20px;
        }

        .crowd-card {
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            background: white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .04);
        }

        .crowd-card.past {
            opacity: .4;
        }

        .crowd-card.current {
            border: 2px solid #7047f2;
            box-shadow: 0 0 0 4px rgba(112, 71, 242, .12);
        }

        .crowd-card .time {
            font-weight: 700;
            font-size: 14.5px;
        }

        .crowd-card .meal {
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .crowd-level-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
        }

        .crowd-level-badge.low {
            background: #dcfce7;
            color: #16a34a;
        }

        .crowd-level-badge.medium {
            background: #fef9c3;
            color: #ca8a04;
        }

        .crowd-level-badge.high {
            background: #fee2e2;
            color: #dc2626;
        }

        .crowd-card .count {
            color: #6b7280;
            font-size: 12px;
            margin-top: 8px;
        }
    </style>
</head>

<body>
    <div class="dashboard-page">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="dashboard-header">
                <div>
                    <h1>Cafeteria Crowd Status</h1>
                    <p>Live crowd levels for today's pickup slots</p>
                </div>
                <div class="header-actions">
                    <button class="icon-btn" type="button"><i class="fa-regular fa-bell"></i></button>
                    <div class="user-chip">
                        <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                        <span><?= e($_SESSION['name']) ?></span>
                    </div>
                </div>
            </header>

            <?php if ($now_slot): ?>
                <div style="background:#f5f3ff; border:1px solid #ddd6fe; border-radius:12px; padding:16px 20px; margin-bottom:10px;">
                    <strong style="color:#7047f2;">Right now (<?= date('h:i A', strtotime($now_slot['start_time'])) ?>–<?= date('h:i A', strtotime($now_slot['end_time'])) ?>):</strong>
                    <span class="crowd-level-badge <?= $now_slot['crowd_class'] ?>"><?= $now_slot['crowd_color'] ?> <?= $now_slot['crowd_label'] ?> crowd</span>
                    <span style="color:#6b7280; font-size:13px; margin-left:8px;"><?= $now_slot['booked_count'] ?> / <?= $now_slot['max_orders'] ?> orders booked in this slot</span>
                </div>
            <?php else: ?>
                <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px; margin-bottom:10px; color:#6b7280;">
                    Cafeteria is currently outside operating hours.
                </div>
            <?php endif; ?>

            <?php
            $grouped = ['Breakfast' => [], 'Lunch' => [], 'Dinner' => []];
            foreach ($slots as $s) {
                $grouped[$s['meal_period']][] = $s;
            }
            $period_icons = ['Breakfast' => '🌅', 'Lunch' => '🍛', 'Dinner' => '🌙'];
            ?>

            <?php foreach ($grouped as $period => $period_slots): ?>
                <?php $is_current_period = ($period === $now_slot['meal_period'] ?? null); ?>
                <h3 style="margin:24px 0 4px; display:flex; align-items:center; gap:8px;">
                    <?= $period_icons[$period] ?> <?= $period ?>
                    <?php if ($now_slot && $now_slot['meal_period'] === $period): ?>
                        <span style="font-size:12px; background:#7047f2; color:white; padding:3px 10px; border-radius:12px;">Now Serving</span>
                    <?php endif; ?>
                </h3>
                <div class="crowd-grid">
                    <?php foreach ($period_slots as $s): ?>
                        <div class="crowd-card <?= $s['is_past'] ? 'past' : ($s['is_now'] ? 'current' : '') ?>">
                            <div class="time"><?= date('h:i A', strtotime($s['start_time'])) ?> – <?= date('h:i A', strtotime($s['end_time'])) ?></div>
                            <div class="meal"><?= $s['is_now'] ? 'Now' : ($s['is_past'] ? 'Passed' : 'Upcoming') ?></div>
                            <span class="crowd-level-badge <?= $s['crowd_class'] ?>"><?= $s['crowd_color'] ?> <?= $s['crowd_label'] ?></span>
                            <div class="count"><?= $s['booked_count'] ?> / <?= $s['max_orders'] ?> booked<?= $s['booked_count'] >= $s['max_orders'] ? ' — FULL' : '' ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

        </main>
    </div>
</body>

</html>