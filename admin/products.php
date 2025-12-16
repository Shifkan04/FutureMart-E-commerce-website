<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

checkSessionTimeout();

$adminId = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Get subcategories for dynamic loading
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

            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, description, price, original_price, sku, image, category_id, brand, 
                    stock_quantity, min_stock_level, weight, is_active, is_featured
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_POST['name'],
                $_POST['description'] ?? null,
                $_POST['price'],
                $_POST['original_price'] ?? $_POST['price'],
                $_POST['sku'] ?: 'SKU-' . time(),
                $imagePath,
                $_POST['category_id'],
                $_POST['brand'] ?? null,
                $_POST['stock_quantity'],
                $_POST['min_stock_level'] ?? 5,
                $_POST['weight'] ?? null,
                $_POST['is_active'] ?? 1,
                0
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
            logUserActivity($adminId, 'product_create', 'Created product: ' . $_POST['name']);

            echo json_encode(['success' => true, 'message' => 'Product added successfully!', 'product_id' => $productId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Update Product
    if ($_POST['action'] === 'update_product') {
        try {
            $pdo->beginTransaction();

            if (empty($_POST['product_id']) || empty($_POST['name']) || empty($_POST['price']) || empty($_POST['stock_quantity'])) {
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
                    name = ?, description = ?, price = ?, original_price = ?, sku = ?, image = ?,
                    category_id = ?, brand = ?, stock_quantity = ?, min_stock_level = ?,
                    weight = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $_POST['name'],
                $_POST['description'] ?? null,
                $_POST['price'],
                $_POST['original_price'] ?? $_POST['price'],
                $_POST['sku'],
                $imagePath,
                $_POST['category_id'],
                $_POST['brand'] ?? null,
                $_POST['stock_quantity'],
                $_POST['min_stock_level'] ?? 5,
                $_POST['weight'] ?? null,
                $_POST['is_active'] ?? 1,
                $_POST['product_id']
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
            logUserActivity($adminId, 'product_update', 'Updated product ID: ' . $_POST['product_id']);

            echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
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
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$_POST['product_id']]);
            
            logUserActivity($adminId, 'product_delete', 'Deleted product ID: ' . $_POST['product_id']);
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
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
                WHERE p.id = ?
            ");
            $stmt->execute([$_POST['product_id']]);
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
    
    // Bulk Actions
    if ($_POST['action'] === 'bulk_action') {
        try {
            $productIds = json_decode($_POST['product_ids']);
            $bulkAction = $_POST['bulk_action_type'];
            
            if (empty($productIds)) {
                echo json_encode(['success' => false, 'message' => 'No products selected']);
                exit();
            }
            
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
            
            if ($bulkAction === 'activate') {
                $stmt = $pdo->prepare("UPDATE products SET is_active = 1 WHERE id IN ($placeholders)");
                $stmt->execute($productIds);
                $message = 'Products activated successfully!';
            } elseif ($bulkAction === 'deactivate') {
                $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id IN ($placeholders)");
                $stmt->execute($productIds);
                $message = 'Products deactivated successfully!';
            } elseif ($bulkAction === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
                $stmt->execute($productIds);
                $message = 'Products deleted successfully!';
            }
            
            logUserActivity($adminId, 'product_bulk_action', "Bulk $bulkAction on " . count($productIds) . " products");
            echo json_encode(['success' => true, 'message' => $message]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Get filter parameters
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Build query
$whereConditions = [];
$params = [];

if (!empty($categoryFilter)) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($statusFilter)) {
    if ($statusFilter === 'active') {
        $whereConditions[] = "p.is_active = 1 AND p.stock_quantity > 0";
    } elseif ($statusFilter === 'inactive') {
        $whereConditions[] = "p.is_active = 0";
    } elseif ($statusFilter === 'out-of-stock') {
        $whereConditions[] = "p.stock_quantity = 0";
    } elseif ($statusFilter === 'low_stock') {
        $whereConditions[] = "p.stock_quantity <= p.min_stock_level AND p.stock_quantity > 0";
    }
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total products count
$countQuery = "SELECT COUNT(*) as total FROM products p $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalProducts = $stmt->fetch()['total'];
$totalPages = ceil($totalProducts / $itemsPerPage);

// Get products
$query = "
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereClause
    ORDER BY p.created_at DESC
    LIMIT $itemsPerPage OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get all main categories (level 0)
$categoriesStmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order, name");
$categories = $categoriesStmt->fetchAll();

// Get admin details
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// Get notification counts
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
$pendingOrders = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'vendor' AND status = 'inactive'");
$pendingVendors = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level AND is_active = 1");
$lowStockProducts = $stmt->fetch()['count'];

// Helper functions
function getStockBadge($stock, $minStock) {
    if ($stock == 0) return '<span class="badge badge-danger">Out of Stock</span>';
    if ($stock <= $minStock) return '<span class="badge badge-warning">' . $stock . '</span>';
    return '<span class="badge badge-success">' . $stock . '</span>';
}

function getStatusBadge($isActive, $stock) {
    if ($stock == 0) return '<span class="badge badge-danger">Out of Stock</span>';
    if (!$isActive) return '<span class="badge badge-secondary">Inactive</span>';
    if ($stock <= 10) return '<span class="badge badge-warning">Low Stock</span>';
    return '<span class="badge badge-success">Active</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *,*::before,*::after {
          box-sizing: border-box;
          padding: 0;
          margin: 0;
        }

        nav ul,nav ul li {
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
          padding-bottom: 20px;
        }

         .main-menu h1 {
          display: block;
          font-size: 1.5rem;
          font-weight: 500;
          text-align: center;
          margin: 0;
          color: #fff;
          font-family: "Nunito", sans-serif;
          padding-top: 20px;
        }

        .main-menu small {
          display: block;
          font-size: 1rem;
          font-weight: 300;
          text-align: center;
          margin: 10px 0;
          color: #fff;
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

        .content {
          background: #f6f7fb;
          padding: 20px;
          border-radius: 0 15px 15px 0;
          overflow-y: auto;
          max-height: calc(100vh - 80px);
        }

        .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
        }

        .header h1 {
          font-size: 1.8rem;
          font-weight: 700;
          color: #484d53;
        }

        .user-section {
          display: flex;
          align-items: center;
          gap: 15px;
        }

        .user-avatar {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: 700;
          color: white;
          font-size: 16px;
        }

        .btn {
          padding: 10px 20px;
          border: none;
          border-radius: 12px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 0.9rem;
        }

        .btn-primary {
          background: rgb(73, 57, 113);
          color: white;
        }

        .btn-primary:hover {
          background: rgb(93, 77, 133);
          transform: translateY(-2px);
        }

        .btn-success {
          background: linear-gradient(240deg, #97e7d1 0%, #ecfcc3 100%);
          color: #484d53;
        }

        .btn-danger {
          background: linear-gradient(240deg, #fc8ebe 0%, #fce5c3 100%);
          color: #484d53;
        }

        .btn-sm {
          padding: 6px 12px;
          font-size: 0.85rem;
        }

        .filter-section {
          background: white;
          padding: 20px;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          margin-bottom: 20px;
        }

        .filter-section .form-row {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 15px;
        }

        .form-group {
          display: flex;
          flex-direction: column;
        }

        .form-group label {
          font-weight: 600;
          color: #484d53;
          margin-bottom: 5px;
          font-size: 0.9rem;
        }

        .form-control {
          padding: 10px;
          border: 1px solid #e0e0e0;
          border-radius: 8px;
          font-size: 0.9rem;
          transition: all 0.3s ease;
        }

        .form-control:focus {
          outline: none;
          border-color: rgb(124, 136, 224);
        }

        .products-section {
          background: white;
          padding: 20px;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .products-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
        }

        .products-header h2 {
          font-size: 1.3rem;
          font-weight: 700;
          color: #484d53;
        }

        .action-buttons {
          display: flex;
          gap: 10px;
        }

        .products-table {
          width: 100%;
          border-collapse: collapse;
          overflow-x: auto;
        }

        .products-table th {
          text-align: left;
          padding: 12px;
          font-weight: 600;
          font-size: 0.9rem;
          color: #484d53;
          border-bottom: 2px solid #f6f7fb;
          background: #f6f7fb;
        }

        .products-table td {
          padding: 12px;
          font-size: 0.9rem;
          color: #484d53;
          border-bottom: 1px solid #f6f7fb;
          vertical-align: middle;
        }

        .products-table tr:hover {
          background: #f6f7fb;
        }

        .product-image {
          width: 60px;
          height: 60px;
          object-fit: cover;
          border-radius: 8px;
          background: #f3f4f6;
        }

        .badge {
          display: inline-block;
          padding: 4px 12px;
          border-radius: 12px;
          font-size: 0.75rem;
          font-weight: 600;
        }

        .badge-success {
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .badge-warning {
          background: rgba(245, 158, 11, 0.2);
          color: #f59e0b;
        }

        .badge-danger {
          background: rgba(239, 68, 68, 0.2);
          color: #ef4444;
        }

        .badge-secondary {
          background: rgba(107, 114, 128, 0.2);
          color: #6b7280;
        }

        .pagination {
          display: flex;
          justify-content: center;
          gap: 10px;
          margin-top: 20px;
        }

        .pagination button {
          padding: 8px 15px;
          border: none;
          background: #f6f7fb;
          color: #484d53;
          border-radius: 8px;
          cursor: pointer;
          font-weight: 600;
        }

        .pagination button.active {
          background: rgb(124, 136, 224);
          color: white;
        }

        .pagination button:hover:not(.active) {
          background: #e5e7eb;
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

        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1 { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .products-table { font-size: 0.8rem; }
          .header { flex-direction: column; align-items: flex-start; gap: 15px; }
          #swal-form-content .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main>
        <nav class="main-menu">
            <h1><i class="fas fa-rocket" style="margin-right: 8px;"></i><?php echo APP_NAME; ?></h1>
             <small>Admin Panel</small>
            <div class="logo">
                <i class="fa fa-rocket" style="font-size: 24px; color: white;"></i>
            </div>
            <ul>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="dashboard.php">
                        <i class="fa fa-home nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <b></b><b></b>
                    <a href="products.php">
                        <i class="fa fa-box nav-icon"></i>
                        <span class="nav-text">Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="orders.php">
                        <i class="fa fa-shopping-cart nav-icon"></i>
                        <span class="nav-text">Orders</span>
                        <?php if ($pendingOrders > 0): ?>
                        <span class="notification-badge"><?php echo $pendingOrders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="vendors.php">
                        <i class="fa fa-users-cog nav-icon"></i>
                        <span class="nav-text">Vendors</span>
                        <?php if ($pendingVendors > 0): ?>
                        <span class="notification-badge"><?php echo $pendingVendors; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="users.php">
                        <i class="fa fa-users nav-icon"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="delivery.php">
                        <i class="fa fa-truck nav-icon"></i>
                        <span class="nav-text">Delivery</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="analytics.php">
                        <i class="fa fa-chart-bar nav-icon"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="testimonials.php">
                        <i class="fa fa-star nav-icon"></i>
                        <span class="nav-text">Testimonials</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="Notifications.php">
                        <i class="fa fa-bell nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="contact.php">
                        <i class="fa fa-envelope nav-icon"></i>
                        <span class="nav-text">Contact</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="settings.php">
                        <i class="fa fa-cog nav-icon"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                  <b></b><b></b>
                  <a href="#" onclick="confirmLogout(event)">
                    <i class="fa fa-sign-out-alt nav-icon"></i>
                    <span class="nav-text">Logout</span>
                  </a>
                </li>
            </ul>
        </nav>

        <div class="content">
            <div class="header">
                <h1><i class="fas fa-box"></i> Products Management</h1>
                <div class="user-section">
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                    <?php if (!empty($admin['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($admin['avatar']); ?>" alt="Admin" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select class="form-control" name="category" id="category-filter">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" name="status" id="status-filter">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="out-of-stock" <?php echo $statusFilter === 'out-of-stock' ? 'selected' : ''; ?>>Out of Stock</option>
                            <option value="low_stock" <?php echo $statusFilter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" class="form-control" name="search" id="search-filter" 
                               value="<?php echo htmlspecialchars($searchQuery); ?>" 
                               placeholder="Search products...">
                    </div>
                    <div class="form-group" style="justify-content: flex-end;">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-primary" onclick="applyFilters()">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="products-section" id="products-container">
                <div class="products-header">
                    <h2>Product List (<?php echo number_format($totalProducts); ?> items)</h2>
                    <div class="action-buttons">
                        <button class="btn btn-success btn-sm" onclick="exportProducts()">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="showBulkActions()">
                            <i class="fas fa-tasks"></i> Bulk Actions
                        </button>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Brand</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="products-tbody">
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
                                    <span style="opacity: 0.6;">No products found</span>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>"></td>
                                    <td>
                                        <?php if ($product['image']): ?>
                                        <img src="../<?php echo htmlspecialchars($product['image']); ?>" class="product-image" alt="Product">
                                        <?php else: ?>
                                        <div class="product-image" style="display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="color: #ccc;"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <?php if ($product['description']): ?>
                                        <br><small style="color: #999;"><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . '...'; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td><strong>$<?php echo number_format($product['price'], 2); ?></strong></td>
                                    <td><?php echo getStockBadge($product['stock_quantity'], $product['min_stock_level']); ?></td>
                                    <td><?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?></td>
                                    <td><?php echo getStatusBadge($product['is_active'], $product['stock_quantity']); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="editProduct(<?php echo $product['id']; ?>)" style="margin-right: 5px;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination" id="pagination-container">
                    <?php if ($page > 1): ?>
                    <button onclick="loadPage(<?php echo $page-1; ?>)">
                        &laquo; Previous
                    </button>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <button class="<?php echo $page == $i ? 'active' : ''; ?>" 
                            onclick="loadPage(<?php echo $i; ?>)">
                        <?php echo $i; ?>
                    </button>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <button onclick="loadPage(<?php echo $page+1; ?>)">
                        Next &raquo;
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="js/products.js"></script>
</body>
</html>