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
$selectedBrands = isset($_GET['brands']) ? explode(',', $_GET['brands']) : [];

// FIXED: Build query with proper category hierarchy logic
$query = "SELECT p.*, 
          COALESCE(p.rating, 0) as rating, 
          c.name as category_name,
          c.id as category_id,
          c.level as category_level,
          parent_cat.name as parent_category_name,
          parent_cat.id as parent_category_id,
          grandparent_cat.name as grandparent_category_name,
          grandparent_cat.id as grandparent_category_id,
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

// FIXED: Proper category hierarchy logic
if ($category !== 'all') {
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

// Build active filter info for display
$activeFilterInfo = '';
if ($subsubcategory) {
    $activeFilterInfo = ucfirst($category) . ' > ' . $subcategory . ' > ' . $subsubcategory;
} elseif ($subcategory) {
    $activeFilterInfo = ucfirst($category) . ' > ' . $subcategory;
} elseif ($category && $category !== 'all') {
    $activeFilterInfo = ucfirst($category);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - FutureMart</title>
    <!-- Add your existing CSS and meta tags here -->
    <style>
        /* Active Filter Banner Styles */
        .active-filter-banner {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(236, 72, 153, 0.2) 100%);
            border: 2px solid rgba(99, 102, 241, 0.3);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .active-filter-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #ec4899, #06b6d4);
        }

        .filter-breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 0.8rem;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 0.8rem;
            background: rgba(99, 102, 241, 0.2);
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .breadcrumb-item:hover:not(.active) {
            background: rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
        }

        .breadcrumb-item.active {
            background: rgba(99, 102, 241, 0.5);
            font-weight: 600;
        }

        .breadcrumb-separator {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .clear-category-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(239, 68, 68, 0.2);
            border: 2px solid var(--danger);
            color: var(--danger);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .clear-category-btn:hover {
            background: var(--danger);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Your existing navbar and header code -->

    <div class="container mb-5">
        <!-- Active Filter Banner -->
        <?php if ($activeFilterInfo): ?>
        <div class="active-filter-banner">
            <button class="clear-category-btn" onclick="clearCategoryFilter()">
                <i class="fas fa-times me-2"></i>Clear Category
            </button>
            <div class="filter-breadcrumb">
                <i class="fas fa-filter me-2" style="color: var(--primary-color);"></i>
                
                <?php if ($category && $category !== 'all'): ?>
                    <div class="breadcrumb-item <?php echo (!$subcategory && !$subsubcategory) ? 'active' : ''; ?>"
                         onclick="filterByCategory('<?php echo strtolower($category); ?>')">
                        <i class="fas fa-folder<?php echo (!$subcategory && !$subsubcategory) ? '-open' : ''; ?> me-1"></i>
                        <?php echo ucfirst($category); ?>
                    </div>
                <?php endif; ?>

                <?php if ($subcategory): ?>
                    <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
                    <div class="breadcrumb-item <?php echo !$subsubcategory ? 'active' : ''; ?>"
                         onclick="filterByCategory('<?php echo strtolower($category); ?>', '<?php echo urlencode($subcategory); ?>')">
                        <i class="fas fa-folder<?php echo !$subsubcategory ? '-open' : ''; ?> me-1"></i>
                        <?php echo $subcategory; ?>
                    </div>
                <?php endif; ?>

                <?php if ($subsubcategory): ?>
                    <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
                    <div class="breadcrumb-item active">
                        <i class="fas fa-folder-open me-1"></i>
                        <?php echo $subsubcategory; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="filter-description" style="color: var(--text-light); font-size: 1rem;">
                <?php
                if ($subsubcategory) {
                    echo "Showing products from <strong>" . htmlspecialchars($subsubcategory) . "</strong> category";
                } elseif ($subcategory) {
                    echo "Showing all products from <strong>" . htmlspecialchars($subcategory) . "</strong> and its subcategories";
                } else {
                    echo "Showing all products from <strong>" . htmlspecialchars(ucfirst($category)) . "</strong> category";
                }
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Your existing products grid and filter code -->
    </div>

    <script>
        // Clear category filter
        function clearCategoryFilter() {
            window.location.href = 'products.php';
        }

        // Enhanced filter by category with proper navigation
        function filterByCategory(category, subcategory = '', subsubcategory = '') {
            let url = 'products.php?category=' + category;
            if (subcategory) url += '&subcategory=' + subcategory;
            if (subsubcategory) url += '&subsubcategory=' + subsubcategory;
            window.location.href = url;
        }

        // Update the existing applyFilters function to maintain category state
        function applyFilters() {
            const minPrice = document.getElementById('minPriceRange').value;
            const maxPrice = document.getElementById('maxPriceRange').value;
            const sort = document.getElementById('sortSelect').value;

            const brandCheckboxes = document.querySelectorAll('.brand-checkbox:checked');
            const brands = Array.from(brandCheckboxes).map(cb => cb.value);

            currentFilters.minPrice = minPrice;
            currentFilters.maxPrice = maxPrice;
            currentFilters.sort = sort;
            currentFilters.brands = brands;

            // Maintain current category selections
            const params = new URLSearchParams(window.location.search);
            if (params.get('category')) currentFilters.category = params.get('category');
            if (params.get('subcategory')) currentFilters.subcategory = params.get('subcategory');
            if (params.get('subsubcategory')) currentFilters.subsubcategory = params.get('subsubcategory');

            loadProducts();
        }

        console.log('Fixed Category Hierarchy Loaded!');
    </script>
</body>
</html>