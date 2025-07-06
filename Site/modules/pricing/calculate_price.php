<?php
// File: calculate_price.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="style.css">
  <title>Price Calculator – Live Rates</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body{font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;margin:0;padding:20px}
    .container{max-width:420px;margin:auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,.05)}
    h2{text-align:center;margin-top:0}
    label{display:block;margin-top:15px;font-size:.9rem}
    input,select{width:100%;padding:10px;margin-top:4px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;font-size:1rem}
    button{width:100%;padding:12px;margin-top:10px;background:#007bff;border:none;border-radius:6px;color:#fff;font-size:1rem;cursor:pointer}
    button:hover{background:#0069d9}
    #result{margin-top:20px;font-weight:bold;text-align:center;font-size:1.1rem}
    .small{font-size:.8rem;color:#555;margin-top:2px}
    .refresh-btn{background:#28a745}
    .refresh-btn:hover{background:#218838}
  </style>
</head>
<body>
  <a href="/index.php" class="back-link">← Back to Main Menu</a>
  <div class="container">
    <h2>Price Calculator</h2>

    <label for="cost">Cost (in selected currency)</label>
    <input type="number" step="0.01" id="cost" placeholder="Enter cost">

    <label for="currency">Currency</label>
    <select id="currency" onchange="updateRate()">
      <option value="USD">USD</option>
      <option value="EUR">EUR</option>
      <option value="GBP">GBP</option>
      <option value="INR" selected>INR</option>
    </select>

    <button type="button" onclick="updateRate(true)" class="refresh-btn">🔄 Refresh Rate</button>

    <label for="rate">Exchange Rate (1 unit → LKR)</label>
    <input type="number" step="0.0001" id="rate" placeholder="Fetching…" readonly>
    <div class="small" id="rateStatus"></div>

    <label for="weight">Weight (grams)</label>
    <input type="number" step="1" id="weight" placeholder="Enter weight in grams">

    <label for="courier">Courier Charges (LKR / kg)</label>
    <input type="number" step="0.01" id="courier" placeholder="Enter courier charges per kg">

    <label for="profit">Profit Margin (%)</label>
    <input type="number" step="0.01" id="profit" placeholder="Enter profit margin">

    <button onclick="calculate()">Calculate</button>

    <div id="result"></div>
  </div>

<script>
const fallbackRates = {USD: 300, EUR: 325, GBP: 375, INR: 3.75};
const apiKey = '2dceae62011fd1aa98c40c89';
const rateField = document.getElementById('rate');
const status = document.getElementById('rateStatus');

// Cache Key Format: rate_INR or rate_USD
function getCacheKey(currency) {
  return `rate_${currency}`;
}

// Save rate to localStorage with timestamp
function cacheRate(currency, rate) {
  const data = {
    value: rate,
    timestamp: Date.now()
  };
  localStorage.setItem(getCacheKey(currency), JSON.stringify(data));
}

// Retrieve cached rate (if less than 12 hours old)
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

  // Always attempt live fetch in background
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
