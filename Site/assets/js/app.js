// File: assets/js/app.js
// Final validated version with all modules and all UI/UX refinements.

console.log('app.js has loaded.');

function showAlert(message, type = 'danger') {
    const alertContainer = document.querySelector('main') || document.body;
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    alertContainer.prepend(alertDiv);
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alertDiv);
        if (bsAlert) { bsAlert.close(); }
    }, 5000);
}

function setupFormSubmitSpinner(formElement) {
    if (!formElement) {
        // This is a safety check, not part of the main debug
        return; 
    }

    // --- DEBUGGING FORM SUBMISSION ---
    console.log('Attaching submit listener to form:', formElement.id);

    formElement.addEventListener('submit', function (event) {
        console.log('Submit event fired for form:', this.id);

        if (!this.checkValidity()) {
            console.log('Form is INVALID. Preventing submission.');
            event.preventDefault();
            event.stopPropagation();
        } else {
            console.log('Form is VALID. Allowing submission.');
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
        if (phone.length < 3) { if (phoneResults) phoneResults.classList.add('d-none'); return; }
        fetch(`/modules/customer/entry_customer.php?phone_lookup=${encodeURIComponent(phone)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Phone search failed'))
            .then(data => {
                if (!phoneResults) return;
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
                } else { phoneResults.classList.add('d-none'); }
            })
            .catch(error => { console.error('[doPhoneLookup] Error:', error); if (phoneResults) phoneResults.classList.add('d-none'); });
    }, 300);
    phoneInput.addEventListener('input', doPhoneLookup);
    document.addEventListener('click', (e) => {
        if (phoneResults && !phoneResults.contains(e.target) && e.target !== phoneInput) {
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
        if (name.length < 2) { if (categoryResults) categoryResults.classList.add('d-none'); return; }
        fetch(`/modules/inventory/entry_category.php?category_lookup=${encodeURIComponent(name)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Category search failed'))
            .then(data => {
                if (!categoryResults) return;
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
                } else { categoryResults.classList.add('d-none'); }
            })
            .catch(error => { console.error('[doCategoryLookup] Error:', error); if (categoryResults) categoryResults.classList.add('d-none'); });
    }, 300);
    nameInput.addEventListener('input', doCategoryLookup);
    document.addEventListener('click', (e) => {
        if (categoryResults && !categoryResults.contains(e.target) && e.target !== nameInput) {
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
        if (name.length < 2) { if (subCategoryResults) subCategoryResults.classList.add('d-none'); return; }
        fetch(`/modules/inventory/entry_category_sub.php?sub_category_lookup=${encodeURIComponent(name)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Sub-Category search failed'))
            .then(data => {
                if (!subCategoryResults) return;
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
                } else { subCategoryResults.classList.add('d-none'); }
            })
            .catch(error => { console.error('[doSubCategoryLookup] Error:', error); if (subCategoryResults) subCategoryResults.classList.add('d-none'); });
    }, 300);
    nameInput.addEventListener('input', doSubCategoryLookup);
    document.addEventListener('click', (e) => {
        if (subCategoryResults && !subCategoryResults.contains(e.target) && e.target !== nameInput) {
            subCategoryResults.classList.add('d-none');
        }
    });
}

// -----------------------------------------
// ----- UNIFIED Item Management Page Handler -----
// This function handles the "Manage Item" page (entry_item.php)
// -----------------------------------------

function initItemManagement() {
    const form = document.getElementById('itemForm');
    if (!form) return;

    // --- Part 1: Cascading Dropdown and Live Search Logic (from old initItemEntry) ---
    const categorySelect = document.getElementById('category_id');
    const subCategorySelect = document.getElementById('category_sub_id');
    const nameInput = document.getElementById('name');
    const itemResults = document.getElementById('itemResults');

    if (categorySelect && subCategorySelect) {
        categorySelect.addEventListener('change', function () {
            const categoryId = this.value;
            subCategorySelect.innerHTML = '<option value="">Loading...</option>';
            subCategorySelect.disabled = true;
            if (!categoryId) { subCategorySelect.innerHTML = '<option value="">Choose Sub-Category...</option>'; return; }
            
            fetch(`/modules/inventory/entry_item.php?get_sub_categories=${encodeURIComponent(categoryId)}`)
                .then(response => response.ok ? response.json() : Promise.reject('Failed to load sub-categories'))
                .then(data => {
                    subCategorySelect.innerHTML = '<option value="">Choose Sub-Category...</option>';
                    if (data.length > 0) {
                        data.forEach(sub_cat => { subCategorySelect.add(new Option(sub_cat.name, sub_cat.category_sub_id)); });
                    }
                    subCategorySelect.disabled = false;
                })
                .catch(error => { console.error('[CascadingDropdown] Error:', error); subCategorySelect.innerHTML = '<option value="">Error loading data</option>'; });
        });
    }

    if (nameInput && itemResults) {
        const doItemLookup = debounce(() => {
            const name = nameInput.value.trim();
            if (name.length < 2) { itemResults.classList.add('d-none'); return; }
            
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
                    } else { itemResults.classList.add('d-none'); }
                })
                .catch(error => { console.error('[doItemLookup] Error:', error); itemResults.classList.add('d-none'); });
        }, 300);
        nameInput.addEventListener('input', doItemLookup);

        document.addEventListener('click', (e) => {
            if (itemResults && !itemResults.contains(e.target) && e.target !== nameInput) {
                itemResults.classList.add('d-none');
            }
        });
    }

    // --- Part 2: New Image Management Click Handling ---
    const imageManagementSection = document.getElementById('imageManagementSection');
    const imageActionInput = document.getElementById('imageAction');
    const imagePathInput = document.getElementById('imagePath');

    if (imageManagementSection) {
        imageManagementSection.addEventListener('click', function(event) {
            // Use .closest() to ensure the click is handled even if the user clicks the icon inside the button
            const button = event.target.closest('button');
            if (!button) return;

            if (button.classList.contains('btn-delete-image')) {
                if (confirm('Are you sure you want to permanently delete this image?')) {
                    imageActionInput.value = 'delete_image';
                    imagePathInput.value = button.dataset.imagePath;
                    form.submit();
                }
            } else if (button.classList.contains('btn-set-main')) {
                imageActionInput.value = 'set_main_image';
                imagePathInput.value = button.dataset.imagePath;
                form.submit();
            }
        });
    }
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
        if (name.length < 2) { if (resultsContainer) resultsContainer.classList.add('d-none'); return; }
        fetch(`/modules/inventory/entry_stock_adjustment.php?item_lookup=${encodeURIComponent(name)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Item search failed'))
            .then(data => {
                if (!resultsContainer) return;
                resultsContainer.innerHTML = '';
                if (data.error) throw new Error(data.error);
                if (data.length > 0) {
                    data.forEach(item => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'list-group-item list-group-item-action py-2';
                        button.innerHTML = `<div class="d-flex justify-content-between"><span><strong>${escapeHtml(item.name)}</strong><br><small class="text-muted">${escapeHtml(item.parent_category_name)} > ${escapeHtml(item.sub_category_name)}</small></span><span class="badge bg-primary align-self-center">${escapeHtml(item.item_id)}</span></div>`;
                        button.addEventListener('click', () => {
                            searchInput.value = item.name;
                            hiddenItemId.value = item.item_id;
                            selectedItemDisplay.innerHTML = `Selected Item ID: <strong>${escapeHtml(item.item_id)}</strong>`;
                            selectedItemDisplay.classList.remove('d-none');
                            resultsContainer.classList.add('d-none');
                        });
                        resultsContainer.appendChild(button);
                    });
                    resultsContainer.classList.remove('d-none');
                } else { resultsContainer.classList.add('d-none'); }
            })
            .catch(error => { console.error('[StockItemLookup] Error:', error); if (resultsContainer) resultsContainer.classList.add('d-none'); });
    }, 300);
    searchInput.addEventListener('input', doItemLookup);
    document.addEventListener('click', (e) => {
        if (resultsContainer && !resultsContainer.contains(e.target) && e.target !== searchInput) {
            resultsContainer.classList.add('d-none');
        }
    });
}

// -----------------------------------------
// ----- NEW: Unified GRN Page Handler -----
// -----------------------------------------

function initGrnPage() {
    const form = document.getElementById('grnForm');
    if (!form) return;

    const addRowBtn = document.getElementById('addItemRow');
    const cancelBtn = document.getElementById('cancelGrnBtn');

    // --- LOGIC FOR "CREATE" MODE ---
    if (addRowBtn) {
        const itemRowsContainer = document.getElementById('grnItemRows');
        const template = document.getElementById('grnItemRowTemplate');
        
        const addRow = () => {
            if (!template) return;
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
                if (name.length < 2) { resultsContainer.classList.add('d-none'); return; }

                fetch(`/modules/purchase/entry_grn.php?action=item_lookup&query=${encodeURIComponent(name)}`)
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
                                    const uomInput = parentRow.querySelector('.uom-input');
                                    if (uomInput && item.uom) { uomInput.value = item.uom; }
                                    resultsContainer.classList.add('d-none');
                                });
                                resultsContainer.appendChild(button);
                            });
                            resultsContainer.classList.remove('d-none');
                        } else { resultsContainer.classList.add('d-none'); }
                    })
                    .catch(error => { console.error('[GRNItemLookup] Error:', error); resultsContainer.classList.add('d-none'); });
            }
        }, 300));
    }

    // --- LOGIC FOR "VIEW" MODE (CANCELLATION) ---
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            const grnId = this.dataset.grnId;
            const csrfToken = this.dataset.csrfToken;

            if (!confirm(`Are you sure you want to cancel GRN #${grnId}?\nThis action will reverse the stock adjustments and cannot be undone.`)) {
                return;
            }

            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Canceling...';
            
            const formData = new FormData();
            formData.append('grn_id', grnId);
            formData.append('csrf_token', csrfToken); // Add CSRF token

            fetch('/modules/purchase/entry_grn.php?action=cancel_grn', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    this.remove();
                    const pageTitle = document.querySelector('h2');
                    if (pageTitle) {
                        pageTitle.innerHTML += ' <span class="badge bg-danger">Canceled</span>';
                    }
                } else {
                    showAlert(data.error, 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-x-circle"></i> Cancel GRN';
                }
            })
            .catch(error => {
                showAlert('A network error occurred or the response was not valid JSON. Please try again.', 'danger');
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-x-circle"></i> Cancel GRN';
                console.error('Cancellation Error:', error);
            });
        });
    }
}

// ───── Order Entry Handler ─────
function initOrderEntry() {
    const form = document.getElementById('orderForm');
    if (!form) return;
    const isEditMode = !!document.querySelector('input[name="order_id"]');
    const stockTypeSelect = document.getElementById('stock_type');
    const customerSearchInput = document.getElementById('customer_search');
    const customerResults = document.getElementById('customerResults');
    const hiddenCustomerId = document.getElementById('customer_id');
    const selectedCustomerDisplay = document.getElementById('selected_customer_display');
    const itemRowsContainer = document.getElementById('orderItemRows');
    const template = document.getElementById('orderItemRowTemplate');
    const addRowBtn = document.getElementById('addItemRow');
    //const itemsTotalDisplay = document.getElementById('itemsTotalDisplay');
    //const orderTotalDisplay = document.getElementById('orderTotalDisplay');
    const otherExpensesInput = document.getElementById('other_expenses');
    const createOrderBtn = document.querySelector('#orderForm button[type="submit"]');
    const orderStatusSelect = document.getElementById('order_status');
    const paymentStatusSelect = document.getElementById('payment_status');

    const toggleRowFields = () => {
        if (!stockTypeSelect || !itemRowsContainer) return;
        const isPreBook = stockTypeSelect.value === 'Pre-Book';
        const stockHeader = form.querySelector('th.stock-col');
        if (stockHeader) { stockHeader.classList.toggle('d-none', isPreBook); }
        itemRowsContainer.querySelectorAll('.order-item-row').forEach(row => {
            const stockDisplay = row.querySelector('.stock-display');
            if (stockDisplay) { stockDisplay.closest('td').classList.toggle('d-none', isPreBook); }
            const costDisplayInput = row.querySelector('.cost-display');
            if (costDisplayInput) {
                costDisplayInput.readOnly = !isPreBook;
            }
        });
    };

    const validateRowStock = (row) => {
        if (!stockTypeSelect || !row) return;
        const stockType = stockTypeSelect.value;
        const stockWarning = row.querySelector('.stock-warning');
        if (!stockWarning) return;
        if (stockType === 'Ex-Stock') {
            const availableStock = parseFloat(row.querySelector('.stock-display')?.value) || 0;
            const orderQuantity = parseFloat(row.querySelector('.quantity-input')?.value) || 0;
            stockWarning.classList.toggle('d-none', orderQuantity <= availableStock);
        } else {
            stockWarning.classList.add('d-none');
        }
    };

    // --- NEW, ROBUST CALCULATION FUNCTION ---
    const calculateTotals = () => {
        // Create a number formatter for consistency.
        const itemsTotalDisplay = document.getElementById('itemsTotalDisplay');
        const orderTotalDisplay = document.getElementById('orderTotalDisplay');
        const formatter = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        let itemsTotal = 0;
        if (itemRowsContainer) {
            itemRowsContainer.querySelectorAll('.order-item-row').forEach(row => {
                const priceEl = row.querySelector('.price-input') || row.querySelector('td:nth-child(6)');
                const quantityEl = row.querySelector('.quantity-input') || row.querySelector('td:nth-child(7)');
                const subtotalDisplay = row.querySelector('.subtotal-display');
                const costInput = row.querySelector('.cost-input');
                const marginDisplay = row.querySelector('.margin-display');

                const price = parseFloat(priceEl.value || priceEl.textContent.replace(/,/g, '')) || 0;
                const quantity = parseFloat(quantityEl.value || quantityEl.textContent.replace(/,/g, '')) || 0;
                const cost = parseFloat(costInput ? costInput.value : 0) || 0;

                const subtotal = price * quantity;
                if (subtotalDisplay) {
                    // Use the formatter for the subtotal
                    subtotalDisplay.textContent = formatter.format(subtotal);
                }
                itemsTotal += subtotal;
                
                if (marginDisplay) {
                    const margin = (cost > 0 && price > 0) ? (((price / cost) - 1) * 100) : 0;
                    if (marginDisplay.tagName === 'INPUT') {
                        marginDisplay.value = margin.toFixed(2);
                    }
                }
            });
        }
        
        if (itemsTotalDisplay) {
            // Use the formatter for the items total
            itemsTotalDisplay.textContent = formatter.format(itemsTotal);
        }
        
        const grandTotal = itemsTotal; // Total does not include other expenses
        
        if (orderTotalDisplay) {
            // Use the formatter for the grand total
            orderTotalDisplay.textContent = formatter.format(grandTotal);
        }
    };
    // --- END NEW CALCULATION FUNCTION ---

    // Event listener for editable item rows
    if (itemRowsContainer) {
        itemRowsContainer.addEventListener('input', (e) => {
            if (e.target.matches('.price-input, .quantity-input')) {
                calculateTotals();
            }
        });
    }
    
    // --- KEY FIX FOR EDIT MODE ---
    if (isEditMode) {
        // Run calculation once on page load to set initial totals.
        calculateTotals();

        const orderStatusDateWrapper = document.getElementById('order_status_date_wrapper');
        const paymentStatusDateWrapper = document.getElementById('payment_status_date_wrapper');

        if (orderStatusSelect && orderStatusDateWrapper) {
            const originalOrderStatus = orderStatusSelect.value;
            orderStatusSelect.addEventListener('change', function() {
                if (this.value !== originalOrderStatus) {
                    orderStatusDateWrapper.classList.remove('d-none');
                } else {
                    orderStatusDateWrapper.classList.add('d-none');
                }
            });
        }

        if (paymentStatusSelect && paymentStatusDateWrapper) {
            const originalPaymentStatus = paymentStatusSelect.value;
            paymentStatusSelect.addEventListener('change', function() {
                if (this.value !== originalPaymentStatus) {
                    paymentStatusDateWrapper.classList.remove('d-none');
                } else {
                    paymentStatusDateWrapper.classList.add('d-none');
                }
            });
        }
    }

    if (stockTypeSelect) {
        stockTypeSelect.addEventListener('change', () => {
            toggleRowFields();

            if (isEditMode) {
                if (stockTypeSelect.value === 'Ex-Stock') {
                    itemRowsContainer.querySelectorAll('.order-item-row').forEach(row => {
                        const itemId = row.querySelector('.item-id-input')?.value;
                        if (!itemId) return;

                        fetch(`/modules/sales/entry_order.php?action=get_item_stock_details&item_id=${itemId}`)
                            .then(res => res.ok ? res.json() : Promise.reject('Failed to fetch item details'))
                            .then(details => {
                                if (details) {
                                    const stockDisplay = row.querySelector('.stock-display');
                                    const costDisplay = row.querySelector('.cost-display');
                                    const marginDisplay = row.querySelector('.margin-input');
                                    const priceDisplay = row.querySelector('.price-input');
                                    
                                    if (stockDisplay) stockDisplay.value = parseFloat(details.stock_on_hand).toFixed(2);
                                    
                                    const cost = parseFloat(details.last_cost) || 0;
                                    if (costDisplay) costDisplay.value = cost.toFixed(2);
                                    
                                    const price = parseFloat(priceDisplay.value) || 0;
                                    let margin = 0.00;
                                    if (cost > 0 && price > 0) {
                                        margin = ((price / cost) - 1) * 100;
                                    }
                                    if (marginDisplay) marginDisplay.value = margin.toFixed(2);

                                    let costInputHidden = row.querySelector('input.cost-input-hidden');
                                    if (!costInputHidden) {
                                        costInputHidden = document.createElement('input');
                                        costInputHidden.type = 'hidden';
                                        costInputHidden.className = 'cost-input-hidden';
                                        costInputHidden.name = 'items[cost][]';
                                        row.appendChild(costInputHidden);
                                    }
                                    costInputHidden.value = cost.toFixed(2);
                                    
                                    let marginInputHidden = row.querySelector('input.margin-input-hidden');
                                    if (!marginInputHidden) {
                                        marginInputHidden = document.createElement('input');
                                        marginInputHidden.type = 'hidden';
                                        marginInputHidden.className = 'margin-input-hidden';
                                        marginInputHidden.name = 'items[margin][]';
                                        row.appendChild(marginInputHidden);
                                    }
                                    marginInputHidden.value = margin.toFixed(2);

                                    validateRowStock(row);
                                }
                            });
                    });
                }
            } else {
                itemRowsContainer.querySelectorAll('.order-item-row').forEach(row => {
                    row.querySelector('.item-search-input').value = '';
                    row.querySelector('.item-id-input').value = '';
                    row.querySelector('.uom-display').value = '';
                    row.querySelector('.stock-display').value = '';
                    row.querySelector('.cost-input').value = '0';
                    row.querySelector('.cost-display').value = '0.00';
                    row.querySelector('.margin-input').value = '0';
                    row.querySelector('.price-input').value = '0.00';
                    row.querySelector('.quantity-input').value = '1';
                    validateRowStock(row);
                });
                calculateTotals();
            }
        });
    }

    if (!isEditMode) {
        if (customerSearchInput) {
            const doCustomerLookup = debounce(() => {
                const phone = customerSearchInput.value.trim();
                if (!customerResults) return;
                if (phone.length < 3) return customerResults.classList.add('d-none');
                fetch(`/modules/sales/entry_order.php?action=customer_lookup&query=${encodeURIComponent(phone)}`)
                    .then(res => res.ok ? res.json() : Promise.reject('Customer lookup failed'))
                    .then(data => {
                        customerResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(cust => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'list-group-item list-group-item-action';
                                btn.innerHTML = `<strong>${escapeHtml(cust.name)}</strong> (${escapeHtml(cust.phone)})`;
                                btn.addEventListener('click', () => {
                                    customerSearchInput.value = cust.phone;
                                    hiddenCustomerId.value = cust.customer_id;
                                    selectedCustomerDisplay.textContent = `${cust.name} (ID: ${cust.customer_id})`;
                                    customerResults.classList.add('d-none');
                                });
                                customerResults.appendChild(btn);
                            });
                            customerResults.classList.remove('d-none');
                        } else {
                            customerResults.classList.add('d-none');
                        }
                    })
                    .catch(error => console.error('[CustomerLookup] Error:', error));
            }, 300);
            customerSearchInput.addEventListener('input', doCustomerLookup);
        }

        document.addEventListener('click', e => {
            if (customerResults && !customerResults.contains(e.target) && e.target !== customerSearchInput) {
                customerResults.classList.add('d-none');
            }
        });

        const addRow = () => {
            if (!template) return;
            const newRow = template.content.cloneNode(true);
            itemRowsContainer.appendChild(newRow);
            toggleRowFields();
        };

        if (addRowBtn) addRowBtn.addEventListener('click', addRow);

        if (itemRowsContainer) {
            itemRowsContainer.addEventListener('click', e => {
                if (e.target.closest('.remove-item-row')) {
                    e.target.closest('.order-item-row').remove();
                    calculateTotals();
                }
            });
        
            itemRowsContainer.addEventListener('input', e => {
                const row = e.target.closest('.order-item-row');
                if (!row) return;
                if (e.target.classList.contains('item-search-input')) {
                    debounce(() => {
                        const searchInput = e.target;
                        const resultsContainer = row.querySelector('.item-results');
                        const name = searchInput.value.trim();
                        const stockType = stockTypeSelect.value;
                        if (name.length < 2) return resultsContainer.classList.add('d-none');
                        fetch(`/modules/sales/entry_order.php?action=item_lookup&query=${encodeURIComponent(name)}&type=${stockType}`)
                            .then(res => res.ok ? res.json() : Promise.reject('Item lookup failed'))
                            .then(data => {
                                resultsContainer.innerHTML = '';
                                if (data && data.length > 0) {
                                    data.forEach(item => {
                                        const btn = document.createElement('button');
                                        btn.type = 'button';
                                        btn.className = 'list-group-item list-group-item-action py-1';
                                        const detailsHtml = stockType === 'Ex-Stock' ? `<small>Cost: ${item.last_cost || 'N/A'} | Stock: ${item.stock_on_hand}</small>` : `<small>ID: ${escapeHtml(item.item_id)}</small>`;
                                        btn.innerHTML = `<strong>${escapeHtml(item.name)}</strong><br>${detailsHtml}`;
                                        btn.addEventListener('click', () => {
                                            row.querySelector('.item-id-input').value = item.item_id;
                                            searchInput.value = item.name;
                                            row.querySelector('.uom-display').value = item.uom;
                                            if (stockType === 'Ex-Stock') {
                                                row.querySelector('.stock-display').value = item.stock_on_hand;
                                                const cost = parseFloat(item.last_cost) || 0;
                                                row.querySelector('.cost-input').value = cost.toFixed(2);
                                                row.querySelector('.cost-display').value = cost.toFixed(2);
                                                row.querySelector('.margin-input').dispatchEvent(new Event('input', { bubbles: true }));
                                            } else {
                                                row.querySelector('.cost-display').focus();
                                            }
                                            resultsContainer.classList.add('d-none');
                                            validateRowStock(row);
                                        });
                                        resultsContainer.appendChild(btn);
                                    });
                                    resultsContainer.classList.remove('d-none');
                                }
                            });
                    }, 300)();
                    return;
                }
                const costDisplayInput = row.querySelector('.cost-display');
                const costHiddenInput = row.querySelector('.cost-input');
                const marginInput = row.querySelector('.margin-input');
                const priceInput = row.querySelector('.price-input');
                if (e.target === costDisplayInput) { costHiddenInput.value = costDisplayInput.value; }
                const cost = parseFloat(costHiddenInput.value) || 0;
                if (e.target === marginInput || e.target === costDisplayInput) {
                    const margin = parseFloat(marginInput.value) || 0;
                    priceInput.value = (cost * (1 + margin / 100)).toFixed(2);
                } else if (e.target === priceInput) {
                    const price = parseFloat(priceInput.value) || 0;
                    if (cost > 0) {
                        marginInput.value = (((price / cost) - 1) * 100).toFixed(2);
                    } else {
                        marginInput.value = '0.00';
                    }
                }
                if (e.target.matches('.price-input, .quantity-input, .margin-input, .cost-display')) {
                    calculateTotals();
                    validateRowStock(row);
                }
            });

            itemRowsContainer.addEventListener('focus', (e) => {
                if (e.target.classList.contains('cost-display') || e.target.classList.contains('margin-input')) {
                    e.target.select();
                }
            }, true);
        }

        if (form) {
            form.addEventListener('keydown', (e) => {
                if (e.key !== 'Tab') return;
                const activeElement = document.activeElement;
                if (activeElement === otherExpensesInput) {
                    const firstItemInput = itemRowsContainer?.querySelector('.item-search-input');
                    if (firstItemInput) { e.preventDefault(); firstItemInput.focus(); }
                    return;
                }
                if (addRowBtn) {
                    const allQuantityInputs = Array.from(itemRowsContainer.querySelectorAll('.quantity-input'));
                    const lastQuantityInput = allQuantityInputs[allQuantityInputs.length - 1];
                    if (activeElement === lastQuantityInput) { e.preventDefault(); addRowBtn.focus(); }
                    if (activeElement === addRowBtn) { e.preventDefault(); createOrderBtn.focus(); }
                }
            });
        }
        
        if (itemRowsContainer && itemRowsContainer.children.length === 0) {
            addRow();
        }
    }

    if (stockTypeSelect) {
        toggleRowFields();
    }
}

// ───── Order List Handler ─────
function initOrderList() {
    const searchForm = document.getElementById('orderSearchForm');
    if (!searchForm) return;

    const tableBody = document.getElementById('orderListTableBody');
    const filterControls = searchForm.querySelectorAll('input, select');
    const dateRangeInput = document.getElementById('search_date_range');

    const picker = new Litepicker({
        element: dateRangeInput,
        singleMode: false,
        autoApply: true,
        format: 'YYYY-MM-DD',
        setup: (picker) => {
            picker.on('selected', (date1, date2) => {
                doOrderSearch();
            });
        }
    });

    const doOrderSearch = debounce(() => {
        const params = new URLSearchParams({ action: 'search' });
        filterControls.forEach(control => {
            if (control.value) {
                params.set(control.id.replace('search_', ''), control.value);
            }
        });

        if (picker.getStartDate() && picker.getEndDate()) {
            params.set('date_from', picker.getStartDate().format('YYYY-MM-DD'));
            params.set('date_to', picker.getEndDate().format('YYYY-MM-DD'));
        }

        fetch(`/modules/sales/list_orders.php?${params.toString()}`)
            .then(response => response.ok ? response.json() : Promise.reject('Search failed'))
            .then(data => {
                tableBody.innerHTML = '';
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No orders found matching your criteria.</td></tr>`;
                    return;
                }
                data.forEach(order => {
                    const paymentStatusClass = order.payment_status === 'Received' ? 'bg-success' : 'bg-warning';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(order.order_id)}</td>
                        <td>${new Date(order.order_date + 'T00:00:00').toLocaleDateString('en-GB')}</td>
                        <td>${escapeHtml(order.customer_name)}</td>
                        <td>${escapeHtml(order.customer_phone)}</td>
                        <td class="text-end">${parseFloat(order.total_amount).toFixed(2)}</td>
                        <td><span class="badge bg-secondary">${escapeHtml(order.stock_type)}</span></td>
                        <td><span class="badge bg-info text-dark">${escapeHtml(order.status)}</span></td>
                        <td><span class="badge ${paymentStatusClass}">${escapeHtml(order.payment_status)}</span></td>
                        <td>
                            <a href="/modules/sales/entry_order.php?order_id=${escapeHtml(order.order_id)}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> View
                            </a>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('[OrderSearch] Error:', error);
                tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Failed to load search results.</td></tr>`;
            });
    }, 300);

    filterControls.forEach(control => {
        const eventType = control.tagName.toLowerCase() === 'select' ? 'change' : 'input';
        control.addEventListener(eventType, doOrderSearch);
    });
}

// -----------------------------------------
// ----- Purchase Order Entry Handler -----
// -----------------------------------------

function initPoEntry() {
    try {
        console.log('--- Starting initPoEntry execution ---');
        const form = document.getElementById('poForm');
        if (!form) return;

        const isEditMode = !!document.querySelector('input[name="purchase_order_id"]');
        const statusSelect = document.getElementById('status');
        const poDateInput = document.getElementById('po_date');
        const preOrderSearchInput = document.getElementById('pre_order_search');
        const preOrderResults = document.getElementById('preOrderResults');
        const linkedOrdersContainer = document.getElementById('linkedOrdersContainer');
        const itemRowsContainer = document.getElementById('poItemRows');
        const template = document.getElementById('poItemRowTemplate');
        const addRowBtn = document.getElementById('addPoItemRow');

        if (poDateInput && !poDateInput.readOnly) {
            poDateInput.focus();
        }

        if (isEditMode && statusSelect) {
            const statusDateWrapper = document.getElementById('po_status_date_wrapper');
            const paymentModalEl = document.getElementById('paymentModal');
            const receiptModalEl = document.getElementById('receiptModal');
            const totalGoodsCostInput = document.getElementById('total_goods_cost');
            const paidByAccountIdInput = document.getElementById('payment_paid_by_account_id');
            const logisticCostInput = document.getElementById('total_logistic_cost');
            const logisticPaidByInput = document.getElementById('logistic_paid_by_account_id');

            if (totalGoodsCostInput) totalGoodsCostInput.required = false;
            if (paidByAccountIdInput) paidByAccountIdInput.required = false;
            if (logisticCostInput) logisticCostInput.required = false;
            if (logisticPaidByInput) logisticPaidByInput.required = false;

            if (paymentModalEl && receiptModalEl) {
                const paymentModal = new bootstrap.Modal(paymentModalEl);
                const receiptModal = new bootstrap.Modal(receiptModalEl);
                let originalStatusOnLoad = statusSelect.value;
                let paymentSubmitted = false;
                let receiptSubmitted = false;

                statusSelect.addEventListener('change', function() {
                    const newStatus = this.value;
                    if (statusDateWrapper) {
                        if (newStatus !== originalStatusOnLoad) {
                            statusDateWrapper.classList.remove('d-none');
                        } else {
                            statusDateWrapper.classList.add('d-none');
                        }
                    }
                    if (newStatus === 'Paid' && originalStatusOnLoad !== 'Paid') {
                        paymentSubmitted = false;
                        totalGoodsCostInput.required = true;
                        paidByAccountIdInput.required = true;
                        let totalSupplierPrice = 0;
                        document.querySelectorAll('#poItemRows tr').forEach(row => {
                            const priceText = row.cells[1]?.textContent.replace(/,/g, '') || '0';
                            const qtyText = row.cells[3]?.textContent.replace(/,/g, '') || '0';
                            totalSupplierPrice += (parseFloat(priceText) * parseFloat(qtyText));
                        });
                        document.getElementById('total_supplier_price').value = totalSupplierPrice.toFixed(2);
                        paymentModal.show();
                    }
                    if (newStatus === 'Received' && originalStatusOnLoad !== 'Received') {
                        receiptSubmitted = false;
                        logisticCostInput.required = true;
                        logisticPaidByInput.required = true;
                        receiptModal.show();
                    }
                });

                paymentModalEl.addEventListener('hide.bs.modal', () => {
                    if (!paymentSubmitted) { statusSelect.value = originalStatusOnLoad; }
                    totalGoodsCostInput.required = false;
                    paidByAccountIdInput.required = false;
                });
                
                receiptModalEl.addEventListener('hide.bs.modal', () => {
                    if (!receiptSubmitted) { statusSelect.value = originalStatusOnLoad; }
                    logisticCostInput.required = false;
                    logisticPaidByInput.required = false;
                });

                const submitPaymentBtn = document.getElementById('submitPaymentBtn');
                if (submitPaymentBtn) {
                    submitPaymentBtn.addEventListener('click', function() {
                        if (!totalGoodsCostInput.value || !paidByAccountIdInput.value) { alert('Please fill in all fields in the payment form.'); return; }
                        paymentSubmitted = true;
                        document.getElementById('hidden_total_goods_cost').value = totalGoodsCostInput.value;
                        document.getElementById('hidden_goods_paid_by_account_id').value = paidByAccountIdInput.value;
                        paymentModal.hide();
                    });
                }
                
                const submitReceiptBtn = document.getElementById('submitReceiptBtn');
                if (submitReceiptBtn) {
                    submitReceiptBtn.addEventListener('click', function() {
                        if (!logisticCostInput.value || !logisticPaidByInput.value) { alert('Please fill in all fields in the receipt form.'); return; }
                        receiptSubmitted = true;
                        document.getElementById('hidden_total_logistic_cost').value = logisticCostInput.value;
                        document.getElementById('hidden_logistic_paid_by_account_id').value = logisticPaidByInput.value;
                        receiptModal.hide();
                    });
                }
            }
        }

        if (preOrderSearchInput && preOrderResults && linkedOrdersContainer) {
            const addLinkedOrderTag = (orderId, customerName) => {
                if (linkedOrdersContainer.querySelector(`input[value="${orderId}"]`)) { preOrderSearchInput.value = ''; return; }
                const tag = document.createElement('span');
                tag.className = 'badge bg-primary d-flex align-items-center';
                tag.innerHTML = `<input type="hidden" name="linked_sales_orders[]" value="${escapeHtml(orderId)}"><a href="/modules/sales/entry_order.php?order_id=${escapeHtml(orderId)}" target="_blank" class="text-white text-decoration-none">${escapeHtml(orderId)} (${escapeHtml(customerName)})</a><button type="button" class="btn-close btn-close-white ms-2 remove-linked-order" aria-label="Remove"></button>`;
                linkedOrdersContainer.appendChild(tag);
                preOrderSearchInput.value = '';
            };
            preOrderSearchInput.addEventListener('input', debounce(() => {
                const query = preOrderSearchInput.value.trim();
                if (query.length < 2) { if (preOrderResults) preOrderResults.classList.add('d-none'); return; }
                fetch(`/modules/purchase/entry_purchase_order.php?action=pre_order_lookup&query=${encodeURIComponent(query)}`)
                    .then(res => res.ok ? res.json() : Promise.reject('Pre-Order search failed'))
                    .then(data => {
                        if (preOrderResults) {
                            preOrderResults.innerHTML = '';
                            if (data && data.length > 0) {
                                data.forEach(order => {
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'list-group-item list-group-item-action';
                                    btn.innerHTML = `<strong>${escapeHtml(order.order_id)}</strong> - ${escapeHtml(order.customer_name)}`;
                                    btn.addEventListener('click', () => {
                                        addLinkedOrderTag(order.order_id, order.customer_name);
                                        preOrderResults.classList.add('d-none');
                                        preOrderSearchInput.focus();
                                    });
                                    preOrderResults.appendChild(btn);
                                });
                                preOrderResults.classList.remove('d-none');
                            } else { preOrderResults.classList.add('d-none'); }
                        }
                    })
                    .catch(error => console.error('[PreOrderLookup] Error:', error));
            }, 300));
            document.addEventListener('click', e => {
                if (preOrderResults && !preOrderResults.contains(e.target) && e.target !== preOrderSearchInput) {
                    preOrderResults.classList.add('d-none');
                }
            });
            linkedOrdersContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('remove-linked-order')) {
                    e.target.closest('.badge').remove();
                }
            });
        }

        if (!isEditMode && itemRowsContainer && template) {
            const addRow = (shouldFocus = true) => {
                const newRow = template.content.cloneNode(true);
                itemRowsContainer.appendChild(newRow);
                if (shouldFocus) {
                    const searchInput = itemRowsContainer.querySelector('.po-item-row:last-child .item-search-input');
                    if (searchInput) searchInput.focus();
                }
            };
            addRow(false);
            if (addRowBtn) addRowBtn.addEventListener('click', () => addRow(true));
            itemRowsContainer.addEventListener('click', function(e) {
                if (e.target.closest('.remove-item-row')) {
                    e.target.closest('.po-item-row').remove();
                }
            });
            itemRowsContainer.addEventListener('input', debounce((e) => {
                if (e.target.classList.contains('item-search-input')) {
                    const searchInput = e.target;
                    const resultsContainer = searchInput.nextElementSibling;
                    const name = searchInput.value.trim();
                    if (name.length < 2) { if (resultsContainer) resultsContainer.classList.add('d-none'); return; }
                    fetch(`/modules/purchase/entry_purchase_order.php?action=item_lookup&query=${encodeURIComponent(name)}`)
                        .then(response => response.ok ? response.json() : Promise.reject('Item search failed'))
                        .then(data => {
                            if (resultsContainer) {
                                resultsContainer.innerHTML = '';
                                if (data && data.length > 0) {
                                    data.forEach(item => {
                                        const button = document.createElement('button');
                                        button.type = 'button';
                                        button.className = 'list-group-item list-group-item-action py-2';
                                        button.innerHTML = `<strong>${escapeHtml(item.name)}</strong> <small class="text-muted">(${escapeHtml(item.item_id)})</small>`;
                                        button.addEventListener('click', () => {
                                            const parentRow = searchInput.closest('.po-item-row');
                                            parentRow.querySelector('.item-id-input').value = item.item_id;
                                            searchInput.value = item.name;
                                            resultsContainer.classList.add('d-none');
                                            parentRow.querySelector('.quantity-input').focus();
                                        });
                                        resultsContainer.appendChild(button);
                                    });
                                    resultsContainer.classList.remove('d-none');
                                } else { resultsContainer.classList.add('d-none'); }
                            }
                        })
                        .catch(error => console.error('[POItemLookup] Error:', error));
                }
            }, 300));
        }
        console.log('--- Finished initPoEntry execution without errors ---');
    } catch (error) {
        console.error('!!!!!!!! A FATAL ERROR OCCURRED IN initPoEntry !!!!!!!!');
        console.error(error);
        alert('A fatal JavaScript error occurred. Please check the console.');
    }
}

// -----------------------------------------
// ----- GRN List Handler -----
// -----------------------------------------

function initGrnList() {
    const searchForm = document.getElementById('grnSearchForm');
    if (!searchForm) return;

    const tableBody = document.getElementById('grnListTableBody');
    const grnIdInput = document.getElementById('search_grn_id');
    const dateRangeInput = document.getElementById('search_date_range');

    const picker = new Litepicker({
        element: dateRangeInput,
        singleMode: false,
        autoApply: true,
        format: 'YYYY-MM-DD',
        setup: (picker) => {
            picker.on('selected', (date1, date2) => {
                doGrnSearch();
            });
        }
    });

    const doGrnSearch = debounce(() => {
        const params = new URLSearchParams({ action: 'search' });
        if (grnIdInput.value) { params.set('grn_id', grnIdInput.value); }
        if (picker.getStartDate() && picker.getEndDate()) {
            params.set('date_from', picker.getStartDate().format('YYYY-MM-DD'));
            params.set('date_to', picker.getEndDate().format('YYYY-MM-DD'));
        }

        fetch(`/modules/purchase/list_grns.php?${params.toString()}`)
            .then(response => response.ok ? response.json() : Promise.reject('Search failed'))
            .then(data => {
                tableBody.innerHTML = '';
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No GRNs found matching your criteria.</td></tr>`;
                    return;
                }
                data.forEach(grn => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(grn.grn_id)}</td>
                        <td>${new Date(grn.grn_date + 'T00:00:00').toLocaleDateString('en-GB')}</td>
                        <td>${escapeHtml(grn.remarks)}</td>
                        <td>${escapeHtml(grn.created_by_name)}</td>
                        <td>
                            <a href="/modules/purchase/entry_grn.php?grn_id=${escapeHtml(grn.grn_id)}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('[GrnSearch] Error:', error);
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Failed to load search results.</td></tr>`;
            });
    }, 300);

    grnIdInput.addEventListener('input', doGrnSearch);
}

// -----------------------------------------
// ----- Purchase Order List Handler -----
// -----------------------------------------

function initPurchaseOrderList() {
    const searchForm = document.getElementById('poSearchForm');
    if (!searchForm) return;

    const tableBody = document.getElementById('purchaseOrderListTableBody');
    const poIdInput = document.getElementById('search_po_id');
    const statusInput = document.getElementById('search_status');
    const dateRangeInput = document.getElementById('search_date_range');

    const picker = new Litepicker({
        element: dateRangeInput,
        singleMode: false,
        autoApply: true,
        format: 'DD/MM/YYYY',
        setup: (picker) => {
            picker.on('selected', () => doPoSearch());
        }
    });

    const doPoSearch = debounce(() => {
        const params = new URLSearchParams({ action: 'search' });

        if (poIdInput.value) { params.set('purchase_order_id', poIdInput.value); }
        if (statusInput.value) { params.set('status', statusInput.value); }
        if (picker.getStartDate() && picker.getEndDate()) {
            params.set('date_from', picker.getStartDate().format('YYYY-MM-DD'));
            params.set('date_to', picker.getEndDate().format('YYYY-MM-DD'));
        }

        fetch(`/modules/purchase/list_purchase_order.php?${params.toString()}`)
            .then(response => response.ok ? response.json() : Promise.reject('Search failed'))
            .then(data => {
                tableBody.innerHTML = '';
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No Purchase Orders found.</td></tr>`;
                    return;
                }
                data.forEach(po => {
                    const tr = document.createElement('tr');
                    const poDate = new Date(po.po_date + 'T00:00:00').toLocaleDateString('en-GB');
                    
                    // --- THIS LOGIC IS CORRECT ---
                    let linkedOrdersHtml = '<span class="text-muted">N/A</span>';
                    if (po.linked_orders && po.linked_orders.length > 0) {
                        linkedOrdersHtml = po.linked_orders.map(orderId => 
                            `<a href="/modules/sales/entry_order.php?order_id=${escapeHtml(orderId)}" target="_blank">${escapeHtml(orderId)}</a>`
                        ).join('<br>');
                    }
                    
                    // --- THIS TEMPLATE IS NOW CORRECTED ---
                    tr.innerHTML = `
                        <td>${escapeHtml(po.purchase_order_id)}</td>
                        <td>${poDate}</td>
                        <td>${escapeHtml(po.supplier_name)}</td>
                        <td>${linkedOrdersHtml}</td>  <!-- THIS LINE IS NOW CORRECT -->
                        <td><span class="badge bg-info text-dark">${escapeHtml(po.status)}</span></td>
                        <td>${escapeHtml(po.created_by_name)}</td>
                        <td>
                            <a href="/modules/purchase/entry_purchase_order.php?purchase_order_id=${escapeHtml(po.purchase_order_id)}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('[PoSearch] Error:', error);
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Failed to load search results.</td></tr>`;
            });
    }, 300);

    if (poIdInput) poIdInput.addEventListener('input', doPoSearch);
    if (statusInput) statusInput.addEventListener('change', doPoSearch);

    // Load initial data
    doPoSearch();

// ──────────────────────────────────────── End ─────────────────────────────────────────
}
// ──────────────────────────────────────── End ─────────────────────────────────────────

// -----------------------------------------
// ----- Purchase Order List Handler -----
// -----------------------------------------

function initPurchaseOrderList() {
    const searchForm = document.getElementById('poSearchForm');
    if (!searchForm) return;

    const tableBody = document.getElementById('purchaseOrderListTableBody');
    const poIdInput = document.getElementById('search_po_id');
    const statusInput = document.getElementById('search_status');
    const dateRangeInput = document.getElementById('search_date_range');

    const picker = new Litepicker({
        element: dateRangeInput,
        singleMode: false,
        autoApply: true,
        format: 'DD/MM/YYYY',
        setup: (picker) => {
            picker.on('selected', () => doPoSearch());
        }
    });

    const doPoSearch = debounce(() => {
        const params = new URLSearchParams({ action: 'search' });

        if (poIdInput.value) { params.set('purchase_order_id', poIdInput.value); }
        if (statusInput.value) { params.set('status', statusInput.value); }
        if (picker.getStartDate() && picker.getEndDate()) {
            params.set('date_from', picker.getStartDate().format('YYYY-MM-DD'));
            params.set('date_to', picker.getEndDate().format('YYYY-MM-DD'));
        }

        fetch(`/modules/purchase/list_purchase_order.php?${params.toString()}`)
            .then(response => response.ok ? response.json() : Promise.reject('Search failed'))
            .then(data => {
                tableBody.innerHTML = '';
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No Purchase Orders found.</td></tr>`;
                    return;
                }
                data.forEach(po => {
                    const tr = document.createElement('tr');
                    const poDate = new Date(po.po_date + 'T00:00:00').toLocaleDateString('en-GB');
                    
                    // --- THIS IS THE FINAL CORRECTED LOGIC ---
                    let linkedOrdersHtml = '<span class="text-muted">N/A</span>';
                    // It now correctly checks the `po.linked_orders` array
                    if (po.linked_orders && po.linked_orders.length > 0) {
                        // It maps over the array to create a link for each ID
                        linkedOrdersHtml = po.linked_orders.map(orderId => 
                            `<a href="/modules/sales/entry_order.php?order_id=${escapeHtml(orderId)}" target="_blank">${escapeHtml(orderId)}</a>`
                        ).join('<br>'); // And joins them with a line break
                    }
                    // --- END CORRECTION ---

                    tr.innerHTML = `
                        <td>${escapeHtml(po.purchase_order_id)}</td>
                        <td>${poDate}</td>
                        <td>${escapeHtml(po.supplier_name)}</td>
                        <td>${linkedOrdersHtml}</td>
                        <td><span class="badge bg-info text-dark">${escapeHtml(po.status)}</span></td>
                        <td>${escapeHtml(po.created_by_name)}</td>
                        <td>
                            <a href="/modules/purchase/entry_purchase_order.php?purchase_order_id=${escapeHtml(po.purchase_order_id)}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('[PoSearch] Error:', error);
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Failed to load search results.</td></tr>`;
            });
    }, 300);

    if (poIdInput) poIdInput.addEventListener('input', doPoSearch);
    if (statusInput) statusInput.addEventListener('change', doPoSearch);

    // This correctly loads the initial data.
    doPoSearch();
}
// ──────────────────────────────────────── End ─────────────────────────────────────────

// -----------------------------------------
// ----- Stock Level List Handler -----
// -----------------------------------------

function initStockLevelList() {
    const searchForm = document.getElementById('stockSearchForm');
    if (!searchForm) return;

    const tableBody = document.getElementById('stockListTableBody');
    const itemNameInput = document.getElementById('search_item_name');
    const categoryIdInput = document.getElementById('search_category_id');
    const subCategoryIdInput = document.getElementById('search_sub_category_id');
    const stockStatusInput = document.getElementById('search_stock_status');

    const doStockSearch = debounce(() => {
        const params = new URLSearchParams({ action: 'search' });
        if (itemNameInput.value) { params.set('item_name', itemNameInput.value); }
        if (categoryIdInput.value) { params.set('category_id', categoryIdInput.value); }
        if (subCategoryIdInput.value) { params.set('sub_category_id', subCategoryIdInput.value); }
        if (stockStatusInput.value) { params.set('stock_status', stockStatusInput.value); }

        fetch(`/modules/inventory/list_stock_levels.php?${params.toString()}`)
            .then(response => response.ok ? response.json() : Promise.reject('Search failed'))
            .then(data => {
                tableBody.innerHTML = '';
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No items found matching your criteria.</td></tr>`;
                    return;
                }
                data.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(item.item_id)}</td>
                        <td>${escapeHtml(item.item_name)}</td>
                        <td>${escapeHtml(item.category_name)}</td>
                        <td>${escapeHtml(item.sub_category_name)}</td>
                        <td class="text-end fw-bold">${parseFloat(item.quantity).toFixed(2)}</td>
                        <td class="text-center">
                            <a href="/modules/inventory/view_item.php?item_id=${escapeHtml(item.item_id)}" class="btn btn-sm btn-outline-primary" title="View Item Details">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('[StockSearch] Error:', error);
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Failed to load search results.</td></tr>`;
            });
    }, 300);

    categoryIdInput.addEventListener('change', function() {
        const categoryId = this.value;
        subCategoryIdInput.innerHTML = '<option value="">All Sub-Categories</option>';
        subCategoryIdInput.disabled = true;

        if (categoryId) {
            fetch(`/modules/inventory/list_stock_levels.php?action=get_sub_categories&category_id=${categoryId}`)
                .then(response => response.ok ? response.json() : Promise.reject('Failed to load sub-categories'))
                .then(data => {
                    data.forEach(sub_cat => {
                        subCategoryIdInput.add(new Option(sub_cat.name, sub_cat.category_sub_id));
                    });
                    subCategoryIdInput.disabled = false;
                })
                .catch(error => console.error('[SubCategoryFetch] Error:', error));
        }
        
        doStockSearch();
    });

    itemNameInput.addEventListener('input', doStockSearch);
    subCategoryIdInput.addEventListener('change', doStockSearch);
    stockStatusInput.addEventListener('change', doStockSearch);
}
// ──────────────────────────────────────── End ─────────────────────────────────────────

// -----------------------------------------
// ----- Item View Page Gallery Handler -----
// -----------------------------------------

function initItemViewPage() {
    const mainImageView = document.getElementById('mainImageView');
    const thumbnails = document.querySelectorAll('.item-thumbnail');

    // If there's no gallery on the page, exit the function
    if (!mainImageView || thumbnails.length === 0) {
        return;
    }

    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            // When a thumbnail is clicked, set its 'src' as the 'src' of the main image view
            mainImageView.src = this.src;

            // Optional: Add an 'active' class to the clicked thumbnail for styling
            thumbnails.forEach(t => t.classList.remove('border-primary', 'border-3'));
            this.classList.add('border-primary', 'border-3');
        });
    });
}
// ──────────────────────────────────────── End ─────────────────────────────────────────

// -----------------------------------------
// ----- Courier Charge Calculator Handler -----
// -----------------------------------------

function initCourierCalculator() {
    const form = document.getElementById('courierCalcForm');
    if (!form) return;

    const resultsCard = document.getElementById('resultsCard');
    const weightChargeEl = document.getElementById('weightCharge');
    const valueChargeEl = document.getElementById('valueCharge');
    const fixedChargeEl = document.getElementById('fixedCharge'); // <-- NEW: Get the fixed charge element
    const totalChargeEl = document.getElementById('totalCharge');
    const weightInput = document.getElementById('weight');
    const valueInput = document.getElementById('value');

    form.addEventListener('submit', function(event) {
        event.preventDefault();

        const weight = weightInput.value;
        const value = valueInput.value;
        const submitBtn = this.querySelector('button[type="submit"]');

        if (!weight || !value) {
            alert('Please enter both weight and value.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Calculating...';

        // NOTE: Make sure this URL is correct for your new file structure
        fetch(`/modules/price/calculate_courier_charge.php?action=calculate&weight=${weight}&value=${value}`)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errData => Promise.reject(errData));
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update the results display
                    weightChargeEl.textContent = `Rs. ${data.data.weight_charge.toFixed(2)}`;
                    valueChargeEl.textContent = `Rs. ${data.data.value_charge.toFixed(2)}`;
                    fixedChargeEl.textContent = `Rs. ${data.data.fixed_charge.toFixed(2)}`; // <-- NEW: Populate the fixed charge
                    totalChargeEl.textContent = `Rs. ${data.data.total_charge.toFixed(2)}`;
                    resultsCard.classList.remove('d-none');
                } else {
                    throw new Error(data.error || 'Calculation failed.');
                }
            })
            .catch(error => {
                const errorMessage = error.error || error.message || 'An unknown error occurred.';
                showAlert(errorMessage, 'danger');
                resultsCard.classList.add('d-none');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-calculator"></i> Calculate Charge';
            });
    });
}
// ──────────────────────────────────────── End ─────────────────────────────────────────

// ───────────────────────────────────── DOM Ready ──────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('customerForm')) { initCustomerEntry(); }
    if (document.getElementById('categoryForm')) { initCategoryEntry(); }
    if (document.getElementById('subCategoryForm')) { initSubCategoryEntry(); }
//    if (document.getElementById('itemForm')) { initItemEntry(); }
    if (document.getElementById('itemForm')) { initItemManagement(); }
    if (document.getElementById('stockAdjustmentForm')) { initStockAdjustmentEntry(); }    
    if (document.getElementById('orderForm')) { initOrderEntry(); }
    if (document.getElementById('orderSearchForm')) { initOrderList(); }
    if (document.getElementById('poForm')) { initPoEntry(); }
    if (document.getElementById('grnSearchForm')) { initGrnList(); }
    if (document.getElementById('poSearchForm')) { initPurchaseOrderList(); }
    if (document.getElementById('stockSearchForm')) { initStockLevelList(); }
    if (document.getElementById('grnForm')) { initGrnPage(); }
    if (document.getElementById('mainImageView')) { initItemViewPage(); }
    if (document.getElementById('courierCalcForm')) { initCourierCalculator(); }

    setupFormSubmitSpinner(document.getElementById('customerForm'));
    setupFormSubmitSpinner(document.getElementById('categoryForm'));
    setupFormSubmitSpinner(document.getElementById('subCategoryForm'));
    setupFormSubmitSpinner(document.getElementById('itemForm'));
    setupFormSubmitSpinner(document.getElementById('stockAdjustmentForm'));
    setupFormSubmitSpinner(document.getElementById('grnForm'));
    setupFormSubmitSpinner(document.getElementById('orderForm'));
    // We are now handling the PO form manually, so we comment this out.
    // setupFormSubmitSpinner(document.getElementById('poForm'));

    // --- DEFINITIVE FIX: MANUAL SUBMIT FOR PO FORM ---
    const forceSaveButton = document.getElementById('savePoBtn');
    if (forceSaveButton) {
        const poForm = document.getElementById('poForm');
        
        console.log('Attaching force save listener to button: savePoBtn');
        
        forceSaveButton.addEventListener('click', function(event) {
            event.preventDefault(); // Always prevent default for a button click
            console.log('Manual save button was clicked.');
            
            if (poForm && poForm.checkValidity()) {
                console.log('Form is valid. Manually submitting...');
                
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
                
                poForm.submit();
            } else {
                console.log('Form is invalid. Showing validation errors.');
                // Add the 'was-validated' class to show Bootstrap's styling for errors
                if (poForm) {
                    poForm.classList.add('was-validated');
                }
            }
        });
    }
    // --- END FIX ---

    const staticAlerts = document.querySelectorAll('.alert-dismissible');
    staticAlerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
});
// ──────────────────────────────────────── End ─────────────────────────────────────────
