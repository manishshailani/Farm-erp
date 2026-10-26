CREATE TABLE orchard_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    species TEXT NOT NULL,
    variety TEXT NULL,
    notes TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE orchard_trees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tree_code TEXT NOT NULL,
    block_id INTEGER NOT NULL,
    row_number INTEGER NULL,
    position_in_row INTEGER NULL,
    planting_date TEXT NULL,
    status TEXT NOT NULL DEFAULT 'alive',
    notes TEXT NULL,
    created_at TEXT NOT NULL,
    UNIQUE (tree_code),
    FOREIGN KEY (block_id) REFERENCES orchard_blocks(id) ON DELETE CASCADE
);

CREATE TABLE orchard_harvests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    block_id INTEGER NOT NULL,
    tree_id INTEGER NULL,
    harvest_date TEXT NOT NULL,
    quantity_kg DECIMAL(10,2) NOT NULL,
    grade TEXT NULL,
    inventory_item_id INTEGER NULL,
    recorded_by INTEGER NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (block_id) REFERENCES orchard_blocks(id),
    FOREIGN KEY (tree_id) REFERENCES orchard_trees(id)
);

CREATE TABLE ponds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    surface_area_sqm DECIMAL(10,2) NULL,
    estimated_depth_m DECIMAL(6,2) NULL,
    water_source TEXT NULL,
    notes TEXT NULL,
    created_at TEXT NOT NULL,
    UNIQUE (name)
);

CREATE TABLE pond_stocking_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pond_id INTEGER NOT NULL,
    species TEXT NOT NULL,
    stocking_date TEXT NOT NULL,
    fingerling_quantity INTEGER NOT NULL,
    average_stocking_weight_g DECIMAL(8,2) NULL,
    purchase_price DECIMAL(12,2) NULL,
    status TEXT NOT NULL DEFAULT 'growing',
    harvest_quantity_kg DECIMAL(10,2) NULL,
    harvest_date TEXT NULL,
    sale_amount DECIMAL(12,2) NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (pond_id) REFERENCES ponds(id) ON DELETE CASCADE
);

CREATE TABLE pond_water_quality_readings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pond_id INTEGER NOT NULL,
    measured_at TEXT NOT NULL,
    temperature_c DECIMAL(5,2) NULL,
    dissolved_oxygen_mgl DECIMAL(5,2) NULL,
    ph DECIMAL(4,2) NULL,
    ammonia_mgl DECIMAL(6,3) NULL,
    measurement_method TEXT NOT NULL DEFAULT 'manual',
    recorded_by INTEGER NULL,
    FOREIGN KEY (pond_id) REFERENCES ponds(id) ON DELETE CASCADE
);
