-- Reduced-scope orchard and pond master data for this delivery pass.
-- Implements the Farm -> Orchard -> Block -> Row -> Tree hierarchy and a
-- pond/stocking-batch master record with basic CRUD and harvest/economics
-- logging, but NOT the full maintenance-scheduling, yield-forecasting and
-- water-quality-alerting feature set described in the master spec (see
-- docs/FINAL_ACCEPTANCE_REPORT.md "Known limitations").

CREATE TABLE IF NOT EXISTS orchard_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    species VARCHAR(100) NOT NULL,
    variety VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orchard_trees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tree_code VARCHAR(50) NOT NULL,
    block_id INT NOT NULL,
    tree_row INT NULL, -- named tree_row, not row_number: ROW_NUMBER is a reserved keyword on MySQL 8+/MariaDB 10.2+ (window function) and is rejected as a bare column name
    position_in_row INT NULL,
    planting_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'alive', -- alive, dead, replaced
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_tree_code (tree_code),
    CONSTRAINT fk_tree_block FOREIGN KEY (block_id) REFERENCES orchard_blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orchard_harvests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    block_id INT NOT NULL,
    tree_id INT NULL,
    harvest_date DATE NOT NULL,
    quantity_kg DECIMAL(10,2) NOT NULL,
    grade VARCHAR(50) NULL,
    inventory_item_id INT NULL, -- produce credited into inventory, if tracked there
    recorded_by INT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_harvest_block FOREIGN KEY (block_id) REFERENCES orchard_blocks(id),
    CONSTRAINT fk_harvest_tree FOREIGN KEY (tree_id) REFERENCES orchard_trees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ponds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    surface_area_sqm DECIMAL(10,2) NULL,
    estimated_depth_m DECIMAL(6,2) NULL,
    water_source VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_pond_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pond_stocking_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pond_id INT NOT NULL,
    species VARCHAR(100) NOT NULL,
    stocking_date DATE NOT NULL,
    fingerling_quantity INT NOT NULL,
    average_stocking_weight_g DECIMAL(8,2) NULL,
    purchase_price DECIMAL(12,2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'growing', -- growing, harvested, lost
    harvest_quantity_kg DECIMAL(10,2) NULL,
    harvest_date DATE NULL,
    sale_amount DECIMAL(12,2) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_stocking_pond FOREIGN KEY (pond_id) REFERENCES ponds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pond_water_quality_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pond_id INT NOT NULL,
    measured_at DATETIME NOT NULL,
    temperature_c DECIMAL(5,2) NULL,
    dissolved_oxygen_mgl DECIMAL(5,2) NULL,
    ph DECIMAL(4,2) NULL,
    ammonia_mgl DECIMAL(6,3) NULL,
    measurement_method VARCHAR(20) NOT NULL DEFAULT 'manual', -- manual, sensor
    recorded_by INT NULL,
    CONSTRAINT fk_reading_pond FOREIGN KEY (pond_id) REFERENCES ponds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
