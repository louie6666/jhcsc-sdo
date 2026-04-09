<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// --- CSV IMPORT LOGIC (Self-Processing) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($tmpName, 'r')) !== false) {
            
            // Skip the header row
            fgetcsv($handle); 

            while (($data = fgetcsv($handle)) !== false) {
                // Column 0: Name (Required)
                // Column 1: Category (Required)
                // Column 2: Storage Location (Optional)
                // Column 3: Initial Quantity (Default: 1)
                
                $name = mysqli_real_escape_string($conn, trim($data[0] ?? ''));
                $category = mysqli_real_escape_string($conn, trim($data[1] ?? 'Uncategorized'));
                $location = mysqli_real_escape_string($conn, trim($data[2] ?? ''));
                
                // Parse quantity and default to 1 if missing or invalid
                $qtyRaw = trim($data[3] ?? '1');
                $qty = (is_numeric($qtyRaw) && $qtyRaw > 0) ? (int)$qtyRaw : 1;

                if (empty($name)) continue; // Skip blank lines

                // 1. Resolve Category ID dynamically
                $cat_q = "SELECT category_id FROM categories WHERE category_name = '$category' LIMIT 1";
                $cat_res = mysqli_query($conn, $cat_q);
                if ($cat_row = mysqli_fetch_assoc($cat_res)) {
                    $category_id = $cat_row['category_id'];
                } else {
                    mysqli_query($conn, "INSERT INTO categories (category_name) VALUES ('$category')");
                    $category_id = mysqli_insert_id($conn);
                }

                // 2. Conflict Resolution (Add Stock if name exists)
                $chk_q = "SELECT equipment_id FROM equipment WHERE name = '$name' LIMIT 1";
                $chk_res = mysqli_query($conn, $chk_q);
                
                if ($chk_row = mysqli_fetch_assoc($chk_res)) {
                    $eq_id = $chk_row['equipment_id'];
                    mysqli_query($conn, "UPDATE equipment SET total_qty = total_qty + $qty, available_qty = available_qty + $qty WHERE equipment_id = $eq_id");
                } else {
                    mysqli_query($conn, "INSERT INTO equipment (name, category_id, storage_location, total_qty, available_qty) 
                                         VALUES ('$name', $category_id, '$location', $qty, $qty)");
                }
            }
            fclose($handle);
            echo "success";
        } else {
            echo "Error opening uploaded file.";
        }
    } else {
        echo "Error uploading file.";
    }
    exit; // Stop execution here to return only the AJAX text string!
}

// --- FILTERING LOGIC ---
$filter_sql = "";
$filter_params = "";
$order_sql = "ORDER BY equipment_id DESC"; // Default ordering

// 1. Category Filter
if (!empty($_GET['category'])) {
    $cat_id = (int)$_GET['category'];
    $filter_sql .= " AND category_id = $cat_id ";
    $filter_params .= "&category=" . urlencode($_GET['category']);
}

// 2. Status Filter
if (!empty($_GET['filter'])) {
    $current_filter = $_GET['filter'];
    $filter_params .= "&filter=" . urlencode($current_filter);
    
    if ($current_filter == 'low_stock') {
        $filter_sql .= " AND available_qty <= low_stock_threshold AND available_qty > 0 ";
    } elseif ($current_filter == 'out_of_stock') {
        $filter_sql .= " AND available_qty = 0 ";
    } elseif ($current_filter == 'in_demand') {
        // Items with active borrowers, sorted by the highest borrowed amount first
        $filter_sql .= " AND (total_qty - available_qty) > 0 ";
        $order_sql = "ORDER BY (total_qty - available_qty) DESC, equipment_id DESC";
    }
}

// Fetch dynamic ALL categories for the dropdown
$cat_query = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
$all_categories = [];
while($c = mysqli_fetch_assoc($cat_query)) {
    $all_categories[] = $c;
}

// --- PAGINATION LOGIC ---
$limit = 12; // 3 rows of 7 cards
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page == 1) {
    $current_limit = $limit - 1; // Fetch 20 items to make room for the +1 New Item card
    $offset = 0;
} else {
    $current_limit = $limit;
    $offset = ($limit - 1) + (($page - 2) * $limit);
}

// Get dynamic counts for the header from DB relative to the applied filters
$stats_query = "SELECT SUM(total_qty) as physical_sum, COUNT(*) as unique_types, COUNT(DISTINCT category_id) as total_cats FROM equipment WHERE 1=1 $filter_sql";
$stats_result = mysqli_query($conn, $stats_query);
$stats_data = mysqli_fetch_assoc($stats_result);

$physical_sum     = $stats_data['physical_sum'] ?? 0; 
$unique_types     = $stats_data['unique_types'] ?? 0;
$total_categories = $stats_data['total_cats'] ?? 0; 

$total_pages = ceil(($unique_types + 1) / $limit);

// Fetch the filtered equipment items
$query = "SELECT * FROM equipment WHERE 1=1 $filter_sql $order_sql LIMIT $current_limit OFFSET $offset";
$items_result = mysqli_query($conn, $query);
?>

<style>
    :root {
        /* Colors & Layout */
        --equipment-bg: #ecefec;
        --equipment-font-color: #000000;
        --equipment-hover: #8faadc;
        --equipment-buttons: #0c1f3f;
        --equipment-border-color: #ffffff;
        --equipment-radius: 8px;

        /* Card Dimensions 150, 210, 40 */
        --equipment-card-width: 200px;
        --equipment-card-height: 340px;
        --equipment-img-height: 60%; 

        /* Font Sizes */
        --equipment-fs-label: 12px;
        --equipment-fs-button: 12px;
        --equipment-fs-info: 16px;
        --equipment-fs-title: 20px;

        /* Dropdown Fonts */
        --dropdown-fs: 12px;
        --dropdown-fw: 400;

        /* Font Weights */
        --equipment-fw-normal: 400;
        --equipment-fw-bold: 700;

        /* SaaS UI Elements */
        --saas-border: rgba(0, 0, 0, 0.05);

        /* New Import UI Elements defined by user */
        --btn-padding: 8px 20px;
        --btn-radius: 6px;
    }

    /* Scoped Container */
    .equipment-container {
        font-family: 'Inter', sans-serif;
        background: var(--equipment-bg);
        color: var(--equipment-font-color);
        padding: 20px 40px 20px 40px;
        border-radius: 0 0 8px 8px; 
        display: flex;
        flex-direction: column;
        min-height: calc(100vh - 60px);
    }

    .equipment-container .inventory-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 20px; 
    }

    .equipment-container .stats-text { 
        font-size: 14px; 
        font-weight: 400;
        color: #000000;
    }
    
    .equipment-container .stats-text b {
        font-weight: var(--equipment-fw-bold);
    }

    .equipment-container .header-actions { display: flex; gap: 0.75rem; align-items: center; }
    
    .equipment-container .btn-filter {
        display: flex; 
        align-items: center; 
        gap: 8px; 
        padding: var(--btn-padding); 
        background: #f7faf7; 
        border: 1px solid #d8e1d8; 
        border-radius: var(--equipment-radius);
        font-weight: 400; 
        font-size: var(--equipment-fs-button); 
        cursor: pointer; 
        transition: none;
    }
    .equipment-container .btn-filter:hover { 
        background: #f7faf7;
    }
    
    .equipment-container .btn-filter.active-filter {
        border: 1px solid #cfd9cf;
        background: #eef4ee;
    }

    /* --- SAAS Dropdowns --- */
    .saas-dropdown-wrapper {
        position: relative;
        display: inline-block;
    }
    
    .saas-dropdown-menu {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        background-color: #ffffff;
        min-width: 160px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        border: 1px solid var(--saas-border);
        border-radius: var(--equipment-radius);
        z-index: 200;
        padding: 6px 0;
        animation: drop-fade 0.2s ease-out forwards;
    }

    @keyframes drop-fade {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .saas-dropdown-item {
        padding: 10px 16px;
        font-size: var(--dropdown-fs);
        font-weight: var(--dropdown-fw);
        color: var(--equipment-font-color);
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .saas-dropdown-item:hover {
        background-color: #f1f5f9;
        color: var(--equipment-buttons);
    }

    /* Import Button Specific Styles */
    .equipment-container .btn-import {
        display: flex; 
        align-items: center; 
        gap: 8px; 
        padding: var(--btn-padding); 
        background: var(--equipment-buttons); 
        color: white;
        border: none; 
        border-radius: var(--btn-radius);
        font-weight: var(--equipment-fw-normal); 
        font-size: var(--equipment-fs-button); 
        cursor: pointer; 
        transition: 0.2s;
        height: 100%;
    }
    .equipment-container .btn-import:hover { 
        opacity: 0.9;
    }

    /* Import CSV Tooltip */
    .csv-tooltip-container {
        position: relative;
        display: inline-flex;
    }
    
    .csv-tooltip-container .tooltip-text {
        visibility: hidden;
        width: max-content;
        background-color: #ffffff;
        color: var(--equipment-buttons);
        text-align: left;
        border-radius: var(--equipment-radius);
        padding: 10px 15px 10px 10px;
        position: absolute;
        z-index: 100;
        top: 125%; /* Position dynamically below the button */
        left: 50%;
        transform: translateX(-50%); /* Centered perfectly dynamically */
        opacity: 0;
        transition: opacity 0.3s;
        font-size: 11.5px;
        line-height: 1.5;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
        border: 1px solid var(--saas-border);
        pointer-events: none;
    }
    
    .csv-tooltip-container .tooltip-text::after {
        content: "";
        position: absolute;
        bottom: 100%; /* Arrow at the top of the tooltip */
        left: 50%;
        margin-left: -6px;
        border-width: 6px;
        border-style: solid;
        border-color: transparent transparent #ffffff transparent;
    }
    
    .csv-tooltip-container:hover .tooltip-text {
        visibility: visible;
        opacity: 1;
    }
    
    .tooltip-title {
        font-weight: var(--equipment-fw-bold);
        margin-bottom: 8px;
        display: block;
        color: var(--equipment-buttons);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .tooltip-list {
        margin: 0; 
        padding-left: 16px;
    }

    /* Utility Icon Styles */
    .icon-12 { font-size: 12px; }
    .icon-16 { font-size: 16px; }
    .icon-18 { font-size: 18px; }
    .icon-40-primary { font-size: 40px; color: var(--equipment-buttons); }

    /* Grid System - Set to fill 3 rows of 7 (21 total slots) */
    .equipment-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
        gap: 10px; 
        border-top: 1px solid var(--saas-border); 
        padding-top: 20px; 
    }

    /* Shared Card Styles */
    .equipment-container .equipment-card, 
    .equipment-container .card-add-new {
        min-width: var(--equipment-card-width);
        height: var(--equipment-card-height);
        background: var(--equipment-border-color);
        border-radius: var(--equipment-radius);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        border: none; 
    }

    /* HOVER: Add New Card */
    .equipment-container .card-add-new {
        background: transparent;
        border: 2px dashed var(--equipment-buttons);
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0.8;
    }

    .equipment-container .card-add-new:hover {
        opacity: 1;
        background: rgba(143, 170, 220, 0.1);
        border-color: var(--equipment-hover);
    }

    .equipment-container .card-add-new-text {
        font-size: var(--equipment-fs-button); 
        font-weight: var(--equipment-fw-normal);
        margin-top: 8px;
    }

    .equipment-container .image-container { 
        height: var(--equipment-img-height); 
        background: #eef2f6; 
        position: relative; 
        overflow: hidden; 
    }
    
    .equipment-container .image-container img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
        transition: transform 0.5s ease; 
    }

    .equipment-card:hover .image-container img {
        transform: scale(1.1);
    }
    
    .equipment-container .card-body { 
        padding: 10px; 
        flex: 1; 
        display: flex; 
        flex-direction: column; 
    }

    .equipment-container .card-title { 
        font-size: 14px; 
        font-weight: var(--equipment-fw-bold);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .equipment-container .location { 
        font-size: var(--equipment-fs-label); 
        opacity: 0.7;
        display: flex;
        align-items: center;
        gap: 3px;
        margin-top: 2px;
    }

    .equipment-container .stock-bar-container {
        margin-top: 8px;
    }

    .equipment-container .stock-text {
        display: flex;
        justify-content: space-between;
        font-size: 10px;
        margin-bottom: 4px;
        font-weight: var(--equipment-fw-bold);
    }

    .equipment-container .bar-bg {
        height: 6px;
        background: #eee;
        border-radius: 10px;
        overflow: hidden;
    }

    .equipment-container .bar-fill {
        height: 100%;
        border-radius: 10px;
    }

    .equipment-container .card-footer { 
        display: flex; 
        gap: 5px; 
        margin-top: auto;
    }

    .equipment-container .btn-action { 
        flex: 1; 
        background: var(--equipment-buttons); 
        color: white;
        border-radius: var(--btn-radius); 
        font-weight: var(--equipment-fw-normal); 
        font-size: var(--equipment-fs-button); 
        padding: var(--btn-padding); 
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }

    .equipment-container .btn-action-small {
        flex: 0 0 40px;
    }

    .equipment-container .btn-action:hover { 
        background: var(--equipment-hover); 
        color: var(--equipment-font-color);
    }

    /* --- SAAS PAGINATION --- */
    .saas-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
        padding-top: 20px;
        border-top: 1px solid var(--saas-border);
        flex-shrink: 0;
    }

    .saas-nav-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: #ffffff;
        color: #475569;
        text-decoration: none;
        border-radius: var(--equipment-radius);
        font-size: 12px;
        font-weight: 400;
        border: 1px solid #d8e1d8;
        transition: none;
    }

    .saas-nav-btn:hover:not(.disabled) {
        background: #ffffff;
        color: #475569;
        border-color: #d8e1d8;
    }

    .saas-nav-btn.disabled {
        opacity: 0.3;
        cursor: not-allowed;
        pointer-events: none;
    }

    .saas-pages {
        display: flex;
        gap: 6px;
    }

    .saas-page-link {
        min-width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #64748b;
        border-radius: var(--equipment-radius);
        font-size: 12px;
        font-weight: 400;
        transition: none;
    }

    .saas-page-link.active {
        background: #0c1f3f;
        color: white;
    }

    .saas-page-link:hover:not(.active) {
        background: transparent;
    }
</style>

<div class="equipment-container">
   <header class="inventory-header">
    <p class="stats-text">
        There are a total of <b><?php echo number_format($physical_sum); ?></b> physical items recorded across <b><?php echo $total_categories; ?></b> distinct categories.
    </p>
    <div class="header-actions">

        <!-- Tooltip Wrapped Import System -->
        <div class="csv-tooltip-container">
            <button class="btn-import" id="btn-import-trigger" onclick="document.getElementById('csv-upload-input').click();">
                <span class="material-symbols-outlined icon-18">description</span> Import CSV
            </button>
            <div class="tooltip-text">
                <span class="tooltip-title">Expected CSV Columns:</span>
                <ul class="tooltip-list">
                    <li><b>Equipment Name</b> (Required)</li>
                    <li><b>Category</b> (Required)</li>
                    <li><b>Storage Location</b> (Optional)</li>
                    <li><b>Initial Quantity</b> (Default: 1)</li>
                </ul>
            </div>
            <!-- Invisible File Input -->
            <input type="file" id="csv-upload-input" accept=".csv" style="display:none">
        </div>

        <!-- Filter Dropdown -->
        <div class="saas-dropdown-wrapper">
            <button class="btn-filter <?php echo isset($_GET['filter']) && $_GET['filter'] !== '' ? 'active-filter' : ''; ?>" onclick="toggleAppDropdown('filter-menu')">
                <span class="material-symbols-outlined icon-18">filter_list</span> 
                <?php 
                    $fLabel = "Filter";
                    if(isset($_GET['filter'])) {
                        if($_GET['filter'] == 'in_demand') $fLabel = 'In Demand';
                        if($_GET['filter'] == 'low_stock') $fLabel = 'Low Stock';
                        if($_GET['filter'] == 'out_of_stock') $fLabel = 'Out of Stock';
                    }
                    echo $fLabel;
                ?>
            </button>
            <div class="saas-dropdown-menu" id="filter-menu">
                <div class="saas-dropdown-item" onclick="applyGridFilter('filter', '')">All Items</div>
                <div class="saas-dropdown-item" onclick="applyGridFilter('filter', 'in_demand')">In Demand</div>
                <div class="saas-dropdown-item" onclick="applyGridFilter('filter', 'low_stock')">Low Stock</div>
                <div class="saas-dropdown-item" onclick="applyGridFilter('filter', 'out_of_stock')">Out of Stock</div>
            </div>
        </div>

        <!-- Category Dropdown -->
        <div class="saas-dropdown-wrapper">
            <button class="btn-filter <?php echo isset($_GET['category']) && $_GET['category'] !== '' ? 'active-filter' : ''; ?>" onclick="toggleAppDropdown('category-menu')">
                <span class="material-symbols-outlined icon-18">category</span> 
                <?php 
                    $cLabel = "Category";
                    if(isset($_GET['category']) && $_GET['category'] !== '') {
                        foreach($all_categories as $cat) {
                            if($cat['category_id'] == $_GET['category']) {
                                $cLabel = htmlspecialchars($cat['category_name']);
                                break;
                            }
                        }
                    }
                    echo $cLabel;
                ?>
            </button>
            <div class="saas-dropdown-menu" id="category-menu" style="max-height: 300px; overflow-y: auto;">
                <div class="saas-dropdown-item" onclick="applyGridFilter('category', '')">All Categories</div>
                <?php foreach($all_categories as $cat): ?>
                <div class="saas-dropdown-item" onclick="applyGridFilter('category', '<?php echo $cat['category_id']; ?>')">
                    <?php echo htmlspecialchars($cat['category_name']); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</header>

   <div class="equipment-grid">
    <?php if($page == 1 && empty($_GET['filter']) && empty($_GET['category'])): ?>
    <div class="card-add-new" onclick="openEquipmentModal()">
        <span class="material-symbols-outlined icon-40-primary">add_circle</span>
        <p class="card-add-new-text">Add New Equipment</p>
    </div>
    <?php endif; ?>

        <?php 
        $hasItems = false;
        while($row = mysqli_fetch_assoc($items_result)): 
            $hasItems = true;
            $total_qty = ($row['total_qty'] > 0) ? $row['total_qty'] : 1; 
            $percent = ($row['available_qty'] / $total_qty) * 100;
            $img_path = !empty($row['image_url']) ? "uploads/" . $row['image_url'] : "../../uploads/placeholder.png";
            $bar_color = ($row['available_qty'] <= $row['low_stock_threshold']) ? "#ff4d4d" : "#2f5da8"; 
        ?>
        <div class="equipment-card">
            <div class="image-container">
                <img src="<?php echo $img_path; ?>" alt="" onerror="this.src='../../uploads/placeholder.png';">
            </div>
            <div class="card-body">
                <h4 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h4>
                <div class="location">
                    <span class="material-symbols-outlined icon-12">location_on</span>
                    <?php echo htmlspecialchars($row['storage_location'] ?? 'N/A'); ?>
                </div>

                <div class="stock-bar-container">
                    <div class="stock-text">
                        <span>STOCK</span>
                        <span><?php echo $row['available_qty']; ?>/<?php echo $row['total_qty']; ?></span>
                    </div>
                    <div class="bar-bg">
                        <div class="bar-fill" style="width: <?php echo $percent; ?>%; background: <?php echo $bar_color; ?>;"></div>
                    </div>
                </div>

                <div class="card-footer">
                    <button class="btn-action" onclick="openStockModal(<?php echo $row['equipment_id']; ?>, '<?php echo addslashes($row['name']); ?>', <?php echo $row['total_qty']; ?>)">
                        <span class="material-symbols-outlined icon-16">add</span> Stock
                    </button>
                    <button class="btn-action btn-action-small" onclick="openEditModal(<?php echo $row['equipment_id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['storage_location'] ?? ''); ?>', '<?php echo addslashes($row['image_url'] ?? ''); ?>')">
                        <span class="material-symbols-outlined icon-18">edit</span>
                    </button>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        
        <?php if(!$hasItems): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #888;">
                No equipment items match the current filters.
            </div>
        <?php endif; ?>
    </div>

    <?php if($unique_types > ($limit - 1)): ?>
    <div class="saas-pagination">
        <a href="#" onclick="loadModule('modules/equipments/equipment.php?page=<?php echo max(1, $page - 1) . $filter_params; ?>'); return false;" class="saas-nav-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
            <span class="material-symbols-outlined icon-18">chevron_left</span> Previous
        </a>

        <div class="saas-pages">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="#" onclick="loadModule('modules/equipments/equipment.php?page=<?php echo $i . $filter_params; ?>'); return false;" class="saas-page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>

        <a href="#" onclick="loadModule('modules/equipments/equipment.php?page=<?php echo min($total_pages, $page + 1) . $filter_params; ?>'); return false;" class="saas-nav-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
            Next <span class="material-symbols-outlined icon-18">chevron_right</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php 
// Modals Included
include __DIR__ . '/../../modals/add_equipment.php'; 
include __DIR__ . '/../../modals/add_stock.php'; 
include __DIR__ . '/../../modals/edit_card.php'; 
?>

<script>
// --- Grid Filter State Management ---
// We attach parameters directly to window so they survive DOM re-executions
window.seisGridFilters = {
    filter: '<?php echo isset($_GET['filter']) ? htmlspecialchars($_GET['filter']) : ''; ?>',
    category: '<?php echo isset($_GET['category']) ? htmlspecialchars($_GET['category']) : ''; ?>'
};

function toggleAppDropdown(menuId) {
    // Close others
    document.querySelectorAll('.saas-dropdown-menu').forEach(el => {
        if(el.id !== menuId) el.style.display = 'none';
    });
    
    // Toggle requested
    const menu = document.getElementById(menuId);
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

function applyGridFilter(type, value) {
    window.seisGridFilters[type] = value;
    
    let url = 'modules/equipments/equipment.php?page=1';
    if(window.seisGridFilters.filter) url += '&filter=' + encodeURIComponent(window.seisGridFilters.filter);
    if(window.seisGridFilters.category) url += '&category=' + encodeURIComponent(window.seisGridFilters.category);
    
    if (typeof loadModule === 'function') {
        loadModule(url);
    }
}

// Close dropdown if clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.saas-dropdown-wrapper')) {
        document.querySelectorAll('.saas-dropdown-menu').forEach(el => el.style.display = 'none');
    }
});


// Logic for Direct Upload
document.getElementById('csv-upload-input').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;

    if (!file.name.endsWith('.csv')) {
        alert("Please upload a valid .csv file.");
        this.value = ''; // Reset
        return;
    }

    const formData = new FormData();
    formData.append('csv_file', file);

    const btn = document.getElementById('btn-import-trigger');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined icon-18">hourglass_top</span> Importing...';
    btn.disabled = true;

    fetch('modules/equipments/equipment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        if(text.trim() === 'success') {
            if (typeof loadModule === 'function') {
                loadModule('modules/equipments/equipment.php?page=<?php echo isset($page) ? $page : 1; ?>');
            }
        } else {
            alert(text);
        }
    })
    .catch(err => console.error('Error:', err))
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        this.value = ''; // Reset input to allow re-uploading
    });
});
</script>