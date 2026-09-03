<?php
// admin/categories.php — Production Vendor Category Management Console
session_start();
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../db.php';

$page_title = "Vendor Category Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Categories — Ohati Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        .cat-card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-top: 20px; }
        .cat-card { background: #ffffff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.03); transition: all 0.2s; }
        .cat-card:hover { border-color: #E05A47; box-shadow: 0 4px 12px rgba(224,90,71,0.1); }
        .cat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .cat-icon-box { width: 44px; height: 44px; border-radius: 10px; background: rgba(224,90,71,0.1); color: #E05A47; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .cat-title { font-size: 1.05rem; font-weight: 700; color: #111827; margin: 0; }
        .cat-badge { font-size: 0.7rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; }
        .badge-active { background: #DEF7EC; color: #03543F; }
        .badge-inactive { background: #FDE8E8; color: #9B1C1C; }
        .cat-meta { font-size: 0.8rem; color: #6B7280; margin: 8px 0 14px 0; display: flex; justify-content: space-between; }
        .cat-actions { display: flex; gap: 8px; border-top: 1px solid #F3F4F6; padding-top: 12px; }
        .modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .modal-box { background: #fff; width: 100%; max-width: 500px; border-radius: 16px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; color: #374151; }
        .form-input { width: 100%; padding: 10px 14px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem; }
        .btn-primary { background: #E05A47; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-danger { background: #EF4444; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main" style="padding: 30px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: #111827; margin: 0;">Vendor Category Management</h1>
                <p style="color: #6B7280; font-size: 0.88rem; margin: 4px 0 0 0;">Manage marketplace categories, icons, ordering, and status with full database safety.</p>
            </div>
            <button class="btn-primary" onclick="openCategoryModal()"><i class="fa-solid fa-plus"></i> Add New Category</button>
        </div>

        <div style="display: flex; gap: 12px; margin-bottom: 20px;">
            <input type="text" id="cat-search" class="form-input" placeholder="Search categories..." oninput="filterCategories()" style="max-width: 360px;">
        </div>

        <div class="cat-card-grid" id="category-list-container">
            <!-- Loaded dynamically -->
        </div>
    </main>
</div>

<!-- Add / Edit Category Modal -->
<div class="modal-backdrop" id="cat-modal">
    <div class="modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 id="cat-modal-title" style="margin: 0; font-size: 1.15rem; font-weight: 700;">Add Category</h3>
            <button onclick="closeCategoryModal()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="cat-form" onsubmit="handleSaveCategory(event)">
            <input type="hidden" id="cat-id" value="0">
            <div class="form-group">
                <label class="form-label">Category Name</label>
                <input type="text" id="cat-name" class="form-input" placeholder="e.g. Drone Videography" required>
            </div>
            <div class="form-group">
                <label class="form-label">FontAwesome Icon Name (without fa-)</label>
                <input type="text" id="cat-icon" class="form-input" placeholder="e.g. camera, video, utensils, music" required>
            </div>
            <div class="form-group">
                <label class="form-label">Display Order (Sorting rank)</label>
                <input type="number" id="cat-order" class="form-input" value="1" min="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description (Optional)</label>
                <textarea id="cat-desc" class="form-input" style="min-height: 70px;" placeholder="Brief summary of services in this category..."></textarea>
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="cat-active" checked>
                    <span class="form-label" style="margin: 0;">Active & Available in Mobile/Web App</span>
                </label>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
                <button type="button" class="btn-secondary" onclick="closeCategoryModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="cat-save-btn">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Category Safety Safeguard Modal -->
<div class="modal-backdrop" id="delete-cat-modal">
    <div class="modal-box">
        <h3 style="margin: 0 0 12px 0; color: #EF4444; font-size: 1.15rem;"><i class="fa-solid fa-triangle-exclamation"></i> Safe Category Deletion Safeguard</h3>
        <p id="delete-warning-text" style="font-size: 0.88rem; color: #374151; margin-bottom: 16px;"></p>

        <div id="reassign-container" style="display: none; background: #FFFBEB; border: 1px solid #FCD34D; padding: 14px; border-radius: 10px; margin-bottom: 16px;">
            <label class="form-label" style="color: #92400E;">Select Target Replacement Category for Vendors:</label>
            <select id="reassign-target-select" class="form-input">
                <!-- Loaded dynamically -->
            </select>
        </div>

        <input type="hidden" id="delete-cat-id" value="0">
        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
            <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn-danger" id="confirm-delete-btn" onclick="executeDeleteCategory()">Confirm Delete</button>
        </div>
    </div>
</div>

<script>
let allCategories = [];

function fetchAdminCategories() {
    fetch('../api.php?action=admin_get_categories')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                allCategories = res.categories || [];
                renderCategories(allCategories);
            } else {
                alert('Error loading categories: ' + (res.error || 'Unknown error'));
            }
        })
        .catch(err => alert('Network error loading categories.'));
}

function renderCategories(list) {
    const container = document.getElementById('category-list-container');
    if (!list || list.length === 0) {
        container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #6B7280;">No categories found. Click "Add New Category" to create one.</div>';
        return;
    }

    container.innerHTML = list.map(c => `
        <div class="cat-card">
            <div class="cat-header">
                <div class="cat-icon-box">
                    <i class="fa-solid fa-${c.icon || 'camera'}"></i>
                </div>
                <span class="cat-badge ${parseInt(c.is_active) === 1 ? 'badge-active' : 'badge-inactive'}">
                    ${parseInt(c.is_active) === 1 ? 'Active' : 'Inactive'}
                </span>
            </div>
            <h4 class="cat-title">${c.name}</h4>
            <div class="cat-meta">
                <span><i class="fa-solid fa-briefcase"></i> ${c.vendor_count || 0} Vendors</span>
                <span><i class="fa-solid fa-sort"></i> Order: ${c.display_order || 0}</span>
            </div>
            <div class="cat-actions">
                <button class="btn-secondary" style="flex:1; font-size:0.8rem; padding:6px 10px;" onclick="openCategoryModal(${c.id})"><i class="fa-solid fa-pen"></i> Edit</button>
                <button class="btn-danger" style="font-size:0.8rem; padding:6px 10px;" onclick="promptDeleteCategory(${c.id}, '${c.name.replace(/'/g, "\\'")}', ${c.vendor_count || 0})"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
    `).join('');
}

function filterCategories() {
    const q = document.getElementById('cat-search').value.toLowerCase().trim();
    if (!q) {
        renderCategories(allCategories);
        return;
    }
    const filtered = allCategories.filter(c => c.name.toLowerCase().includes(q) || (c.description && c.description.toLowerCase().includes(q)));
    renderCategories(filtered);
}

function openCategoryModal(id = 0) {
    const title = document.getElementById('cat-modal-title');
    const idEl = document.getElementById('cat-id');
    const nameEl = document.getElementById('cat-name');
    const iconEl = document.getElementById('cat-icon');
    const orderEl = document.getElementById('cat-order');
    const descEl = document.getElementById('cat-desc');
    const activeEl = document.getElementById('cat-active');

    if (id > 0) {
        const cat = allCategories.find(c => parseInt(c.id) === id);
        if (cat) {
            title.textContent = 'Edit Category';
            idEl.value = cat.id;
            nameEl.value = cat.name;
            iconEl.value = cat.icon || 'camera';
            orderEl.value = cat.display_order || 1;
            descEl.value = cat.description || '';
            activeEl.checked = parseInt(cat.is_active) === 1;
        }
    } else {
        title.textContent = 'Add Category';
        idEl.value = 0;
        nameEl.value = '';
        iconEl.value = 'camera';
        orderEl.value = allCategories.length + 1;
        descEl.value = '';
        activeEl.checked = true;
    }
    document.getElementById('cat-modal').style.display = 'flex';
}

function closeCategoryModal() {
    document.getElementById('cat-modal').style.display = 'none';
}

function handleSaveCategory(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('cat-id').value);
    const payload = {
        id: id,
        name: document.getElementById('cat-name').value.trim(),
        icon: document.getElementById('cat-icon').value.trim(),
        display_order: parseInt(document.getElementById('cat-order').value) || 1,
        description: document.getElementById('cat-desc').value.trim(),
        is_active: document.getElementById('cat-active').checked ? 1 : 0
    };

    const action = id > 0 ? 'admin_update_category' : 'admin_create_category';
    const btn = document.getElementById('cat-save-btn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    fetch('../api.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.textContent = 'Save Category';
        if (res.success) {
            closeCategoryModal();
            fetchAdminCategories();
        } else {
            alert('Error: ' + (res.error || 'Could not save category'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Save Category';
        alert('Network error while saving category.');
    });
}

function promptDeleteCategory(id, name, count) {
    document.getElementById('delete-cat-id').value = id;
    const warnText = document.getElementById('delete-warning-text');
    const reassignContainer = document.getElementById('reassign-container');
    const reassignSelect = document.getElementById('reassign-target-select');

    if (count > 0) {
        warnText.innerHTML = `Category <strong>${name}</strong> currently has <strong>${count} vendor(s)</strong> assigned to it. Deleting this category requires reassigning these vendors so their public profiles do not break.`;
        reassignContainer.style.display = 'block';

        const otherCats = allCategories.filter(c => parseInt(c.id) !== id && parseInt(c.is_active) === 1);
        reassignSelect.innerHTML = otherCats.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
    } else {
        warnText.innerHTML = `Are you sure you want to delete category <strong>${name}</strong>? This action cannot be undone.`;
        reassignContainer.style.display = 'none';
    }

    document.getElementById('delete-cat-modal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('delete-cat-modal').style.display = 'none';
}

function executeDeleteCategory() {
    const id = parseInt(document.getElementById('delete-cat-id').value);
    const reassignSelect = document.getElementById('reassign-target-select');
    const reassignTarget = reassignSelect && reassignSelect.style.display !== 'none' ? reassignSelect.value : '';

    const btn = document.getElementById('confirm-delete-btn');
    btn.disabled = true;
    btn.textContent = 'Deleting...';

    fetch('../api.php?action=admin_delete_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, reassign_to: reassignTarget })
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.textContent = 'Confirm Delete';
        if (res.success) {
            closeDeleteModal();
            fetchAdminCategories();
        } else {
            alert('Error: ' + (res.error || 'Could not delete category'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Confirm Delete';
        alert('Network error while deleting category.');
    });
}

document.addEventListener('DOMContentLoaded', fetchAdminCategories);
</script>
</body>
</html>
