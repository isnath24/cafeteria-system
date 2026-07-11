/**
 * admin/script.js — Shared Admin JavaScript
 * Contains UI helpers, sidebar toggles, toast notifications,
 * custom confirm dialogs, and chart drawing functions.
 */

document.addEventListener("DOMContentLoaded", function () {
    const menuIcon = document.getElementById("menuIcon");
    const sidebar = document.querySelector(".sidebar");

    if (menuIcon && sidebar) {
        menuIcon.addEventListener("click", function () {
            sidebar.classList.toggle("show-sidebar");
        });
    }

    // Auto-show toast from PHP flash message if present on body data attributes
    const flashType = document.body.dataset.flashType;
    const flashMsg  = document.body.dataset.flashMsg;
    if (flashType && flashMsg) {
        showToast(flashType, flashMsg);
    }
});

// Helper functions for status styling
function getOrderStatusClass(status) {
    if (status === "Processing") return "status-processing";
    if (status === "Ready") return "status-ready";
    if (status === "Completed") return "status-completed";
    if (status === "Cancelled") return "status-cancelled";
    return "";
}

function getPaymentStatusClass(status) {
    if (status === "Paid") return "payment-paid";
    if (status === "Failed") return "payment-failed";
    return "";
}

function getUserStatusClass(status) {
    if (status === "Active") return "user-active";
    if (status === "Pending") return "user-pending";
    if (status === "Suspended") return "user-suspended";
    return "";
}

/* =============================================
   TOAST NOTIFICATION SYSTEM
   ============================================= */

/**
 * Show a toast notification.
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {string} message
 * @param {string} [title]  Optional bold title line
 */
function showToast(type, message, title) {
    var container = document.getElementById("toast-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        document.body.appendChild(container);
    }

    var icons  = { success: "✅", error: "❌", warning: "⚠️", info: "ℹ️" };
    var titles = { success: "Success", error: "Error", warning: "Warning", info: "Info" };

    var toast = document.createElement("div");
    toast.className = "toast toast-" + type;
    toast.innerHTML =
        '<span class="toast-icon">' + (icons[type] || "ℹ️") + "</span>" +
        '<div class="toast-body">' +
            '<div class="toast-title">' + (title || titles[type] || "") + "</div>" +
            '<div class="toast-message">' + message + "</div>" +
        "</div>" +
        '<button class="toast-close" onclick="dismissToast(this.parentElement)">&#x00D7;</button>' +
        '<div class="toast-progress"></div>';

    container.appendChild(toast);

    // Auto-dismiss after 3.5s
    setTimeout(function () {
        dismissToast(toast);
    }, 3500);
}

function dismissToast(toast) {
    if (!toast || toast.classList.contains("hide")) return;
    toast.classList.add("hide");
    setTimeout(function () {
        if (toast.parentElement) toast.parentElement.removeChild(toast);
    }, 320);
}

/* =============================================
   CUSTOM CONFIRM DIALOG
   ============================================= */
(function () {
    function injectConfirmDialog() {
        if (document.getElementById("confirm-overlay")) return;
        var overlay = document.createElement("div");
        overlay.id = "confirm-overlay";
        overlay.innerHTML =
            '<div id="confirm-box">' +
                '<span id="confirm-icon">&#x1F5D1;&#xFE0F;</span>' +
                '<div id="confirm-title">Are you sure?</div>' +
                '<div id="confirm-message">This action cannot be undone.</div>' +
                '<div id="confirm-buttons">' +
                    '<button id="confirm-cancel">Cancel</button>' +
                    '<button id="confirm-ok">Yes, Delete</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        document.getElementById("confirm-cancel").onclick = function () {
            overlay.classList.remove("show");
        };
        overlay.addEventListener("click", function (e) {
            if (e.target === overlay) overlay.classList.remove("show");
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", injectConfirmDialog);
    } else {
        injectConfirmDialog();
    }
})();

/**
 * Show a custom confirmation dialog.
 * @param {object} opts  { icon, title, message, okText, onConfirm }
 */
function confirmDialog(opts) {
    var overlay = document.getElementById("confirm-overlay");
    if (!overlay) return;

    document.getElementById("confirm-icon").textContent    = opts.icon    || "🗑️";
    document.getElementById("confirm-title").textContent   = opts.title   || "Are you sure?";
    document.getElementById("confirm-message").textContent = opts.message || "This action cannot be undone.";
    document.getElementById("confirm-ok").textContent      = opts.okText  || "Yes, Delete";

    overlay.classList.add("show");

    document.getElementById("confirm-ok").onclick = function () {
        overlay.classList.remove("show");
        if (typeof opts.onConfirm === "function") opts.onConfirm();
    };
}

// Draw Dashboard Sales/Revenue Line Chart
function drawDashboardSalesChart(orderData, revenueData) {
    var canvas = document.getElementById("salesChart");
    if (!canvas) return;

    var ctx = canvas.getContext("2d");
    canvas.width = 350;
    canvas.height = 200;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    function drawLine(data, color) {
        ctx.strokeStyle = color;
        ctx.lineWidth = 3;
        ctx.beginPath();

        var maxVal = Math.max.apply(null, data.concat([1]));
        data.forEach(function (value, index) {
            var x = 45 + index * 45;
            var y = 165 - (value / maxVal) * 100;
            if (index === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });

        ctx.stroke();
    }

    ctx.strokeStyle = "#ddd";
    for (var i = 0; i < 4; i++) {
        var y = 165 - i * 40;
        ctx.beginPath();
        ctx.moveTo(35, y);
        ctx.lineTo(330, y);
        ctx.stroke();
    }

    drawLine(orderData, "#3d5afe");
    drawLine(revenueData, "#16a34a");
}

// Draw Reports Line Chart
function drawLineChart(canvas, labels, data, color) {
    if (!canvas) return;

    var ctx = canvas.getContext("2d");
    canvas.width = 400;
    canvas.height = 150;

    var max    = Math.max.apply(null, data.concat([1]));
    var left   = 42;
    var top    = 15;
    var width  = 340;
    var height = 100;

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = "#ddd";

    for (var i = 0; i <= 4; i++) {
        var y = top + i * 25;
        ctx.beginPath();
        ctx.moveTo(left, y);
        ctx.lineTo(390, y);
        ctx.stroke();
    }

    ctx.strokeStyle = color;
    ctx.lineWidth = 3;
    ctx.beginPath();

    data.forEach(function (value, index) {
        var x = left + (width / (data.length - 1)) * index;
        var y = top + height - (value / max) * height;
        if (index === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
    });

    ctx.stroke();
}

// Draw Payment Methods Pie Chart
function drawPaymentPieChart(cash, card, other) {
    var canvas = document.getElementById("paymentMethodChart");
    if (!canvas) return;

    var ctx   = canvas.getContext("2d");
    canvas.width  = 170;
    canvas.height = 130;

    var total  = cash + card + other || 1;
    var values = [cash, card, other];
    var colors = ["#41af55", "#2563eb", "#f59e0b"];
    var start  = -Math.PI / 2;

    values.forEach(function (value, index) {
        var angle = (value / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(70, 65);
        ctx.arc(70, 65, 50, start, start + angle);
        ctx.closePath();
        ctx.fillStyle = colors[index];
        ctx.fill();
        start += angle;
    });

    ctx.beginPath();
    ctx.arc(70, 65, 27, 0, Math.PI * 2);
    ctx.fillStyle = "white";
    ctx.fill();

    ctx.fillStyle = "black";
    ctx.font = "bold 16px Arial";
    ctx.fillText(total, 55, 63);
    ctx.font = "10px Arial";
    ctx.fillText("Total", 55, 80);

    var cashText  = document.getElementById("cashText");
    var cardText  = document.getElementById("cardText");
    var otherText = document.getElementById("otherText");

    if (cashText)  cashText.textContent  = cash  + " (" + Math.round((cash  / total) * 100) + "%)";
    if (cardText)  cardText.textContent  = card  + " (" + Math.round((card  / total) * 100) + "%)";
    if (otherText) otherText.textContent = other + " (" + Math.round((other / total) * 100) + "%)";
}

// CSV Export Utility
function exportCSV(fileName, data) {
    if (!data || data.length === 0) {
        showToast("warning", "There is no data available to export.", "Nothing to Export");
        return;
    }

    var headers = Object.keys(data[0]);
    var csv = headers.join(",") + "\n";

    data.forEach(function (row) {
        csv += headers.map(function (header) {
            var val = row[header] === null ? "" : String(row[header]);
            val = val.replace(/"/g, '""');
            return '"' + val + '"';
        }).join(",") + "\n";
    });

    var file    = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    var fileUrl = URL.createObjectURL(file);
    var link    = document.createElement("a");
    link.href     = fileUrl;
    link.download = fileName;
    link.click();
    URL.revokeObjectURL(fileUrl);

    showToast("success", fileName + " has been downloaded.", "Export Complete");
}
