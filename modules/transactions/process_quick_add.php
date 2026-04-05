<?php
header('Content-Type: application/json');
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// Validate input
$borrower_id = isset($_POST['borrower_id']) ? (int)$_POST['borrower_id'] : 0;
$header_id   = isset($_POST['header_id'])   ? (int)$_POST['header_id']   : 0;
$items_json  = $_POST['items'] ?? '[]';
$equipment_ids = json_decode($items_json, true);

if (!$borrower_id || !$header_id || empty($equipment_ids)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

// Verify the header belongs to this borrower
$check = mysqli_query($conn, "SELECT header_id FROM Transaction_Headers WHERE header_id = $header_id AND borrower_id = $borrower_id AND status = 'Open'");
if (!$check || mysqli_num_rows($check) === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction session not found or already closed.']);
    exit;
}

// Get the due date from the existing open session (use the earliest due_date already set)
$due_result = mysqli_query($conn, "SELECT MIN(due_date) as due FROM Transaction_Items WHERE header_id = $header_id");
$due_row = mysqli_fetch_assoc($due_result);
// Default 7 days if no existing due date found
$default_due = $due_row['due'] ?? date('Y-m-d H:i:s', strtotime('+7 days'));

$errors = [];
$inserted = 0;

foreach ($equipment_ids as $equip_id) {
    $equip_id = (int)$equip_id;

    // Check available stock
    $stock_res = mysqli_query($conn, "SELECT available_qty FROM equipment WHERE equipment_id = $equip_id");
    $stock_row = mysqli_fetch_assoc($stock_res);

    if (!$stock_row || $stock_row['available_qty'] < 1) {
        $errors[] = "Equipment ID $equip_id is out of stock.";
        continue;
    }

    // Insert transaction item
    $insert = mysqli_query($conn,
        "INSERT INTO Transaction_Items (header_id, equipment_id, due_date, item_status)
         VALUES ($header_id, $equip_id, '$default_due', 'Borrowed')"
    );

    if ($insert) {
        // Decrease available stock
        mysqli_query($conn, "UPDATE equipment SET available_qty = available_qty - 1 WHERE equipment_id = $equip_id");
        $inserted++;
    } else {
        $errors[] = "Failed to insert equipment ID $equip_id: " . mysqli_error($conn);
    }
}

if ($inserted > 0) {
    echo json_encode([
        'success' => true,
        'message' => "$inserted item(s) added successfully.",
        'errors'  => $errors
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No items were added. ' . implode(' ', $errors)
    ]);
}
