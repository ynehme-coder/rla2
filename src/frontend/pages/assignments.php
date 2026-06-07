<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
  header('Location: tracking.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Assignments - MediRun</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&family=Unica+One&family=Anta&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
</head>
<?php
require_once __DIR__ . '/../../backend/db/Database.php';
require_once __DIR__ . '/../../backend/db/DriversRepository.php';
require_once __DIR__ . '/../../backend/db/VehiclesRepository.php';

$db = Database::getConnection();

// Fetch all assignments
$stmt = $db->query('SELECT da.id, da.delivery_id, da.driver_id, da.vehicle_id, da.assigned_at, da.assignment_status, d.priority, d.deadline, dr.name as driver_name, v.plate FROM delivery_assignments da LEFT JOIN deliveries d ON d.id = da.delivery_id LEFT JOIN drivers dr ON dr.id = da.driver_id LEFT JOIN vehicles v ON v.id = da.vehicle_id ORDER BY da.assigned_at DESC');
$assignments = $stmt->fetchAll();

// Fetch all drivers and vehicles for modals
$driversRepo = new DriversRepository();
$vehiclesRepo = new VehiclesRepository();
$allDrivers = $driversRepo->getAll();
$allVehicles = $vehiclesRepo->getAll();

// Fetch delivery groups
$groupsStmt = $db->query('SELECT dg.id, dg.name, dg.created_at, COUNT(dgi.delivery_id) as delivery_count FROM delivery_groups dg LEFT JOIN delivery_group_items dgi ON dgi.group_id = dg.id GROUP BY dg.id ORDER BY dg.created_at DESC');
$groups = $groupsStmt->fetchAll();

// Fetch pending deliveries for grouping
$pendingStmt = $db->query('SELECT d.id, d.priority, d.deadline, d.status FROM deliveries d WHERE d.status IN ("pending", "assigned") ORDER BY d.priority ASC, d.deadline ASC');
$pendingDeliveries = $pendingStmt->fetchAll();
?>

<body>
  <!-- SIDEBAR -->
  <?php include('../components/nav.php'); ?>

  <div class="main-wrap">
    <main class="content">
      <div class="page-header">
        <div>
          <h1>Assignments</h1>
          <p>Manage delivery assignments and create groups</p>
        </div>
        <div style="display: flex; gap: 10px;">
          <button class="btn btn-primary" id="groupingBtn">📦 Create Group</button>
          <button class="btn btn-gold" id="reassignBtn">🔄 Reassign</button>
        </div>
      </div>

      <!-- Assignments Table -->
      <div class="panel" style="margin-bottom: 24px;">
        <div class="panel-header">
          <div class="panel-title">Active Assignments</div>
          <span style="font-size: 11px; color: var(--text-muted);"><?php echo count($assignments); ?> total</span>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th><input type="checkbox" id="selectAll"></th>
              <th>ID</th>
              <th>Delivery</th>
              <th>Driver</th>
              <th>Vehicle</th>
              <th>Status</th>
              <th>Priority</th>
              <th>Deadline</th>
              <th>Assigned</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($assignments)): ?>
              <?php foreach ($assignments as $assignment): ?>
                <tr data-assignment-id="<?php echo $assignment['id']; ?>" data-delivery-id="<?php echo $assignment['delivery_id']; ?>">
                  <td><input type="checkbox" class="assignment-checkbox" value="<?php echo $assignment['id']; ?>"></td>
                  <td><span style="font-family: 'Anta', sans-serif; color: var(--horizon);">#<?php echo htmlspecialchars($assignment['id']); ?></span></td>
                  <td>#<?php echo htmlspecialchars($assignment['delivery_id']); ?></td>
                  <td><?php echo htmlspecialchars($assignment['driver_name'] ?? 'Unassigned'); ?></td>
                  <td><?php echo htmlspecialchars($assignment['plate'] ?? '-'); ?></td>
                  <td><span class="status-pill pill-blue"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $assignment['assignment_status']))); ?></span></td>
                  <td>
                    <?php 
                    $prioClass = match($assignment['priority']) {
                      1 => 'prio-1',
                      2 => 'prio-2',
                      3 => 'prio-3',
                      4 => 'prio-4',
                      default => 'prio-5'
                    };
                    $prioLabel = match($assignment['priority']) {
                      1 => 'CRITICAL',
                      2 => 'HIGH',
                      3 => 'MEDIUM',
                      4 => 'LOW',
                      default => 'VERY LOW'
                    };
                    ?>
                    <span class="prio-badge <?php echo $prioClass; ?>"><?php echo $prioLabel; ?></span>
                  </td>
                  <td style="font-size: 12px;"><?php echo htmlspecialchars(date('M d, H:i', strtotime($assignment['deadline'] ?? 'now'))); ?></td>
                  <td style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars(date('M d, H:i', strtotime($assignment['assigned_at']))); ?></td>
                  <td>
                    <div class="row-actions">
                      <button class="row-btn reassign-btn" data-assignment-id="<?php echo $assignment['id']; ?>" data-driver-id="<?php echo $assignment['driver_id']; ?>" data-vehicle-id="<?php echo $assignment['vehicle_id']; ?>">Reassign</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="10" style="text-align:center;padding:20px;color:var(--text-muted)">No assignments found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Delivery Groups Section -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">Delivery Groups</div>
          <span style="font-size: 11px; color: var(--text-muted);"><?php echo count($groups); ?> groups</span>
        </div>
        <?php if (!empty($groups)): ?>
          <div style="padding: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
              <?php foreach ($groups as $group): ?>
                <div style="background: rgba(255,247,227,0.04); border: 1px solid var(--border); border-radius: 8px; padding: 14px; cursor: pointer; transition: all 0.15s;" class="group-card">
                  <div style="font-weight: 600; color: var(--horizon); margin-bottom: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;">#<?php echo htmlspecialchars($group['id']); ?></div>
                  <div style="font-size: 14px; font-weight: 500; margin-bottom: 8px;"><?php echo htmlspecialchars($group['name']); ?></div>
                  <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">
                    📦 <?php echo $group['delivery_count']; ?> deliveries
                  </div>
                  <div style="font-size: 10px; color: var(--text-muted);">Created <?php echo htmlspecialchars(date('M d, Y', strtotime($group['created_at']))); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <div style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
            <p style="font-size: 13px;">No delivery groups created yet</p>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- REASSIGNMENT MODAL -->
  <div class="modal-overlay" data-modal="reassignmentModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Reassign Delivery</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content" data-modal-content>
        <form id="reassignmentForm">
          <input type="hidden" name="assignment_id" id="reassignmentAssignmentId">
          
          <div class="form-group">
            <label class="form-label">Select Driver</label>
            <select name="driver_id" class="form-select" required>
              <option value="">-- Choose a driver --</option>
              <?php foreach ($allDrivers as $driver): ?>
                <option value="<?php echo htmlspecialchars($driver['id']); ?>">
                  <?php echo htmlspecialchars($driver['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Select Vehicle</label>
            <select name="vehicle_id" class="form-select" required>
              <option value="">-- Choose a vehicle --</option>
              <?php foreach ($allVehicles as $vehicle): ?>
                <option value="<?php echo htmlspecialchars($vehicle['id']); ?>">
                  <?php echo htmlspecialchars($vehicle['plate']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Notes (optional)</label>
            <textarea name="notes" class="form-input" style="min-height: 80px; resize: vertical; font-family: 'DM Sans', sans-serif;" placeholder="Add any notes about this reassignment..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="btn btn-primary" id="submitReassignBtn">Reassign</button>
      </div>
    </div>
  </div>

  <!-- GROUPING MODAL -->
  <div class="modal-overlay" data-modal="groupingModal" hidden>
    <div class="modal" style="max-width: 600px;">
      <div class="modal-header">
        <div class="modal-title">Create Delivery Group</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content" data-modal-content>
        <form id="groupingForm">
          <div class="form-group">
            <label class="form-label">Group Name</label>
            <input type="text" name="group_name" class="form-input" placeholder="e.g., Morning Route A" required>
          </div>

          <div class="form-group">
            <label class="form-label">Select Deliveries to Group</label>
            <div class="checkbox-list">
              <?php if (!empty($pendingDeliveries)): ?>
                <?php foreach ($pendingDeliveries as $delivery): ?>
                  <label class="checkbox-item">
                    <input type="checkbox" name="delivery_ids" class="checkbox-input" value="<?php echo $delivery['id']; ?>">
                    <span class="checkbox-label">
                      Delivery #<?php echo htmlspecialchars($delivery['id']); ?> 
                      <span style="color: var(--text-muted); font-size: 11px;">
                        (<?php echo htmlspecialchars(date('M d, H:i', strtotime($delivery['deadline']))); ?>)
                      </span>
                    </span>
                  </label>
                <?php endforeach; ?>
              <?php else: ?>
                <p style="color: var(--text-muted); font-size: 12px;">No pending deliveries available</p>
              <?php endif; ?>
            </div>
          </div>

          <div style="padding: 12px; background: rgba(61,106,193,0.1); border-radius: 6px; margin-bottom: 16px;">
            <div style="font-size: 11px; color: var(--text-muted);">
              Selected: <span id="selectedCount" style="color: var(--horizon); font-weight: 600;">0</span> deliveries
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="btn btn-primary" id="submitGroupingBtn">Create Group</button>
      </div>
    </div>
  </div>

  <script src="../js/components.js"></script>
  <script>
    // Initialize modals
    const reassignmentModal = new Modal('reassignmentModal');
    const groupingModal = new Modal('groupingModal');

    // Reassignment button
    document.querySelectorAll('.reassign-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const assignmentId = btn.dataset.assignmentId;
        const driverId = btn.dataset.driverId;
        const vehicleId = btn.dataset.vehicleId;
        
        document.getElementById('reassignmentAssignmentId').value = assignmentId;
        document.querySelector('select[name="driver_id"]').value = driverId;
        document.querySelector('select[name="vehicle_id"]').value = vehicleId;
        
        reassignmentModal.open();
      });
    });

    // Grouping button
    document.getElementById('groupingBtn').addEventListener('click', () => {
      groupingModal.open();
    });

    // Reassign button
    document.getElementById('reassignBtn').addEventListener('click', () => {
      const selected = document.querySelectorAll('.assignment-checkbox:checked');
      if (selected.length === 0) {
        Toast.warning('Please select at least one assignment to reassign', 'No Selection');
        return;
      }
      if (selected.length === 1) {
        const assignmentId = selected[0].value;
        const row = selected[0].closest('tr');
        const driverId = row.dataset.driverId || '';
        const vehicleId = row.dataset.vehicleId || '';
        
        document.getElementById('reassignmentAssignmentId').value = assignmentId;
        document.querySelector('select[name="driver_id"]').value = driverId;
        document.querySelector('select[name="vehicle_id"]').value = vehicleId;
        
        reassignmentModal.open();
      } else {
        Toast.info('Batch reassignment mode - select new driver and vehicle for all', 'Reassign Multiple', 0);
      }
    });

    // Submit reassignment
    document.getElementById('submitReassignBtn').addEventListener('click', async () => {
      const form = document.getElementById('reassignmentForm');
      const assignmentId = document.getElementById('reassignmentAssignmentId').value;
      const driverId = form.elements['driver_id'].value;
      const vehicleId = form.elements['vehicle_id'].value;

      // Validation
      if (!driverId || !vehicleId) {
        Toast.error('Please select both a driver and vehicle', 'Validation Error');
        return;
      }

      try {
        // Get the delivery_id from the selected row
        const deliveryId = document.querySelector(`tr[data-assignment-id="${assignmentId}"]`)?.dataset.deliveryId;
        
        const response = await fetch('../../backend/api/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            delivery_id: parseInt(deliveryId),
            driver_id: parseInt(driverId),
            vehicle_id: parseInt(vehicleId),
            reason: form.elements['notes'].value
          })
        });

        const data = await response.json();

        if (response.ok && data.message && data.message.includes('reassign')) {
          Toast.success('Assignment reassigned successfully', 'Success');
          reassignmentModal.close();
          setTimeout(() => location.reload(), 1500);
        } else {
          Toast.error(data.error || 'Failed to reassign', 'Error');
        }
      } catch (error) {
        Toast.error('Network error: ' + error.message, 'Error');
      }
    });

    // Submit grouping
    document.getElementById('submitGroupingBtn').addEventListener('click', async () => {
      const form = document.getElementById('groupingForm');
      const groupName = form.elements['group_name'].value.trim();
      const deliveryIds = Array.from(form.querySelectorAll('input[name="delivery_ids"]:checked'))
        .map(cb => parseInt(cb.value));

      // Validation
      if (!groupName) {
        Toast.error('Please enter a group name', 'Validation Error');
        return;
      }

      if (deliveryIds.length === 0) {
        Toast.error('Please select at least one delivery', 'Validation Error');
        return;
      }

      try {
        const response = await fetch('../../backend/api/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            name: groupName,
            delivery_ids: deliveryIds
          })
        });

        const data = await response.json();

        if (response.ok && data.message && data.message.includes('group')) {
          Toast.success(`Group "${groupName}" created with ${deliveryIds.length} deliveries`, 'Success');
          groupingModal.close();
          setTimeout(() => location.reload(), 1500);
        } else {
          Toast.error(data.error || 'Failed to create group', 'Error');
        }
      } catch (error) {
        Toast.error('Network error: ' + error.message, 'Error');
      }
    });

    // Update selected delivery count
    document.querySelectorAll('input[name="delivery_ids"]').forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        const count = document.querySelectorAll('input[name="delivery_ids"]:checked').length;
        document.getElementById('selectedCount').textContent = count;
      });
    });

    // Select all checkbox
    document.getElementById('selectAll').addEventListener('change', (e) => {
      document.querySelectorAll('.assignment-checkbox').forEach(cb => {
        cb.checked = e.target.checked;
      });
    });

    // Group card click handler
    document.querySelectorAll('.group-card').forEach(card => {
      card.addEventListener('click', () => {
        Toast.info('Group details page - Coming soon', 'Info', 2000);
      });
    });
  </script>
</body>
</html>
