// File: assets/js/app.js

// Utility Functions
function escapeHtml(unsafe) {
    return unsafe?.toString()?.replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;") || '';
}

function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const container = document.querySelector('main') || document.body;
    container.prepend(alertDiv);
    
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 5000);
}

// Enhanced Customer Entry Functions
function initCustomerEntry() {
    const phoneInput = document.getElementById('phone');
    const phoneResults = document.getElementById('phoneResults');
    const customerForm = document.getElementById('customerForm');

    if (!phoneInput) return;

    function loadCustomerData(customer_id) {
        fetch(`/modules/customer/get_customer_data.php?customer_id=${customer_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showAlert(data.error, 'danger');
                    return;
                }
                
                // Populate all form fields
                Object.keys(data).forEach(key => {
                    const element = document.querySelector(`[name="${key}"]`);
                    if (element) {
                        element.value = data[key] || '';
                    }
                });
                
                // Update UI state
                document.querySelector('h2').textContent = `Edit Customer ${data.customer_id}`;
                document.querySelector('input[name="customer_id"]').value = data.customer_id;
            })
            .catch(error => {
                console.error('Error loading customer:', error);
                showAlert('Failed to load customer data', 'danger');
            });
    }

    function doPhoneLookup() {
        const phone = phoneInput.value.trim();
        if (phone.length < 3) {
            phoneResults.classList.add('d-none');
            return;
        }
        
        fetch(`/modules/customer/entry_customer.php?phone_lookup=${encodeURIComponent(phone)}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                phoneResults.innerHTML = '';
                
                if (data.length > 0) {
                    data.forEach(customer => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action py-2';
                        item.innerHTML = `
                            <div class="d-flex justify-content-between">
                                <span><strong>${escapeHtml(customer.name)}</strong><br>
                                <small class="text-muted">${escapeHtml(customer.phone)}</small></span>
                                <span class="badge bg-primary align-self-center">${escapeHtml(customer.customer_id)}</span>
                            </div>
                        `;
                        item.addEventListener('click', () => {
                            loadCustomerData(customer.customer_id);
                            phoneResults.classList.add('d-none');
                        });
                        phoneResults.appendChild(item);
                    });
                    phoneResults.classList.remove('d-none');
                } else {
                    phoneResults.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                phoneResults.classList.add('d-none');
            });
    }

    // Live search on input
    phoneInput.addEventListener('input', debounce(doPhoneLookup, 300));
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!phoneResults.contains(e.target) && e.target !== phoneInput) {
            phoneResults.classList.add('d-none');
        }
    });

    // Form submission handling
    if (customerForm) {
        customerForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            }
        });
        
        customerForm.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        }, false);
    }
}

// Rest of your existing functions (unchanged)
function setupNavigationMemory() {
    // ... existing code ...
}

function initLoginPage() {
    // ... existing code ...
}

// Main DOM Loaded Handler
document.addEventListener('DOMContentLoaded', function() {
    // Initialize page-specific functionality
    if (document.getElementById('phone')) {
        initCustomerEntry();
    }
    initLoginPage();
    setupNavigationMemory();
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});