<?php
require_once '../config.php';

// Check if vendor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendor') {
    header('Location: ../login.php');
    exit();
}

$vendorId = $_SESSION['user_id'];

// Fetch vendor details
$stmt = $pdo->prepare("
    SELECT u.*, vp.company_name, vp.rating
    FROM users u
    LEFT JOIN vendor_profiles vp ON u.id = vp.user_id
    WHERE u.id = ? AND u.role = 'vendor'
");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();

if (!$vendor) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

$vendorBrand = $vendor['company_name'] ?? $vendor['first_name'] . ' ' . $vendor['last_name'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // Get subcategories for dynamic category loading
    if ($_POST['action'] === 'get_subcategories') {
        try {
            $parentId = $_POST['parent_id'] ?? null;
            
            $stmt = $pdo->prepare("
                SELECT id, name, level 
                FROM categories 
                WHERE parent_id " . ($parentId ? "= ?" : "IS NULL") . " 
                AND is_active = 1 
                ORDER BY sort_order, name
            ");
            
            if ($parentId) {
                $stmt->execute([$parentId]);
            } else {
                $stmt->execute();
            }
            
            $categories = $stmt->fetchAll();
            echo json_encode(['success' => true, 'categories' => $categories]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // Get all colors
    if ($_POST['action'] === 'get_colors') {
        try {
            $stmt = $pdo->query("SELECT id, name, hex_code FROM colors WHERE is_active = 1 ORDER BY name");
            $colors = $stmt->fetchAll();
            echo json_encode(['success' => true, 'colors' => $colors]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // Get all sizes
    if ($_POST['action'] === 'get_sizes') {
        try {
            $stmt = $pdo->query("SELECT id, name FROM sizes WHERE is_active = 1 ORDER BY sort_order");
            $sizes = $stmt->fetchAll();
            echo json_encode(['success' => true, 'sizes' => $sizes]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // Add Product
    if ($_POST['action'] === 'add_product') {
        try {
            $pdo->beginTransaction();

            // Validate required fields
            if (empty($_POST['name']) || empty($_POST['price']) || empty($_POST['stock_quantity']) || empty($_POST['category_id'])) {
                throw new Exception("Missing required fields.");
            }

            // Handle primary image
            $imagePath = null;
            if (!empty($_FILES['image']['name'])) {
                $targetDir = __DIR__ . '/../uploads/products/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $targetFile = $targetDir . $fileName;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imagePath = 'uploads/products/' . $fileName;
                }
            }

            // Insert product
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, description, price, sku, image, category_id, brand, 
                    stock_quantity, min_stock_level, weight, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_POST['name'],
                $_POST['description'] ?? null,
                $_POST['price'],
                $_POST['sku'] ?: 'SKU-' . time(),
                $imagePath,
                $_POST['category_id'],
                $vendorBrand,
                $_POST['stock_quantity'],
                $_POST['min_stock_level'] ?? 5,
                $_POST['weight'] ?? null,
                $_POST['is_active'] ?? 1
            ]);

            $productId = $pdo->lastInsertId();

            // Handle colors
            if (!empty($_POST['colors'])) {
                $colorStmt = $pdo->prepare("INSERT INTO product_colors (product_id, color_id) VALUES (?, ?)");
                foreach ($_POST['colors'] as $colorId) {
                    $colorStmt->execute([$productId, $colorId]);
                }
            }

            // Handle sizes
            if (!empty($_POST['sizes'])) {
                $sizeStmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size_id) VALUES (?, ?)");
                foreach ($_POST['sizes'] as $sizeId) {
                    $sizeStmt->execute([$productId, $sizeId]);
                }
            }

            // Handle multiple images
            if (!empty($_FILES['additional_images']['name'][0])) {
                $imageStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                $sortOrder = 0;
                
                foreach ($_FILES['additional_images']['name'] as $key => $imageName) {
                    if ($_FILES['additional_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $targetDir = __DIR__ . '/../uploads/products/';
                        $fileName = time() . '_' . $key . '_' . basename($imageName);
                        $targetFile = $targetDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$key], $targetFile)) {
                            $imgPath = 'uploads/products/' . $fileName;
                            $isPrimary = ($sortOrder === 0 && empty($imagePath)) ? 1 : 0;
                            $imageStmt->execute([$productId, $imgPath, $isPrimary, $sortOrder]);
                            $sortOrder++;
                        }
                    }
                }
            }

            $pdo->commit();
            logUserActivity($vendorId, 'product_create', 'Created product: ' . $_POST['name']);

            echo json_encode(['success' => true, 'message' => 'Product added successfully!', 'product_id' => $productId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error adding product: ' . $e->getMessage()]);
        }
        exit();
    }

    // Update Product
    if ($_POST['action'] === 'update_product') {
        try {
            $pdo->beginTransaction();

            if (empty($_POST['product_id']) || empty($_POST['name']) || empty($_POST['price']) || empty($_POST['stock_quantity']) || empty($_POST['category_id'])) {
                throw new Exception("Missing required fields.");
            }

            $imagePath = $_POST['existing_image'] ?? null;

            if (!empty($_FILES['image']['name'])) {
                $targetDir = __DIR__ . "/../uploads/products/";
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $targetFile = $targetDir . $fileName;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imagePath = 'uploads/products/' . $fileName;
                }
            }

            $stmt = $pdo->prepare("
                UPDATE products SET
                    name = ?, description = ?, price = ?, sku = ?, image = ?,
                    category_id = ?, stock_quantity = ?, min_stock_level = ?,
                    weight = ?, is_active = ?, updated_at = NOW()
                WHERE id = ? AND brand = ?
            ");

            $stmt->execute([
                $_POST['name'],
                $_POST['description'] ?? null,
                $_POST['price'],
                $_POST['sku'],
                $imagePath,
                $_POST['category_id'],
                $_POST['stock_quantity'],
                $_POST['min_stock_level'] ?? 5,
                $_POST['weight'] ?? null,
                $_POST['is_active'] ?? 1,
                $_POST['product_id'],
                $vendorBrand
            ]);

            // Update colors
            $pdo->prepare("DELETE FROM product_colors WHERE product_id = ?")->execute([$_POST['product_id']]);
            if (!empty($_POST['colors'])) {
                $colorStmt = $pdo->prepare("INSERT INTO product_colors (product_id, color_id) VALUES (?, ?)");
                foreach ($_POST['colors'] as $colorId) {
                    $colorStmt->execute([$_POST['product_id'], $colorId]);
                }
            }

            // Update sizes
            $pdo->prepare("DELETE FROM product_sizes WHERE product_id = ?")->execute([$_POST['product_id']]);
            if (!empty($_POST['sizes'])) {
                $sizeStmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size_id) VALUES (?, ?)");
                foreach ($_POST['sizes'] as $sizeId) {
                    $sizeStmt->execute([$_POST['product_id'], $sizeId]);
                }
            }

            // Handle new additional images
            if (!empty($_FILES['additional_images']['name'][0])) {
                $maxSortOrder = $pdo->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = {$_POST['product_id']}")->fetchColumn();
                $imageStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, 0, ?)");
                
                foreach ($_FILES['additional_images']['name'] as $key => $imageName) {
                    if ($_FILES['additional_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $targetDir = __DIR__ . '/../uploads/products/';
                        $fileName = time() . '_' . $key . '_' . basename($imageName);
                        $targetFile = $targetDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$key], $targetFile)) {
                            $imgPath = 'uploads/products/' . $fileName;
                            $imageStmt->execute([$_POST['product_id'], $imgPath, $maxSortOrder++]);
                        }
                    }
                }
            }

            $pdo->commit();
            logUserActivity($vendorId, 'product_update', 'Updated product ID: ' . $_POST['product_id']);

            echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $e->getMessage()]);
        }
        exit();
    }

    // Delete product image
    if ($_POST['action'] === 'delete_product_image') {
        try {
            $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ?");
            $stmt->execute([$_POST['image_id']]);
            $image = $stmt->fetch();
            
            if ($image) {
                $filePath = __DIR__ . '/../' . $image['image_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$_POST['image_id']]);
                echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Image not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // Delete Product
    if ($_POST['action'] === 'delete_product') {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND brand = ?");
            $stmt->execute([$_POST['product_id'], $vendorBrand]);

            logUserActivity($vendorId, 'product_delete', 'Deleted product ID: ' . $_POST['product_id']);

            echo json_encode(['success' => true, 'message' => 'Product deleted successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error deleting product: ' . $e->getMessage()]);
        }
        exit();
    }

    // Get Product Details
    if ($_POST['action'] === 'get_product') {
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name, c.parent_id
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.brand = ?
            ");
            $stmt->execute([$_POST['product_id'], $vendorBrand]);
            $product = $stmt->fetch();

            if ($product) {
                // Get colors
                $colorStmt = $pdo->prepare("
                    SELECT c.id, c.name, c.hex_code 
                    FROM product_colors pc 
                    JOIN colors c ON pc.color_id = c.id 
                    WHERE pc.product_id = ?
                ");
                $colorStmt->execute([$_POST['product_id']]);
                $product['colors'] = $colorStmt->fetchAll();

                // Get sizes
                $sizeStmt = $pdo->prepare("
                    SELECT s.id, s.name 
                    FROM product_sizes ps 
                    JOIN sizes s ON ps.size_id = s.id 
                    WHERE ps.product_id = ?
                ");
                $sizeStmt->execute([$_POST['product_id']]);
                $product['sizes'] = $sizeStmt->fetchAll();

                // Get additional images
                $imageStmt = $pdo->prepare("
                    SELECT id, image_path, is_primary, sort_order 
                    FROM product_images 
                    WHERE product_id = ? 
                    ORDER BY sort_order
                ");
                $imageStmt->execute([$_POST['product_id']]);
                $product['images'] = $imageStmt->fetchAll();

                // Get category hierarchy
                $categoryPath = [];
                $currentCat = $product['category_id'];
                while ($currentCat) {
                    $catStmt = $pdo->prepare("SELECT id, name, parent_id FROM categories WHERE id = ?");
                    $catStmt->execute([$currentCat]);
                    $cat = $catStmt->fetch();
                    if ($cat) {
                        array_unshift($categoryPath, $cat);
                        $currentCat = $cat['parent_id'];
                    } else {
                        break;
                    }
                }
                $product['category_path'] = $categoryPath;

                echo json_encode(['success' => true, 'product' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Filters
$searchTerm = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'ASC';

// Build query
$whereClause = "WHERE p.brand = ?";
$params = [$vendorBrand];

if (!empty($searchTerm)) {
    $whereClause .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($categoryFilter)) {
    $whereClause .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($statusFilter)) {
    if ($statusFilter === 'low_stock') {
        $whereClause .= " AND p.stock_quantity <= p.min_stock_level";
    } else {
        $whereClause .= " AND p.is_active = ?";
        $params[] = ($statusFilter === 'active') ? 1 : 0;
    }
}

// Valid sort columns
$validSortColumns = ['name' => 'p.name', 'price' => 'p.price', 'stock' => 'p.stock_quantity', 'date' => 'p.created_at'];
$sortColumn = $validSortColumns[$sortBy] ?? 'p.name';
$sortOrder = ($sortOrder === 'DESC') ? 'DESC' : 'ASC';

// Count total products
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereClause");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $itemsPerPage);

// Fetch products
$params[] = $itemsPerPage;
$params[] = $offset;

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereClause
    ORDER BY $sortColumn $sortOrder
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch all main categories (parent_id IS NULL)
$categoriesStmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order, name");
$categories = $categoriesStmt->fetchAll();
$categoryOptions = '';
foreach ($categories as $cat) {
    $categoryOptions .= '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
}

// Unread notifications
$stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM admin_messages WHERE recipient_id = ? AND recipient_type = 'vendor' AND is_read = 0");
$stmt->execute([$vendorId]);
$unreadNotifications = $stmt->fetch()['unread'];

// Pending orders count
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE p.brand = ? AND o.status IN ('pending', 'processing')
");
$stmt->execute([$vendorBrand]);
$pendingOrders = $stmt->fetch()['total'];

// Low stock count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE brand = ? AND stock_quantity <= min_stock_level");
$stmt->execute([$vendorBrand]);
$lowStockItems = $stmt->fetch()['total'];

function getStockBadge($stock, $minStock) {
    if ($stock == 0) {
        return '<span class="badge danger">Out of Stock</span>';
    } elseif ($stock <= $minStock) {
        return '<span class="badge warning">Low Stock (' . $stock . ')</span>';
    } else {
        return '<span class="badge success">' . $stock . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *, *::before, *::after {
          box-sizing: border-box;
          padding: 0;
          margin: 0;
        }

        nav {
          user-select: none;
          -webkit-user-select: none;
          -moz-user-select: none;
          -ms-user-select: none;
          -o-user-select: none;
        }

        nav ul, nav ul li {
          outline: 0;
        }

        nav ul li a {
          text-decoration: none;
        }

        body {
          font-family: "Nunito", sans-serif;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          background-image: url(https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/5baf8325-ed69-40b0-b9d2-d8c5d2bde3b0);
          background-repeat: no-repeat;
          background-size: cover;
        }

        main {
          display: grid;
          grid-template-columns: 13% 87%;
          width: 100%;
          margin: 40px;
          background: rgb(254, 254, 254);
          box-shadow: 0 0.5px 0 1px rgba(255, 255, 255, 0.23) inset,
            0 1px 0 0 rgba(255, 255, 255, 0.66) inset, 0 4px 16px rgba(0, 0, 0, 0.12);
          border-radius: 15px;
          z-index: 10;
        }

        .main-menu {
            overflow: hidden;
            background: rgb(73, 57, 113);
            padding-top: 10px;
            border-radius: 15px 0 0 15px;
            font-family: "Roboto", sans-serif;
            padding-top: 20px;
            padding-bottom: 20px;
        }

        .main-menu h1 {
          display: block;
          font-size: 1.5rem;
          font-weight: 500;
          text-align: center;
          margin:  0;
          color: #fff;
          font-family: "Nunito", sans-serif;
        }

        .main-menu small {
          display: block;
          font-size: 1rem;
          font-weight: 300;
          text-align: center;
          margin: 10px 0 ;
          color: #fff;
          font-family: "Nunito", sans-serif;
          padding-bottom: 10px;
        }

        .logo {
          display: none;
          width: 30px;
          margin: 20px auto;
        }

        .nav-item {
          position: relative;
          display: block;
        }

        .nav-item a {
          position: relative;
          display: flex;
          flex-direction: row;
          align-items: center;
          justify-content: center;
          color: #fff;
          font-size: 1rem;
          padding: 15px 0;
          margin-left: 10px;
          border-top-left-radius: 20px;
          border-bottom-left-radius: 20px;
        }

        .nav-item b:nth-child(1) {
          position: absolute;
          top: -15px;
          height: 15px;
          width: 100%;
          background: #fff;
          display: none;
        }

        .nav-item b:nth-child(1)::before {
          content: "";
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          border-bottom-right-radius: 20px;
          background: rgb(73, 57, 113);
        }

        .nav-item b:nth-child(2) {
          position: absolute;
          bottom: -15px;
          height: 15px;
          width: 100%;
          background: #fff;
          display: none;
        }

        .nav-item b:nth-child(2)::before {
          content: "";
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          border-top-right-radius: 20px;
          background: rgb(73, 57, 113);
        }

        .nav-item.active b:nth-child(1),
        .nav-item.active b:nth-child(2) {
          display: block;
        }

        .nav-item.active a {
          text-decoration: none;
          color: #000;
          background: rgb(254, 254, 254);
        }

        .nav-icon {
          width: 60px;
          height: 20px;
          font-size: 20px;
          text-align: center;
        }

        .nav-text {
          display: block;
          width: 120px;
          height: 20px;
        }

        .notification-badge {
          position: absolute;
          top: 10px;
          right: 20px;
          background: #ef4444;
          color: white;
          border-radius: 50%;
          padding: 2px 6px;
          font-size: 11px;
          font-weight: 700;
        }

        .content-wrapper {
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - 80px);
        }

        .page-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
        }

        .page-header h1 {
          font-size: 1.4rem;
          font-weight: 700;
          color: #484d53;
        }

        .btn {
          display: inline-block;
          padding: 10px 20px;
          font-size: 0.9rem;
          font-weight: 600;
          background: rgb(73, 57, 113);
          color: white;
          border: none;
          border-radius: 12px;
          cursor: pointer;
          text-decoration: none;
          transition: all 0.3s ease;
          font-family: inherit;
        }

        .btn:hover {
          background: rgb(93, 77, 133);
          transform: translateY(-2px);
        }

        .btn-sm {
          padding: 6px 12px;
          font-size: 0.85rem;
        }

        .btn-outline {
          background: white;
          color: rgb(73, 57, 113);
          border: 2px solid rgb(73, 57, 113);
        }

        .btn-outline:hover {
          background: rgb(73, 57, 113);
          color: white;
        }

        .search-filter-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          margin-bottom: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .filter-row {
          display: grid;
          grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
          gap: 15px;
          align-items: end;
        }

        .form-group {
          margin-bottom: 0;
        }

        .form-group label {
          display: block;
          margin-bottom: 5px;
          font-weight: 600;
          color: #484d53;
          font-size: 0.9rem;
        }

        .form-control, .form-select {
          width: 100%;
          padding: 10px 12px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          font-family: inherit;
          font-size: 0.9rem;
          transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
          outline: none;
          border-color: rgb(73, 57, 113);
        }

        .products-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .products-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
          padding-bottom: 15px;
          border-bottom: 2px solid #f6f7fb;
        }

        .products-header h3 {
          font-size: 1.1rem;
          font-weight: 700;
          color: #484d53;
        }

        .view-toggle {
          display: flex;
          gap: 5px;
        }

        .view-toggle-btn {
          padding: 8px 12px;
          background: white;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          cursor: pointer;
          transition: all 0.3s ease;
          color: #484d53;
        }

        .view-toggle-btn.active,
        .view-toggle-btn:hover {
          background: rgb(73, 57, 113);
          color: white;
          border-color: rgb(73, 57, 113);
        }

        table {
          width: 100%;
          border-collapse: collapse;
        }

        table th {
          text-align: left;
          padding: 12px;
          font-weight: 600;
          font-size: 0.9rem;
          color: #484d53;
          border-bottom: 2px solid #f6f7fb;
        }

        table td {
          padding: 12px;
          font-size: 0.9rem;
          color: #484d53;
          border-bottom: 1px solid #f6f7fb;
        }

        table tr:hover {
          background: #f6f7fb;
        }

        .product-img-thumb {
          width: 60px;
          height: 60px;
          border-radius: 10px;
          object-fit: cover;
        }

        .badge {
          display: inline-block;
          padding: 4px 12px;
          border-radius: 12px;
          font-size: 0.8rem;
          font-weight: 600;
        }

        .badge.success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge.warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge.danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        .product-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
          gap: 20px;
        }

        .product-grid-item {
          background: white;
          border-radius: 15px;
          overflow: hidden;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .product-grid-item:hover {
          transform: translateY(-5px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .product-grid-img {
          width: 100%;
          height: 200px;
          object-fit: cover;
        }

        .product-grid-body {
          padding: 15px;
        }

        .product-grid-body h4 {
          font-size: 1rem;
          font-weight: 700;
          margin-bottom: 5px;
          color: #484d53;
        }

        .product-grid-body p {
          font-size: 0.85rem;
          color: #94a3b8;
          margin-bottom: 10px;
        }

        .product-grid-footer {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-top: 10px;
        }

        .product-price {
          font-size: 1.2rem;
          font-weight: 700;
          color: rgb(73, 57, 113);
        }

        .product-actions {
          display: flex;
          gap: 5px;
        }

        /* Color checkbox styles */
        .color-checkbox-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
          gap: 10px;
          max-height: 200px;
          overflow-y: auto;
          padding: 5px;
        }

        .color-checkbox-item {
          display: flex;
          align-items: center;
          gap: 8px;
          padding: 8px;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .color-checkbox-item:hover {
          border-color: rgb(73, 57, 113);
          background: #f6f7fb;
        }

        .color-checkbox-item input[type="checkbox"] {
          width: 18px;
          height: 18px;
          cursor: pointer;
        }

        .color-swatch {
          width: 24px;
          height: 24px;
          border-radius: 50%;
          border: 2px solid #ddd;
        }

        .color-checkbox-item label {
          cursor: pointer;
          font-size: 0.85rem;
          margin: 0;
        }

        /* Size checkbox styles */
        .size-checkbox-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
          gap: 10px;
        }

        .size-checkbox-item {
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 10px;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          cursor: pointer;
          transition: all 0.3s ease;
          text-align: center;
        }

        .size-checkbox-item:hover {
          border-color: rgb(73, 57, 113);
          background: #f6f7fb;
        }

        .size-checkbox-item input[type="checkbox"] {
          margin-right: 5px;
        }

        .size-checkbox-item label {
          cursor: pointer;
          font-weight: 600;
          margin: 0;
        }

        /* Image preview styles */
        .image-preview-container {
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
          margin-top: 10px;
        }

        .image-preview-item {
          position: relative;
          width: 100px;
          height: 100px;
          border-radius: 8px;
          overflow: hidden;
          border: 2px solid #e2e8f0;
        }

        .image-preview-item img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .image-preview-item .delete-image-btn {
          position: absolute;
          top: 5px;
          right: 5px;
          background: #ef4444;
          color: white;
          border: none;
          border-radius: 50%;
          width: 24px;
          height: 24px;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 12px;
        }

        .image-preview-item .delete-image-btn:hover {
          background: #dc2626;
        }

        /* Category cascade styles */
        .category-cascade {
          display: flex;
          flex-direction: column;
          gap: 10px;
        }

        .category-level {
          display: none;
        }

        .category-level.active {
          display: block;
        }

        /* SWEETALERT2 STYLES */
        .swal2-popup {
          border-radius: 15px !important;
          box-shadow: 0 8px 25px rgba(73, 57, 113, 0.3);
          font-family: "Nunito", sans-serif;
          max-width: 900px !important;
          width: 90% !important;
        }

        .swal2-title {
            color: #484d53 !important;
            font-weight: 700 !important;
        }
        
        .swal2-html-container {
            text-align: left;
            padding: 0 20px 10px 20px !important;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        #swal-form-content .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        #swal-form-content .form-group {
            margin-bottom: 15px;
        }

        #swal-form-content .form-group.full-width {
            grid-column: 1 / -1;
        }

        .swal2-confirm.swal-confirm {
          background: linear-gradient(135deg, rgb(73, 57, 113), #a38cd9) !important;
          border: none !important;
          font-weight: 600;
          border-radius: 10px;
        }

        .swal2-cancel.swal-cancel {
          background: #f6f7fb !important;
          color: #484d53 !important;
          border: 1px solid #ddd !important;
          font-weight: 600;
          border-radius: 10px;
        }

        .swal2-confirm:hover, .swal2-cancel:hover {
          transform: translateY(-1px);
        }
        
        .swal2-file {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .swal2-file:focus {
             outline: none;
             border-color: rgb(73, 57, 113);
        }

        .pagination {
          display: flex;
          justify-content: center;
          gap: 5px;
          margin-top: 20px;
        }

        .pagination a, .pagination span {
          padding: 8px 12px;
          background: white;
          color: #484d53;
          border-radius: 8px;
          text-decoration: none;
          font-weight: 600;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .pagination a:hover,
        .pagination a.active {
          background: rgb(73, 57, 113);
          color: white;
          transform: translateY(-2px);
        }

        .pagination span {
          opacity: 0.5;
        }

        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1 { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .filter-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .product-grid { grid-template-columns: 1fr; }
          #swal-form-content .form-row {
              grid-template-columns: 1fr;
          }
        }
    </style>
</head>
<body>
    <main>
        <nav class="main-menu">
            <h1><?php echo APP_NAME; ?></h1>
            <small>Vendor Panel</small>
            <div class="logo">
                <i class="fa fa-rocket" style="font-size: 24px; color: white;"></i>
            </div>
            <ul>
                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="dashboard.php">
                        <i class="fa fa-home nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item active">
                    <b></b>
                    <b></b>
                    <a href="products.php">
                        <i class="fa fa-box nav-icon"></i>
                        <span class="nav-text">Products</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="orders.php">
                        <i class="fa fa-shopping-cart nav-icon"></i>
                        <span class="nav-text">Orders</span>
                        <?php if ($pendingOrders > 0): ?>
                        <span class="notification-badge"><?php echo $pendingOrders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="inventory.php">
                        <i class="fa fa-boxes nav-icon"></i>
                        <span class="nav-text">Inventory</span>
                        <?php if ($lowStockItems > 0): ?>
                        <span class="notification-badge"><?php echo $lowStockItems; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="delivery.php">
                        <i class="fa fa-truck nav-icon"></i>
                        <span class="nav-text">Delivery</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="analytics.php">
                        <i class="fa fa-chart-bar nav-icon"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="customers.php">
                        <i class="fa fa-users nav-icon"></i>
                        <span class="nav-text">Customers</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="notifications.php">
                        <i class="fa fa-bell nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                        <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="contact.php">
                        <i class="fa fa-envelope nav-icon"></i>
                        <span class="nav-text">Contact</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="settings.php">
                        <i class="fa fa-cog nav-icon"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </li>

               <li class="nav-item">
                  <b></b>
                  <b></b>
                  <a href="#" onclick="confirmLogout(event)">
                    <i class="fa fa-sign-out-alt nav-icon"></i>
                    <span class="nav-text">Logout</span>
                  </a>
                </li>
            </ul>
        </nav>

        <div class="content-wrapper">
            <div class="page-header">
                <h1>Products Management</h1>
                <button class="btn" onclick="showAddProductModal()">
                    <i class="fas fa-plus-circle"></i> Add Product
                </button>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter-card">
                <form method="GET" action="products.php">
                    <div class="filter-row">
                        <div class="form-group">
                            <label>Search Products</label>
                            <input type="text" class="form-control" name="search" placeholder="Search by name, SKU..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select class="form-select" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($categoryFilter == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($statusFilter === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="low_stock" <?php echo ($statusFilter === 'low_stock') ? 'selected' : ''; ?>>Low Stock</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sort By</label>
                            <select class="form-select" name="sort">
                                <option value="name" <?php echo ($sortBy === 'name') ? 'selected' : ''; ?>>Name</option>
                                <option value="price" <?php echo ($sortBy === 'price') ? 'selected' : ''; ?>>Price</option>
                                <option value="stock" <?php echo ($sortBy === 'stock') ? 'selected' : ''; ?>>Stock</option>
                                <option value="date" <?php echo ($sortBy === 'date') ? 'selected' : ''; ?>>Date</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn" style="width: 100%; margin-top: 27px;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Products Table/Grid -->
            <div class="products-card">
                <div class="products-header">
                    <h3>Product List (<?php echo $totalProducts; ?> items)</h3>
                    <div class="view-toggle">
                        <button class="view-toggle-btn active" id="tableViewBtn" onclick="switchView('table')">
                            <i class="fas fa-table"></i>
                        </button>
                        <button class="view-toggle-btn" id="gridViewBtn" onclick="switchView('grid')">
                            <i class="fas fa-th"></i>
                        </button>
                    </div>
                </div>

                <!-- Table View -->
                <div id="tableView">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
                                        <span style="opacity: 0.6;">No products found</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <?php if ($product['image']): ?>
                                                <img src="../<?php echo htmlspecialchars($product['image']); ?>" class="product-img-thumb" alt="Product">
                                            <?php else: ?>
                                                <div style="width: 60px; height: 60px; background: #e2e8f0; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-image" style="color: #94a3b8;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                            <small style="color: #94a3b8;">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td><strong>$<?php echo number_format($product['price'], 2); ?></strong></td>
                                        <td><?php echo getStockBadge($product['stock_quantity'], $product['min_stock_level']); ?></td>
                                        <td>
                                            <?php if ($product['is_active']): ?>
                                                <span class="badge success">Active</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(148, 163, 184, 0.2); color: #64748b;">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline" onclick="viewProduct(<?php echo $product['id']; ?>)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline" onclick="showEditProductModal(<?php echo $product['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm" style="background: #ef4444;" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Grid View -->
                <div id="gridView" style="display: none;">
                    <?php if (empty($products)): ?>
                        <div style="text-align: center; padding: 60px 20px;">
                            <i class="fas fa-inbox" style="font-size: 64px; opacity: 0.3; display: block; margin-bottom: 20px;"></i>
                            <span style="opacity: 0.6;">No products found</span>
                        </div>
                    <?php else: ?>
                        <div class="product-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product-grid-item">
                                    <?php if ($product['image']): ?>
                                        <img src="../<?php echo htmlspecialchars($product['image']); ?>" class="product-grid-img" alt="Product">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 200px; background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="font-size: 3rem; color: #94a3b8;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-grid-body">
                                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                        <p><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                                        <div class="product-grid-footer">
                                            <div>
                                                <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                                                <?php echo getStockBadge($product['stock_quantity'], $product['min_stock_level']); ?>
                                            </div>
                                            <div class="product-actions">
                                                <button class="btn btn-sm btn-outline" onclick="viewProduct(<?php echo $product['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline" onclick="showEditProductModal(<?php echo $product['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm" style="background: #ef4444;" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo $categoryFilter; ?>&status=<?php echo $statusFilter; ?>&sort=<?php echo $sortBy; ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo $categoryFilter; ?>&status=<?php echo $statusFilter; ?>&sort=<?php echo $sortBy; ?>" 
                                   class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo $categoryFilter; ?>&status=<?php echo $statusFilter; ?>&sort=<?php echo $sortBy; ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="js/enhanced-products.js"></script>
</body>
</html>