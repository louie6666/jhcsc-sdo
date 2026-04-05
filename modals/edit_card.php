<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// PHP logic for edit_card.php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_equipment_submit'])) {
    $id = (int)$_POST['equipment_id'];
    $name = mysqli_real_escape_string($conn, $_POST['equipment_name']);
    $location = mysqli_real_escape_string($conn, $_POST['storage_location']);

    $image_update_query = "";

    if (isset($_FILES['equipment_image']) && $_FILES['equipment_image']['error'] == 0) {
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/uploads/';
        $file_extension = pathinfo($_FILES["equipment_image"]["name"], PATHINFO_EXTENSION);
        $new_filename = "eq_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        $check = getimagesize($_FILES["equipment_image"]["tmp_name"]);
        if($check !== false) {
            if (move_uploaded_file($_FILES["equipment_image"]["tmp_name"], $target_file)) {
                $image_update_query = ", image_url = '$new_filename'";
            }
        }
    }

    $update_sql = "UPDATE equipment 
                   SET name = '$name', 
                       storage_location = '$location'
                       $image_update_query
                   WHERE equipment_id = $id";

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

    .edit-modal-overlay {
        position: fixed; inset: 0;
        background: rgba(15, 23, 42, 0.4); 
        backdrop-filter: blur(1px);
        display: none; justify-content: center; align-items: center;
        z-index: 10000; padding: 20px;
    }

    .edit-modal-container {
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        background: #ffffff; width: 100%; max-width: 500px;
        border-radius: var(--as-radius);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        overflow: hidden; 
        animation: as-fade-up 0.2s ease-out;
    }

    @keyframes as-fade-up {
        from { transform: translateY(10px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .edit-header {
        padding: 20px 40px; /* Adjusted to 40px to match body */
        display: flex; justify-content: space-between; align-items: center;
        border-bottom: 1px solid var(--as-border);
    }

    .edit-header h3 { margin: 0; font-size: var(--as-fs-title); color: var(--as-primary); font-weight: 700; }

    .as-close-x {
        background: none; border: none; color: var(--as-primary); 
        font-size: 20px; font-weight: 700; cursor: pointer;
        padding: 4px; line-height: 1;
    }

    /* Tightened Body Padding to 40px */
    .edit-body { padding: 40px; }

    /* Reduced margin-bottom to 12px to remove excessive white space between fields */
    .edit-field-group { margin-bottom: 12px; }

    .edit-field-label {
        display: block; font-size: var(--as-fs-label); font-weight: 700; 
        color: var(--as-text-muted); text-transform: uppercase; 
        margin-bottom: 6px; letter-spacing: 0.025em;
    }

    .image-edit-container {
        display: flex; align-items: center; gap: 16px;
        background: var(--as-bg-light); padding: 12px;
        border: 1px solid var(--as-border); border-radius: var(--as-radius);
    }

    .edit-preview-img {
        width: 60px; height: 60px; object-fit: cover;
        border-radius: 4px; border: 1px solid var(--as-border);
        background: #fff;
    }

    .file-input-styled {
        font-size: 13px; color: var(--as-text-muted);
        width: 100%;
    }

    .edit-input {
        width: 100%; padding: 10px 14px; /* Slightly tighter internal input padding */
        border: 1px solid var(--as-border); border-radius: var(--as-radius);
        font-size: var(--as-fs-info); color: var(--as-primary);
        box-sizing: border-box; background: #fff;
        transition: border-color 0.2s;
    }

    .edit-input:focus { border-color: var(--as-primary); outline: none; }

    .edit-footer {
        padding: 20px 40px; /* Adjusted to 40px to match body */
        display: flex; 
        justify-content: flex-end; gap: 12px;
        border-top: 1px solid var(--as-border);
    }

    .as-btn {
        padding: 8px 16px; border-radius: 6px;
        font-weight: 400; font-size: 12px; 
        cursor: pointer; border: none; transition: all 0.2s;
    }

    .as-btn-cancel { background: #f1f5f9; color: #475569; }
    .as-btn-save { background: var(--as-primary); color: white; }
</style>

<div id="edit_equipment_modal" class="edit-modal-overlay">
    <div class="edit-modal-container">
        <div class="edit-header">
            <h3>Edit Details</h3>
            <button type="button" class="as-close-x" onclick="closeEditModal()">✕</button>
        </div>

        <form id="edit-form" enctype="multipart/form-data">
            <div class="edit-body">
                <input type="hidden" name="equipment_id" id="edit-id">
                <input type="hidden" name="edit_equipment_submit" value="1">

                <div class="edit-field-group">
                    <label class="edit-field-label">Update Equipment Photo</label>
                    <div class="image-edit-container">
                        <img id="edit-img-preview" src="" class="edit-preview-img" alt="Preview">
                        <input type="file" name="equipment_image" id="edit-image-input" class="file-input-styled" accept="image/*">
                    </div>
                </div>

                <div class="edit-field-group">
                    <label class="edit-field-label">Equipment Name</label>
                    <input type="text" name="equipment_name" id="edit-name" class="edit-input" required>
                </div>

                <div class="edit-field-group">
                    <label class="edit-field-label">Storage Location</label>
                    <input type="text" name="storage_location" id="edit-location" class="edit-input">
                </div>
            </div>

            <div class="edit-footer">
                <button type="button" class="as-btn as-btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="as-btn as-btn-save" id="edit-save-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, location, currentImg) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-location').value = location;
    
    const preview = document.getElementById('edit-img-preview');
    preview.src = currentImg ? 'uploads/' + currentImg : 'path/to/placeholder.png';
    
    document.getElementById('edit-image-input').value = "";
    document.getElementById('edit_equipment_modal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('edit_equipment_modal').style.display = 'none';
}

document.getElementById('edit-image-input').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('edit-img-preview').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});

document.getElementById('edit-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('edit-save-btn');
    btn.innerText = 'Updating...';
    btn.disabled = true;

    const formData = new FormData(this);

    fetch('modals/edit_card.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === 'success') {
            closeEditModal();
            if (typeof loadModule === 'function') loadModule('modules/equipments/equipment.php?page=<?php echo isset($page) ? $page : 1; ?>');
        } else {
            alert(data);
        }
    })
    .catch(error => console.error('Error:', error))
    .finally(() => {
        btn.innerText = 'Save Changes';
        btn.disabled = false;
    });
});

window.onclick = function(event) {
    const aeModal = document.getElementById('add_equipment');
    const asModal = document.getElementById('add_stock_modal');
    const edModal = document.getElementById('edit_equipment_modal');

    if (event.target == aeModal && typeof closeEquipmentModal === 'function') closeEquipmentModal();
    if (event.target == asModal && typeof closeStockModal === 'function') closeStockModal();
    if (event.target == edModal && typeof closeEditModal === 'function') closeEditModal();
}
</script>