CREATE TABLE species (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    tracking_level TEXT NOT NULL DEFAULT 'individual',
    UNIQUE (name)
);

CREATE TABLE breeds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    species_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE,
    UNIQUE (species_id, name)
);

CREATE TABLE animals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    animal_code TEXT NOT NULL,
    species_id INTEGER NOT NULL,
    breed_id INTEGER NULL,
    name TEXT NULL,
    sex TEXT NOT NULL DEFAULT 'unknown',
    date_of_birth TEXT NULL,
    estimated_age_months INTEGER NULL,
    source TEXT NOT NULL DEFAULT 'born_on_farm',
    purchase_date TEXT NULL,
    purchase_cost DECIMAL(12,2) NULL,
    dam_id INTEGER NULL,
    sire_id INTEGER NULL,
    pen_location TEXT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    current_weight_kg DECIMAL(8,2) NULL,
    health_status TEXT NOT NULL DEFAULT 'healthy',
    notes TEXT NULL,
    photo_path TEXT NULL,
    sold_at TEXT NULL,
    disposed_at TEXT NULL,
    disposal_reason TEXT NULL,
    created_by INTEGER NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (animal_code),
    FOREIGN KEY (species_id) REFERENCES species(id),
    FOREIGN KEY (breed_id) REFERENCES breeds(id),
    FOREIGN KEY (dam_id) REFERENCES animals(id),
    FOREIGN KEY (sire_id) REFERENCES animals(id)
);
CREATE INDEX idx_animal_species ON animals (species_id);
CREATE INDEX idx_animal_status ON animals (status);

CREATE TABLE rfid_tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tag_number TEXT NOT NULL,
    animal_id INTEGER NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    assigned_at TEXT NOT NULL,
    assigned_by INTEGER NULL,
    deactivated_at TEXT NULL,
    deactivation_reason TEXT NULL,
    active_tag_number TEXT GENERATED ALWAYS AS (CASE WHEN is_active = 1 THEN tag_number ELSE NULL END) STORED,
    FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
    UNIQUE (active_tag_number)
);
CREATE INDEX idx_rfid_tag_number ON rfid_tags (tag_number);

CREATE TABLE rfid_scan_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tag_number TEXT NOT NULL,
    animal_id INTEGER NULL,
    matched INTEGER NOT NULL,
    scanned_by INTEGER NULL,
    scanned_at TEXT NOT NULL,
    action_taken TEXT NULL
);
CREATE INDEX idx_scan_log_tag ON rfid_scan_log (tag_number);

CREATE TABLE animal_weight_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    animal_id INTEGER NOT NULL,
    recorded_at TEXT NOT NULL,
    weight_kg DECIMAL(8,2) NOT NULL,
    recorded_by INTEGER NULL,
    notes TEXT NULL,
    FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
);
CREATE INDEX idx_weight_animal_time ON animal_weight_events (animal_id, recorded_at);

CREATE TABLE animal_lifecycle_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    animal_id INTEGER NOT NULL,
    event_type TEXT NOT NULL,
    event_date TEXT NOT NULL,
    details TEXT NULL,
    recorded_by INTEGER NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
);
CREATE INDEX idx_lifecycle_animal ON animal_lifecycle_events (animal_id);
CREATE INDEX idx_lifecycle_type ON animal_lifecycle_events (event_type);

CREATE TABLE vaccinations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    animal_id INTEGER NOT NULL,
    vaccine_name TEXT NOT NULL,
    target_disease TEXT NULL,
    dose TEXT NULL,
    administered_date TEXT NOT NULL,
    batch_number TEXT NULL,
    expiry_date TEXT NULL,
    administered_by TEXT NULL,
    veterinarian TEXT NULL,
    next_due_date TEXT NULL,
    follow_up_notes TEXT NULL,
    inventory_item_id INTEGER NULL,
    created_by INTEGER NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
);
CREATE INDEX idx_vacc_next_due ON vaccinations (next_due_date);
