<?php
/**
 * Transaction History Module - Complete Equipment Transaction Log
 * 
 * Handles transaction history display including:
 * - View all equipment transactions (borrowed, returned, damaged, exchanged)
 * - Filter by date range, status, equipment, borrower
 * - Complete audit trail with staff accountability
 * - Read-only display (no modifications)
 * 
 * Database Tables:
 * - Transaction_Headers (header_id, borrower_id, issued_by_staff_id, borrow_date, status)
 * - Transaction_Items (item_record_id, header_id, equipment_id, due_date, return_date, item_status, return_condition)
 * - Equipment (equipment_id, name, storage_location)
 * - Borrowers (borrower_id, id_number, full_name, department, contact_no)
 * - Users (user_id, full_name) for issued_by and received_by staff
 */

// ═══════════════════════════════════════════════════════════════════════════════
// 1. INITIALIZATION & CONFIG
// ═══════════════════════════════════════════════════════════════════════════════

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// Constants
define('ITEMS_PER_PAGE', 14);

// ═══════════════════════════════════════════════════════════════════════════════
// 2. PAGE DATA LOADING - Prepare data for HTML display
// ═══════════════════════════════════════════════════════════════════════════════

$page_data = loadPageData();

// ═══════════════════════════════════════════════════════════════════════════════
// 3. RENDER HTML
// ═══════════════════════════════════════════════════════════════════════════════

renderTransactionLogPage($page_data);

// ═══════════════════════════════════════════════════════════════════════════════
// 4. FUNCTION DEFINITIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Load page data - pagination, filters, and transaction details
 */
function loadPageData() {
    global $conn;
    
    // Get current page
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * ITEMS_PER_PAGE;
    
    // Get filter parameters (all optional)
    $time_range = isset($_GET['time_range']) && !empty($_GET['time_range']) ? $_GET['time_range'] : null;
    $date_from = null;
    $date_to = null;
    
    // Calculate date range based on time_range filter
    $today = new DateTime();
    if ($time_range) {
        switch ($time_range) {
            case 'last_week':
                $date_from = (clone $today)->modify('-7 days')->format('Y-m-d');
                $date_to = $today->format('Y-m-d');
                break;
            case 'last_month':
                $date_from = (clone $today)->modify('-1 month')->format('Y-m-d');
                $date_to = $today->format('Y-m-d');
                break;
            case 'last_year':
                $date_from = (clone $today)->modify('-1 year')->format('Y-m-d');
                $date_to = $today->format('Y-m-d');
                break;
        }
    }
    
    // Build WHERE clause
    $where_clauses = [];
    $where_clauses[] = "1=1"; // Base condition
    
    // Exclude Lost status
    $where_clauses[] = "ti.item_status != 'Lost'";
    
    if ($date_from) {
        $escaped_date_from = mysqli_real_escape_string($conn, $date_from);
        $where_clauses[] = "DATE(th.borrow_date) >= '$escaped_date_from'";
    }
    
    if ($date_to) {
        $escaped_date_to = mysqli_real_escape_string($conn, $date_to);
        $where_clauses[] = "DATE(th.borrow_date) <= '$escaped_date_to'";
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Get total count
    $count_query = "SELECT COUNT(DISTINCT ti.item_record_id) as total 
                    FROM Transaction_Items ti
                    JOIN Transaction_Headers th ON ti.header_id = th.header_id
                    WHERE $where_sql";
    $count_result = mysqli_query($conn, $count_query);
    if (!$count_result) {
        return ['records' => [], 'total_transactions' => 0, 'page' => 1, 'total_pages' => 1, 'filters' => []];
    }
    
    $count_row = mysqli_fetch_assoc($count_result);
    $total_items = (int)$count_row['total'];
    $total_pages = $total_items > 0 ? ceil($total_items / ITEMS_PER_PAGE) : 1;
    
    // Ensure page is within range
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * ITEMS_PER_PAGE;
    }
    
    // Get transaction data
    $records = getTransactionHistory($conn, $where_sql, ITEMS_PER_PAGE, $offset);
    
    return [
        'records' => $records,
        'total_transactions' => $total_items,
        'page' => $page,
        'total_pages' => $total_pages,
        'filters' => [
            'time_range' => $time_range
        ]
    ];
}

/**
 * Get complete transaction history with all details
 */
function getTransactionHistory($conn, $where_sql, $limit, $offset) {
    $query = "SELECT 
                ti.item_record_id,
                ti.header_id,
                ti.due_date,
                ti.return_date,
                ti.item_status,
                ti.return_condition,
                ti.received_by_staff_id,
                th.borrow_date,
                th.issued_by_staff_id,
                b.borrower_id,
                b.id_number,
                b.full_name as borrower_name,
                b.department,
                b.contact_no,
                e.equipment_id,
                e.name as equipment_name,
                e.storage_location,
                issued_by.full_name as issued_by_staff,
                received_by.full_name as received_by_staff
              FROM Transaction_Items ti
              JOIN Transaction_Headers th ON ti.header_id = th.header_id
              JOIN Borrowers b ON th.borrower_id = b.borrower_id
              JOIN Equipment e ON ti.equipment_id = e.equipment_id
              LEFT JOIN Users issued_by ON th.issued_by_staff_id = issued_by.user_id
              LEFT JOIN Users received_by ON ti.received_by_staff_id = received_by.user_id
              WHERE $where_sql
              ORDER BY th.borrow_date DESC, ti.item_record_id DESC
              LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $transactions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $transactions[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $transactions;
}

/**
 * Get status color class for display
 */
/**
 * Render the complete transaction log page
 */
function renderTransactionLogPage($data) {
    ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        /* ──────────────────────────────────────────────────── */
        /* TRANSACTION LOG MODULE STYLES */
        /* ──────────────────────────────────────────────────── */
        
        :root {
            --log-bg:              #ecefec;
            --log-header:          #8faadc;
            --log-border:          rgba(0, 0, 0, 0.05);
            --log-text:            #1e293b;
            --log-text-muted:      #64748b;
            --log-status-returned: #10b981;
            --log-status-borrowed: #3b82f6;
            --log-status-damaged:  #ef4444;
            --log-status-exchanged:#8b5cf6;
            --log-buttons:         #0c1f3f;
        }

        .transaction-log-container {
            font-family: 'Inter', sans-serif;
            background: var(--log-bg);
            padding: 20px 40px 20px 40px;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 60px);
        }

        .transaction-log-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .transaction-log-header-text {
            font-size: 15px;
            color: var(--log-text-muted);
            margin: 0;
            font-weight: 400;
        }

        .transaction-log-divider {
            border: none;
            border-bottom: 1px solid var(--log-border);
            margin-bottom: 20px;
        }

        /* Header with buttons section */
        .transaction-log-header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
        }

        .transaction-log-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .transaction-log-header-text {
            font-size: 15px;
            color: var(--log-text-muted);
            margin: 0;
            font-weight: 400;
            white-space: nowrap;
        }

        .transaction-log-header-buttons {
            display: flex;
            gap: 8px;
            position: relative;
        }

        /* Filter Dropdown Button */
        .filter-dropdown-btn {
            padding: 8px 16px;
            background: var(--log-buttons);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-dropdown-btn:hover {
            background: #162a4a;
        }

        /* Filter Dropdown Menu */
        .filter-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--log-border);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            margin-top: 8px;
            min-width: 160px;
        }

        .filter-dropdown-menu.active {
            display: block;
        }

        .filter-dropdown-item {
            display: block;
            width: 100%;
            padding: 10px 16px;
            border: none;
            background: none;
            text-align: left;
            font-size: 12px;
            font-weight: 400;
            color: var(--log-text);
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .filter-dropdown-item:first-child {
            border-radius: 8px 8px 0 0;
        }

        .filter-dropdown-item:last-child {
            border-radius: 0 0 8px 8px;
        }

        .filter-dropdown-item:hover {
            background: #f0f4f9;
            color: var(--log-buttons);
        }

        .filter-dropdown-item.active {
            background: #dbeafe;
            color: #1e40af;
            font-weight: 600;
        }

        /* Table Styles */
        .transaction-log-table-wrapper {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow-x: auto;
            overflow-y: hidden;
            flex: 0 1 auto;
        }

        .transaction-log-table-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .transaction-log-table-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .transaction-log-table-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .transaction-log-table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .transaction-log-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        .transaction-log-table th {
            background: var(--log-header);
            color: #ecefec;
            font-size: 13px;
            font-weight: 400;
            padding: 10px 20px;
            text-transform: uppercase;
            text-align: left;
        }

        .transaction-log-table td {
            padding: 10px 20px;
            font-size: 15px;
            font-weight: 400;
            color: var(--log-text);
            border-bottom: 1px solid var(--log-border);
            vertical-align: middle;
        }

        .transaction-log-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        .transaction-log-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .transaction-log-table tbody tr:hover {
            background-color: #f0f4f9;
        }

        /* Status Badges */
        /* Muted text for optional fields */
        .text-muted {
            color: var(--log-text-muted);
        }

        /* Pagination */
        .saas-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--log-border);
            flex-shrink: 0;
        }

        .saas-pages {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex: 1;
        }

        .saas-page-link {
            min-width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #475569;
            font-size: 13px;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
        }

        .saas-page-link.active {
            background: var(--log-buttons);
            color: white;
        }

        .saas-page-link:hover:not(.active) {
            background: #f0f4f9;
        }

        .saas-nav-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: white;
            color: var(--log-buttons);
            border: 1px solid var(--log-border);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .saas-nav-btn:hover:not(.disabled) {
            background: var(--log-header);
            color: white;
        }

        .saas-nav-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state-icon {
            font-size: 48px;
            color: var(--log-text-muted);
            margin-bottom: 12px;
        }

        .empty-state-text {
            font-size: 15px;
            font-weight: 400;
            color: var(--log-text-muted);
        }
    </style>
</head>
<body>

<div class="transaction-log-container">
    <!-- Header with Stats and Filter -->
    <div class="transaction-log-header-section">
        <div class="transaction-log-header-left">
            <p class="transaction-log-header-text">
                Showing <b><?php echo number_format($data['total_transactions']); ?></b> transaction(s) in equipment history.
            </p>
        </div>
        <div class="transaction-log-header-buttons">
            <button class="filter-dropdown-btn" onclick="toggleFilterDropdown()">
                <span class="material-symbols-outlined" style="font-size: 16px;">tune</span>
                Filter
            </button>
            <div class="filter-dropdown-menu" id="filterDropdown">
                <button type="button" class="filter-dropdown-item" onclick="applyTimeRangeFilter('last_week')">Last Week</button>
                <button type="button" class="filter-dropdown-item" onclick="applyTimeRangeFilter('last_month')">Last Month</button>
                <button type="button" class="filter-dropdown-item" onclick="applyTimeRangeFilter('last_year')">Last Year</button>
            </div>
        </div>
    </div>

    <hr class="transaction-log-divider">

    <!-- Data Table -->
    <div class="transaction-log-table-wrapper">
        <table class="transaction-log-table">
            <thead>
                <tr>
                    <th style="width: 14%;">Date & Time</th>
                    <th style="width: 18%;">Borrower Name</th>
                    <th style="width: 22%;">Equipment</th>
                    <th style="width: 12%;">Transaction</th>
                    <th style="width: 17%;">Issued By</th>
                    <th style="width: 17%;">Received By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data['records'])): ?>
                    <?php foreach ($data['records'] as $row): ?>
                    <tr>
                        <td><?php echo date('n-j-y H:i', strtotime($row['borrow_date'])); ?></td>
                        <td>
                            <strong style="font-size: 14px;"><?php echo htmlspecialchars($row['borrower_name']); ?></strong><br>
                            <span style="color: var(--log-text-muted); font-size: 12px;">
                                <?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-size: 14px;"><?php echo htmlspecialchars($row['equipment_name']); ?></span><br>
                            <span style="color: var(--log-text-muted); font-size: 12px;">
                                <?php echo htmlspecialchars($row['storage_location'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="
                                display: inline-block;
                                padding: 4px 10px;
                                border-radius: 4px;
                                font-size: 12px;
                                font-weight: 600;
                                white-space: nowrap;
                                background: <?php echo (strtolower($row['item_status']) === 'returned') ? '#10b981' : '#3b82f6'; ?>;
                                color: #ffffff;
                            ">
                                <?php echo htmlspecialchars(ucfirst(strtolower($row['item_status']))); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['issued_by_staff'] ?? 'N/A'); ?>
                        </td>
                        <td>
                            <?php 
                            if ($row['received_by_staff']) {
                                echo htmlspecialchars($row['received_by_staff']);
                            } else {
                                echo '<span class="text-muted">—</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon">📋</div>
                                <div class="empty-state-text">No transaction records found.</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($data['total_pages'] > 1): ?>
    <div class="saas-pagination">
        <a href="#" onclick="loadModule('modules/transactions/transaction_log.php?page=<?php echo max(1, $data['page'] - 1); ?><?php echo $data['filters']['time_range'] ? '&time_range=' . urlencode($data['filters']['time_range']) : ''; ?>'); return false;" 
           class="saas-nav-btn <?php echo ($data['page'] <= 1) ? 'disabled' : ''; ?>">
            <span class="material-symbols-outlined" style="font-size: 18px;">chevron_left</span> Previous
        </a>
        <div class="saas-pages">
            <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
            <a href="#" onclick="loadModule('modules/transactions/transaction_log.php?page=<?php echo $i; ?><?php echo $data['filters']['time_range'] ? '&time_range=' . urlencode($data['filters']['time_range']) : ''; ?>'); return false;" 
               class="saas-page-link <?php echo ($i == $data['page']) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <a href="#" onclick="loadModule('modules/transactions/transaction_log.php?page=<?php echo min($data['total_pages'], $data['page'] + 1); ?><?php echo $data['filters']['time_range'] ? '&time_range=' . urlencode($data['filters']['time_range']) : ''; ?>'); return false;" 
           class="saas-nav-btn <?php echo ($data['page'] >= $data['total_pages']) ? 'disabled' : ''; ?>">
            Next <span class="material-symbols-outlined" style="font-size: 18px;">chevron_right</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
// ──────────────────────────────────────────────────
// Filter Dropdown Functions
// ──────────────────────────────────────────────────

function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    dropdown.classList.toggle('active');
}

function applyTimeRangeFilter(timeRange) {
    loadModule('modules/transactions/transaction_log.php?page=1&time_range=' + encodeURIComponent(timeRange));
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('filterDropdown');
    const button = document.querySelector('.filter-dropdown-btn');
    
    if (!dropdown.contains(event.target) && !button.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});
</script>

</body>
</html>
<?php
}

?>