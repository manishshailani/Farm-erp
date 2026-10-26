CREATE TABLE IF NOT EXISTS inventory_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    UNIQUE (name)
);

CREATE TABLE IF NOT EXISTS inventory_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sku TEXT NOT NULL,
    name TEXT NOT NULL,
    category_id INTEGER NULL,
    unit TEXT NOT NULL,
    reorder_threshold DECIMAL(12,2) NOT NULL DEFAULT 0,
    current_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    average_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    storage_location TEXT NULL,
    allow_negative_stock INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (sku),
    FOREIGN KEY (category_id) REFERENCES inventory_categories(id)
);

CREATE TABLE IF NOT EXISTS inventory_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    batch_number TEXT NULL,
    quantity_received DECIMAL(12,2) NOT NULL,
    quantity_remaining DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,4) NOT NULL,
    supplier_name TEXT NULL,
    received_date TEXT NOT NULL,
    expiry_date TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
);
CREATE INDEX idx_batch_item ON inventory_batches (item_id);
CREATE INDEX idx_batch_expiry ON inventory_batches (expiry_date);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    batch_id INTEGER NULL,
    movement_type TEXT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,4) NULL,
    reference_type TEXT NULL,
    reference_id INTEGER NULL,
    notes TEXT NULL,
    created_by INTEGER NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (batch_id) REFERENCES inventory_batches(id)
);
CREATE INDEX idx_movement_item ON stock_movements (item_id);
CREATE INDEX idx_movement_reference ON stock_movements (reference_type, reference_id);
