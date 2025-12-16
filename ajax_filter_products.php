<?php
require_once 'config.php';

header('Content-Type: application/json');

// Get filters
$category = $_GET['category'] ?? '';
$subcategory = $_GET['subcategory'] ?? '';
$subsubcategory = $_GET['subsubcategory'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? '';
$minPrice = (int)($_GET['min_price'] ?? 0);
$maxPrice = (int)($_GET['max_price'] ?? 10000);
$selectedBrands = isset($_GET['brands']) ? explode(',', $_GET['brands']) : [];

// FIXED: Proper category hierarchy logic
$query = "SELECT DISTINCT p.*, 
          COALESCE(p.rating, 0) as rating, 
          c.name as category_name,
          c.id as category_id,
          c.level as category_level,
          parent_cat.name as parent_category_name,
          parent_cat.id as parent_category_id,
          grandparent_cat.name as grandparent_category_name,
          grandparent_cat.id as grandparent_category_id,
          CASE 
            WHEN p.original_price > p.price THEN ROUND(((p.original_price - p.price) / p.original_price) * 100)
            ELSE 0 
          END as discount_percentage
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN categories parent_cat ON c.parent_id = parent_cat.id
          LEFT JOIN categories grandparent_cat ON parent_cat.parent_id = grandparent_cat.id
          WHERE p.is_active = 1";
$params = [];

// FIXED Category Filter Logic - Show all products in hierarchy
if ($category && $category !== 'all') {
    if ($subsubcategory) {
        // L3 selected - show ONLY products directly in this L3 category
        $query .= " AND c.name = ?";
        $params[] = $subsubcategory;
    } elseif ($subcategory) {
        // L2 selected - show products in this L2 + ALL its L3 children
        $query .= " AND (c.name = ? OR parent_cat.name = ?)";
        $params[] = $subcategory;
        $params[] = $subcategory;
    } else {
        // L1 selected - show products in L1 + all L2 children + all L3 grandchildren
        $query .= " AND (
            c.name = ? OR 
            parent_cat.name = ? OR 
            grandparent_cat.name = ?
        )";
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

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build active filter info with proper breadcrumb
    $activeFilter = [];
    if ($subsubcategory) {
        $activeFilter = [
            'type' => 'L3',
            'level' => '3',
            'name' => $subsubcategory,
            'full_path' => ucfirst($category) . ' > ' . $subcategory . ' > ' . $subsubcategory,
            'breadcrumb' => [
                ['name' => ucfirst($category), 'level' => 'L1', 'param' => strtolower($category)],
                ['name' => $subcategory, 'level' => 'L2', 'param' => urlencode($subcategory)],
                ['name' => $subsubcategory, 'level' => 'L3', 'param' => urlencode($subsubcategory)]
            ],
            'description' => "Showing products from <strong>" . htmlspecialchars($subsubcategory) . "</strong> category"
        ];
    } elseif ($subcategory) {
        $activeFilter = [
            'type' => 'L2',
            'level' => '2',
            'name' => $subcategory,
            'full_path' => ucfirst($category) . ' > ' . $subcategory,
            'breadcrumb' => [
                ['name' => ucfirst($category), 'level' => 'L1', 'param' => strtolower($category)],
                ['name' => $subcategory, 'level' => 'L2', 'param' => urlencode($subcategory)]
            ],
            'description' => "Showing all products from <strong>" . htmlspecialchars($subcategory) . "</strong> and its subcategories"
        ];
    } elseif ($category && $category !== 'all') {
        $activeFilter = [
            'type' => 'L1',
            'level' => '1',
            'name' => ucfirst($category),
            'full_path' => ucfirst($category),
            'breadcrumb' => [
                ['name' => ucfirst($category), 'level' => 'L1', 'param' => strtolower($category)]
            ],
            'description' => "Showing all products from <strong>" . htmlspecialchars(ucfirst($category)) . "</strong> category"
        ];
    }
    
    // Get price range for filtered products
    $priceRange = [
        'min' => 0,
        'max' => 10000
    ];
    if (!empty($products)) {
        $prices = array_column($products, 'price');
        $priceRange['min'] = (int)min($prices);
        $priceRange['max'] = (int)max($prices);
    }
    
    // Get available brands from filtered products
    $availableBrands = [];
    if (!empty($products)) {
        $brands = array_unique(array_column($products, 'brand'));
        $availableBrands = array_filter($brands);
        sort($availableBrands);
    }
    
    // Additional filter stats
    $filterStats = [
        'total_products' => count($products),
        'avg_price' => !empty($products) ? round(array_sum(array_column($products, 'price')) / count($products), 2) : 0,
        'min_price' => !empty($products) ? min(array_column($products, 'price')) : 0,
        'max_price' => !empty($products) ? max(array_column($products, 'price')) : 0,
        'brands_count' => count($availableBrands),
        'in_stock_count' => count(array_filter($products, function($p) { return $p['stock_quantity'] > 0; })),
        'out_of_stock_count' => count(array_filter($products, function($p) { return $p['stock_quantity'] <= 0; })),
        'has_discount_count' => count(array_filter($products, function($p) { return $p['original_price'] > $p['price']; }))
    ];
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products),
        'activeFilter' => $activeFilter,
        'priceRange' => $priceRange,
        'availableBrands' => $availableBrands,
        'filterStats' => $filterStats
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'products' => [],
        'count' => 0,
        'error' => $e->getMessage()
    ]);
}
?>