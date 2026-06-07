<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../db/DeliveriesRepository.php';
require_once __DIR__ . '/../db/DriversRepository.php';
require_once __DIR__ . '/../db/VehiclesRepository.php';
require_once __DIR__ . '/../db/ProductsRepository.php';
require_once __DIR__ . '/../db/AssignmentsRepository.php';

function readRequestBody(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        return $input;
    }

    return $_POST;
}

function respondJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function fetchScalar(PDO $db, string $sql, array $params = []): int
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if ($row === false) {
        return 0;
    }

    $value = array_values($row)[0] ?? 0;
    return (int)$value;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ── Action-based routing (allows fetches without URL rewriting) ──────────────
// Frontend can call index.php?action=create_delivery instead of needing
// path-based routing which breaks when Apache/Nginx isn't configured.
$action = $_GET['action'] ?? '';
if ($action !== '') {
    switch ($action) {
        case 'list_deliveries':
            $_SERVER['REQUEST_URI'] = '/api/deliveries';
            $path = '/api/deliveries';
            break;
        case 'create_delivery':
            $_SERVER['REQUEST_URI'] = '/api/deliveries';
            $path = '/api/deliveries';
            break;
        case 'list_drivers':
            $_SERVER['REQUEST_URI'] = '/api/drivers';
            $path = '/api/drivers';
            break;
        case 'create_driver':
            $_SERVER['REQUEST_URI'] = '/api/drivers';
            $path = '/api/drivers';
            break;
        case 'list_vehicles':
            $_SERVER['REQUEST_URI'] = '/api/vehicles';
            $path = '/api/vehicles';
            break;
        case 'create_vehicle':
            $_SERVER['REQUEST_URI'] = '/api/vehicles';
            $path = '/api/vehicles';
            break;
        case 'list_products':
            $_SERVER['REQUEST_URI'] = '/api/products';
            $path = '/api/products';
            break;
        case 'create_product':
            $_SERVER['REQUEST_URI'] = '/api/products';
            $path = '/api/products';
            break;
        case 'update_delivery_status':
            $_SERVER['REQUEST_URI'] = '/api/deliveries/status';
            $path = '/api/deliveries/status';
            break;
        case 'dashboard_stats':
            $_SERVER['REQUEST_URI'] = '/api/dashboard/stats';
            $path = '/api/dashboard/stats';
            break;
    }
}
// ─────────────────────────────────────────────────────────────────────────────

if ($method === 'GET' && preg_match('#/api/deliveries$#', $path)) {
    $deliveriesRepo = new DeliveriesRepository();
    $status = $_GET['status'] ?? 'pending';
    if ($status === 'pending') {
        $rows = $deliveriesRepo->getPendingDeliveries();
    } else {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM deliveries ORDER BY created_at DESC');
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['items'] = $deliveriesRepo->getDeliveryItems((int)$r['id']);
        }
    }
    echo json_encode(['data' => $rows]);
    exit;
}

if ($method === 'GET' && preg_match('#/api/drivers$#', $path)) {
    $driversRepo = new DriversRepository();
    $rows = $driversRepo->getAll();
    echo json_encode(['data' => $rows]);
    exit;
}

if ($method === 'GET' && preg_match('#/api/vehicles$#', $path)) {
    $vehiclesRepo = new VehiclesRepository();
    $rows = $vehiclesRepo->getAll();
    echo json_encode(['data' => $rows]);
    exit;
}

if ($method === 'GET' && preg_match('#/api/products$#', $path)) {
    $productsRepo = new ProductsRepository();
    $rows = $productsRepo->getAll();
    echo json_encode(['data' => $rows]);
    exit;
}

if ($method === 'POST' && preg_match('#/api/driver/login$#', $path)) {
    $input = readRequestBody();
    $driverId = isset($input['driver_id']) ? (int)$input['driver_id'] : 0;

    if ($driverId <= 0) {
        respondJson(400, ['error' => 'driver_id is required']);
    }

    $driversRepo = new DriversRepository();
    $driver = $driversRepo->findById($driverId);

    if ($driver === null) {
        respondJson(404, ['error' => 'Driver not found']);
    }

    respondJson(200, [
        'message' => 'Driver login successful',
        'data' => [
            'driver' => $driver,
        ],
    ]);
}

if ($method === 'GET' && preg_match('#/api/driver/queue$#', $path)) {
    $driverId = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;

    if ($driverId <= 0) {
        respondJson(400, ['error' => 'driver_id is required']);
    }

    $driversRepo = new DriversRepository();
    if ($driversRepo->findById($driverId) === null) {
        respondJson(404, ['error' => 'Driver not found']);
    }

    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT da.id, da.delivery_id, da.driver_id, da.vehicle_id, da.assigned_at, da.scheduled_start, da.scheduled_end, da.assignment_status, da.queue_position, d.priority, d.deadline, d.status AS delivery_status, v.plate
         FROM delivery_assignments da
         LEFT JOIN deliveries d ON d.id = da.delivery_id
         LEFT JOIN vehicles v ON v.id = da.vehicle_id
         WHERE da.driver_id = ? AND da.assignment_status IN (\'assigned\', \'started\')
         ORDER BY da.queue_position ASC, da.assigned_at ASC'
    );
    $stmt->execute([$driverId]);
    $rows = $stmt->fetchAll();

    respondJson(200, ['data' => $rows]);
}

if ($method === 'POST' && preg_match('#/api/driver/day-off$#', $path)) {
    $input = readRequestBody();
    $driverId = isset($input['driver_id']) ? (int)$input['driver_id'] : 0;
    $requestDate = isset($input['request_date']) ? trim((string)$input['request_date']) : '';
    $reason = isset($input['reason']) ? trim((string)$input['reason']) : '';

    if ($driverId <= 0 || $requestDate === '') {
        respondJson(400, ['error' => 'driver_id and request_date are required']);
    }

    $driversRepo = new DriversRepository();
    if ($driversRepo->findById($driverId) === null) {
        respondJson(404, ['error' => 'Driver not found']);
    }

    $db = Database::getConnection();
    $stmt = $db->prepare('INSERT INTO driver_day_off_requests (driver_id, request_date, reason, status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$driverId, $requestDate, $reason, 'pending']);

    respondJson(201, [
        'message' => 'Day-off request created',
        'data' => [
            'request_id' => (int)$db->lastInsertId(),
            'driver_id' => $driverId,
            'request_date' => $requestDate,
            'reason' => $reason,
            'status' => 'pending',
        ],
    ]);
}

if ($method === 'GET' && preg_match('#/api/driver/vehicle$#', $path)) {
    $driverId = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;

    if ($driverId <= 0) {
        respondJson(400, ['error' => 'driver_id is required']);
    }

    $driversRepo = new DriversRepository();
    $driver = $driversRepo->findById($driverId);

    if ($driver === null) {
        respondJson(404, ['error' => 'Driver not found']);
    }

    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT v.*, vt.name AS vehicle_type_name, vt.capacity_kg, vt.capacity_m3, vt.max_range_km
         FROM vehicles v
         LEFT JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
         WHERE v.id = ?'
    );
    $stmt->execute([(int)($driver['base_vehicle_id'] ?? 0)]);
    $baseVehicle = $stmt->fetch();

    $activeStmt = $db->prepare(
        'SELECT v.*, vt.name AS vehicle_type_name, vt.capacity_kg, vt.capacity_m3, vt.max_range_km, da.queue_position, da.assignment_status
         FROM delivery_assignments da
         INNER JOIN vehicles v ON v.id = da.vehicle_id
         LEFT JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
         WHERE da.driver_id = ? AND da.assignment_status IN (\'assigned\', \'started\')
         ORDER BY da.queue_position ASC, da.assigned_at ASC
         LIMIT 1'
    );
    $activeStmt->execute([$driverId]);
    $activeVehicle = $activeStmt->fetch();

    respondJson(200, [
        'data' => [
            'driver_id' => $driverId,
            'base_vehicle' => $baseVehicle === false ? null : $baseVehicle,
            'active_vehicle' => $activeVehicle === false ? null : $activeVehicle,
        ],
    ]);
}

if ($method === 'PATCH' && preg_match('#/api/deliveries/status$#', $path)) {
    $input = readRequestBody();

    $deliveryId = isset($input['delivery_id']) ? (int)$input['delivery_id'] : 0;
    $status = isset($input['status']) ? trim((string)$input['status']) : '';
    $allowedStatuses = ['pending', 'assigned', 'in_progress', 'done', 'failed'];

    if ($deliveryId <= 0 || $status === '') {
        http_response_code(400);
        echo json_encode(['error' => 'delivery_id and status are required']);
        exit;
    }

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid delivery status']);
        exit;
    }

    $deliveriesRepo = new DeliveriesRepository();
    $delivery = $deliveriesRepo->getById($deliveryId);

    if ($delivery === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Delivery not found']);
        exit;
    }

    $updated = $deliveriesRepo->updateStatus($deliveryId, $status);

    if (!$updated) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update delivery status']);
        exit;
    }

    echo json_encode([
        'message' => 'Delivery status updated',
        'data' => array_merge($delivery, ['status' => $status]),
    ]);
    exit;
}

if ($method === 'POST' && preg_match('#/api/deliveries/reassign$#', $path)) {
    $input = readRequestBody();

    $deliveryId = isset($input['delivery_id']) ? (int)$input['delivery_id'] : 0;
    $driverId = isset($input['driver_id']) ? (int)$input['driver_id'] : 0;
    $vehicleId = isset($input['vehicle_id']) ? (int)$input['vehicle_id'] : 0;
    $scheduledStart = isset($input['scheduled_start']) && $input['scheduled_start'] !== '' ? trim((string)$input['scheduled_start']) : null;
    $scheduledEnd = isset($input['scheduled_end']) && $input['scheduled_end'] !== '' ? trim((string)$input['scheduled_end']) : null;
    $reason = isset($input['reason']) ? trim((string)$input['reason']) : '';

    if ($deliveryId <= 0 || $driverId <= 0 || $vehicleId <= 0) {
        respondJson(400, ['error' => 'delivery_id, driver_id, and vehicle_id are required']);
    }

    $deliveriesRepo = new DeliveriesRepository();
    $driversRepo = new DriversRepository();
    $vehiclesRepo = new VehiclesRepository();
    $assignmentsRepo = new AssignmentsRepository();

    $delivery = $deliveriesRepo->getById($deliveryId);
    if ($delivery === null) {
        respondJson(404, ['error' => 'Delivery not found']);
    }

    $driver = $driversRepo->findById($driverId);
    if ($driver === null) {
        respondJson(404, ['error' => 'Driver not found']);
    }

    $vehicle = $vehiclesRepo->findById($vehicleId);
    if ($vehicle === null) {
        respondJson(404, ['error' => 'Vehicle not found']);
    }

    if (($vehicle['status'] ?? 'available') !== 'available') {
        respondJson(409, ['error' => 'Vehicle is not available']);
    }

    $activeAssignment = $assignmentsRepo->getActiveAssignmentByDeliveryId($deliveryId);
    $previousVehicleId = $activeAssignment !== null ? (int)($activeAssignment['vehicle_id'] ?? 0) : 0;

    Database::beginTransaction();

    try {
        $assignmentsRepo->cancelActiveAssignmentsByDeliveryId($deliveryId);
        $assignmentId = $assignmentsRepo->createAssignment($deliveryId, $driverId, $vehicleId, $scheduledStart, $scheduledEnd, 'assigned');
        $deliveriesRepo->updateStatus($deliveryId, 'assigned');

        $db = Database::getConnection();
        $vehicleStmt = $db->prepare('UPDATE vehicles SET status = ? WHERE id = ?');
        $vehicleStmt->execute(['enroute', $vehicleId]);

        if ($previousVehicleId > 0 && $previousVehicleId !== $vehicleId) {
            if ($assignmentsRepo->countActiveAssignmentsByVehicle($previousVehicleId) === 0) {
                $vehicleStmt->execute(['available', $previousVehicleId]);
            }
        }

        Database::commit();

        respondJson(200, [
            'message' => 'Delivery reassigned',
            'data' => [
                'assignment_id' => $assignmentId,
                'delivery_id' => $deliveryId,
                'driver_id' => $driverId,
                'vehicle_id' => $vehicleId,
                'status' => 'assigned',
                'reason' => $reason,
            ],
        ]);
    } catch (Throwable $e) {
        Database::rollback();
        respondJson(500, ['error' => 'Failed to reassign delivery']);
    }
}
#delivery creation (yara)
if ($method === 'POST' && preg_match('#/api/deliveries$#', $path)) {
    $input = readRequestBody();

    $clientId = isset($input['client_id']) ? (int)$input['client_id'] : 0;
    $deadline = isset($input['deadline']) ? trim((string)$input['deadline']) : '';
    $priority = isset($input['priority']) ? (int)$input['priority'] : 3;
    $temperatureSensitive = !empty($input['temperature_sensitive']) ? 1 : 0;
    $notes = isset($input['notes']) ? trim((string)$input['notes']) : '';
    $totalWeight = isset($input['total_weight_kg']) ? (float)$input['total_weight_kg'] : 0.0;
    $totalVolume = isset($input['total_volume_m3']) ? (float)$input['total_volume_m3'] : 0.0;

    if ($clientId <= 0) {
        respondJson(400, ['error' => 'client_id is required']);
    }

    if ($deadline === '') {
        respondJson(400, ['error' => 'deadline is required']);
    }

    $clientsStmt = Database::getConnection()->prepare('SELECT id FROM clients WHERE id = ?');
    $clientsStmt->execute([$clientId]);
    if ($clientsStmt->fetch() === false) {
        respondJson(404, ['error' => 'Client not found']);
    }

    $db = Database::getConnection();
    $stmt = $db->prepare('INSERT INTO deliveries (client_id, deadline, priority, status, temperature_sensitive, total_weight_kg, total_volume_m3, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$clientId, $deadline, $priority, 'pending', $temperatureSensitive, $totalWeight, $totalVolume, $notes]);

    respondJson(201, [
        'message' => 'Delivery created',
        'data' => [
            'delivery_id' => (int)$db->lastInsertId(),
            'client_id' => $clientId,
            'deadline' => $deadline,
            'priority' => $priority,
        ],
    ]);
}

if ($method === 'POST' && preg_match('#/api/delivery-groups$#', $path)) {
    $input = readRequestBody();

    $name = isset($input['name']) ? trim((string)$input['name']) : '';
    $deliveryIds = $input['delivery_ids'] ?? [];

    if (!is_array($deliveryIds)) {
        $deliveryIds = [];
    }

    $deliveryIds = array_values(array_unique(array_filter(array_map('intval', $deliveryIds), static fn ($id) => $id > 0)));

    if ($name === '') {
        $name = 'Manual group ' . date('Y-m-d H:i:s');
    }

    if (empty($deliveryIds)) {
        respondJson(400, ['error' => 'delivery_ids must contain at least one delivery']);
    }

    $deliveriesRepo = new DeliveriesRepository();
    foreach ($deliveryIds as $deliveryId) {
        if ($deliveriesRepo->getById($deliveryId) === null) {
            respondJson(404, ['error' => "Delivery not found: {$deliveryId}"]);
        }
    }

    Database::beginTransaction();

    try {
        $assignmentsRepo = new AssignmentsRepository();
        $groupId = $assignmentsRepo->createDeliveryGroup($name, $deliveryIds);
        Database::commit();

        respondJson(201, [
            'message' => 'Delivery group created',
            'data' => [
                'group_id' => $groupId,
                'name' => $name,
                'delivery_ids' => $deliveryIds,
            ],
        ]);
    } catch (Throwable $e) {
        Database::rollback();
        respondJson(500, ['error' => 'Failed to create delivery group']);
    }
}

if ($method === 'GET' && preg_match('#/api/dashboard/manager$#', $path)) {
    $db = Database::getConnection();
    $deliveriesRepo = new DeliveriesRepository();

    $pendingCount = fetchScalar($db, 'SELECT COUNT(*) AS total FROM deliveries WHERE status = ?', ['pending']);
    $assignedCount = fetchScalar($db, 'SELECT COUNT(*) AS total FROM deliveries WHERE status = ?', ['assigned']);
    $inProgressCount = fetchScalar($db, 'SELECT COUNT(*) AS total FROM deliveries WHERE status = ?', ['in_progress']);
    $overdueCount = fetchScalar($db, "SELECT COUNT(*) AS total FROM deliveries WHERE deadline < NOW() AND status NOT IN ('done', 'failed')");
    $availableDriversCount = fetchScalar($db, "SELECT COUNT(*) AS total FROM drivers d WHERE NOT EXISTS (SELECT 1 FROM delivery_assignments da WHERE da.driver_id = d.id AND da.assignment_status IN ('assigned', 'started'))");
    $availableVehiclesCount = fetchScalar($db, "SELECT COUNT(*) AS total FROM vehicles WHERE status = 'available'");
    $activeGroupsCount = fetchScalar($db, "SELECT COUNT(DISTINCT dg.id) AS total FROM delivery_groups dg INNER JOIN delivery_group_items dgi ON dgi.group_id = dg.id INNER JOIN delivery_assignments da ON da.delivery_id = dgi.delivery_id WHERE da.assignment_status IN ('assigned', 'started')");

    $recentAssignmentsStmt = $db->query('SELECT da.id, da.delivery_id, da.driver_id, da.vehicle_id, da.assigned_at, da.assignment_status, d.priority, dr.name AS driver_name, v.plate FROM delivery_assignments da LEFT JOIN deliveries d ON d.id = da.delivery_id LEFT JOIN drivers dr ON dr.id = da.driver_id LEFT JOIN vehicles v ON v.id = da.vehicle_id ORDER BY da.assigned_at DESC LIMIT 10');
    $recentAssignments = $recentAssignmentsStmt->fetchAll();

    $pendingDeliveries = $deliveriesRepo->getPendingDeliveries();

    $driversStmt = $db->query('SELECT d.id, d.name, d.phone, CASE WHEN EXISTS (SELECT 1 FROM delivery_assignments da WHERE da.driver_id = d.id AND da.assignment_status IN (\'assigned\', \'started\')) THEN 0 ELSE 1 END AS available FROM drivers d ORDER BY d.name ASC LIMIT 10');
    $dashboardDrivers = $driversStmt->fetchAll();

    $vehiclesStmt = $db->query('SELECT v.id, v.plate, v.status, v.refrigerated, vt.capacity_kg, vt.capacity_m3 FROM vehicles v LEFT JOIN vehicle_types vt ON vt.id = v.vehicle_type_id ORDER BY v.plate ASC LIMIT 10');
    $dashboardVehicles = $vehiclesStmt->fetchAll();

    respondJson(200, [
        'data' => [
            'counts' => [
                'pending_deliveries' => $pendingCount,
                'assigned_deliveries' => $assignedCount,
                'in_progress_deliveries' => $inProgressCount,
                'overdue_deliveries' => $overdueCount,
                'available_drivers' => $availableDriversCount,
                'available_vehicles' => $availableVehiclesCount,
                'active_groups' => $activeGroupsCount,
            ],
            'recent_assignments' => $recentAssignments,
            'pending_deliveries' => $pendingDeliveries,
            'drivers' => $dashboardDrivers,
            'vehicles' => $dashboardVehicles,
        ],
    ]);
}

if ($method === 'GET' && preg_match('#/api/dashboard/stats$#', $path)) {
    $range = $_GET['range'] ?? 'today';
    $db = Database::getConnection();

    $deliveryWhere = '1 = 1';
    $assignmentWhere = '1 = 1';
    $params = [];

    if ($range === 'today') {
        $deliveryWhere = 'DATE(created_at) = CURDATE()';
        $assignmentWhere = 'DATE(assigned_at) = CURDATE()';
    } elseif ($range === 'week') {
        $deliveryWhere = 'created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
        $assignmentWhere = 'assigned_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
    }

    $stats = [
        'deliveries_total' => fetchScalar($db, "SELECT COUNT(*) AS total FROM deliveries WHERE {$deliveryWhere}", $params),
        'deliveries_pending' => fetchScalar($db, "SELECT COUNT(*) AS total FROM deliveries WHERE {$deliveryWhere} AND status = 'pending'", $params),
        'deliveries_assigned' => fetchScalar($db, "SELECT COUNT(*) AS total FROM deliveries WHERE {$deliveryWhere} AND status = 'assigned'", $params),
        'deliveries_completed' => fetchScalar($db, "SELECT COUNT(*) AS total FROM deliveries WHERE {$deliveryWhere} AND status = 'done'", $params),
        'assignments_total' => fetchScalar($db, "SELECT COUNT(*) AS total FROM delivery_assignments WHERE {$assignmentWhere}", $params),
        'groups_total' => fetchScalar($db, "SELECT COUNT(*) AS total FROM delivery_groups", $params),
        'overdue_deliveries' => fetchScalar($db, "SELECT COUNT(*) AS total FROM deliveries WHERE deadline < NOW() AND status NOT IN ('done', 'failed')", $params),
    ];

    respondJson(200, [
        'range' => $range,
        'data' => $stats,
    ]);
}

if ($method === 'POST' && preg_match('#/api/assignments/auto-assign$#', $path)) {
    require_once __DIR__ . '/assignments.php';
    assignments_auto_assign();
    exit;
}

// ── POST /api/drivers ─────────────────────────────────────────────────────────
if ($method === 'POST' && preg_match('#/api/drivers$#', $path)) {
    $input = readRequestBody();
    $name  = isset($input['name'])  ? trim((string)$input['name'])  : '';
    $phone = isset($input['phone']) ? trim((string)$input['phone']) : '';
    if ($name === '') { respondJson(400, ['error' => 'name is required']); }
    $db   = Database::getConnection();
    $stmt = $db->prepare('INSERT INTO drivers (name, phone) VALUES (?, ?)');
    $stmt->execute([$name, $phone]);
    respondJson(201, ['message' => 'Driver created', 'data' => ['id' => (int)$db->lastInsertId(), 'name' => $name, 'phone' => $phone]]);
}

// ── POST /api/vehicles ────────────────────────────────────────────────────────
if ($method === 'POST' && preg_match('#/api/vehicles$#', $path)) {
    $input        = readRequestBody();
    $plate        = isset($input['plate'])        ? trim((string)$input['plate'])        : '';
    $capacityKg   = isset($input['capacity_kg'])  ? (float)$input['capacity_kg']         : 0.0;
    $capacityM3   = isset($input['capacity_m3'])  ? (float)$input['capacity_m3']         : 0.0;
    $tempCapable  = !empty($input['temperature_capable']) ? 1 : 0;
    if ($plate === '') { respondJson(400, ['error' => 'plate is required']); }
    $db   = Database::getConnection();
    $stmt = $db->prepare('INSERT INTO vehicles (plate, capacity_kg, capacity_m3, temperature_capable, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$plate, $capacityKg, $capacityM3, $tempCapable, 'available']);
    respondJson(201, ['message' => 'Vehicle created', 'data' => ['id' => (int)$db->lastInsertId(), 'plate' => $plate]]);
}

// ── POST /api/products ────────────────────────────────────────────────────────
if ($method === 'POST' && preg_match('#/api/products$#', $path)) {
    $input    = readRequestBody();
    $name     = isset($input['name'])      ? trim((string)$input['name'])     : '';
    $sku      = isset($input['sku'])       ? trim((string)$input['sku'])      : '';
    $weightKg = isset($input['weight_kg']) ? (float)$input['weight_kg']       : 0.0;
    $volumeM3 = isset($input['volume_m3']) ? (float)$input['volume_m3']       : 0.0;
    if ($name === '') { respondJson(400, ['error' => 'name is required']); }
    $db   = Database::getConnection();
    $stmt = $db->prepare('INSERT INTO products (name, sku, weight_kg, volume_m3) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $sku, $weightKg, $volumeM3]);
    respondJson(201, ['message' => 'Product created', 'data' => ['id' => (int)$db->lastInsertId(), 'name' => $name, 'sku' => $sku]]);
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
