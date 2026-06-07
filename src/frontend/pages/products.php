<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') { header('Location: tracking.php'); exit(); }
require_once __DIR__ . '/../../backend/db/ProductsRepository.php';
$productsRepo = new ProductsRepository();
$products = $productsRepo->getAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Products - MediRun</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&family=Unica+One&family=Anta&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
</head>
<body>
  <?php include('../components/nav.php'); ?>

  <div class="main-wrap">
    <header class="topbar">
      <span class="topbar-title">Products</span>
      <div class="topbar-actions">
        <button class="btn btn-gold" id="addProductBtn" type="button">+ Add Product</button>
      </div>
    </header>

    <main class="content">
      <div class="page-header">
        <div>
          <h1>Products</h1>
          <p><?php echo count($products); ?> products in catalogue</p>
        </div>
      </div>

      <div class="panel">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>SKU</th>
              <th>Weight (kg)</th>
              <th>Volume (m³)</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($products)): ?>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><?php echo htmlspecialchars($p['id'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($p['name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($p['sku'] ?? '—'); ?></td>
                  <td><?php echo htmlspecialchars($p['weight_kg'] ?? '0'); ?></td>
                  <td><?php echo htmlspecialchars($p['volume_m3'] ?? '0'); ?></td>
                  <td>
                    <button type="button" class="btn btn-ghost btn-sm"
                      onclick="viewProduct(<?php echo htmlspecialchars(json_encode($p)); ?>)">View</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">No products found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- Add Product Modal -->
  <div class="modal-overlay" data-modal="addProductModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Add New Product</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content">
        <form id="addProductForm">
          <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" name="name" class="form-input" placeholder="e.g. Insulin Pens" required>
          </div>
          <div class="form-group">
            <label class="form-label">SKU</label>
            <input type="text" name="sku" class="form-input" placeholder="e.g. MED-INS-001">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group">
              <label class="form-label">Weight (kg)</label>
              <input type="number" step="0.001" min="0" name="weight_kg" class="form-input" value="0">
            </div>
            <div class="form-group">
              <label class="form-label">Volume (m³)</label>
              <input type="number" step="0.0001" min="0" name="volume_m3" class="form-input" value="0">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="btn btn-primary" id="submitAddProductBtn">Add Product</button>
      </div>
    </div>
  </div>

  <!-- View Product Modal -->
  <div class="modal-overlay" data-modal="viewProductModal" hidden>
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Product Details</div>
        <button type="button" class="modal-close" data-modal-close>×</button>
      </div>
      <div class="modal-content" id="viewProductContent"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Close</button>
      </div>
    </div>
  </div>

  <script src="../js/components.js"></script>
  <script>
    const addProductModal  = new Modal('addProductModal');
    const viewProductModal = new Modal('viewProductModal');
    const API = '../../backend/api/index.php';

    document.getElementById('addProductBtn').addEventListener('click', () => addProductModal.open());

    document.getElementById('submitAddProductBtn').addEventListener('click', async () => {
      const form = document.getElementById('addProductForm');
      const name = form.elements['name'].value.trim();
      if (!name) { Toast.error('Product name is required', 'Required'); return; }

      const btn = document.getElementById('submitAddProductBtn');
      btn.disabled = true; btn.textContent = 'Adding…';

      try {
        const res = await fetch(API + '?action=create_product', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            name,
            sku:       form.elements['sku'].value.trim(),
            weight_kg: parseFloat(form.elements['weight_kg'].value || '0'),
            volume_m3: parseFloat(form.elements['volume_m3'].value || '0'),
          })
        });
        const data = await res.json();
        if (res.ok) {
          Toast.success(name + ' added!', 'Product Added');
          addProductModal.close();
          setTimeout(() => location.reload(), 900);
        } else {
          Toast.error(data.error || 'Failed to add product', 'Error');
        }
      } catch(e) {
        Toast.error('Network error: ' + e.message, 'Error');
      } finally {
        btn.disabled = false; btn.textContent = 'Add Product';
      }
    });

    function viewProduct(p) {
      document.getElementById('viewProductContent').innerHTML = `
        <div style="display:grid;gap:12px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><div class="form-label">Product ID</div><div>#${p.id}</div></div>
            <div><div class="form-label">Name</div><div>${p.name}</div></div>
            <div><div class="form-label">SKU</div><div>${p.sku || '—'}</div></div>
            <div><div class="form-label">Weight</div><div>${p.weight_kg || 0} kg</div></div>
            <div><div class="form-label">Volume</div><div>${p.volume_m3 || 0} m³</div></div>
          </div>
        </div>`;
      viewProductModal.open();
    }
  </script>
</body>
</html>
