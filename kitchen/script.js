/**
 * ============================================================
 * UWU CAFETERIA - KITCHEN MANAGEMENT SYSTEM
 * JavaScript - Shared functionality for all pages
 * ============================================================
 */

// ============================================================
// 1. SIDEBAR TOGGLE
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const menuToggle = document.getElementById('menuToggle');

    if (menuToggle && sidebar && mainContent) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
            mainContent.classList.toggle('shifted');
        });

        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const isSidebar = sidebar.contains(e.target);
                const isToggle = menuToggle.contains(e.target);
                if (!isSidebar && !isToggle && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                    mainContent.classList.remove('shifted');
                }
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                mainContent.classList.remove('shifted');
            }
        });

        document.querySelectorAll('.sidebar-menu li').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                    mainContent.classList.remove('shifted');
                }
            });
        });
    }
});

// ============================================================
// 2. LIVE DATE & TIME
// ============================================================
function updateDateTime() {
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];
    const timeStr = now.toTimeString().split(' ')[0];
    
    const datePart = document.getElementById('datePart');
    const timePart = document.getElementById('timePart');
    
    if (datePart) datePart.textContent = dateStr;
    if (timePart) timePart.textContent = timeStr;
}

updateDateTime();
setInterval(updateDateTime, 1000);

// ============================================================
// 3. DASHBOARD DATA
// ============================================================
const dashboardData = {
    stats: {
        ordersToday: 35,
        pending: 10,
        preparing: 8,
        ready: 17
    },
    recentOrders: [
       
    ],
    schedule: [
        
    ],
    stock: [
       
    ]
};

// ============================================================
// 4. ORDERS DATA
// ============================================================
const ordersData = {
    allOrders: [
        
    ],
    stats: {
        all: 15,
        pending: 5,
        preparing: 3,
        ready: 4,
        completed: 3
    }
};

// ============================================================
// 5. MEAL PREP DATA
// ============================================================
const mealPrepData = {
    items: [
       
    ]
};

// ============================================================
// 6. STOCK DATA (with minStock for Low Stock Alert)
// ============================================================
const stockData = {
    items: [
        
    ]
};

// ============================================================
// 7. LOW STOCK DATA (Filtered from stockData)
// ============================================================
function getLowStockItems() {
    return stockData.items.filter(item => item.remaining <= item.minStock);
}

// ============================================================
// 8. ORDERS STATE
// ============================================================
let currentFilter = 'all';
let currentSearch = '';
let currentPage = 1;
const itemsPerPage = 8;

// ============================================================
// 9. MEAL PREP STATE
// ============================================================
let currentStatusFilter = 'all';
let currentSearchQuery = '';

// ============================================================
// 10. DASHBOARD RENDER FUNCTIONS
// ============================================================

function renderStats() {
    const ordersToday = document.getElementById('ordersToday');
    const pendingOrders = document.getElementById('pendingOrders');
    const preparingOrders = document.getElementById('preparingOrders');
    const readyOrders = document.getElementById('readyOrders');
    
    if (ordersToday) ordersToday.textContent = dashboardData.stats.ordersToday;
    if (pendingOrders) pendingOrders.textContent = dashboardData.stats.pending;
    if (preparingOrders) preparingOrders.textContent = dashboardData.stats.preparing;
    if (readyOrders) readyOrders.textContent = dashboardData.stats.ready;
}

function renderRecentOrders() {
    const tbody = document.getElementById('recentOrdersBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';

    dashboardData.recentOrders.forEach(order => {
        const tr = document.createElement('tr');
        let statusClass = 'pending';
        const statusLower = order.status.toLowerCase();
        if (statusLower === 'preparing') statusClass = 'preparing';
        else if (statusLower === 'ready') statusClass = 'ready';

        tr.innerHTML = `
            <td>${order.id}</td>
            <td>${order.student}</td>
            <td>${order.item}</td>
            <td>${order.qty}</td>
            <td>${order.time}</td>
            <td><span class="status-badge ${statusClass}">${order.status}</span></td>
        `;
        tbody.appendChild(tr);
    });
}

function renderPieChart() {
    const pending = dashboardData.stats.pending;
    const preparing = dashboardData.stats.preparing;
    const ready = dashboardData.stats.ready;
    const total = pending + preparing + ready;

    const pieChart = document.getElementById('pieChart');
    const centerText = document.querySelector('.pie-chart .center-text');
    const pieLegend = document.querySelector('.pie-legend');
    
    if (!pieChart) return;

    if (total === 0) {
        pieChart.style.background = '#e0e7f0';
        if (centerText) centerText.innerHTML = '0<small>No orders</small>';
        if (pieLegend) pieLegend.innerHTML = '<span>No data available</span>';
        return;
    }

    let pPct = Math.round((pending / total) * 100);
    let prepPct = Math.round((preparing / total) * 100);
    let readyPct = Math.round((ready / total) * 100);

    const sum = pPct + prepPct + readyPct;
    if (sum !== 100) {
        const diff = 100 - sum;
        if (pPct >= prepPct && pPct >= readyPct) pPct += diff;
        else if (prepPct >= pPct && prepPct >= readyPct) prepPct += diff;
        else readyPct += diff;
    }

    const c1 = pPct;
    const c2 = pPct + prepPct;
    const gradient = `conic-gradient(
        #f59e42 0% ${c1}%,
        #4a6fa5 ${c1}% ${c2}%,
        #2ecc71 ${c2}% 100%
    )`;
    pieChart.style.background = gradient;

    let highestLabel = 'Pending';
    let highestPct = pPct;
    if (prepPct > highestPct) {
        highestPct = prepPct;
        highestLabel = 'Preparing';
    }
    if (readyPct > highestPct) {
        highestPct = readyPct;
        highestLabel = 'Ready';
    }
    if (centerText) {
        centerText.innerHTML = `${highestPct}%<small>${highestLabel}</small>`;
    }

    if (pieLegend) {
        pieLegend.innerHTML = `
            <span><span class="dot pending-dot"></span> Pending (${pPct}%)</span>
            <span><span class="dot preparing-dot"></span> Preparing (${prepPct}%)</span>
            <span><span class="dot ready-dot"></span> Ready (${readyPct}%)</span>
        `;
    }
}

function renderSchedule() {
    const container = document.getElementById('scheduleList');
    if (!container) return;
    
    container.innerHTML = '';

    dashboardData.schedule.forEach(item => {
        const div = document.createElement('div');
        div.className = 'schedule-item';
        div.innerHTML = `
            <span>${item.item}</span>
            <span class="count">${item.count} Orders</span>
        `;
        container.appendChild(div);
    });
}

function renderDashboardStock() {
    const container = document.getElementById('stockList');
    if (!container) return;
    
    container.innerHTML = '';

    dashboardData.stock.forEach(item => {
        const div = document.createElement('div');
        div.className = 'stock-item';
        div.innerHTML = `
            <span>${item.item}</span>
            <span class="stock-qty">${item.qty}</span>
        `;
        container.appendChild(div);
    });
}

function initDashboard() {
    renderStats();
    renderRecentOrders();
    renderPieChart();
    renderSchedule();
    renderDashboardStock();
    console.log('✅ Dashboard initialized successfully!');
}

// ============================================================
// 11. ORDERS RENDER FUNCTIONS
// ============================================================

function getFilteredOrders() {
    let filtered = [...ordersData.allOrders];

    if (currentFilter !== 'all') {
        filtered = filtered.filter(order => order.status === currentFilter);
    }

    if (currentSearch.trim() !== '') {
        const search = currentSearch.toLowerCase().trim();
        filtered = filtered.filter(order => 
            order.id.toLowerCase().includes(search) ||
            order.student.toLowerCase().includes(search) ||
            order.item.toLowerCase().includes(search)
        );
    }

    return filtered;
}

function getPaginatedOrders(filteredOrders) {
    const totalItems = filteredOrders.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    
    const start = (currentPage - 1) * itemsPerPage;
    const end = Math.min(start + itemsPerPage, totalItems);
    const pageItems = filteredOrders.slice(start, end);
    
    return {
        items: pageItems,
        total: totalItems,
        start: totalItems > 0 ? start + 1 : 0,
        end: end,
        totalPages: totalPages
    };
}

function updateStatusCards() {
    const stats = {
        all: ordersData.allOrders.length,
        pending: ordersData.allOrders.filter(o => o.status === 'pending').length,
        preparing: ordersData.allOrders.filter(o => o.status === 'preparing').length,
        ready: ordersData.allOrders.filter(o => o.status === 'ready').length,
        completed: ordersData.allOrders.filter(o => o.status === 'completed').length
    };
    
    const allCount = document.getElementById('allCount');
    const pendingCount = document.getElementById('pendingCount');
    const preparingCount = document.getElementById('preparingCount');
    const readyCount = document.getElementById('readyCount');
    const completedCount = document.getElementById('completedCount');
    
    if (allCount) allCount.textContent = stats.all;
    if (pendingCount) pendingCount.textContent = stats.pending;
    if (preparingCount) preparingCount.textContent = stats.preparing;
    if (readyCount) readyCount.textContent = stats.ready;
    if (completedCount) completedCount.textContent = stats.completed;
}

function renderOrders() {
    const tableBody = document.getElementById('ordersTableBody');
    if (!tableBody) return;
    
    const filtered = getFilteredOrders();
    const paginated = getPaginatedOrders(filtered);
    const orders = paginated.items;

    tableBody.innerHTML = '';

    if (orders.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center;padding:40px 0;color:#6b7a8f;">
                    <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                    No orders found
                </td>
            </tr>
        `;
    } else {
        orders.forEach(order => {
            const tr = document.createElement('tr');
            const statusDisplay = order.status.charAt(0).toUpperCase() + order.status.slice(1);
            
            let actionHtml = '';
            if (order.status === 'pending') {
                actionHtml = `<button class="action-btn start" data-id="${order.id}">Start</button>`;
            } else if (order.status === 'preparing') {
                actionHtml = `<button class="action-btn mark-ready" data-id="${order.id}">Mark Ready</button>`;
            } else if (order.status === 'ready') {
                actionHtml = `<button class="action-btn completed" disabled>Completed</button>`;
            } else if (order.status === 'completed') {
                actionHtml = `<button class="action-btn completed" disabled>✓ Done</button>`;
            }

            tr.innerHTML = `
                <td>${order.id}</td>
                <td>${order.student}</td>
                <td>${order.item}</td>
                <td>${order.qty}</td>
                <td>${order.time}</td>
                <td><span class="status-badge ${order.status}">${statusDisplay}</span></td>
                <td>${actionHtml}</td>
            `;
            tableBody.appendChild(tr);
        });
    }

    const startRange = document.getElementById('startRange');
    const endRange = document.getElementById('endRange');
    const totalOrders = document.getElementById('totalOrders');
    const currentPageDisplay = document.getElementById('currentPage');
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    
    if (totalOrders) totalOrders.textContent = paginated.total;
    if (startRange) startRange.textContent = paginated.start;
    if (endRange) endRange.textContent = paginated.end;
    if (currentPageDisplay) currentPageDisplay.textContent = currentPage;
    
    if (prevPageBtn) prevPageBtn.disabled = currentPage <= 1;
    if (nextPageBtn) nextPageBtn.disabled = currentPage >= paginated.totalPages || paginated.total === 0;

    document.querySelectorAll('.action-btn:not(:disabled)').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.id;
            const action = this.textContent.trim();
            handleOrderAction(orderId, action);
        });
    });
}

function handleOrderAction(orderId, action) {
    const order = ordersData.allOrders.find(o => o.id === orderId);
    if (!order) return;

    let newStatus = '';
    let message = '';

    if (action === 'Start' && order.status === 'pending') {
        newStatus = 'preparing';
        message = `✅ Order ${orderId} status updated to "Preparing"`;
    } else if (action === 'Mark Ready' && order.status === 'preparing') {
        newStatus = 'ready';
        message = `✅ Order ${orderId} status updated to "Ready"`;
    } else {
        return;
    }

    order.status = newStatus;
    updateOrdersStats();
    updateStatusCards();
    renderOrders();
    showNotification(message);
}

function updateOrdersStats() {
    const stats = {
        all: ordersData.allOrders.length,
        pending: ordersData.allOrders.filter(o => o.status === 'pending').length,
        preparing: ordersData.allOrders.filter(o => o.status === 'preparing').length,
        ready: ordersData.allOrders.filter(o => o.status === 'ready').length,
        completed: ordersData.allOrders.filter(o => o.status === 'completed').length
    };
    ordersData.stats = stats;
}

function initOrdersPage() {
    updateStatusCards();
    renderOrders();
    
    const statusCards = document.querySelectorAll('.status-card');
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterSelect');
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    
    if (statusCards.length > 0) {
        statusCards.forEach(card => {
            card.addEventListener('click', function() {
                statusCards.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                currentPage = 1;
                if (filterSelect) filterSelect.value = currentFilter;
                renderOrders();
            });
        });
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            currentSearch = this.value;
            currentPage = 1;
            renderOrders();
        });
    }
    
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            currentFilter = this.value;
            currentPage = 1;
            statusCards.forEach(card => {
                card.classList.toggle('active', card.dataset.filter === currentFilter);
            });
            renderOrders();
        });
    }
    
    if (prevPageBtn) {
        prevPageBtn.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                renderOrders();
            }
        });
    }
    
    if (nextPageBtn) {
        nextPageBtn.addEventListener('click', function() {
            const filtered = getFilteredOrders();
            const totalPages = Math.ceil(filtered.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderOrders();
            }
        });
    }
    
    console.log('✅ Orders page initialized successfully!');
}

// ============================================================
// 12. MEAL PREP RENDER FUNCTIONS
// ============================================================

function getFilteredMealItems() {
    let filtered = [...mealPrepData.items];

    if (currentStatusFilter !== 'all') {
        filtered = filtered.filter(item => item.status === currentStatusFilter);
    }

    if (currentSearchQuery.trim() !== '') {
        const query = currentSearchQuery.toLowerCase().trim();
        filtered = filtered.filter(item => 
            item.foodItem.toLowerCase().includes(query)
        );
    }

    return filtered;
}

function renderMealPrepTable() {
    const tableBody = document.getElementById('mealPrepTableBody');
    if (!tableBody) return;
    
    const filtered = getFilteredMealItems();
    
    tableBody.innerHTML = '';

    if (filtered.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center;padding:40px 0;color:#6b7a8f;">
                    <i class="fas fa-utensils" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                    No meal items found
                </td>
            </tr>
        `;
        return;
    }

    filtered.forEach(item => {
        const tr = document.createElement('tr');
        
        let statusDisplay = 'Pending';
        let statusClass = 'pending';
        let actionHtml = '';
        
        if (item.status === 'cooking') {
            statusDisplay = 'Cooking';
            statusClass = 'cooking';
            actionHtml = `
                <button class="action-btn ready-btn" data-id="${item.id}">
                    <i class="fas fa-check"></i> Ready
                </button>
            `;
        } else if (item.status === 'ready') {
            statusDisplay = 'Ready';
            statusClass = 'ready';
            actionHtml = `
                <button class="action-btn completed-btn" disabled>
                    <i class="fas fa-check-double"></i> Completed
                </button>
            `;
        } else {
            statusDisplay = 'Pending';
            statusClass = 'pending';
            actionHtml = `
                <button class="action-btn cooking" data-id="${item.id}">
                    <i class="fas fa-fire"></i> Start Cooking
                </button>
            `;
        }

        tr.innerHTML = `
            <td><span style="font-weight:600;color:#0a1a3a;">${item.foodItem}</span></td>
            <td><span style="font-weight:600;color:#4a6fa5;">${item.total}</span></td>
            <td><span style="font-weight:600;color:#f59e42;">${item.preparing}</span></td>
            <td><span style="font-weight:600;color:#2ecc71;">${item.ready}</span></td>
            <td>${actionHtml}</td>
        `;
        tableBody.appendChild(tr);
    });

    document.querySelectorAll('.action-btn:not(:disabled)').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = parseInt(this.dataset.id);
            const action = this.textContent.trim();
            handleMealAction(itemId, action);
        });
    });
}

function handleMealAction(itemId, action) {
    const item = mealPrepData.items.find(i => i.id === itemId);
    if (!item) return;

    let newStatus = '';
    let message = '';

    if (action.includes('Start Cooking') && item.status === 'pending') {
        newStatus = 'cooking';
        item.preparing = Math.min(item.preparing + 1, item.total);
        message = `✅ Started cooking ${item.foodItem}`;
    } else if (action.includes('Ready') && item.status === 'cooking') {
        newStatus = 'ready';
        item.preparing = Math.max(item.preparing - 1, 0);
        item.ready = Math.min(item.ready + 1, item.total);
        message = `✅ ${item.foodItem} is now ready!`;
    } else {
        return;
    }

    item.status = newStatus;
    renderMealPrepTable();
    showNotification(message);
}

function initMealPrepPage() {
    renderMealPrepTable();
    
    const searchInput = document.getElementById('searchPrepInput');
    const statusFilter = document.getElementById('statusFilterSelect');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            currentSearchQuery = this.value;
            renderMealPrepTable();
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            currentStatusFilter = this.value;
            renderMealPrepTable();
        });
    }
    
    console.log('✅ Meal Preparation page initialized successfully!');
}

// ============================================================
// 13. STOCK MANAGEMENT RENDER FUNCTIONS
// ============================================================

function renderStockTable() {
    const tableBody = document.getElementById('stockTableBody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';

    stockData.items.forEach(item => {
        const tr = document.createElement('tr');
        
        let statusClass = 'good';
        let statusText = 'Good';
        const remaining = item.remaining;
        const total = item.currentStock;
        const percentage = total > 0 ? (remaining / total) * 100 : 0;
        
        if (percentage <= 25) {
            statusClass = 'low';
            statusText = '⚠️ Low';
        } else if (percentage <= 50) {
            statusClass = 'medium';
            statusText = '⚠️ Medium';
        }

        tr.innerHTML = `
            <td><span style="font-weight:600;color:#0a1a3a;">${item.foodItem}</span></td>
            <td><span style="font-weight:600;color:#4a6fa5;">${item.currentStock}</span></td>
            <td><span style="font-weight:600;color:#f59e42;">${item.usedToday}</span></td>
            <td>
                <span style="font-weight:600;color:#2ecc71;">${item.remaining}</span>
                <span class="stock-status ${statusClass}">${statusText}</span>
            </td>
            <td>${item.unit}</td>
            <td style="font-size:13px;color:#6b7a8f;">${item.lastUpdated}</td>
            <td>
                <button class="action-btn update-stock" data-id="${item.id}">
                    <i class="fas fa-edit"></i> Update
                </button>
            </td>
        `;
        tableBody.appendChild(tr);
    });

    document.querySelectorAll('.action-btn.update-stock').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = parseInt(this.dataset.id);
            openUpdateModal(itemId);
        });
    });
}

function openUpdateModal(itemId) {
    const item = stockData.items.find(i => i.id === itemId);
    if (!item) return;

    let modal = document.querySelector('.modal-overlay');
    if (!modal) {
        modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-box">
                <button class="modal-close-btn" id="modalCloseBtn">
                    <i class="fas fa-times"></i>
                </button>
                <h2><i class="fas fa-boxes"></i> Update Stock</h2>
                <p class="modal-subtitle">Update stock levels for <strong id="modalItemName"></strong></p>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Current Stock <span class="required">*</span></label>
                        <input type="number" id="modalCurrentStock" min="0" placeholder="Enter current stock quantity" />
                        <div class="input-hint">Enter the total available stock</div>
                    </div>
                    <div class="form-group">
                        <label>Used Today <span class="required">*</span></label>
                        <input type="number" id="modalUsedToday" min="0" placeholder="Enter quantity used today" />
                        <div class="input-hint">Enter how much was used today</div>
                    </div>
                    <div class="form-group">
                        <label>Minimum Stock Level</label>
                        <input type="number" id="modalMinStock" min="0" placeholder="Enter minimum stock level" />
                        <div class="input-hint">Alert when stock falls below this level</div>
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <input type="text" id="modalUnit" readonly />
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button class="btn-cancel" id="modalCancelBtn">Cancel</button>
                    <button class="btn-save" id="modalSaveBtn">Save Changes</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('modalCloseBtn').addEventListener('click', closeModal);
        document.getElementById('modalCancelBtn').addEventListener('click', closeModal);
        document.getElementById('modalSaveBtn').addEventListener('click', function() {
            const id = parseInt(this.dataset.itemId);
            saveStockUpdate(id);
        });
        
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });
    }

    document.getElementById('modalItemName').textContent = item.foodItem;
    document.getElementById('modalCurrentStock').value = item.currentStock;
    document.getElementById('modalUsedToday').value = item.usedToday;
    document.getElementById('modalMinStock').value = item.minStock || 0;
    document.getElementById('modalUnit').value = item.unit;
    document.getElementById('modalSaveBtn').dataset.itemId = item.id;

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function saveStockUpdate(itemId) {
    const item = stockData.items.find(i => i.id === itemId);
    if (!item) return;

    const currentStockInput = document.getElementById('modalCurrentStock');
    const usedTodayInput = document.getElementById('modalUsedToday');
    const minStockInput = document.getElementById('modalMinStock');

    const currentStock = parseInt(currentStockInput.value);
    const usedToday = parseInt(usedTodayInput.value);
    const minStock = parseInt(minStockInput.value) || 0;

    if (isNaN(currentStock) || currentStock < 0) {
        showNotification('⚠️ Please enter a valid stock quantity');
        currentStockInput.focus();
        currentStockInput.style.borderColor = '#e74c3c';
        setTimeout(() => { currentStockInput.style.borderColor = ''; }, 3000);
        return;
    }

    if (isNaN(usedToday) || usedToday < 0) {
        showNotification('⚠️ Please enter a valid used quantity');
        usedTodayInput.focus();
        usedTodayInput.style.borderColor = '#e74c3c';
        setTimeout(() => { usedTodayInput.style.borderColor = ''; }, 3000);
        return;
    }

    if (usedToday > currentStock) {
        showNotification('⚠️ Used today cannot exceed current stock');
        usedTodayInput.focus();
        usedTodayInput.style.borderColor = '#e74c3c';
        setTimeout(() => { usedTodayInput.style.borderColor = ''; }, 3000);
        return;
    }

    const oldStock = item.currentStock;
    item.currentStock = currentStock;
    item.usedToday = usedToday;
    item.remaining = currentStock - usedToday;
    item.minStock = minStock;
    
    const now = new Date();
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const dateStr = `${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    item.lastUpdated = `${dateStr}, ${timeStr}`;

    closeModal();
    renderStockTable();

    const change = currentStock - oldStock;
    let changeText = '';
    if (change > 0) {
        changeText = `(+${change} ${item.unit})`;
    } else if (change < 0) {
        changeText = `(${change} ${item.unit})`;
    }
    showNotification(`✅ Stock updated for ${item.foodItem} ${changeText}`);
}

function initStockPage() {
    renderStockTable();
    
    const updateStockBtn = document.getElementById('updateStockBtn');
    if (updateStockBtn) {
        updateStockBtn.addEventListener('click', function() {
            const totalItems = stockData.items.length;
            const lowItems = stockData.items.filter(item => {
                const percentage = item.currentStock > 0 ? (item.remaining / item.currentStock) * 100 : 0;
                return percentage <= 25;
            }).length;
            const mediumItems = stockData.items.filter(item => {
                const percentage = item.currentStock > 0 ? (item.remaining / item.currentStock) * 100 : 0;
                return percentage > 25 && percentage <= 50;
            }).length;
            
            showNotification(`📊 Total: ${totalItems} items | Low: ${lowItems} | Medium: ${mediumItems}`);
        });
    }
    
    console.log('✅ Stock Management page initialized successfully!');
}

// ============================================================
// 14. LOW STOCK ALERT RENDER FUNCTIONS
// ============================================================

function renderLowStockTable() {
    const tableBody = document.getElementById('lowStockTableBody');
    if (!tableBody) return;
    
    const lowItems = getLowStockItems();
    
    tableBody.innerHTML = '';

    if (lowItems.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center;padding:40px 0;color:#6b7a8f;">
                    <i class="fas fa-check-circle" style="font-size:24px;display:block;margin-bottom:8px;color:#2ecc71;"></i>
                    All items are above minimum stock level
                </td>
            </tr>
        `;
        // Update info bar
        const lowStockCount = document.getElementById('lowStockCount');
        if (lowStockCount) lowStockCount.textContent = '0';
        return;
    }

    lowItems.forEach(item => {
        const tr = document.createElement('tr');
        
        const shortage = item.minStock - item.remaining;
        const statusClass = shortage > 5 ? 'critical' : 'warning';
        const statusText = shortage > 5 ? '⚠️ Critical' : '⚠️ Low Stock';

        tr.innerHTML = `
            <td><span style="font-weight:600;color:#0a1a3a;">${item.foodItem}</span></td>
            <td><span style="font-weight:600;color:#f59e42;">${item.remaining} ${item.unit}</span></td>
            <td><span style="font-weight:600;color:#e74c3c;">${item.minStock} ${item.unit}</span></td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td>
                <button class="action-btn restock" data-id="${item.id}">
                    <i class="fas fa-plus-circle"></i> Restock
                </button>
            </td>
        `;
        tableBody.appendChild(tr);
    });

    // Update info bar
    const lowStockCount = document.getElementById('lowStockCount');
    if (lowStockCount) lowStockCount.textContent = lowItems.length;

    document.querySelectorAll('.action-btn.restock').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = parseInt(this.dataset.id);
            openUpdateModal(itemId);
        });
    });
}

function initLowStockPage() {
    renderLowStockTable();
    console.log('✅ Low Stock Alert page initialized successfully!');
}

// ============================================================
// 15. NOTIFICATION FUNCTION
// ============================================================

function showNotification(message) {
    let notification = document.querySelector('.notification-toast');
    if (notification) {
        notification.remove();
    }

    notification = document.createElement('div');
    notification.className = 'notification-toast';
    const isError = message.includes('⚠️');
    const iconColor = isError ? '#e74c3c' : '#2ecc71';
    const borderColor = isError ? '#e74c3c' : '#2ecc71';
    notification.style.borderLeftColor = borderColor;
    notification.innerHTML = `
        <i class="fas ${isError ? 'fa-exclamation-circle' : 'fa-check-circle'}" style="color:${iconColor};font-size:18px;"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ============================================================
// 16. NAVIGATION HELPERS
// ============================================================

function navigateTo(page) {
    const pages = {
    'dashboard': 'index.php',
    'orders': 'orders.php',
    'meal-prep': 'meal-prep.php',
    'stock': 'stock.php',
    'low-stock': 'low-stock.php'
};
    const url = pages[page];
    if (url) {
        window.location.href = url;
    }
}

function getCurrentPage() {
    const path = window.location.pathname;
    const page = path.split('/').pop().split('.')[0] || 'index';
    const pageMap = {
        'index': 'dashboard',
        'orders': 'orders',
        'meal-prep': 'meal-prep',
        'stock': 'stock',
        'low-stock': 'low-stock'
    };
    return pageMap[page] || 'dashboard';
}

// ============================================================
// 17. VIEW ALL LINKS
// ============================================================

const viewAllOrders = document.getElementById('viewAllOrders');
if (viewAllOrders) {
    viewAllOrders.addEventListener('click', function(e) {});
}

const viewAllStock = document.getElementById('viewAllStock');
if (viewAllStock) {
    viewAllStock.addEventListener('click', function(e) {});
}

// ============================================================
// 18. KEYBOARD SHORTCUTS
// ============================================================

document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === '1') { e.preventDefault(); navigateTo('dashboard'); }
    if (e.ctrlKey && e.key === '2') { e.preventDefault(); navigateTo('orders'); }
    if (e.ctrlKey && e.key === '3') { e.preventDefault(); navigateTo('meal-prep'); }
    if (e.ctrlKey && e.key === '4') { e.preventDefault(); navigateTo('stock'); }
    if (e.ctrlKey && e.key === '5') { e.preventDefault(); navigateTo('low-stock'); }
});

// ============================================================
// 19. UPDATE DASHBOARD DATA
// ============================================================

function updateDashboardData(newData) {
    if (newData.stats) {
        dashboardData.stats = { ...dashboardData.stats, ...newData.stats };
    }
    if (newData.recentOrders) {
        dashboardData.recentOrders = newData.recentOrders;
    }
    if (newData.schedule) {
        dashboardData.schedule = newData.schedule;
    }
    if (newData.stock) {
        dashboardData.stock = newData.stock;
    }

    renderStats();
    renderRecentOrders();
    renderPieChart();
    renderSchedule();
    renderDashboardStock();
}

function syncDashboardFromOrders() {
    const stats = {
        all: ordersData.allOrders.length,
        pending: ordersData.allOrders.filter(o => o.status === 'pending').length,
        preparing: ordersData.allOrders.filter(o => o.status === 'preparing').length,
        ready: ordersData.allOrders.filter(o => o.status === 'ready').length,
        completed: ordersData.allOrders.filter(o => o.status === 'completed').length
    };
    
    dashboardData.stats.ordersToday = stats.all;
    dashboardData.stats.pending = stats.pending;
    dashboardData.stats.preparing = stats.preparing;
    dashboardData.stats.ready = stats.ready;
    
    const recent = [...ordersData.allOrders].slice(-5).reverse();
    dashboardData.recentOrders = recent.map(order => ({
        ...order,
        status: order.status.charAt(0).toUpperCase() + order.status.slice(1)
    }));
}

// ============================================================
// 20. INITIALIZE BASED ON CURRENT PAGE
// ============================================================

function initializePage() {
    // Disabled: this used to inject hardcoded mock data (fake student names, fake orders)
    // into the same table IDs that our real PHP pages now render server-side.
    // All 5 kitchen pages (dashboard, orders, meal-prep, stock, low-stock) now get
    // their real data directly from the database via PHP — this function must do nothing.
}

// ============================================================
// 21. EXPOSE FUNCTIONS TO GLOBAL SCOPE
// ============================================================

window.dashboardData = dashboardData;
window.ordersData = ordersData;
window.mealPrepData = mealPrepData;
window.stockData = stockData;
window.getLowStockItems = getLowStockItems;
window.updateDashboardData = updateDashboardData;
window.syncDashboardFromOrders = syncDashboardFromOrders;
window.navigateTo = navigateTo;
window.getCurrentPage = getCurrentPage;
window.renderStats = renderStats;
window.renderRecentOrders = renderRecentOrders;
window.renderPieChart = renderPieChart;
window.renderSchedule = renderSchedule;
window.renderDashboardStock = renderDashboardStock;
window.renderOrders = renderOrders;
window.renderMealPrepTable = renderMealPrepTable;
window.renderStockTable = renderStockTable;
window.renderLowStockTable = renderLowStockTable;
window.updateStatusCards = updateStatusCards;
window.handleOrderAction = handleOrderAction;
window.handleMealAction = handleMealAction;
window.openUpdateModal = openUpdateModal;
window.closeModal = closeModal;
window.saveStockUpdate = saveStockUpdate;
window.showNotification = showNotification;

// ============================================================
// 22. START THE APPLICATION
// ============================================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePage);
} else {
    initializePage();
}

console.log('🚀 UWU Cafeteria System loaded successfully!');
console.log('📊 Current page:', getCurrentPage());
console.log('💡 Press Ctrl+1-5 to navigate between pages');