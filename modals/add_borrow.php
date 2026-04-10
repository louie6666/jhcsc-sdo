<?php 
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php'; 

// Fetch available equipment (excluding equipment already borrowed)
$equip_query = "SELECT equipment_id, name, available_qty FROM equipment WHERE available_qty > 0 ORDER BY name ASC";
$equip_result = $conn->query($equip_query);
$available_items = [];
if ($equip_result) {
    while($row = $equip_result->fetch_assoc()) {
        $available_items[] = $row;
    }
}
?>

<style>
    :root {
        --add-bg: #f8fafc;
        --add-card: #ffffff;
        --add-border: #e2e8f0;
        --add-text: #0f172a;
        --add-muted: #64748b;
    }

    .add-borrow-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(1px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        font-family: 'Inter', sans-serif;
    }

    .add-borrow-overlay.hidden { display: none; }

    .add-borrow-modal {
        background: var(--add-card);
        width: 100%;
        max-width: 600px;
        border-radius: 8px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        border: 1px solid var(--add-border);
        max-height: 90vh;
        overflow-y: auto;
    }

    .add-borrow-header {
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: none;
        position: sticky;
        top: 0;
        background: var(--add-card);
    }

    .add-borrow-header h3 {
        margin: 0;
        font-size: 18px;
        color: var(--add-text);
        font-weight: 700;
    }

    .add-borrow-close {
        background: none;
        border: none;
        font-size: 20px;
        color: var(--add-muted);
        cursor: pointer;
        font-family: 'Inter', sans-serif;
        font-weight: 700;
        line-height: 1;
    }

    .add-borrow-form { padding: 24px; }

    .form-section {
        margin-bottom: 24px;
    }

    .section-title {
        font-size: 12px;
        font-weight: 400;
        color: var(--add-muted);
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin-bottom: 12px;
        display: block;
    }

    .search-wrapper { position: relative; margin-bottom: 16px; }

    .add-search-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--add-border);
        border-radius: 6px;
        font-size: 13px;
        outline: none;
    }

    .add-search-input:focus { 
        border-color: #3b82f6;
        background: #fff;
    }

    .add-dropdown {
        position: absolute;
        width: 100%;
        top: 100%;
        left: 0;
        background: white;
        border: 1px solid var(--add-border);
        border-top: none;
        border-radius: 0 0 6px 6px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 10000;
        display: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .add-drop-item {
        padding: 10px 12px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9;
        font-size: 12px;
        transition: background 0.15s;
    }

    .add-drop-item:hover { background: #f0f4f9; }

    .current-items {
        background: #f8fafc;
        border: 1px solid var(--add-border);
        border-radius: 6px;
        padding: 12px;
        max-height: 200px;
        overflow-y: auto;
    }

    .current-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
        font-size: 13px;
    }

    .current-item:last-child { border-bottom: none; }

    .item-name { font-weight: 400; color: var(--add-text); }
    .item-status { font-size: 11px; color: var(--add-muted); }

    .added-items {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 12px;
        min-height: 20px;
    }

    .added-chip {
        background: #eff6ff;
        border: 1px solid #dbeafe;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        color: #1e40af;
        font-weight: 400;
    }

    .chip-remove {
        background: none;
        border: none;
        color: #ef4444;
        cursor: pointer;
        font-size: 14px;
        padding: 0;
        font-weight: bold;
    }

    .add-borrow-footer {
        padding: 16px 24px;
        border-top: none;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: var(--add-card);
        position: sticky;
        bottom: 0;
    }

    .btn-cancel {
        background: #cbd5e1;
        color: #334155;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-submit {
        background: #0f172a;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-submit:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
    }
</style>

<div id="addBorrowModal" class="add-borrow-overlay hidden">
    <div class="add-borrow-modal">
        <div class="add-borrow-header">
            <h3>Add Equipment to Transaction</h3>
            <button class="add-borrow-close" onclick="closeAddBorrowModal()">&times;</button>
        </div>

        <div class="add-borrow-form">
            <!-- Current Items Section (Read-Only) -->
            <div class="form-section">
                <label class="section-title">Currently Borrowed (Read-Only)</label>
                <div id="currentItemsList" class="current-items">
                    <div style="padding: 12px; text-align: center; color: var(--add-muted); font-size: 12px;">Loading...</div>
                </div>
            </div>

            <!-- Add New Equipment Section -->
            <div class="form-section">
                <label class="section-title">Add New Equipment</label>
                <div class="search-wrapper">
                    <input type="text" id="addBorrowSearch" class="add-search-input" placeholder="Search equipment to add..." autocomplete="off">
                    <div id="addBorrowDropdown" class="add-dropdown"></div>
                </div>
                <div id="addedItems" class="added-items"></div>
            </div>
        </div>

        <div class="add-borrow-footer">
            <button type="button" class="btn-cancel" onclick="closeAddBorrowModal()">Cancel</button>
            <button type="button" id="addBorrowSubmit" class="btn-submit" onclick="submitAddBorrow()" disabled>Add Selected Items</button>
        </div>
    </div>
</div>

<script>
    // State for ADD BORROW modal
    let addBorrowState = {
        headerId: null,
        borrowerId: null,
        currentItems: [],
        stagedItems: [],
        availableEquipment: <?php echo json_encode($available_items); ?>
    };

    // Open ADD BORROW modal
    function openAddBorrowModal(headerId, borrowerId) {
        addBorrowState.headerId = headerId;
        addBorrowState.borrowerId = borrowerId;
        addBorrowState.stagedItems = [];
        
        const modal = document.getElementById('addBorrowModal');
        modal.classList.remove('hidden');
        
        // Fetch current items
        fetchCurrentItems(headerId);
        document.getElementById('addBorrowSearch').focus();
    }

    // Close ADD BORROW modal
    function closeAddBorrowModal() {
        const modal = document.getElementById('addBorrowModal');
        modal.classList.add('hidden');
        addBorrowState.stagedItems = [];
        document.getElementById('addedItems').innerHTML = '';
        document.getElementById('addBorrowSearch').value = '';
    }

    // Fetch current borrowed items
    function fetchCurrentItems(headerId) {
        fetch('modules/transactions/get_borrowed_items.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ header_id: headerId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                addBorrowState.currentItems = data.items;
                renderCurrentItems();
            }
        })
        .catch(err => console.error('Error fetching items:', err));
    }

    // Render current items (read-only)
    function renderCurrentItems() {
        const container = document.getElementById('currentItemsList');
        
        if (addBorrowState.currentItems.length === 0) {
            container.innerHTML = '<div style="padding: 12px; text-align: center; color: var(--add-muted); font-size: 12px;">No items currently borrowed</div>';
            return;
        }
        
        container.innerHTML = addBorrowState.currentItems.map(item => `
            <div class="current-item">
                <span class="item-name">${item.name}</span>
                <span class="item-status">Due: ${new Date(item.due_date).toLocaleDateString()}</span>
            </div>
        `).join('');
    }

    // Equipment search and filter
    const searchInput = document.getElementById('addBorrowSearch');
    const dropdown = document.getElementById('addBorrowDropdown');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            
            if (!query) {
                dropdown.style.display = 'none';
                return;
            }

            // Filter equipment, excluding already borrowed and staged
            const borrowed_ids = addBorrowState.currentItems.map(i => i.equipment_id).concat(addBorrowState.stagedItems.map(s => s.id));
            const matches = addBorrowState.availableEquipment.filter(e =>
                e.name.toLowerCase().includes(query) &&
                !borrowed_ids.includes(String(e.equipment_id))
            );

            if (matches.length === 0) {
                dropdown.innerHTML = '<div style="padding: 10px; text-align: center; color: var(--add-muted); font-size: 12px;">No equipment found</div>';
            } else {
                dropdown.innerHTML = matches.map(e => `
                    <div class="add-drop-item" onclick="stageEquipmentForAdd(${e.equipment_id}, '${e.name.replace(/'/g, "\\'")}', ${e.available_qty})">
                        <span>${e.name}</span>
                        <span style="color: #059669; font-weight: 400; font-size: 11px;">Stock: ${e.available_qty}</span>
                    </div>
                `).join('');
            }
            dropdown.style.display = 'block';
        });

        // Close dropdown on blur
        searchInput.addEventListener('blur', function() {
            setTimeout(() => { dropdown.style.display = 'none'; }, 200);
        });
    }

    // Stage equipment for adding
    function stageEquipmentForAdd(equipId, equipName, availQty) {
        if (addBorrowState.stagedItems.some(s => s.id === String(equipId))) return;
        
        addBorrowState.stagedItems.push({
            id: String(equipId),
            name: equipName,
            available_qty: availQty
        });
        
        renderAddedChips();
        document.getElementById('addBorrowSearch').value = '';
        dropdown.style.display = 'none';
        document.getElementById('addBorrowSearch').focus();
    }

    // Remove staged equipment
    function removeFromStaged(equipId) {
        addBorrowState.stagedItems = addBorrowState.stagedItems.filter(s => s.id !== String(equipId));
        renderAddedChips();
    }

    // Render staged items
    function renderAddedChips() {
        const container = document.getElementById('addedItems');
        const btn = document.getElementById('addBorrowSubmit');
        
        if (addBorrowState.stagedItems.length === 0) {
            container.innerHTML = '';
            btn.disabled = true;
            return;
        }
        
        btn.disabled = false;
        container.innerHTML = addBorrowState.stagedItems.map(s => `
            <div class="added-chip">
                ${s.name}
                <button type="button" class="chip-remove" onclick="removeFromStaged('${s.id}')">&times;</button>
            </div>
        `).join('');
    }

    // Submit add borrow form
    function submitAddBorrow() {
        if (addBorrowState.stagedItems.length === 0) {
            alert('Please select at least one equipment to add');
            return;
        }

        const btn = document.getElementById('addBorrowSubmit');
        btn.textContent = 'Adding...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('header_id', addBorrowState.headerId);
        formData.append('equipment_ids', JSON.stringify(addBorrowState.stagedItems.map(s => s.id)));

        fetch('modules/transactions/process_add_equipment.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Equipment added successfully!');
                closeAddBorrowModal();
                location.reload();
            } else {
                alert(data.message || 'Error adding equipment');
                btn.textContent = 'Add Selected Items';
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Network error. Please try again.');
            btn.textContent = 'Add Selected Items';
            btn.disabled = false;
        });
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('addBorrowModal');
        const modalContent = modal.querySelector('.add-borrow-modal');
        if (modal.classList.contains('hidden') === false && modalContent && !modalContent.contains(e.target)) {
            closeAddBorrowModal();
        }
    });
</script>