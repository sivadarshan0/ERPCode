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
    return function () {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

function showAlert(message, type = 'danger') {
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
        console.log('[loadCustomerData] Fetching for ID:', customer_id);

        fetch(`/modules/customer/get_customer_data.php?customer_id=${encodeURIComponent(customer_id)}`)
            .then(response => {
                console.log('[loadCustomerData] HTTP status:', response.status);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                console.log('[loadCustomerData] Response JSON:', data);

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
                console.error('[loadCustomerData] Error:', error);
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
                if (!response.ok) throw new Error('Phone search failed');
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
                            console.log('[PhoneLookup] Selected customer:', customer.customer_id);
                            window.location.href = `entry_customer.php?customer_id=${encodeURIComponent(customer.customer_id)}`;
                        });
                        phoneResults.appendChild(item);
                    });
                    phoneResults.classList.remove('d-none');
                } else {
                    phoneResults.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('[doPhoneLookup] Error:', error);
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
        customerForm.addEventListener('submit', function (event) {
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

// ───── Table Live Search Functionality ─────
function initLiveSearch() {
    const inputs = document.querySelectorAll(".live-search");
    if (inputs.length === 0) return;

    const debouncedSearch = debounce(() => {
        const params = new URLSearchParams();
        inputs.forEach(inp => {
            if (inp.value.trim()) {
                params.set(inp.dataset.column, inp.value.trim());
            }
        });

        // Also include the select filter
        const sourceFilter = document.querySelector(".live-search-select");
        if (sourceFilter && sourceFilter.value) {
            params.set(sourceFilter.dataset.column, sourceFilter.value);
        }

        fetch("/modules/customer/search_customers.php?" + params.toString())
            .then(res => {
                if (!res.ok) throw new Error('Search failed');
                return res.json();
            })
            .then(data => {
                const tbody = document.querySelector("table tbody");
                tbody.innerHTML = "";

                if (data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No results found</td></tr>`;
                    return;
                }

                data.forEach(cust => {
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td>${escapeHtml(cust.customer_id)}</td>
                        <td>${escapeHtml(cust.name)}</td>
                        <td>${escapeHtml(cust.phone)}</td>
                        <td>${escapeHtml(cust.city || '')}</td>
                        <td>${escapeHtml(cust.district || '')}</td>
                        <td>${escapeHtml(cust.known_by || '')}</td>
                        <td>
                            <a href="entry_customer.php?customer_id=${cust.customer_id}" 
                               class="btn btn-sm btn-outline-primary customer-edit-btn">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('[LiveSearch] Error:', error);
                showAlert('Search failed. Please try again.', 'danger');
            });
    }, 300);

    inputs.forEach(input => {
        input.addEventListener("input", debouncedSearch);
    });

    const sourceFilter = document.querySelector(".live-search-select");
    if (sourceFilter) {
        sourceFilter.addEventListener("change", debouncedSearch);
    }
}

// ───── NEW: Category Entry Handler ─────
function initCategoryEntry() {
    const nameInput = document.getElementById('name');
    const categoryResults = document.getElementById('categoryResults');
    const categoryForm = document.getElementById('categoryForm');

    if (!nameInput) return; // Exit if the required elements aren't on the page

    // Live category name search
    const doCategoryLookup = debounce(() => {
        const name = nameInput.value.trim();
        if (name.length < 2) {
            categoryResults.classList.add('d-none');
            return;
        }

        // The AJAX endpoint is the page itself, with a specific query parameter
        fetch(`/modules/category/entry_category.php?category_lookup=${encodeURIComponent(name)}`)
            .then(response => {
                if (!response.ok) throw new Error('Category search failed');
                return response.json();
            })
            .then(data => {
                categoryResults.innerHTML = '';

                if (data.error) {
                    console.error('Search Error:', data.error);
                    categoryResults.classList.add('d-none');
                    return;
                }

                if (data.length > 0) {
                    data.forEach(category => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action py-2';
                        item.innerHTML = `
                            <div class="d-flex justify-content-between">
                                <span><strong>${escapeHtml(category.name)}</strong><br>
                                <small class="text-muted">${escapeHtml(category.description || 'No description')}</small></span>
                                <span class="badge bg-primary align-self-center">${escapeHtml(category.category_id)}</span>
                            </div>
                        `;
                        item.addEventListener('click', () => {
                            // Redirect to the edit page for the selected category
                            window.location.href = `entry_category.php?category_id=${encodeURIComponent(category.category_id)}`;
                        });
                        categoryResults.appendChild(item);
                    });
                    categoryResults.classList.remove('d-none');
                } else {
                    categoryResults.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('[doCategoryLookup] Error:', error);
                categoryResults.classList.add('d-none');
            });
    }, 300);

    nameInput.addEventListener('input', doCategoryLookup);

    // Hide results when clicking elsewhere
    document.addEventListener('click', (e) => {
        if (!categoryResults.contains(e.target) && e.target !== nameInput) {
            categoryResults.classList.add('d-none');
        }
    });

    // Form validation + loading spinner on submit
    if (categoryForm) {
        categoryForm.addEventListener('submit', function (event) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            }

            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                // Re-enable button if validation fails client-side
                if(submitBtn) {
                   submitBtn.disabled = false;
                   submitBtn.innerHTML = this.querySelector('input[name="category_id"]').value ? 'Update Category' : 'Create Category';
                }
            }
            this.classList.add('was-validated');
        }, false);
    }
}


// ───── DOM Ready (UPDATED) ─────
document.addEventListener('DOMContentLoaded', function () {
    // Initialize modules based on unique page elements
    if (document.getElementById('customerForm')) {
        initCustomerEntry();
    }
    
    // ADD THIS:
    if (document.getElementById('categoryForm')) {
        initCategoryEntry();
    }

    if (document.querySelector('.live-search')) {
        initLiveSearch();
    }

    // Auto-hide bootstrap alerts (can be removed if using showAlert exclusively)
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if(bsAlert) bsAlert.close();
        }, 5000);
    });
});