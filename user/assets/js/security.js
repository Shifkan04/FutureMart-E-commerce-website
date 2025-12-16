// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initSecurityScore();
    initPasswordForm();
    initPasswordStrength();
    initSecurityPreferences();
    console.log('Security page initialized');
});

// Initialize Security Score
function initSecurityScore() {
    const scoreValue = parseInt(document.getElementById('securityScore').textContent);
    const circle = document.getElementById('scoreProgress');
    const circumference = 2 * Math.PI * 65;
    const offset = circumference - (scoreValue / 100) * circumference;

    circle.style.strokeDasharray = circumference;
    circle.style.strokeDashoffset = circumference;

    setTimeout(() => {
        circle.style.transition = 'stroke-dashoffset 2s ease';
        circle.style.strokeDashoffset = offset;
    }, 300);

    let currentScore = 0;
    const scoreInterval = setInterval(() => {
        if (currentScore >= scoreValue) {
            clearInterval(scoreInterval);
        } else {
            currentScore++;
            document.getElementById('securityScore').textContent = currentScore;
        }
    }, 20);

    const statusEl = document.getElementById('securityStatus');
    if (scoreValue >= 80) {
        statusEl.textContent = 'Excellent Security';
        statusEl.style.color = 'var(--success-color)';
    } else if (scoreValue >= 60) {
        statusEl.textContent = 'Good Security';
        statusEl.style.color = 'var(--primary-color)';
    } else if (scoreValue >= 40) {
        statusEl.textContent = 'Moderate Security';
        statusEl.style.color = 'var(--warning-color)';
    } else {
        statusEl.textContent = 'Weak Security';
        statusEl.style.color = 'var(--error-color)';
    }
}

// Password Form Handler
function initPasswordForm() {
    const form = document.getElementById('changePasswordForm');
    
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                showMessage('passwordMessage', 'Passwords do not match', 'danger');
                return;
            }
            
            if (newPassword.length < 8) {
                showMessage('passwordMessage', 'Password must be at least 8 characters long', 'danger');
                return;
            }
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            formData.append('action', 'change_password');
            
            try {
                const response = await fetch('ajax_security.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('passwordMessage', data.message, 'success');
                    form.reset();
                    
                    const strengthFill = document.getElementById('strengthFill');
                    const strengthText = document.getElementById('strengthText');
                    strengthFill.className = 'strength-fill';
                    strengthFill.style.width = '0%';
                    strengthText.textContent = 'Password Strength';
                    strengthText.className = 'strength-text';
                } else {
                    showMessage('passwordMessage', data.message, 'danger');
                }
            } catch (error) {
                console.error('Error changing password:', error);
                showMessage('passwordMessage', 'Error changing password. Please try again.', 'danger');
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.innerHTML = originalHTML;
                submitBtn.disabled = false;
            }
        });
    }
}

// Password Strength Checker
function initPasswordStrength() {
    const passwordInput = document.getElementById('newPassword');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            strengthFill.className = 'strength-fill ' + strength.class;
            strengthText.textContent = strength.text;
            strengthText.className = 'strength-text ' + strength.class;
            strengthFill.style.width = strength.width;
        });
    }
}

// Calculate Password Strength
function calculatePasswordStrength(password) {
    if (password.length === 0) {
        return { class: '', text: 'Password Strength', width: '0%' };
    }
    
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    if (strength <= 2) {
        return { class: 'weak', text: 'Weak Password', width: '33%' };
    } else if (strength <= 4) {
        return { class: 'medium', text: 'Medium Password', width: '66%' };
    } else {
        return { class: 'strong', text: 'Strong Password', width: '100%' };
    }
}

// Toggle Password Visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.parentElement.querySelector('.password-toggle i');
    
    if (field.type === 'password') {
        field.type = 'text';
        button.classList.remove('fa-eye');
        button.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        button.classList.remove('fa-eye-slash');
        button.classList.add('fa-eye');
    }
}

// Enable Two-Factor Authentication
async function enable2FA() {
    try {
        const qrContainer = document.getElementById('qrCodeContainer');
        qrContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Generating QR Code...</p></div>';
        
        const response = await fetch('ajax_security.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=generate_2fa_secret'
        });
        
        const data = await response.json();
        
        if (data.success) {
            const modal = new bootstrap.Modal(document.getElementById('twoFactorModal'));
            modal.show();
            
            generateQRCode(data.data.qr_code_url, data.data.secret);
            document.getElementById('secretKey').textContent = data.data.secret;
            init2FAVerification();
        } else {
            showMessage('twoFactorMessage', data.message || 'Failed to generate 2FA secret', 'danger');
        }
    } catch (error) {
        console.error('Error generating 2FA secret:', error);
        showMessage('twoFactorMessage', 'Error setting up 2FA. Please try again.', 'danger');
    }
}

// Generate QR Code with multiple fallbacks
function generateQRCode(url, secret) {
    const qrContainer = document.getElementById('qrCodeContainer');
    
    const apiMethods = [
        `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(url)}`,
        `https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=${encodeURIComponent(url)}&choe=UTF-8`
    ];
    
    let methodIndex = 0;
    
    function tryNextMethod() {
        if (methodIndex >= apiMethods.length) {
            qrContainer.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>QR Code unavailable</strong><br>
                    Please use the manual key entry below.
                </div>
                <div class="manual-setup-guide mt-3">
                    <h6><i class="fas fa-mobile-alt me-2"></i>Manual Setup:</h6>
                    <ol class="text-start small">
                        <li>Open your authenticator app</li>
                        <li>Select "Add Account" or "+"</li>
                        <li>Choose "Enter a setup key"</li>
                        <li>Enter the key shown below</li>
                        <li>Tap "Add" or "Done"</li>
                    </ol>
                </div>
            `;
            return;
        }
        
        const img = new Image();
        const currentUrl = apiMethods[methodIndex];
        
        img.onload = function() {
            qrContainer.innerHTML = '';
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'padding: 15px; background: white; border-radius: 12px; display: inline-block; box-shadow: 0 4px 20px rgba(0,0,0,0.15);';
            
            img.style.cssText = 'display: block; width: 250px; height: 250px;';
            img.alt = '2FA QR Code';
            
            wrapper.appendChild(img);
            qrContainer.appendChild(wrapper);
        };
        
        img.onerror = function() {
            methodIndex++;
            setTimeout(tryNextMethod, 500);
        };
        
        img.src = currentUrl;
        
        setTimeout(() => {
            if (!img.complete || img.naturalHeight === 0) {
                img.onerror();
            }
        }, 5000);
    }
    
    tryNextMethod();
}

// Initialize 2FA Verification
function init2FAVerification() {
    const form = document.getElementById('verify2FAForm');
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);
    
    const codeInput = newForm.querySelector('input[name="verification_code"]');
    codeInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
        if (this.value.length > 6) {
            this.value = this.value.slice(0, 6);
        }
    });
    
    newForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const code = codeInput.value.trim();
        
        if (code.length !== 6) {
            showMessage('verify2FAMessage', 'Please enter a complete 6-digit code', 'danger');
            return;
        }
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalHTML = submitBtn.innerHTML;
        
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
        submitBtn.disabled = true;
        
        const formData = new FormData(this);
        formData.append('action', 'verify_2fa');
        
        try {
            const response = await fetch('ajax_security.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                let backupCodesHTML = `
                    <div class="backup-codes-display mt-3 p-3" style="background: rgba(16, 185, 129, 0.1); border-radius: 10px; border: 2px solid #10b981;">
                        <h6 class="text-success mb-3">
                            <i class="fas fa-shield-alt me-2"></i>2FA Enabled Successfully!
                        </h6>
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Save these backup codes securely!
                        </div>
                        <h6 class="mb-2"><i class="fas fa-key me-2"></i>Backup Codes:</h6>
                        <div class="row">
                `;
                
                data.data.backup_codes.forEach((code) => {
                    backupCodesHTML += `
                        <div class="col-6 mb-2">
                            <code style="background: white; padding: 8px 12px; border-radius: 5px; display: block; color: #000; font-size: 14px; font-weight: 600;">
                                ${code}
                            </code>
                        </div>
                    `;
                });
                
                backupCodesHTML += `
                        </div>
                        <button class="btn btn-sm btn-outline-success mt-3" onclick="copyBackupCodes()">
                            <i class="fas fa-copy me-2"></i>Copy All Codes
                        </button>
                    </div>
                `;
                
                showMessage('verify2FAMessage', backupCodesHTML, 'success');
                window.tempBackupCodes = data.data.backup_codes;
                
                setTimeout(() => {
                    location.reload();
                }, 8000);
            } else {
                showMessage('verify2FAMessage', data.message, 'danger');
                submitBtn.classList.remove('loading');
                submitBtn.innerHTML = originalHTML;
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error verifying 2FA:', error);
            showMessage('verify2FAMessage', 'Error verifying code. Please try again.', 'danger');
            submitBtn.classList.remove('loading');
            submitBtn.innerHTML = originalHTML;
            submitBtn.disabled = false;
        }
    });
}

// Copy Backup Codes
function copyBackupCodes() {
    if (window.tempBackupCodes) {
        const text = window.tempBackupCodes.join('\n');
        navigator.clipboard.writeText(text).then(() => {
            alert('Backup codes copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy:', err);
        });
    }
}

// Disable 2FA
async function disable2FA() {
    if (!confirm('Are you sure you want to disable two-factor authentication?')) {
        return;
    }
    
    try {
        const response = await fetch('ajax_security.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=disable_2fa'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('twoFactorMessage', data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showMessage('twoFactorMessage', data.message, 'danger');
        }
    } catch (error) {
        console.error('Error disabling 2FA:', error);
        showMessage('twoFactorMessage', 'Error disabling 2FA. Please try again.', 'danger');
    }
}

// Security Preferences Form
function initSecurityPreferences() {
    const form = document.getElementById('securityPreferencesForm');
    
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            formData.append('action', 'update_security_preferences');
            
            const loginAlertsCheckbox = form.querySelector('input[name="login_alerts"]');
            formData.set('login_alerts', loginAlertsCheckbox.checked ? '1' : '0');
            
            try {
                const response = await fetch('ajax_security.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('preferencesMessage', data.message, 'success');
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

// Terminate All Sessions
async function terminateAllSessions() {
    if (!confirm('This will log you out from all devices. Continue?')) {
        return;
    }
    
    try {
        const response = await fetch('ajax_security.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=terminate_all_sessions'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            window.location.href = '../login.php';
        } else {
            alert(data.message);
        }
    } catch (error) {
        console.error('Error terminating sessions:', error);
        alert('Error terminating sessions. Please try again.');
    }
}

// Scroll to Section
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
        section.classList.add('scroll-highlight');
        setTimeout(() => {
            section.classList.remove('scroll-highlight');
        }, 2000);
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
    
    const timeout = type === 'success' ? 10000 : 5000;
    setTimeout(() => {
        if (container.querySelector('.alert')) {
            const alert = container.querySelector('.alert');
            alert.style.transition = 'all 0.3s ease';
            alert.style.opacity = '0';
            alert.style.height = '0';
            alert.style.marginTop = '0';
            setTimeout(() => {
                container.innerHTML = '';
            }, 300);
        }
    }, timeout);
}