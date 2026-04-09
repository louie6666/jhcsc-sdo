<?php
/**
 * Settings Module - System Configuration & User Management
 * 
 * Features:
 * - Create new user accounts with profile pictures
 * - Manage existing users with password reset functionality
 * - Backup and export database (manual or scheduled)
 * - System reset (data only, preserves schema)
 */

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// ═══════════════════════════════════════════════════════════════════════════════
// 1. HANDLE FORM SUBMISSIONS
// ═══════════════════════════════════════════════════════════════════════════════

$message = null;
$message_type = null;

// Create User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = (int)$_POST['role_id'] ?? 2;
    
    // Validate input
    if (empty($full_name) || empty($username) || empty($password)) {
        $message = 'All fields are required';
        $message_type = 'error';
    } else if (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters';
        $message_type = 'error';
    } else {
        // Check if username exists
        $check_query = "SELECT user_id FROM Users WHERE username = '$username'";
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) > 0) {
            $message = 'Username already exists';
            $message_type = 'error';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Handle profile picture upload
            $profile_pic = null;
            if (!empty($_FILES['profile_pic']['tmp_name'])) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $file_name = 'profile_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $file_path)) {
                    $profile_pic = 'uploads/profiles/' . $file_name;
                }
            }
            
            // Insert user
            $insert_query = "INSERT INTO Users (role_id, full_name, username, password, profile_pic, is_active) 
                            VALUES ($role_id, '$full_name', '$username', '$hashed_password', " . 
                            ($profile_pic ? "'$profile_pic'" : "NULL") . ", 1)";
            
            if (mysqli_query($conn, $insert_query)) {
                $message = 'User created successfully!';
                $message_type = 'success';
                // Clear form
                $_POST = [];
            } else {
                $message = 'Error creating user: ' . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
}

// Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $user_id = (int)$_POST['user_id'] ?? 0;
    $temp_password = bin2hex(random_bytes(4)); // Generate random password
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    $update_query = "UPDATE Users SET password = '$hashed_password' WHERE user_id = $user_id";
    if (mysqli_query($conn, $update_query)) {
        $message = "Password reset to: <strong>$temp_password</strong> (Share securely with user)";
        $message_type = 'success';
    } else {
        $message = 'Error resetting password';
        $message_type = 'error';
    }
}

// Backup & Export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    generateBackup($conn);
}

// System Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'system_reset') {
    $confirm_password = $_POST['confirm_password'] ?? '';
    if (empty($confirm_password) || !password_verify($confirm_password, $_SESSION['password_hash'] ?? '')) {
        $message = 'Invalid password. System reset cancelled.';
        $message_type = 'error';
    } else {
        resetSystemData($conn);
        $message = 'System data cleared successfully! New school year ready.';
        $message_type = 'success';
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 2. LOAD PAGE DATA
// ═══════════════════════════════════════════════════════════════════════════════

$users_query = "SELECT u.user_id, u.full_name, u.username, u.profile_pic, r.role_name, u.is_active 
                FROM Users u 
                LEFT JOIN Roles r ON u.role_id = r.role_id 
                ORDER BY u.user_id DESC";
$users_result = mysqli_query($conn, $users_query);
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);

// Get scheduled backup info
$scheduled_backup = date('Y-m-d H:i', strtotime('next Friday 00:00'));

?>
<!DOCTYPE html>
<html>
<head>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #ecefec;
            --bg-secondary: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: rgba(0, 0, 0, 0.05);
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
            --accent-green: #10b981;
            --button-dark: #0c1f3f;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .settings-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
        }

        /* Header & Title */
        .settings-header {
            margin-bottom: 20px;
        }

        .settings-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .settings-header p {
            font-size: 14px;
            font-weight: 400;
            color: var(--text-secondary);
        }

        /* Messages */
        .message-box {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 400;
        }

        .message-box.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message-box.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Main Grid Layout */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 20px;
        }

        .settings-section {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .settings-section h2 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .settings-section p {
            font-size: 12px;
            font-weight: 400;
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.5;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 14px;
            font-weight: 400;
            font-family: inherit;
            color: var(--text-primary);
            background: #ffffff;
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-secondary);
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border: 2px dashed var(--border-light);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: #f9fafb;
        }

        .file-input-label:hover {
            border-color: var(--accent-blue);
            background: #f0f4f9;
        }

        .file-input-label input[type="file"] {
            display: none;
        }

        .file-input-text {
            font-size: 12px;
            font-weight: 400;
            color: var(--text-secondary);
        }

        /* Button Styles */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--button-dark);
            color: white;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            background: #162a4a;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: var(--button-dark);
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-danger {
            background: var(--accent-red);
            color: white;
            width: 100%;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-success {
            background: var(--accent-green);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 11px;
            width: auto;
        }

        /* Users Table */
        .users-table-wrapper {
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .users-table th {
            background: #f9fafb;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-light);
        }

        .users-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-light);
            font-size: 14px;
            font-weight: 400;
            color: var(--text-primary);
        }

        .users-table tbody tr:hover {
            background: #f9fafb;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: var(--button-dark);
            object-fit: cover;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-badge.admin {
            background: #dbeafe;
            color: #1e40af;
        }

        .role-badge.staff {
            background: #dbeafe;
            color: #1e40af;
        }

        .role-badge.student {
            background: #ede9fe;
            color: #6d28d9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-cell {
            display: flex;
            gap: 8px;
        }

        /* Backup Section */
        .backup-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 16px;
        }

        .schedule-info {
            background: #f0f4f9;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 400;
            color: var(--text-secondary);
            margin-top: 12px;
        }

        .schedule-info strong {
            color: var(--text-primary);
        }

        /* System Reset Section */
        .system-reset-section {
            grid-column: 1 / -1;
            background: #fef2f2;
            border: 2px solid #fee2e2;
        }

        .reset-warning {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 12px;
            font-weight: 400;
            border-left: 4px solid #ef4444;
        }

        .reset-warning strong {
            font-weight: 600;
        }

        .reset-info {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
        }

        .reset-info-item {
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            border-left: 3px solid #ef4444;
            font-size: 12px;
            font-weight: 400;
            color: var(--text-secondary);
        }

        .reset-info-item strong {
            color: var(--text-primary);
        }

        /* Wide Layout */
        .section-wide {
            grid-column: 1 / -1;
        }

        /* Form Container */
        .create-account-section {
            position: relative;
        }

        @media (max-width: 1200px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }

            .section-wide {
                grid-column: 1;
            }
        }
    </style>
</head>
<body>

<div class="settings-container">
    <!-- Header -->
    <div class="settings-header">
        <h1>Settings</h1>
        <p>Manage your system configuration, users, and backups</p>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="message-box <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <!-- Main Grid -->
    <div class="settings-grid">
        <!-- Create Account Form -->
        <div class="settings-section create-account-section">
            <h2>Create Account</h2>
            <p>Add a new staff member or admin to the system. They can update their password after first login.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" placeholder="John Smith" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" placeholder="john.smith" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role_id" class="form-select" required>
                        <option value="1">Admin</option>
                        <option value="2" selected>Staff</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Profile Picture</label>
                    <div class="file-input-wrapper">
                        <label class="file-input-label">
                            <input type="file" name="profile_pic" accept="image/*">
                            <span class="file-input-text">📷 Click to upload profile picture (optional)</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>
        </div>

        <!-- Backup & Export -->
        <div class="settings-section">
            <h2>Backup & Export</h2>
            <p>Download your system data as CSV files for backup or analysis. Choose manual download or automatic weekly backup.</p>
            
            <div class="backup-options">
                <form method="POST" style="display: contents;">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-primary">📥 Manual Download</button>
                </form>
            </div>

            <div class="schedule-info">
                <strong>Automatic Backup:</strong><br>
                Enabled • Scheduled for <strong><?php echo $scheduled_backup; ?></strong><br>
                Files exported: Inventory, Transaction History
            </div>
        </div>

        <!-- Users Management Table -->
        <div class="settings-section section-wide">
            <h2>Users Management</h2>
            <p>View all system users, reset passwords, and manage accounts. Use the reset password feature when staff forget their credentials.</p>
            
            <div class="users-table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <?php if (!empty($user['profile_pic'])): ?>
                                        <img src="/jhcsc_seis/<?php echo htmlspecialchars($user['profile_pic']); ?>" class="user-avatar" alt="">
                                    <?php else: ?>
                                        <div class="user-avatar"><?php echo substr($user['full_name'], 0, 2); ?></div>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <span class="role-badge <?php echo strtolower($user['role_name']); ?>">
                                    <?php echo htmlspecialchars($user['role_name']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo ($user['is_active'] ? 'active' : 'inactive'); ?>">
                                    <?php echo ($user['is_active'] ? 'Active' : 'Inactive'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-cell">
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-small" onclick="return confirm('Reset password for <?php echo htmlspecialchars($user['full_name']); ?>?')">
                                            🔑 Reset
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Reset -->
        <div class="settings-section system-reset-section">
            <h2 style="color: var(--accent-red);">System Reset</h2>
            <p>Clear all transaction data for a new school year. The database structure will remain intact—only data will be removed.</p>
            
            <div class="reset-warning">
                ⚠️ <strong>CAUTION:</strong> This action is permanent and cannot be undone. All borrowers, transactions, and maintenance records will be deleted.
            </div>

            <div class="reset-info">
                <div class="reset-info-item">
                    ✓ Cleared: All borrowers, transaction records, transaction items, maintenance logs
                </div>
                <div class="reset-info-item">
                    ✓ Preserved: Equipment catalog, user accounts, system settings
                </div>
                <div class="reset-info-item">
                    ℹ️ Use this when starting a new semester or school year
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="system_reset">
                
                <div class="form-group">
                    <label class="form-label">Confirm your password to proceed</label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This will delete all transaction data permanently!')">
                    🗑️ Clear All Data
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>

<?php

// ═══════════════════════════════════════════════════════════════════════════════
// 3. HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Generate and download database backup as CSV
 */
function generateBackup($conn) {
    // Get Inventory
    $csv_inventory = "Equipment ID,Name,Category,Total Qty,Available Qty,Damaged Qty,Storage Location\n";
    $inventory_query = "SELECT e.equipment_id, e.name, c.category_name, e.total_qty, e.available_qty, e.damaged_qty, e.storage_location 
                       FROM Equipment e 
                       LEFT JOIN Categories c ON e.category_id = c.category_id";
    $result = mysqli_query($conn, $inventory_query);
    while ($row = mysqli_fetch_assoc($result)) {
        $csv_inventory .= implode(',', array_map(function($val) { 
            return '"' . str_replace('"', '""', $val) . '"'; 
        }, array_values($row))) . "\n";
    }

    // Get Transaction History
    $csv_transactions = "Trans ID,Borrower,Equipment,Borrow Date,Due Date,Return Date,Status,Return Condition,Issued By,Received By\n";
    $trans_query = "SELECT ti.item_record_id, b.full_name, e.name, th.borrow_date, ti.due_date, ti.return_date, ti.item_status, ti.return_condition, u1.full_name as issued_by, u2.full_name as received_by
                    FROM Transaction_Items ti
                    JOIN Transaction_Headers th ON ti.header_id = th.header_id
                    JOIN Borrowers b ON th.borrower_id = b.borrower_id
                    JOIN Equipment e ON ti.equipment_id = e.equipment_id
                    LEFT JOIN Users u1 ON th.issued_by_staff_id = u1.user_id
                    LEFT JOIN Users u2 ON ti.received_by_staff_id = u2.user_id";
    $result = mysqli_query($conn, $trans_query);
    while ($row = mysqli_fetch_assoc($result)) {
        $csv_transactions .= implode(',', array_map(function($val) { 
            return '"' . str_replace('"', '""', $val) . '"'; 
        }, array_values($row))) . "\n";
    }

    // Create ZIP file with both CSVs
    $zip_file = sys_get_temp_dir() . '/backup_' . date('Y-m-d_H-i-s') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
        $zip->addFromString('inventory_' . date('Y-m-d') . '.csv', $csv_inventory);
        $zip->addFromString('transactions_' . date('Y-m-d') . '.csv', $csv_transactions);
        $zip->close();

        // Download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.zip"');
        readfile($zip_file);
        unlink($zip_file);
        exit;
    }
}

/**
 * Reset all system data (preserves schema)
 */
function resetSystemData($conn) {
    // Delete in correct order (respecting foreign keys)
    mysqli_query($conn, "DELETE FROM Transaction_Items");
    mysqli_query($conn, "DELETE FROM Transaction_Headers");
    mysqli_query($conn, "DELETE FROM Maintenance");
    mysqli_query($conn, "DELETE FROM Borrowers");
    
    // Reset Equipment quantities to 0
    mysqli_query($conn, "UPDATE Equipment SET available_qty = 0, damaged_qty = 0, total_qty = 0");
}

?>