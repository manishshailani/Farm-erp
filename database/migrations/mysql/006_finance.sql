-- Income/expense ledger. "transfer" rows move money between two of the
-- farm's own accounts and must never be summed as income or expense in
-- reports (see FinanceService::profitAndLoss, which filters type != 'transfer').
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'cash', -- cash, bank
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_account_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    txn_date DATE NOT NULL,
    type VARCHAR(10) NOT NULL, -- income, expense, transfer
    category VARCHAR(100) NOT NULL,
    enterprise VARCHAR(50) NULL, -- livestock, orchard, pond, general
    amount DECIMAL(14,2) NOT NULL,
    account_id INT NULL,
    transfer_to_account_id INT NULL, -- only set when type = transfer
    related_type VARCHAR(50) NULL, -- order, inventory_purchase, vaccination, employee, animal
    related_id INT NULL,
    payment_method VARCHAR(30) NULL,
    counterparty VARCHAR(150) NULL,
    reference VARCHAR(100) NULL,
    notes VARCHAR(500) NULL,
    is_capital_expenditure TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NULL,
    approved_by INT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_txn_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_txn_transfer_account FOREIGN KEY (transfer_to_account_id) REFERENCES accounts(id),
    KEY idx_txn_date (txn_date),
    KEY idx_txn_type (type),
    KEY idx_txn_related (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
