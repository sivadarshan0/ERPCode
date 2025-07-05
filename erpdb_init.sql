-- erpdb_init.sql
-- Create database, tables, and user privileges without sample data

CREATE DATABASE IF NOT EXISTS erpdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erpdb;

-- Table: category
CREATE TABLE IF NOT EXISTS category (
  CategoryCode VARCHAR(10) NOT NULL PRIMARY KEY,
  Category VARCHAR(100) NOT NULL UNIQUE,
  Description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: sub_category
CREATE TABLE IF NOT EXISTS sub_category (
  SubCategoryCode VARCHAR(10) NOT NULL PRIMARY KEY,
  SubCategory VARCHAR(100) NOT NULL UNIQUE,
  Description TEXT,
  CategoryCode VARCHAR(10) NOT NULL,
  FOREIGN KEY (CategoryCode) REFERENCES category(CategoryCode) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: item
CREATE TABLE IF NOT EXISTS item (
  ItemCode INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  Item VARCHAR(200) NOT NULL UNIQUE,
  Description TEXT,
  CategoryCode VARCHAR(10) NOT NULL,
  SubCategoryCode VARCHAR(10) NOT NULL,
  FOREIGN KEY (CategoryCode) REFERENCES category(CategoryCode) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (SubCategoryCode) REFERENCES sub_category(SubCategoryCode) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: customers
CREATE TABLE IF NOT EXISTS customers (
  CustomerCode INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  PhoneNumber VARCHAR(15) NOT NULL UNIQUE,
  Name VARCHAR(100) NOT NULL,
  Email VARCHAR(100),
  Address TEXT,
  City VARCHAR(50),
  District VARCHAR(50),
  FirstOrderDate DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: grn_master
CREATE TABLE IF NOT EXISTS grn_master (
  GRNCode VARCHAR(10) NOT NULL PRIMARY KEY,
  GRNDate DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: grn_detail
CREATE TABLE IF NOT EXISTS grn_detail (
  ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  GRNCode VARCHAR(10) NOT NULL,
  ItemCode INT NOT NULL,
  UOM VARCHAR(10) NOT NULL,
  Quantity INT NOT NULL CHECK (Quantity >= 0),
  CostPrice DECIMAL(10,2) NOT NULL,
  Remarks TEXT,
  FOREIGN KEY (GRNCode) REFERENCES grn_master(GRNCode) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (ItemCode) REFERENCES item(ItemCode) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user and grant privileges
CREATE USER IF NOT EXISTS 'dbauser'@'localhost' IDENTIFIED BY 'dbauser';
GRANT ALL PRIVILEGES ON erpdb.* TO 'dbauser'@'localhost';
FLUSH PRIVILEGES;
