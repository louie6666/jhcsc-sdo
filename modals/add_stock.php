<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// PHP logic remains the same - handling the post
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_stock_submit'])) {
    $equipment_id = (int)$_POST['equipment_id'];
    $additional_qty = (int)$_POST['qty_to_add'];

    $update_sql = "UPDATE equipment 
                   SET total_qty = total_qty + $additional_qty, 
                       available_qty = available_qty + $additional_qty 
                   WHERE equipment_id = $equipment_id";

    if (mysqli_query($conn, $update_sql)) {
        echo "success";
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
        exit;
    }
}
?>

<style>
    :root {
        --as-primary: #0c1f3f;
        --as-text-muted: #64748b;
        --as-border: #e2e8f0;
        --as-bg-light: #f8fafc;
        --as-radius: 8px; 
        --as-fs-label: 12px;
        --as-fs-button: 14px;
        --as-fs-info: 16px;
        --as-fs-title: 20px;
    }

    .as-modal-overlay {
        position: fixed; inset: 0;
        background: rgba(15, 23, 42, 0.4); 
        backdrop-filter: blur(1px);
        display: none; justify-content: center; align-items: center;
        z-index: 10000; padding: 20px;
    }

    .as-modal-container {
        font-family: 'Inter', sans-serif;
        background: #ffffff; width: 100%; max-width: 480px;
        border-radius: var(--as-radius);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        animation: as-fade-up 0.2s ease-out; overflow: hidden;
    }

    @keyframes as-fade-up {
        from { transform: translateY(10px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .as-header {
        padding: 10px 20px; display: flex; justify-content: space-between; align-items: center;
        border-bottom: none; background-color: var(--as-bg-light);
    }

    .as-header h3 { margin: 0; font-weight: 700; font-size: var(--as-fs-title); color: var(--as-primary); }
    .as-close-x { background: none; border: none; color: var(--as-primary); font-size: 20px; font-weight: 700; cursor: pointer; }

    .as-body { padding: 10px 20px; }
    .as-field-label { display: block; font-size: var(--as-fs-label); font-weight: 400; color: var(--as-text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.025em; }

    .as-info-display {
        background: var(--as-bg-light); border: 1px solid var(--as-border);
        padding: 10px 16px; border-radius: var(--as-radius);
        margin-bottom: 10px; color: var(--as-primary); font-weight: 400; font-size: var(--as-fs-info);
    }

    .as-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }

    .as-input-styled {
        width: 100%; padding: 10px 16px; border: 1px solid var(--as-border); 
        border-radius: var(--as-radius); font-size: var(--as-fs-info);
        color: var(--as-primary); background: #ffffff; box-sizing: border-box;
    }
    .as-input-readonly { background: var(--as-bg-light); color: var(--as-text-muted); cursor: not-allowed; }

    /* Preview Box Styles */
    .as-adjustment-box {
        background: #f1f5f9; padding: 10px 20px; border-radius: var(--as-radius);
        border: 1px dashed #cbd5e1; margin-top: 10px; display: none;
    }
    .as-adj-title { display: block; font-size: 12px; font-weight: 400; color: #1e293b; }
    .as-adj-text { margin: 4px 0 0; font-size: 13px; color: #64748b; }
    .as-new-total { color: var(--as-primary); font-weight: 400; }

    .as-footer {
        padding: 10px 20px; display: flex; justify-content: flex-end; gap: 12px;
        border-top: none; background-color: var(--as-bg-light);
    }

    .as-btn { padding: 8px 16px; border-radius: 6px; font-weight: 400; font-size: 12px; cursor: pointer; border: none; transition: 0.2s; }
    .as-btn-cancel { background: #f1f5f9; color: #475569; }
    .as-btn-save { background: var(--as-primary); color: white; }
</style>

<div id="add_stock_modal" class="as-modal-overlay">
    <div class="as-modal-container">
        <div class="as-header">
            <h3>Add Stock</h3>
            <button type="button" class="as-close-x" onclick="closeStockModal()">✕</button>
        </div>

        <form id="as-form">
            <div class="as-body">
                <input type="hidden" name="equipment_id" id="as-equipment-id">
                <input type="hidden" name="add_stock_submit" value="1">

                <div style="margin-bottom: 20px;">
                    <label class="as-field-label">Updating Inventory For</label>
                    <div class="as-info-display" id="as-equipment-name">Loading...</div>
                </div>

                <div class="as-grid-2">
                    <div>
                        <label class="as-field-label">Current Stock</label>
                        <input type="text" id="as-current-stock" class="as-input-styled as-input-readonly" readonly>
                    </div>
                    <div>
                        <label class="as-field-label">Add/Remove</label>
                        <input type="number" name="qty_to_add" id="as-qty-input" class="as-input-styled" value="0" required autofocus>
                    </div>
                </div>

                <!-- Live Preview Box -->
                <div id="as-preview-box" class="as-adjustment-box">
                    <span class="as-adj-title">Stock Update Preview</span>
                    <p class="as-adj-text">
                        New Total: <strong id="as-new-total-val" class="as-new-total">0</strong>
                    </p>
                </div>
            </div>

            <div class="as-footer">
                <button type="button" class="as-btn as-btn-cancel" onclick="closeStockModal()">Cancel</button>
                <button type="submit" class="as-btn as-btn-save" id="as-save-btn">Confirm Addition</button>
            </div>
        </form>
    </div>
</div>

<script>
window.stockBaseQty = 0;

function openStockModal(id, name, currentStock) {
    document.getElementById('as-equipment-id').value = id;
    document.getElementById('as-equipment-name').innerText = name;
    document.getElementById('as-current-stock').value = currentStock;
    document.getElementById('as-qty-input').value = 0;
    
    window.stockBaseQty = parseInt(currentStock) || 0;
    
    document.getElementById('as-preview-box').style.display = 'none';
    document.getElementById('add_stock_modal').style.display = 'flex';
}

function closeStockModal() {
    document.getElementById('add_stock_modal').style.display = 'none';
}

// Live Logic for Preview
document.getElementById('as-qty-input').addEventListener('input', function() {
    const val = parseInt(this.value) || 0;
    const preview = document.getElementById('as-preview-box');
    const totalDisplay = document.getElementById('as-new-total-val');
    const saveBtn = document.getElementById('as-save-btn');
    
    if (val !== 0) {
        preview.style.display = 'block';
        totalDisplay.innerText = window.stockBaseQty + val;
        
        // Dynamically shift wording based on polarity
        if (val < 0) {
            saveBtn.innerText = 'Confirm Removing';
            saveBtn.style.backgroundColor = '#dc2626'; // Destructive Red
        } else {
            saveBtn.innerText = 'Confirm Addition';
            saveBtn.style.backgroundColor = 'var(--as-primary)';
        }
    } else {
        preview.style.display = 'none';
        saveBtn.innerText = 'Confirm Addition';
        saveBtn.style.backgroundColor = 'var(--as-primary)';
    }
});

// Form Submission
document.getElementById('as-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = this.querySelector('.as-btn-save');
    const originalText = submitBtn.innerText;
    submitBtn.innerText = 'Saving...';
    submitBtn.disabled = true;

    fetch('modals/add_stock.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => response.text())
    .then(data => {
        if(data.trim() === 'success') {
            closeStockModal();
            if (typeof loadModule === 'function') {
                loadModule('modules/equipments/equipment.php?page=<?php echo isset($page) ? $page : 1; ?>');
            }
            this.reset();
        } else {
            alert(data);
        }
    })
    .catch(error => console.error('Error:', error))
    .finally(() => {
        submitBtn.innerText = originalText;
        submitBtn.disabled = false;
    });
});

window.onclick = function(event) {
    const modal = document.getElementById('add_stock_modal');
    if (event.target == modal) closeStockModal();
}
</script>