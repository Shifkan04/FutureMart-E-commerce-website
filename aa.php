<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #f59e0b;
            --accent-color: #06b6d4;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-bg);
            color: var(--text-light);
            padding-top: 200px;
        }

        /* Active Filter Display */
        .active-filter-banner {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(236, 72, 153, 0.2) 100%);
            border: 2px solid rgba(99, 102, 241, 0.3);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
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

        .breadcrumb-item:hover {
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

        .filter-description {
            color: var(--text-light);
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .filter-stats {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .filter-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(30, 41, 59, 0.5);
            border-radius: 10px;
            font-size: 0.85rem;
        }

        .filter-stat i {
            color: var(--primary-color);
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

        /* Quick Stats Cards */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #6366f1, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-light);
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* View Mode Toggle */
        .view-mode-toggle {
            display: flex;
            gap: 0.5rem;
            background: rgba(30, 41, 59, 0.5);
            padding: 0.3rem;
            border-radius: 10px;
        }

        .view-mode-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            color: var(--text-muted);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-mode-btn.active {
            background: var(--primary-color);
            color: white;
        }

        /* List View Styles */
        .products-list-view .product-card {
            display: flex;
            flex-direction: row;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .products-list-view .product-image {
            width: 200px;
            height: 200px;
            margin-right: 1.5rem;
            margin-bottom: 0;
        }

        .products-list-view .product-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Comparison Mode */
        .comparison-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(30, 41, 59, 0.98);
            backdrop-filter: blur(20px);
            border-top: 2px solid var(--primary-color);
            padding: 1rem;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .comparison-bar.active {
            transform: translateY(0);
        }

        .comparison-items {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
        }

        .comparison-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(99, 102, 241, 0.2);
            border-radius: 10px;
            white-space: nowrap;
        }

        .comparison-item img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
        }

        /* Filter Toggle for Mobile */
        .filter-toggle-btn {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #6366f1, #ec4899);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.5);
            cursor: pointer;
            z-index: 999;
        }

        @media (max-width: 992px) {
            .filter-toggle-btn {
                display: block;
            }

            .filters-sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                bottom: 0;
                width: 320px;
                z-index: 1001;
                transition: left 0.3s ease;
                overflow-y: auto;
            }

            .filters-sidebar.mobile-open {
                left: 0;
            }

            .filter-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                display: none;
                z-index: 1000;
            }

            .filter-overlay.active {
                display: block;
            }
        }

        /* Loading Skeleton */
        .skeleton {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 50%, rgba(99, 102, 241, 0.1) 100%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
            border-radius: 10px;
        }

        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .skeleton-card {
            height: 400px;
            margin-bottom: 2rem;
        }

        /* Scroll to Top Button */
        .scroll-top-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: linear-gradient(135deg, #6366f1, #ec4899);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.5);
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 998;
        }

        .scroll-top-btn.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top-btn:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>

    <!-- Filter Overlay for Mobile -->
    <div class="filter-overlay" id="filterOverlay" onclick="toggleMobileFilters()"></div>

    <!-- Filter Toggle Button for Mobile -->
    <button class="filter-toggle-btn" onclick="toggleMobileFilters()">
        <i class="fas fa-sliders-h"></i>
    </button>

    <!-- Scroll to Top Button -->
    <button class="scroll-top-btn" id="scrollTopBtn" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </button>

    <div class="container mb-5">
        <!-- Active Filter Banner -->
        <div id="activeFilterBanner" style="display: none;"></div>

        <!-- Quick Stats -->
        <div class="quick-stats" id="quickStats" style="display: none;"></div>

        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3">
                <div class="filters-sidebar" id="filtersSidebar">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="filter-title mb-0">
                            <i class="fas fa-sliders-h"></i> Filters
                        </h5>
                        <button class="btn btn-sm btn-outline-light d-lg-none" onclick="toggleMobileFilters()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Price Range -->
                    <div class="filter-section">
                        <h6 class="filter-section-title">
                            <i class="fas fa-dollar-sign me-2"></i>Price Range
                        </h6>
                        <div class="price-range-container">
                            <input type="range" id="minPriceRange" class="price-slider" 
                                   min="0" max="10000" step="100" value="0" 
                                   oninput="updatePriceDisplay()">
                            <input type="range" id="maxPriceRange" class="price-slider" 
                                   min="0" max="10000" step="100" value="10000" 
                                   oninput="updatePriceDisplay()">
                            <div class="price-values">
                                <span id="minPriceDisplay">$0</span>
                                <span id="maxPriceDisplay">$10000</span>
                            </div>
                            <button class="btn btn-primary w-100 mt-3" onclick="applyFilters()">
                                <i class="fas fa-check me-2"></i>Apply Price
                            </button>
                        </div>
                    </div>

                    <!-- Brand Filter -->
                    <div class="filter-section" id="brandFilterSection">
                        <h6 class="filter-section-title">
                            <i class="fas fa-tag me-2"></i>Brands
                        </h6>
                        <div class="brand-list" id="brandList">
                            <p class="text-muted">Loading brands...</p>
                        </div>
                    </div>

                    <!-- Clear Filters -->
                    <button class="btn btn-danger w-100" onclick="clearAllFilters()">
                        <i class="fas fa-times-circle me-2"></i>Clear All Filters
                    </button>
                </div>
            </div>

            <!-- Products Section -->
            <div class="col-lg-9">
                <!-- Sort Bar & View Options -->
                <div class="results-info">
                    <div id="productCount">
                        <i class="fas fa-check-circle me-2"></i>
                        Found <strong>0</strong> products
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <!-- View Mode Toggle -->
                        <div class="view-mode-toggle">
                            <button class="view-mode-btn active" onclick="setViewMode('grid')" title="Grid View">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="view-mode-btn" onclick="setViewMode('list')" title="List View">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>

                        <!-- Sort Dropdown -->
                        <select class="form-select" id="sortSelect" style="width: auto;" onchange="applyFilters()">
                            <option value="">Default</option>
                            <option value="newest">Newest</option>
                            <option value="popular">Popular</option>
                            <option value="price-low">Price: Low-High</option>
                            <option value="price-high">Price: High-Low</option>
                            <option value="rating">Top Rated</option>
                            <option value="discount">Best Discount</option>
                        </select>
                    </div>
                </div>

                <!-- Products Grid -->
                <div id="productsContainer" class="products-grid-view">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p class="mt-3">Loading products...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparison Bar -->
    <div class="comparison-bar" id="comparisonBar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-2">Compare Products (<span id="compareCount">0</span>/4)</h6>
                    <div class="comparison-items" id="comparisonItems"></div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="viewComparison()">
                        <i class="fas fa-balance-scale me-2"></i>Compare
                    </button>
                    <button class="btn btn-outline-danger" onclick="clearComparison()">
                        <i class="fas fa-times me-2"></i>Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global state
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

        let viewMode = 'grid';
        let comparisonProducts = [];

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadProducts();
            setupScrollListener();
        });

        // Category filtering
        function filterByCategory(category, subcategory = '', subsubcategory = '') {
            currentFilters.category = category;
            currentFilters.subcategory = subcategory;
            currentFilters.subsubcategory = subsubcategory;
            applyFilters();
        }

        // Apply all filters
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

            loadProducts();
        }

        // Load products with AJAX
        function loadProducts() {
            const container = document.getElementById('productsContainer');
            container.innerHTML = getSkeletonHtml();

            const params = new URLSearchParams();
            if (currentFilters.category) params.append('category', currentFilters.category);
            if (currentFilters.subcategory) params.append('subcategory', currentFilters.subcategory);
            if (currentFilters.subsubcategory) params.append('subsubcategory', currentFilters.subsubcategory);
            if (currentFilters.search) params.append('search', currentFilters.search);
            if (currentFilters.sort) params.append('sort', currentFilters.sort);
            params.append('min_price', currentFilters.minPrice);
            params.append('max_price', currentFilters.maxPrice);
            if (currentFilters.brands.length > 0) params.append('brands', currentFilters.brands.join(','));

            fetch('ajax_filter_products.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayProducts(data.products);
                        updateProductCount(data.count);
                        displayActiveFilter(data.activeFilter);
                        displayQuickStats(data.filterStats);
                        updateBrandList(data.availableBrands);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<div class="alert alert-danger">Error loading products</div>';
                });
        }

        // Display active filter banner
        function displayActiveFilter(filter) {
            const banner = document.getElementById('activeFilterBanner');
            
            if (!filter || !filter.name) {
                banner.style.display = 'none';
                return;
            }

            let breadcrumbHtml = '';
            if (filter.breadcrumb) {
                breadcrumbHtml = filter.breadcrumb.map((item, index) => {
                    const isLast = index === filter.breadcrumb.length - 1;
                    const clickHandler = !isLast ? 
                        `onclick="filterByCategory('${item.param}', ${index > 0 ? "'" + filter.breadcrumb[1].param + "'" : "''"})"` : '';
                    
                    return `
                        <div class="breadcrumb-item ${isLast ? 'active' : ''}" ${clickHandler}>
                            <i class="fas fa-folder${isLast ? '-open' : ''} me-1"></i>
                            ${item.name}
                        </div>
                        ${!isLast ? '<span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>' : ''}
                    `;
                }).join('');
            }

            banner.innerHTML = `
                <button class="clear-category-btn" onclick="clearCategoryFilter()">
                    <i class="fas fa-times me-2"></i>Clear Category
                </button>
                <div class="filter-breadcrumb">
                    <i class="fas fa-filter me-2" style="color: var(--primary-color);"></i>
                    ${breadcrumbHtml}
                </div>
                <div class="filter-description">${filter.description}</div>
            `;
            banner.style.display = 'block';
        }

        // Display quick stats
        function displayQuickStats(stats) {
            if (!stats) return;

            const statsContainer = document.getElementById('quickStats');
            statsContainer.innerHTML = `
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                    <div class="stat-value">${stats.total_products}</div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-value">$${stats.avg_price}</div>
                    <div class="stat-label">Average Price</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value">${stats.in_stock_count}</div>
                    <div class="stat-label">In Stock</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                    <div class="stat-value">${stats.has_discount_count}</div>
                    <div class="stat-label">On Sale</div>
                </div>
            `;
            statsContainer.style.display = 'grid';
        }

        // Update brand list
        function updateBrandList(brands) {
            const brandList = document.getElementById('brandList');
            if (!brands || brands.length === 0) {
                brandList.innerHTML = '<p class="text-muted">No brands available</p>';
                return;
            }

            brandList.innerHTML = brands.map(brand => `
                <label class="brand-option">
                    <input type="checkbox" class="brand-checkbox" 
                           value="${brand}" 
                           ${currentFilters.brands.includes(brand) ? 'checked' : ''}
                           onchange="applyFilters()">
                    <span>${brand}</span>
                </label>
            `).join('');
        }

        // View mode toggle
        function setViewMode(mode) {
            viewMode = mode;
            const container = document.getElementById('productsContainer');
            
            document.querySelectorAll('.view-mode-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            if (mode === 'list') {
                container.className = 'products-list-view';
            } else {
                container.className = 'products-grid-view';
            }
        }

        // Mobile filters
        function toggleMobileFilters() {
            const sidebar = document.getElementById('filtersSidebar');
            const overlay = document.getElementById('filterOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        }

        // Clear category filter
        function clearCategoryFilter() {
            currentFilters.category = '';
            currentFilters.subcategory = '';
            currentFilters.subsubcategory = '';
            applyFilters();
        }

        // Clear all filters
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

            document.getElementById('minPriceRange').value = 0;
            document.getElementById('maxPriceRange').value = 10000;
            document.getElementById('sortSelect').value = '';
            document.querySelectorAll('.brand-checkbox').forEach(cb => cb.checked = false);

            updatePriceDisplay();
            loadProducts();
        }

        // Scroll to top
        function setupScrollListener() {
            const scrollBtn = document.getElementById('scrollTopBtn');
            window.addEventListener('scroll', () => {
                if (window.pageYOffset > 300) {
                    scrollBtn.classList.add('visible');
                } else {
                    scrollBtn.classList.remove('visible');
                }
            });
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Skeleton loading
        function getSkeletonHtml() {
            return `
                <div class="row g-4">
                    ${Array(6).fill('<div class="col-lg-4 col-md-6"><div class="skeleton skeleton-card"></div></div>').join('')}
                </div>
            `;
        }

        // Update product count
        function updateProductCount(count) {
            document.getElementById('productCount').innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                Found <strong>${count}</strong> products
            `;
        }

        // Update price display
        function updatePriceDisplay() {
            const minPrice = document.getElementById('minPriceRange').value;
            const maxPrice = document.getElementById('maxPriceRange').value;
            document.getElementById('minPriceDisplay').textContent = '$' + minPrice;
            document.getElementById('maxPriceDisplay').textContent = '$' + maxPrice;
        }

        // Display products (placeholder - integrate with your existing function)
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

            // Your existing product display logic here
            container.innerHTML = '<div class="row g-4">' + 
                products.map(p => `
                    <div class="col-lg-4 col-md-6">
                        <div class="product-card">
                            <h5>${p.name}</h5>
                            <p>$${p.price}</p>
                        </div>
                    </div>
                `).join('') + 
                '</div>';
        }

        console.log('Enhanced Products Page Loaded!');
    </script>
</body>
</html>