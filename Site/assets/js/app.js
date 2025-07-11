// File: assets/js/app.js

// ───── Utility Functions ─────
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

// ───── Enhanced Customer Entry Handler ─────
function initCustomerEntry() {
    const phoneInput = document.getElementById('phone');
    const phoneResults = document.getElementById('phoneResults');
    const customerForm = document.getElementById('customerForm');

    if (!phoneInput) return;

    // Load customer data into form
    function loadCustomerData(customer_id) {
        fetch(`/modules/customer/get_customer_data.php?customer_id=${customer_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showAlert(data.error, 'danger');
                    return;
                }

                Object.keys(data).forEach(key => {
                    const element = document.querySelector(`[name="${key}"]`);
                    if (element) element.value = data[key] || '';
                });

                const header = document.querySelector('h2');
                if (header) header.textContent = `Edit Customer ${data.customer_id}`;

                const hiddenId = document.querySelector('input[name="customer_id"]');
                if (hiddenId) hiddenId.value = data.customer_id;
            })
            .catch(error => {
                console.error('Error loading customer:', error);
                showAlert('Failed to load customer data', 'danger');
            });
    }

    // Live phone number search
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

    phoneInput.addEventListener('input', debounce(doPhoneLookup, 300));

    document.addEventListener('click', (e) => {
        if (!phoneResults.contains(e.target) && e.target !== phoneInput) {
            phoneResults.classList.add('d-none');
        }
    });

    // Form validation + loading spinner
    if (customerForm) {
        customerForm.addEventListener('submit', function(event) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            }

            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            this.classList.add('was-validated');
        }, false);
    }
}

// ───── Optional: Navigation & Login page handlers ─────
function setupNavigationMemory() {
    // Placeholder for nav memory (if implemented)
}

function initLoginPage() {
    // Placeholder for login enhancements (if implemented)
}

// ───── DOM Ready ─────
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('phone')) {
        initCustomerEntry();
    }

    initLoginPage();
    setupNavigationMemory();

    // Auto-hide bootstrap alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
