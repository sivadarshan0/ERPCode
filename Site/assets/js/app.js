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
    return function (...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

function showAlert(message, type = 'danger') {
    const alertContainer = document.querySelector('main') || document.body;
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    alertContainer.prepend(alertDiv);
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alertDiv);
        if (bsAlert) {
            bsAlert.close();
        }
    }, 5000);
}

function setupFormSubmitSpinner(formElement) {
    if (!formElement) return;
    formElement.addEventListener('submit', function (event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        } else {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            }
        }
        this.classList.add('was-validated');
    }, false);
}

// ───── Customer Entry Handler ─────
function initCustomerEntry() {
    const phoneInput = document.getElementById('phone');
    const phoneResults = document.getElementById('phoneResults');
    if (!phoneInput) return;

    const doPhoneLookup = debounce(() => {
        const phone = phoneInput.value.trim();
        if (phone.length < 3) {
            phoneResults.classList.add('d-none');
            return;
        }
        fetch(`/modules/customer/entry_customer.php?phone_lookup=${encodeURIComponent(phone)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Phone search failed'))
            .then(data => {
                phoneResults.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(customer => {
                        const item = document.createElement('a');
                        item.href = `entry_customer.php?customer_id=${encodeURIComponent(customer.customer_id)}`;
                        item.className = 'list-group-item list-group-item-action py-2';
                        item.innerHTML = `<div class="d-flex justify-content-between"><span><strong>${escapeHtml(customer.name)}</strong><br><small class="text-muted">${escapeHtml(customer.phone)}</small></span><span class="badge bg-primary align-self-center">${escapeHtml(customer.customer_id)}</span></div>`;
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
    }, 300);

    phoneInput.addEventListener('input', doPhoneLookup);
    document.addEventListener('click', (e) => {
        if (!phoneResults.contains(e.target) && e.target !== phoneInput) {
            phoneResults.classList.add('d-none');
        }
    });
}

// ───── Category Entry Handler ─────
function initCategoryEntry() {
    const nameInput = document.getElementById('name');
    const categoryResults = document.getElementById('categoryResults');
    if (!nameInput) return;

    const doCategoryLookup = debounce(() => {
        const name = nameInput.value.trim();
        if (name.length < 2) {
            categoryResults.classList.add('d-none');
            return;
        }
        fetch(`/modules/inventory/entry_category.php?category_lookup=${encodeURIComponent(name)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Category search failed'))
            .then(data => {
                categoryResults.innerHTML = '';
                if (data.error) throw new Error(data.error);
                if (data.length > 0) {
                    data.forEach(category => {
                        const item = document.createElement('a');
                        item.href = `entry_category.php?category_id=${encodeURIComponent(category.category_id)}`;
                        item.className = 'list-group-item list-group-item-action py-2';
                        item.innerHTML = `<div class="d-flex justify-content-between"><span><strong>${escapeHtml(category.name)}</strong><br><small class="text-muted">${escapeHtml(category.description || 'No description')}</small></span><span class="badge bg-primary align-self-center">${escapeHtml(category.category_id)}</span></div>`;
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
    document.addEventListener('click', (e) => {
        if (!categoryResults.contains(e.target) && e.target !== nameInput) {
            categoryResults.classList.add('d-none');
        }
    });
}

// ───── Sub-Category Entry Handler ─────
function initSubCategoryEntry() {
    const nameInput = document.getElementById('name');
    const subCategoryResults = document.getElementById('subCategoryResults');
    if (!nameInput) return;

    const doSubCategoryLookup = debounce(() => {
        const name = nameInput.value.trim();
        if (name.length < 2) {
            subCategoryResults.classList.add('d-none');
            return;
        }
        fetch(`/modules/inventory/entry_category_sub.php?sub_category_lookup=${encodeURIComponent(name)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Sub-Category search failed'))
            .then(data => {
                subCategoryResults.innerHTML = '';
                if (data.error) throw new Error(data.error);
                if (data.length > 0) {
                    data.forEach(sub_cat => {
                        const item = document.createElement('a');
                        item.href = `entry_category_sub.php?category_sub_id=${encodeURIComponent(sub_cat.category_sub_id)}`;
                        item.className = 'list-group-item list-group-item-action py-2';
                        item.innerHTML = `<div class="d-flex justify-content-between"><span><strong>${escapeHtml(sub_cat.name)}</strong><br><small class="text-muted">Parent: ${escapeHtml(sub_cat.parent_category_name)}</small></span><span class="badge bg-primary align-self-center">${escapeHtml(sub_cat.category_sub_id)}</span></div>`;
                        subCategoryResults.appendChild(item);
                    });
                    subCategoryResults.classList.remove('d-none');
                } else {
                    subCategoryResults.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('[doSubCategoryLookup] Error:', error);
                subCategoryResults.classList.add('d-none');
            });
    }, 300);

    nameInput.addEventListener('input', doSubCategoryLookup);
    document.addEventListener('click', (e) => {
        if (!subCategoryResults.contains(e.target) && e.target !== nameInput) {
            subCategoryResults.classList.add('d-none');
        }
    });
}

// ───── Item Entry Handler ─────
function initItemEntry() {
    const itemForm = document.getElementById('itemForm');
    if (!itemForm) return;

    const categorySelect = document.getElementById('category_id');
    const subCategorySelect = document.getElementById('category_sub_id');
    const nameInput = document.getElementById('name');
    const itemResults = document.getElementById('itemResults');

    categorySelect.addEventListener('change', function () {
        const categoryId = this.value;
        subCategorySelect.innerHTML = '<option value="">Loading...</option>';
        subCategorySelect.disabled = true;

        if (!categoryId) {
            subCategorySelect.innerHTML = '<option value="">Choose Sub-Category...</option>';
            return;
        }

        fetch(`/modules/inventory/entry_item.php?get_sub_categories=${encodeURIComponent(categoryId)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Failed to load sub-categories'))
            .then(data => {
                subCategorySelect.innerHTML = '<option value="">Choose Sub-Category...</option>';
                if (data.length > 0) {
                    data.forEach(sub_cat => {
                        subCategorySelect.add(new Option(sub_cat.name, sub_cat.category_sub_id));
                    });
                }
                subCategorySelect.disabled = false;
            })
            .catch(error => {
                console.error('[CascadingDropdown] Error:', error);
                subCategorySelect.innerHTML = '<option value="">Error loading data</option>';
            });
    });

    const doItemLookup = debounce(() => {
        const name = nameInput.value.trim();
        if (name.length < 2) {
            itemResults.classList.add('d-none');
            return;
        }

        fetch(`/modules/inventory/entry_item.php?item_lookup=${encodeURIComponent(name)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Item search failed'))
            .then(data => {
                itemResults.innerHTML = '';
                if (data.error) throw new Error(data.error);
                if (data.length > 0) {
                    data.forEach(item => {
                        const link = document.createElement('a');
                        link.href = `entry_item.php?item_id=${encodeURIComponent(item.item_id)}`;
                        link.className = 'list-group-item list-group-item-action py-2';
                        link.innerHTML = `<div class="d-flex justify-content-between"><span><strong>${escapeHtml(item.name)}</strong><br><small class="text-muted">${escapeHtml(item.parent_category_name)} > ${escapeHtml(item.sub_category_name)}</small></span><span class="badge bg-primary align-self-center">${escapeHtml(item.item_id)}</span></div>`;
                        itemResults.appendChild(link);
                    });
                    itemResults.classList.remove('d-none');
                } else {
                    itemResults.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('[doItemLookup] Error:', error);
                itemResults.classList.add('d-none');
            });
    }, 300);

    nameInput.addEventListener('input', doItemLookup);
    document.addEventListener('click', (e) => {
        if (!itemResults.contains(e.target) && e.target !== nameInput) {
            itemResults.classList.add('d-none');
        }
    });
}

// ───── Stock Adjustment Handler ─────
function initStockAdjustmentEntry() {
    const form = document.getElementById('stockAdjustmentForm');
    if (!form) return;

    const searchInput = document.getElementById('item_search');
    const resultsContainer = document.getElementById('itemResults');
    const hiddenItemId = document.getElementById('item_id');
    const selectedItemDisplay = document.getElementById('selected_item_display');

    const doItemLookup = debounce(() => {
        const name = searchInput.value.trim();
        if (name.length < 2) {
            resultsContainer.classList.add('d-none');
            return;
        }

        fetch(`entry_stock_adjustment.php?item_lookup=${encodeURIComponent(name)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Item search failed'))
            .then(data => {
                resultsContainer.innerHTML = '';
                if (data.error) throw new Error(data.error);

                if (data.length > 0) {
                    data.forEach(item => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'list-group-item list-group-item-action py-2';
                        // CORRECTED: Use `item.name` here, not `item.item_name`
                        button.innerHTML = `<div class="d-flex justify-content-between"><span><strong>${escapeHtml(item.name)}</strong><br><small class="text-muted">${escapeHtml(item.parent_category_name)} > ${escapeHtml(item.sub_category_name)}</small></span><span class="badge bg-primary align-self-center">${escapeHtml(item.item_id)}</span></div>`;
                        button.addEventListener('click', () => {
                            // CORRECTED: Use `item.name` here as well
                            searchInput.value = item.name;
                            hiddenItemId.value = item.item_id;
                            selectedItemDisplay.innerHTML = `Selected Item ID: <strong>${escapeHtml(item.item_id)}</strong>`;
                            selectedItemDisplay.classList.remove('d-none');
                            resultsContainer.classList.add('d-none');
                        });
                        resultsContainer.appendChild(button);
                    });
                    resultsContainer.classList.remove('d-none');
                } else {
                    resultsContainer.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('[StockItemLookup] Error:', error);
                resultsContainer.classList.add('d-none');
            });
    }, 300);

    searchInput.addEventListener('input', doItemLookup);

    document.addEventListener('click', (e) => {
        if (!resultsContainer.contains(e.target) && e.target !== searchInput) {
            resultsContainer.classList.add('d-none');
        }
    });
}

// ───── GRN Entry Handler ─────
function initGrnEntry() {
    const form = document.getElementById('grnForm');
    if (!form) return;

    const itemRowsContainer = document.getElementById('grnItemRows');
    const template = document.getElementById('grnItemRowTemplate');
    const addRowBtn = document.getElementById('addItemRow');

    const addRow = () => {
        const newRow = template.content.cloneNode(true);
        itemRowsContainer.appendChild(newRow);
    };

    addRow(); // Add initial row

    addRowBtn.addEventListener('click', addRow);

    itemRowsContainer.addEventListener('click', function (e) {
        if (e.target.closest('.remove-item-row')) {
            e.target.closest('.grn-item-row').remove();
        }
    });

    itemRowsContainer.addEventListener('input', debounce((e) => {
        if (e.target.classList.contains('item-search-input')) {
            const searchInput = e.target;
            const resultsContainer = searchInput.nextElementSibling;
            const name = searchInput.value.trim();

            if (name.length < 2) {
                resultsContainer.classList.add('d-none');
                return;
            }

            fetch(`entry_grn.php?item_lookup=${encodeURIComponent(name)}`)
                .then(response => response.ok ? response.json() : Promise.reject('Item search failed'))
                .then(data => {
                    resultsContainer.innerHTML = '';
                    if (data.error) throw new Error(data.error);
                    if (data.length > 0) {
                        data.forEach(item => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'list-group-item list-group-item-action py-2';
                            button.innerHTML = `<strong>${escapeHtml(item.name)}</strong> <small class="text-muted">(${escapeHtml(item.item_id)})</small>`;
                            
                            button.addEventListener('click', () => {
                                const parentRow = searchInput.closest('.grn-item-row');
                                parentRow.querySelector('.item-id-input').value = item.item_id;
                                searchInput.value = item.name;
                                
                                // MODIFIED: This now sets the value of the readonly text input
                                const uomInput = parentRow.querySelector('.uom-input');
                                if (uomInput && item.uom) {
                                    uomInput.value = item.uom;
                                }
                                
                                resultsContainer.classList.add('d-none');
                            });
                            resultsContainer.appendChild(button);
                        });
                        resultsContainer.classList.remove('d-none');
                    } else {
                        resultsContainer.classList.add('d-none');
                    }
                })
                .catch(error => {
                    console.error('[GRNItemLookup] Error:', error);
                    resultsContainer.classList.add('d-none');
                });
        }
    }, 300));
}

// ───── DOM Ready ─────
document.addEventListener('DOMContentLoaded', function () {
    // Initialize modules based on which form is present on the page.
    if (document.getElementById('customerForm')) {
        initCustomerEntry();
    }
    if (document.getElementById('categoryForm')) {
        initCategoryEntry();
    }
    if (document.getElementById('subCategoryForm')) {
        initSubCategoryEntry();
    }
    if (document.getElementById('itemForm')) {
        initItemEntry();
    }
    if (document.getElementById('stockAdjustmentForm')) {
        initStockAdjustmentEntry();
    }
    if (document.getElementById('grnForm')) {
        initGrnEntry();
    }

    // Initialize live search for table pages.
    if (document.querySelector('.live-search')) {
        initLiveSearch();
    }

    // Attach standardized spinner logic to all forms.
    setupFormSubmitSpinner(document.getElementById('customerForm'));
    setupFormSubmitSpinner(document.getElementById('categoryForm'));
    setupFormSubmitSpinner(document.getElementById('subCategoryForm'));
    setupFormSubmitSpinner(document.getElementById('itemForm'));
    setupFormSubmitSpinner(document.getElementById('stockAdjustmentForm'));
    setupFormSubmitSpinner(document.getElementById('grnForm'));

    // Auto-hide any static alerts that were loaded with the page.
    const staticAlerts = document.querySelectorAll('.alert-dismissible');
    staticAlerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
});