<?php
include_once 'connection.php';

echo "=== Borrowers in Database ===\n";
$result = $conn->query('SELECT borrower_id, id_number, full_name FROM Borrowers LIMIT 10');
if($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "BorrowerId: {$row['borrower_id']} | ID#: {$row['id_number']} | Name: {$row['full_name']}\n";
    }
} else {
    echo "No borrowers found or query error: " . $conn->error . "\n";
}

echo "\n=== Recent Transactions ===\n";
$result = $conn->query('SELECT th.borrower_id, th.borrow_date, b.full_name, u.full_name as staff FROM Transaction_Headers th 
                        LEFT JOIN Borrowers b ON th.borrower_id = b.borrower_id 
                        LEFT JOIN Users u ON th.issued_by_staff_id = u.user_id 
                        ORDER BY th.borrow_date DESC LIMIT 5');
if($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "Borrower: {$row['full_name']} | Staff: {$row['staff']} | Date: {$row['borrow_date']}\n";
    }
} else {
    echo "No transactions found\n";
}
?>
