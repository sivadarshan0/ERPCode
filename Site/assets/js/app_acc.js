// File: /assets/js/app_acc.js
// All frontend JavaScript for the Accounts module.

// -----------------------------------------
// ----- Chart of Accounts List Handler -----
// -----------------------------------------

function initAccountList() {
    const searchForm = document.getElementById('accountSearchForm');
    if (!searchForm) return;

    const tableBody = document.getElementById('accountListTableBody');
    const accountNameInput = document.getElementById('search_account_name');
    const accountTypeInput = document.getElementById('search_account_type');
    const isActiveInput = document.getElementById('search_is_active');

    const doAccountSearch = debounce(() => {
        const params = new URLSearchParams({ action: 'search' });

        if (accountNameInput.value) { params.set('account_name', accountNameInput.value); }
        if (accountTypeInput.value) { params.set('account_type', accountTypeInput.value); }
        if (isActiveInput.value) { params.set('is_active', isActiveInput.value); } // Read from status

        fetch(`/modules/accounts/list_accounts.php?${params.toString()}`)
            .then(response => response.ok ? response.json() : Promise.reject('Search failed'))
            .then(data => {
                tableBody.innerHTML = '';
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No accounts found matching your criteria.</td></tr>`;
                    return;
                }
                data.forEach(account => {
                    const tr = document.createElement('tr');
                    const statusClass = account.is_active ? 'bg-success' : 'bg-secondary';
                    const statusText = account.is_active ? 'Active' : 'Inactive';
                    
                    tr.innerHTML = `
                        <td>${escapeHtml(account.account_name)}</td>
                        <td>${escapeHtml(account.account_type)}</td>
                        <td><span class="text-capitalize">${escapeHtml(account.normal_balance)}</span></td>
                        <td>
                            <span class="badge ${statusClass}">${statusText}</span>
                        </td>
                        <td>
                            <a href="/modules/accounts/entry_account.php?account_id=${account.account_id}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('[AccountSearch] Error:', error);
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Failed to load search results.</td></tr>`;
            });
    }, 300);

    accountNameInput.addEventListener('input', doAccountSearch);
    accountTypeInput.addEventListener('change', doAccountSearch);
    isActiveInput.addEventListener('change', doAccountSearch); // Add listener for status
    
    doAccountSearch();
}
// --------------------------End-------------------------------

// -----------------------------------------
// ----- Chart of Accounts Entry Handler -----
// -----------------------------------------
function initAccountEntry() {
    const accountTypeSelect = document.getElementById('account_type');
    const normalBalanceSelect = document.getElementById('normal_balance');

    if (!accountTypeSelect || !normalBalanceSelect) return;

    accountTypeSelect.addEventListener('change', function() {
        const selectedType = this.value;
        
        // Define which account types have a normal debit balance
        const debitTypes = ['Asset', 'Expense'];

        if (debitTypes.includes(selectedType)) {
            normalBalanceSelect.value = 'debit';
        } else {
            // All other types (Liability, Equity, Revenue) have a credit balance
            normalBalanceSelect.value = 'credit';
        }
    });
}
// ──────────────────────────────────────── End ─────────────────────────────────────────

// -----------------------------------------
// ----- Transaction List Handler -----
// -----------------------------------------

function initTransactionList() {
    const searchForm = document.getElementById('transactionSearchForm');
    if (!searchForm) return;

    const tableBody = document.getElementById('transactionListTableBody');
    const dateRangeInput = document.getElementById('search_date_range');
    const financialYearInput = document.getElementById('search_financial_year'); // ADDED
    const accountIdInput = document.getElementById('search_account_id');
    const descriptionInput = document.getElementById('search_description');

    const picker = new Litepicker({
        element: dateRangeInput,
        singleMode: false,
        autoApply: true,
        format: 'YYYY-MM-DD',
        setup: (picker) => {
            picker.on('selected', () => {
                doTransactionSearch();
            });
        }
    });

    const doTransactionSearch = debounce(() => {
        const params = new URLSearchParams({ action: 'search' });

        if (picker.getStartDate() && picker.getEndDate()) {
            params.set('date_from', picker.getStartDate().format('YYYY-MM-DD'));
            params.set('date_to', picker.getEndDate().format('YYYY-MM-DD'));
        }
        if (financialYearInput.value) { // ADDED
            params.set('financial_year', financialYearInput.value);
        }
        if (accountIdInput.value) {
            params.set('account_id', accountIdInput.value);
        }
        if (descriptionInput.value) {
            params.set('description', descriptionInput.value);
        }

        fetch(`/modules/accounts/list_transactions.php?${params.toString()}`)
            .then(response => response.ok ? response.json() : Promise.reject('Search failed'))
            .then(data => {
                tableBody.innerHTML = '';
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No transactions found.</td></tr>`; // Colspan updated
                    return;
                }
                data.forEach(txn => {
                    const tr = document.createElement('tr');
                    const isCredit = txn.credit_amount !== null;
                    const numberFormatter = new Intl.NumberFormat('en-US', { style: 'decimal', minimumFractionDigits: 2 });
                    const debitAmount = txn.debit_amount ? numberFormatter.format(txn.debit_amount) : '';
                    const creditAmount = txn.credit_amount ? numberFormatter.format(txn.credit_amount) : '';
                    
                    let sourceHtml = txn.source_type; // Display source type by default
                    if (txn.source_id) {
                        let url = '#';
                        if (txn.source_type === 'sales_order') {
                            url = `/modules/sales/entry_order.php?order_id=${txn.source_id}`;
                        } else if (txn.source_type === 'purchase_order') {
                            url = `/modules/purchase/entry_purchase_order.php?purchase_order_id=${txn.source_id}`;
                        }
                        sourceHtml = `<a href="${url}" target="_blank">${escapeHtml(txn.source_id)}</a>`;
                    }
                    
                    // --- THIS IS THE CRITICAL FIX ---
                    // Logic to build the Actions column
                    let actionsHtml = '';
                    // ONLY show the Cancel button if it's a 'manual_entry' and its status is 'Posted'
                    if (txn.source_type === 'manual_entry' && txn.status === 'Posted' && txn.debit_amount !== null) {
                        actionsHtml = `
                            <button class="btn btn-xs btn-danger cancel-txn-btn" data-group-id="${escapeHtml(txn.transaction_group_id)}">
                                Cancel
                            </button>
                        `;
                    }
                    
                    const statusClass = txn.status === 'Posted' ? 'bg-success' : 'bg-secondary';

                    tr.innerHTML = `
                        <td>${new Date(txn.transaction_date).toLocaleDateString('en-GB')}</td>
                        <td>${escapeHtml(txn.financial_year)}</td>
                        <td style="${isCredit ? 'padding-left: 1.5rem; color: #6c757d;' : ''}">${escapeHtml(txn.account_name)}</td>
                        <td>${escapeHtml(txn.description)}</td>
                        <td class="text-end font-monospace">${debitAmount}</td>
                        <td class="text-end font-monospace">${creditAmount}</td>
                        <td><span class="badge ${statusClass}">${escapeHtml(txn.status)}</span></td>
                        <td>${sourceHtml}</td>
                        <td>${actionsHtml}</td>
                    `;
                    tableBody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('[TransactionSearch] Error:', error);
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Failed to load results.</td></tr>`; // Colspan updated
            });
    }, 300);

    // Attach event listeners to all filter controls
    financialYearInput.addEventListener('change', doTransactionSearch); // ADDED
    accountIdInput.addEventListener('change', doTransactionSearch);
    descriptionInput.addEventListener('input', doTransactionSearch);
    
    // Initial load
    doTransactionSearch();
}

// ──────────────────────────────────────── End ─────────────────────────────────────────

// -----------------------------------------
// ----- Transaction Cancel Handler -----
// -----------------------------------------

function initTransactionCancel() {
    const tableBody = document.getElementById('transactionListTableBody');
    if (!tableBody) return;

    // Use event delegation to listen for clicks on cancel buttons
    tableBody.addEventListener('click', function(e) {
        if (e.target.classList.contains('cancel-txn-btn')) {
            const button = e.target;
            const groupId = button.dataset.groupId;

            if (!confirm(`Are you sure you want to cancel transaction group #${groupId}?\nThis will create a reversing entry and cannot be undone.`)) {
                return;
            }

            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

            const formData = new FormData();
            formData.append('group_id', groupId);

            fetch('/modules/accounts/list_transactions.php?action=cancel_transaction', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    // Find the doTransactionSearch function which is in the scope of initTransactionList and trigger it
                    // This is a bit of a hack, a better way would be to use custom events or a shared state object
                    // For now, we will just reload the search to refresh the list
                    const searchForm = document.getElementById('transactionSearchForm');
                    if (searchForm) {
                        // A simple way to trigger the search again is to dispatch an event on one of the inputs
                        document.getElementById('search_description').dispatchEvent(new Event('input', { bubbles: true }));
                    }
                } else {
                    showAlert(data.error, 'danger');
                    button.disabled = false;
                    button.textContent = 'Cancel';
                }
            })
            .catch(error => {
                showAlert('An error occurred. Please try again.', 'danger');
                button.disabled = false;
                button.textContent = 'Cancel';
                console.error('Cancel Transaction Error:', error);
            });
        }
    });
}
// ──────────────────────────────────────── End ─────────────────────────────────────────

// ──────────────────── DOM Ready ───────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('accountSearchForm')) { initAccountList(); } // Call the list page handler
    if (document.getElementById('accountForm')) { initAccountEntry(); } // Call the entry page handler
    if (document.getElementById('transactionSearchForm')) {
        initTransactionList();
        initTransactionCancel(); // <-- ADD THIS LINE
    }
});
// ───────────────────────── End ──────────────────────────