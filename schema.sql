-- MySQL schema for RLA medical delivery project

CREATE DATABASE IF NOT EXISTS rla_medical_delivery
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rla_medical_delivery;

-- Address / clients
CREATE TABLE addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  street VARCHAR(255),
  city VARCHAR(128),
  postal_code VARCHAR(32),
  latitude NUMERIC(9,6),
  longitude NUMERIC(9,6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  client_type VARCHAR(32),
  address_id INTEGER,
  contact_phone VARCHAR(32),
  contact_email VARCHAR(255),
  CONSTRAINT fk_clients_address
    FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products and items
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(64),
  name VARCHAR(255) NOT NULL,
  weight_kg NUMERIC(8,3) DEFAULT 0,
  volume_m3 NUMERIC(8,4) DEFAULT 0,
  temperature_sensitive BOOLEAN DEFAULT FALSE,
  shelf_life_hours INTEGER -- nominal max life in hours after pickup
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INTEGER,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  pickup_time TIMESTAMP, -- when goods enter vehicle
  deadline TIMESTAMP,    -- must be delivered before this
  priority SMALLINT DEFAULT 3, -- 1 highest (hospital early), 4 lowest
  status VARCHAR(32) DEFAULT 'pending', -- pending, assigned, in_progress, done, failed
  temperature_sensitive BOOLEAN DEFAULT FALSE,
  total_weight_kg NUMERIC(10,3) DEFAULT 0,
  total_volume_m3 NUMERIC(10,4) DEFAULT 0,
  notes TEXT,
  CONSTRAINT fk_deliveries_client
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE delivery_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_id INTEGER,
  product_id INTEGER,
  quantity INTEGER DEFAULT 1,
  CONSTRAINT fk_delivery_items_delivery
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_items_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE time_windows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_id INTEGER,
  window_start TIMESTAMP,
  window_end TIMESTAMP,
  CONSTRAINT fk_time_windows_delivery
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vehicles and drivers
CREATE TABLE vehicle_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64),
  capacity_kg NUMERIC(10,2),
  capacity_m3 NUMERIC(10,4),
  max_range_km INTEGER
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_type_id INTEGER,
  plate VARCHAR(64),
  refrigerated BOOLEAN DEFAULT FALSE,
  status VARCHAR(32) DEFAULT 'available', -- available, enroute, maintenance
  CONSTRAINT fk_vehicles_vehicle_type
    FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE drivers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(32),
  license_number VARCHAR(64),
  base_vehicle_id INTEGER,
  CONSTRAINT fk_drivers_base_vehicle
    FOREIGN KEY (base_vehicle_id) REFERENCES vehicles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE driver_shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_id INTEGER,
  shift_date DATE NOT NULL,
  start_time TIME,
  end_time TIME,
  max_hours SMALLINT DEFAULT 8,
  CONSTRAINT fk_driver_shifts_driver
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assignment of deliveries to driver/vehicle
CREATE TABLE delivery_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_id INTEGER,
  driver_id INTEGER,
  vehicle_id INTEGER,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  scheduled_start TIMESTAMP NULL DEFAULT NULL,
  scheduled_end TIMESTAMP NULL DEFAULT NULL,
  assignment_status VARCHAR(32) DEFAULT 'assigned', -- assigned, started, completed, cancelled
  queue_position INT NOT NULL DEFAULT 1, -- position in driver's queue (1 = first, 2 = second, etc.)
  CONSTRAINT fk_delivery_assignments_delivery
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_assignments_driver
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
  CONSTRAINT fk_delivery_assignments_vehicle
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tracking & events
CREATE TABLE tracking_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_id INTEGER,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  latitude NUMERIC(9,6),
  longitude NUMERIC(9,6),
  status VARCHAR(64),
  note TEXT,
  CONSTRAINT fk_tracking_events_delivery
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE driver_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_id INTEGER,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  latitude NUMERIC(9,6),
  longitude NUMERIC(9,6),
  CONSTRAINT fk_driver_locations_driver
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE driver_day_off_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_id INTEGER NOT NULL,
  request_date DATE NOT NULL,
  reason TEXT,
  status VARCHAR(32) DEFAULT 'pending', -- pending, approved, rejected
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_driver_day_off_requests_driver
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grouping deliveries for routing
CREATE TABLE delivery_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE delivery_group_items (
  group_id INTEGER,
  delivery_id INTEGER,
  PRIMARY KEY (group_id, delivery_id)
  ,CONSTRAINT fk_delivery_group_items_group
    FOREIGN KEY (group_id) REFERENCES delivery_groups(id) ON DELETE CASCADE
  ,CONSTRAINT fk_delivery_group_items_delivery
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Useful indexes
CREATE INDEX idx_deliveries_deadline ON deliveries(deadline);
CREATE INDEX idx_deliveries_status ON deliveries(status);
CREATE INDEX idx_assignments_driver ON delivery_assignments(driver_id);
CREATE INDEX idx_tracking_delivery ON tracking_events(delivery_id);

-- Notes:
-- - The 24-hour product-in-vehicle constraint is modelled via product.shelf_life_hours
--   and deliveries.pickup_time/deadline. Enforcement should be done at application
--   or with DB triggers if desired (not included here for clarity).
