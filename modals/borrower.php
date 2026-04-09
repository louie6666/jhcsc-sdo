<?php 
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php'; 

// Fetch available equipment
$equip_query = "SELECT equipment_id, name, available_qty FROM equipment WHERE available_qty > 0 ORDER BY name ASC";
$equip_result = mysqli_query($conn, $equip_query);
$available_items = [];
if ($equip_result) {
    while($row = mysqli_fetch_assoc($equip_result)) {
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
        overflow: visible;
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

    .borrower-form { 
        padding: 24px;
        overflow: visible;
    }

    .borrower-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        overflow: visible;
    }

    .col-full { grid-column: span 2; }

    .borrower-field { 
        display: flex; 
        flex-direction: column;
        overflow: visible;
        position: relative;
    }

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

    /* Borrower ID input wrapper with check button */
    .borrower-id-wrapper {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }

    .borrower-id-wrapper .borrower-input {
        flex: 1;
    }

    .btn-check-borrower {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
        height: 44px;
        display: flex;
        align-items: center;
    }

    .btn-check-borrower:hover {
        background: #2563eb;
    }

    .btn-check-borrower:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
    }

    .check-status {
        font-size: 11px;
        margin-top: 4px;
        display: none;
    }

    .check-status.success {
        color: #10b981;
        display: block;
    }

    .check-status.error {
        color: #ef4444;
        display: block;
    }

    /* Items display below search bar */
    .chip-area {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
        min-height: 20px;
        padding-right: 0;
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

    .search-wrapper { 
        position: relative;
        width: 100%;
        z-index: 100;
    }

    .borrower-dropdown {
        position: absolute;
        width: 100%;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid var(--borrower-border);
        border-radius: 6px;
        max-height: 150px;
        overflow-y: auto;
        z-index: 9999;
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
        transition: background 0.15s;
        user-select: none;
    }

    .dropdown-item:hover { background: #8faadc; color: white; }
    .dropdown-item:last-child { border-bottom: none; }

    .borrower-error-message {
        background: #fee2e2;
        border: 1px solid #fca5a5;
        color: #dc2626;
        padding: 12px;
        border-radius: 6px;
        font-size: 12px;
        margin-bottom: 16px;
        display: none;
    }

    .borrower-error-message.show {
        display: block;
    }

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
            <div id="errorMessage" class="borrower-error-message"></div>
            
            <div class="borrower-grid">
                
                <!-- Line 1: Borrower ID and Full Name -->
                <div class="borrower-field">
                    <label class="borrower-label">Borrower ID</label>
                    <input type="text" name="id_number" id="id_number" placeholder="Enter ID number..." class="borrower-input" required>
                </div>

                <div class="borrower-field">
                    <label class="borrower-label">Full Name</label>
                    <input type="text" name="full_name" id="full_name" required placeholder="Enter name..." class="borrower-input">
                </div>

                <!-- Line 2: Department and Contact Number -->
                <div class="borrower-field">
                    <label class="borrower-label">Department</label>
                    <input type="text" name="department" id="department" placeholder="e.g. BSIT" class="borrower-input">
                </div>

                <div class="borrower-field">
                    <label class="borrower-label">Contact Number</label>
                    <input type="text" name="contact_no" id="contact_no" placeholder="09..." class="borrower-input">
                </div>

                <!-- Line 3: Return Due Date and Search Equipment -->
                <div class="borrower-field">
                    <label class="borrower-label">Return Due Date</label>
                    <input type="datetime-local" name="due_date" required class="borrower-input">
                </div>

                <div class="borrower-field">
                    <label class="borrower-label">Search Equipment</label>
                    <div class="search-wrapper" style="position: relative;">
                        <input type="text" id="equipSearch" placeholder="Type equipment name..." class="borrower-input" autocomplete="off">
                        <div id="equipDropdown" class="borrower-dropdown">
                            <?php foreach($available_items as $item): ?>
                                <div class="dropdown-item" onclick="addBorrowItem('<?php echo $item['equipment_id']; ?>', '<?php echo addslashes($item['name']); ?>')">
                                    <span><?php echo $item['name']; ?></span>
                                    <span style="color: #059669; font-weight: bold;">Stock: <?php echo $item['available_qty']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Chips container (full width below) -->
                <div id="chipContainer" class="chip-area" style="grid-column: span 2;"></div>

                <!-- Hidden field: Processed By (auto-set from session) -->
                <input type="hidden" name="issued_by_staff_id" id="issued_by_staff_id" value="<?php echo $_SESSION['user_id'] ?? 1; ?>">

            </div>

            <div class="borrower-footer">
                <button type="button" class="btn-cancel" onclick="toggleBorrowModal(false)">Cancel</button>
                <button type="submit" class="btn-submit">Confirm Transaction</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ─── Data from PHP ───
    const borrowerEquipList = <?php echo json_encode(array_values($available_items)); ?>;

    // ─── State ───
    let borrowerState = {
        borrowerId: null,
        stagedEquipment: [],
        isNewBorrower: true,
        idExists: false,
        checkingId: false
    };

    // ─── Elements ───
    const bSearchInput = document.getElementById('equipSearch');
    const bDropdown = document.getElementById('equipDropdown');
    const bChipContainer = document.getElementById('chipContainer');
    const borrowForm = document.getElementById('borrowForm');
    const idNumberInput = document.getElementById('id_number');
    const fullNameInput = document.getElementById('full_name');
    const departmentInput = document.getElementById('department');
    const contactInput = document.getElementById('contact_no');
    const issuedByStaffIdInput = document.getElementById('issued_by_staff_id');
    const errorMessage = document.getElementById('errorMessage');

    // ─── Check if Borrower ID Already Exists ───
    async function checkBorrowerIdExists(idNumber) {
        if (!idNumber.trim()) {
            errorMessage.classList.remove('show');
            borrowerState.idExists = false;
            return;
        }

        borrowerState.checkingId = true;

        try {
            const formData = new FormData();
            formData.append('id_number', idNumber);

            const response = await fetch('modules/transactions/borrow.php?action=check_borrower_exists', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success && data.exists) {
                borrowerState.idExists = true;
                errorMessage.textContent = `This person is already borrowing. Search their name in the table if you want to add more items to their transaction.`;
                errorMessage.classList.add('show');
            } else {
                borrowerState.idExists = false;
                errorMessage.classList.remove('show');
            }
        } catch (error) {
            console.error('Error checking ID:', error);
            borrowerState.idExists = false;
            errorMessage.classList.remove('show');
        }

        borrowerState.checkingId = false;
    }

    // ─── Event listener for ID input (check on blur) ───
    if (idNumberInput) {
        idNumberInput.addEventListener('blur', function() {
            checkBorrowerIdExists(this.value);
        });
    }

    // ─── Toggle Modal ───
    function toggleBorrowModal(show) {
        const modal = document.getElementById('borrowModal');
        if (show) {
            modal.classList.remove('hidden');
            resetForm();
        } else {
            modal.classList.add('hidden');
        }
    }

    // ─── Reset Form ───
    function resetForm() {
        const sessionUserId = <?php echo $_SESSION['user_id'] ?? 1; ?>;
        borrowerState = { borrowerId: null, stagedEquipment: [], isNewBorrower: true, idExists: false, checkingId: false };
        borrowForm.reset();
        bChipContainer.innerHTML = '';
        bSearchInput.value = '';
        bDropdown.style.display = 'none';
        errorMessage.classList.remove('show');
        issuedByStaffIdInput.value = sessionUserId;
        idNumberInput.focus();
    }

    // ─── Equipment Search ───
    bSearchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim().toLowerCase();
        
        // If empty, show all items; otherwise filter by query
        const matches = borrowerEquipList.filter(eq => 
            (query === '' || eq.name.toLowerCase().includes(query)) &&
            !borrowerState.stagedEquipment.some(s => s.equipment_id == eq.equipment_id)
        );

        if (matches.length === 0) {
            bDropdown.innerHTML = '<div style="padding: 10px; text-align: center; color: #999; font-size: 12px;">No equipment found</div>';
        } else {
            bDropdown.innerHTML = matches.map(eq => 
                `<div class="dropdown-item" onclick="addBorrowItem(${eq.equipment_id}, '${eq.name.replace(/'/g, "\\'")}', ${eq.available_qty})">
                    <span>${eq.name}</span>
                    <span style="color: #059669; font-weight: bold;">Stock: ${eq.available_qty}</span>
                </div>`
            ).join('');
        }
        bDropdown.style.display = 'block';
    });

    // ─── Handle Equipment Selection ───
    function addBorrowItem(equipId, equipName, availQty) {
        if (borrowerState.stagedEquipment.some(s => s.equipment_id == equipId)) return;
        
        borrowerState.stagedEquipment.push({ 
            equipment_id: equipId, 
            name: equipName,
            available_qty: availQty 
        });
        
        renderChips();
        bSearchInput.value = '';
        bSearchInput.focus();
        
        // Re-show dropdown with updated list
        setTimeout(() => {
            const matches = borrowerEquipList.filter(eq =>
                !borrowerState.stagedEquipment.some(s => s.equipment_id == eq.equipment_id)
            );
            
            if (matches.length === 0) {
                bDropdown.innerHTML = '<div style="padding: 10px; text-align: center; color: #999; font-size: 12px;">All equipment already selected</div>';
            } else {
                bDropdown.innerHTML = matches.map(eq => 
                    `<div class="dropdown-item" onclick="addBorrowItem(${eq.equipment_id}, '${eq.name.replace(/'/g, "\\'")}', ${eq.available_qty}); return false;">
                        <span>${eq.name}</span>
                        <span style="color: #059669; font-weight: bold;">Stock: ${eq.available_qty}</span>
                    </div>`
                ).join('');
            }
            bDropdown.style.display = 'block';
        }, 50);
    }

    // ─── Remove Equipment ───
    function removeBorrowItem(equipId) {
        borrowerState.stagedEquipment = borrowerState.stagedEquipment.filter(s => s.equipment_id != equipId);
        renderChips();
    }

    // ─── Render Equipment Chips ───
    function renderChips() {
        if (borrowerState.stagedEquipment.length === 0) {
            bChipContainer.innerHTML = '';
            return;
        }
        bChipContainer.innerHTML = borrowerState.stagedEquipment.map(item =>
            `<div class="borrower-chip" id="chip-${item.equipment_id}">
                ${item.name}
                <button type="button" onclick="removeBorrowItem(${item.equipment_id})" style="margin-left: 8px; border: none; background: none; color: #ef4444; cursor: pointer; font-size: 16px;">&times;</button>
             </div>`
        ).join('');
    }

    // ─── Form Submission ───
    borrowForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validation
        const idNumber = idNumberInput.value.trim();
        const fullName = fullNameInput.value.trim();
        const dueDate = document.querySelector('input[name="due_date"]').value;
        const issuedByStaffId = issuedByStaffIdInput.value.trim();

        if (!idNumber || !fullName || !dueDate || !issuedByStaffId || borrowerState.stagedEquipment.length === 0) {
            alert('Please fill in all required fields and select at least one equipment.');
            return;
        }

        // Check if ID already exists
        if (borrowerState.idExists) {
            alert('Error: This Borrower ID already exists. Please use a different ID.');
            return;
        }

        // Prepare data
        const formData = new FormData();
        formData.append('id_number', idNumber);
        formData.append('full_name', fullName);
        formData.append('department', departmentInput.value.trim());
        formData.append('contact_no', contactInput.value.trim());
        formData.append('due_date', dueDate);
        formData.append('issued_by_staff_id', issuedByStaffIdInput.value);
        formData.append('is_new_borrower', borrowerState.isNewBorrower ? '1' : '0');
        formData.append('equipment_ids', JSON.stringify(
            borrowerState.stagedEquipment.map(e => e.equipment_id)
        ));

        // Disable button
        const submitBtn = document.querySelector('.btn-submit');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        try {
            const response = await fetch('modules/transactions/borrow.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                alert('Transaction created successfully!');
                toggleBorrowModal(false);
                // Reload the page to show the new transaction
                location.reload();
            } else {
                alert(data.message || 'Error creating transaction. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    // ─── Close dropdowns when clicking outside ───
    document.addEventListener('mousedown', function(e) {
        const equipSearch = document.getElementById('equipSearch');
        const equipDropdown = document.getElementById('equipDropdown');
        
        if (equipSearch && equipDropdown) {
            const searchWrapper = equipSearch.parentElement;
            // Only hide if clicking outside the wrapper
            if (!searchWrapper.contains(e.target)) {
                setTimeout(() => {
                    equipDropdown.style.display = 'none';
                }, 100);
            }
        }
    });

    // ─── Focus on equipment search to show dropdown ───
    if (bSearchInput) {
        bSearchInput.addEventListener('focus', function(e) {
            e.stopPropagation();
            // Show all available items (filtering for unselected only)
            const matches = borrowerEquipList.filter(eq =>
                !borrowerState.stagedEquipment.some(s => s.equipment_id == eq.equipment_id)
            );
            
            if (matches.length === 0) {
                bDropdown.innerHTML = '<div style="padding: 10px; text-align: center; color: #999; font-size: 12px;">All equipment already selected</div>';
            } else {
                bDropdown.innerHTML = matches.map(eq => 
                    `<div class="dropdown-item" onclick="addBorrowItem(${eq.equipment_id}, '${eq.name.replace(/'/g, "\\'")}', ${eq.available_qty}); return false;">
                        <span>${eq.name}</span>
                        <span style="color: #059669; font-weight: bold;">Stock: ${eq.available_qty}</span>
                    </div>`
                ).join('');
            }
            bDropdown.style.display = 'block';
        });

        // Allow clicks on dropdown items without closing
        bDropdown.addEventListener('mousedown', function(e) {
            e.preventDefault();
        });
    }
</script>