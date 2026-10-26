CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL, -- login account for the public customer portal
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_customer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_customer_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    label VARCHAR(50) NOT NULL DEFAULT 'Home',
    line1 VARCHAR(255) NOT NULL,
    line2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_address_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A product may optionally link to one animal (individual livestock
-- listing) or one inventory item (produce/feed sold directly); both are
-- nullable because many products (e.g. a quotation-only animal lot) are
-- neither. available_quantity is authoritative for stock-controlled
-- products that are NOT linked to an inventory item; for ones that are,
-- OrderService reads/decrements the linked inventory_items row instead so
-- the two bookkeeping systems never disagree.
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    category VARCHAR(100) NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'piece',
    price DECIMAL(12,2) NULL, -- NULL when is_quotation_only
    is_quotation_only TINYINT(1) NOT NULL DEFAULT 0,
    available_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    min_order_quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    linked_animal_id INT NULL,
    linked_inventory_item_id INT NULL,
    image_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_product_sku (sku),
    CONSTRAINT fk_product_animal FOREIGN KEY (linked_animal_id) REFERENCES animals(id),
    CONSTRAINT fk_product_item FOREIGN KEY (linked_inventory_item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_cart_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price_snapshot DECIMAL(12,2) NULL,
    CONSTRAINT fk_cart_item_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_item_product FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY uq_cart_product (cart_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Order lifecycle states are enforced in code (OrderService::VALID_TRANSITIONS),
-- not just documented here: draft, awaiting_quotation_approval, placed,
-- confirmed, awaiting_payment, partially_paid, processing,
-- ready_for_dispatch, dispatched, delivered, completed, cancelled,
-- refund_pending, refunded, returned.
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL,
    customer_id INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'placed',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    delivery_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    delivery_method VARCHAR(20) NOT NULL DEFAULT 'pickup', -- pickup, delivery
    delivery_address_id INT NULL,
    expected_dispatch_date DATE NULL,
    notes VARCHAR(500) NULL,
    created_by INT NULL,
    placed_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_order_number (order_number),
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_order_address FOREIGN KEY (delivery_address_id) REFERENCES customer_addresses(id),
    KEY idx_order_status (status),
    KEY idx_order_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name_snapshot VARCHAR(150) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    linked_animal_id INT NULL,
    CONSTRAINT fk_order_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    changed_by INT NULL,
    changed_at DATETIME NOT NULL,
    notes VARCHAR(500) NULL,
    CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    KEY idx_history_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'unpaid', -- unpaid, partially_paid, paid, void
    issued_at DATETIME NOT NULL,
    due_date DATE NULL,
    UNIQUE KEY uq_invoice_number (invoice_number),
    CONSTRAINT fk_invoice_order FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT fk_invoice_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method VARCHAR(30) NOT NULL, -- cash, upi, bank_transfer, cod, other
    reference VARCHAR(100) NULL,
    is_refund TINYINT(1) NOT NULL DEFAULT 0,
    received_at DATETIME NOT NULL,
    recorded_by INT NULL,
    notes VARCHAR(255) NULL,
    CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
