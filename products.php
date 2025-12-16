<?php
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$userData = null;

if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
}

// Get filters
$category = $_GET['category'] ?? 'all';
$subcategory = $_GET['subcategory'] ?? '';
$subsubcategory = $_GET['subsubcategory'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? '';
$minPrice = (int)($_GET['min_price'] ?? 0);
$maxPrice = (int)($_GET['max_price'] ?? 10000);
$selectedColors = isset($_GET['colors']) ? explode(',', $_GET['colors']) : [];
$selectedBrands = isset($_GET['brands']) ? explode(',', $_GET['brands']) : [];

// Build query with all filters
$query = "SELECT p.*, 
          COALESCE(p.rating, 0) as rating, 
          c.name as category_name,
          c.id as category_id,
          c.level as category_level,
          parent_cat.name as parent_category_name,
          grandparent_cat.name as grandparent_category_name,
          u.first_name as vendor_name,
          CASE 
            WHEN p.original_price > p.price THEN ROUND(((p.original_price - p.price) / p.original_price) * 100)
            ELSE 0 
          END as discount_percentage
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN categories parent_cat ON c.parent_id = parent_cat.id
          LEFT JOIN categories grandparent_cat ON parent_cat.parent_id = grandparent_cat.id
          LEFT JOIN users u ON p.vendor_id = u.id
          WHERE p.is_active = 1";
$params = [];

// Category filter
if ($category !== 'all') {
    if ($subsubcategory) {
        $query .= " AND c.name = ?";
        $params[] = $subsubcategory;
    } elseif ($subcategory) {
        $query .= " AND (c.name = ? OR parent_cat.name = ?)";
        $params[] = $subcategory;
        $params[] = $subcategory;
    } else {
        $query .= " AND (c.name = ? OR parent_cat.name = ? OR grandparent_cat.name = ?)";
        $params[] = ucfirst($category);
        $params[] = ucfirst($category);
        $params[] = ucfirst($category);
    }
}

// Search filter
if ($search) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Price filter
$query .= " AND p.price BETWEEN ? AND ?";
$params[] = $minPrice;
$params[] = $maxPrice;

// Brand filter
if (!empty($selectedBrands)) {
    $brandPlaceholders = str_repeat('?,', count($selectedBrands) - 1) . '?';
    $query .= " AND p.brand IN ($brandPlaceholders)";
    $params = array_merge($params, $selectedBrands);
}

// Color filter
if (!empty($selectedColors)) {
    $colorPlaceholders = str_repeat('?,', count($selectedColors) - 1) . '?';
    $query .= " AND p.id IN (
        SELECT DISTINCT pc.product_id 
        FROM product_colors pc 
        INNER JOIN colors col ON pc.color_id = col.id 
        WHERE col.name IN ($colorPlaceholders)
    )";
    $params = array_merge($params, $selectedColors);
}

// Sorting
switch ($sort) {
    case 'price-low':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price-high':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'rating':
        $query .= " ORDER BY p.rating DESC";
        break;
    case 'newest':
        $query .= " ORDER BY p.created_at DESC";
        break;
    case 'discount':
        $query .= " ORDER BY discount_percentage DESC";
        break;
    case 'popular':
        $query .= " ORDER BY p.review_count DESC";
        break;
    default:
        $query .= " ORDER BY p.id DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get main categories (L1)
$stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order, name");
$mainCategories = $stmt->fetchAll();

// Get L2 categories
$stmt = $pdo->query("SELECT c.*, parent.name as parent_name, parent.id as parent_id
                     FROM categories c 
                     LEFT JOIN categories parent ON c.parent_id = parent.id 
                     WHERE c.level = 1 AND c.is_active = 1 
                     ORDER BY c.parent_id, c.sort_order, c.name");
$l2Categories = $stmt->fetchAll();

// Get L3 categories
$stmt = $pdo->query("SELECT c.*, parent.name as parent_name, parent.id as parent_id
                     FROM categories c 
                     LEFT JOIN categories parent ON c.parent_id = parent.id 
                     WHERE c.level = 2 AND c.is_active = 1 
                     ORDER BY c.parent_id, c.sort_order, c.name");
$l3Categories = $stmt->fetchAll();

// Group L2 categories by parent
$categoriesByParent = [];
foreach ($l2Categories as $cat) {
    $parentId = $cat['parent_id'];
    if (!isset($categoriesByParent[$parentId])) {
        $categoriesByParent[$parentId] = [];
    }
    $categoriesByParent[$parentId][] = $cat;
}

// Group L3 categories by parent
$l3ByParent = [];
foreach ($l3Categories as $cat) {
    $parentId = $cat['parent_id'];
    if (!isset($l3ByParent[$parentId])) {
        $l3ByParent[$parentId] = [];
    }
    $l3ByParent[$parentId][] = $cat;
}

// Debug: Let's see what we have
error_log("Main Categories: " . count($mainCategories));
foreach ($mainCategories as $mc) {
    error_log("L1: " . $mc['name'] . " (ID: " . $mc['id'] . ")");
    if (isset($categoriesByParent[$mc['id']])) {
        error_log("  - Has " . count($categoriesByParent[$mc['id']]) . " L2 children");
    }
}

// Get all available colors
$stmt = $pdo->query("SELECT DISTINCT c.* FROM colors c 
                     INNER JOIN product_colors pc ON c.id = pc.color_id 
                     WHERE c.is_active = 1 
                     ORDER BY c.name");
$availableColors = $stmt->fetchAll();

// Get all available brands
$stmt = $pdo->query("SELECT DISTINCT brand FROM products 
                     WHERE brand IS NOT NULL AND brand != '' AND is_active = 1 
                     ORDER BY brand");
$availableBrands = $stmt->fetchAll();

// Get cart count
$cartCount = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['total'] ?? 0;
}

// Get wishlist items
$wishlistItems = [];
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlistItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$theme = $userData['theme_preference'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #f59e0b;
            --accent-color: #06b6d4;
            --light-bg: #f8fafc;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Hide Scrollbars */
        ::-webkit-scrollbar {
            width: 0px;
            height: 0px;
        }

        * {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-bg);
            color: var(--text-light);
            padding-top: 185px;
            line-height: 1.6;
        }

        /* topbar */
        .topbar {
            position: absolute;
            background: var(--dark-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            padding: 0.5rem 0;
            transition: all 0.3s ease;
        }

        .topbar a {
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            margin: 0 0.5rem;
            font-size: 0.9rem;
        }

        .topbar a:hover {
            color: var(--primary-color);
        }

        .text-body i {
            color: var(--text-muted);
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        .text-body i:hover {
            color: var(--primary-color);
        }

        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(236, 72, 153, 0.15) 100%);
            padding-top: 65px;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: #0f172a;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            padding-top: 15px;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-link {
            color: var(--text-light) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
            transform: translateY(-2px);
        }

        .nav-link.active {
            color: var(--primary-color) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--gradient-1);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        /* Horizontal Category Menu */
        .category-nav-wrapper {
            position: fixed;
            top: 135px;
            left: 0;
            right: 0;
            background: var(--dark-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 999;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            overflow: visible;
            transition: all 0.3s ease;
        }

        .category-nav-wrapper.scrolled {
            top: 70px;
            transition: all 0.3s ease;
        }

        .category-nav {
            display: flex;
            align-items: center !important;
            justify-content: flex-start;
            gap: 0;
            padding: 0;
            margin: 0;
            list-style: none;
            overflow-x: auto;
            overflow-y: visible !important;
            white-space: nowrap;
            scrollbar-width: thin;
            scrollbar-color: rgba(99, 102, 241, 0.5) transparent;
        }

        .category-nav::-webkit-scrollbar {
            height: 4px;
        }

        .category-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .category-nav::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.5);
            border-radius: 10px;
        }

        .category-nav-item {
            position: relative;
            flex-shrink: 0;
        }

        .category-nav-link {
            display: flex;
            align-items: center !important;
            justify-content: flex-start;
            gap: 0.5rem;
            background: transparent;
            border: none;
            outline: none;
            margin-left: 40px;
            padding: 1rem 1.5rem;
            color: var(--text-light);
            text-decoration: none;
            font-weight: 400;
            font-size: 0.80rem;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            white-space: nowrap;
        }

        .category-nav-link span {
            cursor: pointer;
        }

        .category-nav-link:hover {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
            border-bottom-color: var(--primary-color);
        }

        .category-nav-link i.fa-chevron-down {
            font-size: 0.75rem;
            transition: transform 0.3s ease;
        }

        .category-nav-item:hover .category-nav-link i.fa-chevron-down {
            transform: rotate(180deg);
        }

        /* L2 Dropdown */
        .category-dropdown {
            position: fixed !important;
            top: 50px !important;
            left: auto;
            background: #1e293b;
            min-width: 320px;
            max-width: 400px;
            border-radius: 15px;
            padding: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10000 !important;
            display: none;
            max-height: none !important;
            overflow: visible !important;
        }

        /* Show on hover */
        .category-nav-item:hover .category-dropdown {
            display: block !important;
        }

        .dropdown-item-custom {
            position: relative;
            margin-bottom: 0.3rem;
        }

        .dropdown-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.7rem 1rem;
            color: #e2e8f0;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 300;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .dropdown-link span {
            cursor: pointer;
            display: flex;
            align-items: center;
            color: var(--text-muted);
        }

        .dropdown-link:hover {
            background: rgba(99, 102, 241, 0.2);
            color: #6366f1;
        }

        .dropdown-link:hover span {
            color: #6366f1;
        }

        .dropdown-link i.fa-chevron-right {
            font-size: 0.7rem;
            opacity: 0.6;
            transition: all 0.3s ease;
            color: var(--text-muted);
        }

        .dropdown-item-custom:hover .dropdown-link i.fa-chevron-right {
            opacity: 1;
            transform: translateX(3px);
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        /* L3 Submenu - Show All Items Outside */
        .l3-submenu {
            position: fixed !important;
            left: auto;
            top: 80px !important;
            background: #1e293b;
            min-width: 280px;
            max-width: 350px;
            border-radius: 15px;
            padding: 1rem;
            margin-left: -70px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10001 !important;
            display: none;
            max-height: none !important;
            overflow: visible !important;
        }

        /* Show on hover */
        .dropdown-item-custom:hover .l3-submenu {
            display: block !important;
        }

        .l3-submenu-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary-color) !important;
            margin-bottom: 0.8rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(99, 102, 241, 0.3);
        }

        .l3-submenu-item {
            margin-bottom: 0.3rem;
        }

        .l3-submenu-link {
            color: var(--text-muted) !important;
            text-decoration: none;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 0.8rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            white-space: normal;
            /* Allow text wrapping */
            word-wrap: break-word;
        }

        .l3-submenu-link:hover {
            background: rgba(99, 102, 241, 0.15);
            color: var(--text-light) !important;
            transform: translateX(3px);
        }

        .l3-submenu-link i {
            font-size: 0.7rem;
            color: var(--primary-color) !important;
            flex-shrink: 0;
        }


        /* Cart Icon */
        .cart-icon {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .cart-icon:hover {
            transform: scale(1.1);
        }

        .cart-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ec4899;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            animation: pulse 2s infinite;
            box-shadow: 0 0 10px rgba(236, 72, 153, 0.5);
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.15);
            }
        }

        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .user-avatar-placeholder {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid var(--primary-color);
        }

        /* Search Section */
        .search-hero {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(236, 72, 153, 0.15) 100%);
            padding: 3rem 0 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: visible;
        }

        .search-container {
            position: relative;
            z-index: 100;
            max-width: 900px;
            margin: 0 auto;
        }

        .search-wrapper {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 2px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .search-wrapper:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.4);
        }

        .search-input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 0.8rem 1.5rem;
            color: var(--text-light);
            font-size: 1rem;
            outline: none;
        }

        .search-input::placeholder {
            color: var(--text-muted);
        }

        /* Live Search Results */
        .live-search-results {
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 0;
            right: 0;
            background: rgba(30, 41, 59, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            margin-top: 0.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-height: 450px;
            overflow-y: auto;
            display: none;
            z-index: 999;
        }

        .live-search-results.active {
            display: block;
        }

        .search-result-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .search-result-item:hover {
            background: rgba(99, 102, 241, 0.2);
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-image {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
        }

        .search-result-info {
            flex: 1;
        }

        .search-result-name {
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 0.2rem;
        }

        .search-result-price {
            color: #06b6d4;
            font-weight: 700;
        }

        .search-btn {
            background: var(--gradient-1);
            border: none;
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        /* Filters Sidebar */
        .filters-sidebar {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 160px;
            max-height: calc(100vh - 180px);
            overflow-y: auto;
        }

        .filter-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .filter-section:last-child {
            border-bottom: none;
        }

        .filter-section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-light);
        }

        /* Price Range */
        .price-range-container {
            padding: 1rem 0;
        }

        .price-slider {
            width: 100%;
            height: 6px;
            border-radius: 10px;
            background: rgba(30, 41, 59, 0.5);
            outline: none;
            -webkit-appearance: none;
        }

        .price-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--gradient-1);
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.4);
        }

        .price-values {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Brand Filter */
        .brand-list {
            max-height: 250px;
            overflow-y: auto;
        }

        .brand-option {
            display: flex;
            align-items: center;
            padding: 0.6rem 0.8rem;
            margin-bottom: 0.5rem;
            background: rgba(30, 41, 59, 0.3);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .brand-option:hover {
            background: rgba(99, 102, 241, 0.2);
            transform: translateX(3px);
        }

        .brand-option input[type="checkbox"] {
            margin-right: 0.8rem;
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .brand-option label {
            cursor: pointer;
        }

        .brand-option:last-child {
            margin-bottom: 0;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;

        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        /* Product Cards */
        .product-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(236, 72, 153, 0.08) 100%);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: 100%;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .product-image {
            position: relative;
            cursor: pointer;
            width: 100%;
            height: 220px;
            border-radius: 15px;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-image:hover img {
            transform: scale(1.1);
        }

        .view-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .product-image:hover .view-overlay {
            opacity: 1;
        }

        .discount-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--gradient-2);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            z-index: 10;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .product-content {
            padding: 1rem 0;
        }

        .product-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-light);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price-container {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .product-price {
            font-size: 1.6rem;
            font-weight: 800;
            color: #06b6d4;
        }

        .product-original-price {
            font-size: 1.1rem;
            color: var(--text-muted);
            text-decoration: line-through;
        }

        .product-rating {
            margin-bottom: 1rem;
            color: #fbbf24;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .product-stock {
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .stock-available {
            color: var(--success);
            font-weight: 600;
        }

        .stock-low {
            color: var(--warning);
            font-weight: 600;
        }

        .stock-out {
            color: var(--danger);
            font-weight: 600;
        }

        .btn-add-cart {
            width: 100%;
            background: var(--gradient-3);
            border: none;
            padding: 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            transition: all 0.3s ease;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .btn-add-cart:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(6, 182, 212, 0.4);
        }

        .btn-add-cart:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-wishlist {
            background: transparent;
            border: 2px solid #ec4899;
            padding: 0.75rem;
            border-radius: 50px;
            color: #ec4899;
            transition: all 0.3s ease;
            width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-wishlist:hover {
            background: #ec4899;
            color: white;
            transform: translateY(-3px);
        }

        .btn-wishlist.active {
            background: #ec4899;
            color: white;
        }

        /* Results Info */
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem 1.5rem;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 15px;
        }

        .filter-tag {
            background: var(--gradient-1);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--gradient-1);
            border: none;
            padding: 0.85rem 1.5rem;
            border-radius: 50px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
        }

        /* Loading State */
        .loading-spinner {
            text-align: center;
            padding: 3rem;
        }

        .spinner {
            border: 4px solid rgba(99, 102, 241, 0.2);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Cart Modal */
        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            z-index: 10500;
            backdrop-filter: blur(5px);
        }

        .cart-overlay.active {
            display: block;
        }

        .cart-modal {
            position: fixed;
            top: 0;
            right: -450px;
            width: 420px;
            height: 100%;
            background: #fff;
            z-index: 10501;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.5);
        }

        .cart-modal.open {
            right: 0;
        }

        .cart-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: visible;
        }

        .cart-content {
            flex: 1;
            padding: 1.5rem;
            color: #1e293b;
        }

        .cart-item {
            border-bottom: 1px solid #e2e8f0;
            padding: 1.2rem 0;
        }

        .cart-summary {
            background: #f9fafb;
            padding: 1.5rem;
            border-top: 2px solid #e2e8f0;
            color: #1e293b;
        }

        /* Footer */
        .footer {
            background: #0f172a;
            padding: 3rem 0 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 5rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--gradient-1);
            color: white;
            text-decoration: none;
            margin: 0 0.4rem;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            transform: translateY(-5px);
        }

        /* Alert */
        .alert-custom {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 10000;
            min-width: 320px;
            animation: slideIn 0.4s ease;
            border-radius: 15px;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Light Mode */
        body.light-mode {
            background: #f8fafc;
            color: #1e293b;
        }

        /* topbar */
        body.light-mode .topbar {
            position: absolute;
            background: rgba(199, 205, 251, 0.58);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 50px rgba(99, 102, 241, 0.2) !important;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            padding: 0.5rem 0;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            color: #000 !important;
        }

        body.light-mode .topbar a {
            color: #000;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            margin: 0 0.5rem;
            font-size: 0.9rem;
        }

        body.light-mode .topbar a:hover {
            color: var(--primary-color);
        }

        body.light-mode .text-body i {
            color: #000;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        body.light-mode .text-body i:hover {
            color: var(--primary-color);
        }

        body.light-mode .navbar {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .nav-link {
            color: #1e293b !important;
        }

        body.light-mode .nav-link:hover {
            color: var(--primary-color) !important;
        }

        body.light-mode .category-nav-wrapper {
            background: rgba(199, 205, 251, 0.58);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .category-nav-link {
            color: #1e293b;
        }

        body.light-mode .category-nav-link:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        body.light-mode .category-dropdown,
        body.light-mode .l3-submenu {
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .dropdown-link {
            color: #1e293b;
        }

        body.light-mode .dropdown-link:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        body.light-mode .l3-submenu-link {
            color: #64748b;
        }

        body.light-mode .l3-submenu-link:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color) !important;
        }

        body.light-mode .search-hero {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
        }

        body.light-mode .search-wrapper {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .search-wrapper:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.2);
        }

        body.light-mode .search-input {
            color: #1e293b;
        }

        body.light-mode .search-input::placeholder {
            color: #94a3b8;
        }

        body.light-mode .live-search-results {
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        body.light-mode .search-result-item {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .search-result-item:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        body.light-mode .search-result-name {
            color: #1e293b;
        }

        body.light-mode .filters-sidebar {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .filter-title,
        body.light-mode .filter-section-title {
            color: #1e293b;
        }

        body.light-mode .brand-option {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        body.light-mode .brand-option:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        body.light-mode .product-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .product-card:hover {
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        body.light-mode .product-title {
            color: #1e293b;
        }

        body.light-mode .results-info {
            background: rgba(99, 102, 241, 0.08);
            color: #1e293b;
        }

        body.light-mode .empty-state {
            color: #64748b;
        }

        body.light-mode .empty-state svg {
            fill: #64748b;
        }

        body.light-mode .empty-state i {
            opacity: 0.3;
        }

        body.light-mode .loading-state {
            background: rgba(99, 102, 241, 0.05);
        }

        body.light-mode .loading-state svg {
            fill: #1e293b;
        }

        body.light-mode .loading-state i {
            opacity: 0.3;
        }

        body.light-mode .pagination-wrapper {
            background: rgba(99, 102, 241, 0.05);
        }

        body.light-mode .pagination-link {
            color: #1e293b;
        }

        body.light-mode .pagination-link.active {
            background: #6366f1;
            color: #ffffff;
        }

        body.light-mode .pagination-link:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        body.light-mode .pagination-link i {
            color: #1e293b;
        }

        body.light-mode .pagination-link.active i {
            color: #ffffff;
        }

        body.light-mode .pagination-link:hover i {
            color: #1e293b;
        }

        body.light-mode .pagination-link.disabled {
            opacity: 0.3;
        }

        body.light-mode .pagination-link.disabled:hover {
            background: transparent;
        }

        /* ===== MOBILE RESPONSIVE ===== */

        @media (max-width: 992px) {
            .category-nav-wrapper {
                position: relative;
                top: 0;
                margin-top: 0;
            }

            body {
                padding-top: 120px;
            }

            .category-nav {
                overflow-x: scroll;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 5px;
            }

            .category-nav-link {
                padding: 0.8rem 1.2rem;
                font-size: 0.9rem;
            }

            /* Mobile Dropdown - Convert to Modal Style */
            .category-dropdown {
                position: fixed;
                top: auto;
                left: 0 !important;
                right: 0;
                /* bottom: 0; */
                max-width: 100%;
                width: 100%;
                border-radius: 20px 20px 0 0;
                max-height: 70vh;
                overflow-y: auto;
                transform: translateY(100%);
                transition: transform 0.3s ease;
            }

            .category-nav-item:hover .category-dropdown {
                transform: translateY(0);
            }

            /* L3 Submenu on Mobile - Expand Below */
            .l3-submenu {
                position: relative;
                left: 0;
                top: 0;
                width: 100%;
                margin-left: 0;
                margin-top: 0.5rem;
                border-radius: 10px;
            }

            .dropdown-item-custom:hover .l3-submenu {
                display: block !important;
            }
        }

        @media (max-width: 768px) {
            .category-nav-link {
                padding: 0.7rem 1rem;
                font-size: 0.85rem;
            }

            .category-dropdown {
                max-height: 60vh;
                padding: 0.8rem;
            }

            .l3-submenu {
                padding: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .category-nav-wrapper {
                position: sticky;
                top: 56px;
            }

            .category-nav-link {
                padding: 0.6rem 0.8rem;
                font-size: 0.8rem;
            }

            .dropdown-link {
                padding: 0.5rem 0.8rem;
                font-size: 0.85rem;
            }

            .l3-submenu-link {
                padding: 0.5rem 0.7rem;
                font-size: 0.8rem;
            }

            .category-dropdown {
                max-height: 50vh;
            }
        }

        /* Smooth Scrolling for Category Nav */
        .category-nav {
            scroll-behavior: smooth;
        }

        /* Visual Indicator for More Categories */
        .category-nav-wrapper::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 50px;
            background: linear-gradient(to left, rgba(30, 41, 59, 0.98), transparent);
            pointer-events: none;
        }

        @media (max-width: 992px) {
            .category-nav-wrapper::after {
                display: none;
            }
        }

        /* Accessibility Improvements */
        .category-nav-link:focus,
        .dropdown-link:focus,
        .l3-submenu-link:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Touch-friendly hover areas on mobile */
        @media (hover: none) and (pointer: coarse) {
            .category-nav-link {
                padding: 1rem 1.2rem;
            }

            .dropdown-link {
                padding: 0.9rem 1rem;
            }

            .l3-submenu-link {
                padding: 0.8rem 1rem;
            }
        }

        @media (max-width: 992px) {
            .category-dropdown.mobile-open {
                display: block !important;
                transform: translateY(0);
            }

            /* Add backdrop for mobile dropdowns */
            .category-dropdown.mobile-open::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: -1;
            }

            /* Smooth scroll for dropdown content */
            .category-dropdown {
                scroll-behavior: smooth;
            }
        }
    </style>
</head>

<body<?php echo ($isLoggedIn && $userData && $userData['theme_preference'] === 'light') ? ' class="light-mode"' : ''; ?>>

    <!-- Cart Modal -->
    <?php if ($isLoggedIn): ?>
        <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
        <div class="cart-modal" id="cartModal">
            <div class="cart-header">
                <h5><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h5>
                <button class="btn btn-sm btn-outline-light" onclick="toggleCart()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="cart-body">
                <div class="cart-content" id="cartItems">
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x mb-3 text-muted"></i>
                        <p>Your cart is empty</p>
                    </div>
                </div>
                <div class="cart-summary">
                    <h6 class="mb-3">Order Summary</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="subtotal" class="fw-bold">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping:</span>
                        <span class="text-success fw-bold">Free</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Tax (8%):</span>
                        <span id="tax" class="fw-bold">$0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong style="font-size: 1.2rem;">Total:</strong>
                        <strong id="total" style="font-size: 1.2rem; color: #06b6d4;">$0.00</strong>
                    </div>
                    <button class="btn btn-success w-100 mb-2" onclick="proceedToCheckout()">
                        <i class="fas fa-lock me-2"></i>Proceed to Checkout
                    </button>
                    <button class="btn btn-outline-danger w-100" onclick="clearCart()">
                        <i class="fas fa-trash me-2"></i>Clear Cart
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- topbar start -->
    <div class="container-fluid topbar p-0">
        <div class="row gx-0 d-none d-lg-flex">
            <div class="col-lg-7 px-5 text-start">
                <div class="h-100 d-inline-flex align-items-center py-3 me-4">
                    <small class="fa fa-map-marker-alt text-primary me-2"></small>
                    <small>123 Street, Puttalam, Sri Lanka</small>
                </div>
                <div class="h-100 d-inline-flex align-items-center py-3">
                    <small class="far fa-clock text-primary me-2"></small>
                    <small>Mon - Fri : 09.00 AM - 09.00 PM</small>
                </div>
            </div>
            <div class="media-body col-lg-5 px-5 text-end">
                <div class="h-100 d-inline-flex align-items-center py-3">
                    <small class="fa fa-phone-alt text-primary me-2"></small>
                    <a href="tel:+94755638086">+94 75 563 8086</a>
                </div>
                <div class="h-100 d-inline-flex align-items-center">
                    <a class="text-body px-2" href="">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a class="text-body px-2" href="">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a class="text-body px-2" href="">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a class="text-body ps-2" href="">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- topbar end -->

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-rocket me-2"></i> <?php echo APP_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="products.php">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <?php if ($isLoggedIn): ?>
                        <a href="#" class="nav-link cart-icon" onclick="toggleCart(); return false;">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-badge" id="cartCount"><?php echo $cartCount; ?></span>
                        </a>

                        <div class="dropdown">
                            <div class="user-profile" data-bs-toggle="dropdown">
                                <?php if (!empty($userData['avatar'])): ?>
                                    <img src="uploads/avatars/<?= htmlspecialchars($userData['avatar']); ?>"
                                        alt="Profile" class="user-avatar">
                                <?php else: ?>
                                    <div class="user-avatar-placeholder">
                                        <?= strtoupper(substr($userData['first_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>

                                <span class="d-none d-lg-inline">
                                    <?= htmlspecialchars($userData['first_name']); ?>
                                </span>
                            </div>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="user/dashboard.php">
                                        <i class="fas fa-user me-2"></i>Profile
                                    </a></li>
                                <li><a class="dropdown-item" href="user/orders.php">
                                        <i class="fas fa-shopping-bag me-2"></i>My Orders
                                    </a></li>
                                <li><a class="dropdown-item" href="settings.php">
                                        <i class="fas fa-cog me-2"></i>Settings
                                    </a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-user me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>


    <!-- Horizontal Category Navigation -->
    <div class="category-nav-wrapper">
        <div class="container-fluid">
            <ul class="category-nav">
                <?php
                // Debug output
                echo "<!-- Total Main Categories: " . count($mainCategories) . " -->\n";

                foreach ($mainCategories as $mainCat):
                    $hasChildren = isset($categoriesByParent[$mainCat['id']]) && !empty($categoriesByParent[$mainCat['id']]);
                    echo "<!-- Category: " . $mainCat['name'] . " (ID: " . $mainCat['id'] . ") - Has children: " . ($hasChildren ? 'YES' : 'NO') . " -->\n";
                ?>
                    <li class="category-nav-item">
                        <?php if ($hasChildren): ?>
                            <!-- Category with dropdown -->
                            <div class="category-nav-link">
                                <span onclick="filterByCategory('<?php echo strtolower($mainCat['name']); ?>')">
                                    <?php echo htmlspecialchars($mainCat['name']); ?>
                                </span>
                                <i class="fas fa-chevron-down"></i>
                            </div>

                            <div class="category-dropdown">
                                <?php
                                echo "<!-- L2 Children: " . count($categoriesByParent[$mainCat['id']]) . " -->\n";
                                foreach ($categoriesByParent[$mainCat['id']] as $l2Cat):
                                    $hasL3 = isset($l3ByParent[$l2Cat['id']]) && !empty($l3ByParent[$l2Cat['id']]);
                                    echo "<!-- L2: " . $l2Cat['name'] . " - Has L3: " . ($hasL3 ? 'YES' : 'NO') . " -->\n";
                                ?>
                                    <div class="dropdown-item-custom">
                                        <div class="dropdown-link">
                                            <span onclick="filterByCategory('<?php echo strtolower($mainCat['name']); ?>', '<?php echo urlencode($l2Cat['name']); ?>')">
                                                <i class="fas fa-folder me-2"></i>
                                                <?php echo htmlspecialchars($l2Cat['name']); ?>
                                            </span>
                                            <?php if ($hasL3): ?>
                                                <i class="fas fa-chevron-right"></i>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($hasL3): ?>
                                            <div class="l3-submenu">
                                                <div class="l3-submenu-title">
                                                    <?php echo htmlspecialchars($l2Cat['name']); ?>
                                                </div>
                                                <?php foreach ($l3ByParent[$l2Cat['id']] as $l3Cat): ?>
                                                    <div class="l3-submenu-item">
                                                        <a href="javascript:void(0)" class="l3-submenu-link"
                                                            onclick="filterByCategory('<?php echo strtolower($mainCat['name']); ?>', '<?php echo urlencode($l2Cat['name']); ?>', '<?php echo urlencode($l3Cat['name']); ?>')">
                                                            <i class="fas fa-caret-right"></i>
                                                            <?php echo htmlspecialchars($l3Cat['name']); ?>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Category without dropdown -->
                            <div class="category-nav-link" onclick="filterByCategory('<?php echo strtolower($mainCat['name']); ?>')">
                                <span><?php echo htmlspecialchars($mainCat['name']); ?></span>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Search Hero Section -->
    <div class="search-hero">
        <div class="container">
            <div class="search-container">
                <div class="search-wrapper">
                    <i class="fas fa-search ms-3" style="color: var(--text-muted); font-size: 1.2rem;"></i>
                    <input type="text" id="liveSearchInput" class="search-input"
                        placeholder="Search for products, brands, categories..."
                        autocomplete="off">
                    <button type="button" class="search-btn" onclick="performSearch()">
                        <i class="fas fa-search me-2"></i>
                        Search
                    </button>

                    <!-- Live Search Results -->
                    <div class="live-search-results" id="liveSearchResults"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3">
                <div class="filters-sidebar">
                    <h5 class="filter-title">
                        <i class="fas fa-sliders-h"></i>
                        Filters
                    </h5>

                    <!-- Price Range -->
                    <div class="filter-section">
                        <h6 class="filter-section-title">
                            <i class="fas fa-dollar-sign me-2"></i>Price Range
                        </h6>
                        <div class="price-range-container">
                            <input type="range" id="minPriceRange" class="price-slider"
                                min="0" max="10000" step="100" value="<?php echo $minPrice; ?>"
                                oninput="updatePriceDisplay()">
                            <input type="range" id="maxPriceRange" class="price-slider"
                                min="0" max="10000" step="100" value="<?php echo $maxPrice; ?>"
                                oninput="updatePriceDisplay()">
                            <div class="price-values">
                                <span id="minPriceDisplay">$<?php echo $minPrice; ?></span>
                                <span id="maxPriceDisplay">$<?php echo $maxPrice; ?></span>
                            </div>
                            <button class="btn btn-primary w-100 mt-3" onclick="applyFilters()">
                                <i class="fas fa-check me-2"></i>Apply Price
                            </button>
                        </div>
                    </div>

                    <!-- Brand Filter -->
                    <?php if (!empty($availableBrands)): ?>
                        <div class="filter-section">
                            <h6 class="filter-section-title">
                                <i class="fas fa-tag me-2"></i>Brands
                            </h6>
                            <div class="brand-list">
                                <?php foreach ($availableBrands as $brand): ?>
                                    <label class="brand-option">
                                        <input type="checkbox" class="brand-checkbox"
                                            value="<?php echo htmlspecialchars($brand['brand']); ?>"
                                            <?php echo in_array($brand['brand'], $selectedBrands) ? 'checked' : ''; ?>
                                            onchange="applyFilters()">
                                        <span><?php echo htmlspecialchars($brand['brand']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Clear Filters -->
                    <button class="btn btn-danger w-100" onclick="clearAllFilters()">
                        <i class="fas fa-times-circle me-2"></i>Clear All Filters
                    </button>
                </div>
            </div>

            <!-- Products Section -->
            <div class="col-lg-9">
                <!-- Sort Bar -->
                <div class="results-info">
                    <div id="productCount">
                        <i class="fas fa-check-circle me-2"></i>
                        Found <strong><?php echo count($products); ?></strong> products
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label style="color: var(--text-light); white-space: nowrap;">
                            <i class="fas fa-sort me-1"></i>Sort:
                        </label>
                        <select class="form-select" id="sortSelect" style="width: auto;" onchange="applyFilters()">
                            <option value="">Default</option>
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                            <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Popular</option>
                            <option value="price-low" <?php echo $sort === 'price-low' ? 'selected' : ''; ?>>Price: Low-High</option>
                            <option value="price-high" <?php echo $sort === 'price-high' ? 'selected' : ''; ?>>Price: High-Low</option>
                            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Top Rated</option>
                            <option value="discount" <?php echo $sort === 'discount' ? 'selected' : ''; ?>>Best Discount</option>
                        </select>
                    </div>
                </div>

                <!-- Products Grid -->
                <div id="productsContainer">
                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>No Products Found</h3>
                            <p>Try adjusting your filters or search terms.</p>
                            <button class="btn btn-primary mt-3" onclick="clearAllFilters()">
                                <i class="fas fa-redo me-2"></i>Clear Filters
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($products as $product): ?>
                                <div class="col-lg-4 col-md-6">
                                    <div class="product-card">
                                        <?php if ($product['discount_percentage'] > 0): ?>
                                            <div class="discount-badge">
                                                -<?php echo $product['discount_percentage']; ?>%
                                            </div>
                                        <?php endif; ?>

                                        <a href="product-details.php?id=<?php echo $product['id']; ?>">
                                            <div class="product-image">
                                                <?php if ($product['image']): ?>
                                                    <img src="<?php echo htmlspecialchars($product['image']); ?>"
                                                        alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-box" style="font-size: 3rem; color: white;"></i>
                                                <?php endif; ?>
                                                <div class="view-overlay">
                                                    <i class="fas fa-eye fa-2x"></i>
                                                    <span>Quick View</span>
                                                </div>
                                            </div>
                                        </a>

                                        <div class="product-content">
                                            <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>

                                            <div class="product-rating">
                                                <?php
                                                $rating = max(0, min(5, floatval($product['rating'])));
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                }
                                                ?>
                                                <span class="text-muted" style="font-size:0.85rem;">
                                                    (<?php echo number_format($rating, 1); ?>)
                                                </span>
                                            </div>

                                            <div class="product-price-container">
                                                <div class="product-price">
                                                    $<?php echo number_format($product['price'], 2); ?>
                                                </div>
                                                <?php if ($product['original_price'] > $product['price']): ?>
                                                    <div class="product-original-price">
                                                        $<?php echo number_format($product['original_price'], 2); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="product-stock">
                                                <?php if ($product['stock_quantity'] > 10): ?>
                                                    <span class="stock-available">
                                                        <i class="fas fa-check-circle me-1"></i>In Stock
                                                    </span>
                                                <?php elseif ($product['stock_quantity'] > 0): ?>
                                                    <span class="stock-low">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Only <?php echo $product['stock_quantity']; ?> left
                                                    </span>
                                                <?php else: ?>
                                                    <span class="stock-out">
                                                        <i class="fas fa-times-circle me-1"></i>Out of Stock
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($isLoggedIn): ?>
                                                <div class="d-flex gap-2">
                                                    <?php if ($product['stock_quantity'] > 0): ?>
                                                        <button class="btn btn-add-cart flex-grow-1"
                                                            onclick="addToCart(<?php echo $product['id']; ?>)">
                                                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-add-cart flex-grow-1" disabled>
                                                            <i class="fas fa-ban me-2"></i>Out of Stock
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-wishlist <?php echo in_array($product['id'], $wishlistItems) ? 'active' : ''; ?>"
                                                        onclick="toggleWishlist(<?php echo $product['id']; ?>, this)">
                                                        <i class="<?php echo in_array($product['id'], $wishlistItems) ? 'fas' : 'far'; ?> fa-heart"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-add-cart flex-grow-1" onclick="showLoginRequired()">
                                                        <i class="fas fa-lock me-2"></i>Login to Purchase
                                                    </button>
                                                    <button class="btn btn-wishlist" onclick="showLoginRequired()">
                                                        <i class="far fa-heart"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="navbar-brand mb-3">
                        <i class="fas fa-rocket me-2"></i>FutureMart
                    </h5>
                    <p class="text-light">Your trusted partner for cutting-edge products and exceptional shopping experiences.</p>
                    <div class="social-icons mt-3">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 style="color: var(--text-light); font-weight: 700; margin-bottom: 1rem;">Quick Links</h6>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="products.php">Shop</a></li>
                        <li><a href="categories.php">Categories</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 style="color: var(--text-light); font-weight: 700; margin-bottom: 1rem;">Categories</h6>
                    <ul class="footer-links">
                        <li><a href="#" onclick="filterByCategory('electronics'); return false;">Electronics</a></li>
                        <li><a href="#" onclick="filterByCategory('clothing'); return false;">Fashion</a></li>
                        <li><a href="#" onclick="filterByCategory('home & garden'); return false;">Home & Living</a></li>
                        <li><a href="#" onclick="filterByCategory('sports & outdoors'); return false;">Sports</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 style="color: var(--text-light); font-weight: 700; margin-bottom: 1rem;">Support</h6>
                    <ul class="footer-links">
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Returns</a></li>
                        <li><a href="#">Shipping Info</a></li>
                        <li><a href="settings.php">Settings</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 style="color: var(--text-light); font-weight: 700; margin-bottom: 1rem;">Legal</h6>
                    <ul class="footer-links">
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Cookie Policy</a></li>
                        <li><a href="about.php">About Us</a></li>
                    </ul>
                </div>
            </div>

            <hr style="border-color: rgba(255, 255, 255, 0.1);">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="text-light mb-0">&copy; 2024 FutureMart. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="text-light mb-0">
                        <i class="fas fa-heart text-danger"></i> Made with love
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global Variables
        let searchTimeout;
        let currentFilters = {
            category: '',
            subcategory: '',
            subsubcategory: '',
            search: '',
            sort: '',
            minPrice: 0,
            maxPrice: 10000,
            brands: []
        };

        // Notification System
        function showNotification(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-custom`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 4000);
        }

        // Live Search Functionality
        document.getElementById('liveSearchInput').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();

            if (query.length < 2) {
                document.getElementById('liveSearchResults').classList.remove('active');
                return;
            }

            searchTimeout = setTimeout(() => {
                performLiveSearch(query);
            }, 300);
        });

        function performLiveSearch(query) {
            fetch('ajax_search.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `query=${encodeURIComponent(query)}`
                })
                .then(response => response.json())
                .then(data => {
                    displayLiveSearchResults(data);
                })
                .catch(error => {
                    console.error('Search error:', error);
                });
        }

        function displayLiveSearchResults(results) {
            const resultsContainer = document.getElementById('liveSearchResults');

            if (results.length === 0) {
                resultsContainer.innerHTML = '<div class="search-result-item">No results found</div>';
                resultsContainer.classList.add('active');
                return;
            }

            let html = '';
            results.forEach(product => {
                html += `
                    <div class="search-result-item" onclick="window.location.href='product-details.php?id=${product.id}'">
                        <img src="${product.image || 'img/placeholder.jpg'}" class="search-result-image" alt="${product.name}">
                        <div class="search-result-info">
                            <div class="search-result-name">${product.name}</div>
                            <div class="search-result-price">${parseFloat(product.price).toFixed(2)}</div>
                        </div>
                    </div>
                `;
            });

            resultsContainer.innerHTML = html;
            resultsContainer.classList.add('active');
        }

        function performSearch() {
            const query = document.getElementById('liveSearchInput').value.trim();
            if (query) {
                currentFilters.search = query;
                applyFilters();
            }
        }

        // Close live search on outside click
        document.addEventListener('click', function(e) {
            const searchWrapper = document.querySelector('.search-wrapper');
            const liveResults = document.getElementById('liveSearchResults');
            if (!searchWrapper.contains(e.target)) {
                liveResults.classList.remove('active');
            }
        });

        // Category Filter Functions
        function filterByCategory(category, subcategory = '', subsubcategory = '') {
            currentFilters.category = category;
            currentFilters.subcategory = subcategory;
            currentFilters.subsubcategory = subsubcategory;
            applyFilters();
        }

        // Apply All Filters with AJAX
        function applyFilters() {
            const minPrice = document.getElementById('minPriceRange').value;
            const maxPrice = document.getElementById('maxPriceRange').value;
            const sort = document.getElementById('sortSelect').value;

            // Get selected brands
            const brandCheckboxes = document.querySelectorAll('.brand-checkbox:checked');
            const brands = Array.from(brandCheckboxes).map(cb => cb.value);

            currentFilters.minPrice = minPrice;
            currentFilters.maxPrice = maxPrice;
            currentFilters.sort = sort;
            currentFilters.brands = brands;

            // Show loading state
            document.getElementById('productsContainer').innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p class="mt-3">Loading products...</p>
                </div>
            `;

            // Build query string
            const params = new URLSearchParams();
            if (currentFilters.category) params.append('category', currentFilters.category);
            if (currentFilters.subcategory) params.append('subcategory', currentFilters.subcategory);
            if (currentFilters.subsubcategory) params.append('subsubcategory', currentFilters.subsubcategory);
            if (currentFilters.search) params.append('search', currentFilters.search);
            if (currentFilters.sort) params.append('sort', currentFilters.sort);
            params.append('min_price', currentFilters.minPrice);
            params.append('max_price', currentFilters.maxPrice);
            if (currentFilters.brands.length > 0) params.append('brands', currentFilters.brands.join(','));

            // Fetch filtered products
            fetch('ajax_filter_products.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    displayProducts(data.products);
                    updateProductCount(data.count);

                    // Update URL without page reload
                    const newUrl = window.location.pathname + '?' + params.toString();
                    window.history.pushState({}, '', newUrl);
                })
                .catch(error => {
                    console.error('Filter error:', error);
                    showNotification('Error loading products', 'danger');
                });
        }

        function displayProducts(products) {
            const container = document.getElementById('productsContainer');

            if (products.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No Products Found</h3>
                        <p>Try adjusting your filters or search terms.</p>
                        <button class="btn btn-primary mt-3" onclick="clearAllFilters()">
                            <i class="fas fa-redo me-2"></i>Clear Filters
                        </button>
                    </div>
                `;
                return;
            }

            let html = '<div class="row g-4">';
            products.forEach(product => {
                const isInWishlist = <?php echo json_encode($wishlistItems); ?>.includes(product.id);
                const discount = product.discount_percentage > 0 ?
                    `<div class="discount-badge">-${product.discount_percentage}%</div>` : '';

                let stockHtml = '';
                if (product.stock_quantity > 10) {
                    stockHtml = '<span class="stock-available"><i class="fas fa-check-circle me-1"></i>In Stock</span>';
                } else if (product.stock_quantity > 0) {
                    stockHtml = `<span class="stock-low"><i class="fas fa-exclamation-triangle me-1"></i>Only ${product.stock_quantity} left</span>`;
                } else {
                    stockHtml = '<span class="stock-out"><i class="fas fa-times-circle me-1"></i>Out of Stock</span>';
                }

                let ratingHtml = '';
                for (let i = 1; i <= 5; i++) {
                    ratingHtml += i <= product.rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                }

                const originalPriceHtml = product.original_price > product.price ?
                    `<div class="product-original-price">${parseFloat(product.original_price).toFixed(2)}</div>` : '';

                html += `
                    <div class="col-lg-4 col-md-6">
                        <div class="product-card">
                            ${discount}
                            <a href="product-details.php?id=${product.id}">
                                <div class="product-image">
                                    ${product.image ? 
                                        `<img src="${product.image}" alt="${product.name}">` :
                                        '<i class="fas fa-box" style="font-size: 3rem; color: white;"></i>'
                                    }
                                    <div class="view-overlay">
                                        <i class="fas fa-eye fa-2x"></i>
                                        <span>Quick View</span>
                                    </div>
                                </div>
                            </a>
                            <div class="product-content">
                                <h5 class="product-title">${product.name}</h5>
                                <div class="product-rating">
                                    ${ratingHtml}
                                    <span class="text-muted" style="font-size:0.85rem;">
                                        (${parseFloat(product.rating).toFixed(1)})
                                    </span>
                                </div>
                                <div class="product-price-container">
                                    <div class="product-price">${parseFloat(product.price).toFixed(2)}</div>
                                    ${originalPriceHtml}
                                </div>
                                <div class="product-stock">${stockHtml}</div>
                                ${<?php echo $isLoggedIn ? 'true' : 'false'; ?> ? `
                                    <div class="d-flex gap-2">
                                        ${product.stock_quantity > 0 ? `
                                            <button class="btn btn-add-cart flex-grow-1" onclick="addToCart(${product.id})">
                                                <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                            </button>
                                        ` : `
                                            <button class="btn btn-add-cart flex-grow-1" disabled>
                                                <i class="fas fa-ban me-2"></i>Out of Stock
                                            </button>
                                        `}
                                        <button class="btn btn-wishlist ${isInWishlist ? 'active' : ''}" 
                                            onclick="toggleWishlist(${product.id}, this)">
                                            <i class="${isInWishlist ? 'fas' : 'far'} fa-heart"></i>
                                        </button>
                                    </div>
                                ` : `
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-add-cart flex-grow-1" onclick="showLoginRequired()">
                                            <i class="fas fa-lock me-2"></i>Login to Purchase
                                        </button>
                                        <button class="btn btn-wishlist" onclick="showLoginRequired()">
                                            <i class="far fa-heart"></i>
                                        </button>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function updateProductCount(count) {
            document.getElementById('productCount').innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                Found <strong>${count}</strong> products
            `;
        }

        function updatePriceDisplay() {
            const minPrice = document.getElementById('minPriceRange').value;
            const maxPrice = document.getElementById('maxPriceRange').value;
            document.getElementById('minPriceDisplay').textContent = '$' +
                minPrice;
            document.getElementById('maxPriceDisplay').textContent = '$' +
                maxPrice;
        }

        function clearAllFilters() {
            currentFilters = {
                category: '',
                subcategory: '',
                subsubcategory: '',
                search: '',
                sort: '',
                minPrice: 0,
                maxPrice: 10000,
                brands: []
            };

            document.getElementById('liveSearchInput').value = '';
            document.getElementById('minPriceRange').value = 0;
            document.getElementById('maxPriceRange').value = 10000;
            document.getElementById('sortSelect').value = '';
            document.querySelectorAll('.brand-checkbox').forEach(cb => cb.checked = false);

            updatePriceDisplay();
            applyFilters();
        }

        // Cart Functions
        function toggleCart() {
            <?php if (!$isLoggedIn): ?>
                showLoginRequired();
                return;
            <?php endif; ?>
            document.getElementById('cartModal').classList.toggle('open');
            document.getElementById('cartOverlay').classList.toggle('active');
            if (document.getElementById('cartModal').classList.contains('open')) {
                loadCartItems();
            }
        }

        function loadCartItems() {
            fetch('cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=get'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCartItems(data.items, data.total);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function displayCartItems(items, total) {
            const cartItemsContainer = document.getElementById('cartItems');
            if (items.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x mb-3 text-muted"></i>
                        <p>Your cart is empty</p>
                    </div>`;
            } else {
                cartItemsContainer.innerHTML = items.map(item => `
                    <div class="cart-item">
                        <div class="d-flex gap-3">
                            <img src="${item.image || 'img/placeholder.jpg'}" style="width:70px;height:70px;border-radius:10px;object-fit:cover;">
                            <div class="flex-grow-1">
                                <h6>${item.name}</h6>
                                <small class="text-muted">${parseFloat(item.price).toFixed(2)} each</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" onclick="updateCartQuantity(${item.id}, ${item.quantity - 1})">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <button class="btn btn-outline-secondary" disabled>${item.quantity}</button>
                                <button class="btn btn-outline-secondary" onclick="updateCartQuantity(${item.id}, ${item.quantity + 1})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div>
                                <strong>${parseFloat(item.subtotal).toFixed(2)}</strong>
                                <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart(${item.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            const subtotal = parseFloat(total);
            const tax = subtotal * 0.08;
            document.getElementById('subtotal').textContent = '$' +
                subtotal.toFixed(2);
            document.getElementById('tax').textContent = '$' +
                tax.toFixed(2);
            document.getElementById('total').textContent = '$' +
                (subtotal + tax).toFixed(2);
        }

        function addToCart(productId) {
            const button = event.currentTarget;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            button.disabled = true;

            fetch('cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=add&product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        button.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
                        button.style.background = '#10b981';
                        document.getElementById('cartCount').textContent = data.cartCount;
                        showNotification(data.message, 'success');
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.disabled = false;
                            button.style.background = '';
                        }, 2000);
                    } else {
                        button.innerHTML = originalText;
                        button.disabled = false;
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    showNotification('Error adding to cart', 'danger');
                });
        }

        function updateCartQuantity(cartItemId, newQuantity) {
            if (newQuantity < 1) {
                removeFromCart(cartItemId);
                return;
            }
            fetch('cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=update&cart_item_id=${cartItemId}&quantity=${newQuantity}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadCartItems();
                        document.getElementById('cartCount').textContent = data.cartCount;
                    } else {
                        showNotification(data.message, 'danger');
                    }
                });
        }

        function removeFromCart(cartItemId) {
            if (!confirm('Remove this item?')) return;
            fetch('cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=remove&cart_item_id=${cartItemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Item removed', 'success');
                        loadCartItems();
                        document.getElementById('cartCount').textContent = data.cartCount;
                    }
                });
        }

        function clearCart() {
            if (!confirm('Clear entire cart?')) return;
            fetch('cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=clear'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Cart cleared', 'success');
                        loadCartItems();
                        document.getElementById('cartCount').textContent = '0';
                    }
                });
        }

        function proceedToCheckout() {
            window.location.href = 'checkout.php';
        }

        // Wishlist
        function toggleWishlist(productId, btn) {
            fetch('wishlist_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=toggle&product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.action === 'added') {
                            btn.innerHTML = '<i class="fas fa-heart"></i>';
                            btn.classList.add('active');
                            showNotification('Added to wishlist', 'success');
                        } else {
                            btn.innerHTML = '<i class="far fa-heart"></i>';
                            btn.classList.remove('active');
                            showNotification('Removed from wishlist', 'info');
                        }
                    }
                });
        }

        function showLoginRequired() {
            showNotification('Please login to continue', 'warning');
            setTimeout(() => window.location.href = 'login.php', 1500);
        }

        // Navbar scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Category nav scroll
        window.addEventListener('scroll', function() {
            const categoryNavWrapper = document.querySelector('.category-nav-wrapper');
            if (window.scrollY > 50) {
                categoryNavWrapper.classList.add('scrolled');
            } else {
                categoryNavWrapper.classList.remove('scrolled');
            }
        });

        // Close cart on outside click
        document.addEventListener('click', function(event) {
            const cartModal = document.getElementById('cartModal');
            const cartIcon = document.querySelector('.cart-icon');
            if (cartModal && cartIcon && !cartModal.contains(event.target) &&
                !cartIcon.contains(event.target) && cartModal.classList.contains('open')) {
                toggleCart();
            }
        });

        // Close cart on Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const cartModal = document.getElementById('cartModal');
                if (cartModal && cartModal.classList.contains('open')) {
                    toggleCart();
                }
            }
        });

        console.log('Enhanced Products Page with AJAX Loaded Successfully!');

        // Enhanced Category Navigation for Mobile
        document.addEventListener('DOMContentLoaded', function() {
            const isMobile = window.innerWidth <= 992;

            // Position dropdowns dynamically
            document.querySelectorAll('.category-nav-item').forEach(item => {
                const link = item.querySelector('.category-nav-link');
                const dropdown = item.querySelector('.category-dropdown');

                if (link && dropdown) {
                    // Position L2 dropdown on hover/click
                    item.addEventListener('mouseenter', function() {
                        if (!isMobile) {
                            const rect = link.getBoundingClientRect();
                            dropdown.style.left = rect.left + 'px';
                            dropdown.style.top = '130px';
                        }
                    });

                    if (isMobile) {
                        link.addEventListener('click', function(e) {
                            e.stopPropagation();

                            // Close other open dropdowns
                            document.querySelectorAll('.category-dropdown.mobile-open').forEach(d => {
                                if (d !== dropdown) {
                                    d.classList.remove('mobile-open');
                                }
                            });

                            // Toggle current dropdown
                            dropdown.classList.toggle('mobile-open');
                        });
                    }
                }
            });

            // Position L3 submenus dynamically
            document.querySelectorAll('.dropdown-item-custom').forEach(item => {
                const link = item.querySelector('.dropdown-link');
                const submenu = item.querySelector('.l3-submenu');

                if (link && submenu) {
                    item.addEventListener('mouseenter', function() {
                        if (!isMobile) {
                            const rect = link.getBoundingClientRect();
                            const parentDropdown = item.closest('.category-dropdown');
                            const parentRect = parentDropdown.getBoundingClientRect();

                            // Position to the right of parent dropdown
                            submenu.style.left = (parentRect.right + 10) + 'px';
                            submenu.style.top = rect.top + 'px';
                        }
                    });

                    if (isMobile) {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
                        });
                    }
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.category-nav-item')) {
                    document.querySelectorAll('.category-dropdown').forEach(d => {
                        d.style.display = 'none';
                        d.classList.remove('mobile-open');
                    });
                    document.querySelectorAll('.l3-submenu').forEach(s => {
                        s.style.display = 'none';
                    });
                }
            });

            // Prevent dropdown from closing when clicking inside
            document.querySelectorAll('.category-dropdown, .l3-submenu').forEach(element => {
                element.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                location.reload();
            }, 250);
        });
    </script>
    </body>

</html>