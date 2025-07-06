// Global application JavaScript
// file: Site/assets/js/app.js

document.addEventListener('DOMContentLoaded', function () {
    initAutoComplete();
    initFormValidations();

    // Initialize exchange rate if calculator is loaded
    if (document.getElementById('rate')) {
        updateRate(false);
    }
});

/* ---------------- AutoComplete ---------------- */
function initAutoComplete() {
    document.querySelectorAll('[data-autocomplete]').forEach(input => {
        const target = input.dataset.autocomplete;
        const dropdown = document.getElementById(target);

        if (!dropdown) return;

        input.addEventListener('input', debounce(async function () {
            const query = this.value.trim();
            dropdown.innerHTML = '';

            if (query.length < 2) return;

            try {
                const res = await fetch(`/search.php?type=${this.dataset.type}&q=${encodeURIComponent(query)}`);
                const items = await res.json();

                items.forEach(item => {
                    const option = document.createElement('div');
                    option.className = 'autocomplete-item';
                    option.textContent = item.text;
                    option.addEventListener('click', () => {
                        input.value = item.text;
                        if (input.dataset.valueField) {
                            document.getElementById(input.dataset.valueField).value = item.id;
                        }
                        dropdown.innerHTML = '';
                    });
                    dropdown.appendChild(option);
                });
            } catch (error) {
                console.error('Autocomplete error:', error);
            }
        }, 300));
    });
}

/* ---------------- Form Validation ---------------- */
function initFormValidations() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function (e) {
            let isValid = true;

            this.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('error');
                    isValid = false;
                } else {
                    input.classList.remove('error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill all required fields');
            }
        });
    });
}

/* ---------------- Debounce ---------------- */
function debounce(func, wait) {
    let timeout;
    return function () {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

/* ---------------- Auto Logout ---------------- */
window.addEventListener('unload', function () {
    navigator.sendBeacon('/Site/logout.php?auto=1');
});

/* ---------------- Price Calculator ---------------- */
const fallbackRates = { USD: 300, EUR: 325, GBP: 375, INR: 3.75 };
const apiKey = '2dceae62011fd1aa98c40c89';

function getCacheKey(currency) {
    return `rate_${currency}`;
}

function cacheRate(currency, rate) {
    const data = {
        value: rate,
        timestamp: Date.now()
    };
    localStorage.setItem(getCacheKey(currency), JSON.stringify(data));
}

function getCachedRate(currency) {
    const raw = localStorage.getItem(getCacheKey(currency));
    if (!raw) return null;
    try {
        const data = JSON.parse(raw);
        if ((Date.now() - data.timestamp) < 12 * 60 * 60 * 1000) {
            return data.value;
        }
    } catch (e) {}
    return null;
}

async function fetchRate(base) {
    try {
        const res = await fetch(`https://v6.exchangerate-api.com/v6/${apiKey}/pair/${base}/LKR`);
        const data = await res.json();
        if (data.result === "success") {
            cacheRate(base, data.conversion_rate);
            return data.conversion_rate;
        }
    } catch (e) {
        console.warn("Live rate fetch failed");
    }
    return null;
}

async function updateRate(forceRefresh = false) {
    const currencyEl = document.getElementById('currency');
    const rateField = document.getElementById('rate');
    const status = document.getElementById('rateStatus');

    if (!currencyEl || !rateField || !status) return;

    const currency = currencyEl.value;
    rateField.setAttribute('readonly', true);
    status.textContent = 'Fetching live rate…';

    let rate = null;

    if (!forceRefresh) {
        rate = getCachedRate(currency);
        if (rate) {
            rateField.value = rate.toFixed(4);
            status.textContent = '✔️ Using cached rate. You can refresh if needed.';
        }
    }

    const liveRate = await fetchRate(currency);
    if (liveRate !== null) {
        rateField.value = liveRate.toFixed(4);
        status.textContent = '✔️ Live rate fetched. You can edit if needed.';
    } else if (!rate) {
        rate = fallbackRates[currency];
        rateField.value = rate.toFixed(4);
        status.textContent = '⚠️ Using fallback rate. Edit manually if needed.';
    }

    rateField.removeAttribute('readonly');
}

function calculate() {
    const cost = parseFloat(document.getElementById('cost')?.value);
    const rate = parseFloat(document.getElementById('rate')?.value);
    const grams = parseFloat(document.getElementById('weight')?.value);
    const courierPerKg = parseFloat(document.getElementById('courier')?.value);
    const profitPct = parseFloat(document.getElementById('profit')?.value);
    const resultField = document.getElementById('result');

    if (!resultField) return;

    const values = [cost, rate, grams, courierPerKg, profitPct];
    if (values.some(v => isNaN(v))) {
        resultField.textContent = '⚠️ Please fill every field with valid numbers.';
        return;
    }

    const costLKR = cost * rate;
    const weightKg = grams / 1000;
    const courierTotal = courierPerKg * weightKg;
    const baseTotal = costLKR + courierTotal;
    const selling = baseTotal + (baseTotal * profitPct / 100);

    resultField.textContent = `Selling Price: LKR ${selling.toFixed(2)}`;
}
