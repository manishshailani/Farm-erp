CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'cash',
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    UNIQUE (name)
);

CREATE TABLE IF NOT EXISTS financial_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    txn_date TEXT NOT NULL,
    type TEXT NOT NULL,
    category TEXT NOT NULL,
    enterprise TEXT NULL,
    amount DECIMAL(14,2) NOT NULL,
    account_id INTEGER NULL,
    transfer_to_account_id INTEGER NULL,
    related_type TEXT NULL,
    related_id INTEGER NULL,
    payment_method TEXT NULL,
    counterparty TEXT NULL,
    reference TEXT NULL,
    notes TEXT NULL,
    is_capital_expenditure INTEGER NOT NULL DEFAULT 0,
    created_by INTEGER NULL,
    approved_by INTEGER NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    FOREIGN KEY (transfer_to_account_id) REFERENCES accounts(id)
);
CREATE INDEX idx_txn_date ON financial_transactions (txn_date);
CREATE INDEX idx_txn_type ON financial_transactions (type);
CREATE INDEX idx_txn_related ON financial_transactions (related_type, related_id);
