<?php
/**
 * Maintenance Module - Equipment Repair Management
 * 
 * Handles maintenance operations including:
 * - View pending and in-repair maintenance records
 * - Mark equipment as fully fixed
 * - Update equipment inventory after repair
 * - Track repair history
 * 
 * Database Tables:
 * - Maintenance (maintenance_id, equipment_id, repair_status, etc.)
 * - Equipment (equipment_id, name, damaged_qty, available_qty)
 */

// ═══════════════════════════════════════════════════════════════════════════════
// 1. INITIALIZATION & CONFIG
// ═══════════════════════════════════════════════════════════════════════════════

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// Constants
define('ITEMS_PER_PAGE', 12);

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

renderMaintenancePage($page_data);

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
            case 'mark_fixed':
                handleMarkFixed();
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
    
    if (isset($_POST['maintenance_id']) && isset($_POST['equipment_id'])) {
        return 'mark_fixed';
    }
    
    return 'unknown';
}

/**
 * Mark equipment as fully fixed
 * Updates repair_status to 'Fixed' and restores equipment to available inventory
 */
function handleMarkFixed() {
    global $conn;
    
    $maintenance_id = isset($_POST['maintenance_id']) ? (int)$_POST['maintenance_id'] : 0;
    $equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    
    if ($maintenance_id <= 0 || $equipment_id <= 0) {
        sendJsonError('Invalid maintenance or equipment ID');
    }
    
    try {
        // Get equipment name for response
        $eq_query = "SELECT name, damaged_qty FROM Equipment WHERE equipment_id = ?";
        $eq_stmt = mysqli_prepare($conn, $eq_query);
        if (!$eq_stmt) {
            throw new Exception("Database error: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($eq_stmt, "i", $equipment_id);
        mysqli_stmt_execute($eq_stmt);
        $eq_result = mysqli_stmt_get_result($eq_stmt);
        $equipment = mysqli_fetch_assoc($eq_result);
        mysqli_stmt_close($eq_stmt);
        
        if (!$equipment) {
            throw new Exception("Equipment not found");
        }
        
        if ($equipment['damaged_qty'] <= 0) {
            throw new Exception("No damaged items to repair");
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        // 1. Update maintenance record status to 'Fixed'
        $update_maint = "UPDATE Maintenance 
                         SET repair_status = 'Fixed' 
                         WHERE maintenance_id = ?";
        $maint_stmt = mysqli_prepare($conn, $update_maint);
        if (!$maint_stmt) {
            throw new Exception("Database error: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($maint_stmt, "i", $maintenance_id);
        if (!mysqli_stmt_execute($maint_stmt)) {
            throw new Exception("Failed to update maintenance status: " . mysqli_error($conn));
        }
        mysqli_stmt_close($maint_stmt);
        
        // 2. Update equipment quantities (damaged_qty - 1, available_qty + 1)
        $update_equip = "UPDATE Equipment 
                         SET damaged_qty = damaged_qty - 1,
                             available_qty = available_qty + 1
                         WHERE equipment_id = ? AND damaged_qty > 0";
        $equip_stmt = mysqli_prepare($conn, $update_equip);
        if (!$equip_stmt) {
            throw new Exception("Database error: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($equip_stmt, "i", $equipment_id);
        if (!mysqli_stmt_execute($equip_stmt)) {
            throw new Exception("Failed to update equipment inventory: " . mysqli_error($conn));
        }
        
        $affected = mysqli_stmt_affected_rows($equip_stmt);
        mysqli_stmt_close($equip_stmt);
        
        if ($affected === 0) {
            throw new Exception("Equipment inventory update failed - no rows affected");
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => "Equipment '" . $equipment['name'] . "' is now FULLY FIXED and ready to use!",
            'equipment_name' => $equipment['name']
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
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
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM Maintenance 
                    WHERE repair_status IN ('Pending', 'In-Repair')";
    $count_result = mysqli_query($conn, $count_query);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_items = (int)$count_row['total'];
    $total_pages = ceil($total_items / ITEMS_PER_PAGE);
    
    // Ensure page is within range
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * ITEMS_PER_PAGE;
    }
    
    // Get maintenance records
    $records = getMaintenanceRecords($conn, ITEMS_PER_PAGE, $offset);
    
    // Get overall statistics
    $stats = getMaintenanceStats($conn);
    
    return [
        'records' => $records,
        'total_pending' => $stats['pending'],
        'total_items' => $stats['total'],
        'page' => $page,
        'total_pages' => $total_pages
    ];
}

/**
 * Get maintenance statistics
 */
function getMaintenanceStats($conn) {
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN repair_status IN ('Pending', 'In-Repair') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN repair_status = 'Fixed' THEN 1 ELSE 0 END) as fixed,
                SUM(CASE WHEN repair_status = 'Scrapped' THEN 1 ELSE 0 END) as scrapped
              FROM Maintenance";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        return ['pending' => 0, 'total' => 0, 'fixed' => 0, 'scrapped' => 0];
    }
    
    $row = mysqli_fetch_assoc($result);
    return [
        'pending' => (int)$row['pending'] ?: 0,
        'total' => (int)$row['total'] ?: 0,
        'fixed' => (int)$row['fixed'] ?: 0,
        'scrapped' => (int)$row['scrapped'] ?: 0
    ];
}

/**
 * Get maintenance records with equipment details
 */
function getMaintenanceRecords($conn, $limit, $offset) {
    $query = "SELECT 
                m.maintenance_id,
                m.equipment_id,
                m.date_reported,
                m.issue_description,
                m.repair_status,
                e.name as equipment_name,
                e.storage_location,
                e.damaged_qty
              FROM Maintenance m
              JOIN Equipment e ON m.equipment_id = e.equipment_id
              WHERE m.repair_status IN ('Pending', 'In-Repair')
              ORDER BY m.date_reported DESC
              LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $records = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $records;
}

/**
 * Helper: Send JSON error response
 */
function sendJsonError($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Render the complete maintenance page
 */
function renderMaintenancePage($data) {
    ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        /* ──────────────────────────────────────────────────── */
        /* MAINTENANCE MODULE STYLES */
        /* ──────────────────────────────────────────────────── */
        
        :root {
            --maint-bg:          #ecefec;
            --maint-hover:       #8faadc;
            --maint-buttons:     #0c1f3f;
            --maint-border:      rgba(0, 0, 0, 0.05);
            --maint-row-even:    #f0f4f9;
            --maint-accent-red:  #e11d48;
            --maint-muted:       #64748b;
            --maint-status-ok:   #10b981;
            --maint-status-warn: #f59e0b;
            --maint-status-info: #3b82f6;
            --maint-status-gray: #64748b;
        }

        .maint-container {
            font-family: 'Inter', sans-serif;
            background: var(--maint-bg);
            padding: 20px 40px 20px 40px;
            border-radius: none;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 60px);
        }

        .maint-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .maint-header-text {
            font-size: 14px;
            color: #000000;
            font-weight: 400;
            margin: 0;
        }

        .maint-divider {
            border: none;
            border-bottom: 1px solid var(--maint-border);
            margin-bottom: 20px;
        }

        .maint-table-wrapper {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .maint-table {
            width: 100%;
            border-collapse: collapse;
        }

        .maint-table th {
            background: var(--maint-hover);
            color: #ecefec;
            font-size: 13px;
            font-weight: 400;
            padding: 10px 20px;
            text-transform: uppercase;
            text-align: left;
        }

        .maint-table td {
            padding: 10px 20px;
            font-size: 14px;
            color: #000000;
            border-bottom: 1px solid var(--maint-border);
            vertical-align: middle;
        }

        .maint-table tbody tr.main-row:nth-child(odd) { background-color: #ffffff; }
        .maint-table tbody tr.main-row:nth-child(even) { background-color: #f9fafb; }

        .maint-table tbody tr.main-row:hover {
            background-color: #f0f4f9;
        }

        .equipment-info {
            display: flex;
            flex-direction: column;
        }

        .equipment-name {
            font-weight: 600;
            color: #000000;
        }

        .equipment-location {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        .issue-description {
            max-width: 300px;
            white-space: normal;
            word-wrap: break-word;
            color: #334155;
            font-size: 13px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            min-width: 70px;
        }

        .status-pending {
            background-color: #fff7ed;
            color: #92400e;
        }

        .status-in-repair {
            background-color: #eff6ff;
            color: #1e40af;
        }

        .status-fixed {
            background-color: #f0fdf4;
            color: #166534;
        }

        .status-scrapped {
            background-color: #f1f5f9;
            color: #475569;
        }

        .actions-cell {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 6px 10px;
            border: 1px solid var(--maint-border);
            background: white;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            font-size: 12px;
            font-weight: 600;
            color: var(--maint-buttons);
        }

        .btn-action:hover:not(:disabled) {
            background: var(--maint-hover);
            color: white;
        }

        .btn-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-action span {
            font-size: 8px;
            margin-right: 4px;
        }

        /* Pagination */
        .saas-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--maint-border);
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
            background: var(--maint-buttons);
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
            color: var(--maint-buttons);
            border: 1px solid var(--maint-border);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .saas-nav-btn:hover:not(.disabled) {
            background: var(--maint-hover);
            color: white;
        }

        .saas-nav-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="maint-container">
    <!-- Header -->
    <div class="maint-header-row">
        <p class="maint-header-text">
            Tracking <b><?php echo number_format($data['total_pending']); ?></b> items pending repair.
        </p>
    </div>

    <hr class="maint-divider">

    <!-- Data Table -->
    <div class="maint-table-wrapper">
        <table class="maint-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Maint. ID</th>
                    <th style="width: 20%;">Equipment Name</th>
                    <th style="width: 28%;">Issue Description</th>
                    <th style="width: 12%;">Date Reported</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 8%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($data['records'])): ?>
                    <?php foreach($data['records'] as $row): ?>
                    <tr class="main-row" data-maintenance-id="<?php echo $row['maintenance_id']; ?>" 
                        data-equipment-id="<?php echo $row['equipment_id']; ?>">
                        <td>M-<?php echo str_pad($row['maintenance_id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td>
                            <div class="equipment-info">
                                <div class="equipment-name"><?php echo htmlspecialchars($row['equipment_name']); ?></div>
                                <div class="equipment-location"><?php echo htmlspecialchars($row['storage_location']); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="issue-description" title="<?php echo htmlspecialchars($row['issue_description']); ?>">
                                <?php echo htmlspecialchars(substr($row['issue_description'], 0, 50)); 
                                      if (strlen($row['issue_description']) > 50) echo '...'; ?>
                            </div>
                        </td>
                        <td><?php echo date('n-j-y', strtotime($row['date_reported'])); ?></td>
                        <td>
                            <?php 
                            $status_class = 'status-' . strtolower(str_replace('-', '_', $row['repair_status']));
                            ?>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($row['repair_status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <?php if ($row['repair_status'] !== 'Fixed' && $row['repair_status'] !== 'Scrapped'): ?>
                                <button class="btn-action" title="Mark as Fixed"
                                    onclick="markEquipmentFixed(<?php echo $row['maintenance_id']; ?>, <?php echo $row['equipment_id']; ?>)">
                                    <span class="material-symbols-outlined">done_all</span> Fix
                                </button>
                                <?php else: ?>
                                <button class="btn-action" disabled title="<?php echo $row['repair_status']; ?>">
                                    <span class="material-symbols-outlined">block</span> N/A
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            No pending repairs.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if($data['total_pages'] > 1): ?>
    <div class="saas-pagination">
        <a href="#" onclick="loadModule('modules/equipments/maintenance_list.php?page=<?php echo max(1, $data['page'] - 1); ?>'); return false;" 
           class="saas-nav-btn <?php echo ($data['page'] <= 1) ? 'disabled' : ''; ?>">
            <span class="material-symbols-outlined" style="font-size: 18px;">chevron_left</span> Previous
        </a>
        <div class="saas-pages">
            <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
            <a href="#" onclick="loadModule('modules/equipments/maintenance_list.php?page=<?php echo $i; ?>'); return false;" 
               class="saas-page-link <?php echo ($i == $data['page']) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <a href="#" onclick="loadModule('modules/equipments/maintenance_list.php?page=<?php echo min($data['total_pages'], $data['page'] + 1); ?>'); return false;" 
           class="saas-nav-btn <?php echo ($data['page'] >= $data['total_pages']) ? 'disabled' : ''; ?>">
            Next <span class="material-symbols-outlined" style="font-size: 18px;">chevron_right</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
// ──────────────────────────────────────────────────
// Mark Equipment Fixed Functions
// ──────────────────────────────────────────────────

function markEquipmentFixed(maintenanceId, equipmentId) {
    if (!confirm('Mark equipment as FULLY FIXED?\nThis will move it back to available inventory.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'mark_fixed');
    formData.append('maintenance_id', maintenanceId);
    formData.append('equipment_id', equipmentId);
    
    // Get button element and row
    const btn = event.target.closest('.btn-action');
    const row = event.target.closest('.main-row');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Loading';
    
    fetch('modules/equipments/maintenance_list.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Show success message in alert
            alert('✓ ' + data.message);
            
            // Remove row from table with fade effect
            row.style.opacity = '0';
            row.style.transition = 'opacity 0.3s ease-out';
            setTimeout(() => row.remove(), 300);
        } else {
            alert('❌ ' + (data.message || 'Failed to mark equipment as fixed'));
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Network error occurred');
        btn.innerHTML = originalContent;
        btn.disabled = false;
    });
}
</script>

</body>
</html>
<?php
}

?>