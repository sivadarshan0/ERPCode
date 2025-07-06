<?php
// File: calculate_price.php

// Error config (production-safe)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Do not display errors in browser
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: /login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Price Calculator – Live Rates</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/assets/css/style.css">
  <script>
    // Indicate logged-in user for JS logic
    window.isLoggedIn = true;
  </script>
</head>
<body>
  <a href="/index.php" class="back-link">← Back to Main Menu</a>

  <div class="container">
    <h2>Price Calculator</h2>

    <label for="cost">Cost (in selected currency)</label>
    <input type="number" step="0.01" id="cost" placeholder="Enter cost" required>

    <label for="currency">Currency</label>
    <select id="currency" onchange="updateRate()" required>
      <option value="USD">USD</option>
      <option value="EUR">EUR</option>
      <option value="GBP">GBP</option>
      <option value="INR" selected>INR</option>
    </select>

    <button type="button" onclick="updateRate(true)" class="refresh-btn">🔄 Refresh Rate</button>

    <label for="rate">Exchange Rate (1 unit → LKR)</label>
    <input type="number" step="0.0001" id="rate" placeholder="Fetching…" readonly required>
    <div class="small" id="rateStatus"></div>

    <label for="weight">Weight (grams)</label>
    <input type="number" step="1" id="weight" placeholder="Enter weight in grams" required>

    <label for="courier">Courier Charges (LKR / kg)</label>
    <input type="number" step="0.01" id="courier" placeholder="Enter courier charges per kg" required>

    <label for="profit">Profit Margin (%)</label>
    <input type="number" step="0.01" id="profit" placeholder="Enter profit margin" required>

    <button onclick="calculate()">Calculate</button>

    <div id="result"></div>
  </div>

  <!-- External scripts -->
  <script src="/assets/js/calculate.js"></script>
  <script src="/assets/js/app.js"></script>
</body>
</html>
