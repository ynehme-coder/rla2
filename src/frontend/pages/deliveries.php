<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
  header('Location: tracking.php');
  exit();
}

require_once __DIR__ . '/../../backend/db/DeliveriesRepository.php';
require_once __DIR__ . '/../../backend/db/Database.php';
$deliveriesRepo = new DeliveriesRepository();
$deliveries = $deliveriesRepo->getAll();

$db = Database::getConnection();
$clientsStmt = $db->query('SELECT id, name FROM clients ORDER BY name ASC LIMIT 50');
$clients = $clientsStmt->fetchAll();

$priorityLabels = [1 => 'Critical', 2 => 'High', 3 => 'Medium', 4 => 'Low'];
$priorityPills  = [1 => 'pill-red', 2 => 'pill-gold', 3 => 'pill-blue', 4 => 'pill-green'];
$statusPills    = ['pending' => 'pill-gold', 'assigned' => 'pill-blue', 'in_progress' => 'pill-blue', 'done' => 'pill-green', 'failed' => 'pill-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Deliveries - MediRun</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&family=Unica+One&family=Anta&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
</head>
<body>
  <?php include('../components/nav.php'); ?>

  <div class="main-wrap">
    <header class="topbar">
      <span class="topbar-title">Deliveries</span>
      <div class="topbar-actions">
        <button class="btn btn-gold" id="newDeliveryBtn" type="button">+ New Delivery</button>
      </div>
    </header>

    <main class="content">
      <div class="page-header">
        <div>
          <h1>Deliveries</h1>
          <p><?php echo count($deliveries); ?> total deliveries</p>
        </div>
      </div>

      <div class="panel">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Client</th>
              <th>Deadline</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Weight (kg)</th>
              <th>Temp. Sensitive</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($deliveries)): ?>
              <?php foreach ($deliveries as $d): ?>
                <?php
                  $prio = (int)($d['priority'] ?? 3);
                  $status = $d['status'] ?? 'pending';
                  $pillPrio = $priorityPills[$prio] ?? 'pill-blue';
                  $pillStatus = $statusPills[$status] ?? 'pill-blue';
                  $prioLabel = $priorityLabels[$prio] ?? $prio;
                ?>
                <tr>
                  <td>#<?php echo htmlspecialchars($d['id']); ?></td>
                  <td><?php echo htmlspecialchars($d['client_id'] ?? '-'); ?></td>
                  <td><?php echo $d['deadline'] ? date('d/m/Y H:i', strtotime($d['deadline'])) : '-'; ?></td>
                  <td><span class="status-pill <?php echo $pillPrio; ?>"><?php echo htmlspecialchars($prioLabel); ?></span></td>
                  <td><span class="status-pill <?php echo $pillStatus; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?></span></td>
                  <td><?php echo htmlspecialchars($d['total_weight_kg'] ?? '0'); ?></td>
                  <td><?php echo !empty($d['temperature_sensitive']) ? '🌡 Yes' : 'No'; ?></td>
                  <td>
                    <button type="button" class="btn btn-ghost btn-sm"
                      onclick="viewDelivery(<?php echo (int)$d['id']; ?>)">View</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
                  No deliveries yet. Create one to get started.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- FAB -->
  <button class="fab" title="New Delivery" id="newDeliveryFab" type="button">
    <svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor">
      <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
    </svg>
  </button>

  <!-- Create Delivery Modal -->
  <div class="modal-overlay" data-modal="newDeliveryModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Create New Delivery</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content" data-modal-content>
        <form id="newDeliveryForm">
          <div class="form-group">
            <label class="form-label">Client *</label>
            <select name="client_id" class="form-select" required>
              <option value="">-- Choose a client --</option>
              <?php foreach ($clients as $client): ?>
                <option value="<?php echo htmlspecialchars($client['id']); ?>"><?php echo htmlspecialchars($client['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Deadline *</label>
            <input type="datetime-local" name="deadline" class="form-input" required>
          </div>

          <div class="form-group">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
              <option value="1">🔴 Critical</option>
              <option value="2">🟠 High</option>
              <option value="3" selected>🔵 Medium</option>
              <option value="4">🟢 Low</option>
            </select>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group">
              <label class="form-label">Weight (kg)</label>
              <input type="number" step="0.001" min="0" name="total_weight_kg" class="form-input" value="0">
            </div>
            <div class="form-group">
              <label class="form-label">Volume (m³)</label>
              <input type="number" step="0.0001" min="0" name="total_volume_m3" class="form-input" value="0">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-input" style="min-height:80px;resize:vertical;"></textarea>
          </div>

          <label class="checkbox-item">
            <input type="checkbox" name="temperature_sensitive" class="checkbox-input" value="1">
            <span class="checkbox-label">🌡 Temperature sensitive cargo</span>
          </label>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="btn btn-primary" id="submitNewDeliveryBtn">Create Delivery</button>
      </div>
    </div>
  </div>

  <!-- View Delivery Modal -->
  <div class="modal-overlay" data-modal="viewDeliveryModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Delivery Details</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content" data-modal-content id="viewDeliveryContent">
        <div style="text-align:center;padding:40px;color:var(--text-muted)">Loading…</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Close</button>
      </div>
    </div>
  </div>

  <script src="../js/components.js"></script>
  <script>
    const newDeliveryModal  = new Modal('newDeliveryModal');
    const viewDeliveryModal = new Modal('viewDeliveryModal');
    const API = '../../backend/api/index.php';

    const openNew = () => newDeliveryModal.open();
    document.getElementById('newDeliveryBtn').addEventListener('click', openNew);
    document.getElementById('newDeliveryFab').addEventListener('click', openNew);

    document.getElementById('submitNewDeliveryBtn').addEventListener('click', async () => {
      const form = document.getElementById('newDeliveryForm');
      const clientId = form.elements['client_id'].value;
      const deadline = form.elements['deadline'].value;

      if (!clientId) { Toast.error('Please select a client', 'Required'); return; }
      if (!deadline) { Toast.error('Please set a deadline', 'Required'); return; }

      const btn = document.getElementById('submitNewDeliveryBtn');
      btn.disabled = true;
      btn.textContent = 'Creating…';

      try {
        const res = await fetch(API + '?action=create_delivery', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            client_id:           parseInt(clientId, 10),
            deadline:            deadline.replace('T', ' ') + ':00',
            priority:            parseInt(form.elements['priority'].value, 10),
            total_weight_kg:     parseFloat(form.elements['total_weight_kg'].value || '0'),
            total_volume_m3:     parseFloat(form.elements['total_volume_m3'].value || '0'),
            temperature_sensitive: form.elements['temperature_sensitive'].checked ? 1 : 0,
            notes:               form.elements['notes'].value.trim(),
          })
        });
        const data = await res.json();
        if (res.ok) {
          Toast.success('Delivery #' + data.data.delivery_id + ' created!', 'Success');
          newDeliveryModal.close();
          setTimeout(() => location.reload(), 900);
        } else {
          Toast.error(data.error || 'Failed to create delivery', 'Error');
        }
      } catch (e) {
        Toast.error('Network error: ' + e.message, 'Error');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Create Delivery';
      }
    });

    async function viewDelivery(id) {
      viewDeliveryModal.open();
      const content = document.getElementById('viewDeliveryContent');
      content.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted)">Loading…</div>';
      try {
        const res = await fetch(API + '?action=list_deliveries');
        const data = await res.json();
        const d = (data.data || []).find(x => x.id == id);
        if (!d) { content.innerHTML = '<p>Delivery not found.</p>'; return; }
        const prios = {1:'🔴 Critical',2:'🟠 High',3:'🔵 Medium',4:'🟢 Low'};
        content.innerHTML = `
          <div style="display:grid;gap:12px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div><div class="form-label">Delivery ID</div><div>#${d.id}</div></div>
              <div><div class="form-label">Status</div><div>${d.status}</div></div>
              <div><div class="form-label">Client</div><div>${d.client_id}</div></div>
              <div><div class="form-label">Priority</div><div>${prios[d.priority] || d.priority}</div></div>
              <div><div class="form-label">Deadline</div><div>${d.deadline || '-'}</div></div>
              <div><div class="form-label">Weight</div><div>${d.total_weight_kg || 0} kg</div></div>
              <div><div class="form-label">Temp. Sensitive</div><div>${d.temperature_sensitive ? '🌡 Yes' : 'No'}</div></div>
              <div><div class="form-label">Created</div><div>${d.created_at || '-'}</div></div>
            </div>
            ${d.notes ? `<div><div class="form-label">Notes</div><div style="white-space:pre-wrap">${d.notes}</div></div>` : ''}
          </div>`;
      } catch(e) {
        content.innerHTML = '<p style="color:var(--red)">Failed to load details.</p>';
      }
    }
  </script>
</body>
</html>
