<?php
$pageTitle = 'Staff Inventory Management';
require_once __DIR__ . '/../includes/header.php';
require_role(['admin', 'staff']);

$pdo = Database::getConnection();
$msg = '';
$error = '';

// Handle Permanent Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plant'])) {
    $delete_id = (int) $_POST['id'];

    try {
        // 1. Fetch the image path so we can delete the physical file too!
        $stmt = $pdo->prepare("SELECT image_url FROM plants WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        $plantToDelete = $stmt->fetch();

        // 2. Delete the record from the database
        $deleteStmt = $pdo->prepare("DELETE FROM plants WHERE id = :id");
        $deleteStmt->execute([':id' => $delete_id]);

        // 3. Delete the physical image file (if it's not the default image)
        if ($plantToDelete && !empty($plantToDelete['image_url']) && $plantToDelete['image_url'] !== 'assets/img/default_plant.png') {
            $filePath = __DIR__ . '/../' . $plantToDelete['image_url'];
            if (file_exists($filePath)) {
                unlink($filePath); // This is the PHP command to delete a file!
            }
        }
        $msg = "Plant and its image were permanently deleted.";
    } catch (PDOException $e) {
        // If it fails (e.g., a customer already ordered this plant), catch the error!
        $error = "Cannot delete this plant because it is linked to an existing order. Deactivate it instead.";
    }
}

// Handle Add New Plant Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_plant'])) {
    $name = sanitize($_POST['name'] ?? '');
    $botanical = sanitize($_POST['botanical_name'] ?? '');
    $catId = (int) ($_POST['category_id'] ?? 1);
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock_quantity'] ?? 0);
    $sunlight = $_POST['care_sunlight'] ?? 'Partial Shade';
    $water = $_POST['care_water'] ?? '2-3 Times a Week';
    $desc = sanitize($_POST['description'] ?? '');

    // --- IMAGE UPLOAD LOGIC ---
    $imageUrl = 'assets/img/default_plant.png'; // Default if no image uploaded

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/img/plants/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileExt, $allowedExts)) {
            $fileName = uniqid('plant_') . '.' . $fileExt;
            $targetFile = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $imageUrl = 'assets/img/plants/' . $fileName;
            }
        } else {
            $error = 'Invalid image format. Only JPG, PNG, and WEBP are allowed.';
        }
    }

    if (empty($name) || $price <= 0) {
        $error = $error ?: 'Plant name and valid price are required.';
    } else if (!$error) { 
        try {
            $stmt = $pdo->prepare("INSERT INTO plants (category_id, name, botanical_name, description, care_sunlight, care_water, price, stock_quantity, image_url) 
                                   VALUES (:cat, :name, :bot, :desc, :sun, :wat, :price, :stock, :img)");
            $stmt->execute([
                ':cat' => $catId,
                ':name' => $name,
                ':bot' => $botanical,
                ':desc' => $desc,
                ':sun' => $sunlight,
                ':wat' => $water,
                ':price' => $price,
                ':stock' => $stock,
                ':img' => $imageUrl
            ]);
            $msg = 'New botanical variety added with image!';
        } catch (PDOException $e) {
            $error = 'Failed to add plant: ' . $e->getMessage();
        }
    }
}

// Handle Deactivate/Activate Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $plant_id = (int) $_POST['plant_id'];
    $new_status = (int) $_POST['new_status'];
    try {
        $stmt = $pdo->prepare("UPDATE plants SET is_active = :status WHERE id = :id");
        $stmt->execute([':status' => $new_status, ':id' => $plant_id]);
        $msg = $new_status === 1 ? 'Plant reactivated successfully.' : 'Plant deactivated and hidden from catalog.';
    } catch (PDOException $e) {
        $error = 'Failed to update status: ' . $e->getMessage();
    }
}

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$plants = $pdo->query("SELECT p.*, c.name AS category_name FROM plants p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
?>

<div class="container" style="padding-top: 2rem;">
    
    <!-- Dashboard Header with Add Button -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div>
            <h1 class="section-title" style="margin-bottom: 0.5rem;">Nursery Staff Inventory Panel</h1>
            <p class="section-subtitle" style="margin: 0;">Manage plant stock levels, update botanical descriptions, and add new varieties.</p>
        </div>
        
        <button onclick="document.getElementById('registerFormContainer').style.display = 'block';" 
                style="display: inline-flex; align-items: center; gap: 8px; background: #059669; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);"
                onmouseover="this.style.background='#047857'" 
                onmouseout="this.style.background='#059669'">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
            Add New Plant
        </button>
    </div>

    <!-- System Messages -->
    <?php if ($msg): ?>
        <div style="background: var(--primary-subtle); color: var(--primary-dark); padding: 12px; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fee2e2; color: var(--danger); padding: 12px; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Hidden Registration Form Container -->
    <div id="registerFormContainer" style="display: none; background: var(--surface-white); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 2.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; color: var(--primary-dark);">+ Register New Plant Variety</h3>
            <button type="button" onclick="document.getElementById('registerFormContainer').style.display = 'none';" 
                    style="background: #f3f4f6; border: none; border-radius: 50%; width: 32px; height: 32px; font-size: 1.2rem; cursor: pointer; color: #4b5563; display: flex; align-items: center; justify-content: center;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                &times;
            </button>
        </div>

        <!-- IMPORTANT: enctype="multipart/form-data" is required for image uploads! -->
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="add_plant" value="1">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label>Plant Common Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Ceylon Cinnamon" required>
                </div>
                <div class="form-group">
                    <label>Botanical / Scientific Name</label>
                    <input type="text" name="botanical_name" class="form-control" placeholder="e.g. Cinnamomum verum">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-control">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Plant Image</label>
                    <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/webp">
                </div>
                <div class="form-group">
                    <label>Initial Stock</label>
                    <input type="number" name="stock_quantity" class="form-control" value="25" required>
                </div>
                <div class="form-group">
                    <label>Sunlight Requirement</label>
                    <select name="care_sunlight" class="form-control">
                        <option value="Full Sun">Full Sun</option>
                        <option value="Partial Shade">Partial Shade</option>
                        <option value="Indirect Light">Indirect Light</option>
                        <option value="Low Light">Low Light</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description & Care Instructions</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Soil composition, watering tips, growth habits..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save to Live Inventory</button>
        </form>
    </div>

    <!-- Data Table Section -->
    <div class="table-responsive">
        
        <!-- Table Header & Search Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; margin-top: 1rem;">
            <h2 style="margin: 0; color: var(--text-dark);">Current Stock Levels</h2>

            <div style="position: relative; width: 350px;">
                <svg style="position: absolute; left: 12px; top: 10px; color: #9ca3af;" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" id="inventorySearch" placeholder="Search plants, categories, or botanical names..."
                    style="width: 100%; padding: 10px 15px 10px 38px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.95rem; outline: none; transition: 0.2s;"
                    onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'"
                    onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
            </div>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Botanical</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plants as $p): ?>
                    <tr>
                        <td>#<?= $p['id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td><em><?= htmlspecialchars($p['botanical_name']) ?></em></td>
                        <td><?= htmlspecialchars($p['category_name']) ?></td>
                        <td><?= APP_CURRENCY ?> <?= number_format($p['price'], 2) ?></td>
                        <td><strong><?= $p['stock_quantity'] ?> units</strong></td>
                        <td>
                            <?php if (isset($p['is_active']) && $p['is_active'] == 0): ?>
                                <span class="role-pill" style="background: #374151; color: white;">Deactivated</span>
                            <?php elseif ($p['stock_quantity'] <= 5): ?>
                                <span class="role-pill" style="background: #fee2e2; color: var(--danger);">Low Stock</span>
                            <?php else: ?>
                                <span class="role-pill" style="background: var(--primary-subtle); color: var(--primary-dark);">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <div style="display: flex; gap: 8px; align-items: center;">

                                <!-- 1. Modern Edit Button -->
                                <a href="edit_plant.php?id=<?= $p['id'] ?>"
                                    style="display: inline-flex; align-items: center; gap: 5px; background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: 0.2s;"
                                    onmouseover="this.style.background='#e0f2fe'"
                                    onmouseout="this.style.background='#f0f9ff'">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Edit
                                </a>

                                <!-- 2. Modern Deactivate Button -->
                                <form method="POST" action="" style="margin: 0;" onsubmit="confirmAction(event, this, 'deactivate')">
                                    <input type="hidden" name="deactivate_plant" value="1">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit"
                                        style="display: inline-flex; align-items: center; gap: 5px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: 0.2s;"
                                        onmouseover="this.style.background='#fee2e2'"
                                        onmouseout="this.style.background='#fef2f2'">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                        </svg>
                                        Deactivate
                                    </button>
                                </form>

                                <!-- 3. Permanent Delete Button -->
                                <form method="POST" action="" style="margin: 0;" onsubmit="confirmAction(event, this, 'delete')">
                                    <input type="hidden" name="delete_plant" value="1">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit"
                                        style="display: inline-flex; align-items: center; gap: 5px; background: #fff1f2; color: #be123c; border: 1px solid #fda4af; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: 0.2s;"
                                        onmouseover="this.style.background='#ffe4e6'"
                                        onmouseout="this.style.background='#fff1f2'">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SweetAlert2 Library for Beautiful Popups -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmAction(e, form, actionType) {
        e.preventDefault(); // Stop the form from submitting immediately

        let titleText = '';
        let textBody = '';
        let confirmColor = '';

        if (actionType === 'delete') {
            titleText = "Permanently delete this plant?";
            textBody = "This will erase the database record and the image file. You cannot undo this!";
            confirmColor = "#be123c"; // Dark Red
        } else {
            titleText = "Deactivate this plant?";
            textBody = "This will hide the plant from the catalog, but keep it in the database.";
            confirmColor = "#dc2626"; // Standard Red
        }

        Swal.fire({
            title: titleText,
            text: textBody,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: "#6b7280",
            confirmButtonText: "Yes, do it!"
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit(); // If they click yes, actually submit the form!
            }
        });
    }
</script>

<!-- Real-Time Table Filter Script (Foolproof Version) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('inventorySearch');
    const tableRows = document.querySelectorAll('table tr'); 

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            
            tableRows.forEach(row => {
                if (row.querySelector('td')) {
                    const rowText = row.textContent.toLowerCase(); 
                    if (rowText.includes(searchTerm)) {
                        row.style.display = ''; // Show row
                    } else {
                        row.style.display = 'none'; // Hide row
                    }
                }
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>