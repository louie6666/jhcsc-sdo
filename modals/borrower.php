<?php 
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php'; 

// Fetch available equipment
$equip_query = "SELECT equipment_id, name, available_qty FROM equipment WHERE available_qty > 0";
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
        --borrower-bg: #f8fafc;
        --borrower-card: #ffffff;
        --borrower-border: #e2e8f0;
        --borrower-text: #0f172a;
        --borrower-muted: #64748b;
        --borrower-primary: #3b82f6;
        --borrower-primary-hover: #2563eb;
        --borrower-font-main: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        --borrower-font-size-label: 12px; /* Updated to 12px */
        --borrower-font-size-info: 13px;
        --borrower-font-size-title: 18px;
        --borrower-font-weight-bold: 700;
    }

    .borrower-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(1px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        font-family: var(--borrower-font-main);
    }

    .borrower-overlay.hidden { display: none; }

    .borrower-modal {
        background: var(--borrower-card);
        width: 100%;
        max-width: 600px;
        border-radius: 8px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        border: 1px solid var(--borrower-border);
    }

    .borrower-header {
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9;
    }

    .borrower-header h3 {
        margin: 0;
        font-size: var(--borrower-font-size-title);
        color: var(--borrower-text);
        font-weight: var(--borrower-font-weight-bold);
    }

    .borrower-close {
        background: none;
        border: none;
        font-size: 20px;
        color: var(--borrower-muted);
        cursor: pointer;
    }

    .borrower-form { padding: 24px; }

    .borrower-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .col-full { grid-column: span 2; }

    .borrower-field { display: flex; flex-direction: column; }

    .borrower-label {
        font-size: var(--borrower-font-size-label);
        font-weight: var(--borrower-font-weight-bold); /* Bold 12px */
        color: var(--borrower-muted);
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin-bottom: 6px;
    }

    .borrower-input {
        background: #f1f5f9;
        border: 1px solid var(--borrower-border);
        padding: 10px 12px;
        border-radius: 6px;
        font-size: var(--borrower-font-size-info);
        color: var(--borrower-text);
        outline: none;
    }

    .borrower-input:focus {
        background: #fff;
        border-color: var(--borrower-primary);
    }

    .borrower-input[readonly] {
        background: #eef2f6;
        color: #64748b;
    }

    /* Items display below search bar */
    .chip-area {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px; /* Space above chips */
        min-height: 20px;
    }

    .borrower-chip {
        background: #eff6ff;
        border: 1px solid #dbeafe;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        display: flex;
        align-items: center;
        color: #1e40af;
        font-weight: 600;
    }

    .borrower-chip button {
        margin-left: 8px;
        border: none;
        background: none;
        color: #ef4444;
        cursor: pointer;
        font-size: 16px;
        line-height: 1;
    }

    .search-wrapper { position: relative; }

    .borrower-dropdown {
        position: absolute;
        width: 100%;
        top: 100%;
        left: 0;
        background: #fff;
        border: 1px solid var(--borrower-border);
        border-radius: 6px;
        margin-top: 4px;
        max-height: 150px;
        overflow-y: auto;
        z-index: 10;
        display: none;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }

    .dropdown-item {
        padding: 10px;
        font-size: 12px;
        display: flex;
        justify-content: space-between;
        cursor: pointer;
        border-bottom: 1px solid #f8fafc;
    }

    .dropdown-item:hover { background: #f1f5f9; }

    .borrower-footer {
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    .btn-cancel {
        background: lightgray;
        border: none;
        color: white;
        font-weight: 400;
        font-size: 12px;
        cursor: pointer;
        padding: 10px 20px;
        border-radius: 6px;
    }

    .btn-submit {
        background: #0f172a;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 400;
        font-size: 12px;
        cursor: pointer;
    }
</style>

<div id="borrowModal" class="borrower-overlay hidden">
    <div class="borrower-modal">
        <div class="borrower-header">
            <h3>New Borrowing Transaction</h3>
            <button class="borrower-close" onclick="toggleBorrowModal(false)">&times;</button>
        </div>

        <form action="process_borrow.php" method="POST" id="borrowForm" class="borrower-form">
            <div class="borrower-grid">
                
                <div class="borrower-field">
                    <label class="borrower-label">Borrower ID</label>
                    <input type="text" name="id_number" id="id_number" required placeholder="Search ID..." class="borrower-input" onblur="checkBorrower(this.value)">
                </div>

                <div class="borrower-field">
                    <label class="borrower-label">Processed By</label>
                    <input type="text" value="<?php echo $_SESSION['full_name'] ?? 'Staff Name'; ?>" readonly class="borrower-input">
                    <input type="hidden" name="issued_by_staff_id" value="<?php echo $_SESSION['user_id'] ?? 1; ?>">
                </div>

                <div class="borrower-field">
                    <label class="borrower-label">Full Name</label>
                    <input type="text" name="full_name" id="full_name" required placeholder="Enter name..." class="borrower-input">
                </div>

                <div class="borrower-field">
                    <label class="borrower-label">Department</label>
                    <input type="text" name="department" id="department" placeholder="e.g. BSIT" class="borrower-input">
                </div>

                <div class="borrower-field">
                    <label class="borrower-label">Contact Number</label>
                    <input type="text" name="contact_no" id="contact_no" placeholder="09..." class="borrower-input">
                </div>

                <div class="borrower-field">
                    <label class="borrower-label">Return Due Date</label>
                    <input type="datetime-local" name="due_date" required class="borrower-input">
                </div>

                <div class="borrower-field col-full">
                    <label class="borrower-label">Search Equipment</label>
                    <div class="search-wrapper">
                        <input type="text" id="equipSearch" placeholder="Type equipment name..." class="borrower-input">
                        <div id="equipDropdown" class="borrower-dropdown">
                            <?php foreach($available_items as $item): ?>
                                <div class="dropdown-item" onclick="addBorrowItem('<?php echo $item['equipment_id']; ?>', '<?php echo addslashes($item['name']); ?>')">
                                    <span><?php echo $item['name']; ?></span>
                                    <span style="color: #059669; font-weight: bold;">Stock: <?php echo $item['available_qty']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="chipContainer" class="chip-area"></div>
                </div>

            </div>

            <div class="borrower-footer">
                <button type="button" class="btn-cancel" onclick="toggleBorrowModal(false)">Cancel</button>
                <button type="submit" class="btn-submit">Confirm Transaction</button>
            </div>
        </form>
    </div>
</div>

<script>
    const bSearchInput = document.getElementById('equipSearch');
    const bDropdown = document.getElementById('equipDropdown');
    const bChipContainer = document.getElementById('chipContainer');
    const bSelectedIds = new Set();

    function toggleBorrowModal(show) {
        document.getElementById('borrowModal').classList.toggle('hidden', !show);
    }

    // Equipment Search Filter
    bSearchInput.addEventListener('input', (e) => {
        const val = e.target.value.toLowerCase();
        const items = bDropdown.querySelectorAll('.dropdown-item');
        let found = false;
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if(text.includes(val)) {
                item.style.display = 'flex';
                found = true;
            } else {
                item.style.display = 'none';
            }
        });
        bDropdown.style.display = (val && found) ? 'block' : 'none';
    });

    function addBorrowItem(id, name) {
        if(bSelectedIds.has(id)) return;
        bSelectedIds.add(id);
        const chip = document.createElement('div');
        chip.className = 'borrower-chip';
        chip.id = `b-chip-${id}`;
        chip.innerHTML = `${name}<button type="button" onclick="removeBorrowItem('${id}')">&times;</button><input type="hidden" name="equipment_ids[]" value="${id}">`;
        bChipContainer.appendChild(chip);
        bSearchInput.value = '';
        bDropdown.style.display = 'none';
    }

    function removeBorrowItem(id) {
        bSelectedIds.delete(id);
        const chip = document.getElementById(`b-chip-${id}`);
        if(chip) chip.remove();
    }

    // Optional: Add an AJAX call here if you want to auto-fill borrower info based on ID
    function checkBorrower(id) {
        if(!id) return;
        // You would typically fetch details here via fetch('get_borrower.php?id='+id)
    }

    document.addEventListener('click', (e) => {
        if (!bSearchInput.contains(e.target)) bDropdown.style.display = 'none';
    });
</script>