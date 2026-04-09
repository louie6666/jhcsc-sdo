<?php
/**
 * Analytics Module - Equipment & Transaction Analytics
 * 
 * Features:
 * - Equipment Health Dashboard (Pie Chart)
 * - Most Popular Equipment (Bar Chart)
 * - Department Leaderboard (Damaged Items)
 * - Equipment Lifecycle Tracking
 * - Monthly Report Download (PDF)
 * - Inventory Audit Export (CSV)
 */

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// ═══════════════════════════════════════════════════════════════════════════════
// 1. GET ANALYTICS DATA FROM DATABASE
// ═══════════════════════════════════════════════════════════════════════════════

// Equipment Health - Count by status
$health_query = "SELECT 
                    SUM(available_qty) as available,
                    SUM(damaged_qty) as damaged,
                    (SELECT COUNT(*) FROM Transaction_Items WHERE item_status = 'Borrowed') as borrowed
                FROM Equipment";
$health_result = mysqli_query($conn, $health_query);
$health_data = mysqli_fetch_assoc($health_result);

// Most Popular Equipment - Top 5 this month
$current_month = date('Y-m-01');
$popular_query = "SELECT e.name, COUNT(ti.item_record_id) as times_borrowed
                  FROM Transaction_Items ti
                  JOIN Equipment e ON ti.equipment_id = e.equipment_id
                  JOIN Transaction_Headers th ON ti.header_id = th.header_id
                  WHERE DATE(th.borrow_date) >= '$current_month' AND ti.item_status != 'Lost'
                  GROUP BY e.equipment_id, e.name
                  ORDER BY times_borrowed DESC
                  LIMIT 5";
$popular_result = mysqli_query($conn, $popular_query);
$popular_data = mysqli_fetch_all($popular_result, MYSQLI_ASSOC);

// Department Leaderboard - Damaged items
$dept_query = "SELECT b.department, COUNT(ti.item_record_id) as damaged_count
               FROM Transaction_Items ti
               JOIN Transaction_Headers th ON ti.header_id = th.header_id
               JOIN Borrowers b ON th.borrower_id = b.borrower_id
               WHERE ti.item_status = 'Damaged'
               GROUP BY b.department
               ORDER BY damaged_count DESC";
$dept_result = mysqli_query($conn, $dept_query);
$dept_data = mysqli_fetch_all($dept_result, MYSQLI_ASSOC);

// Equipment Lifecycle
$lifecycle_query = "SELECT 
                        c.category_name,
                        COUNT(e.equipment_id) as total_items,
                        COALESCE(SUM(e.total_qty), 0) as starting_stock,
                        COALESCE(SUM(e.available_qty), 0) as currently_available,
                        COALESCE(SUM(e.damaged_qty), 0) as damaged_count
                    FROM Equipment e
                    LEFT JOIN Categories c ON e.category_id = c.category_id
                    GROUP BY e.category_id, c.category_name
                    ORDER BY total_items DESC";
$lifecycle_result = mysqli_query($conn, $lifecycle_query);
$lifecycle_data = mysqli_fetch_all($lifecycle_result, MYSQLI_ASSOC);

// Handle Export CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    exportInventoryCSV($conn);
}

// Handle Download PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_pdf') {
    downloadMonthlyReportPDF($conn, $health_data, $popular_data, $dept_data);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            --accent-green: #10b981;
            --accent-red: #ef4444;
            --accent-yellow: #f59e0b;
            --button-dark: #0c1f3f;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .analytics-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
        }

        /* Header */
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
        }

        .analytics-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .analytics-header p {
            font-size: 12px;
            font-weight: 400;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--button-dark);
            color: white;
        }

        .btn-primary:hover {
            background: #162a4a;
        }

        .btn-secondary {
            background: white;
            color: var(--button-dark);
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        /* Grid Layouts */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            min-height: 400px;
        }

        .kpi-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .kpi-description {
            font-size: 12px;
            font-weight: 400;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 20px;
        }

        .data-section {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
        }

        .section-description {
            font-size: 12px;
            font-weight: 400;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        /* Table Styles */
        .table-wrapper {
            overflow-x: auto;
        }

        .analytics-table {
            width: 100%;
            border-collapse: collapse;
        }

        .analytics-table th {
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

        .analytics-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-light);
            font-size: 14px;
            font-weight: 400;
            color: var(--text-primary);
        }

        .analytics-table tbody tr:hover {
            background: #f9fafb;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-available {
            background: #dcfce7;
            color: #166534;
        }

        .badge-borrowed {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-damaged {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 400;
        }

        /* Full Width Section */
        .section-full {
            grid-column: 1 / -1;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .main-grid {
                grid-template-columns: 1fr;
            }

            .analytics-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="analytics-container">
    <!-- Header -->
    <div class="analytics-header">
        <div>
            <h1>Analytics Dashboard</h1>
            <p>Equipment health, usage trends, and department performance metrics</p>
        </div>
        <div class="header-actions">
            <form method="POST" style="display: contents;">
                <input type="hidden" name="action" value="download_pdf">
                <button type="submit" class="btn btn-primary">📄 Monthly Report</button>
            </form>
            <form method="POST" style="display: contents;">
                <input type="hidden" name="action" value="export_csv">
                <button type="submit" class="btn btn-secondary">📊 Inventory Audit</button>
            </form>
        </div>
    </div>

    <!-- KPI Cards with Charts -->
    <div class="kpi-grid">
        <!-- Equipment Health -->
        <div class="kpi-card">
            <h3 class="kpi-title">Equipment Health</h3>
            <p class="kpi-description">Current status breakdown of all equipment inventory</p>
            <div class="chart-container">
                <canvas id="healthChart"></canvas>
            </div>
        </div>

        <!-- Most Popular Equipment -->
        <div class="kpi-card">
            <h3 class="kpi-title">Most Popular Equipment</h3>
            <p class="kpi-description">Top 5 items borrowed this month</p>
            <div class="chart-container">
                <canvas id="popularChart"></canvas>
            </div>
        </div>

        <!-- This Month Stats (Placeholder for third KPI) -->
        <div class="kpi-card">
            <h3 class="kpi-title">This Month Activity</h3>
            <p class="kpi-description">Transaction statistics for current month</p>
            <div style="padding: 20px; text-align: center;">
                <div style="font-size: 36px; font-weight: 700; color: var(--accent-blue); margin-bottom: 8px;">
                    <?php 
                        $current_month = date('Y-m-01');
                        $trans_query = "SELECT COUNT(DISTINCT th.header_id) as count FROM Transaction_Headers th WHERE DATE(th.borrow_date) >= '$current_month'";
                        $trans_result = mysqli_query($conn, $trans_query);
                        $trans_row = mysqli_fetch_assoc($trans_result);
                        echo $trans_row['count'] ?? 0;
                    ?>
                </div>
                <p style="font-size: 12px; color: var(--text-secondary);">Total Transactions</p>
            </div>
        </div>
    </div>

    <!-- Main Data Tables -->
    <div class="main-grid">
        <!-- Department Leaderboard -->
        <div class="data-section">
            <h3 class="section-title">Department Leaderboard</h3>
            <p class="section-description">Damaged items grouped by department</p>
            
            <?php if (!empty($dept_data)): ?>
            <div class="table-wrapper">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Damaged Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dept_data as $dept): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dept['department'] ?? 'N/A'); ?></td>
                            <td>
                                <strong style="color: var(--accent-red);">
                                    <?php echo $dept['damaged_count']; ?>
                                </strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">No damage records found</div>
            <?php endif; ?>
        </div>

        <!-- Equipment Lifecycle -->
        <div class="data-section">
            <h3 class="section-title">Equipment by Category</h3>
            <p class="section-description">Inventory breakdown and status by category</p>
            
            <?php if (!empty($lifecycle_data)): ?>
            <div class="table-wrapper">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Items</th>
                            <th>Total Stock</th>
                            <th>Damaged</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lifecycle_data as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                            <td>
                                <span class="badge badge-available"><?php echo $item['total_items']; ?></span>
                            </td>
                            <td><?php echo $item['starting_stock']; ?></td>
                            <td>
                                <span class="badge badge-damaged"><?php echo $item['damaged_count']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">No equipment categories found</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js Scripts -->
<script>
// Chart Colors
const chartColors = {
    available: 'rgba(16, 185, 129, 1)',
    borrowed: 'rgba(59, 130, 246, 1)',
    damaged: 'rgba(239, 68, 68, 1)',
    activity: 'rgba(249, 115, 22, 1)'
};

const chartBgColors = {
    available: 'rgba(16, 185, 129, 0.1)',
    borrowed: 'rgba(59, 130, 246, 0.1)',
    damaged: 'rgba(239, 68, 68, 0.1)',
    activity: 'rgba(249, 115, 22, 0.1)'
};

// Equipment Health - Pie Chart
const healthCtx = document.getElementById('healthChart');
if (healthCtx) {
    new Chart(healthCtx, {
        type: 'doughnut',
        data: {
            labels: ['Available', 'Borrowed', 'Damaged/Repair'],
            datasets: [{
                data: [
                    <?php echo $health_data['available'] ?? 0; ?>,
                    <?php echo $health_data['borrowed'] ?? 0; ?>,
                    <?php echo $health_data['damaged'] ?? 0; ?>
                ],
                backgroundColor: [
                    chartColors.available,
                    chartColors.borrowed,
                    chartColors.damaged
                ],
                borderColor: ['#ffffff', '#ffffff', '#ffffff'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 12, weight: '400' },
                        color: '#64748b',
                        padding: 15,
                        boxWidth: 12,
                        boxHeight: 12
                    }
                }
            }
        }
    });
}

// Most Popular Equipment - Bar Chart
const popularCtx = document.getElementById('popularChart');
if (popularCtx) {
    new Chart(popularCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($popular_data as $item) { echo "'" . htmlspecialchars($item['name']) . "',"; } ?>
            ],
            datasets: [{
                label: 'Times Borrowed',
                data: [
                    <?php foreach ($popular_data as $item) { echo $item['times_borrowed'] . ","; } ?>
                ],
                backgroundColor: chartColors.borrowed,
                borderColor: chartColors.borrowed,
                borderWidth: 0,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { drawBorder: false, color: 'transparent' },
                    ticks: { font: { size: 12 } }
                },
                y: {
                    border: { display: false },
                    grid: { drawBorder: false },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
}
</script>

</body>
</html>

<?php

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Export Inventory Audit as CSV
 */
function exportInventoryCSV($conn) {
    $csv = "Equipment ID,Name,Category,Total Qty,Available,Borrowed,Damaged,Storage Location\n";
    
    $query = "SELECT e.equipment_id, e.name, c.category_name, e.total_qty, e.available_qty, 
                     (SELECT COUNT(*) FROM Transaction_Items WHERE equipment_id = e.equipment_id AND item_status = 'Borrowed') as borrowed,
                     e.damaged_qty, e.storage_location
              FROM Equipment e
              LEFT JOIN Categories c ON e.category_id = c.category_id
              ORDER BY e.equipment_id";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $csv .= implode(',', array_map(function($val) {
            return '"' . str_replace('"', '""', $val) . '"';
        }, array_values($row))) . "\n";
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_audit_' . date('Y-m-d') . '.csv"');
    echo $csv;
    exit;
}

/**
 * Download Monthly Report as PDF
 */
function downloadMonthlyReportPDF($conn, $health_data, $popular_data, $dept_data) {
    // Generate simple HTML report and convert to text/data
    $html = "
    MONTHLY ANALYTICS REPORT
    Generated: " . date('Y-m-d H:i:s') . "
    
    EQUIPMENT HEALTH SUMMARY
    Available: " . ($health_data['available'] ?? 0) . "
    Borrowed: " . ($health_data['borrowed'] ?? 0) . "
    Damaged/Under Repair: " . ($health_data['damaged'] ?? 0) . "
    
    TOP 5 MOST BORROWED THIS MONTH
    ";
    
    foreach ($popular_data as $item) {
        $html .= $item['name'] . " - " . $item['times_borrowed'] . " times\n";
    }
    
    $html .= "
    DEPARTMENT DAMAGE LEADERBOARD
    ";
    
    foreach ($dept_data as $dept) {
        $html .= ($dept['department'] ?? 'N/A') . " - " . $dept['damaged_count'] . " items\n";
    }
    
    // For now, output as text file (can be upgraded to MPDF later)
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="monthly_report_' . date('Y-m-d') . '.txt"');
    echo $html;
    exit;
}

?>