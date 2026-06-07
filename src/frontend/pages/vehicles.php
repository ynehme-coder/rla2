<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') { header('Location: tracking.php'); exit(); }
require_once __DIR__ . '/../../backend/db/VehiclesRepository.php';
$vehiclesRepo = new VehiclesRepository();
$vehicles = $vehiclesRepo->getAll();
$statusPills = ['available' => 'pill-green', 'in_use' => 'pill-blue', 'maintenance' => 'pill-gold', 'unavailable' => 'pill-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vehicles - MediRun</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&family=Unica+One&family=Anta&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
</head>
<body>
  <?php include('../components/nav.php'); ?>

  <div class="main-wrap">
    <header class="topbar">
      <span class="topbar-title">Fleet</span>
      <div class="topbar-actions">
        <button class="btn btn-gold" id="addVehicleBtn" type="button">+ Add Vehicle</button>
      </div>
    </header>

    <main class="content">
      <div class="page-header">
        <div>
          <h1>Fleet Management</h1>
          <p><?php echo count($vehicles); ?> vehicles registered</p>
        </div>
      </div>

      <div class="panel">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Plate</th>
              <th>Capacity (kg)</th>
              <th>Volume (m³)</th>
              <th>Temp. Capable</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($vehicles)): ?>
              <?php foreach ($vehicles as $v): ?>
                <?php $pill = $statusPills[$v['status'] ?? 'available'] ?? 'pill-blue'; ?>
                <tr>
                  <td><?php echo htmlspecialchars($v['id']); ?></td>
                  <td><?php echo htmlspecialchars($v['plate']); ?></td>
                  <td><?php echo htmlspecialchars($v['capacity_kg'] ?? '—'); ?></td>
                  <td><?php echo htmlspecialchars($v['capacity_m3'] ?? '—'); ?></td>
                  <td><?php echo !empty($v['temperature_capable']) ? '🌡 Yes' : 'No'; ?></td>
                  <td><span class="status-pill <?php echo $pill; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $v['status'] ?? 'available'))); ?></span></td>
                  <td>
                    <button type="button" class="btn btn-ghost btn-sm"
                      onclick="viewVehicle(<?php echo htmlspecialchars(json_encode($v)); ?>)">View</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">No vehicles found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- Add Vehicle Modal -->
  <div class="modal-overlay" data-modal="addVehicleModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Add New Vehicle</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content">
        <form id="addVehicleForm">
          <div class="form-group">
            <label class="form-label">License Plate *</label>
            <input type="text" name="plate" class="form-input" placeholder="e.g. AA-123-BB" required>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group">
              <label class="form-label">Capacity (kg)</label>
              <input type="number" step="0.1" min="0" name="capacity_kg" class="form-input" value="1000">
            </div>
            <div class="form-group">
              <label class="form-label">Volume (m³)</label>
              <input type="number" step="0.01" min="0" name="capacity_m3" class="form-input" value="5">
            </div>
          </div>
          <label class="checkbox-item">
            <input type="checkbox" name="temperature_capable" class="checkbox-input" value="1">
            <span class="checkbox-label">🌡 Temperature-controlled vehicle</span>
          </label>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="btn btn-primary" id="submitAddVehicleBtn">Add Vehicle</button>
      </div>
    </div>
  </div>

  <!-- View Vehicle Modal -->
  <div class="modal-overlay" data-modal="viewVehicleModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Vehicle Details</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content" id="viewVehicleContent"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Close</button>
      </div>
    </div>
  </div>

  <script src="../js/components.js"></script>
  <script>
    const addVehicleModal  = new Modal('addVehicleModal');
    const viewVehicleModal = new Modal('viewVehicleModal');
    const API = '../../backend/api/index.php';

    document.getElementById('addVehicleBtn').addEventListener('click', () => addVehicleModal.open());

    document.getElementById('submitAddVehicleBtn').addEventListener('click', async () => {
      const form = document.getElementById('addVehicleForm');
      const plate = form.elements['plate'].value.trim();
      if (!plate) { Toast.error('License plate is required', 'Required'); return; }

      const btn = document.getElementById('submitAddVehicleBtn');
      btn.disabled = true; btn.textContent = 'Adding…';

      try {
        const res = await fetch(API + '?action=create_vehicle', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            plate,
            capacity_kg:          parseFloat(form.elements['capacity_kg'].value || '0'),
            capacity_m3:          parseFloat(form.elements['capacity_m3'].value || '0'),
            temperature_capable:  form.elements['temperature_capable'].checked ? 1 : 0,
          })
        });
        const data = await res.json();
        if (res.ok) {
          Toast.success(plate + ' added to fleet!', 'Vehicle Added');
          addVehicleModal.close();
          setTimeout(() => location.reload(), 900);
        } else {
          Toast.error(data.error || 'Failed to add vehicle', 'Error');
        }
      } catch(e) {
        Toast.error('Network error: ' + e.message, 'Error');
      } finally {
        btn.disabled = false; btn.textContent = 'Add Vehicle';
      }
    });

    function viewVehicle(v) {
      document.getElementById('viewVehicleContent').innerHTML = `
        <div style="display:grid;gap:12px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><div class="form-label">Vehicle ID</div><div>#${v.id}</div></div>
            <div><div class="form-label">Plate</div><div>${v.plate}</div></div>
            <div><div class="form-label">Capacity</div><div>${v.capacity_kg || '—'} kg</div></div>
            <div><div class="form-label">Volume</div><div>${v.capacity_m3 || '—'} m³</div></div>
            <div><div class="form-label">Temp. Capable</div><div>${v.temperature_capable ? '🌡 Yes' : 'No'}</div></div>
            <div><div class="form-label">Status</div><div>${v.status || 'available'}</div></div>
          </div>
        </div>`;
      viewVehicleModal.open();
    }
  </script>
</body>
</html>
