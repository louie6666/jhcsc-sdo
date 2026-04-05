<?php
header('Content-Type: application/json');
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

$header_id    = isset($_POST['header_id']) ? (int)$_POST['header_id'] : 0;
$item_statuses = $_POST['item_status'] ?? []; // [ item_record_id => 'Returned'|'Damaged'|'Lost' ]

if (!$header_id || empty($item_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

// Verify the header exists and is still open
$check = mysqli_query($conn,
    "SELECT header_id FROM Transaction_Headers WHERE header_id = $header_id AND status = 'Open'"
);
if (!$check || mysqli_num_rows($check) === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found or already completed.']);
    exit;
}

$allowed_statuses = ['Returned', 'Damaged', 'Lost'];
$errors    = [];
$processed = 0;
$return_date = date('Y-m-d H:i:s');

foreach ($item_statuses as $item_record_id => $new_status) {
    $item_record_id = (int)$item_record_id;

    if (!in_array($new_status, $allowed_statuses)) {
        $errors[] = "Invalid status '$new_status' for item #$item_record_id.";
        continue;
    }

    // Fetch the item so we know the equipment_id and current status
    $item_res = mysqli_query($conn,
        "SELECT equipment_id, item_status FROM Transaction_Items
         WHERE item_record_id = $item_record_id AND header_id = $header_id"
    );
    $item = mysqli_fetch_assoc($item_res);

    if (!$item) {
        $errors[] = "Item #$item_record_id not found in this transaction.";
        continue;
    }

    if ($item['item_status'] !== 'Borrowed') {
        // Skip items already resolved — no double-processing
        continue;
    }

    $equip_id = (int)$item['equipment_id'];

    // 1. Update the item record
    $escaped_status = mysqli_real_escape_string($conn, $new_status);
    $update = mysqli_query($conn,
        "UPDATE Transaction_Items
         SET item_status = '$escaped_status',
             return_date = '$return_date',
             return_condition = '$escaped_status'
         WHERE item_record_id = $item_record_id"
    );

    if (!$update) {
        $errors[] = "Failed to update item #$item_record_id: " . mysqli_error($conn);
        continue;
    }

    $processed++;

    // 2. Equipment stock adjustments based on condition
    if ($new_status === 'Returned') {
        // Good condition — return to available pool
        mysqli_query($conn,
            "UPDATE equipment
             SET available_qty = available_qty + 1
             WHERE equipment_id = $equip_id"
        );

    } elseif ($new_status === 'Damaged') {
        // Increment damaged count (already removed from available when borrowed)
        mysqli_query($conn,
            "UPDATE equipment
             SET damaged_qty = damaged_qty + 1
             WHERE equipment_id = $equip_id"
        );

        // Log into Maintenance table for follow-up
        mysqli_query($conn,
            "INSERT INTO Maintenance
                (equipment_id, item_record_id, issue_description, repair_status)
             VALUES
                ($equip_id, $item_record_id, 'Item returned in damaged condition.', 'Pending')"
        );

    }
    // 'Lost' — stock stays gone, no maintenance record needed
}

// 3. Check if all items in this header are now resolved (none left as 'Borrowed')
$remaining_res = mysqli_query($conn,
    "SELECT COUNT(*) as cnt FROM Transaction_Items
     WHERE header_id = $header_id AND item_status = 'Borrowed'"
);
$remaining = mysqli_fetch_assoc($remaining_res);

if ((int)$remaining['cnt'] === 0) {
    // All done — close the header
    mysqli_query($conn,
        "UPDATE Transaction_Headers SET status = 'Completed' WHERE header_id = $header_id"
    );
}

if ($processed > 0) {
    echo json_encode([
        'success'   => true,
        'message'   => "$processed item(s) processed successfully.",
        'errors'    => $errors,
        'is_closed' => ((int)$remaining['cnt'] === 0)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No items were processed. ' . implode(' ', $errors)
    ]);
}
