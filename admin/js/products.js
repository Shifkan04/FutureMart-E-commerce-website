let allColors = [];
let allSizes = [];
let currentFilters = {
    category: '',
    status: '',
    search: '',
    page: 1
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load colors and sizes
    loadColorsAndSizes();
    
    // Setup filter change listeners
    setupFilterListeners();
    
    // Select all checkbox
    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.product-checkbox').forEach(cb => {
            cb.checked = this.checked;
        });
    });
});

// Setup filter listeners for AJAX
function setupFilterListeners() {
    const categoryFilter = document.getElementById('category-filter');
    const statusFilter = document.getElementById('status-filter');
    const searchFilter = document.getElementById('search-filter');

    // Auto-filter on change
    if (categoryFilter) {
        categoryFilter.addEventListener('change', applyFilters);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }
    if (searchFilter) {
        searchFilter.addEventListener('keyup', debounce(applyFilters, 500));
    }
}

// Debounce function for search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Apply filters with AJAX
function applyFilters() {
    currentFilters.category = document.getElementById('category-filter')?.value || '';
    currentFilters.status = document.getElementById('status-filter')?.value || '';
    currentFilters.search = document.getElementById('search-filter')?.value || '';
    currentFilters.page = 1;
    
    loadProducts();
}

// Load page with AJAX
function loadPage(page) {
    currentFilters.page = page;
    loadProducts();
}

// Load products with AJAX
async function loadProducts() {
    try {
        const params = new URLSearchParams(currentFilters);
        const response = await fetch(`products.php?${params.toString()}`);
        const html = await response.text();
        
        // Parse the HTML and extract the products table and pagination
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        const newTbody = doc.querySelector('#products-tbody');
        const newPagination = doc.querySelector('#pagination-container');
        const newCount = doc.querySelector('.products-header h2');
        
        if (newTbody) {
            document.getElementById('products-tbody').innerHTML = newTbody.innerHTML;
        }
        if (newPagination) {
            const currentPagination = document.getElementById('pagination-container');
            if (currentPagination) {
                currentPagination.outerHTML = newPagination.outerHTML;
            }
        }
        if (newCount) {
            document.querySelector('.products-header h2').textContent = newCount.textContent;
        }
        
        // Reset select all checkbox
        document.getElementById('selectAll').checked = false;
        
    } catch (error) {
        console.error('Error loading products:', error);
        Swal.fire('Error', 'Failed to load products', 'error');
    }
}

// Load colors and sizes
async function loadColorsAndSizes() {
    try {
        // Load colors
        const colorsResponse = await fetch('products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ajax=1&action=get_colors'
        });
        const colorsData = await colorsResponse.json();
        if (colorsData.success) {
            allColors = colorsData.colors;
        }
        
        // Load sizes
        const sizesResponse = await fetch('products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ajax=1&action=get_sizes'
        });
        const sizesData = await sizesResponse.json();
        if (sizesData.success) {
            allSizes = sizesData.sizes;
        }
    } catch (error) {
        console.error('Error loading colors/sizes:', error);
    }
}

// Load subcategories dynamically
async function loadSubcategories(parentId, targetSelectId) {
    try {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'get_subcategories');
        if (parentId) {
            formData.append('parent_id', parentId);
        }
        
        const response = await fetch('products.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            return data.categories;
        }
        return [];
    } catch (error) {
        console.error('Error loading subcategories:', error);
        return [];
    }
}

// Show add product modal
async function showAddModal() {
    const categoriesHtml = await buildCategoryHtml();
    const colorsHtml = buildColorsHtml();
    const sizesHtml = buildSizesHtml();
    
    Swal.fire({
        title: '<i class="fas fa-plus"></i> Add New Product',
        html: `
            <div id="swal-form-content">
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Product Name *</label>
                        <input type="text" id="product-name" class="swal2-input" placeholder="Product Name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>SKU</label>
                        <input type="text" id="product-sku" class="swal2-input" placeholder="SKU (Auto-generated if empty)">
                    </div>
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" id="product-brand" class="swal2-input" placeholder="Brand">
                    </div>
                </div>
                
                ${categoriesHtml}
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Price ($) *</label>
                        <input type="number" id="product-price" class="swal2-input" step="0.01" placeholder="Price" required>
                    </div>
                    <div class="form-group">
                        <label>Original Price ($)</label>
                        <input type="number" id="product-original-price" class="swal2-input" step="0.01" placeholder="Original Price">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" id="product-stock" class="swal2-input" placeholder="Stock" required>
                    </div>
                    <div class="form-group">
                        <label>Min Stock Level</label>
                        <input type="number" id="product-min-stock" class="swal2-input" value="5" placeholder="Min Stock">
                    </div>
                    <div class="form-group">
                        <label>Weight (kg)</label>
                        <input type="number" id="product-weight" class="swal2-input" step="0.01" placeholder="Weight">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea id="product-description" class="swal2-textarea" rows="3" placeholder="Product description"></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label>Colors</label>
                    ${colorsHtml}
                </div>
                
                <div class="form-group full-width">
                    <label>Sizes</label>
                    ${sizesHtml}
                </div>
                
                <div class="form-group full-width">
                    <label>Primary Image</label>
                    <input type="file" id="product-image" class="swal2-file" accept="image/*">
                </div>
                
                <div class="form-group full-width">
                    <label>Additional Images</label>
                    <input type="file" id="product-additional-images" class="swal2-file" accept="image/*" multiple>
                </div>
                
                <div class="form-group full-width">
                    <label>Status</label>
                    <select id="product-status" class="swal2-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        `,
        width: '900px',
        showCancelButton: true,
        confirmButtonText: 'Save Product',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel'
        },
        didOpen: () => {
            setupCategoryListeners();
        },
        preConfirm: () => {
            return saveProduct();
        }
    });
}

// Build category HTML with cascade
async function buildCategoryHtml() {
    const categories = await loadSubcategories(null);
    
    return `
        <div class="form-group full-width category-cascade">
            <label>Category * (Level 1)</label>
            <select id="category-level-1" class="swal2-select category-level active">
                <option value="">Select Category</option>
                ${categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
            </select>
            
            <div id="category-level-2-container"></div>
            <div id="category-level-3-container"></div>
        </div>
    `;
}

// Setup category cascade listeners
function setupCategoryListeners() {
    document.getElementById('category-level-1')?.addEventListener('change', async function() {
        const parentId = this.value;
        const level2Container = document.getElementById('category-level-2-container');
        const level3Container = document.getElementById('category-level-3-container');
        
        // Clear lower levels
        level2Container.innerHTML = '';
        level3Container.innerHTML = '';
        
        if (!parentId) return;
        
        const subcategories = await loadSubcategories(parentId);
        if (subcategories.length > 0) {
            level2Container.innerHTML = `
                <label>Subcategory (Level 2)</label>
                <select id="category-level-2" class="swal2-select category-level active">
                    <option value="">Select Subcategory</option>
                    ${subcategories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
                </select>
            `;
            
            // Setup level 2 listener
            document.getElementById('category-level-2')?.addEventListener('change', async function() {
                const parentId2 = this.value;
                level3Container.innerHTML = '';
                
                if (!parentId2) return;
                
                const subcategories2 = await loadSubcategories(parentId2);
                if (subcategories2.length > 0) {
                    level3Container.innerHTML = `
                        <label>Subcategory (Level 3)</label>
                        <select id="category-level-3" class="swal2-select category-level active">
                            <option value="">Select Subcategory</option>
                            ${subcategories2.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
                        </select>
                    `;
                }
            });
        }
    });
}

// Build colors HTML
function buildColorsHtml() {
    if (allColors.length === 0) return '<p>No colors available</p>';
    
    return `
        <div class="color-checkbox-grid">
            ${allColors.map(color => `
                <div class="color-checkbox-item">
                    <input type="checkbox" id="color-${color.id}" name="colors[]" value="${color.id}">
                    <div class="color-swatch" style="background-color: ${color.hex_code}"></div>
                    <label for="color-${color.id}">${color.name}</label>
                </div>
            `).join('')}
        </div>
    `;
}

// Build sizes HTML
function buildSizesHtml() {
    if (allSizes.length === 0) return '<p>No sizes available</p>';
    
    return `
        <div class="size-checkbox-grid">
            ${allSizes.map(size => `
                <div class="size-checkbox-item">
                    <input type="checkbox" id="size-${size.id}" name="sizes[]" value="${size.id}">
                    <label for="size-${size.id}">${size.name}</label>
                </div>
            `).join('')}
        </div>
    `;
}

// Save product
async function saveProduct() {
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'add_product');
    formData.append('name', document.getElementById('product-name').value);
    formData.append('sku', document.getElementById('product-sku').value);
    formData.append('brand', document.getElementById('product-brand').value);
    formData.append('price', document.getElementById('product-price').value);
    formData.append('original_price', document.getElementById('product-original-price').value || document.getElementById('product-price').value);
    formData.append('stock_quantity', document.getElementById('product-stock').value);
    formData.append('min_stock_level', document.getElementById('product-min-stock').value || 5);
    formData.append('weight', document.getElementById('product-weight').value);
    formData.append('description', document.getElementById('product-description').value);
    formData.append('is_active', document.getElementById('product-status').value);
    
    // Get selected category (deepest level)
    const category3 = document.getElementById('category-level-3')?.value;
    const category2 = document.getElementById('category-level-2')?.value;
    const category1 = document.getElementById('category-level-1')?.value;
    const categoryId = category3 || category2 || category1;
    
    if (!categoryId) {
        Swal.showValidationMessage('Please select a category');
        return false;
    }
    formData.append('category_id', categoryId);
    
    // Get selected colors
    document.querySelectorAll('input[name="colors[]"]:checked').forEach(cb => {
        formData.append('colors[]', cb.value);
    });
    
    // Get selected sizes
    document.querySelectorAll('input[name="sizes[]"]:checked').forEach(cb => {
        formData.append('sizes[]', cb.value);
    });
    
    // Get primary image
    const imageFile = document.getElementById('product-image').files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }
    
    // Get additional images
    const additionalImages = document.getElementById('product-additional-images').files;
    for (let i = 0; i < additionalImages.length; i++) {
        formData.append('additional_images[]', additionalImages[i]);
    }
    
    try {
        const response = await fetch('products.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            await Swal.fire('Success', data.message, 'success');
            location.reload();
        } else {
            Swal.showValidationMessage(data.message);
            return false;
        }
    } catch (error) {
        Swal.showValidationMessage('Error: ' + error.message);
        return false;
    }
}

// Edit product
async function editProduct(id) {
    try {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'get_product');
        formData.append('product_id', id);
        
        const response = await fetch('products.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (!data.success) {
            Swal.fire('Error', data.message, 'error');
            return;
        }
        
        const product = data.product;
        await showEditModal(product);
        
    } catch (error) {
        Swal.fire('Error', 'Failed to load product: ' + error.message, 'error');
    }
}

// Show edit modal
async function showEditModal(product) {
    const categoriesHtml = await buildCategoryHtml();
    const colorsHtml = buildColorsHtml();
    const sizesHtml = buildSizesHtml();
    
    Swal.fire({
        title: '<i class="fas fa-edit"></i> Edit Product',
        html: `
            <div id="swal-form-content">
                <input type="hidden" id="edit-product-id" value="${product.id}">
                <input type="hidden" id="edit-existing-image" value="${product.image || ''}">
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Product Name *</label>
                        <input type="text" id="product-name" class="swal2-input" value="${product.name}" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>SKU</label>
                        <input type="text" id="product-sku" class="swal2-input" value="${product.sku || ''}">
                    </div>
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" id="product-brand" class="swal2-input" value="${product.brand || ''}">
                    </div>
                </div>
                
                ${categoriesHtml}
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Price ($) *</label>
                        <input type="number" id="product-price" class="swal2-input" step="0.01" value="${product.price}" required>
                    </div>
                    <div class="form-group">
                        <label>Original Price ($)</label>
                        <input type="number" id="product-original-price" class="swal2-input" step="0.01" value="${product.original_price || product.price}">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" id="product-stock" class="swal2-input" value="${product.stock_quantity}" required>
                    </div>
                    <div class="form-group">
                        <label>Min Stock Level</label>
                        <input type="number" id="product-min-stock" class="swal2-input" value="${product.min_stock_level || 5}">
                    </div>
                    <div class="form-group">
                        <label>Weight (kg)</label>
                        <input type="number" id="product-weight" class="swal2-input" step="0.01" value="${product.weight || ''}">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea id="product-description" class="swal2-textarea" rows="3">${product.description || ''}</textarea>
                </div>
                
                <div class="form-group full-width">
                    <label>Colors</label>
                    ${colorsHtml}
                </div>
                
                <div class="form-group full-width">
                    <label>Sizes</label>
                    ${sizesHtml}
                </div>
                
                ${product.image ? `
                <div class="form-group full-width">
                    <label>Current Image</label>
                    <img src="../${product.image}" style="max-width: 150px; border-radius: 8px;">
                </div>
                ` : ''}
                
                <div class="form-group full-width">
                    <label>Change Primary Image</label>
                    <input type="file" id="product-image" class="swal2-file" accept="image/*">
                </div>
                
                ${product.images && product.images.length > 0 ? `
                <div class="form-group full-width">
                    <label>Additional Images</label>
                    <div class="image-preview-container">
                        ${product.images.map(img => `
                            <div class="image-preview-item">
                                <img src="../${img.image_path}">
                                <button class="delete-image-btn" onclick="deleteProductImage(${img.id})" type="button">×</button>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                
                <div class="form-group full-width">
                    <label>Add More Images</label>
                    <input type="file" id="product-additional-images" class="swal2-file" accept="image/*" multiple>
                </div>
                
                <div class="form-group full-width">
                    <label>Status</label>
                    <select id="product-status" class="swal2-select">
                        <option value="1" ${product.is_active == 1 ? 'selected' : ''}>Active</option>
                        <option value="0" ${product.is_active == 0 ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
            </div>
        `,
        width: '900px',
        showCancelButton: true,
        confirmButtonText: 'Update Product',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel'
        },
        didOpen: () => {
            setupCategoryListeners();
            
            // Pre-select category
            if (product.category_path) {
                preselectCategories(product.category_path);
            }
            
            // Pre-select colors
            if (product.colors) {
                product.colors.forEach(color => {
                    const checkbox = document.getElementById(`color-${color.id}`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            // Pre-select sizes
            if (product.sizes) {
                product.sizes.forEach(size => {
                    const checkbox = document.getElementById(`size-${size.id}`);
                    if (checkbox) checkbox.checked = true;
                });
            }
        },
        preConfirm: () => {
            return updateProduct();
        }
    });
}

// Preselect categories
async function preselectCategories(categoryPath) {
    if (categoryPath.length >= 1) {
        const level1 = document.getElementById('category-level-1');
        if (level1) level1.value = categoryPath[0].id;
    }
    
    if (categoryPath.length >= 2) {
        const subcats = await loadSubcategories(categoryPath[0].id);
        const level2Container = document.getElementById('category-level-2-container');
        level2Container.innerHTML = `
            <label>Subcategory (Level 2)</label>
            <select id="category-level-2" class="swal2-select category-level active">
                <option value="">Select Subcategory</option>
                ${subcats.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
            </select>
        `;
        document.getElementById('category-level-2').value = categoryPath[1].id;
        
        // Setup level 2 listener for level 3
        document.getElementById('category-level-2')?.addEventListener('change', async function() {
            const parentId2 = this.value;
            const level3Container = document.getElementById('category-level-3-container');
            level3Container.innerHTML = '';
            
            if (!parentId2) return;
            
            const subcats2 = await loadSubcategories(parentId2);
            if (subcats2.length > 0) {
                level3Container.innerHTML = `
                    <label>Subcategory (Level 3)</label>
                    <select id="category-level-3" class="swal2-select category-level active">
                        <option value="">Select Subcategory</option>
                        ${subcats2.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
                    </select>
                `;
            }
        });
        
        if (categoryPath.length >= 3) {
            const subcats2 = await loadSubcategories(categoryPath[1].id);
            const level3Container = document.getElementById('category-level-3-container');
            level3Container.innerHTML = `
                <label>Subcategory (Level 3)</label>
                <select id="category-level-3" class="swal2-select category-level active">
                    <option value="">Select Subcategory</option>
                    ${subcats2.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
                </select>
            `;
            document.getElementById('category-level-3').value = categoryPath[2].id;
        }
    }
}

// Update product
async function updateProduct() {
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'update_product');
    formData.append('product_id', document.getElementById('edit-product-id').value);
    formData.append('existing_image', document.getElementById('edit-existing-image').value);
    formData.append('name', document.getElementById('product-name').value);
    formData.append('sku', document.getElementById('product-sku').value);
    formData.append('brand', document.getElementById('product-brand').value);
    formData.append('price', document.getElementById('product-price').value);
    formData.append('original_price', document.getElementById('product-original-price').value || document.getElementById('product-price').value);
    formData.append('stock_quantity', document.getElementById('product-stock').value);
    formData.append('min_stock_level', document.getElementById('product-min-stock').value || 5);
    formData.append('weight', document.getElementById('product-weight').value);
    formData.append('description', document.getElementById('product-description').value);
    formData.append('is_active', document.getElementById('product-status').value);
    
    // Get selected category
    const category3 = document.getElementById('category-level-3')?.value;
    const category2 = document.getElementById('category-level-2')?.value;
    const category1 = document.getElementById('category-level-1')?.value;
    const categoryId = category3 || category2 || category1;
    
    if (!categoryId) {
        Swal.showValidationMessage('Please select a category');
        return false;
    }
    formData.append('category_id', categoryId);
    
    // Get selected colors
    document.querySelectorAll('input[name="colors[]"]:checked').forEach(cb => {
        formData.append('colors[]', cb.value);
    });
    
    // Get selected sizes
    document.querySelectorAll('input[name="sizes[]"]:checked').forEach(cb => {
        formData.append('sizes[]', cb.value);
    });
    
    // Get primary image
    const imageFile = document.getElementById('product-image').files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }
    
    // Get additional images
    const additionalImages = document.getElementById('product-additional-images').files;
    for (let i = 0; i < additionalImages.length; i++) {
        formData.append('additional_images[]', additionalImages[i]);
    }
    
    try {
        const response = await fetch('products.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            await Swal.fire('Success', data.message, 'success');
            location.reload();
        } else {
            Swal.showValidationMessage(data.message);
            return false;
        }
    } catch (error) {
        Swal.showValidationMessage('Error: ' + error.message);
        return false;
    }
}

// Delete product image
async function deleteProductImage(imageId) {
    const result = await Swal.fire({
        title: 'Delete Image?',
        text: 'This action cannot be undone',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel'
        }
    });
    
    if (!result.isConfirmed) return;
    
    try {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'delete_product_image');
        formData.append('image_id', imageId);
        
        const response = await fetch('products.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            Swal.fire('Deleted!', data.message, 'success');
            // Remove the image preview element
            document.querySelector(`[onclick="deleteProductImage(${imageId})"]`)?.closest('.image-preview-item')?.remove();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to delete image: ' + error.message, 'error');
    }
}

// Delete product
async function deleteProduct(id) {
    const result = await Swal.fire({
        title: 'Delete Product?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel'
        }
    });
    
    if (!result.isConfirmed) return;
    
    try {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'delete_product');
        formData.append('product_id', id);
        
        const response = await fetch('products.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            await Swal.fire('Deleted!', data.message, 'success');
            location.reload();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to delete product: ' + error.message, 'error');
    }
}

// Bulk actions
async function showBulkActions() {
    const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
    if (checkedBoxes.length === 0) {
        Swal.fire('No Selection', 'Please select products to perform bulk actions', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Bulk Actions',
        html: `
            <div style="text-align: left; padding: 20px;">
                <p style="margin-bottom: 15px;"><strong>${checkedBoxes.length}</strong> products selected</p>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Select Action:</label>
                <select id="bulk-action-type" class="swal2-select" style="width: 100%;">
                    <option value="">-- Choose Action --</option>
                    <option value="activate">Activate Products</option>
                    <option value="deactivate">Deactivate Products</option>
                    <option value="delete">Delete Products</option>
                </select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Apply',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel'
        },
        preConfirm: () => {
            const action = document.getElementById('bulk-action-type').value;
            if (!action) {
                Swal.showValidationMessage('Please select an action');
                return false;
            }
            return action;
        }
    });
    
    if (result.isConfirmed) {
        await executeBulkAction(result.value, checkedBoxes);
    }
}

// Execute bulk action
async function executeBulkAction(action, checkedBoxes) {
    // Confirm if deleting
    if (action === 'delete') {
        const confirmResult = await Swal.fire({
            title: 'Are you sure?',
            text: `This will permanently delete ${checkedBoxes.length} products!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete them!',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'swal-confirm',
                cancelButton: 'swal-cancel'
            }
        });
        
        if (!confirmResult.isConfirmed) return;
    }
    
    try {
        const productIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'bulk_action');
        formData.append('bulk_action_type', action);
        formData.append('product_ids', JSON.stringify(productIds));
        
        const response = await fetch('products.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            await Swal.fire('Success!', data.message, 'success');
            location.reload();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to perform bulk action: ' + error.message, 'error');
    }
}

// Export products
async function exportProducts() {
    try {
        Swal.fire({
            title: 'Export Products',
            html: `
                <div style="text-align: left; padding: 20px;">
                    <p style="margin-bottom: 15px;">Choose export format:</p>
                    <select id="export-format" class="swal2-select" style="width: 100%;">
                        <option value="csv">CSV (Excel Compatible)</option>
                        <option value="json">JSON</option>
                        <option value="pdf">PDF Report</option>
                    </select>
                    <p style="margin-top: 15px; font-size: 0.9rem; color: #666;">
                        This will export all filtered products based on your current filter settings.
                    </p>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Export',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'swal-confirm',
                cancelButton: 'swal-cancel'
            },
            preConfirm: () => {
                const format = document.getElementById('export-format').value;
                return format;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                performExport(result.value);
            }
        });
    } catch (error) {
        Swal.fire('Error', 'Failed to show export dialog: ' + error.message, 'error');
    }
}

// Perform export
function performExport(format) {
    // Build URL with current filters
    const params = new URLSearchParams(currentFilters);
    params.append('export', format);
    
    // Create a temporary link and trigger download
    const url = `export_products.php?${params.toString()}`;
    
    Swal.fire({
        title: 'Exporting...',
        html: 'Please wait while we prepare your export file.',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Create invisible iframe to trigger download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);
    
    // Close loading after 2 seconds
    setTimeout(() => {
        Swal.close();
        Swal.fire('Success!', 'Your export has been downloaded', 'success');
        document.body.removeChild(iframe);
    }, 2000);
}

// Confirm logout
function confirmLogout(event) {
    event.preventDefault();
    
    Swal.fire({
        title: 'Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../logout.php';
        }
    });
}

// Helper function to format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Helper function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(date);
}

// Helper function to truncate text
function truncateText(text, maxLength) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

// Initialize tooltips (if using Bootstrap or similar)
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-toggle="tooltip"]');
    tooltipElements.forEach(element => {
        // Initialize tooltip if library is available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            new bootstrap.Tooltip(element);
        }
    });
}

// Call initialize tooltips on page load
document.addEventListener('DOMContentLoaded', initializeTooltips);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K: Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('search-filter')?.focus();
    }
    
    // Ctrl/Cmd + N: New product
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        showAddModal();
    }
    
    // Escape: Close any open modals (SweetAlert2 handles this automatically)
});

// Auto-save draft functionality (optional enhancement)
let draftTimer = null;
function saveDraft(formData) {
    clearTimeout(draftTimer);
    draftTimer = setTimeout(() => {
        localStorage.setItem('product_draft', JSON.stringify(formData));
        console.log('Draft saved');
    }, 1000);
}

function loadDraft() {
    const draft = localStorage.getItem('product_draft');
    if (draft) {
        return JSON.parse(draft);
    }
    return null;
}

function clearDraft() {
    localStorage.removeItem('product_draft');
}

// Image preview functionality
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Validation helpers
function validateProductForm() {
    const name = document.getElementById('product-name')?.value.trim();
    const price = parseFloat(document.getElementById('product-price')?.value);
    const stock = parseInt(document.getElementById('product-stock')?.value);
    
    if (!name) {
        Swal.showValidationMessage('Product name is required');
        return false;
    }
    
    if (isNaN(price) || price <= 0) {
        Swal.showValidationMessage('Valid price is required');
        return false;
    }
    
    if (isNaN(stock) || stock < 0) {
        Swal.showValidationMessage('Valid stock quantity is required');
        return false;
    }
    
    return true;
}

// Console log for debugging
console.log('Products management script loaded successfully');
console.log('Available functions:', {
    showAddModal,
    editProduct,
    deleteProduct,
    showBulkActions,
    exportProducts,
    applyFilters,
    loadPage
});