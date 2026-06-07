<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') { header('Location: tracking.php'); exit(); }
require_once __DIR__ . '/../../backend/db/DriversRepository.php';
$driversRepo = new DriversRepository();
$drivers = $driversRepo->getAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Drivers - MediRun</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&family=Unica+One&family=Anta&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
</head>
<body>
  <?php include('../components/nav.php'); ?>

  <div class="main-wrap">
    <header class="topbar">
      <span class="topbar-title">Drivers</span>
      <div class="topbar-actions">
        <button class="btn btn-gold" id="addDriverBtn" type="button">+ Add Driver</button>
      </div>
    </header>

    <main class="content">
      <div class="page-header">
        <div>
          <h1>Drivers</h1>
          <p><?php echo count($drivers); ?> registered drivers</p>
        </div>
      </div>

      <div class="panel">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Phone</th>
              <th>Status</th>
              <th>Base Vehicle</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($drivers)): ?>
              <?php foreach ($drivers as $d): ?>
                <tr>
                  <td><?php echo htmlspecialchars($d['id']); ?></td>
                  <td><?php echo htmlspecialchars($d['name']); ?></td>
                  <td><?php echo htmlspecialchars($d['phone'] ?? '—'); ?></td>
                  <td><span class="status-pill pill-green">Available</span></td>
                  <td><?php echo $d['base_vehicle_id'] ? '#' . htmlspecialchars($d['base_vehicle_id']) : '—'; ?></td>
                  <td>
                    <button type="button" class="btn btn-ghost btn-sm"
                      onclick="viewDriver(<?php echo (int)$d['id']; ?>, <?php echo htmlspecialchars(json_encode($d)); ?>)">View</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">No drivers found. Add one to get started.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- Add Driver Modal -->
  <div class="modal-overlay" data-modal="addDriverModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Add New Driver</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content">
        <form id="addDriverForm">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-input" placeholder="e.g. Jean Dupont" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone" class="form-input" placeholder="e.g. +33 6 12 34 56 78">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="btn btn-primary" id="submitAddDriverBtn">Add Driver</button>
      </div>
    </div>
  </div>

  <!-- View Driver Modal -->
  <div class="modal-overlay" data-modal="viewDriverModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Driver Details</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content" id="viewDriverContent"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Close</button>
      </div>
    </div>
  </div>

  <script src="../js/components.js"></script>
  <script>
    const addDriverModal  = new Modal('addDriverModal');
    const viewDriverModal = new Modal('viewDriverModal');
    const API = '../../backend/api/index.php';

    document.getElementById('addDriverBtn').addEventListener('click', () => addDriverModal.open());

    document.getElementById('submitAddDriverBtn').addEventListener('click', async () => {
      const form = document.getElementById('addDriverForm');
      const name = form.elements['name'].value.trim();
      if (!name) { Toast.error('Name is required', 'Required'); return; }

      const btn = document.getElementById('submitAddDriverBtn');
      btn.disabled = true; btn.textContent = 'Adding…';

      try {
        const res = await fetch(API + '?action=create_driver', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name, phone: form.elements['phone'].value.trim() })
        });
        const data = await res.json();
        if (res.ok) {
          Toast.success(name + ' added successfully!', 'Driver Added');
          addDriverModal.close();
          setTimeout(() => location.reload(), 900);
        } else {
          Toast.error(data.error || 'Failed to add driver', 'Error');
        }
      } catch(e) {
        Toast.error('Network error: ' + e.message, 'Error');
      } finally {
        btn.disabled = false; btn.textContent = 'Add Driver';
      }
    });

    function viewDriver(id, d) {
      document.getElementById('viewDriverContent').innerHTML = `
        <div style="display:grid;gap:12px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><div class="form-label">Driver ID</div><div>#${d.id}</div></div>
            <div><div class="form-label">Name</div><div>${d.name}</div></div>
            <div><div class="form-label">Phone</div><div>${d.phone || '—'}</div></div>
            <div><div class="form-label">Base Vehicle</div><div>${d.base_vehicle_id ? '#' + d.base_vehicle_id : '—'}</div></div>
          </div>
        </div>`;
      viewDriverModal.open();
    }
  </script>
</body>
</html>
