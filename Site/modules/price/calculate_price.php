<?php
// File: calculate_price.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/header.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Price Calculator – Live Rates</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>

<a href="/index.php" style="display:inline-block; margin: 1rem; color: #3498db; text-decoration: none;">← Back to Main Menu</a>

<div class="login-container">
  <div class="login-box calc-container">
    <h2>Price Calculator</h2>

    <div class="form-group">
      <label for="cost">Cost (in selected currency)</label>
      <input type="number" step="0.01" id="cost" placeholder="Enter cost">
    </div>

    <div class="form-group">
      <label for="currency">Currency</label>
      <select id="currency" onchange="updateRate()">
        <option value="USD">USD</option>
        <option value="EUR">EUR</option>
        <option value="GBP">GBP</option>
        <option value="INR" selected>INR</option>
      </select>
    </div>

    <div class="form-group">
      <button type="button" onclick="updateRate(true)" class="refresh-btn">🔄 Refresh Rate</button>
    </div>

    <div class="form-group">
      <label for="rate">Exchange Rate (1 unit → LKR)</label>
      <input type="number" step="0.0001" id="rate" placeholder="Fetching…" readonly>
      <div class="small" id="rateStatus"></div>
    </div>

    <div class="form-group">
      <label for="weight">Weight (grams)</label>
      <input type="number" step="1" id="weight" placeholder="Enter weight in grams">
    </div>

    <div class="form-group">
      <label for="courier">Courier Charges (LKR/kg)</label>
      <input type="number" step="0.01" id="courier" placeholder="Enter courier charges per kg">
    </div>

    <div class="form-group">
      <label for="profit">Profit Margin (%)</label>
      <input type="number" step="0.01" id="profit" placeholder="Enter profit margin">
    </div>

    <button onclick="calculate()">Calculate</button>

    <div id="result"></div>
  </div>
</div>

<script>
const fallbackRates = {USD: 300, EUR: 325, GBP: 375, INR: 3.75};
const apiKey = '2dceae62011fd1aa98c40c89';
const rateField = document.getElementById('rate');
const status = document.getElementById('rateStatus');

function getCacheKey(currency) {
  return `rate_${currency}`;
}

function cacheRate(currency, rate) {
  const data = { value: rate, timestamp: Date.now() };
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
    return null;
  } catch (e) {
    return null;
  }
}

async function updateRate(forceRefresh = false) {
  const currency = document.getElementById('currency').value;
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
    status.textContent = '⚠️ Live fetch failed – using fallback. Feel free to edit.';
  }

  rateField.removeAttribute('readonly');
}

function calculate() {
  const cost = parseFloat(document.getElementById('cost').value);
  const rate = parseFloat(document.getElementById('rate').value);
  const grams = parseFloat(document.getElementById('weight').value);
  const courierPerKg = parseFloat(document.getElementById('courier').value);
  const profitPct = parseFloat(document.getElementById('profit').value);

  const vals = [cost, rate, grams, courierPerKg, profitPct];
  if (vals.some(v => isNaN(v))) {
    document.getElementById('result').textContent = '⚠️ Please fill every field with valid numbers.';
    return;
  }

  const costLKR = cost * rate;
  const weightKg = grams / 1000;
  const courierTotal = courierPerKg * weightKg;
  const baseTotal = costLKR + courierTotal;
  const selling = baseTotal + (baseTotal * profitPct / 100);

  document.getElementById('result').textContent = `Selling Price: LKR ${selling.toFixed(2)}`;
}

window.onload = () => updateRate(false);
</script>

</body>
</html>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>