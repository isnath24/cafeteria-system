<?php
// user/Html/includes/sidebar.php — Shared student sidebar
$current = basename($_SERVER['PHP_SELF']);
?>

<!-- Toggle button is inserted into the page header by JS below, not floated on top of content -->

<aside class="sidebar" style="display:flex; flex-direction:column; background-color:#111827 !important;">
  <div class="brand">
    <img src="../Images/logo.png" alt="Cafeteria logo" />
    <div>
      <h2>Cafeteria</h2>
      <p>Pre-Order<br />System</p>
    </div>
  </div>
  <nav class="nav-menu">
    <a href="Dashboard.php" <?= $current === 'Dashboard.php'   ? 'class="active"' : '' ?>><i class="fa-solid fa-house"></i> Dashboard</a>
    <a href="Menu.php" <?= $current === 'Menu.php'        ? 'class="active"' : '' ?>><i class="fa-solid fa-table-list"></i> Menu</a>
    <a href="MyOrders.php" <?= $current === 'MyOrders.php'    ? 'class="active"' : '' ?>><i class="fa-solid fa-bag-shopping"></i> My Orders</a>
    <a href="TrackOrders.php" <?= $current === 'TrackOrders.php' ? 'class="active"' : '' ?>><i class="fa-solid fa-arrow-trend-up"></i> Track orders</a>
    <a href="Payments.php" <?= $current === 'Payments.php'    ? 'class="active"' : '' ?>><i class="fa-solid fa-credit-card"></i> Payments</a>
    <a href="Profile.php" <?= $current === 'Profile.php'     ? 'class="active"' : '' ?>><i class="fa-solid fa-user"></i> Profile</a>
    <a href="CrowdStatus.php" <?= $current === 'CrowdStatus.php' ? 'class="active"' : '' ?>><i class="fa-solid fa-chart-simple"></i> Crowd Status</a>
  </nav>
  <div style="margin-top:auto; border-top:1px solid rgba(255,255,255,0.15); padding-top:14px;">
    <a href="../../logout.php" style="color:#ffffff !important; text-decoration:none;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</aside>

<!-- Notifications System JS/CSS (Shared) -->
<style>
  /* Notification Badge */
  .notif-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 8px;
    height: 8px;
    border: 1.5px solid white;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15);
  }

  /* Dropdown Box */
  .notif-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 40px;
    width: 290px;
    background: white;
    border: 1px solid #e5e7eb;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    border-radius: 8px;
    z-index: 10000;
    padding: 10px 0;
    font-family: sans-serif;
    text-align: left;
  }

  .notif-dropdown.active {
    display: block;
  }

  .notif-header {
    margin: 0;
    padding: 0 14px 8px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13.5px;
    color: #1f2937;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .notif-clear-btn {
    background: none;
    border: none;
    color: #7047f2;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
  }

  .notif-clear-btn:hover {
    text-decoration: underline;
  }

  .notif-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 240px;
    overflow-y: auto;
  }

  .notif-item {
    padding: 10px 14px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 12px;
    color: #374151;
    line-height: 1.4;
    transition: background 0.2s;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .notif-item:hover {
    background: #f9fafb;
  }

  .notif-item.unread {
    background: #f5f3ff;
    border-left: 3px solid #7047f2;
  }

  .notif-item .notif-time {
    font-size: 10px;
    color: #9ca3af;
  }

  .notif-empty {
    padding: 16px;
    font-size: 12px;
    color: #6b7280;
    text-align: center;
  }
</style>
<script>
  document.addEventListener("DOMContentLoaded", function() {

    // ── Sidebar hamburger toggle (inline icon, aligned with the page title — matches admin style) ──
    var sb = document.querySelector('.sidebar');
    var header = document.querySelector('header.dashboard-header, header.menu-header, .cart-header');
    var SIDEBAR_WIDTH = 200; // px — sidebar width and content push amount, kept in sync

    if (sb && header) {
      // Insert the icon as the FIRST child of the header itself (not inside the title's own div),
      // so it sits to the left of "Welcome back..." without disturbing the heading/subtitle stacking
      var menuBtn = document.createElement('i');
      menuBtn.className = 'fa-solid fa-bars';
      menuBtn.style.cssText = 'cursor:pointer; font-size:22px; margin-right:16px; color:#111827; flex-shrink:0;';

      header.style.display = 'flex';
      header.style.alignItems = 'left';
      header.insertBefore(menuBtn, header.firstChild);

      var isOpen = false;
      var contentEl = sb.parentElement ? sb.parentElement.lastElementChild : null;

      function applySidebarState() {
        sb.style.setProperty('left', isOpen ? '0px' : (-SIDEBAR_WIDTH) + 'px', 'important');
        if (contentEl) {
          contentEl.style.setProperty('transition', 'margin-left 0.3s ease', 'important');
          contentEl.style.setProperty('margin-left', isOpen ? SIDEBAR_WIDTH + 'px' : '0px', 'important');
        }
      }

      sb.style.setProperty('position', 'fixed', 'important');
      sb.style.setProperty('top', '0px', 'important');
      sb.style.setProperty('width', SIDEBAR_WIDTH + 'px', 'important');
      sb.style.setProperty('height', '100%', 'important');
      sb.style.setProperty('transition', 'left 0.3s ease', 'important');
      sb.style.setProperty('z-index', '999998', 'important');
      applySidebarState();

      menuBtn.addEventListener('click', function() {
        isOpen = !isOpen;
        applySidebarState();
      });
    }

    // ── Notifications ──────────────────────────────────────────
    var bellIcon = document.querySelector('.fa-bell');
    if (!bellIcon) return;

    var bellBtn = bellIcon.closest('button') || bellIcon.closest('a');
    if (!bellBtn) return;

    // Wrap bell button to relative container
    var container = document.createElement('div');
    container.className = 'notif-bell-container';
    container.style.position = 'relative';
    container.style.display = 'inline-block';

    bellBtn.parentNode.insertBefore(container, bellBtn);
    container.appendChild(bellBtn);

    // Create Badge
    var badge = document.createElement('span');
    badge.className = 'notif-badge';
    badge.style.display = 'none';
    container.appendChild(badge);

    // Create Dropdown
    var dropdown = document.createElement('div');
    dropdown.className = 'notif-dropdown';
    dropdown.innerHTML = `
        <div class="notif-header">
            <span>Notifications</span>
            <button class="notif-clear-btn" id="notifClearAll">Clear All</button>
        </div>
        <ul class="notif-list" id="notifList">
            <li class="notif-empty">Loading...</li>
        </ul>
    `;
    container.appendChild(dropdown);

    // Toggle dropdown
    bellBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      var isActive = dropdown.classList.toggle('active');
      if (isActive) {
        markAllAsRead();
      }
    });

    document.addEventListener('click', function(e) {
      if (!container.contains(e.target)) {
        dropdown.classList.remove('active');
      }
    });

    // Functions
    function checkUnread() {
      fetch('ajax_notifications.php?action=get')
        .then(res => res.json())
        .then(data => {
          var unreadCount = data.filter(n => parseInt(n.is_read) === 0).length;
          if (unreadCount > 0) {
            badge.style.display = 'block';
          } else {
            badge.style.display = 'none';
          }

          // Populate list
          var list = document.getElementById('notifList');
          if (data.length === 0) {
            list.innerHTML = '<li class="notif-empty">No notifications yet.</li>';
            return;
          }

          list.innerHTML = data.map(n => {
            var isUnread = parseInt(n.is_read) === 0 ? 'unread' : '';
            var d = new Date(n.created_at);
            var timeStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {
              hour: '2-digit',
              minute: '2-digit'
            });
            return `
                        <li class="notif-item ${isUnread}">
                            <span>${escapeHtml(n.message)}</span>
                            <span class="notif-time">${timeStr}</span>
                        </li>
                    `;
          }).join('');
        })
        .catch(err => console.error("Error fetching notifications:", err));
    }

    function markAllAsRead() {
      fetch('ajax_notifications.php?action=read_all')
        .then(res => res.json())
        .then(res => {
          if (res.status === 'success') {
            badge.style.display = 'none';
            checkUnread();
          }
        });
    }

    function escapeHtml(str) {
      return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    document.getElementById('notifClearAll').addEventListener('click', function(e) {
      e.stopPropagation();
      fetch('ajax_notifications.php?action=clear')
        .then(res => res.json())
        .then(res => {
          if (res.status === 'success') {
            checkUnread();
          }
        });
    });

    // Check periodically + on load
    checkUnread();
    setInterval(checkUnread, 15000);
  });
</script>