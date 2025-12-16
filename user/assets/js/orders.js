let currentPage = 1;
const limit = 5;
let currentFilter = 'all';

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    loadOrders();
    initFilterTabs();
    initReviewModal();
});

// Initialize Filter Tabs
function initFilterTabs() {
    document.querySelectorAll('[data-filter]').forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();

            // Update active state
            document.querySelectorAll('[data-filter]').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Apply filter
            currentFilter = this.dataset.filter;
            currentPage = 1;
            loadOrders(1, false);
        });
    });
}

// Load Orders
async function loadOrders(page = 1, append = false) {
    const container = document.getElementById('ordersList');

    if (!append) {
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading your orders...</p>
            </div>
        `;
    }

    try {
        // Build URL with proper filter parameter
        const url = `../ajax.php?action=get_orders&page=${page}&limit=${limit}&filter=${currentFilter}`;
        console.log('Loading orders with URL:', url); // Debug
        console.log('Current filter:', currentFilter); // Debug
        
        const response = await fetch(url);
        const data = await response.json();
        
        console.log('Response data:', data); // Debug

        if (!data.success || !data.data || data.data.length === 0) {
            if (!append) {
                showEmptyState();
            }
            document.getElementById('loadMoreContainer').style.display = 'none';
            return;
        }

        const ordersHTML = data.data.map((order, index) => renderOrderCard(order, index + (page - 1) * limit)).join('');

        if (append) {
            container.innerHTML += ordersHTML;
        } else {
            container.innerHTML = ordersHTML;
        }

        // Show load more button if there might be more orders
        if (data.data.length === limit) {
            document.getElementById('loadMoreContainer').style.display = 'block';
        } else {
            document.getElementById('loadMoreContainer').style.display = 'none';
        }

    } catch (error) {
        console.error('Error loading orders:', error);
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle empty-icon"></i>
                <h3 class="empty-title">Error Loading Orders</h3>
                <p class="empty-text">Unable to load your orders. Please try again later.</p>
            </div>
        `;
    }
}

// Render Order Card
function renderOrderCard(order, index) {
    const status = order.status.toLowerCase();
    const total = parseFloat(order.total_amount).toFixed(2);
    const datePlaced = new Date(order.created_at).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });

    const itemsHTML = (order.items || []).map(item => {
        const colorInfo = item.color_name ? `<span class="text-muted">Color: ${item.color_name}</span>` : '';
        const sizeInfo = item.size_name ? `<span class="text-muted">Size: ${item.size_name}</span>` : '';
        const details = [colorInfo, sizeInfo].filter(Boolean).join(' | ');
        
        return `
        <div class="order-item">
            <img src="${item.image ? '../uploads/products/' + item.image : '../uploads/products/default.png'}"
                alt="${item.name}" 
                class="product-image" 
                onerror="this.src='assets/img/future mart logo.png'">
            <div class="product-details">
                <h6 class="product-name">${item.name}</h6>
                <p class="product-info">${details || 'Standard variant'}</p>
                <p class="product-info">Quantity: ${item.quantity}</p>
            </div>
            <div class="product-price">$${item.price}</div>
        </div>
        ${status === 'delivered' && item.product_id ? `
            <div class="review-section">
                <button class="btn btn-sm btn-outline-primary review-btn" onclick="openReviewModal(${item.product_id}, ${order.id}, '${item.name}')">
                    <i class="fas fa-star me-2"></i>Write Review
                </button>
                <div id="review-display-${item.product_id}-${order.id}"></div>
            </div>
        ` : ''}
    `;
    }).join('');

    const trackingHTML = (order.tracking || []).length > 0 ? 
        order.tracking.map(step => `
            <div class="tracking-step completed">
                <div class="tracking-icon"><i class="fas fa-check-circle"></i></div>
                <div class="tracking-info">
                    <div class="tracking-status">${capitalize(step.status)}</div>
                    <div class="tracking-time">${new Date(step.date_time).toLocaleString()}</div>
                </div>
            </div>
        `).join('') : 
        `<p class="text-muted">No tracking information available yet</p>`;

    return `
        <div class="order-card" data-status="${status}">
            <div class="order-header">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <h6 class="order-number">Order #${order.order_number}</h6>
                        <p class="order-date mb-0">Placed on ${datePlaced}</p>
                    </div>
                    <div class="col-md-2">
                        <span class="order-status status-${status}">${capitalize(status)}</span>
                    </div>
                    <div class="col-md-2">
                        <strong class="text-primary">$${total}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">${order.tracking_number || 'Tracking pending'}</small>
                    </div>
                    <div class="col-md-2 text-end">
                        <button class="toggle-details-btn" onclick="toggleOrderDetails(${index})">
                            <i class="fas fa-chevron-down" id="arrow-${index}"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="order-body" id="details-${index}">
                <div class="row">
                    <div class="col-lg-8">
                        <h6 class="mb-3"><i class="fas fa-box me-2"></i>Order Items</h6>
                        <div class="order-items">
                            ${itemsHTML}
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <h6 class="mb-3"><i class="fas fa-truck me-2"></i>Tracking Information</h6>
                        <div class="tracking-section">
                            <div class="tracking-timeline">
                                ${trackingHTML}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Toggle Order Details
function toggleOrderDetails(id) {
    const details = document.getElementById(`details-${id}`);
    const arrow = document.getElementById(`arrow-${id}`);
    const btn = arrow.parentElement;

    if (details.classList.contains('show')) {
        details.classList.remove('show');
        btn.classList.remove('active');
        arrow.style.transform = 'rotate(0deg)';
    } else {
        details.classList.add('show');
        btn.classList.add('active');
        arrow.style.transform = 'rotate(180deg)';
        
        // Load reviews for this order
        loadOrderReviews(id);
    }
}

// Load reviews for order items
async function loadOrderReviews(orderIndex) {
    const detailsDiv = document.getElementById(`details-${orderIndex}`);
    if (!detailsDiv) return;
    
    const orderCard = detailsDiv.closest('.order-card');
    const orderNumber = orderCard.querySelector('.order-number').textContent.replace('Order #', '');
    
    try {
        const response = await fetch(`../ajax.php?action=get_order_reviews&order_number=${orderNumber}`);
        const data = await response.json();
        
        if (data.success && data.reviews) {
            data.reviews.forEach(review => {
                const reviewDisplay = document.getElementById(`review-display-${review.product_id}-${review.order_id}`);
                if (reviewDisplay) {
                    reviewDisplay.innerHTML = `
                        <div class="submitted-review mt-2">
                            <div class="review-header">
                                <div class="review-rating">
                                    ${generateStars(review.rating)}
                                </div>
                                <small class="text-muted">${new Date(review.created_at).toLocaleDateString()}</small>
                            </div>
                            <p class="review-comment">${review.comment}</p>
                        </div>
                    `;
                }
            });
        }
    } catch (error) {
        console.error('Error loading reviews:', error);
    }
}

// Generate star rating HTML
function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star text-warning"></i>';
        } else {
            stars += '<i class="far fa-star text-warning"></i>';
        }
    }
    return stars;
}

// Load More Orders
document.getElementById('loadMoreBtn')?.addEventListener('click', function () {
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
    this.disabled = true;

    currentPage++;
    loadOrders(currentPage, true).then(() => {
        this.innerHTML = '<i class="fas fa-plus me-2"></i>Load More Orders';
        this.disabled = false;
    }).catch(() => {
        this.innerHTML = '<i class="fas fa-plus me-2"></i>Load More Orders';
        this.disabled = false;
    });
});

// Show Empty State
function showEmptyState() {
    const filterText = currentFilter === 'all' ? '' : ` with status "${currentFilter}"`;
    document.getElementById('ordersList').innerHTML = `
        <div class="empty-state">
            <i class="fas fa-shopping-bag empty-icon"></i>
            <h3 class="empty-title">No Orders Found</h3>
            <p class="empty-text">You don't have any orders${filterText}. Start shopping to see your orders here!</p>
            <button class="btn btn-primary btn-lg" onclick="window.location.href='../products.php'">
                <i class="fas fa-shopping-cart me-2"></i>Start Shopping
            </button>
        </div>
    `;
}

// Initialize Review Modal
function initReviewModal() {
    // Star rating interaction
    document.querySelectorAll('.star-label').forEach(label => {
        label.addEventListener('click', function () {
            const input = document.getElementById(this.getAttribute('for'));
            input.checked = true;
        });
    });
}

// Open Review Modal
function openReviewModal(productId, orderId, productName) {
    // Check if review already exists
    fetch(`../ajax.php?action=check_review&product_id=${productId}&order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                showNotification('You have already reviewed this product', 'info');
                return;
            }
            
            document.getElementById('reviewProductId').value = productId;
            document.getElementById('reviewOrderId').value = orderId;
            document.getElementById('reviewProductName').textContent = productName;
            document.getElementById('reviewMessage').innerHTML = '';
            document.getElementById('reviewForm').reset();

            const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
            modal.show();
        });
}

// Submit Review
async function submitReview() {
    const form = document.getElementById('reviewForm');
    const formData = new FormData(form);
    formData.append('action', 'submit_review');

    const messageDiv = document.getElementById('reviewMessage');
    const submitBtn = event.target;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

    try {
        const response = await fetch('../ajax.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            messageDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>${data.message}
                </div>
            `;

            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
                form.reset();
                // Reload orders to show the new review
                loadOrders(currentPage, false);
            }, 2000);
        } else {
            messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>${data.message}
                </div>
            `;
        }
    } catch (error) {
        console.error('Error submitting review:', error);
        messageDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>Error submitting review. Please try again.
            </div>
        `;
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '';
    }
}

// Capitalize first letter
function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}