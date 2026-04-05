<?php
// DB Connection Placeholder
// $conn = mysqli_connect("localhost", "root", "", "your_db_name");

// Logic Point: Get counts for the header
$total_items = 1248; 
$total_categories = 14; 
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

        /* Card Dimensions */
        --equipment-card-width: 200px;
        --equipment-card-height: 300px;
        --equipment-img-height: 55%; /* Adjusted to 55% */

        /* Font Sizes */
        --equipment-fs-label: 12px;
        --equipment-fs-button: 14px;
        --equipment-fs-info: 16px;
        --equipment-fs-title: 20px;

        /* Font Weights */
        --equipment-fw-normal: 400;
        --equipment-fw-bold: 700;
    }

    /* Scoped Container */
    .equipment-container {
        font-family: 'Inter', sans-serif;
        background: var(--equipment-bg);
        color: var(--equipment-font-color);
        padding: 20px; /* Adjusted outside margin to 20px */
        border-radius: none; /* Added 8px radius to main container */
        min-height: 100vh;
    }

    .equipment-container .inventory-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 2rem; 
    }

    .equipment-container .stats-text { 
        font-size: var(--equipment-fs-info); 
        font-weight: var(--equipment-fw-normal);
    }
    
    .equipment-container .stats-text b {
        font-weight: var(--equipment-fw-bold);
    }

    /* Filter & Category Buttons */
    .equipment-container .header-actions { display: flex; gap: 0.75rem; }
    
    .equipment-container .btn-filter {
        display: flex; 
        align-items: center; 
        gap: 8px; 
        padding: 10px; 
        background: var(--equipment-border-color); 
        border: none; 
        border-radius: var(--equipment-radius);
        font-weight: var(--equipment-fw-normal); 
        font-size: var(--equipment-fs-button); 
        cursor: pointer; 
        transition: 0.2s;
    }
    .equipment-container .btn-filter:hover { 
        background: var(--equipment-hover); 
    }

    /* Grid System */
    .equipment-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, var(--equipment-card-width)); 
        gap: 20px; 
    }

    /* Shared Card Styles */
    .equipment-container .equipment-card, 
    .equipment-container .card-add-new {
        width: var(--equipment-card-width);
        height: var(--equipment-card-height);
        background: var(--equipment-border-color);
        border-radius: var(--equipment-radius);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: 0.3s;
        border: none; 
    }

    .equipment-container .card-add-new {
        background: transparent;
        border: 2px dashed var(--equipment-buttons);
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .equipment-container .image-container { 
        height: var(--equipment-img-height); 
        background: #eef2f6; 
        position: relative; 
    }
    
    .equipment-container .image-container img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
    }
    
    /* Card Body */
    .equipment-container .card-body { 
        padding: 10px; 
        flex: 1; 
        display: flex; 
        flex-direction: column; 
    }

    .equipment-container .card-title { 
        font-size: 14px; /* Balanced for the 200px width */
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

    /* Stock Level Bar */
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
        background: var(--equipment-buttons);
        border-radius: 10px;
    }

    /* Footer Buttons */
    .equipment-container .card-footer { 
        display: flex; 
        gap: 5px; 
        margin-top: auto;
    }

    .equipment-container .btn-action { 
        flex: 1; 
        background: var(--equipment-buttons); 
        color: white;
        border-radius: var(--equipment-radius); 
        font-weight: var(--equipment-fw-normal); 
        font-size: 14px; /* Forced to 14px */
        padding: 10px 5px; 
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .equipment-container .btn-action:hover { 
        background: var(--equipment-hover); 
        color: var(--equipment-font-color);
    }
</style>

<div class="equipment-container">
    <header class="inventory-header">
        <p class="stats-text">
            There are a total of <b><?php echo number_format($total_items); ?></b> items recorded across <b><?php echo $total_categories; ?></b> distinct categories.
        </p>
        <div class="header-actions">
            <button class="btn-filter"><span class="material-symbols-outlined" style="font-size:18px;">filter_list</span> Filter</button>
            <button class="btn-filter"><span class="material-symbols-outlined" style="font-size:18px;">category</span> Category</button>
        </div>
    </header>

    <div class="equipment-grid">
        <div class="card-add-new">
            <span class="material-symbols-outlined" style="font-size: 40px; color: var(--equipment-buttons);">add_circle</span>
            <p style="font-size: var(--equipment-fs-button); font-weight: var(--equipment-fw-bold);">New Item</p>
        </div>

        <?php 
        $items = [
            ['id'=>1, 'name'=>'Spalding TF-1000', 'loc'=>'Storage A', 'avail'=>90, 'total'=>120, 'img'=>'0'],
            ['id'=>2, 'name'=>'Adidas Al Rihla', 'loc'=>'Main Room', 'avail'=>20, 'total'=>200, 'img'=>'1']
        ];

        foreach($items as $row): 
            $percent = ($row['avail'] / $row['total']) * 100;
        ?>
        <div class="equipment-card">
            <div class="image-container">
                <img src="uploads/<?php echo $row['img']; ?>.jpg" alt="Item">
            </div>
            <div class="card-body">
                <h4 class="card-title"><?php echo $row['name']; ?></h4>
                <div class="location">
                    <span class="material-symbols-outlined" style="font-size: 12px;">location_on</span>
                    <?php echo $row['loc']; ?>
                </div>

                <div class="stock-bar-container">
                    <div class="stock-text">
                        <span>STOCK</span>
                        <span><?php echo $row['avail']; ?>/<?php echo $row['total']; ?></span>
                    </div>
                    <div class="bar-bg">
                        <div class="bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                    </div>
                </div>

                <div class="card-footer">
                    <button class="btn-action">Stock</button>
                    <button class="btn-action" style="flex: 0 0 40px;">
                        <span class="material-symbols-outlined" style="font-size: 18px;">edit</span>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>