-- Inventory: categories, items, batches and a transactional stock-movement
-- ledger. current_quantity on inventory_items is a cached/derived value
-- that InventoryService keeps in sync inside the same DB transaction as
-- each stock_movements insert -- it must never be written independently of
-- a movement row, or the two will drift.

CREATE TABLE inventory_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_inventory_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    category_id INT NULL,
    unit VARCHAR(20) NOT NULL, -- kg, litre, piece, dose, bag...
    reorder_threshold DECIMAL(12,2) NOT NULL DEFAULT 0,
    current_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    average_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    storage_location VARCHAR(100) NULL,
    allow_negative_stock TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_item_sku (sku),
    CONSTRAINT fk_item_category FOREIGN KEY (category_id) REFERENCES inventory_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inventory_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    batch_number VARCHAR(100) NULL,
    quantity_received DECIMAL(12,2) NOT NULL,
    quantity_remaining DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,4) NOT NULL,
    supplier_name VARCHAR(150) NULL,
    received_date DATE NOT NULL,
    expiry_date DATE NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_batch_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    KEY idx_batch_item (item_id),
    KEY idx_batch_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FIFO consumption: StockService always deducts from the oldest
-- quantity_remaining > 0 batch(es) first, recording one stock_movements
-- row per batch touched so the batch-level cost basis is preserved.
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    batch_id INT NULL,
    movement_type VARCHAR(10) NOT NULL, -- in, out, adjustment
    quantity DECIMAL(12,2) NOT NULL, -- always positive; direction is movement_type
    unit_cost DECIMAL(12,4) NULL,
    reference_type VARCHAR(50) NULL, -- purchase, vaccination, feeding, orchard_activity, order, manual_adjustment
    reference_id INT NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_movement_item FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    CONSTRAINT fk_movement_batch FOREIGN KEY (batch_id) REFERENCES inventory_batches(id),
    KEY idx_movement_item (item_id),
    KEY idx_movement_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
