#!/bin/bash

# Table creation script with proper authentication
DB_NAME="erpdb"
ADMIN_USER="dbauser"
ADMIN_PASS="dbauser"

# Function to display error and exit
error_exit() {
    echo "$1" 1>&2
    exit 1
}

# Verify database connection first
if ! mysql -u "$ADMIN_USER" -p"$ADMIN_PASS" -e "USE $DB_NAME"; then
    error_exit "Failed to connect to database. Check credentials and try again."
fi

# Create tables
mysql -u "$ADMIN_USER" -p"$ADMIN_PASS" "$DB_NAME" <<EOF || error_exit "Failed to create tables"
-- Sequence table for all code generation
CREATE TABLE IF NOT EXISTS sequences (
    name VARCHAR(50) PRIMARY KEY,
    next_value INT NOT NULL DEFAULT 1
);

-- Customer table
CREATE TABLE IF NOT EXISTS customers (
    CustomerCode VARCHAR(20) PRIMARY KEY,
    PhoneNumber VARCHAR(15) NOT NULL UNIQUE,
    Name VARCHAR(100) NOT NULL,
    Email VARCHAR(100),
    Address TEXT,
    City VARCHAR(50),
    District VARCHAR(50),
    FirstOrderDate DATE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Category table
CREATE TABLE IF NOT EXISTS category (
    CategoryCode VARCHAR(20) PRIMARY KEY,
    Category VARCHAR(100) NOT NULL UNIQUE,
    Description TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sub-category table
CREATE TABLE IF NOT EXISTS sub_category (
    SubCategoryCode VARCHAR(20) PRIMARY KEY,
    SubCategory VARCHAR(100) NOT NULL UNIQUE,
    Description TEXT,
    CategoryCode VARCHAR(20) NOT NULL,
    FOREIGN KEY (CategoryCode) REFERENCES category(CategoryCode),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Item table
CREATE TABLE IF NOT EXISTS item (
    ItemCode VARCHAR(20) PRIMARY KEY,
    Item VARCHAR(100) NOT NULL UNIQUE,
    Description TEXT,
    CategoryCode VARCHAR(20) NOT NULL,
    SubCategoryCode VARCHAR(20) NOT NULL,
    FOREIGN KEY (CategoryCode) REFERENCES category(CategoryCode),
    FOREIGN KEY (SubCategoryCode) REFERENCES sub_category(SubCategoryCode),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- GRN tables
CREATE TABLE IF NOT EXISTS grn_master (
    GRNCode VARCHAR(20) PRIMARY KEY,
    GRNDate DATE NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grn_detail (
    GRNDetailID INT AUTO_INCREMENT PRIMARY KEY,
    GRNCode VARCHAR(20) NOT NULL,
    ItemCode VARCHAR(20) NOT NULL,
    UOM VARCHAR(10) NOT NULL,
    Quantity DECIMAL(10,2) NOT NULL,
    CostPrice DECIMAL(10,2) NOT NULL,
    Remarks TEXT,
    FOREIGN KEY (GRNCode) REFERENCES grn_master(GRNCode),
    FOREIGN KEY (ItemCode) REFERENCES item(ItemCode),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert initial sequences
INSERT IGNORE INTO sequences (name) VALUES 
('customer_code'), 
('category_code'), 
('subcategory_code'), 
('item_code'), 
('grn_code');
EOF

echo "Tables created successfully"