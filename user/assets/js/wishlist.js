let wishlistData = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadWishlist();
    initSearch();
    initSort();
});

// Load Wishlist
async function loadWishlist() {
    try {
        const response = await fetch('../ajax.php?action=get_wishlist');
        const data = await response.json();

        console.log('Full wishlist response:', data); // Check the complete response
        
        if (data.success && data.data && data.data.length > 0) {
            console.log('First wishlist item:', data.data[0]); // Check first item structure
            wishlistData = data.data;
            displayWishlist(wishlistData);
            updateWishlistCount(wishlistData.length);
        } else {
            showEmptyWishlist();
        }
    } catch (error) {
        console.error('Error loading wishlist:', error);
        showError();
    }
}

function initAnimations() {
    // wishlist-header 
    gsap.from('.wishlist-header', {
        duration: 0.8,
        y: -50,
        opacity: 0,
        ease: 'power3.out'
    });

    // Animate content cards
    gsap.from('.filter-section', {
        duration: 0.6,
        x: -30,
        opacity: 0,
        stagger: 0.15,
        ease: 'power2.out',
        delay: 0.4
    });
}

// Display Wishlist
// Display Wishlist
function displayWishlist(items) {
    const grid = document.getElementById('wishlistGrid');
    
    const html = items.map(item => {
        // Safely parse numeric values
        const price = parseFloat(item.price) || 0;
        const originalPrice = parseFloat(item.original_price) || 0;
        const rating = parseFloat(item.rating) || 4.5;
        const reviewCount = parseInt(item.review_count) || 0;
        const stockQuantity = parseInt(item.stock_quantity) || 0;
        
        const discount = originalPrice > 0 ? Math.round(((originalPrice - price) / originalPrice) * 100) : 0;
        const isInStock = stockQuantity > 0;
        
        // Handle description - create a short version if full description exists
        let description = item.short_description || item.description || 'No description available';
        
        // If description is long, truncate it
        if (description.length > 100) {
            description = description.substring(0, 100) + '...';
        }
        
        // Ensure product image path
        let imagePath = item.image || 'assets/img/placeholder.jpg';
        if (!imagePath.startsWith('http') && !imagePath.startsWith('uploads/')) {
            imagePath = imagePath.replace(/^\.\.\//, '');
        }
        
        return `
            <div class="col-lg-4 col-md-6 mb-4 wishlist-item" data-id="${item.product_id}" data-price="${price}" data-name="${escapeHtml(item.name)}">
                <div class="wishlist-card">
                    <div class="product-image-wrapper">
                        <img src="../${imagePath}" alt="${escapeHtml(item.name)}" class="product-image" onerror="this.src='../assets/img/placeholder.jpg'">
                        <button class="remove-btn" onclick="removeFromWishlist(${item.product_id})">
                            <i class="fas fa-times"></i>
                        </button>
                        <span class="stock-badge ${isInStock ? 'in-stock' : 'out-of-stock'}">
                            ${isInStock ? 'In Stock' : 'Out of Stock'}
                        </span>
                    </div>
                    
                    <div class="product-details">
                        <h5 class="product-name">${escapeHtml(item.name)}</h5>
                        <p class="product-description">${escapeHtml(description)}</p>
                        
                        <div class="product-rating">
                            <div class="rating-stars">
                                ${getRatingStars(rating)}
                            </div>
                            <span class="rating-text">${rating.toFixed(1)} (${reviewCount} reviews)</span>
                        </div>
                        
                        <div class="mb-3">
                            <span class="product-price">$${price.toFixed(2)}</span>
                            ${originalPrice > 0 ? `
                                <span class="original-price">$${originalPrice.toFixed(2)}</span>
                                <span class="discount-badge">${discount}% OFF</span>
                            ` : ''}
                        </div>
                        
                        <div class="wishlist-actions">
                            <button class="btn btn-add-cart" onclick="addToCart(${item.product_id}, '${escapeHtml(item.name)}')" ${!isInStock ? 'disabled' : ''}>
                                <i class="fas fa-shopping-cart me-2"></i>${isInStock ? 'Add to Cart' : 'Out of Stock'}
                            </button>
                            <button class="btn btn-view" onclick="viewProduct(${item.product_id})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    grid.innerHTML = html;
    
    // Animate cards
    gsap.from('.wishlist-card', {
        duration: 0.9,
        opacity: 0,
        y: 5000,
        stagger: 0.1,
        ease: 'power2.out',
        delay: -0.5
    });
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Get Rating Stars
function getRatingStars(rating) {
    const ratingNum = parseFloat(rating) || 0;
    const fullStars = Math.floor(ratingNum);
    const halfStar = (ratingNum % 1) >= 0.5;
    const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
    
    let stars = '';
    for (let i = 0; i < fullStars; i++) stars += '<i class="fas fa-star"></i>';
    if (halfStar) stars += '<i class="fas fa-star-half-alt"></i>';
    for (let i = 0; i < emptyStars; i++) stars += '<i class="far fa-star"></i>';
    
    return stars;
}

// Remove from Wishlist
async function removeFromWishlist(productId) {
    if (!confirm('Remove this item from your wishlist?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove_from_wishlist');
    formData.append('product_id', productId);
    
    try {
        const response = await fetch('../ajax.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Animate removal
            const card = document.querySelector(`[data-id="${productId}"]`);
            if (card) {
                gsap.to(card, {
                    duration: 0.4,
                    scale: 0,
                    opacity: 0,
                    ease: 'back.in(1.7)',
                    onComplete: () => {
                        card.remove();
                        // Reload to update count
                        loadWishlist();
                    }
                });
            }
            
            showNotification('Item removed from wishlist', 'success');
        } else {
            showNotification(data.message || 'Error removing item', 'error');
        }
    } catch (error) {
        console.error('Error removing item:', error);
        showNotification('Error removing item', 'error');
    }
}

// Add to Cart
async function addToCart(productId, productName) {
    const formData = new FormData();
    formData.append('action', 'add_to_cart');
    formData.append('product_id', productId);
    formData.append('quantity', 1);
    
    try {
        const response = await fetch('../ajax.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`${productName} added to cart!`, 'success');
        } else {
            showNotification(data.message || 'Error adding to cart', 'error');
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showNotification('Error adding to cart', 'error');
    }
}

// View Product
function viewProduct(productId) {
    window.location.href = `../product-details.php?id=${productId}`;
}

// Update Wishlist Count
function updateWishlistCount(count) {
    const countElement = document.getElementById('wishlistCount');
    if (countElement) {
        countElement.textContent = `You have ${count} item${count !== 1 ? 's' : ''} in your wishlist`;
    }
    
    // Update sidebar badge
    const badge = document.querySelector('.sidebar-menu .active .badge');
    if (badge) {
        badge.textContent = count;
        if (count === 0) {
            badge.style.display = 'none';
        }
    }
}

// Show Empty Wishlist
function showEmptyWishlist() {
    const grid = document.getElementById('wishlistGrid');
    grid.innerHTML = `
        <div class="col-12">
            <div class="empty-wishlist">
                <i class="fas fa-heart-broken empty-icon"></i>
                <h2 class="empty-title">Your Wishlist is Empty</h2>
                <p class="empty-text">Start adding items to your wishlist by clicking the heart icon on products you love!</p>
                <button class="btn btn-primary btn-lg" onclick="window.location.href='../products.php'">
                    <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                </button>
            </div>
        </div>
    `;
    updateWishlistCount(0);
    
    // Animate empty state
    gsap.from('.empty-wishlist', {
        duration: 0.6,
        scale: 0.8,
        opacity: 0,
        ease: 'back.out(1.7)'
    });
}

// Show Error
function showError() {
    const grid = document.getElementById('wishlistGrid');
    grid.innerHTML = `
        <div class="col-12">
            <div class="empty-wishlist">
                <i class="fas fa-exclamation-triangle empty-icon text-danger"></i>
                <h2 class="empty-title">Error Loading Wishlist</h2>
                <p class="empty-text">Unable to load your wishlist. Please try again later.</p>
                <button class="btn btn-primary" onclick="loadWishlist()">
                    <i class="fas fa-redo me-2"></i>Retry
                </button>
            </div>
        </div>
    `;
    
    // Animate error state
    gsap.from('.empty-wishlist', {
        duration: 0.6,
        scale: 0.8,
        opacity: 0,
        ease: 'back.out(1.7)'
    });
}

// Initialize Search
function initSearch() {
    const searchInput = document.getElementById('searchInput');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.wishlist-item');
            
            let visibleCount = 0;
            items.forEach(item => {
                const name = (item.dataset.name || '').toLowerCase();
                if (name.includes(query)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show message if no results
            if (visibleCount === 0 && query.length > 0) {
                showNoResults(query);
            }
        });
    }
}

// Show No Results
function showNoResults(query) {
    const grid = document.getElementById('wishlistGrid');
    const existingMessage = grid.querySelector('.no-results');
    
    if (!existingMessage) {
        const message = document.createElement('div');
        message.className = 'col-12 no-results';
        message.innerHTML = `
            <div class="empty-wishlist">
                <i class="fas fa-search empty-icon"></i>
                <h3 class="empty-title">No results found</h3>
                <p class="empty-text">No items match "${escapeHtml(query)}"</p>
            </div>
        `;
        grid.appendChild(message);
    }
}

// Initialize Sort
function initSort() {
    const sortSelect = document.getElementById('sortSelect');
    
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const sortBy = this.value;
            sortWishlist(sortBy);
        });
    }
}

// Sort Wishlist
function sortWishlist(sortBy) {
    let sorted = [...wishlistData];
    
    switch(sortBy) {
        case 'price_low':
            sorted.sort((a, b) => parseFloat(a.price || 0) - parseFloat(b.price || 0));
            break;
        case 'price_high':
            sorted.sort((a, b) => parseFloat(b.price || 0) - parseFloat(a.price || 0));
            break;
        case 'name':
            sorted.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            break;
        case 'recent':
        default:
            // Already in recent order
            break;
    }
    
    displayWishlist(sorted);
}

// Show Notification
function showNotification(message, type) {
    const notification = document.createElement('div');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    notification.innerHTML = `
        <div class="alert ${alertClass} position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 300px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <i class="fas ${icon} me-2"></i>
            ${escapeHtml(message)}
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    gsap.from(notification.firstElementChild, {
        duration: 0.5,
        x: 100,
        opacity: 0,
        ease: 'back.out(1.7)'
    });
    
    // Remove after 3 seconds
    setTimeout(() => {
        gsap.to(notification.firstElementChild, {
            duration: 0.3,
            x: 100,
            opacity: 0,
            ease: 'power2.in',
            onComplete: () => notification.remove()
        });
    }, 3000);
}