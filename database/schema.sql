-- Fresh installations only. Review existing schemas before importing.
-- No property/sample data is inserted here. All tables use InnoDB.
CREATE TABLE IF NOT EXISTS buildings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS apartments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    building_id INT UNSIGNED NOT NULL,
    unit VARCHAR(30) NOT NULL,
    UNIQUE KEY apartment_identity (building_id, unit),
    FOREIGN KEY (building_id) REFERENCES buildings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hvac_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    apartment_id INT UNSIGNED NOT NULL,
    equipment_type VARCHAR(40) NOT NULL,
    unit_number INT UNSIGNED NOT NULL,
    unit_name VARCHAR(100) NOT NULL,
    overall_status VARCHAR(40) NOT NULL DEFAULT 'unknown',
    line_set_status VARCHAR(40) NOT NULL DEFAULT 'unknown',
    drain_line_status VARCHAR(40) NOT NULL DEFAULT 'unknown',
    pending_work TEXT NOT NULL,
    last_deep_cleaning DATE NULL,
    last_preventive_maintenance DATE NULL,
    last_filter_change DATE NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY equipment_identity (apartment_id, equipment_type, unit_number),
    FOREIGN KEY (apartment_id) REFERENCES apartments(id),
    CONSTRAINT central_number CHECK (equipment_type <> 'central_ac' OR unit_number = 1),
    CONSTRAINT positive_number CHECK (unit_number >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hvac_maintenance_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hvac_unit_id INT UNSIGNED NOT NULL,
    service_date DATE NOT NULL,
    maintenance_type VARCHAR(60) NOT NULL,
    notes TEXT NOT NULL,
    request_token CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY equipment_history (hvac_unit_id, service_date, id),
    FOREIGN KEY (hvac_unit_id) REFERENCES hvac_units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
