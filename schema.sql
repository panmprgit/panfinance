-- Database schema for panfinance
CREATE TABLE finance_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE finance_banks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100),
    balance DECIMAL(12,2) DEFAULT 0.00,
    type ENUM('bank','credit_card') DEFAULT 'bank',
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES finance_users(id)
);

CREATE TABLE finance_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    bank_id INT,
    date DATE,
    description VARCHAR(255),
    amount DECIMAL(12,2),
    type ENUM('income','expense') NOT NULL,
    reflected_on_balance TINYINT(1) DEFAULT 1,
    reflected_on_date DATE,
    is_reflected TINYINT(1) DEFAULT 1,
    already_in_balance TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES finance_users(id),
    FOREIGN KEY (bank_id) REFERENCES finance_banks(id)
);

CREATE TABLE finance_recurring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    description VARCHAR(128) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type ENUM('income','expense') NOT NULL,
    bank_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    days_of_week VARCHAR(15),
    day_of_month INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES finance_users(id),
    FOREIGN KEY (bank_id) REFERENCES finance_banks(id)
);

CREATE TABLE finance_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    setting_key VARCHAR(50),
    setting_value VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES finance_users(id)
);
