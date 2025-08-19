// File: assets/js/app.js
// Final validated version with all modules and all UI/UX refinements.

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
        if (bsAlert) { bsAlert.close(); }
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
        if (subCategoryResults && !subCategoryResults.contains(e.target) && e.target !== nameInput) {
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
        if (itemResults && !itemResults.contains(e.target) && e.target !== nameInput) {
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

        fetch(`/modules/inventory/entry_stock_adjustment.php?item_lookup=${encodeURIComponent(name)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Item search failed'))
            .then(data => {
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
        if (resultsContainer && !resultsContainer.contains(e.target) && e.target !== searchInput) {
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

    addRow();

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

            fetch(`/modules/inventory/entry_grn.php?item_lookup=${encodeURIComponent(name)}`)
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

// ───── Order Entry Handler ─────
function initOrderEntry() {
    const form = document.getElementById('orderForm');
    if (!form) return;

    const stockTypeSelect = document.getElementById('stock_type');
    const customerSearchInput = document.getElementById('customer_search');
    const customerResults = document.getElementById('customerResults');
    const hiddenCustomerId = document.getElementById('customer_id');
    const selectedCustomerDisplay = document.getElementById('selected_customer_display');
    const itemRowsContainer = document.getElementById('orderItemRows');
    const template = document.getElementById('orderItemRowTemplate');
    const addRowBtn = document.getElementById('addItemRow');
    const orderTotalDisplay = document.getElementById('orderTotal');
    const otherExpensesInput = document.getElementById('other_expenses');
    const createOrderBtn = document.querySelector('#orderForm button[type="submit"]');

    const toggleRowFields = () => {
        const isPreBook = stockTypeSelect.value === 'Pre-Book';
        form.querySelector('th.stock-col').classList.toggle('d-none', isPreBook);
        itemRowsContainer.querySelectorAll('.order-item-row').forEach(row => {
            row.querySelector('.stock-display').closest('td').classList.toggle('d-none', isPreBook);
            const costDisplayInput = row.querySelector('.cost-display');
            const priceInput = row.querySelector('.price-input');
            costDisplayInput.readOnly = !isPreBook;
            priceInput.readOnly = !isPreBook;
        });
    };

    const validateRowStock = (row) => {
        const stockType = stockTypeSelect.value;
        const stockWarning = row.querySelector('.stock-warning');
        if (!stockWarning) return;
        if (stockType === 'Ex-Stock') {
            const availableStock = parseFloat(row.querySelector('.stock-display').value) || 0;
            const orderQuantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
            stockWarning.classList.toggle('d-none', orderQuantity <= availableStock);
        } else {
            stockWarning.classList.add('d-none');
        }
    };
    
    const calculateTotals = () => {
        let total = 0;
        itemRowsContainer.querySelectorAll('.order-item-row').forEach(row => {
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
            const subtotal = price * quantity;
            row.querySelector('.subtotal-display').textContent = subtotal.toFixed(2);
            total += subtotal;
        });
        orderTotalDisplay.textContent = new Intl.NumberFormat('en-US', { style: 'decimal', minimumFractionDigits: 2 }).format(total);
    };

    const doCustomerLookup = debounce(() => {
        const phone = customerSearchInput.value.trim();
        if (phone.length < 3) return customerResults.classList.add('d-none');
        
        // CORRECTED: The fetch URL now points to /modules/sales/entry_order.php
        fetch(`/modules/sales/entry_order.php?customer_lookup=${encodeURIComponent(phone)}`)
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
    document.addEventListener('click', e => {
        if (customerResults && !customerResults.contains(e.target) && e.target !== customerSearchInput) {
            customerResults.classList.add('d-none');
        }
    });

    const addRow = () => {
        const newRow = template.content.cloneNode(true);
        itemRowsContainer.appendChild(newRow);
        toggleRowFields();
    };
    addRowBtn.addEventListener('click', addRow);
    
    itemRowsContainer.addEventListener('click', e => {
        if (e.target.closest('.remove-item-row')) {
            e.target.closest('.order-item-row').remove();
            calculateTotals();
        }
    });

    stockTypeSelect.addEventListener('change', () => {
        toggleRowFields();
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
                
                // CORRECTED: The fetch URL now points to /modules/sales/entry_order.php
                fetch(`/modules/sales/entry_order.php?item_lookup=${encodeURIComponent(name)}&type=${stockType}`)
                    .then(res => res.ok ? res.json() : Promise.reject())
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
        
        if (e.target === costDisplayInput) {
            costHiddenInput.value = costDisplayInput.value;
        }

        const cost = parseFloat(costHiddenInput.value) || 0;
        
        if (e.target === marginInput || e.target === costDisplayInput) {
            const margin = parseFloat(marginInput.value) || 0;
            priceInput.value = (cost * (1 + margin / 100)).toFixed(2);
        } 
        else if (e.target === priceInput) {
            const price = parseFloat(priceInput.value) || 0;
            if (cost > 0) {
                marginInput.value = (((price / cost) - 1) * 100).toFixed(2);
            } else {
                marginInput.value = (price > 0) ? '100.00' : '0.00';
            }
        }
        
        if (e.target.matches('.price-input, .quantity-input, .margin-input, .cost-display')) {
            calculateTotals();
            validateRowStock(row);
        }
    });
    
    form.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') return;

        const activeElement = document.activeElement;

        if (activeElement === otherExpensesInput) {
            const firstItemInput = itemRowsContainer.querySelector('.item-search-input');
            if (firstItemInput) {
                e.preventDefault();
                firstItemInput.focus();
            }
            return;
        }

        const allQuantityInputs = Array.from(itemRowsContainer.querySelectorAll('.quantity-input'));
        const lastQuantityInput = allQuantityInputs[allQuantityInputs.length - 1];
        
        if (activeElement === lastQuantityInput) {
            e.preventDefault();
            addRowBtn.focus();
        }

        if (activeElement === addRowBtn) {
            e.preventDefault();
            createOrderBtn.focus();
        }
    });

    addRow();
    toggleRowFields();
}

// ───── Order List Handler ─────
function initOrderList() {
    const searchForm = document.getElementById('orderSearchForm');
    if (!searchForm) return;

    const tableBody = document.getElementById('orderListTableBody');
    const filterControls = searchForm.querySelectorAll('input, select');
    const dateRangeInput = document.getElementById('search_date_range');

    // --- Date Range Picker Initialization ---
    const picker = new Litepicker({
        element: dateRangeInput,
        singleMode: false,
        autoApply: true,
        format: 'YYYY-MM-DD',
        setup: (picker) => {
            picker.on('selected', (date1, date2) => {
                // When a date range is selected, trigger the search
                doOrderSearch();
            });
        }
    });

    const doOrderSearch = debounce(() => {
        const params = new URLSearchParams({ action: 'search' });

        // Handle regular text and select inputs
        searchForm.querySelectorAll('input[type="text"], select').forEach(control => {
            if (control.value) {
                params.set(control.id.replace('search_', ''), control.value);
            }
        });

        // Handle the date range picker input
        const dateRange = picker.getStartDate() && picker.getEndDate() ? picker.getStartDate().format('YYYY-MM-DD') + ' - ' + picker.getEndDate().format('YYYY-MM-DD') : '';
        if (dateRange) {
            params.set('date_from', picker.getStartDate().format('YYYY-MM-DD'));
            params.set('date_to', picker.getEndDate().format('YYYY-MM-DD'));
        }

        fetch(`/modules/sales/list_orders.php?${params.toString()}`)
            .then(response => response.ok ? response.json() : Promise.reject('Search failed'))
            .then(data => {
                tableBody.innerHTML = '';
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No orders found matching your criteria.</td></tr>`;
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
                tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Failed to load search results.</td></tr>`;
            });
    }, 300);

    // Attach event listeners to all filter controls EXCEPT the date range picker
    filterControls.forEach(control => {
        if (control.id !== 'search_date_range') {
            const eventType = control.tagName.toLowerCase() === 'select' ? 'change' : 'input';
            control.addEventListener(eventType, doOrderSearch);
        }
    });
}

// -----------------------------------------
// ----- Purchase Order Entry Handler -----
// -----------------------------------------

function initPoEntry() {
    const form = document.getElementById('poForm');
    if (!form) return;

    const itemRowsContainer = document.getElementById('poItemRows');
    const template = document.getElementById('poItemRowTemplate');
    const addRowBtn = document.getElementById('addPoItemRow');

    const addRow = () => {
        const newRow = template.content.cloneNode(true);
        itemRowsContainer.appendChild(newRow);
        // Focus the new search input for a better user experience
        newRow.querySelector('.item-search-input').focus();
    };

    // Add the first row automatically when the page loads
    addRow();

    // Add more rows when the button is clicked
    addRowBtn.addEventListener('click', addRow);

    // Use event delegation to handle events on dynamic rows
    itemRowsContainer.addEventListener('click', function(e) {
        // Handle removing an item row
        if (e.target.closest('.remove-item-row')) {
            e.target.closest('.po-item-row').remove();
        }
    });

    itemRowsContainer.addEventListener('input', debounce((e) => {
        // Handle the live search for items within each row
        if (e.target.classList.contains('item-search-input')) {
            const searchInput = e.target;
            const resultsContainer = searchInput.nextElementSibling; // The div right after the input
            const name = searchInput.value.trim();

            if (name.length < 2) {
                resultsContainer.classList.add('d-none');
                return;
            }

            // The AJAX endpoint is the PO page itself
            fetch(`/modules/purchase/entry_purchase_order.php?item_lookup=${encodeURIComponent(name)}`)
                .then(response => response.ok ? response.json() : Promise.reject('Item search failed'))
                .then(data => {
                    resultsContainer.innerHTML = '';
                    if (data.error) throw new Error(data.error);

                    if (data.length > 0) {
                        data.forEach(item => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'list-group-item list-group-item-action py-2';
                            button.innerHTML = `<strong>${escapeHtml(item.name)}</strong> <small class.text-muted="">(${escapeHtml(item.item_id)})</small>`;
                            
                            button.addEventListener('click', () => {
                                const parentRow = searchInput.closest('.po-item-row');
                                parentRow.querySelector('.item-id-input').value = item.item_id;
                                searchInput.value = item.name;
                                resultsContainer.classList.add('d-none'); // Hide results after selection
                                // Move focus to the quantity input for a fast workflow
                                parentRow.querySelector('.quantity-input').focus();
                            });
                            resultsContainer.appendChild(button);
                        });
                        resultsContainer.classList.remove('d-none');
                    } else {
                        resultsContainer.classList.add('d-none');
                    }
                })
                .catch(error => {
                    console.error('[POItemLookup] Error:', error);
                    resultsContainer.classList.add('d-none');
                });
        }
    }, 300));
}

// ───── DOM Ready ─────
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('customerForm')) { initCustomerEntry(); }
    if (document.getElementById('categoryForm')) { initCategoryEntry(); }
    if (document.getElementById('subCategoryForm')) { initSubCategoryEntry(); }
    if (document.getElementById('itemForm')) { initItemEntry(); }
    if (document.getElementById('stockAdjustmentForm')) { initStockAdjustmentEntry(); }
    if (document.getElementById('grnForm')) { initGrnEntry(); }
    if (document.getElementById('orderForm')) { initOrderEntry(); }
    if (document.getElementById('orderSearchForm')) { initOrderList(); }
    if (document.querySelector('.live-search')) { initLiveSearch(); }
    if (document.getElementById('poForm')) { initPoEntry(); }

    setupFormSubmitSpinner(document.getElementById('customerForm'));
    setupFormSubmitSpinner(document.getElementById('categoryForm'));
    setupFormSubmitSpinner(document.getElementById('subCategoryForm'));
    setupFormSubmitSpinner(document.getElementById('itemForm'));
    setupFormSubmitSpinner(document.getElementById('stockAdjustmentForm'));
    setupFormSubmitSpinner(document.getElementById('grnForm'));
    setupFormSubmitSpinner(document.getElementById('orderForm'));
    setupFormSubmitSpinner(document.getElementById('poForm'));

    const staticAlerts = document.querySelectorAll('.alert-dismissible');
    staticAlerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
});