-- Livestock master data, RFID tags and lifecycle/vaccination events.

CREATE TABLE IF NOT EXISTS species (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    tracking_level VARCHAR(20) NOT NULL DEFAULT 'individual', -- 'individual' or 'batch'
    UNIQUE KEY uq_species_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS breeds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    species_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    CONSTRAINT fk_breed_species FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE,
    UNIQUE KEY uq_breed_species_name (species_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS animals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_code VARCHAR(50) NOT NULL,
    species_id INT NOT NULL,
    breed_id INT NULL,
    name VARCHAR(100) NULL,
    sex VARCHAR(10) NOT NULL DEFAULT 'unknown',
    date_of_birth DATE NULL,
    estimated_age_months INT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'born_on_farm', -- born_on_farm, purchased, transferred, other
    purchase_date DATE NULL,
    purchase_cost DECIMAL(12,2) NULL,
    dam_id INT NULL,
    sire_id INT NULL,
    pen_location VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- active, sold, deceased, transferred, reserved
    current_weight_kg DECIMAL(8,2) NULL,
    health_status VARCHAR(20) NOT NULL DEFAULT 'healthy',
    notes TEXT NULL,
    photo_path VARCHAR(255) NULL,
    sold_at DATETIME NULL,
    disposed_at DATETIME NULL,
    disposal_reason VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_animal_code (animal_code),
    CONSTRAINT fk_animal_species FOREIGN KEY (species_id) REFERENCES species(id),
    CONSTRAINT fk_animal_breed FOREIGN KEY (breed_id) REFERENCES breeds(id),
    CONSTRAINT fk_animal_dam FOREIGN KEY (dam_id) REFERENCES animals(id),
    CONSTRAINT fk_animal_sire FOREIGN KEY (sire_id) REFERENCES animals(id),
    KEY idx_animal_species (species_id),
    KEY idx_animal_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Only one ACTIVE rfid tag may exist per tag_number at a time. A tag can be
-- deactivated and the same physical number re-registered later (e.g. a
-- replaced ear tag), so uniqueness is enforced on the *active* tag number
-- only, via a generated column + unique index (MySQL 8.0.13+).
CREATE TABLE IF NOT EXISTS rfid_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag_number VARCHAR(100) NOT NULL,
    animal_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_at DATETIME NOT NULL,
    assigned_by INT NULL,
    deactivated_at DATETIME NULL,
    deactivation_reason VARCHAR(255) NULL,
    active_tag_number VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN is_active = 1 THEN tag_number ELSE NULL END) STORED,
    CONSTRAINT fk_rfid_animal FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rfid_active_tag (active_tag_number),
    KEY idx_rfid_tag_number (tag_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rfid_scan_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag_number VARCHAR(100) NOT NULL,
    animal_id INT NULL,
    matched TINYINT(1) NOT NULL,
    scanned_by INT NULL,
    scanned_at DATETIME NOT NULL,
    action_taken VARCHAR(50) NULL,
    KEY idx_scan_log_tag (tag_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS animal_weight_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    recorded_at DATETIME NOT NULL,
    weight_kg DECIMAL(8,2) NOT NULL,
    recorded_by INT NULL,
    notes VARCHAR(255) NULL,
    CONSTRAINT fk_weight_animal FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
    KEY idx_weight_animal_time (animal_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Generic chronological lifecycle log: movement, breeding, pregnancy check,
-- birth outcome, quarantine, disease symptom, treatment, disposal, etc.
-- event_type is an open vocabulary (documented in docs/DATA_DICTIONARY.md)
-- rather than a rigid ENUM, matching the spec's instruction to support
-- configurable, species-appropriate workflows.
CREATE TABLE IF NOT EXISTS animal_lifecycle_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    details JSON NULL,
    recorded_by INT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_lifecycle_animal FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
    KEY idx_lifecycle_animal (animal_id),
    KEY idx_lifecycle_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vaccinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    vaccine_name VARCHAR(150) NOT NULL,
    target_disease VARCHAR(150) NULL,
    dose VARCHAR(50) NULL,
    administered_date DATE NOT NULL,
    batch_number VARCHAR(100) NULL,
    expiry_date DATE NULL,
    administered_by VARCHAR(150) NULL,
    veterinarian VARCHAR(150) NULL,
    next_due_date DATE NULL,
    follow_up_notes TEXT NULL,
    inventory_item_id INT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_vacc_animal FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
    KEY idx_vacc_next_due (next_due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
