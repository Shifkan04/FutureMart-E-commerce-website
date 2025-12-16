// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Don't call animations immediately
    initAvatarUpload();
    initPersonalInfoForm();
    initPreferencesForm();
    
    // Call animations after a small delay to ensure DOM is ready
    setTimeout(() => {
        initAnimations();
    }, 100);
});

// GSAP Animations
function initAnimations() {
    // Check if GSAP is loaded
    if (typeof gsap === 'undefined') {
        console.log('GSAP not loaded, content will display normally');
        return;
    }

    try {
        // Animate cards (start from visible state)
        gsap.fromTo('.settings-card', 
            { y: 30, opacity: 0 },
            { y: 0, opacity: 1, duration: 0.6, stagger: 0.1, ease: 'power2.out' }
        );

        // Animate page header
        gsap.fromTo('.profile-header',
            { y: -30, opacity: 0 },
            { y: 0, opacity: 1, duration: 0.8, ease: 'power3.out' }
        );

        // Animate quick stats
        gsap.fromTo('.quick-stat-item',
            { x: -20, opacity: 0 },
            { x: 0, opacity: 1, duration: 0.5, stagger: 0.1, ease: 'power2.out', delay: 0.3 }
        );
    } catch (error) {
        console.error('Animation error:', error);
    }
}

// Avatar Upload Handler
function initAvatarUpload() {
    const avatarInput = document.getElementById('avatarInput');
    const currentAvatar = document.getElementById('currentAvatar');
    
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.match('image.*')) {
                    showMessage('personalInfoMessage', 'Please select a valid image file', 'danger');
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showMessage('personalInfoMessage', 'Image size must be less than 5MB', 'danger');
                    return;
                }
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatarPreview');
                    if (preview) {
                        preview.src = e.target.result;
                    } else {
                        const placeholder = currentAvatar.querySelector('.avatar-placeholder');
                        if (placeholder) {
                            placeholder.parentElement.innerHTML = `<img src="${e.target.result}" alt="Profile" id="avatarPreview">
                            <div class="avatar-overlay">
                                <i class="fas fa-camera"></i>
                            </div>`;
                        }
                    }
                    
                    // Animate avatar change
                    gsap.from('#currentAvatar', {
                        duration: 0.5,
                        scale: 0.8,
                        opacity: 0,
                        ease: 'back.out(1.7)'
                    });
                };
                reader.readAsDataURL(file);
                
                // Upload avatar
                uploadAvatar(file);
            }
        });
    }
    
    // Click to upload
    if (currentAvatar) {
        currentAvatar.addEventListener('click', function() {
            avatarInput.click();
        });
    }
}

// Upload Avatar to Server
async function uploadAvatar(file) {
    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('action', 'upload_avatar');
    
    try {
        const response = await fetch('ajax_profile.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('personalInfoMessage', data.message, 'success');
            
            // Update navbar avatar
            updateNavbarAvatar(data.avatar_url);
            
            // Show remove button if not exists
            showRemoveButton();
        } else {
            showMessage('personalInfoMessage', data.message, 'danger');
        }
    } catch (error) {
        console.error('Error uploading avatar:', error);
        showMessage('personalInfoMessage', 'Error uploading image. Please try again.', 'danger');
    }
}

// Remove Avatar
async function removeAvatar() {
    if (!confirm('Are you sure you want to remove your profile picture?')) {
        return;
    }
    
    try {
        const response = await fetch('ajax_profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=remove_avatar'
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Get user's first name initial
            const firstName = document.querySelector('input[name="first_name"]').value;
            const initial = firstName.charAt(0).toUpperCase();
            
            // Replace with placeholder
            document.getElementById('currentAvatar').innerHTML = `
                <div class="avatar-placeholder">${initial}</div>
                <div class="avatar-overlay">
                    <i class="fas fa-camera"></i>
                </div>
            `;
            
            // Update navbar
            updateNavbarAvatarToInitial(initial);
            
            // Hide remove button
            const removeBtn = document.querySelector('.btn-remove');
            if (removeBtn) {
                gsap.to(removeBtn, {
                    duration: 0.3,
                    opacity: 0,
                    height: 0,
                    marginTop: 0,
                    onComplete: () => removeBtn.remove()
                });
            }
            
            showMessage('personalInfoMessage', data.message, 'success');
            
            // Animate change
            gsap.from('#currentAvatar', {
                duration: 0.5,
                scale: 0.8,
                opacity: 0,
                ease: 'back.out(1.7)'
            });
        } else {
            showMessage('personalInfoMessage', data.message, 'danger');
        }
    } catch (error) {
        console.error('Error removing avatar:', error);
        showMessage('personalInfoMessage', 'Error removing image. Please try again.', 'danger');
    }
}

// Update Navbar Avatar
function updateNavbarAvatar(avatarUrl) {
    const navAvatars = document.querySelectorAll('.profile-circle img');
    navAvatars.forEach(img => {
        img.src = avatarUrl;
    });
}

// Update Navbar Avatar to Initial
function updateNavbarAvatarToInitial(initial) {
    const navAvatars = document.querySelectorAll('.profile-circle');
    navAvatars.forEach(circle => {
        circle.innerHTML = initial;
    });
}

// Show Remove Button
function showRemoveButton() {
    const uploadArea = document.querySelector('.profile-upload-area');
    if (!document.querySelector('.btn-remove')) {
        const removeBtn = document.createElement('button');
        removeBtn.className = 'btn btn-outline-danger btn-remove mt-2';
        removeBtn.innerHTML = '<i class="fas fa-trash me-2"></i>Remove Photo';
        removeBtn.onclick = removeAvatar;
        
        const uploadBtn = document.querySelector('.btn-upload');
        uploadBtn.parentNode.insertBefore(removeBtn, uploadBtn.nextSibling);
        
        gsap.from(removeBtn, {
            duration: 0.4,
            opacity: 0,
            height: 0,
            ease: 'power2.out'
        });
    }
}

// Personal Info Form Handler
function initPersonalInfoForm() {
    const form = document.getElementById('personalInfoForm');
    
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            formData.append('action', 'update_personal_info');
            
            try {
                const response = await fetch('ajax_profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('personalInfoMessage', data.message, 'success');
                    
                    // Update navbar name
                    const firstName = formData.get('first_name');
                    document.querySelectorAll('.profile-name').forEach(el => {
                        el.textContent = firstName;
                    });
                    
                    // Animate success
                    gsap.from('#personalInfoMessage .alert', {
                        duration: 0.5,
                        x: 20,
                        opacity: 0,
                        ease: 'back.out(1.7)'
                    });
                } else {
                    showMessage('personalInfoMessage', data.message, 'danger');
                }
            } catch (error) {
                console.error('Error updating personal info:', error);
                showMessage('personalInfoMessage', 'Error updating information. Please try again.', 'danger');
            } finally {
                // Reset button
                submitBtn.classList.remove('loading');
                submitBtn.innerHTML = originalHTML;
                submitBtn.disabled = false;
            }
        });
    }
}

// Preferences Form Handler
function initPreferencesForm() {
    const form = document.getElementById('preferencesForm');
    
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            formData.append('action', 'update_preferences');
            
            try {
                const response = await fetch('ajax_profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('preferencesMessage', data.message, 'success');
                    
                    // Animate success
                    gsap.from('#preferencesMessage .alert', {
                        duration: 0.5,
                        x: 20,
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
                // Reset button
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
            <i class="fas ${icon}"></i>
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