<?php
/**
 * Borrow Module - Transaction Management
 * 
 * Handles borrowing transactions including:
 * - View active transactions with pagination
 * - Create new borrow transactions
 * - Add equipment to existing transactions
 * - Return equipment with condition tracking
 * - Search borrowers
 * 
 * Dependencies:
 * - modals/borrower.php (New transaction modal)
 * - modals/add_borrow.php (Add equipment to transaction modal)
 */

// ═══════════════════════════════════════════════════════════════════════════════
// 1. INITIALIZATION & CONFIG
// ═══════════════════════════════════════════════════════════════════════════════

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// Constants
define('ITEMS_PER_PAGE', 14);
define('SEARCH_LIMIT', 10);

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
// 4. LOAD MODAL DATA - Fetch equipment and staff for modals
// ═══════════════════════════════════════════════════════════════════════════════

// Fetch staff list for modals
$staff_query = "SELECT user_id, full_name FROM Users ORDER BY full_name ASC";
$staff_result = mysqli_query($conn, $staff_query);
$staff_list = [];
if ($staff_result) {
    while($row = mysqli_fetch_assoc($staff_result)) {
        $staff_list[] = $row;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 5. RENDER HTML
// ═══════════════════════════════════════════════════════════════════════════════

renderBorrowPage($page_data);

// ═══════════════════════════════════════════════════════════════════════════════
// 6. FUNCTION DEFINITIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Detect and handle API requests
 * Routes to appropriate handler based on action
 */
function handleApiRequest() {
    global $conn;
    
    header('Content-Type: application/json');
    
    // Determine the action
    $action = determineAction();
    
    try {
        switch ($action) {
            case 'search_borrower':
                handleSearchBorrower();
                break;
            case 'get_borrowed_items':
                handleGetBorrowedItems();
                break;
            case 'create_transaction':
                handleCreateTransaction();
                break;
            case 'add_equipment':
                handleAddEquipment();
                break;
            case 'process_return':
                handleProcessReturn();
                break;
            case 'search_borrowers':
                handleSearchBorrowers();
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
    // Check Content-Type for JSON
    if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        return $input['action'] ?? 'unknown';
    }
    
    // Check $_POST for action
    if (isset($_POST['action'])) {
        return $_POST['action'];
    }
    
    // Based on endpoint and method, guess the action
    if (isset($_POST['header_id']) && isset($_POST['items'])) {
        return 'process_return';
    }
    if (isset($_POST['header_id']) && isset($_POST['equipment_ids'])) {
        return 'add_equipment';
    }
    if (isset($_POST['id_number']) && isset($_POST['full_name'])) {
        return 'create_transaction';
    }
    
    return 'unknown';
}

/**
 * Search for a borrower by ID/name
 * Returns borrower info and which page they appear on
 */
function handleSearchBorrower() {
    global $conn;
    
    $id_number = isset($_POST['id_number']) ? trim($_POST['id_number']) : '';
    
    if (empty($id_number)) {
        sendJsonError('ID number is required');
    }
    
    $query = "SELECT borrower_id, full_name, department, contact_no 
              FROM Borrowers 
              WHERE id_number = ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        sendJsonError('Database error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $id_number);
    if (!mysqli_stmt_execute($stmt)) {
        sendJsonError('Query error: ' . mysqli_error($conn));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $borrower = mysqli_fetch_assoc($result);
        $page = calculateBorrowerPage($conn, $borrower['full_name']);
        
        echo json_encode([
            'success' => true,
            'borrower' => $borrower,
            'page' => $page
        ]);
    } else {
        sendJsonError('Borrower not found');
    }
    
    mysqli_stmt_close($stmt);
}

/**
 * Get all currently borrowed items for a transaction header
 */
function handleGetBorrowedItems() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $header_id = isset($input['header_id']) ? (int)$input['header_id'] : 0;
    
    if (!$header_id) {
        sendJsonError('Invalid header ID');
    }
    
    $query = "SELECT ti.item_record_id, e.name, ti.due_date 
              FROM Transaction_Items ti 
              JOIN Equipment e ON ti.equipment_id = e.equipment_id 
              WHERE ti.header_id = ? AND ti.item_status = 'Borrowed'
              ORDER BY e.name ASC";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        sendJsonError('Database error');
    }
    
    mysqli_stmt_bind_param($stmt, "i", $header_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
    mysqli_stmt_close($stmt);
}

/**
 * Create a new borrow transaction (new or existing borrower)
 */
function handleCreateTransaction() {
    global $conn;
    
    // Verify session
    if (!isset($_SESSION['user_id'])) {
        sendJsonError('Unauthorized access', 403);
    }
    
    // Validate input
    $id_number = isset($_POST['id_number']) ? trim($_POST['id_number']) : '';
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $contact_no = isset($_POST['contact_no']) ? trim($_POST['contact_no']) : '';
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : '';
    $equipment_ids = json_decode($_POST['equipment_ids'] ?? '[]', true);
    
    if (empty($id_number) || empty($full_name) || empty($due_date)) {
        sendJsonError('Missing required fields');
    }
    
    if (empty($equipment_ids) || !is_array($equipment_ids)) {
        sendJsonError('Please select at least one equipment');
    }
    
    try {
        mysqli_begin_transaction($conn);
        
        // Step 1: Get or create borrower
        $borrower_id = getOrCreateBorrower($conn, $id_number, $full_name, $department, $contact_no);
        
        // Step 2: Create transaction header
        $header_id = createTransactionHeader($conn, $borrower_id, $_SESSION['user_id']);
        
        // Step 3: Parse due date
        $due_date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $due_date);
        if (!$due_date_obj) {
            throw new Exception("Invalid date format");
        }
        $formatted_due_date = $due_date_obj->format('Y-m-d H:i:s');
        
        // Step 4: Add items and update stock
        addTransactionItems($conn, $header_id, $equipment_ids, $formatted_due_date);
        
        // Commit
        mysqli_commit($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Transaction created successfully',
            'header_id' => $header_id,
            'borrower_id' => $borrower_id
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        sendJsonError($e->getMessage());
    }
}

/**
 * Add equipment to an existing transaction
 */
function handleAddEquipment() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        sendJsonError('Unauthorized access', 403);
    }
    
    $header_id = isset($_POST['header_id']) ? (int)$_POST['header_id'] : 0;
    $equipment_ids = json_decode($_POST['equipment_ids'] ?? '[]', true);
    
    if (!$header_id || empty($equipment_ids)) {
        sendJsonError('Invalid request');
    }
    
    try {
        mysqli_begin_transaction($conn);
        
        // Get existing transaction's due date
        $due_date = getHeaderDueDate($conn, $header_id);
        
        // Add items and update stock
        addTransactionItems($conn, $header_id, $equipment_ids, $due_date);
        
        mysqli_commit($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Equipment added successfully',
            'header_id' => $header_id
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        sendJsonError($e->getMessage());
    }
}

/**
 * Process item returns with condition tracking
 */
function handleProcessReturn() {
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
        
        foreach ($item_statuses as $item_record_id => $new_status) {
            $item_record_id = (int)$item_record_id;
            
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
                
                mysqli_query($conn, "INSERT INTO Maintenance
                                     (equipment_id, item_record_id, issue_description, repair_status)
                                     VALUES ($equip_id, $item_record_id, 'Item returned in damaged condition.', 'Pending')");
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
                'message' => "$processed item(s) processed successfully",
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
 * Search borrowers by ID, name, or department
 */
function handleSearchBorrowers() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $query = isset($input['query']) ? trim($input['query']) : '';
    
    if (empty($query) || strlen($query) < 2) {
        sendJsonError('Query too short');
    }
    
    $search_term = '%' . $query . '%';
    $sql = "SELECT DISTINCT 
                b.borrower_id, b.id_number, b.full_name, b.department,
                COUNT(DISTINCT th.header_id) as active_transactions
            FROM Borrowers b
            LEFT JOIN Transaction_Headers th ON b.borrower_id = th.borrower_id
            LEFT JOIN Transaction_Items ti ON th.header_id = ti.header_id 
                        AND ti.item_status = 'Borrowed'
            WHERE b.id_number LIKE ? OR b.full_name LIKE ? OR b.department LIKE ?
            GROUP BY b.borrower_id
            ORDER BY b.full_name ASC
            LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        sendJsonError('Database error');
    }
    
    $search_limit = SEARCH_LIMIT;
    mysqli_stmt_bind_param($stmt, "sssi", $search_term, $search_term, $search_term, $search_limit);
    if (!mysqli_stmt_execute($stmt)) {
        sendJsonError('Query error');
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $borrowers = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $page = calculateBorrowerPage($conn, $row['full_name']);
        $borrowers[] = [
            'borrower_id' => $row['borrower_id'],
            'id_number' => $row['id_number'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'active_transactions' => $row['active_transactions'],
            'page' => $page
        ];
    }
    
    echo json_encode(['success' => true, 'borrowers' => $borrowers]);
    mysqli_stmt_close($stmt);
}

// ═══════════════════════════════════════════════════════════════════════════════
// 6. HELPER FUNCTIONS - Database operations
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Get existing borrower or create new one
 */
function getOrCreateBorrower($conn, $id_number, $full_name, $department, $contact_no) {
    $query = "SELECT borrower_id FROM Borrowers WHERE id_number = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $id_number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row['borrower_id'];
    }
    
    mysqli_stmt_close($stmt);
    
    // Create new borrower
    $insert_query = "INSERT INTO Borrowers (id_number, full_name, department, contact_no) 
                     VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_query);
    if (!$stmt) {
        throw new Exception("Database error: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "ssss", $id_number, $full_name, $department, $contact_no);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to create borrower: " . mysqli_error($conn));
    }
    
    $borrower_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $borrower_id;
}

/**
 * Create a transaction header
 */
function createTransactionHeader($conn, $borrower_id, $staff_id) {
    $query = "INSERT INTO Transaction_Headers (borrower_id, issued_by_staff_id, borrow_date, status) 
              VALUES (?, ?, NOW(), 'Open')";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Database error: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $borrower_id, $staff_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to create transaction header: " . mysqli_error($conn));
    }
    
    $header_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $header_id;
}

/**
 * Add transaction items and update equipment stock
 */
function addTransactionItems($conn, $header_id, $equipment_ids, $due_date) {
    $query = "INSERT INTO Transaction_Items (header_id, equipment_id, due_date, item_status) 
              VALUES (?, ?, ?, 'Borrowed')";
    
    foreach ($equipment_ids as $equipment_id) {
        $equipment_id = (int)$equipment_id;
        
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            throw new Exception("Database error: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "iis", $header_id, $equipment_id, $due_date);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to add item: " . mysqli_error($conn));
        }
        
        // Update equipment quantity
        $update_query = "UPDATE Equipment SET available_qty = available_qty - 1 
                         WHERE equipment_id = ? AND available_qty > 0";
        $update_stmt = mysqli_prepare($conn, $update_query);
        if (!$update_stmt) {
            throw new Exception("Database error: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($update_stmt, "i", $equipment_id);
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Failed to update equipment quantity: " . mysqli_error($conn));
        }
        
        mysqli_stmt_close($stmt);
        mysqli_stmt_close($update_stmt);
    }
}

/**
 * Get the due date from a transaction header
 */
function getHeaderDueDate($conn, $header_id) {
    $query = "SELECT MAX(due_date) as max_due_date 
              FROM Transaction_Items 
              WHERE header_id = ? AND item_status = 'Borrowed'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $header_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    
    if ($row['max_due_date']) {
        return $row['max_due_date'];
    }
    
    // Default: 7 days from now
    return date('Y-m-d H:i:s', strtotime('+7 days'));
}

/**
 * Calculate which page a borrower appears on
 */
function calculateBorrowerPage($conn, $full_name) {
    $query = "SELECT COUNT(*) as position 
              FROM Borrowers 
              WHERE full_name <= ? 
              ORDER BY full_name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $full_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    
    $position = $row['position'] ?? 1;
    return max(1, ceil($position / ITEMS_PER_PAGE));
}

/**
 * Send JSON error response
 */
function sendJsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// 7. PAGE DATA LOADING
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Load all data needed for page render
 */
function loadPageData() {
    global $conn;
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * ITEMS_PER_PAGE;
    
    // Get stats
    $stats = getTransactionStats($conn);
    $total_pages = ceil($stats['total_borrowers'] / ITEMS_PER_PAGE);
    
    // Get transactions with items
    $transactions = getActiveTransactions($conn, ITEMS_PER_PAGE, $offset);
    
    // Fetch equipment for modals
    $equipment_list = getAvailableEquipment($conn);
    
    return [
        'page' => $page,
        'total_pages' => $total_pages,
        'total_borrowers' => $stats['total_borrowers'],
        'total_items' => $stats['total_items'],
        'transactions' => $transactions,
        'equipment_list' => $equipment_list
    ];
}

/**
 * Get transaction statistics
 */
function getTransactionStats($conn) {
    $query = "SELECT 
                COUNT(DISTINCT th.borrower_id) as total_borrowers,
                COUNT(ti.item_record_id) as total_items
              FROM Transaction_Items ti
              JOIN Transaction_Headers th ON ti.header_id = th.header_id
              WHERE ti.item_status = 'Borrowed'";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        return ['total_borrowers' => 0, 'total_items' => 0];
    }
    
    $row = mysqli_fetch_assoc($result);
    return [
        'total_borrowers' => (int)$row['total_borrowers'],
        'total_items' => (int)$row['total_items']
    ];
}

/**
 * Get active borrowing transactions
 */
function getActiveTransactions($conn, $limit, $offset) {
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
        // Get items for this transaction
        $items = getTransactionItems($conn, $row['header_id']);
        $row['items'] = $items;
        $transactions[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $transactions;
}

/**
 * Get items for a specific transaction
 */
function getTransactionItems($conn, $header_id) {
    $query = "SELECT ti.item_record_id, e.name, ti.due_date 
              FROM Transaction_Items ti
              JOIN Equipment e ON ti.equipment_id = e.equipment_id
              WHERE ti.header_id = ? AND ti.item_status = 'Borrowed'
              ORDER BY e.name ASC";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $header_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $items;
}

/**
 * Get available equipment for modals
 */
function getAvailableEquipment($conn) {
    $query = "SELECT equipment_id, name, storage_location, available_qty 
              FROM equipment 
              WHERE available_qty > 0
              ORDER BY name ASC";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        return [];
    }
    
    $equipment = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $equipment[] = $row;
    }
    
    return $equipment;
}

// ═══════════════════════════════════════════════════════════════════════════════
// 8. HTML RENDERING
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Render the complete borrow page
 */
function renderBorrowPage($data) {
    ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        /* ──────────────────────────────────────────────────── */
        /* BORROW MODULE STYLES */
        /* ──────────────────────────────────────────────────── */
        
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
        }

        .borrow-table tbody tr.main-row:nth-child(odd) { background-color: #ffffff; }
        .borrow-table tbody tr.main-row:nth-child(even) { background-color: #f9fafb; }

        .items-list {
            padding: 8px 0;
        }

        .items-list > div {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 4px;
        }

        .items-list > div:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .overdue-cell {
            color: var(--brw-accent-red) !important;
            font-weight: 700;
        }

        .actions-cell {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 6px 10px;
            border: 1px solid var(--brw-border);
            background: white;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            font-size: 16px;
        }

        .btn-action:hover {
            background: var(--brw-hover);
            color: white;
        }

        /* Return Sidebar */
        /* Inline item return checkboxes */
        .items-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .items-list > div {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
        }

        .item-return-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--brw-status-ok);
            flex-shrink: 0;
        }

        .item-return-checkbox + span {
            color: #1e293b;
            font-size: 13px;
        }

        /* Pagination */
        .saas-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--brw-border);
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
            background: var(--brw-buttons);
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
            color: var(--brw-buttons);
            border: 1px solid var(--brw-border);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .saas-nav-btn:hover:not(.disabled) {
            background: var(--brw-hover);
            color: white;
        }

        .saas-nav-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="borrow-container">
    <!-- Header -->
    <div class="borrow-header-row">
        <p class="borrow-header-text">
            Tracking <b><?php echo number_format($data['total_items']); ?></b> items across 
            <b><?php echo number_format($data['total_borrowers']); ?></b> active borrowers.
        </p>
        <button class="borrow-btn-primary" onclick="toggleBorrowModal(true)">
            <span class="material-symbols-outlined" style="font-size: 18px;">add</span> 
            New Transaction
        </button>
    </div>

    <hr class="borrow-divider">

    <!-- Data Table -->
    <div class="borrow-table-wrapper">
        <table class="borrow-table">
            <thead>
                <tr>
                    <th style="width: 12%;">ID Number</th>
                    <th style="width: 24%;">Borrower Name</th>
                    <th style="width: 20%;">Items Borrowed</th>
                    <th style="width: 10%;">Borrow Date</th>
                    <th style="width: 10%;">Due Date</th>
                    <th style="width: 12%;">Contact</th>
                    <th style="width: 12%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($data['transactions'])): ?>
                    <?php foreach($data['transactions'] as $row): 
                        $is_overdue = strtotime($row['earliest_due']) < time();
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
                                    <input type="checkbox" class="item-return-checkbox" 
                                        data-item-id="<?php echo $item['item_record_id']; ?>" 
                                        data-header-id="<?php echo $row['header_id']; ?>">
                                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><?php echo date('n-j-y', strtotime($row['borrow_date'])); ?></td>
                        <td class="<?php echo $is_overdue ? 'overdue-cell' : ''; ?>">
                            <?php echo date('n-j-y', strtotime($row['earliest_due'])); ?>
                        </td>
                        <td style="font-size: 12px;">
                            <?php echo htmlspecialchars($row['contact_no']); ?>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <button class="btn-action" title="Return checked items"
                                    onclick="submitInlineReturn(<?php echo $row['header_id']; ?>)">
                                    <span class="material-symbols-outlined">assignment_return</span>
                                </button>
                                <button class="btn-action" title="Add items"
                                    onclick="openAddBorrowModal(<?php echo $row['header_id']; ?>, <?php echo $row['borrower_id']; ?>)">
                                    <span class="material-symbols-outlined">add_circle</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            No active borrowing transactions.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if($data['total_pages'] > 1): ?>
    <div class="saas-pagination">
        <a href="#" onclick="loadModule('modules/transactions/borrow.php?page=<?php echo max(1, $data['page'] - 1); ?>'); return false;" 
           class="saas-nav-btn <?php echo ($data['page'] <= 1) ? 'disabled' : ''; ?>">
            <span class="material-symbols-outlined" style="font-size: 18px;">chevron_left</span> Previous
        </a>
        <div class="saas-pages">
            <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
            <a href="#" onclick="loadModule('modules/transactions/borrow.php?page=<?php echo $i; ?>'); return false;" 
               class="saas-page-link <?php echo ($i == $data['page']) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <a href="#" onclick="loadModule('modules/transactions/borrow.php?page=<?php echo min($data['total_pages'], $data['page'] + 1); ?>'); return false;" 
           class="saas-nav-btn <?php echo ($data['page'] >= $data['total_pages']) ? 'disabled' : ''; ?>">
            Next <span class="material-symbols-outlined" style="font-size: 18px;">chevron_right</span>
        </a>
    </div>
    <?php endif; ?>
</div>



<script>
// ──────────────────────────────────────────────────
// Inline Return Functions
// ──────────────────────────────────────────────────

function submitInlineReturn(headerId) {
    // Get all checked checkboxes for this header
    const row = event.target.closest('tr');
    const checkboxes = row.querySelectorAll('.item-return-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select at least one item to return.');
        return;
    }
    
    if (!confirm('Have you verified the condition of all checked items?')) return;
    if (!confirm('Are you sure you want to return the selected items?')) return;
    
    // Build items object from checked boxes
    const items = {};
    row.querySelectorAll('.item-return-checkbox').forEach(cb => {
        const itemId = cb.dataset.itemId;
        items[itemId] = cb.checked ? 'Returned' : 'Lost';
    });
    
    const formData = new FormData();
    formData.append('header_id', headerId);
    formData.append('items', JSON.stringify(items));
    
    // Disable button during submission
    const btn = event.target.closest('.btn-action');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px;">hourglass_empty</span>';
    
    fetch('modules/transactions/borrow.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px; color: green;">check_circle</span>';
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
// Modal Functions (overridden by modals)
// ──────────────────────────────────────────────────

function toggleBorrowModal(show) {
    const modal = document.getElementById('borrowModal');
    if (!modal) return;
    
    if (show) {
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}

function openAddBorrowModal(headerId, borrowerId) {
    const modal = document.getElementById('addBorrowModal');
    if (!modal) return;
    
    modal.classList.remove('hidden');
    if (typeof openAddBorrowModal_internal === 'function') {
        openAddBorrowModal_internal(headerId, borrowerId);
    }
}
</script>

</body>
</html>
<?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// 9. INCLUDE MODAL FILES - Make them available globally with connection access
// ═══════════════════════════════════════════════════════════════════════════════

// Now include modals - $conn and $staff_list are available globally
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/modals/borrower.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/modals/add_borrow.php';

?>
