<?php
// admin_catalog.php
include 'includes/db_connect.php';

// Handle Form Submissions
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Delete Product
    if (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        try {
            $conn->prepare("DELETE FROM product_test WHERE product_id = ?")->execute([$product_id]);
            $conn->prepare("DELETE FROM product_discipline WHERE product_id = ?")->execute([$product_id]);
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            if ($stmt->execute([$product_id])) {
                $message = "Product deleted successfully!";
            }
        } catch (PDOException $e) {
            $message = "Error deleting product: " . $e->getMessage();
            $messageType = 'error';
        }
    }
    // 2. Delete Category / Discipline
    elseif (isset($_POST['delete_discipline'])) {
        $discipline_id = (int)$_POST['discipline_id'];
        try {
            $conn->prepare("DELETE FROM product_test WHERE test_id IN (SELECT id FROM tests WHERE discipline_id = ?)")->execute([$discipline_id]);
            $conn->prepare("DELETE FROM tests WHERE discipline_id = ?")->execute([$discipline_id]);
            $conn->prepare("DELETE FROM product_discipline WHERE discipline_id = ?")->execute([$discipline_id]);
            $stmt = $conn->prepare("DELETE FROM disciplines WHERE id = ?");
            if ($stmt->execute([$discipline_id])) {
                $message = "Category / Discipline deleted successfully!";
            }
        } catch (PDOException $e) {
            $message = "Error deleting category: " . $e->getMessage();
            $messageType = 'error';
        }
    }
    // 3. Add Test
    elseif (isset($_POST['add_test'])) {
        $discipline_id = $_POST['discipline_id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $standards = $_POST['standards'];
        
        $stmt = $conn->prepare("INSERT INTO tests (discipline_id, name, description, standards) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$discipline_id, $name, $description, $standards])) {
            $message = "Test added successfully!";
        }
    } 
    // 4. Map Test
    elseif (isset($_POST['map_test'])) {
        $product_id = $_POST['product_id'];
        $test_id = $_POST['test_id'];
        
        $check = $conn->prepare("SELECT * FROM product_test WHERE product_id = ? AND test_id = ?");
        $check->execute([$product_id, $test_id]);
        if ($check->rowCount() > 0) {
            $message = "Mapping already exists!";
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO product_test (product_id, test_id) VALUES (?, ?)");
            if ($stmt->execute([$product_id, $test_id])) {
                $message = "Test mapped to product successfully!";
            }
        }
    } 
    // 5. Unmap Test
    elseif (isset($_POST['unmap_test'])) {
        $product_id = $_POST['product_id'];
        $test_id = $_POST['test_id'];
        $stmt = $conn->prepare("DELETE FROM product_test WHERE product_id = ? AND test_id = ?");
        if ($stmt->execute([$product_id, $test_id])) {
            $message = "Test unmapped successfully!";
        }
    } 
    // 6. Add Category / Discipline
    elseif (isset($_POST['add_discipline'])) {
        $name = trim($_POST['discipline_name']);
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        try {
            $stmt = $conn->prepare("INSERT INTO disciplines (name, slug) VALUES (?, ?)");
            if ($stmt->execute([$name, $slug])) {
                $message = "Category added successfully!";
            }
        } catch(PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    } 
    // 7. Add Product
    elseif (isset($_POST['add_product'])) {
        $name = trim($_POST['product_name']);
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        try {
            $stmt = $conn->prepare("INSERT INTO products (name, slug) VALUES (?, ?)");
            if ($stmt->execute([$name, $slug])) {
                $message = "Product added successfully!";
            }
        } catch(PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch data
$disciplines = $conn->query("
    SELECT d.*, (SELECT COUNT(*) FROM tests WHERE discipline_id = d.id) as test_count 
    FROM disciplines d 
    ORDER BY d.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$products = $conn->query("
    SELECT p.*, (SELECT COUNT(*) FROM product_test WHERE product_id = p.id) as test_count 
    FROM products p 
    ORDER BY p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$allTests = $conn->query("
    SELECT t.*, d.name as discipline_name 
    FROM tests t 
    LEFT JOIN disciplines d ON t.discipline_id = d.id 
    ORDER BY d.name, t.name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch mapping grid
$mappingsStmt = $conn->query("
    SELECT p.id as product_id, p.name as product_name, t.id as test_id, t.name as test_name, d.name as disc_name
    FROM product_test pt
    JOIN products p ON pt.product_id = p.id
    JOIN tests t ON pt.test_id = t.id
    LEFT JOIN disciplines d ON t.discipline_id = d.id
    ORDER BY p.name, d.name, t.name
");
$mappings = $mappingsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Catalog Management - AKC Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f8fafc; padding: 30px; color: #1e293b; }
        .container { max-width: 1250px; margin: 0 auto; }
        h1, h2, h3 { color: #0f172a; margin-top: 0; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #475569; font-size: 13px; }
        input[type="text"], textarea, select { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; box-sizing: border-box; }
        input[type="text"]:focus, select:focus { outline: none; border-color: #2563eb; }
        .btn { background: #2563eb; color: white; border: none; padding: 11px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .btn-danger { background: #ef4444; color: white; padding: 6px 12px; border-radius: 6px; font-size: 12px; border: none; cursor: pointer; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-danger:hover { background: #dc2626; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        th { background: #f8fafc; color: #475569; font-weight: 600; }
        .message-success { background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #bbf7d0; font-weight: 500; }
        .message-error { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #fecaca; font-weight: 500; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-count { background: #eff6ff; color: #1d4ed8; }
        .highlight-item { background: #fffbeb !important; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1><i class="fa-solid fa-layer-group" style="color: #2563eb;"></i> Manage Products, Categories &amp; Tests</h1>
            <p style="margin: 0; color: #64748b; font-size: 14px;">Add, review, or delete product sectors and testing categories.</p>
        </div>
        <a href="admin_dashboard.php" class="btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'message-error' : 'message-success'; ?>">
            <i class="fa-solid <?php echo $messageType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check'; ?>"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- 1. Add Sections -->
    <div class="grid-2">
        <!-- Add New Product Form -->
        <div class="card">
            <h2><i class="fa-solid fa-cube" style="color: #0284c7;"></i> Add New Product Sector</h2>
            <form method="POST">
                <input type="hidden" name="add_product" value="1">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" required placeholder="e.g. Biodegradable Plastics">
                </div>
                <button type="submit" class="btn">Create Product</button>
            </form>
        </div>

        <!-- Add New Category Form -->
        <div class="card">
            <h2><i class="fa-solid fa-microscope" style="color: #7c3aed;"></i> Add New Testing Category</h2>
            <form method="POST">
                <input type="hidden" name="add_discipline" value="1">
                <div class="form-group">
                    <label>Category / Discipline Name</label>
                    <input type="text" name="discipline_name" required placeholder="e.g. Environmental Testing">
                </div>
                <button type="submit" class="btn">Create Category</button>
            </form>
        </div>
    </div>

    <!-- 2. Existing Products List with DELETE Buttons -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2><i class="fa-solid fa-boxes-stacked" style="color: #0284c7;"></i> Existing Products (Sectors)</h2>
            <span class="badge badge-count"><?php echo count($products); ?> Total Products</span>
        </div>
        <p style="color: #64748b; font-size: 13px; margin-top: 0;">Click <strong>Delete</strong> next to any product you want to remove (such as "new producr").</p>
        
        <div style="max-height: 400px; overflow-y: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Product Name</th>
                        <th>URL Slug</th>
                        <th>Mapped Tests</th>
                        <th style="width: 120px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): 
                        $isHighlight = (stripos($p['name'], 'new produc') !== false);
                    ?>
                    <tr class="<?php echo $isHighlight ? 'highlight-item' : ''; ?>">
                        <td>#<?php echo $p['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                            <?php if ($isHighlight): ?>
                                <span style="background: #fef08a; color: #854d0e; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 6px;">Target for removal</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($p['slug']); ?></code></td>
                        <td><span class="badge badge-count"><?php echo $p['test_count']; ?> tests</span></td>
                        <td style="text-align: center;">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently DELETE product: <?php echo addslashes(htmlspecialchars($p['name'])); ?>?');">
                                <input type="hidden" name="delete_product" value="1">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn-danger"><i class="fa-solid fa-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Existing Testing Categories with DELETE Buttons -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2><i class="fa-solid fa-tags" style="color: #7c3aed;"></i> Existing Testing Categories</h2>
            <span class="badge badge-count"><?php echo count($disciplines); ?> Categories</span>
        </div>
        <p style="color: #64748b; font-size: 13px; margin-top: 0;">Remove any testing category / discipline. (Deleting a category also removes its tests).</p>
        
        <div style="max-height: 350px; overflow-y: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Category Name</th>
                        <th>Slug</th>
                        <th>Total Tests</th>
                        <th style="width: 120px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($disciplines as $d): ?>
                    <tr>
                        <td>#<?php echo $d['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($d['slug']); ?></code></td>
                        <td><span class="badge badge-count"><?php echo $d['test_count']; ?> tests</span></td>
                        <td style="text-align: center;">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to DELETE category: <?php echo addslashes(htmlspecialchars($d['name'])); ?>? All tests inside it will be deleted.');">
                                <input type="hidden" name="delete_discipline" value="1">
                                <input type="hidden" name="discipline_id" value="<?php echo $d['id']; ?>">
                                <button type="submit" class="btn-danger"><i class="fa-solid fa-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. Add Test & Map Test to Product -->
    <div class="grid-2">
        <div class="card">
            <h2><i class="fa-solid fa-flask" style="color: #10b981;"></i> Add New Test</h2>
            <form method="POST">
                <input type="hidden" name="add_test" value="1">
                <div class="form-group">
                    <label>Category</label>
                    <select name="discipline_id" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach($disciplines as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Test Name</label>
                    <input type="text" name="name" required placeholder="e.g. Tensile Strength">
                </div>
                <div class="form-group">
                    <label>Standards</label>
                    <input type="text" name="standards" placeholder="e.g. ISO 527 / ASTM D638">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" required placeholder="Test description..."></textarea>
                </div>
                <button type="submit" class="btn">Create Test</button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-link" style="color: #f59e0b;"></i> Map Test to Product</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Link an existing test to a product page so it displays on the frontend.</p>
            <form method="POST">
                <input type="hidden" name="map_test" value="1">
                <div class="form-group">
                    <label>Select Product</label>
                    <select name="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Test</label>
                    <select name="test_id" required>
                        <option value="">-- Select Test --</option>
                        <?php foreach($allTests as $t): ?>
                            <option value="<?php echo $t['id']; ?>">[<?php echo htmlspecialchars($t['discipline_name'] ?? 'General'); ?>] <?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">Map Test</button>
            </form>
        </div>
    </div>

    <!-- 5. Active Mappings Table -->
    <div class="card">
        <h2><i class="fa-solid fa-list-check" style="color: #6366f1;"></i> Active Product-Test Mappings</h2>
        <div style="max-height: 400px; overflow-y: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Discipline</th>
                        <th>Test Name</th>
                        <th style="width: 120px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($mappings as $m): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($m['product_name']); ?></strong></td>
                        <td><span style="background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($m['disc_name'] ?? 'General'); ?></span></td>
                        <td><?php echo htmlspecialchars($m['test_name']); ?></td>
                        <td style="text-align: center;">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to unmap this test?');">
                                <input type="hidden" name="unmap_test" value="1">
                                <input type="hidden" name="product_id" value="<?php echo $m['product_id']; ?>">
                                <input type="hidden" name="test_id" value="<?php echo $m['test_id']; ?>">
                                <button type="submit" class="btn-danger"><i class="fa-solid fa-unlink"></i> Unmap</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($mappings)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #64748b;">No mappings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
