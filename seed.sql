-- Minimal seed data for testing

USE rla_medical_delivery;

-- Addresses
INSERT INTO addresses (street, city, postal_code, latitude, longitude) VALUES
('10 Rue de la Santé','Paris','75013',48.833,2.365),
('20 Avenue de l''Hôpital','Paris','75014',48.832,2.324),
('5 Rue des Pharmaciens','Issy-les-Moulineaux','92130',48.826,2.264),
('1 Boulevard Pasteur','Paris','75015',48.846,2.304),
('50 Rue de l''Université','Paris','75007',48.856,2.314),
('12 Route de Versailles','Versailles','78000',48.804,2.120),
('3 Rue de la Gare','Nanterre','92000',48.892,2.206),
('77 Avenue Victor Hugo','Boulogne-Billancourt','92100',48.835,2.241),
('9 Place Léon Blum','Saint-Denis','93200',48.936,2.357),
('22 Rue du Commerce','Neuilly-sur-Seine','92200',48.887,2.274);

-- Clients
INSERT INTO clients (name, client_type, address_id, contact_phone) VALUES
('Hôpital Saint-Jean','hospital',1,'+33123456789'),
('Clinique du Centre','clinic',2,'+33198765432'),
('Pharmacie Centrale','pharmacy',3,'+33111223344'),
('Centre de Soins Rive Gauche','clinic',4,'+33122334455'),
('Clinique Sainte-Marie','clinic',5,'+33133445566'),
('Pharmacie du Parc','pharmacy',6,'+33144556677'),
('Laboratoire BioTest','laboratory',7,'+33155667788'),
('Maison de Santé Est','hospital',8,'+33166778899'),
('Cabinet Médical Central','clinic',9,'+33177889900'),
('Pharmacie du Commerce','pharmacy',10,'+33188990011');

-- Vehicle types and vehicles
INSERT INTO vehicle_types (name, capacity_kg, capacity_m3, max_range_km) VALUES
('Small van',1000,3.5,200),
('Large van',2500,10,350),
('Refrigerated van',1500,6,250);

INSERT INTO vehicles (vehicle_type_id, plate, refrigerated, status) VALUES
(1,'AB-123-CD',FALSE,'available'),
(1,'EF-456-GH',FALSE,'available'),
(1,'IJ-789-KL',FALSE,'available'),
(2,'MN-012-OP',FALSE,'available'),
(2,'QR-345-ST',FALSE,'available'),
(3,'UV-678-WX',TRUE,'available');

-- Drivers
INSERT INTO drivers (name, phone, license_number, base_vehicle_id) VALUES
('Alice','+33600000001','LIC-A',1),
('Bob','+33600000002','LIC-B',2),
('Carlos','+33600000003','LIC-C',3),
('Danielle','+33600000004','LIC-D',4),
('Ethan','+33600000005','LIC-E',6);

-- Products
INSERT INTO products (sku, name, weight_kg, volume_m3, temperature_sensitive, shelf_life_hours) VALUES
('MED-001','Vaccine A',0.5,0.001,TRUE,24),
('MED-002','Antibiotic B',0.2,0.0005,FALSE,72),
('SUP-001','Syringe Pack',1.0,0.01,FALSE,240),
('MED-003','Insulin C',0.1,0.0003,TRUE,48),
('MED-004','Painkiller D',0.05,0.0002,FALSE,720),
('MED-005','Blood Bag',0.8,0.005,TRUE,12),
('SUP-002','Bandage Roll',0.3,0.002,FALSE,1000),
('MED-006','Antiseptic E',0.4,0.0015,FALSE,3650),
('MED-007','Covid Test Kit',0.15,0.0008,FALSE,168),
('SUP-003','Gloves Box',0.6,0.004,FALSE,2000);

-- Example deliveries (all at once, before delivery_items)
INSERT INTO deliveries (client_id, pickup_time, deadline, priority, temperature_sensitive, total_weight_kg, total_volume_m3, notes) VALUES
(1, DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 6 HOUR), 1, TRUE, 5.0, 0.01, 'Hospital urgent vaccine delivery'),
(2, DATE_ADD(NOW(), INTERVAL 8 HOUR), DATE_ADD(NOW(), INTERVAL 20 HOUR), 2, FALSE, 2.0, 0.02, 'Clinic supplies'),
(3, DATE_ADD(NOW(), INTERVAL 10 HOUR), DATE_ADD(NOW(), INTERVAL 30 HOUR), 4, FALSE, 0.5, 0.001, 'Pharmacy restock'),
(4, DATE_ADD(NOW(), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 12 HOUR), 2, FALSE, 10.0, 0.1, 'Rive Gauche supplies'),
(5, DATE_ADD(NOW(), INTERVAL 6 HOUR), DATE_ADD(NOW(), INTERVAL 18 HOUR), 3, TRUE, 3.0, 0.02, 'Sainte-Marie urgent meds'),
(6, DATE_ADD(NOW(), INTERVAL 12 HOUR), DATE_ADD(NOW(), INTERVAL 36 HOUR), 4, FALSE, 1.5, 0.02, 'Pharmacie restock'),
(7, DATE_ADD(NOW(), INTERVAL 20 HOUR), DATE_ADD(NOW(), INTERVAL 48 HOUR), 5, FALSE, 8.0, 0.05, 'Central cabinet supplies');

INSERT INTO delivery_items (delivery_id, product_id, quantity) VALUES
(1,1,10),(1,3,5),(2,2,8),(3,3,2),
(4,4,20),(5,5,6),(5,6,8),(6,7,12),(7,8,15),(7,9,3),(7,10,2);

-- Example assignments (initial)
INSERT INTO delivery_assignments (delivery_id, driver_id, vehicle_id, scheduled_start, scheduled_end) VALUES
(1,5,6, DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 5 HOUR)),
(2,1,1, DATE_ADD(NOW(), INTERVAL 8 HOUR), DATE_ADD(NOW(), INTERVAL 12 HOUR));