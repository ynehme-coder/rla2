<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MediRun - Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&family=Unica+One&family=Anta&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
</head>
<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
  header('Location: tracking.php');
  exit();
}

require_once __DIR__ . '/../../backend/db/DeliveriesRepository.php';
require_once __DIR__ . '/../../backend/db/DriversRepository.php';
require_once __DIR__ . '/../../backend/db/Database.php';

$db = Database::getConnection();
$deliveriesRepo = new DeliveriesRepository();
$driversRepo = new DriversRepository();
$clientsStmt = $db->query('SELECT id, name FROM clients ORDER BY name ASC LIMIT 25');
$clients = $clientsStmt->fetchAll();

$stmt = $db->prepare('SELECT COUNT(*) as count FROM deliveries WHERE status = ?');
$stmt->execute(['pending']);
$pendingCount = $stmt->fetch()['count'];

$stmt = $db->prepare('SELECT COUNT(*) as count FROM deliveries WHERE status = ?');
$stmt->execute(['in_progress']);
$activeCount = $stmt->fetch()['count'];

$stmt = $db->prepare('SELECT COUNT(*) as count FROM deliveries WHERE status = ? AND DATE(created_at) = CURDATE()');
$stmt->execute(['done']);
$completedTodayCount = $stmt->fetch()['count'];

$availableDrivers = $driversRepo->findAvailableDrivers();
$availableCount = count($availableDrivers);

$stmt = $db->prepare('SELECT d.id, d.name, d.phone FROM drivers d LIMIT 5');
$stmt->execute();
$dashboardDrivers = $stmt->fetchAll();

$stmt = $db->prepare('SELECT v.id, v.status, v.plate FROM vehicles v LIMIT 5');
$stmt->execute();
$dashboardVehicles = $stmt->fetchAll();

$stmt = $db->prepare('SELECT de.id, de.priority, de.created_at FROM deliveries de WHERE de.status = ? ORDER BY de.deadline ASC LIMIT 5');
$stmt->execute(['pending']);
$pendingDeliveries = $stmt->fetchAll();
?>

<body>
  <!-- SIDEBAR -->
  <?php include('../components/nav.php'); ?>

  <!-- MAIN -->
  <div class="main-wrap">
    <!-- Top Bar -->
    <header class="topbar">
      <span class="topbar-title">Dashboard</span>
      <div class="topbar-actions">
        <button class="btn btn-gold" id="newDeliveryBtn" type="button">+ New Delivery</button>
      </div>
    </header>

    <!-- Page Content -->
    <main class="content">

      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1>Welcome <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</h1>
          <p><?php echo date('l, d F Y'); ?></p>
        </div>
        <button class="btn btn-primary" id="runAutoAssignBtn" type="button">
          <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
          </svg>
          Run Auto-Assignment
        </button>
      </div>

      <!-- Metric Cards -->
      <div class="metrics-row">
        <div class="metric-card c-red">
          <div class="metric-label">Pending Assignments</div>
          <div class="metric-value"><?php echo $pendingCount; ?></div>
        </div>
        <div class="metric-card c-blue">
          <div class="metric-label">Active Deliveries</div>
          <div class="metric-value"><?php echo $activeCount; ?></div>
        </div>
        <div class="metric-card c-green">
          <div class="metric-label">Completed Today</div>
          <div class="metric-value"><?php echo $completedTodayCount; ?></div>
        </div>
        <div class="metric-card c-gold">
          <div class="metric-label">Available Drivers</div>
          <div class="metric-value"><?php echo $availableCount; ?></div>
        </div>
      </div>

      <!-- Panels Row -->
      <div class="panels-row">

        <!-- Available Drivers -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Drivers</span>
            <span class="panel-action"><a href="drivers.php" style="color:inherit">View all →</a></span>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Driver Name</th>
                <th>Status</th>
                <th>Vehicle</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($dashboardDrivers)): ?>
                <?php foreach ($dashboardDrivers as $driver): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($driver['name']); ?></td>
                    <td><span class="status-pill pill-green">Available</span></td>
                    <td><?php echo htmlspecialchars($driver['phone'] ?? 'N/A'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">No drivers available</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Fleet Status -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Fleet Status</span>
            <span class="panel-action"><a href="vehicles.php" style="color:inherit">View all →</a></span>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Vehicle ID</th>
                <th>Status</th>
                <th>Current Driver</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($dashboardVehicles)): ?>
                <?php foreach ($dashboardVehicles as $vehicle): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($vehicle['plate']); ?></td>
                    <td><span class="status-pill pill-green"><?php echo htmlspecialchars($vehicle['status']); ?></span></td>
                    <td>-</td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">No vehicles available</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>

      <!-- Bottom Row -->
      <div class="bottom-row">

        <!-- Pending Deliveries -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Pending Deliveries</span>
            <span class="panel-action"><a href="deliveries.php" style="color:inherit">View all →</a></span>
          </div>
          <?php if (!empty(array_slice($pendingDeliveries, 0, 5))): ?>
            <?php foreach (array_slice($pendingDeliveries, 0, 5) as $delivery): ?>
              <div class="queue-item">
                <div class="queue-id">#<?php echo htmlspecialchars($delivery['id']); ?></div>
                <div class="queue-info">
                  <div class="queue-dest">Delivery</div>
                  <div class="queue-meta">Priority: <?php echo htmlspecialchars($delivery['priority'] ?? 'N/A'); ?></div>
                </div>
                <div class="queue-priority prio-high">Pending</div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="padding:20px;text-align:center;color:var(--text-muted)">No pending deliveries</div>
          <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Recent Activity</span>
          </div>
          <div style="padding:20px;text-align:center;color:var(--text-muted)">
            Activity tracking coming soon
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- FAB -->
  <button class="fab" title="New Delivery" id="newDeliveryFab" type="button">
    <svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor">
      <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
    </svg>
  </button>

  <div class="modal-overlay" data-modal="newDeliveryModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Create New Delivery</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content" data-modal-content>
        <form id="newDeliveryForm">
          <div class="form-group">
            <label class="form-label">Client</label>
            <select name="client_id" class="form-select" required>
              <option value="">-- Choose a client --</option>
              <?php foreach ($clients as $client): ?>
                <option value="<?php echo htmlspecialchars($client['id']); ?>"><?php echo htmlspecialchars($client['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Deadline</label>
            <input type="datetime-local" name="deadline" class="form-input" required>
          </div>

          <div class="form-group">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
              <option value="1">Critical</option>
              <option value="2">High</option>
              <option value="3" selected>Medium</option>
              <option value="4">Low</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Weight (kg)</label>
            <input type="number" step="0.001" min="0" name="total_weight_kg" class="form-input" value="0">
          </div>

          <div class="form-group">
            <label class="form-label">Volume (m3)</label>
            <input type="number" step="0.0001" min="0" name="total_volume_m3" class="form-input" value="0">
          </div>

          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-input" style="min-height: 90px; resize: vertical;"></textarea>
          </div>

          <label class="checkbox-item" style="margin-bottom: 0;">
            <input type="checkbox" name="temperature_sensitive" class="checkbox-input" value="1">
            <span class="checkbox-label">Temperature sensitive</span>
          </label>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="btn btn-primary" id="submitNewDeliveryBtn">Create Delivery</button>
      </div>
    </div>
  </div>

  <script src="../js/components.js"></script>
  <script>
    const newDeliveryModal = new Modal('newDeliveryModal');

    const openNewDelivery = () => newDeliveryModal.open();

    document.getElementById('newDeliveryBtn').addEventListener('click', openNewDelivery);
    document.getElementById('newDeliveryFab').addEventListener('click', openNewDelivery);

    document.getElementById('runAutoAssignBtn').addEventListener('click', () => {
      window.location.href = '../../../RunAssignment.php';
    });

    document.getElementById('submitNewDeliveryBtn').addEventListener('click', async () => {
      const form = document.getElementById('newDeliveryForm');
      const clientId = form.elements['client_id'].value;
      const deadline = form.elements['deadline'].value;

      if (!clientId || !deadline) {
        Toast.error('Client and deadline are required', 'Validation Error');
        return;
      }

      try {
        const response = await fetch('../../backend/api/index.php?action=create_delivery', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            client_id: parseInt(clientId, 10),
            deadline: deadline.replace('T', ' ') + ':00',
            priority: parseInt(form.elements['priority'].value, 10),
            total_weight_kg: parseFloat(form.elements['total_weight_kg'].value || '0'),
            total_volume_m3: parseFloat(form.elements['total_volume_m3'].value || '0'),
            temperature_sensitive: form.elements['temperature_sensitive'].checked ? 1 : 0,
            notes: form.elements['notes'].value.trim(),
          })
        });

        const data = await response.json();

        if (response.ok) {
          Toast.success('Delivery created successfully', 'Success');
          newDeliveryModal.close();
          setTimeout(() => window.location.reload(), 1000);
        } else {
          Toast.error(data.error || 'Failed to create delivery', 'Error');
        }
      } catch (error) {
        Toast.error('Network error: ' + error.message, 'Error');
      }
    });

  </script>

</body>
</html>
