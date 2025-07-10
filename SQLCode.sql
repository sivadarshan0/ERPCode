-- Main sequence table
CREATE TABLE system_sequences (
    sequence_name VARCHAR(50) PRIMARY KEY,
    prefix VARCHAR(5) NOT NULL,
    next_value INT NOT NULL DEFAULT 1,
    digit_length TINYINT NOT NULL DEFAULT 5,
    description VARCHAR(100),
    last_used_at TIMESTAMP NULL,
    last_used_by VARCHAR(50) NULL
);

-- Customers table
CREATE TABLE customers (
    customer_id VARCHAR(10) PRIMARY KEY, -- Format: CUS00001
    phone VARCHAR(15) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    city VARCHAR(50),
    postal_code VARCHAR(20),
    email VARCHAR(100),
    first_order_date DATE,
    description TEXT,
    created_at DATETIME NOT NULL,
    created_by INT NOT NULL,
    updated_at DATETIME NOT NULL,
    updated_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Initialize sequences
INSERT INTO system_sequences (sequence_name, prefix, description) VALUES 
('customer_id', 'CUS', 'Customer IDs');