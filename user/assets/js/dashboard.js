// Initialize GSAP animations
gsap.registerPlugin();

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    initAnimations();
    updateDateTime();
    setInterval(updateDateTime, 1000);
    loadRecentActivity();
    animateProgressBars();
    
    // Initialize theme
    const theme = document.documentElement.getAttribute('data-theme');
    updateThemeIcon(theme);
});

// GSAP Animations
function initAnimations() {
    // Animate welcome card
    gsap.from('.welcome-card', {
        duration: 0.8,
        y: -50,
        opacity: 0,
        ease: 'power3.out'
    });

    // Animate stat cards with stagger
    gsap.from('.stat-card', {
        duration: 0.8,
        y: 3000,
        opacity: 0,
        stagger: 0.1,
        ease: 'power2.out',
        delay: -0.5
    });

    // Animate quick actions
    // gsap.from('.action-item', {
    //     duration: 0.5,
    //     scale: 0.8,
    //     opacity: 0,
    //     stagger: 0.1,
    //     ease: 'back.out(1.7)',
    //     delay: 0.5
    // });

    // Animate content cards
    gsap.from('.content-card', {
        duration: 0.6,
        x: -30,
        opacity: 0,
        stagger: 0.15,
        ease: 'power2.out',
        delay: 0.4
    });
}

// Update Date and Time
function updateDateTime() {
    const now = new Date();
    const timeOptions = { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit',
        hour12: true 
    };
    const dateOptions = { 
        weekday: 'long',
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    
    const timeEl = document.getElementById('currentTime');
    const dateEl = document.getElementById('currentDate');
    
    if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
    if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
}

// Animate Progress Bars
function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    
    progressBars.forEach(bar => {
        const progress = bar.getAttribute('data-progress');
        
        gsap.to(bar, {
            duration: 1.5,
            width: progress + '%',
            ease: 'power2.out',
            delay: 0.8
        });
    });
}

// Load Recent Activity
function loadRecentActivity() {
    fetch('../ajax.php?action=get_activity&limit=5')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRecentActivity(data.data);
            } else {
                showEmptyActivity();
            }
        })
        .catch(error => {
            console.error('Error loading activity:', error);
            showEmptyActivity();
        });
}

// Display Recent Activity
function displayRecentActivity(activities) {
    const container = document.getElementById('recentActivity');
    
    if (!activities || activities.length === 0) {
        showEmptyActivity();
        return;
    }

    const activityHTML = activities.map(activity => {
        const icon = getActivityIcon(activity.activity_type);
        const color = getActivityColor(activity.activity_type);
        return `
            <div class="activity-item">
                <div class="activity-icon" style="background: ${color};">
                    <i class="${icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${activity.activity_description}</div>
                    <div class="activity-time">${timeAgo(activity.created_at)}</div>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = activityHTML;
    
    // Animate activity items
    gsap.from('.activity-item', {
        duration: 0.5,
        x: 20,
        opacity: 0,
        stagger: 0.1,
        ease: 'power2.out'
    });
}

// Show Empty Activity
function showEmptyActivity() {
    const container = document.getElementById('recentActivity');
    container.innerHTML = `
        <div class="text-center py-4 text-muted">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <p>No recent activity</p>
        </div>
    `;
}

// Get Activity Icon
function getActivityIcon(type) {
    const icons = {
        'order_delivered': 'fas fa-check-circle',
        'order_placed': 'fas fa-shopping-cart',
        'order_shipped': 'fas fa-truck',
        'wishlist_add': 'fas fa-heart',
        'wishlist_remove': 'fas fa-heart-broken',
        'profile_update': 'fas fa-user-edit',
        'review_added': 'fas fa-star',
        'password_change': 'fas fa-key',
        'address_add': 'fas fa-map-marker-alt',
        'logout': 'fas fa-sign-out-alt'
    };
    return icons[type] || 'fas fa-info-circle';
}

// Get Activity Color
function getActivityColor(type) {
    const colors = {
        'order_delivered': 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'order_placed': 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
        'order_shipped': 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'wishlist_add': 'linear-gradient(135deg, #ec4899 0%, #db2777 100%)',
        'profile_update': 'linear-gradient(135deg, #06b6d4 0%, #0891b2 100%)',
        'review_added': 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)'
    };
    return colors[type] || 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)';
}

// Time Ago Function
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Toggle Theme
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    updateThemeIcon(newTheme);
    
    // Animate theme change
    gsap.from('body', {
        duration: 0.3,
        opacity: 0.8,
        ease: 'power2.inOut'
    });
    
    // Save theme preference
    fetch('../ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_theme&theme=${newTheme}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Theme updated successfully!', 'success');
        }
    })
    .catch(error => console.error('Error updating theme:', error));
}

// Update Theme Icon
function updateThemeIcon(theme) {
    const icon = document.getElementById('themeIcon');
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Show Notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    const alertClass = type === 'success' ? 'alert-success' : 
                       type === 'error' ? 'alert-danger' : 'alert-info';
    const icon = type === 'success' ? 'fa-check-circle' : 
                 type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    
    notification.innerHTML = `
        <div class="alert ${alertClass} position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 300px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <i class="fas ${icon} me-2"></i>
            ${message}
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

// Logout Function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        // Animate out
        gsap.to('body', {
            duration: 0.5,
            opacity: 0,
            scale: 0.95,
            ease: 'power2.in',
            onComplete: () => {
                window.location.href = 'logout.php';
            }
        });
    }
}

// Sidebar Active State
document.querySelectorAll('.sidebar-menu a').forEach(link => {
    link.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href && href !== '#' && !href.startsWith('http')) {
            // Animate page transition
            gsap.to('.main-content', {
                duration: 0.3,
                opacity: 0,
                x: -30,
                ease: 'power2.in',
                onComplete: () => {
                    window.location.href = href;
                }
            });
            e.preventDefault();
        }
    });
});