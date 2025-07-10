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

// Customer Entry Functions
function initCustomerEntry() {
    const phoneInput = document.getElementById('phone');
    const phoneLookupBtn = document.getElementById('phoneLookupBtn');
    const phoneResults = document.getElementById('phoneResults');
    const customerForm = document.getElementById('customerForm');

    if (!phoneInput) return;

    function doPhoneLookup() {
        const phone = phoneInput.value.trim();
        if (phone.length < 3) {
            phoneResults.classList.add('d-none');
            return;
        }
        
        fetch(`/modules/customer/entry_customer.php?phone_lookup=${encodeURIComponent(phone)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showAlert(data.error, 'danger');
                    return;
                }
                
                phoneResults.innerHTML = '';
                
                if (data.length > 0) {
                    data.forEach(customer => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `
                            <strong>${escapeHtml(customer.name)}</strong><br>
                            <small>${escapeHtml(customer.phone)}</small>
                            <span class="float-end badge bg-primary">${escapeHtml(customer.customer_id)}</span>
                        `;
                        item.addEventListener('click', function() {
                            window.location.href = `entry_customer.php?customer_id=${customer.customer_id}`;
                        });
                        phoneResults.appendChild(item);
                    });
                    phoneResults.classList.remove('d-none');
                } else {
                    phoneResults.classList.add('d-none');
                }
            })
            .catch(error => {
                showAlert('Error searching customers', 'danger');
                console.error('Error:', error);
            });
    }

    if (phoneLookupBtn) {
        phoneLookupBtn.addEventListener('click', doPhoneLookup);
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', debounce(doPhoneLookup, 500));
    }

    document.addEventListener('click', function(e) {
        if (phoneResults && !phoneResults.contains(e.target) && e.target !== phoneInput && e.target !== phoneLookupBtn) {
            phoneResults.classList.add('d-none');
        }
    });

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

// Navigation Memory
function setupNavigationMemory() {
    document.querySelectorAll('.customer-edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            localStorage.setItem('lastCustomerPage', window.location.href);
        });
    });

    if (window.location.pathname.includes('entry_customer.php') && window.location.search.includes('customer_id')) {
        const backBtn = document.getElementById('backToList');
        if (backBtn) {
            const lastPage = localStorage.getItem('lastCustomerPage');
            if (lastPage) {
                backBtn.href = lastPage;
            }
        }
    }
}

// Login Page Functionality
function initLoginPage() {
    const togglePassword = document.querySelector('.toggle-password');
    const passwordInput = document.getElementById('password');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    }
    
    const usernameField = document.getElementById('username');
    if (usernameField) {
        usernameField.focus();
    }
}

// Main DOM Loaded Handler
document.addEventListener('DOMContentLoaded', function() {
    // Initialize page-specific functionality
    initLoginPage();
    initCustomerEntry();
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