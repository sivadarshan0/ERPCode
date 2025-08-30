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
    const accountIdInput = document.getElementById('search_account_id');
    const descriptionInput = document.getElementById('search_description');

    // --- Date Range Picker Initialization ---
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
                    tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No transactions found matching your criteria.</td></tr>`;
                    return;
                }
                data.forEach(txn => {
                    const tr = document.createElement('tr');
                    const isCredit = txn.credit_amount !== null;
                    
                    // --- CORRECTED: Use Intl.NumberFormat for proper comma separation ---
                    const numberFormatter = new Intl.NumberFormat('en-US', { style: 'decimal', minimumFractionDigits: 2 });
                    const debitAmount = txn.debit_amount ? numberFormatter.format(txn.debit_amount) : '';
                    const creditAmount = txn.credit_amount ? numberFormatter.format(txn.credit_amount) : '';

                    let sourceHtml = escapeHtml(txn.source_type);
                    if (txn.source_id) {
                        let url = '#';
                        if (txn.source_type === 'sales_order') {
                            url = `/modules/sales/entry_order.php?order_id=${txn.source_id}`;
                        } else if (txn.source_type === 'purchase_order') {
                            url = `/modules/purchase/entry_purchase_order.php?purchase_order_id=${txn.source_id}`;
                        }
                        sourceHtml = `<a href="${url}" target="_blank">${escapeHtml(txn.source_id)}</a>`;
                    }
                    
                    // --- CORRECTED: Added indentation to the credit-side account name ---
                    tr.innerHTML = `
                        <td>${new Date(txn.transaction_date).toLocaleDateString('en-GB')}</td>
                        <td class="${isCredit ? 'ps-4 text-muted' : ''}">${escapeHtml(txn.account_name)}</td>
                        <td>${escapeHtml(txn.description)}</td>
                        <td class="text-end font-monospace">${debitAmount}</td>
                        <td class="text-end font-monospace">${creditAmount}</td>
                        <td>${sourceHtml}</td>
                    `;
                    tableBody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('[TransactionSearch] Error:', error);
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Failed to load search results.</td></tr>`;
            });
    }, 300);

    // Attach event listeners to all filter controls
    accountIdInput.addEventListener('change', doTransactionSearch);
    descriptionInput.addEventListener('input', doTransactionSearch);
    
    // Initial load
    doTransactionSearch();
}

// ──────────────────────────────────────── End ─────────────────────────────────────────

// ──────────────────── DOM Ready ───────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('accountSearchForm')) { initAccountList(); } // Call the list page handler
    if (document.getElementById('accountForm')) { initAccountEntry(); } // Call the entry page handler
    if (document.getElementById('transactionSearchForm')) { initTransactionList(); }
});
// ───────────────────────── End ──────────────────────────