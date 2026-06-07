<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('location: login.php?err=2');
    exit();
}
require_once __DIR__ . '/src/algorithm/AssignmentEngine.php';
require_once __DIR__ . '/src/backend/db/Database.php';

// Handle reset for testing
if (!empty($_GET['reset'])) {
    $con = Database::getConnection();
    
    // Reset deliveries from 'assigned' back to 'pending'
    $sql = "UPDATE deliveries SET status = 'pending' WHERE status = 'assigned'";
    $con->prepare($sql)->execute();
    
    // Reset vehicles from 'enroute' back to 'available'
    $sql = "UPDATE vehicles SET status = 'available' WHERE status = 'enroute'";
    $con->prepare($sql)->execute();
    
    // Clear assignment records
    $sql = "DELETE FROM delivery_assignments";
    $con->prepare($sql)->execute();
    
    // Clear groups
    $sql = "DELETE FROM delivery_group_items";
    $con->prepare($sql)->execute();
    $sql = "DELETE FROM delivery_groups";
    $con->prepare($sql)->execute();
    
    header('location: RunAssignment.php');
    exit();
}

$engine  = new AssignmentEngine();
$results = $engine->run();
$flags   = $engine->getFlags();

// Restructure fresh results to match fallback display format
// Fresh results are grouped by driver with queue positions
if (!empty($results)) {
    $restructured = [];
    $driverHours = [];
    
    foreach ($results as $group) {
        $driver_id = $group['driver_id'];
        
        if (!isset($restructured[$driver_id])) {
            $restructured[$driver_id] = [
                'driver_id'  => $driver_id,
                'total_hours' => 0,
                'queues'     => []
            ];
            $driverHours[$driver_id] = 0;
        }
        
        // Each group is 1 hour (count of orders in the group)
        $group_hours = count($group['orders'] ?? []);
        $driverHours[$driver_id] += $group_hours;
        
        // Get queue position from database (for fresh results just assigned)
        // Since we just saved them, query to get the queue position
        $con = Database::getConnection();
        $first_order_id = $group['orders'][0]['id'] ?? null;
        if ($first_order_id) {
            $sql = "SELECT queue_position FROM delivery_assignments WHERE delivery_id = :id LIMIT 1";
            $stmt = $con->prepare($sql);
            $stmt->execute([':id' => $first_order_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $queue_pos = $row['queue_position'] ?? 1;
        } else {
            $queue_pos = 1;
        }
        
        $key = "queue_" . $queue_pos;
        $restructured[$driver_id]['queues'][$key] = [
            'queue_position' => $queue_pos,
            'vehicle_id'     => $group['vehicle_id'] ?? null,
            'orders'         => $group['orders'] ?? [],
            'total_weight'   => $group['total_weight'] ?? 0,
            'needs_cooling'  => $group['needs_cooling'] ?? false
        ];
    }
    
    // Update total hours for each driver
    foreach ($restructured as &$driver_data) {
        $driver_data['total_hours'] = $driverHours[$driver_data['driver_id']];
    }
    unset($driver_data);
    
    $results = array_values($restructured);
}

// If no new assignments, fetch previously assigned orders for viewing
if (empty($results)) {
    $con = Database::getConnection();
    
    $sql = "
        SELECT
            da.id,
            da.driver_id,
            da.vehicle_id,
            da.assignment_status,
            da.queue_position,
            d.id AS order_id,
            d.priority,
            d.deadline,
            d.total_weight_kg,
            d.temperature_sensitive,
            d.status,
            dg.id AS group_id,
            dg.name AS group_name
        FROM delivery_assignments da
        JOIN deliveries d ON d.id = da.delivery_id
        LEFT JOIN delivery_group_items dgi ON dgi.delivery_id = d.id
        LEFT JOIN delivery_groups dg ON dg.id = dgi.group_id
        WHERE da.assignment_status IN ('assigned', 'started')
        ORDER BY da.driver_id, da.queue_position, d.priority DESC
    ";
    
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total hours per driver (count of assignments * 1 hour each)
    $driverHours = [];
    foreach ($assignments as $assign) {
        $driver_id = $assign['driver_id'];
        if (!isset($driverHours[$driver_id])) {
            $driverHours[$driver_id] = 0;
        }
        // Count each assignment as 1 hour
        $driverHours[$driver_id]++;
    }
    
    // Group by driver_id, then by queue_position
    $groupedByDriver = [];
    foreach ($assignments as $assign) {
        $driver_id = $assign['driver_id'];
        $queue_pos = $assign['queue_position'];
        
        if (!isset($groupedByDriver[$driver_id])) {
            $groupedByDriver[$driver_id] = [
                'driver_id'  => $driver_id,
                'total_hours' => $driverHours[$driver_id],
                'queues'     => []
            ];
        }
        
        $key = "queue_" . $queue_pos;
        if (!isset($groupedByDriver[$driver_id]['queues'][$key])) {
            $groupedByDriver[$driver_id]['queues'][$key] = [
                'queue_position' => $queue_pos,
                'vehicle_id'     => $assign['vehicle_id'],
                'orders'         => [],
                'total_weight'   => 0,
                'needs_cooling'  => false
            ];
        }
        
        $groupedByDriver[$driver_id]['queues'][$key]['orders'][] = $assign;
        // Accumulate weight
        $groupedByDriver[$driver_id]['queues'][$key]['total_weight'] += (float)($assign['total_weight_kg'] ?? 0);
        // Check if any order needs cooling
        if ($assign['temperature_sensitive']) {
            $groupedByDriver[$driver_id]['queues'][$key]['needs_cooling'] = true;
        }
    }
    
    if (!empty($groupedByDriver)) {
        $results = array_values($groupedByDriver);
        $flags[] = [
            'message' => 'No pending deliveries. Showing previously assigned deliveries. <a href="?reset=1" style="color:blue;">Reset for testing</a>'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auto-Assignment - MediRun</title>
</head>
<body>
<h2>Auto-Assignment Results</h2>
<a href="src/frontend/pages/index.php">← Dashboard</a>
<br><br>

<?php if (empty($results) && empty($flags)): ?>
    <p>No pending deliveries to assign.</p>

<?php else: ?>

    <?php if (!empty($flags)): ?>
        <h3 style="color:red;">⚠ Manager Alerts</h3>
        <ul>
            <?php foreach ($flags as $flag): ?>
                <li style="color:red;"><?= $flag['message'] ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
        <h3>Assigned Groups</h3>
        <?php foreach ($results as $i => $driver_group): ?>
            <fieldset style="margin-bottom:20px; border: 2px solid #333; padding: 10px;">
                <legend><strong>Driver ID: <?= htmlspecialchars($driver_group['driver_id']) ?></strong> 
                    | Total Hours Assigned: <?= $driver_group['total_hours'] ?> hours
                </legend>
                
                <?php foreach ($driver_group['queues'] as $queue_item): ?>
                    <div style="margin: 10px 0; padding: 10px; background-color: #f0f0f0; border-left: 4px solid #007bff;">
                        <strong>Queue Position #<?= $queue_item['queue_position'] ?></strong>
                        — Vehicle ID: <?= htmlspecialchars($queue_item['vehicle_id']) ?>
                        | Total Weight: <?= htmlspecialchars($queue_item['total_weight']) ?> kg
                        | Cooling needed: <?= $queue_item['needs_cooling'] ? 'Yes' : 'No' ?>
                        
                        <table border="1" cellpadding="5" style="margin-top: 10px; width: 100%;">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Priority</th>
                                    <th>Label</th>
                                    <th>Score</th>
                                    <th>Deadline</th>
                                    <th>Weight (kg)</th>
                                    <th>Temp Sensitive</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($queue_item['orders'] as $order): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['order_id'] ?? $order['id']) ?></td>
                                        <td><?= htmlspecialchars($order['priority']) ?></td>
                                        <td><?= htmlspecialchars($order['label'] ?? '—') ?></td>
                                        <td><?= isset($order['score']) ? round($order['score'], 3) : '—' ?></td>
                                        <td><?= htmlspecialchars($order['deadline'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($order['total_weight_kg']) ?></td>
                                        <td><?= $order['temperature_sensitive'] ? 'Yes' : 'No' ?></td>
                                        <td><?= htmlspecialchars($order['status'] ?? $order['assignment_status'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>