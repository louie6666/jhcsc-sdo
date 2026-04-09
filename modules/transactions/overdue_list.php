<?php
/**
 * Overdue List Module - Overdue Equipment Tracking
 * 
 * Handles overdue transactions including:
 * - View all overdue borrowed items (past due date)
 * - Return equipment with condition tracking
 * - Track days overdue
 * - Damage description on return
 * 
 * Database Tables:
 * - Transaction_Headers (header_id, borrower_id, borrow_date)
 * - Transaction_Items (item_record_id, equipment_id, due_date, item_status)
 * - Equipment (equipment_id, name, storage_location)
 * - Borrowers (borrower_id, id_number, full_name, department, contact_no)
 */

// ═══════════════════════════════════════════════════════════════════════════════
// 1. INITIALIZATION & CONFIG
// ═══════════════════════════════════════════════════════════════════════════════

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// Constants
define('ITEMS_PER_PAGE', 14);

// ═══════════════════════════════════════════════════════════════════════════════
// 2. REQUEST DETECTION - Determine if this is an API call or page load
// ═══════════════════════════════════════════════════════════════════════════════

$request_type = $_SERVER['REQUEST_METHOD'];
$is_api_call = !empty($_POST) || !empty($_GET['action']) || 
               (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

// If API request, handle it and exit
if ($is_api_call && $request_type === 'POST') {
    handleApiRequest();
}

// ═══════════════════════════════════════════════════════════════════════════════
// 3. PAGE DATA LOADING - Prepare data for HTML display
// ═══════════════════════════════════════════════════════════════════════════════

$page_data = loadPageData();

// ═══════════════════════════════════════════════════════════════════════════════
// 4. RENDER HTML
// ═══════════════════════════════════════════════════════════════════════════════

renderOverdueListPage($page_data);

// ═══════════════════════════════════════════════════════════════════════════════
// 5. FUNCTION DEFINITIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Detect and handle API requests
 * Routes to appropriate handler based on action
 */
function handleApiRequest() {
    global $conn;
    
    header('Content-Type: application/json');
    
    $action = determineAction();
    
    try {
        switch ($action) {
            case 'return_overdue':
                handleReturnOverdueItems();
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    if (isset($conn)) {
        mysqli_close($conn);
    }
    exit;
}

/**
 * Determine which API action is being requested
 */
function determineAction() {
    if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        return $input['action'] ?? 'unknown';
    }
    
    if (isset($_POST['action'])) {
        return $_POST['action'];
    }
    
    if (isset($_POST['header_id']) && isset($_POST['items'])) {
        return 'return_overdue';
    }
    
    return 'unknown';
}

/**
 * Return overdue items with condition tracking
 */
function handleReturnOverdueItems() {
    global $conn;
    
    $header_id = isset($_POST['header_id']) ? (int)$_POST['header_id'] : 0;
    $item_statuses = json_decode($_POST['items'] ?? '{}', true) ?? [];
    
    if (!$header_id || empty($item_statuses)) {
        sendJsonError('Missing required data');
    }
    
    // Verify header exists and is open
    $check = mysqli_query($conn, "SELECT header_id FROM Transaction_Headers 
                                   WHERE header_id = $header_id AND status = 'Open'");
    if (!$check || mysqli_num_rows($check) === 0) {
        sendJsonError('Transaction not found or already completed');
    }
    
    try {
        $allowed_statuses = ['Returned', 'Damaged', 'Lost'];
        $errors = [];
        $processed = 0;
        $return_date = date('Y-m-d H:i:s');
        
        foreach ($item_statuses as $item_record_id => $status_data) {
            $item_record_id = (int)$item_record_id;
            
            // Handle both string and object status formats
            $damage_description = null;
            if (is_array($status_data)) {
                $new_status = $status_data['status'] ?? 'Returned';
                $damage_description = $status_data['description'] ?? null;
            } else {
                $new_status = $status_data;
            }
            
            if (!in_array($new_status, $allowed_statuses)) {
                $errors[] = "Invalid status '$new_status' for item #$item_record_id";
                continue;
            }
            
            // Fetch item info
            $item_res = mysqli_query($conn, "SELECT equipment_id, item_status 
                                             FROM Transaction_Items
                                             WHERE item_record_id = $item_record_id 
                                             AND header_id = $header_id");
            $item = mysqli_fetch_assoc($item_res);
            
            if (!$item) {
                $errors[] = "Item #$item_record_id not found in this transaction";
                continue;
            }
            
            if ($item['item_status'] !== 'Borrowed') {
                continue; // Already processed
            }
            
            $equip_id = (int)$item['equipment_id'];
            
            // Update item status
            $escaped_status = mysqli_real_escape_string($conn, $new_status);
            $update = mysqli_query($conn, "UPDATE Transaction_Items
                                           SET item_status = '$escaped_status',
                                               return_date = '$return_date',
                                               return_condition = '$escaped_status'
                                           WHERE item_record_id = $item_record_id");
            
            if (!$update) {
                $errors[] = "Failed to update item #$item_record_id: " . mysqli_error($conn);
                continue;
            }
            
            $processed++;
            
            // Adjust equipment stock based on condition
            if ($new_status === 'Returned') {
                // Item in good condition - return to available
                mysqli_query($conn, "UPDATE equipment 
                                     SET available_qty = available_qty + 1 
                                     WHERE equipment_id = $equip_id");
                
            } elseif ($new_status === 'Damaged') {
                // Item damaged - increment damage count and create maintenance record
                mysqli_query($conn, "UPDATE equipment 
                                     SET damaged_qty = damaged_qty + 1 
                                     WHERE equipment_id = $equip_id");
                
                // Use provided description or default message
                $issue_desc = $damage_description ? 
                              mysqli_real_escape_string($conn, $damage_description) : 
                              'Item returned in damaged condition (overdue).';
                
                mysqli_query($conn, "INSERT INTO Maintenance
                                     (equipment_id, item_record_id, issue_description, repair_status)
                                     VALUES ($equip_id, $item_record_id, '$issue_desc', 'Pending')");
            }
            // 'Lost' - stock stays gone
        }
        
        // Check if all items resolved
        $remaining = mysqli_query($conn, "SELECT COUNT(*) as cnt 
                                          FROM Transaction_Items
                                          WHERE header_id = $header_id AND item_status = 'Borrowed'");
        $remaining_row = mysqli_fetch_assoc($remaining);
        
        if ((int)$remaining_row['cnt'] === 0) {
            // All items processed - close header
            mysqli_query($conn, "UPDATE Transaction_Headers 
                                 SET status = 'Completed' 
                                 WHERE header_id = $header_id");
        }
        
        if ($processed > 0) {
            echo json_encode([
                'success' => true,
                'message' => "$processed item(s) returned successfully",
                'errors' => $errors,
                'is_closed' => ((int)$remaining_row['cnt'] === 0)
            ]);
        } else {
            sendJsonError('No items were processed. ' . implode(' ', $errors));
        }
        
    } catch (Exception $e) {
        sendJsonError($e->getMessage());
    }
}

/**
 * Load page data - pagination and statistics
 */
function loadPageData() {
    global $conn;
    
    // Get current page
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * ITEMS_PER_PAGE;
    
    // Get total count of overdue items
    $count_query = "SELECT COUNT(DISTINCT ti.item_record_id) as total 
                    FROM Transaction_Items ti
                    JOIN Transaction_Headers th ON ti.header_id = th.header_id
                    WHERE ti.item_status = 'Borrowed' AND ti.due_date < NOW() AND th.status = 'Open'";
    $count_result = mysqli_query($conn, $count_query);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_items = (int)$count_row['total'];
    $total_pages = ceil($total_items / ITEMS_PER_PAGE);
    
    // Ensure page is within range
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * ITEMS_PER_PAGE;
    }
    
    // Get overdue transactions grouped by header
    $records = getOverdueTransactions($conn, ITEMS_PER_PAGE, $offset);
    
    // Get statistics
    $stats = getOverdueStats($conn);
    
    return [
        'records' => $records,
        'total_overdue' => $stats['overdue'],
        'total_days_overdue' => $stats['total_days'],
        'page' => $page,
        'total_pages' => $total_pages
    ];
}

/**
 * Get overdue statistics
 */
function getOverdueStats($conn) {
    $query = "SELECT 
                COUNT(DISTINCT ti.item_record_id) as overdue,
                DATEDIFF(NOW(), MAX(ti.due_date)) as max_days_overdue
              FROM Transaction_Items ti
              JOIN Transaction_Headers th ON ti.header_id = th.header_id
              WHERE ti.item_status = 'Borrowed' AND ti.due_date < NOW() AND th.status = 'Open'";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        return ['overdue' => 0, 'total_days' => 0];
    }
    
    $row = mysqli_fetch_assoc($result);
    return [
        'overdue' => (int)$row['overdue'] ?: 0,
        'total_days' => (int)$row['max_days_overdue'] ?: 0
    ];
}

/**
 * Get overdue transactions with equipment and borrower details
 */
function getOverdueTransactions($conn, $limit, $offset) {
    $query = "SELECT 
                th.header_id,
                th.borrow_date,
                b.borrower_id,
                b.id_number,
                b.full_name,
                b.department,
                b.contact_no,
                ti.item_record_id,
                ti.due_date,
                ti.equipment_id,
                e.name as equipment_name,
                e.storage_location,
                DATEDIFF(NOW(), ti.due_date) as days_overdue
              FROM Transaction_Items ti
              JOIN Transaction_Headers th ON ti.header_id = th.header_id
              JOIN Borrowers b ON th.borrower_id = b.borrower_id
              JOIN Equipment e ON ti.equipment_id = e.equipment_id
              WHERE ti.item_status = 'Borrowed' AND ti.due_date < NOW() AND th.status = 'Open'
              ORDER BY th.header_id ASC, ti.due_date ASC
              LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $transactions = [];
    $current_header = null;
    $transactions_buffer = null;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $header_id = $row['header_id'];
        
        // Group items by transaction header
        if ($current_header !== $header_id) {
            if ($transactions_buffer !== null) {
                $transactions[] = $transactions_buffer;
            }
            $current_header = $header_id;
            $transactions_buffer = [
                'header_id' => $header_id,
                'borrower_id' => $row['borrower_id'],
                'id_number' => $row['id_number'],
                'full_name' => $row['full_name'],
                'department' => $row['department'],
                'contact_no' => $row['contact_no'],
                'borrow_date' => $row['borrow_date'],
                'items' => []
            ];
        }
        
        $transactions_buffer['items'][] = [
            'item_record_id' => $row['item_record_id'],
            'equipment_name' => $row['equipment_name'],
            'storage_location' => $row['storage_location'],
            'due_date' => $row['due_date'],
            'days_overdue' => $row['days_overdue']
        ];
    }
    
    // Add last transaction
    if ($transactions_buffer !== null) {
        $transactions[] = $transactions_buffer;
    }
    
    mysqli_stmt_close($stmt);
    return $transactions;
}

/**
 * Helper: Send JSON error response
 */
function sendJsonError($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Render the complete overdue list page
 */
function renderOverdueListPage($data) {
    ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        /* ──────────────────────────────────────────────────── */
        /* OVERDUE LIST MODULE STYLES */
        /* ──────────────────────────────────────────────────── */
        
        :root {
            --overdue-bg:          #ecefec;
            --overdue-hover:       #8faadc;
            --overdue-buttons:     #0c1f3f;
            --overdue-border:      rgba(0, 0, 0, 0.05);
            --overdue-row-even:    #f0f4f9;
            --overdue-accent-red:  #e11d48;
            --overdue-muted:       #64748b;
            --overdue-status-ok:   #10b981;
            --overdue-status-warn: #f59e0b;
        }

        .overdue-container {
            font-family: 'Inter', sans-serif;
            background: var(--overdue-bg);
            padding: 20px 40px 20px 40px;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 60px);
        }

        .overdue-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .overdue-header-text {
            font-size: 14px;
            color: #000000;
            font-weight: 400;
            margin: 0;
        }

        .overdue-divider {
            border: none;
            border-bottom: 1px solid var(--overdue-border);
            margin-bottom: 20px;
        }

        .overdue-table-wrapper {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: auto;
            flex: 0 1 auto;
        }

        .overdue-table {
            width: 100%;
            border-collapse: collapse;
        }

        .overdue-table th {
            background: var(--overdue-hover);
            color: #ecefec;
            font-size: 13px;
            font-weight: 400;
            padding: 10px 20px;
            text-transform: uppercase;
            text-align: left;
        }

        .overdue-table td {
            padding: 10px 20px;
            font-size: 14px;
            color: #000000;
            border-bottom: 1px solid var(--overdue-border);
            vertical-align: middle;
        }

        .overdue-table tbody tr.main-row:nth-child(odd) { background-color: #ffffff; }
        .overdue-table tbody tr.main-row:nth-child(even) { background-color: #f9fafb; }

        .overdue-table tbody tr.main-row:hover {
            background-color: #f0f4f9;
        }

        .items-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .items-list > div {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 0;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 4px;
        }

        .items-list > div:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .condition-checkboxes {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }

        .condition-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            flex-shrink: 0;
            appearance: none;
            -webkit-appearance: none;
            border-radius: 3px;
            border: 2px solid #e2e8f0;
            font-size: 12px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .condition-checkbox:checked {
            font-weight: bold;
            color: white;
            background-size: 100% 100%;
            border: 2px solid #10b981;
        }

        .condition-checkbox.good-condition {
            background-color: #f0fdf4;
            border-color: #d1fae5;
        }

        .condition-checkbox.good-condition:checked {
            background-color: #10b981;
            border-color: #059669;
        }

        .condition-checkbox.bad-condition {
            background-color: #fef2f2;
            border-color: #fee2e2;
        }

        .condition-checkbox.bad-condition:checked {
            background-color: #dc2626;
            border-color: #b91c1c;
        }

        .item-name {
            color: #1e293b;
            font-size: 13px;
            flex: 1;
        }

        .days-overdue {
            font-weight: 600;
            color: #dc2626;
            font-size: 12px;
            margin-left: auto;
        }

        .overdue-cell {
            color: var(--overdue-accent-red) !important;
            font-weight: 700;
        }

        .actions-cell {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 6px 10px;
            border: 1px solid var(--overdue-border);
            background: white;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            font-size: 16px;
        }

        .btn-action:hover {
            background: var(--overdue-hover);
            color: white;
        }

        /* Pagination */
        .saas-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--overdue-border);
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
            background: var(--overdue-buttons);
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
            color: var(--overdue-buttons);
            border: 1px solid var(--overdue-border);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .saas-nav-btn:hover:not(.disabled) {
            background: var(--overdue-hover);
            color: white;
        }

        .saas-nav-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* Damage Modal Styles */
        .damage-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-in-out;
        }

        .damage-modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .damage-modal {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            padding: 24px;
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .damage-modal-header {
            font-size: 16px;
            font-weight: 700;
            color: #0c1f3f;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .damage-modal-header span {
            font-size: 20px;
            color: #dc2626;
        }

        .damage-modal-body {
            margin-bottom: 20px;
        }

        .damage-modal-label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }

        .damage-modal-textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
            font-family: 'Inter', sans-serif;
            resize: vertical;
            color: #000000;
        }

        .damage-modal-textarea:focus {
            outline: none;
            border-color: #8faadc;
            box-shadow: 0 0 0 3px rgba(143, 170, 220, 0.1);
        }

        .damage-modal-footer {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .damage-modal-btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .damage-modal-btn-cancel {
            background: white;
            color: #475569;
        }

        .damage-modal-btn-cancel:hover {
            background: #f1f5f9;
        }

        .damage-modal-btn-confirm {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        .damage-modal-btn-confirm:hover {
            background: #b91c1c;
            border-color: #b91c1c;
        }
    </style>
</head>
<body>

<div class="overdue-container">
    <!-- Header -->
    <div class="overdue-header-row">
        <p class="overdue-header-text">
            Tracking <b><?php echo number_format($data['total_overdue']); ?></b> overdue items 
            <?php if ($data['total_days_overdue'] > 0): ?>
                (up to <b><?php echo $data['total_days_overdue']; ?></b> days overdue).
            <?php else: ?>
                .
            <?php endif; ?>
        </p>
    </div>

    <hr class="overdue-divider">

    <!-- Data Table -->
    <div class="overdue-table-wrapper">
        <table class="overdue-table">
            <thead>
                <tr>
                    <th style="width: 12%;">ID Number</th>
                    <th style="width: 18%;">Borrower Name</th>
                    <th style="width: 34%;">Items Overdue</th>
                    <th style="width: 10%;">Borrow Date</th>
                    <th style="width: 10%;">Due Date</th>
                    <th style="width: 8%;">Contact</th>
                    <th style="width: 8%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($data['records'])): ?>
                    <?php foreach($data['records'] as $row): 
                        $is_overdue = strtotime($row['items'][0]['due_date']) < time();
                    ?>
                    <tr class="main-row" data-borrowerId="<?php echo $row['borrower_id']; ?>" 
                        data-headerId="<?php echo $row['header_id']; ?>">
                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                            <span style="color: #64748b; font-size: 12px;">
                                <?php echo htmlspecialchars($row['department']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="items-list">
                                <?php foreach($row['items'] as $item): ?>
                                <div>
                                    <div class="condition-checkboxes">
                                        <input type="checkbox" class="condition-checkbox good-condition" 
                                            data-item-id="<?php echo $item['item_record_id']; ?>" 
                                            data-header-id="<?php echo $row['header_id']; ?>"
                                            data-condition="Returned"
                                            title="Good Condition">
                                        <input type="checkbox" class="condition-checkbox bad-condition" 
                                            data-item-id="<?php echo $item['item_record_id']; ?>" 
                                            data-header-id="<?php echo $row['header_id']; ?>"
                                            data-condition="Damaged"
                                            title="Damaged">
                                    </div>
                                    <span class="item-name"><?php echo htmlspecialchars($item['equipment_name']); ?></span>
                                    <span class="days-overdue"><?php echo $item['days_overdue']; ?>d</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><?php echo date('n-j-y', strtotime($row['borrow_date'])); ?></td>
                        <td class="<?php echo $is_overdue ? 'overdue-cell' : ''; ?>">
                            <?php echo date('n-j-y', strtotime($row['items'][0]['due_date'])); ?>
                        </td>
                        <td style="font-size: 12px;">
                            <?php echo htmlspecialchars($row['contact_no']); ?>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <button class="btn-action" title="Return overdue items"
                                    onclick="submitOverdueReturn(<?php echo $row['header_id']; ?>)">
                                    <span class="material-symbols-outlined" style="font-size: 8px;">assignment_return</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            No overdue items.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination (Always Visible) -->
    <div class="saas-pagination">
        <a href="#" onclick="loadModule('modules/transactions/overdue_list.php?page=<?php echo max(1, $data['page'] - 1); ?>'); return false;" 
           class="saas-nav-btn <?php echo ($data['page'] <= 1) ? 'disabled' : ''; ?>">
            <span class="material-symbols-outlined" style="font-size: 18px;">chevron_left</span> Previous
        </a>
        <div class="saas-pages">
            <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
            <a href="#" onclick="loadModule('modules/transactions/overdue_list.php?page=<?php echo $i; ?>'); return false;" 
               class="saas-page-link <?php echo ($i == $data['page']) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <a href="#" onclick="loadModule('modules/transactions/overdue_list.php?page=<?php echo min($data['total_pages'], $data['page'] + 1); ?>'); return false;" 
           class="saas-nav-btn <?php echo ($data['page'] >= $data['total_pages']) ? 'disabled' : ''; ?>">
            Next <span class="material-symbols-outlined" style="font-size: 18px;">chevron_right</span>
        </a>
    </div>
</div>

<!-- Damage Description Modal -->
<div class="damage-modal-overlay" id="damageModal">
    <div class="damage-modal">
        <div class="damage-modal-header">
            <span class="material-symbols-outlined">error_circle</span>
            Describe Equipment Damage
        </div>
        <div class="damage-modal-body">
            <label class="damage-modal-label">What is the condition issue?</label>
            <textarea class="damage-modal-textarea" id="damageDescription" placeholder="Describe the damage, defect, or condition issue..."></textarea>
        </div>
        <div class="damage-modal-footer">
            <button class="damage-modal-btn damage-modal-btn-cancel" onclick="closeDamageModal()">Cancel</button>
            <button class="damage-modal-btn damage-modal-btn-confirm" id="confirmDamageBtn" onclick="confirmDamageDescription()">Confirm</button>
        </div>
    </div>
</div>

<script>
// ──────────────────────────────────────────────────
// State Management for Damage Modal
// ──────────────────────────────────────────────────
let currentDamagedCheckbox = null;

// ──────────────────────────────────────────────────
// Return Overdue Functions
// ──────────────────────────────────────────────────

function submitOverdueReturn(headerId) {
    // Get all checked condition checkboxes for this header
    const row = event.target.closest('tr');
    const checkedCheckboxes = row.querySelectorAll('.condition-checkbox:checked');
    
    if (checkedCheckboxes.length === 0) {
        alert('Please select condition (Good or Damaged) for at least one item to return.');
        return;
    }
    
    if (!confirm('Have you verified the condition of all checked items?')) return;
    if (!confirm('Are you sure you want to return the selected items?')) return;
    
    // Build items object from checked boxes with their condition
    const items = {};
    row.querySelectorAll('.condition-checkboxes').forEach(checkboxGroup => {
        const goodCheckbox = checkboxGroup.querySelector('.good-condition');
        const badCheckbox = checkboxGroup.querySelector('.bad-condition');
        const itemId = goodCheckbox.dataset.itemId;
        
        if (goodCheckbox.checked) {
            items[itemId] = 'Returned'; // Good condition
        } else if (badCheckbox.checked) {
            items[itemId] = 'Damaged'; // Damaged - goes to maintenance
            // Store damage description if available
            if (badCheckbox.dataset.damageDescription) {
                items[itemId] = { status: 'Damaged', description: badCheckbox.dataset.damageDescription };
            }
        }
    });
    
    const formData = new FormData();
    formData.append('header_id', headerId);
    formData.append('items', JSON.stringify(items));
    
    // Disable button during submission
    const btn = event.target.closest('.btn-action');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 8px;">hourglass_empty</span>';
    
    fetch('modules/transactions/overdue_list.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 8px; color: green;">check_circle</span>';
            setTimeout(() => location.reload(), 500);
        } else {
            alert(data.message || 'Return failed');
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Network error');
        btn.innerHTML = originalContent;
        btn.disabled = false;
    });
}

// ──────────────────────────────────────────────────
// Damage Modal Functions
// ──────────────────────────────────────────────────

function openDamageModal(checkbox) {
    currentDamagedCheckbox = checkbox;
    const modal = document.getElementById('damageModal');
    const textarea = document.getElementById('damageDescription');
    
    // Clear textarea
    textarea.value = checkbox.dataset.damageDescription || '';
    textarea.focus();
    
    // Show modal
    modal.classList.add('active');
}

function closeDamageModal() {
    const modal = document.getElementById('damageModal');
    modal.classList.remove('active');
    currentDamagedCheckbox = null;
}

function confirmDamageDescription() {
    if (!currentDamagedCheckbox) return;
    
    const textarea = document.getElementById('damageDescription');
    const description = textarea.value.trim();
    
    if (!description) {
        alert('Please describe the damage');
        return;
    }
    
    // Store damage description on the checkbox
    currentDamagedCheckbox.dataset.damageDescription = description;
    
    // Close modal
    closeDamageModal();
}

// ──────────────────────────────────────────────────
// Condition Checkbox Logic
// ──────────────────────────────────────────────────

// Prevent checking both good and damaged condition for same item
document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('condition-checkbox')) return;
    
    const checkboxGroup = e.target.parentElement;
    const goodCheckbox = checkboxGroup.querySelector('.good-condition');
    const badCheckbox = checkboxGroup.querySelector('.bad-condition');
    
    if (e.target.classList.contains('good-condition') && e.target.checked) {
        badCheckbox.checked = false;
    } else if (e.target.classList.contains('bad-condition') && e.target.checked) {
        goodCheckbox.checked = false;
        // Open damage description modal when red checkbox is checked
        setTimeout(() => openDamageModal(badCheckbox), 100);
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('damageModal');
    if (e.target === modal) {
        closeDamageModal();
    }
});
</script>

</body>
</html>
<?php
}

?>