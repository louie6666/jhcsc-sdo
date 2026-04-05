<?php
// include_once '../../connection.php'; 
?>

<style>
    :root {
        /* Colors & Layout */
        --ae-bg: #ecefec;
        --ae-font-color: #000000;
        --ae-hover: #8faadc;
        --ae-buttons: #0c1f3f;
        --ae-border-color: #ffffff;
        --ae-radius: 8px;

        /* Font Sizes */
        --ae-fs-label: 12px;
        --ae-fs-button: 14px;
        --ae-fs-info: 16px;
        --ae-fs-title: 20px;

        /* Font Weights */
        --ae-fw-normal: 400;
        --ae-fw-bold: 700;
    }

    .ae-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(12, 31, 63, 0.4); 
        backdrop-filter: blur(8px);
        display: none; 
        justify-content: center;
        align-items: center;
        z-index: 9999;
        padding: 20px;
    }

    .ae-modal-container {
        background: var(--ae-border-color);
        width: 100%;
        max-width: 600px;
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: ae-slideUp 0.3s ease-out;
    }

    @keyframes ae-slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .ae-modal-header {
        padding: 20px 32px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ae-modal-header h2 {
        margin: 0;
        font-size: var(--ae-fs-title);
        font-weight: var(--ae-fw-bold);
        color: var(--ae-buttons);
    }

    .ae-modal-body {
        padding: 32px;
        max-height: 80vh;
        overflow-y: auto;
    }

    .ae-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .ae-full-width { grid-column: span 2; }

    .ae-input-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .ae-input-group label {
        font-size: var(--ae-fs-label);
        font-weight: var(--ae-fw-bold);
        text-transform: uppercase;
        color: #64748b;
    }

    .ae-input-group input, 
    .ae-input-group select {
        padding: 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: var(--ae-radius);
        font-size: var(--ae-fs-button);
        background: #f8fafc;
        transition: 0.2s;
    }

    .ae-input-group input:focus {
        outline: none;
        border-color: var(--ae-hover);
        background: #fff;
    }

    /* Image Upload UI */
    .ae-image-upload-section {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        position: relative;
        background: #f8fafc;
        overflow: hidden;
    }

    #ae-preview-img {
        position: absolute;
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: none;
        background: #fff;
    }

    /* Category Combo Box Logic */
    .ae-category-container {
        position: relative;
        display: flex;
        flex-direction: column;
    }

    .ae-modal-footer {
        padding: 20px 32px;
        background: #f8fafc;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        border-top: 1px solid rgba(0,0,0,0.05);
    }

    .ae-btn {
        padding: 12px 24px;
        border-radius: var(--ae-radius);
        font-weight: var(--ae-fw-bold);
        font-size: var(--ae-fs-button);
        cursor: pointer;
        border: none;
    }

    .ae-btn-cancel { background: #e2e8f0; color: #475569; }
    .ae-btn-save { background: var(--ae-buttons); color: white; }
</style>

<div id="add_equipment" class="ae-modal-overlay">
    <div class="ae-modal-container">
        <div class="ae-modal-header">
            <h2>Add New Equipment</h2>
            <button onclick="closeEquipmentModal()" style="background:none; border:none; cursor:pointer;">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="ae-form" action="modules/equipments/save_equipment.php" method="POST" enctype="multipart/form-data">
            <div class="ae-modal-body">
                <div class="ae-form-grid">
                    
                    <div class="ae-full-width ae-input-group">
                        <label>Equipment Visual</label>
                        <div class="ae-image-upload-section" onclick="document.getElementById('ae-file-input').click()">
                            <img id="ae-preview-img" src="#" alt="Preview">
                            <div class="ae-upload-placeholder" id="ae-placeholder" style="text-align:center; color:#64748b;">
                                <span class="material-symbols-outlined" style="font-size: 32px;">image</span>
                                <p style="margin:0; font-size:12px;">Click to upload</p>
                            </div>
                        </div>
                        <input type="file" id="ae-file-input" name="image" hidden accept="image/*" onchange="previewEquipmentImage(this)">
                    </div>

                    <div class="ae-full-width ae-input-group">
                        <label>Equipment Name</label>
                        <input type="text" name="name" placeholder="e.g. Molten Basketball" required>
                    </div>

                    <div class="ae-input-group">
                        <label>Category</label>
                        <div class="ae-category-container">
                            <input type="text" name="category_name" id="ae-category-input" list="ae-category-list" placeholder="Select or Type..." required>
                            <datalist id="ae-category-list">
                                <option value="Balls">
                                <option value="Rackets">
                                <option value="Tables">
                                <option value="Fitness">
                                <option value="Aquatics">
                                </datalist>
                        </div>
                    </div>

                    <div class="ae-input-group">
                        <label>Quantity</label>
                        <input type="number" name="total_qty" min="1" value="1" required>
                    </div>

                    <div class="ae-input-group">
                        <label>Storage Location</label>
                        <input type="text" name="storage_location" placeholder="e.g. Locker A-1">
                    </div>

                    <div class="ae-input-group">
                        <label>Handled By</label>
                        <input type="text" name="staff_name" placeholder="Your name" required>
                    </div>

                </div>
            </div>

            <div class="ae-modal-footer">
                <button type="button" class="ae-btn ae-btn-cancel" onclick="closeEquipmentModal()">Cancel</button>
                <button type="submit" class="ae-btn ae-btn-save">Save Item</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEquipmentModal() {
    document.getElementById('add_equipment').style.display = 'flex';
}

function closeEquipmentModal() {
    document.getElementById('add_equipment').style.display = 'none';
}

function previewEquipmentImage(input) {
    const preview = document.getElementById('ae-preview-img');
    const placeholder = document.getElementById('ae-placeholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Close on outside click
window.onclick = function(event) {
    const modal = document.getElementById('add_equipment');
    if (event.target == modal) {
        closeEquipmentModal();
    }
}
</script>