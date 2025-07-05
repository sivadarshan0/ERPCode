-- FILE: create_tables.sql
-- PURPOSE: Define Category, Sub-Category, and Item Tables with Triggers
-- DATABASE: erpdb

USE erpdb;

-- Drop existing tables if re-running
DROP TABLE IF EXISTS item;
DROP TABLE IF EXISTS sub_category;
DROP TABLE IF EXISTS category;

-- 1. Category Table
CREATE TABLE category (
  CategoryCode VARCHAR(10) PRIMARY KEY,
  Category     VARCHAR(100) NOT NULL UNIQUE,
  Description  TEXT,
  CreatedAt    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Sub-Category Table
CREATE TABLE sub_category (
  SubCategoryCode VARCHAR(10) PRIMARY KEY,
  SubCategory     VARCHAR(100) NOT NULL UNIQUE,
  Description     TEXT,
  CreatedAt       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Item Table
CREATE TABLE item (
  ItemCode        VARCHAR(10) PRIMARY KEY,
  Item            VARCHAR(100) NOT NULL UNIQUE,
  Description     TEXT,
  CategoryCode    VARCHAR(10),
  SubCategoryCode VARCHAR(10),
  CreatedAt       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (CategoryCode) REFERENCES category(CategoryCode) ON DELETE SET NULL,
  FOREIGN KEY (SubCategoryCode) REFERENCES sub_category(SubCategoryCode) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggers without DELIMITER (for VS Code / SQLTools)

CREATE TRIGGER trg_category_ai
BEFORE INSERT ON category
FOR EACH ROW
BEGIN
  IF NEW.CategoryCode IS NULL OR NEW.CategoryCode = '' THEN
    SET NEW.CategoryCode = (
      SELECT CONCAT('cat', LPAD(IFNULL(MAX(CAST(SUBSTRING(CategoryCode, 4) AS UNSIGNED)), 0) + 1, 6, '0'))
      FROM category
    );
  END IF;
END;

CREATE TRIGGER trg_sub_category_ai
BEFORE INSERT ON sub_category
FOR EACH ROW
BEGIN
  IF NEW.SubCategoryCode IS NULL OR NEW.SubCategoryCode = '' THEN
    SET NEW.SubCategoryCode = (
      SELECT CONCAT('sca', LPAD(IFNULL(MAX(CAST(SUBSTRING(SubCategoryCode, 4) AS UNSIGNED)), 0) + 1, 6, '0'))
      FROM sub_category
    );
  END IF;
END;

CREATE TRIGGER trg_item_ai
BEFORE INSERT ON item
FOR EACH ROW
BEGIN
  IF NEW.ItemCode IS NULL OR NEW.ItemCode = '' THEN
    SET NEW.ItemCode = (
      SELECT CONCAT('itm', LPAD(IFNULL(MAX(CAST(SUBSTRING(ItemCode, 4) AS UNSIGNED)), 0) + 1, 6, '0'))
      FROM item
    );
  END IF;
END;
