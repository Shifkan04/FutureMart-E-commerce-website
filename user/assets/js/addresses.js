let addressModal;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    addressModal = new bootstrap.Modal(document.getElementById('addressModal'));
    loadAddresses();
});

function initAnimations() {
    // addresses-header 
    gsap.from('.addresses-header', {
        duration: 0.8,
        y: -50,
        opacity: 0,
        ease: 'power3.out'
    });
}

// Load Addresses
async function loadAddresses() {
    const grid = document.getElementById('addressesGrid');
    
    try {
        const response = await fetch('../ajax.php?action=get_addresses');
        const data = await response.json();

        if (data.success && data.data && data.data.length > 0) {
            displayAddresses(data.data);
        } else {
            showEmptyState();
        }
    } catch (error) {
        console.error('Error loading addresses:', error);
        showError();
    }
}

// Display Addresses
function displayAddresses(addresses) {
    const grid = document.getElementById('addressesGrid');
    
    const html = addresses.map(address => `
        <div class="col-lg-6 mb-4">
            <div class="address-card ${address.is_default ? 'default' : ''}">
                <div class="address-header">
                    <div>
                        <h5 class="address-title">${address.title}</h5>
                        ${address.is_default ? '<span class="default-badge"><i class="fas fa-check me-1"></i>Default</span>' : ''}
                    </div>
                </div>
                
                <div class="address-content">
                    <p><i class="fas fa-map-marker-alt"></i> ${address.address_line_1}</p>
                    ${address.address_line_2 ? `<p><i class="fas fa-building"></i> ${address.address_line_2}</p>` : ''}
                    <p><i class="fas fa-city"></i> ${address.city}, ${address.state} ${address.postal_code}</p>
                    <p><i class="fas fa-globe"></i> ${address.country}</p>
                    ${address.phone ? `<p><i class="fas fa-phone"></i> ${address.phone}</p>` : ''}
                </div>
                
                <div class="address-actions">
                    ${!address.is_default ? `
                        <button class="btn btn-set-default" onclick="setDefaultAddress(${address.id})">
                            <i class="fas fa-check me-1"></i>Set Default
                        </button>
                    ` : ''}
                    <button class="btn btn-edit-address" onclick="editAddress(${address.id})">
                        <i class="fas fa-edit me-1"></i>Edit
                    </button>
                    <button class="btn btn-delete-address" onclick="deleteAddress(${address.id})">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    grid.innerHTML = html;
    
    // Animate cards
    gsap.from('.address-card', {
        duration: 0.6,
        opacity: 0,
        y: 30,
        stagger: 0.1,
        ease: 'power2.out'
    });
}

// Show Add Address Modal
function showAddAddressModal() {
    document.getElementById('modalTitle').textContent = 'Add New Address';
    document.getElementById('addressForm').reset();
    document.getElementById('addressId').value = '';
    document.getElementById('addressMessage').innerHTML = '';
    addressModal.show();
    
    gsap.from('.modal-content', {
        duration: 0.4,
        scale: 0.9,
        opacity: 0,
        ease: 'back.out(1.7)'
    });
}

// Edit Address
async function editAddress(addressId) {
    try {
        const response = await fetch(`../ajax.php?action=get_addresses`);
        const data = await response.json();
        
        if (data.success) {
            const address = data.data.find(a => a.id == addressId);
            if (address) {
                document.getElementById('modalTitle').textContent = 'Edit Address';
                document.getElementById('addressId').value = address.id;
                document.getElementById('addressTitle').value = address.title;
                document.getElementById('addressPhone').value = address.phone || '';
                document.getElementById('addressLine1').value = address.address_line_1;
                document.getElementById('addressLine2').value = address.address_line_2 || '';
                document.getElementById('addressCity').value = address.city;
                document.getElementById('addressState').value = address.state;
                document.getElementById('addressPostalCode').value = address.postal_code;
                document.getElementById('addressCountry').value = address.country;
                document.getElementById('isDefault').checked = address.is_default == 1;
                
                addressModal.show();
                
                gsap.from('.modal-content', {
                    duration: 0.4,
                    scale: 0.9,
                    opacity: 0,
                    ease: 'back.out(1.7)'
                });
            }
        }
    } catch (error) {
        console.error('Error loading address:', error);
    }
}

// Save Address
async function saveAddress() {
    const form = document.getElementById('addressForm');
    const formData = new FormData(form);
    const addressId = document.getElementById('addressId').value;
    
    if (addressId) {
        formData.append('action', 'update_address');
    } else {
        formData.append('action', 'add_address');
    }
    
    const messageDiv = document.getElementById('addressMessage');
    const saveBtn = event.target;
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    
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
                addressModal.hide();
                loadAddresses();
                form.reset();
            }, 1500);
        } else {
            messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>${data.message}
                </div>
            `;
        }
    } catch (error) {
        console.error('Error saving address:', error);
        messageDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>Error saving address. Please try again.
            </div>
        `;
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Address';
    }
}

// Set Default Address
async function setDefaultAddress(addressId) {
    const formData = new FormData();
    formData.append('action', 'update_address');
    formData.append('address_id', addressId);
    formData.append('is_default', '1');
    
    try {
        const response = await fetch('../ajax.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Default address updated', 'success');
            loadAddresses();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Error setting default:', error);
        showNotification('Error updating address', 'error');
    }
}

// Delete Address
async function deleteAddress(addressId) {
    if (!confirm('Are you sure you want to delete this address?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_address');
    formData.append('address_id', addressId);
    
    try {
        const response = await fetch('../ajax.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Address deleted successfully', 'success');
            loadAddresses();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting address:', error);
        showNotification('Error deleting address', 'error');
    }
}

// Show Empty State
function showEmptyState() {
    const grid = document.getElementById('addressesGrid');
    grid.innerHTML = `
        <div class="col-12">
            <div class="empty-addresses">
                <i class="fas fa-map-marked-alt"></i>
                <h3>No Addresses Yet</h3>
                <p>Add your first delivery address to get started</p>
                <button class="btn btn-primary btn-lg" onclick="showAddAddressModal()">
                    <i class="fas fa-plus me-2"></i>Add Your First Address
                </button>
            </div>
        </div>
    `;
}

// Show Error
function showError() {
    const grid = document.getElementById('addressesGrid');
    grid.innerHTML = `
        <div class="col-12">
            <div class="empty-addresses">
                <i class="fas fa-exclamation-triangle text-danger"></i>
                <h3>Error Loading Addresses</h3>
                <p>Unable to load your addresses. Please try again later.</p>
                <button class="btn btn-primary" onclick="loadAddresses()">
                    <i class="fas fa-redo me-2"></i>Retry
                </button>
            </div>
        </div>
    `;
}