<?php
$pageTitle = 'Edit Plant Inventory';
require_once __DIR__ . '/../includes/header.php';
require_role(['admin', 'staff']);

$pdo = Database::getConnection();
$msg = '';
$error = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plant'])) {
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock_quantity'];
    $desc = sanitize($_POST['description']);

    if ($price <= 0 || $stock < 0) {
        $error = 'Price must be greater than 0 and stock cannot be negative.';
    } else {
        try {
            $updateImageSql = "";
            $imageUrl = "";
            
            // NEW: Strict Error Checking for Uploads
            if (isset($_FILES['image']) && $_FILES['image']['name'] !== '') {
                if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    // This will tell us exactly why PHP blocked it!
                    $error = 'Upload Failed! PHP Error Code: ' . $_FILES['image']['error'] . ' (Code 1 or 2 means the image is too large. Must be under 2MB).';
                } else {
                    $uploadDir = __DIR__ . '/../assets/img/plants/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $fileExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $fileName = uniqid('plant_') . '.' . $fileExt;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                            $imageUrl = 'assets/img/plants/' . $fileName;
                            $updateImageSql = ", image_url = :img";
                        } else {
                            $error = 'Failed to move the image to the assets folder. Check folder permissions.';
                        }
                    } else {
                        $error = 'Invalid image format. Only JPG, PNG, and WEBP allowed. (Do not use .avif or .jfif)';
                    }
                }
            }

            if (!$error) {
                $sql = "UPDATE plants SET price = :price, stock_quantity = :stock, description = :desc" . $updateImageSql . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                
                $params = [
                    ':price' => $price,
                    ':stock' => $stock,
                    ':desc'  => $desc,
                    ':id'    => $id
                ];
                if ($imageUrl !== "") $params[':img'] = $imageUrl;
                
                $stmt->execute($params);
                $msg = 'Plant inventory updated successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch current plant data
$stmt = $pdo->prepare("SELECT * FROM plants WHERE id = :id");
$stmt->execute([':id' => $id]);
$plant = $stmt->fetch();

if (!$plant) {
    echo "<div class='container' style='padding-top: 2rem;'><h2>Plant not found.</h2></div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Edit Plant: <?= htmlspecialchars($plant['name']) ?></h1>
    <a href="staff_inventory.php" class="btn btn-outline" style="margin-bottom: 1.5rem; display: inline-block;">&larr; Back to Inventory</a>

    <?php if ($msg): ?>
        <div style="background: var(--primary-subtle); color: var(--primary-dark); padding: 12px; border-radius: 4px; margin-bottom: 1.5rem;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background: #fee2e2; color: var(--danger); padding: 12px; border-radius: 4px; margin-bottom: 1.5rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: 8px; padding: 2rem; max-width: 600px;">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="update_plant" value="1">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Update Price (LKR)</label>
                <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($plant['price']) ?>" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Update Stock Quantity</label>
                <input type="number" name="stock_quantity" class="form-control" value="<?= htmlspecialchars($plant['stock_quantity']) ?>" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Update Description</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($plant['description']) ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem; background: var(--background-light); padding: 1rem; border-radius: 8px;">
                <label>Current Image</label><br>
                <img src="<?= APP_URL ?>/<?= htmlspecialchars($plant['image_url'] ?? 'assets/img/default_plant.png') ?>" alt="Plant Image" style="max-width: 120px; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #ddd;"><br>
                
                <label>Upload New Image (Must be under 2MB)</label>
                <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/webp">
            </div>
            
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>