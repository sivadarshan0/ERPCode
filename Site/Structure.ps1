# File: create-erp-structure.ps1

# Define base folder
$base = "/Users/admin/Library/CloudStorage/OneDrive-Personal/ERP/Site"

# Define folder structure and files
$structure = @{
    "assets\css"                = @("main.css")
    "assets\js"                 = @("app.js")
    "config"                    = @("database.php", "env.example")
    "includes"                  = @("auth.php", "header.php", "footer.php", "functions.php", "db.php")
    "modules\inventory"         = @("category.php", "subcategory.php", "item.php", "grn.php")
    "modules\customers"         = @("customer.php")
    "modules\pricing"           = @("calculator.php")
    "scripts"                   = @("setup-db.sh", "setup-tables.sh")
    "."                         = @("index.php", "login.php", "search.php")  # Root files
}

# Create folders and files
foreach ($folder in $structure.Keys) {
    $fullPath = Join-Path $base $folder
    if (!(Test-Path $fullPath)) {
        New-Item -ItemType Directory -Path $fullPath -Force | Out-Null
    }

    foreach ($file in $structure[$folder]) {
        $filePath = Join-Path $fullPath $file
        if (!(Test-Path $filePath)) {
            New-Item -ItemType File -Path $filePath -Force | Out-Null
        }
    }
}

Write-Host "ERP folder structure created successfully under '$base'" -ForegroundColor Green
