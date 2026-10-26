CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    name TEXT NOT NULL,
    phone TEXT NULL,
    email TEXT NULL,
    credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX idx_customer_email ON customers (email);

CREATE TABLE IF NOT EXISTS customer_addresses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    label TEXT NOT NULL DEFAULT 'Home',
    line1 TEXT NOT NULL,
    line2 TEXT NULL,
    city TEXT NOT NULL,
    state TEXT NOT NULL,
    pincode TEXT NOT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sku TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT NULL,
    category TEXT NULL,
    unit TEXT NOT NULL DEFAULT 'piece',
    price DECIMAL(12,2) NULL,
    is_quotation_only INTEGER NOT NULL DEFAULT 0,
    available_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    min_order_quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    linked_animal_id INTEGER NULL,
    linked_inventory_item_id INTEGER NULL,
    image_path TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (sku),
    FOREIGN KEY (linked_animal_id) REFERENCES animals(id),
    FOREIGN KEY (linked_inventory_item_id) REFERENCES inventory_items(id)
);

CREATE TABLE IF NOT EXISTS carts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cart_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cart_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price_snapshot DECIMAL(12,2) NULL,
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE (cart_id, product_id)
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_number TEXT NOT NULL,
    customer_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'placed',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    delivery_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    delivery_method TEXT NOT NULL DEFAULT 'pickup',
    delivery_address_id INTEGER NULL,
    expected_dispatch_date TEXT NULL,
    notes TEXT NULL,
    created_by INTEGER NULL,
    placed_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (order_number),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (delivery_address_id) REFERENCES customer_addresses(id)
);
CREATE INDEX idx_order_status ON orders (status);
CREATE INDEX idx_order_customer ON orders (customer_id);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    product_name_snapshot TEXT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    linked_animal_id INTEGER NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS order_status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    changed_by INTEGER NULL,
    changed_at TEXT NOT NULL,
    notes TEXT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
CREATE INDEX idx_history_order ON order_status_history (order_id);

CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number TEXT NOT NULL,
    order_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'unpaid',
    issued_at TEXT NOT NULL,
    due_date TEXT NULL,
    UNIQUE (invoice_number),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method TEXT NOT NULL,
    reference TEXT NULL,
    is_refund INTEGER NOT NULL DEFAULT 0,
    received_at TEXT NOT NULL,
    recorded_by INTEGER NULL,
    notes TEXT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);
