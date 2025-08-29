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
// ──────────────────────── End ─────────────────────────

// ──────────────────── DOM Ready ───────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Call the list page handler
    if (document.getElementById('accountSearchForm')) { initAccountList(); }
    // Call the entry page handler
    if (document.getElementById('accountForm')) { initAccountEntry(); }
});
// ───────────────────────── End ──────────────────────────