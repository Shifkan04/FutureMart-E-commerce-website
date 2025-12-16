// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initAnimations();
    initFilterButtons();
    initNotificationPreferences();
    loadNotifications();
});

// GSAP Animations
// function initAnimations() {
//     // Animate cards
//     gsap.from('.notification-card', {
//         duration: 0.6,
//         y: 30,
//         opacity: 0,
//         stagger: 0.1,
//         ease: 'power2.out'
//     });

//     // Animate stats
//     gsap.from('.stat-item', {
//         duration: 0.5,
//         x: -20,
//         opacity: 0,
//         stagger: 0.1,
//         ease: 'power2.out',
//         delay: 0.3
//     });

//     // Animate filter buttons
//     gsap.from('.filter-btn', {
//         duration: 0.4,
//         x: -20,
//         opacity: 0,
//         stagger: 0.1,
//         ease: 'power2.out',
//         delay: 0.4
//     });

//     // Animate notification items
//     gsap.from('.notification-item', {
//         duration: 0.5,
//         x: 20,
//         opacity: 0,
//         stagger: 0.05,
//         ease: 'power2.out',
//         delay: 0.5
//     });
// }

// Initialize Filter Buttons
function initFilterButtons() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active state
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Apply filter
            const filter = this.dataset.filter;
            filterNotifications(filter);
            
            // Animate
            gsap.from('.notification-item:not([style*="display: none"])', {
                duration: 0.4,
                opacity: 0,
                y: 20,
                stagger: 0.05,
                ease: 'power2.out'
            });
        });
    });
}

// Filter Notifications
function filterNotifications(filter) {
    const notifications = document.querySelectorAll('.notification-item');
    
    notifications.forEach(notification => {
        const type = notification.dataset.type;
        const isUnread = notification.classList.contains('unread');
        
        let show = false;
        
        switch(filter) {
            case 'all':
                show = true;
                break;
            case 'unread':
                show = isUnread;
                break;
            case 'order':
            case 'security':
            case 'profile':
                show = type === filter;
                break;
        }
        
        notification.style.display = show ? 'flex' : 'none';
    });
}

// Load Notifications (refresh)
function loadNotifications() {
    // This function can be used to periodically check for new notifications
    // For now, it's a placeholder for future AJAX implementation
}

// Mark Single Notification as Read
async function markAsRead(notificationId) {
    try {
        const response = await fetch('ajax_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_as_read&notification_id=${notificationId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.classList.remove('unread');
                
                // Animate removal of unread indicator
                gsap.to(notificationItem, {
                    duration: 0.3,
                    backgroundColor: 'rgba(255, 255, 255, 0.02)',
                    ease: 'power2.out'
                });
                
                // Update unread count
                updateUnreadCount(-1);
            }
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark All as Read
async function markAllAsRead() {
    if (!confirm('Mark all notifications as read?')) {
        return;
    }
    
    try {
        const response = await fetch('ajax_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_all_as_read'
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove unread class from all notifications
            const unreadNotifications = document.querySelectorAll('.notification-item.unread');
            
            unreadNotifications.forEach((notif, index) => {
                setTimeout(() => {
                    notif.classList.remove('unread');
                    gsap.to(notif, {
                        duration: 0.3,
                        backgroundColor: 'rgba(255, 255, 255, 0.02)',
                        ease: 'power2.out'
                    });
                }, index * 50);
            });
            
            // Update unread count to 0
            const badge = document.querySelector('.sidebar-menu .active .badge');
            if (badge) {
                gsap.to(badge, {
                    duration: 0.3,
                    scale: 0,
                    opacity: 0,
                    ease: 'back.in(1.7)',
                    onComplete: () => badge.remove()
                });
            }
            
            // Update stats
            document.querySelector('.unread-notif').closest('.stat-item').querySelector('h3').textContent = '0';
            
            showNotification('All notifications marked as read', 'success');
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
        showNotification('Error marking notifications as read', 'error');
    }
}

// Delete Single Notification
async function deleteNotification(notificationId) {
    if (!confirm('Delete this notification?')) {
        return;
    }
    
    try {
        const response = await fetch('ajax_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_notification&notification_id=${notificationId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (notificationItem) {
                const isUnread = notificationItem.classList.contains('unread');
                
                // Animate deletion
                gsap.to(notificationItem, {
                    duration: 0.3,
                    x: -100,
                    opacity: 0,
                    height: 0,
                    marginBottom: 0,
                    paddingTop: 0,
                    paddingBottom: 0,
                    ease: 'power2.in',
                    onComplete: () => {
                        notificationItem.remove();
                        
                        // Update counts
                        updateTotalCount(-1);
                        if (isUnread) {
                            updateUnreadCount(-1);
                        }
                        
                        // Check if list is empty
                        checkEmptyList();
                    }
                });
            }
        }
    } catch (error) {
        console.error('Error deleting notification:', error);
        showNotification('Error deleting notification', 'error');
    }
}

// Clear All Notifications
async function clearAllNotifications() {
    if (!confirm('Delete all notifications? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('ajax_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear_all_notifications'
        });
        
        const data = await response.json();
        
        if (data.success) {
            const allNotifications = document.querySelectorAll('.notification-item');
            
            // Animate deletion of all notifications
            allNotifications.forEach((notif, index) => {
                setTimeout(() => {
                    gsap.to(notif, {
                        duration: 0.3,
                        x: -100,
                        opacity: 0,
                        ease: 'power2.in',
                        onComplete: () => notif.remove()
                    });
                }, index * 30);
            });
            
            // Show empty state after animation
            setTimeout(() => {
                const listContainer = document.getElementById('notificationsList');
                listContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h4>No Notifications</h4>
                        <p>You're all caught up! Check back later for new updates.</p>
                    </div>
                `;
                
                // Animate empty state
                gsap.from('.empty-state', {
                    duration: 0.6,
                    scale: 0.8,
                    opacity: 0,
                    ease: 'back.out(1.7)'
                });
                
                // Update stats
                document.querySelector('.all-notif').closest('.stat-item').querySelector('h3').textContent = '0';
                document.querySelector('.unread-notif').closest('.stat-item').querySelector('h3').textContent = '0';
                document.querySelector('.today-notif').closest('.stat-item').querySelector('h3').textContent = '0';
            }, allNotifications.length * 30 + 300);
            
            showNotification('All notifications cleared', 'success');
        }
    } catch (error) {
        console.error('Error clearing notifications:', error);
        showNotification('Error clearing notifications', 'error');
    }
}

// Update Unread Count
function updateUnreadCount(change) {
    const unreadStat = document.querySelector('.unread-notif').closest('.stat-item').querySelector('h3');
    const currentCount = parseInt(unreadStat.textContent);
    const newCount = Math.max(0, currentCount + change);
    unreadStat.textContent = newCount;
    
    // Update sidebar badge
    const sidebarBadge = document.querySelector('.sidebar-menu .active .badge');
    if (sidebarBadge) {
        const currentBadgeCount = parseInt(sidebarBadge.textContent);
        const newBadgeCount = Math.max(0, currentBadgeCount + change);
        
        if (newBadgeCount === 0) {
            gsap.to(sidebarBadge, {
                duration: 0.3,
                scale: 0,
                opacity: 0,
                ease: 'back.in(1.7)',
                onComplete: () => sidebarBadge.remove()
            });
        } else {
            sidebarBadge.textContent = newBadgeCount;
        }
    }
}

// Update Total Count
function updateTotalCount(change) {
    const totalStat = document.querySelector('.all-notif').closest('.stat-item').querySelector('h3');
    const currentCount = parseInt(totalStat.textContent);
    const newCount = Math.max(0, currentCount + change);
    totalStat.textContent = newCount;
}

// Check if List is Empty
function checkEmptyList() {
    const notifications = document.querySelectorAll('.notification-item');
    if (notifications.length === 0) {
        const listContainer = document.getElementById('notificationsList');
        listContainer.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h4>No Notifications</h4>
                <p>You're all caught up! Check back later for new updates.</p>
            </div>
        `;
        
        gsap.from('.empty-state', {
            duration: 0.6,
            scale: 0.8,
            opacity: 0,
            ease: 'back.out(1.7)'
        });
    }
}

// Initialize Notification Preferences Form
function initNotificationPreferences() {
    const form = document.getElementById('notificationPreferencesForm');
    
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            formData.append('action', 'update_notification_preferences');
            
            // Add all checkbox values
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                formData.set(checkbox.name, checkbox.checked ? '1' : '0');
            });
            
            try {
                const response = await fetch('ajax_notifications.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('preferencesMessage', data.message, 'success');
                    
                    gsap.from('#preferencesMessage .alert', {
                        duration: 0.5,
                        scale: 0.8,
                        opacity: 0,
                        ease: 'back.out(1.7)'
                    });
                } else {
                    showMessage('preferencesMessage', data.message, 'danger');
                }
            } catch (error) {
                console.error('Error updating preferences:', error);
                showMessage('preferencesMessage', 'Error updating preferences. Please try again.', 'danger');
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.innerHTML = originalHTML;
                submitBtn.disabled = false;
            }
        });
    }
}

// Show Message Helper
function showMessage(containerId, message, type) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    container.innerHTML = `
        <div class="alert ${alertClass}">
            <i class="fas ${icon} me-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (container.querySelector('.alert')) {
            gsap.to(container.querySelector('.alert'), {
                duration: 0.3,
                opacity: 0,
                height: 0,
                marginTop: 0,
                ease: 'power2.in',
                onComplete: () => {
                    container.innerHTML = '';
                }
            });
        }
    }, 5000);
}

// Show Notification Toast
function showNotification(message, type) {
    const notification = document.createElement('div');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
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