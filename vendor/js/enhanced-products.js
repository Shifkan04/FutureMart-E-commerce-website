// Global variables
let allColors = [];
let allSizes = [];
let selectedProductImages = [];

// Load initial data
document.addEventListener('DOMContentLoaded', async function() {
    await loadColors();
    await loadSizes();
    
    const currentPage = window.location.pathname.split('/').pop() || 'products.php';
    const navItems = document.querySelectorAll(".nav-item");
    navItems.forEach((navItem) => {
        const link = navItem.querySelector('a');
        if (link && link.getAttribute('href') === currentPage) {
            navItems.forEach((item) => item.classList.remove("active"));
            navItem.classList.add("active");
        }
    });
});

// Utility function for AJAX requests
async function postData(action, formData, isFormData = false) {
    const fetchOptions = {
        method: 'POST',
        body: isFormData ? formData : new URLSearchParams(formData),
    };
    
    if (isFormData) {
        formData.append('ajax', '1');
        formData.append('action', action);
    } else {
        fetchOptions.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
        fetchOptions.body += '&ajax=1&action=' + action;
    }

    try {
        const response = await fetch('products.php', fetchOptions);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    } catch (error) {
        console.error('Fetch error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Request Failed',
            text: 'Could not connect to the server or process the request.'
        });
        return { success: false, message: 'Request Failed.' };
    }
}

// Load colors from database
async function loadColors() {
    const data = await postData('get_colors', '');
    if (data.success) {
        allColors = data.colors;
    }
}

// Load sizes from database
async function loadSizes() {
    const data = await postData('get_sizes', '');
    if (data.success) {
        allSizes = data.sizes;
    }
}

// Load subcategories based on parent
async function loadSubcategories(parentId, targetSelectId) {
    const data = await postData('get_subcategories', `parent_id=${parentId || ''}`);
    if (data.success && data.categories.length > 0) {
        return data.categories;
    }
    return [];
}

// Generate color checkbox HTML
function generateColorCheckboxes(selectedColorIds = []) {
    let html = '<div class="color-checkbox-grid">';
    allColors.forEach(color => {
        const isChecked = selectedColorIds.includes(color.id);
        html += `
            <div class="color-checkbox-item">
                <input type="checkbox" 
                       id="color_${color.id}" 
                       name="colors[]" 
                       value="${color.id}" 
                       ${isChecked ? 'checked' : ''}>
                <div class="color-swatch" style="background-color: ${color.hex_code}"></div>
                <label for="color_${color.id}">${color.name}</label>
            </div>
        `;
    });
    html += '</div>';
    return html;
}

// Generate size checkbox HTML
function generateSizeCheckboxes(selectedSizeIds = []) {
    let html = '<div class="size-checkbox-grid">';
    allSizes.forEach(size => {
        const isChecked = selectedSizeIds.includes(size.id);
        html += `
            <div class="size-checkbox-item">
                <input type="checkbox" 
                       id="size_${size.id}" 
                       name="sizes[]" 
                       value="${size.id}" 
                       ${isChecked ? 'checked' : ''}>
                <label for="size_${size.id}">${size.name}</label>
            </div>
        `;
    });
    html += '</div>';
    return html;
}

// Generate category cascade HTML
async function generateCategoryCascade(selectedPath = []) {
    let html = '<div class="category-cascade">';
    
    // Level 0 - Main Categories
    const mainCategories = await loadSubcategories(null);
    html += `
        <div class="category-level active" data-level="0">
            <label for="category_level_0">Main Category *</label>
            <select class="form-select category-select" 
                    id="category_level_0" 
                    data-level="0" 
                    onchange="handleCategoryChange(this)">
                <option value="">Select Main Category</option>
                ${mainCategories.map(cat => 
                    `<option value="${cat.id}" ${selectedPath[0] && selectedPath[0].id == cat.id ? 'selected' : ''}>${cat.name}</option>`
                ).join('')}
            </select>
        </div>
    `;
    
    // Load subsequent levels if there's a selected path
    for (let i = 0; i < selectedPath.length; i++) {
        const subcats = await loadSubcategories(selectedPath[i].id);
        if (subcats.length > 0) {
            html += `
                <div class="category-level active" data-level="${i + 1}">
                    <label for="category_level_${i + 1}">Subcategory ${i + 1}</label>
                    <select class="form-select category-select" 
                            id="category_level_${i + 1}" 
                            data-level="${i + 1}" 
                            onchange="handleCategoryChange(this)">
                        <option value="">Select Subcategory</option>
                        ${subcats.map(cat => 
                            `<option value="${cat.id}" ${selectedPath[i + 1] && selectedPath[i + 1].id == cat.id ? 'selected' : ''}>${cat.name}</option>`
                        ).join('')}
                    </select>
                </div>
            `;
        }
    }
    
    html += '</div>';
    html += '<input type="hidden" name="category_id" id="final_category_id" value="' + (selectedPath.length > 0 ? selectedPath[selectedPath.length - 1].id : '') + '">';
    return html;
}

// Handle category selection change
async function handleCategoryChange(selectElement) {
    const level = parseInt(selectElement.dataset.level);
    const selectedValue = selectElement.value;
    const categoryContainer = selectElement.closest('.category-cascade');
    
    // Remove all subsequent level selects
    const subsequentLevels = categoryContainer.querySelectorAll(`.category-level[data-level="${level + 1}"], .category-level[data-level="${level + 2}"], .category-level[data-level="${level + 3}"]`);
    subsequentLevels.forEach(el => el.remove());
    
    // Update final category ID
    document.getElementById('final_category_id').value = selectedValue;
    
    if (selectedValue) {
        // Load subcategories for the next level
        const subcats = await loadSubcategories(selectedValue);
        if (subcats.length > 0) {
            const nextLevel = level + 1;
            const newSelectHtml = `
                <div class="category-level active" data-level="${nextLevel}">
                    <label for="category_level_${nextLevel}">Subcategory ${nextLevel}</label>
                    <select class="form-select category-select" 
                            id="category_level_${nextLevel}" 
                            data-level="${nextLevel}" 
                            onchange="handleCategoryChange(this)">
                        <option value="">Select Subcategory</option>
                        ${subcats.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
                    </select>
                </div>
            `;
            categoryContainer.insertAdjacentHTML('beforeend', newSelectHtml);
        }
    }
}

// Handle multiple image preview
function handleImagePreview(input, previewContainerId) {
    const container = document.getElementById(previewContainerId);
    container.innerHTML = '';
    
    if (input.files) {
        Array.from(input.files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'image-preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="delete-image-btn" onclick="removeImagePreview(this, ${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
}

// Remove image preview
function removeImagePreview(btn, index) {
    const previewItem = btn.closest('.image-preview-item');
    previewItem.remove();
    
    // Remove file from input (create new FileList without this file)
    const input = document.getElementById('swal_additional_images');
    const dt = new DataTransfer();
    const files = input.files;
    
    for (let i = 0; i < files.length; i++) {
        if (i !== index) {
            dt.items.add(files[i]);
        }
    }
    input.files = dt.files;
}

// Delete existing product image
async function deleteProductImage(imageId, element) {
    const result = await Swal.fire({
        title: 'Delete Image?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel',
        }
    });
    
    if (result.isConfirmed) {
        const data = await postData('delete_product_image', `image_id=${imageId}`);
        if (data.success) {
            element.closest('.image-preview-item').remove();
            Swal.fire('Deleted!', data.message, 'success');
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    }
}

// Show Add Product Modal
async function showAddProductModal() {
    const categoryHtml = await generateCategoryCascade();
    const colorHtml = generateColorCheckboxes();
    const sizeHtml = generateSizeCheckboxes();
    
    const formHtml = `
        <form id="swalAddProductForm" enctype="multipart/form-data">
            <div id="swal-form-content">
                <div class="form-row">
                    <div class="form-group">
                        <label for="swal_name">Product Name *</label>
                        <input type="text" class="form-control" name="name" id="swal_name" required>
                    </div>
                    <div class="form-group">
                        <label for="swal_sku">SKU (Optional)</label>
                        <input type="text" class="form-control" name="sku" id="swal_sku" placeholder="Auto-generated">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    ${categoryHtml}
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="swal_price">Price ($) *</label>
                        <input type="number" class="form-control" name="price" id="swal_price" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="swal_stock_quantity">Stock Quantity *</label>
                        <input type="number" class="form-control" name="stock_quantity" id="swal_stock_quantity" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="swal_min_stock_level">Min Stock Level</label>
                        <input type="number" class="form-control" name="min_stock_level" id="swal_min_stock_level" value="5">
                    </div>
                    <div class="form-group">
                        <label for="swal_weight">Weight (kg)</label>
                        <input type="number" class="form-control" name="weight" id="swal_weight" step="0.01">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label>Colors</label>
                    ${colorHtml}
                </div>
                
                <div class="form-group full-width">
                    <label>Sizes</label>
                    ${sizeHtml}
                </div>
                
                <div class="form-group full-width">
                    <label for="swal_image">Primary Product Image</label>
                    <input type="file" class="swal2-file" name="image" id="swal_image" accept="image/*">
                </div>
                
                <div class="form-group full-width">
                    <label for="swal_additional_images">Additional Images (Multiple)</label>
                    <input type="file" class="swal2-file" name="additional_images[]" id="swal_additional_images" 
                           accept="image/*" multiple onchange="handleImagePreview(this, 'add_image_preview')">
                    <div id="add_image_preview" class="image-preview-container"></div>
                </div>
                
                <div class="form-group full-width">
                    <label for="swal_description">Description</label>
                    <textarea class="form-control" name="description" id="swal_description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="swal_is_active">Status</label>
                    <select class="form-select" name="is_active" id="swal_is_active">
                        <option value="1" selected>Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </form>
    `;

    Swal.fire({
        title: 'Add New Product',
        html: formHtml,
        width: '900px',
        showCancelButton: true,
        confirmButtonText: 'Save Product',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel',
        },
        preConfirm: () => {
            const form = document.getElementById('swalAddProductForm');
            
            // Validate required fields
            const requiredFields = ['name', 'price', 'stock_quantity'];
            for (const fieldName of requiredFields) {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (!field.value) {
                    Swal.showValidationMessage(`Please fill in: ${field.previousElementSibling.textContent.replace('*', '').trim()}`);
                    return false;
                }
            }
            
            // Validate category selection
            const categoryId = document.getElementById('final_category_id').value;
            if (!categoryId) {
                Swal.showValidationMessage('Please select a category');
                return false;
            }

            const formData = new FormData(form);
            
            // Add selected colors
            const selectedColors = Array.from(form.querySelectorAll('input[name="colors[]"]:checked')).map(cb => cb.value);
            formData.delete('colors[]');
            selectedColors.forEach(colorId => formData.append('colors[]', colorId));
            
            // Add selected sizes
            const selectedSizes = Array.from(form.querySelectorAll('input[name="sizes[]"]:checked')).map(cb => cb.value);
            formData.delete('sizes[]');
            selectedSizes.forEach(sizeId => formData.append('sizes[]', sizeId));

            return postData('add_product', formData, true);
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            showSwalResult(result.value, 'Product Added!', 'Failed to Add Product');
        }
    });
}

// Show Edit Product Modal
async function showEditProductModal(productId) {
    Swal.fire({
        title: 'Loading Product...',
        text: 'Please wait.',
        didOpen: () => {
            Swal.showLoading();
        },
        allowOutsideClick: false,
    });

    const data = await postData('get_product', 'product_id=' + productId);
    Swal.close();

    if (!data.success) {
        Swal.fire('Error', data.message, 'error');
        return;
    }

    const product = data.product;
    const selectedColorIds = product.colors.map(c => c.id);
    const selectedSizeIds = product.sizes.map(s => s.id);
    const categoryPath = product.category_path || [];
    
    const categoryHtml = await generateCategoryCascade(categoryPath);
    const colorHtml = generateColorCheckboxes(selectedColorIds);
    const sizeHtml = generateSizeCheckboxes(selectedSizeIds);
    
    const currentImageUrl = product.image ? '../' + product.image : '';
    
    // Generate existing images HTML
    let existingImagesHtml = '';
    if (product.images && product.images.length > 0) {
        existingImagesHtml = '<div class="image-preview-container">';
        product.images.forEach(img => {
            existingImagesHtml += `
                <div class="image-preview-item">
                    <img src="../${img.image_path}" alt="Product Image">
                    <button type="button" class="delete-image-btn" onclick="deleteProductImage(${img.id}, this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });
        existingImagesHtml += '</div>';
    }

    const formHtml = `
        <form id="swalEditProductForm" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="${product.id}">
            <input type="hidden" name="existing_image" value="${product.image || ''}" id="edit_existing_image">
            <div id="swal-form-content">
                <div class="form-row">
                    <div class="form-group">
                        <label for="swal_edit_name">Product Name *</label>
                        <input type="text" class="form-control" name="name" id="swal_edit_name" value="${product.name}" required>
                    </div>
                    <div class="form-group">
                        <label for="swal_edit_sku">SKU</label>
                        <input type="text" class="form-control" name="sku" id="swal_edit_sku" value="${product.sku || 'N/A'}" readonly>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    ${categoryHtml}
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="swal_edit_price">Price ($) *</label>
                        <input type="number" class="form-control" name="price" id="swal_edit_price" step="0.01" value="${product.price}" required>
                    </div>
                    <div class="form-group">
                        <label for="swal_edit_stock_quantity">Stock Quantity *</label>
                        <input type="number" class="form-control" name="stock_quantity" id="swal_edit_stock_quantity" value="${product.stock_quantity}" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="swal_edit_min_stock_level">Min Stock Level</label>
                        <input type="number" class="form-control" name="min_stock_level" id="swal_edit_min_stock_level" value="${product.min_stock_level}">
                    </div>
                    <div class="form-group">
                        <label for="swal_edit_weight">Weight (kg)</label>
                        <input type="number" class="form-control" name="weight" id="swal_edit_weight" step="0.01" value="${product.weight || ''}">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label>Colors</label>
                    ${colorHtml}
                </div>
                
                <div class="form-group full-width">
                    <label>Sizes</label>
                    ${sizeHtml}
                </div>
                
                <div class="form-group full-width">
                    <label>Current Primary Image</label>
                    <div style="margin-bottom: 10px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px;">
                        ${currentImageUrl ? `<img src="${currentImageUrl}" style="max-width: 100px; max-height: 100px; border-radius: 5px;">` : 'No Image'}
                    </div>
                    <label for="swal_edit_image">Upload New Primary Image (Optional)</label>
                    <input type="file" class="swal2-file" name="image" id="swal_edit_image" accept="image/*">
                </div>
                
                <div class="form-group full-width">
                    <label>Existing Additional Images</label>
                    ${existingImagesHtml || '<p style="color: #94a3b8;">No additional images</p>'}
                </div>
                
                <div class="form-group full-width">
                    <label for="swal_edit_additional_images">Add More Images</label>
                    <input type="file" class="swal2-file" name="additional_images[]" id="swal_edit_additional_images" 
                           accept="image/*" multiple onchange="handleImagePreview(this, 'edit_image_preview')">
                    <div id="edit_image_preview" class="image-preview-container"></div>
                </div>
                
                <div class="form-group full-width">
                    <label for="swal_edit_description">Description</label>
                    <textarea class="form-control" name="description" id="swal_edit_description" rows="3">${product.description || ''}</textarea>
                </div>
                
                <div class="form-group">
                    <label for="swal_edit_is_active">Status</label>
                    <select class="form-select" name="is_active" id="swal_edit_is_active">
                        <option value="1" ${product.is_active == 1 ? 'selected' : ''}>Active</option>
                        <option value="0" ${product.is_active == 0 ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
            </div>
        </form>
    `;

    Swal.fire({
        title: 'Edit Product: ' + product.name,
        html: formHtml,
        width: '900px',
        showCancelButton: true,
        confirmButtonText: 'Update Product',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel',
        },
        preConfirm: () => {
            const form = document.getElementById('swalEditProductForm');
            
            // Validate required fields
            const requiredFields = ['name', 'price', 'stock_quantity'];
            for (const fieldName of requiredFields) {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (!field.value) {
                    Swal.showValidationMessage(`Please fill in: ${field.previousElementSibling.textContent.replace('*', '').trim()}`);
                    return false;
                }
            }
            
            // Validate category
            const categoryId = document.getElementById('final_category_id').value;
            if (!categoryId) {
                Swal.showValidationMessage('Please select a category');
                return false;
            }
            
            const formData = new FormData(form);
            
            // Add selected colors
            const selectedColors = Array.from(form.querySelectorAll('input[name="colors[]"]:checked')).map(cb => cb.value);
            formData.delete('colors[]');
            selectedColors.forEach(colorId => formData.append('colors[]', colorId));
            
            // Add selected sizes
            const selectedSizes = Array.from(form.querySelectorAll('input[name="sizes[]"]:checked')).map(cb => cb.value);
            formData.delete('sizes[]');
            selectedSizes.forEach(sizeId => formData.append('sizes[]', sizeId));
            
            return postData('update_product', formData, true);
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            showSwalResult(result.value, 'Product Updated!', 'Failed to Update Product');
        }
    });
}

// View Product Details
async function viewProduct(productId) {
    Swal.fire({
        title: 'Loading Details',
        text: 'Fetching product data...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const data = await postData('get_product', 'product_id=' + productId);
    
    if (!data.success) {
        Swal.fire('Error', data.message, 'error');
        return;
    }

    const product = data.product;
    const imageUrl = product.image ? '../' + product.image : '';
    const statusBadge = product.is_active == 1 ? '<span class="badge success">Active</span>' : '<span class="badge" style="background: rgba(148, 163, 184, 0.2); color: #64748b;">Inactive</span>';
    
    // Generate colors HTML
    let colorsHtml = 'None';
    if (product.colors && product.colors.length > 0) {
        colorsHtml = product.colors.map(c => 
            `<span class="badge" style="background: ${c.hex_code}; color: white; margin-right: 5px;">${c.name}</span>`
        ).join('');
    }
    
    // Generate sizes HTML
    let sizesHtml = 'None';
    if (product.sizes && product.sizes.length > 0) {
        sizesHtml = product.sizes.map(s => 
            `<span class="badge" style="background: rgba(73, 57, 113, 0.2); color: rgb(73, 57, 113); margin-right: 5px;">${s.name}</span>`
        ).join('');
    }
    
    // Generate additional images HTML
    let additionalImagesHtml = '';
    if (product.images && product.images.length > 0) {
        additionalImagesHtml = '<div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">';
        product.images.forEach(img => {
            additionalImagesHtml += `<img src="../${img.image_path}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0;">`;
        });
        additionalImagesHtml += '</div>';
    }
    
    const contentHtml = `
        <div style="max-width: 700px; margin: auto; padding: 10px;">
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1 1 150px; text-align: center;">
                    ${imageUrl ? `<img src="${imageUrl}" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 10px;">` : '<div style="width: 100%; height: 200px; background: #e2e8f0; border-radius: 10px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-image" style="font-size: 3rem; color: #94a3b8;"></i></div>'}
                </div>
                <div style="flex: 2 1 250px;">
                    <table style="width: 100%; font-size: 0.95rem;">
                        <tr><td style="padding: 5px 10px; font-weight: 600; width: 40%;">Product Name:</td><td style="padding: 5px 10px;">${product.name}</td></tr>
                        <tr><td style="padding: 5px 10px; font-weight: 600;">SKU:</td><td style="padding: 5px 10px;">${product.sku || 'N/A'}</td></tr>
                        <tr><td style="padding: 5px 10px; font-weight: 600;">Category:</td><td style="padding: 5px 10px;">${product.category_name || 'Uncategorized'}</td></tr>
                        <tr><td style="padding: 5px 10px; font-weight: 600;">Price:</td><td style="padding: 5px 10px;"><strong>$${parseFloat(product.price).toFixed(2)}</strong></td></tr>
                        <tr><td style="padding: 5px 10px; font-weight: 600;">Stock:</td><td style="padding: 5px 10px;">${product.stock_quantity} (Min: ${product.min_stock_level})</td></tr>
                        <tr><td style="padding: 5px 10px; font-weight: 600;">Weight:</td><td style="padding: 5px 10px;">${product.weight ? product.weight + ' kg' : 'N/A'}</td></tr>
                        <tr><td style="padding: 5px 10px; font-weight: 600;">Status:</td><td style="padding: 5px 10px;">${statusBadge}</td></tr>
                    </table>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <h4 style="font-size: 1rem; color: #484d53; margin-bottom: 5px;">Colors</h4>
                <div style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fcfcfc; margin-bottom: 15px;">
                    ${colorsHtml}
                </div>
                
                <h4 style="font-size: 1rem; color: #484d53; margin-bottom: 5px;">Sizes</h4>
                <div style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fcfcfc; margin-bottom: 15px;">
                    ${sizesHtml}
                </div>
                
                <h4 style="font-size: 1rem; color: #484d53; margin-bottom: 5px;">Description</h4>
                <p style="font-size: 0.9rem; color: #64748b; padding: 10px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fcfcfc; margin-bottom: 15px;">
                    ${product.description || 'No description provided.'}
                </p>
                
                ${additionalImagesHtml ? `
                    <h4 style="font-size: 1rem; color: #484d53; margin-bottom: 5px;">Additional Images</h4>
                    ${additionalImagesHtml}
                ` : ''}
            </div>
        </div>
    `;
    
    Swal.fire({
        title: 'Product Details',
        html: contentHtml,
        width: '750px',
        showCloseButton: true,
        showConfirmButton: true,
        confirmButtonText: 'Close',
        customClass: {
            confirmButton: 'swal-confirm',
        },
    });
}

// Delete Product
function deleteProduct(productId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this! This will permanently delete the product.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel',
        },
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            postData('delete_product', 'product_id=' + productId)
                .then(data => {
                    showSwalResult(data, 'Deleted!', 'Failed to Delete');
                });
        }
    });
}

// Show result with SweetAlert
function showSwalResult(data, successTitle = 'Success', errorTitle = 'Error') {
    Swal.fire({
        icon: data.success ? 'success' : 'error',
        title: data.success ? successTitle : errorTitle,
        text: data.message,
        customClass: {
            confirmButton: 'swal-confirm',
        }
    }).then(() => {
        if (data.success) {
            location.reload();
        }
    });
}

// Switch between table and grid view
function switchView(view) {
    if (view === 'table') {
        document.getElementById('tableView').style.display = 'block';
        document.getElementById('gridView').style.display = 'none';
        document.getElementById('tableViewBtn').classList.add('active');
        document.getElementById('gridViewBtn').classList.remove('active');
    } else {
        document.getElementById('tableView').style.display = 'none';
        document.getElementById('gridView').style.display = 'block';
        document.getElementById('tableViewBtn').classList.remove('active');
        document.getElementById('gridViewBtn').classList.add('active');
    }
}

// Confirm logout
function confirmLogout(e) {
    e.preventDefault();

    Swal.fire({
        title: 'Logout Confirmation',
        text: 'Are you sure you want to log out?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, log me out',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'swal-confirm',
            cancelButton: 'swal-cancel'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Logging out...',
                text: 'Please wait a moment',
                icon: 'info',
                showConfirmButton: false,
                timer: 1000,
                timerProgressBar: true,
                didClose: () => {
                    window.location.href = '../logout.php';
                }
            });
        }
    });
}

// Navigation active state
const navItems = document.querySelectorAll(".nav-item");
navItems.forEach((navItem) => {
    navItem.addEventListener("click", () => {
        navItems.forEach((item) => {
            item.classList.remove("active");
        });
        navItem.classList.add("active");
    });
});