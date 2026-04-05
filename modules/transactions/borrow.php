<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// 1. PAGINATION LOGIC
$limit = 14;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 2. STATS QUERY
$stats_query = "SELECT COUNT(DISTINCT th.borrower_id) as total_borrowers, COUNT(ti.item_record_id) as total_items 
                FROM Transaction_Items ti 
                JOIN Transaction_Headers th ON ti.header_id = th.header_id 
                WHERE ti.item_status = 'Borrowed'";
                
$stats_result = @mysqli_query($conn, $stats_query);
$stats_data = mysqli_fetch_assoc($stats_result);
$total_borrowers_count = $stats_data['total_borrowers'] ?? 0;
$total_items_count = $stats_data['total_items'] ?? 0;

$total_pages = ceil($total_borrowers_count / $limit);

// 3. DATA FETCH QUERY
$query = "SELECT 
            b.borrower_id, b.full_name, b.id_number, b.department, b.contact_no,
            th.header_id, th.borrow_date,
            MIN(ti.due_date) as earliest_due,
            COUNT(ti.item_record_id) as active_item_count
          FROM Borrowers b
          JOIN Transaction_Headers th ON b.borrower_id = th.borrower_id
          JOIN Transaction_Items ti ON th.header_id = ti.header_id
          WHERE ti.item_status = 'Borrowed'
          GROUP BY th.header_id
          ORDER BY b.full_name ASC
          LIMIT $limit OFFSET $offset";

$items_result = @mysqli_query($conn, $query); 

// 4. FETCH EQUIPMENT FOR AUTOCOMPLETE
$equip_query = "SELECT equipment_id, name, storage_location, available_qty 
                FROM equipment 
                WHERE available_qty > 0";
$equip_result = mysqli_query($conn, $equip_query);
$equipment_list = [];
if($equip_result) {
    while($e = mysqli_fetch_assoc($equip_result)) {
        $equipment_list[] = $e;
    }
}
?>

<style>
/* Scoped to borrow module - all vars use --brw- prefix to prevent global bleed */
:root {
    --brw-bg:          #ecefec;
    --brw-hover:       #8faadc;
    --brw-buttons:     #0c1f3f;
    --brw-border:      rgba(0, 0, 0, 0.05);
    --brw-row-even:    #f0f4f9;
    --brw-accent-red:  #e11d48;
    --brw-muted:       #64748b;
    --brw-status-ok:   #10b981;
    --brw-status-warn: #f59e0b;
}

/* Container & Layout */
.borrow-container {
    font-family: 'Inter', sans-serif;
    background: var(--brw-bg);
    padding: 40px;
    border-radius: 0 0 8px 8px;
}

.borrow-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.borrow-header-text {
    font-size: 14px;
    color: #475569;
    margin: 0;
}

.borrow-btn-primary {
    background: var(--brw-buttons);
    color: white;
    padding: 8px 20px;
    font-size: 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.borrow-divider {
    border: none;
    border-bottom: 1px solid var(--brw-border);
    margin-bottom: 24px;
}

/* Table Styling */
.borrow-table-wrapper {
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
}

.borrow-table {
    width: 100%;
    border-collapse: collapse;
}

.borrow-table th {
    background: var(--brw-hover);
    color: #ecefec;
    font-size: 13px;
    font-weight: 400;
    padding: 10px 20px;
    text-transform: uppercase;
    text-align: left;
}

.borrow-table td {
    padding: 10px 20px;
    font-size: 14px;
    color: #000000;
    border-bottom: 1px solid var(--brw-border);
    vertical-align: middle;
    position: relative;
}

.borrow-table tbody tr.main-row:nth-child(even) { background-color: var(--brw-row-even); }

.status-badge {
    background: #e0e7ff;
    color: #4338ca;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
}

/* Details Expansion */
.expansion-row { background-color: #f8fafc !important; }
.expansion-content { padding: 24px 40px; border-left: 4px solid var(--brw-buttons); }
.return-form-grid { display: grid; grid-template-columns: 2fr .6fr; gap: 40px; }
.detail-label { font-size: 11px; font-weight: 700; color: var(--brw-muted); text-transform: uppercase; margin-bottom: 15px; display: block; }

.item-return-entry {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
}

.item-name-group { display: flex; flex-direction: column; }
.item-main-name { font-size: 13px; font-weight: 600; color: #1e293b; }
.due-tag { font-size: 11px; color: var(--brw-muted); }
.overdue { color: var(--brw-accent-red); font-weight: 700; }

.item-return-select {
    padding: 5px 10px;
    font-size: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #fff;
    outline: none;
}

.btn-process-return {
    background: var(--brw-buttons);
    color: white;
    border: none;
    padding: 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    margin-top: 15px;
}

/* Actions */
.actions-cell { display: flex; gap: 8px; align-items: center; }
.btn-view-items, .btn-quick-add {
    padding: 4px 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid var(--brw-border);
    background: white;
}

/* ─── Global Quick-Add Panel ─── */
#qa-panel {
    display: none;
    position: fixed;
    width: 320px;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.18), 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    z-index: 99999;
    padding: 0;
    font-family: 'Inter', sans-serif;
    animation: qaPop 0.18s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}
@keyframes qaPop {
    from { opacity: 0; transform: scale(0.92) translateY(6px); }
    to   { opacity: 1; transform: scale(1)   translateY(0); }
}
#qa-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px 10px;
    border-bottom: 1px solid #f1f5f9;
}
#qa-panel-title {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: 0.01em;
}
#qa-panel-close {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    display: flex;
    align-items: center;
    padding: 2px;
    border-radius: 4px;
    transition: color 0.15s;
}
#qa-panel-close:hover { color: #ef4444; }
#qa-panel-body { padding: 12px 16px; display: flex; flex-direction: column; min-height: 200px; }

/* Search box */
.qa-search-wrap { position: relative; margin-bottom: 10px; }
#qa-search-input {
    width: 100%;
    padding: 9px 12px;
    font-size: 12px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s;
    color: #0f172a;
}
#qa-search-input:focus { border-color: #0c1f3f; }
#qa-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    max-height: 160px;
    overflow-y: auto;
    z-index: 100000;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}
.qa-drop-item {
    padding: 9px 14px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f8fafc;
    transition: background 0.12s;
}
.qa-drop-item:hover { background: #f1f5f9; }
.qa-drop-stock { font-size: 11px; color: #10b981; font-weight: 600; }
.qa-no-results { padding: 12px 14px; font-size: 12px; color: #94a3b8; text-align: center; }

/* Staged chips */
#qa-staged-label {
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
    display: none;
}
#qa-chips { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; max-height: 140px; overflow-y: auto; }
.qa-chip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f0f4f8;
    border: 1px solid #e2e8f0;
    padding: 7px 10px;
    border-radius: 6px;
    font-size: 12px;
    color: #1e293b;
}
.qa-chip-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-right: 8px; }
.qa-chip-x {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    font-size: 16px;
    line-height: 1;
    padding: 0 2px;
    transition: color 0.15s;
}
.qa-chip-x:hover { color: #ef4444; }

/* Confirm button */
#qa-confirm-btn {
    width: 100%;
    padding: 10px;
    background: var(--brw-buttons);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
    margin-top: 8px;
}
#qa-confirm-btn:hover { opacity: 0.88; }
#qa-confirm-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Pagination */
.saas-pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--brw-border); }
.saas-pages { display: flex; gap: 8px; }
.saas-page-link { min-width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; text-decoration: none; color: #475569; font-size: 13px; font-weight: 700; border-radius: 4px; }
.saas-page-link.active { background: var(--brw-buttons); color: white; }
.saas-nav-btn { display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: white; color: var(--brw-buttons); border: 1px solid var(--brw-border); border-radius: 6px; font-size: 13px; font-weight: 700; text-decoration: none; }
</style>

<div class="borrow-container">
    <div class="borrow-header-row">
        <p class="borrow-header-text">
            Tracking <b><?php echo number_format($total_items_count); ?></b> items across <b><?php echo number_format($total_borrowers_count); ?></b> active borrowers.
        </p>
        <button class="borrow-btn-primary" onclick="toggleBorrowModal(true)">
            <span class="material-symbols-outlined" style="font-size: 18px;">add</span> New Transaction
        </button>
    </div>

    <hr class="borrow-divider">

    <div class="borrow-table-wrapper">
        <table class="borrow-table">
            <thead>
                <tr>
                    <th style="width: 15%;">ID Number</th>
                    <th style="width: 25%;">Borrower Name</th>
                    <th style="width: 30%;">Department</th>
                    <th style="width: 15%;">Status</th>
                    <th style="width: 25%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($items_result && mysqli_num_rows($items_result) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($items_result)): 
                        $hid = $row['header_id'];
                        $is_overdue = strtotime($row['earliest_due']) < time();
                    ?>
                    <tr class="main-row">
                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td><span class="status-badge"><?php echo $row['active_item_count']; ?> Items Out</span></td>
                        <td style="overflow: visible;">
                            <div class="actions-cell">
                                <button class="btn-view-items" onclick="toggleRow('details-<?php echo $hid; ?>')">
                                    <span class="material-symbols-outlined" style="font-size: 14px;">visibility</span> Details
                                </button>
                                
                                <button class="btn-quick-add" onclick="qaOpen(event, <?php echo $hid; ?>, <?php echo $row['borrower_id']; ?>)">
                                    <span class="material-symbols-outlined" style="font-size: 14px;">add_circle</span> Add
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr id="details-<?php echo $hid; ?>" class="expansion-row" style="display: none;">
                        <td colspan="5">
                            <form action="modules/transactions/process_return.php" method="POST" class="expansion-content" onsubmit="return confirm('Process return?')">
                                <input type="hidden" name="header_id" value="<?php echo $hid; ?>">
                                <div class="return-form-grid">
                                    <div>
                                        <span class="detail-label">Verify Item Condition</span>
                                        <?php 
                                        $item_query = "SELECT ti.item_record_id, e.name, ti.due_date 
                                                       FROM Transaction_Items ti 
                                                       JOIN Equipment e ON ti.equipment_id = e.equipment_id 
                                                       WHERE ti.header_id = $hid AND ti.item_status = 'Borrowed'";
                                        $item_res = mysqli_query($conn, $item_query);
                                        while($item = mysqli_fetch_assoc($item_res)):
                                            $item_overdue = strtotime($item['due_date']) < time();
                                        ?>
                                        <div class="item-return-entry">
                                            <div class="item-name-group">
                                                <span class="item-main-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                                <span class="due-tag <?php echo $item_overdue ? 'overdue' : ''; ?>">Due: <?php echo date('M d', strtotime($item['due_date'])); ?></span>
                                            </div>
                                            <select name="item_status[<?php echo $item['item_record_id']; ?>]" class="item-return-select">
                                                <option value="Returned">Returned (Good)</option>
                                                <option value="Damaged" style="color: red;">Damaged</option>
                                                <option value="Lost" style="color: gray;">Lost</option>
                                            </select>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                    <div style="background: #f1f5f9; padding: 20px; border-radius: 8px;">
                                        <span class="detail-label">Summary</span>
                                        <p style="font-size:13px;">Borrowed: <?php echo date('M d, Y', strtotime($row['borrow_date'])); ?></p>
                                        <p style="font-size:13px;">Status: <b class="<?php echo $is_overdue ? 'overdue' : ''; ?>"><?php echo $is_overdue ? 'Overdue' : 'On Time'; ?></b></p>
                                        <button type="submit" class="btn-process-return">Confirm Return</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 40px;">No active transactions.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_pages > 1): ?>
    <div class="saas-pagination">
        <a href="#" onclick="loadModule('modules/transactions/borrow.php?page=<?php echo max(1, $page - 1); ?>'); return false;" class="saas-nav-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">Previous</a>
        <div class="saas-pages">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="#" onclick="loadModule('modules/transactions/borrow.php?page=<?php echo $i; ?>'); return false;" class="saas-page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <a href="#" onclick="loadModule('modules/transactions/borrow.php?page=<?php echo min($total_pages, $page + 1); ?>'); return false;" class="saas-nav-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">Next</a>
    </div>
    <?php endif; ?>
</div>

<!-- ─── Global Quick-Add Panel (lives outside the table, no clipping) ─── -->
<div id="qa-panel">
    <div id="qa-panel-header">
        <span id="qa-panel-title">Add Items to Borrower</span>
        <button id="qa-panel-close" onclick="qaClose()">
            <span class="material-symbols-outlined" style="font-size:18px;">close</span>
        </button>
    </div>
    <div id="qa-panel-body">
        <div class="qa-search-wrap">
            <input type="text" id="qa-search-input" placeholder="Search equipment name..." autocomplete="off">
            <div id="qa-dropdown"></div>
        </div>
        <div id="qa-staged-label">Selected Items</div>
        <div id="qa-chips"></div>
        <button id="qa-confirm-btn" onclick="qaSubmit()" disabled>Add Items</button>
    </div>
</div>

<script>
// ─── Equipment Data from PHP ───
const qaEquipList = <?php echo json_encode(array_values($equipment_list)); ?>;

// ─── State ───
let qaState = { headerId: null, borrowerId: null, staged: [] };

// ─── Open Panel ───
function qaOpen(event, headerId, borrowerId) {
    event.stopPropagation();
    const btn = event.currentTarget;
    qaState = { headerId, borrowerId, staged: [] };
    qaRenderChips();

    const panel = document.getElementById('qa-panel');

    // Render off-screen first so we can measure the real height
    panel.style.visibility = 'hidden';
    panel.style.display = 'block';

    const rect    = btn.getBoundingClientRect();
    const panelW  = panel.offsetWidth  || 320;
    const panelH  = panel.offsetHeight || 320;
    const margin  = 6;
    const vpW     = window.innerWidth;
    const vpH     = window.innerHeight;

    // Default: open BELOW the button
    let top  = rect.bottom + margin;
    let left = rect.right  - panelW;

    // If not enough room below, open ABOVE
    if (top + panelH > vpH - 8) {
        top = rect.top - panelH - margin;
    }

    // Clamp horizontally so the panel never scrolls off-screen
    if (left < 8)            left = 8;
    if (left + panelW > vpW) left = vpW - panelW - 8;

    panel.style.top        = top  + 'px';
    panel.style.left       = left + 'px';
    panel.style.visibility = 'visible';

    // Reset search
    const inp = document.getElementById('qa-search-input');
    inp.value = '';
    document.getElementById('qa-dropdown').style.display = 'none';
    inp.focus();
}

// ─── Close Panel ───
function qaClose() {
    document.getElementById('qa-panel').style.display = 'none';
    document.getElementById('qa-dropdown').style.display = 'none';
}

// ─── Search / Dropdown (event delegation approach - no inline handlers) ───
(function() {
    const inp = document.getElementById('qa-search-input');
    const dd  = document.getElementById('qa-dropdown');
    const panel = document.getElementById('qa-panel');

    // Search: filter equipment as user types
    inp.addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        if (!q) { dd.style.display = 'none'; return; }

        const matches = qaEquipList.filter(e =>
            e.name.toLowerCase().includes(q) &&
            !qaState.staged.some(s => s.id === String(e.equipment_id))
        );

        if (matches.length === 0) {
            dd.innerHTML = '<div class="qa-no-results">No equipment found</div>';
        } else {
            // Use data attributes only - NO inline handlers, NO global function calls
            dd.innerHTML = matches.map(e =>
                `<div class="qa-drop-item" data-equip-id="${e.equipment_id}" data-equip-name="${e.name.replace(/"/g, '&quot;')}">
                    <span>${e.name}</span>
                    <span class="qa-drop-stock">Stock: ${e.available_qty}</span>
                 </div>`
            ).join('');
        }
        dd.style.display = 'block';
    });

    // Event delegation: single listener on the dropdown container
    dd.addEventListener('mousedown', function(e) {
        // Prevent focus from leaving the input (allows click to fire normally)
        e.preventDefault();
    });

    dd.addEventListener('click', function(e) {
        const item = e.target.closest('.qa-drop-item');
        if (!item) return;
        const id   = item.dataset.equipId;
        const name = item.dataset.equipName;
        qaStageItem(id, name);
    });

    // Close dropdown when input loses focus (after 200ms to let click fire first)
    inp.addEventListener('blur', function() {
        setTimeout(() => { dd.style.display = 'none'; }, 200);
    });

    // Close panel when clicking outside
    document.addEventListener('click', function(e) {
        if (panel && !panel.contains(e.target)) qaClosePanel();
    });

    // Prevent clicks inside panel from closing it
    panel.addEventListener('click', function(e) { e.stopPropagation(); });

    // Chip remove buttons use event delegation too
    document.getElementById('qa-chips').addEventListener('click', function(e) {
        const btn = e.target.closest('.qa-chip-x');
        if (!btn) return;
        const id = btn.dataset.removeId;
        qaRemoveItem(id);
    });

    // Confirm button
    document.getElementById('qa-confirm-btn').addEventListener('click', qaSubmit);
})();

// ─── Stage Item ───
function qaStageItem(id, name) {
    if (!id || qaState.staged.some(s => s.id === String(id))) return;
    qaState.staged.push({ id: String(id), name });
    qaRenderChips();
    const inp = document.getElementById('qa-search-input');
    inp.value = '';
    document.getElementById('qa-dropdown').style.display = 'none';
    inp.focus();
}

// ─── Remove Item ───
function qaRemoveItem(id) {
    qaState.staged = qaState.staged.filter(s => s.id !== String(id));
    qaRenderChips();
}

// ─── Render Chips (uses data-remove-id instead of inline onclick) ───
function qaRenderChips() {
    const container = document.getElementById('qa-chips');
    const label     = document.getElementById('qa-staged-label');
    const btn       = document.getElementById('qa-confirm-btn');

    if (qaState.staged.length === 0) {
        container.innerHTML = '';
        label.style.display = 'none';
        btn.disabled = true;
        return;
    }

    label.style.display = 'block';
    btn.disabled = false;
    container.innerHTML = qaState.staged.map(s =>
        `<div class="qa-chip">
            <span class="qa-chip-name">${s.name}</span>
            <button class="qa-chip-x" data-remove-id="${s.id}" title="Remove">&times;</button>
         </div>`
    ).join('');
}

// ─── Row toggle ───
function toggleRow(rowId) {
    const row = document.getElementById(rowId);
    const isVisible = row.style.display === 'table-row';
    document.querySelectorAll('.expansion-row').forEach(r => r.style.display = 'none');
    row.style.display = isVisible ? 'none' : 'table-row';
}

// --- Reliable self-reload (does NOT depend on window.loadModule scope) ---
function brwReloadSelf() {
    const body = document.querySelector('.dashboard-body');
    if (!body) { location.reload(); return; }

    fetch('modules/transactions/borrow.php')
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;
            body.querySelectorAll('script').forEach(old => {
                const s = document.createElement('script');
                Array.from(old.attributes).forEach(a => s.setAttribute(a.name, a.value));
                s.textContent = old.textContent;
                old.parentNode.replaceChild(s, old);
            });
            if (window.lucide) window.lucide.createIcons();
        })
        .catch(() => location.reload());
}

// --- Submit (quick-add items) ---
function qaSubmit() {
    if (qaState.staged.length === 0) return;
    const btn = document.getElementById('qa-confirm-btn');
    btn.textContent = 'Saving...';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('borrower_id', qaState.borrowerId);
    fd.append('header_id',   qaState.headerId);
    fd.append('items', JSON.stringify(qaState.staged.map(s => s.id)));

    fetch('modules/transactions/process_quick_add.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.textContent = 'Added!';
                btn.style.background = '#10b981';
                setTimeout(() => {
                    qaClose();
                    btn.textContent = 'Add Items';
                    btn.style.background = '';
                    btn.disabled = false;
                    brwReloadSelf();
                }, 800);
            } else {
                alert(data.message || 'An error occurred.');
                btn.textContent = 'Add Items';
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.textContent = 'Add Items';
            btn.disabled = false;
        });
}

// --- Intercept Return Form (AJAX instead of hard POST) ---
document.addEventListener('submit', function(e) {
    const form = e.target;
    if (!form.classList.contains('expansion-content')) return;
    e.preventDefault();

    if (!confirm('Confirm return of these items?')) return;

    const submitBtn = form.querySelector('.btn-process-return');
    if (submitBtn) { submitBtn.textContent = 'Processing...'; submitBtn.disabled = true; }

    fetch('modules/transactions/process_return.php', { method: 'POST', body: new FormData(form) })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                brwReloadSelf();
            } else {
                alert(data.message || 'Return failed.');
                if (submitBtn) { submitBtn.textContent = 'Confirm Return'; submitBtn.disabled = false; }
            }
        })
        .catch(() => {
            alert('Network error.');
            if (submitBtn) { submitBtn.textContent = 'Confirm Return'; submitBtn.disabled = false; }
        });
});
</script>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/modals/borrower.php'; ?>