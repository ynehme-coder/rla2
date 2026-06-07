<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../../backend/db/Database.php';

$con = Database::getConnection();

$isDriverView = isset($_SESSION['role']) && $_SESSION['role'] === 'driver' && isset($_SESSION['driver_id']);
$driverFilterSql = $isDriverView ? ' AND da.driver_id = :driver_id' : '';
$driverFilterParams = $isDriverView ? [':driver_id' => (int)$_SESSION['driver_id']] : [];

$sql = "
    SELECT
        dg.id AS group_id,
        dg.name AS group_name,
    MAX(dg.created_at) AS created_at,
    MAX(d.id) AS driver_id,
    MAX(d.name) AS driver_name,
    MAX(v.plate) AS vehicle_plate,
    MAX(vt.name) AS vehicle_type,
        SUM(del.total_weight_kg) AS total_weight,
        MAX(CASE WHEN del.temperature_sensitive THEN 1 ELSE 0 END) AS needs_cooling,
        COUNT(DISTINCT del.id) AS total_orders,
        SUM(CASE WHEN del.status = 'done' THEN 1 ELSE 0 END) AS completed_orders,
        MAX(del.deadline) AS group_deadline,
        GROUP_CONCAT(DISTINCT del.status) AS statuses
    FROM delivery_groups dg
    LEFT JOIN delivery_group_items dgi ON dgi.group_id = dg.id
    LEFT JOIN deliveries del ON del.id = dgi.delivery_id
    LEFT JOIN delivery_assignments da ON da.delivery_id = del.id
    LEFT JOIN drivers d ON d.id = da.driver_id
    LEFT JOIN vehicles v ON v.id = da.vehicle_id
    LEFT JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
    WHERE del.status IN ('assigned', 'in_progress', 'done')
    {$driverFilterSql}
    GROUP BY dg.id, dg.name
    ORDER BY
        CASE
            WHEN NOW() > MAX(del.deadline) THEN 0
            WHEN TIMESTAMPDIFF(HOUR, NOW(), MAX(del.deadline)) < 2 THEN 1
            ELSE 2
        END,
        MAX(del.deadline) ASC
";

$stmt = $con->prepare($sql);
  $stmt->execute($driverFilterParams);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$overdue_count = 0;
$at_risk_count = 0;
$on_track_count = 0;

foreach ($groups as $group) {
  $deadlineTs = !empty($group['group_deadline']) ? strtotime($group['group_deadline']) : false;
  if ($deadlineTs !== false && $deadlineTs < time()) {
        $overdue_count++;
  } elseif ($deadlineTs !== false && $deadlineTs - time() < 7200) {
        $at_risk_count++;
    } else {
        $on_track_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tracking - MediRun</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&family=Unica+One&family=Anta&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
  <style>
    /* ── Tracking-specific overrides (all using project CSS vars) ── */

    .tracking-metrics {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }

    .track-card {
      background: var(--nuit);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      position: relative;
      overflow: hidden;
      transition: transform 0.2s, border-color 0.2s;
    }

    .track-card:hover {
      transform: translateY(-2px);
      border-color: rgba(255,247,227,0.14);
    }

    .track-card::before {
      content: '';
      position: absolute;
      top: 0; right: 0;
      width: 70px; height: 70px;
      border-radius: 50%;
      filter: blur(28px);
      opacity: 0.25;
    }

    .track-card.c-red::before  { background: var(--red); }
    .track-card.c-gold::before { background: var(--gold-mid); }
    .track-card.c-green::before{ background: var(--green); }

    .track-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }

    .track-card.c-red  .track-icon { background: rgba(224,82,82,0.15); }
    .track-card.c-gold .track-icon { background: rgba(205,177,120,0.15); }
    .track-card.c-green .track-icon { background: rgba(46,204,143,0.15); }

    .track-meta { flex: 1; }

    .track-label {
      font-size: 10px; font-weight: 600; letter-spacing: 0.1em;
      text-transform: uppercase; color: var(--text-muted);
      font-family: 'Unica One', sans-serif;
      margin-bottom: 4px;
    }

    .track-count {
      font-family: 'Orbitron', sans-serif;
      font-size: 32px; font-weight: 700; line-height: 1;
    }

    .track-card.c-red   .track-count { color: var(--red); }
    .track-card.c-gold  .track-count { color: var(--gold-mid); }
    .track-card.c-green .track-count { color: var(--green); }

    /* ── Group cards ── */

    .group-card {
      background: var(--nuit);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 16px;
      transition: border-color 0.2s;
    }

    .group-card:hover { border-color: rgba(255,247,227,0.14); }

    .group-card-header {
      padding: 18px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .group-card-header.status-overdue { border-left: 3px solid var(--red); }
    .group-card-header.status-at-risk  { border-left: 3px solid var(--orange); }
    .group-card-header.status-on-track { border-left: 3px solid var(--green); }

    .group-title-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
    }

    .group-id-badge {
      font-family: 'Anta', sans-serif;
      font-size: 12px;
      color: var(--horizon);
      background: rgba(61,106,193,0.15);
      padding: 3px 10px;
      border-radius: 20px;
    }

    .group-name {
      font-family: 'Unica One', sans-serif;
      font-size: 14px;
      color: var(--text-main);
    }

    .group-meta-grid {
      display: flex;
      gap: 28px;
      flex-wrap: wrap;
    }

    .meta-block .meta-label {
      font-size: 10px; font-weight: 600; letter-spacing: 0.1em;
      text-transform: uppercase; color: var(--text-muted);
      font-family: 'Unica One', sans-serif;
      margin-bottom: 2px;
    }

    .meta-block .meta-value {
      font-size: 13px; font-weight: 500; color: var(--text-main);
      font-family: 'Anta', sans-serif;
    }

    .cooling-pill {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 2px 8px;
      background: rgba(61,106,193,0.15);
      color: var(--horizon);
      border-radius: 20px;
      font-size: 11px; font-weight: 500;
    }

    .deadline-block {
      text-align: right;
      flex-shrink: 0;
    }

    .deadline-date {
      font-size: 11px; color: var(--text-muted); margin-bottom: 4px;
      font-family: 'Unica One', sans-serif;
      text-transform: uppercase; letter-spacing: 0.06em;
    }

    .countdown-pill {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 5px 12px;
      border-radius: 20px;
      font-family: 'Anta', sans-serif;
      font-size: 12px; font-weight: 600;
    }

    .countdown-pill.overdue { background: rgba(224,82,82,0.15); color: var(--red); }
    .countdown-pill.at-risk { background: rgba(232,148,58,0.15); color: var(--orange); }
    .countdown-pill.on-track { background: rgba(46,204,143,0.12); color: var(--green); }

    .countdown-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: currentColor;
      flex-shrink: 0;
    }

    /* Progress bar */
    .progress-row {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 20px;
      border-bottom: 1px solid var(--border);
    }

    .progress-label {
      font-size: 11px; color: var(--text-muted);
      font-family: 'Unica One', sans-serif;
      text-transform: uppercase; letter-spacing: 0.08em;
      min-width: 90px;
    }

    .progress-track {
      flex: 1; height: 4px;
      background: rgba(255,247,227,0.08);
      border-radius: 2px; overflow: hidden;
    }

    .progress-fill {
      height: 100%; border-radius: 2px;
      background: linear-gradient(90deg, var(--marine), var(--horizon));
      transition: width 0.3s ease;
    }

    .progress-fill.done { background: linear-gradient(90deg, #1a9c6e, var(--green)); }
    .progress-fill.warn { background: linear-gradient(90deg, var(--orange), #e8a83a); }
    .progress-fill.danger { background: linear-gradient(90deg, #c0392b, var(--red)); }

    .progress-pct {
      font-size: 11px; color: var(--text-muted);
      font-family: 'Anta', sans-serif;
      min-width: 34px; text-align: right;
    }

    /* Child table using existing .data-table */
    .data-table .prio-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 3px 8px; border-radius: 4px;
      font-size: 10px; font-weight: 700;
      letter-spacing: 0.08em; text-transform: uppercase;
    }

    .prio-1 { background: rgba(224,82,82,0.15);   color: var(--red); }
    .prio-2 { background: rgba(232,148,58,0.15);  color: var(--orange); }
    .prio-3 { background: rgba(61,106,193,0.15);  color: var(--horizon); }
    .prio-4 { background: rgba(255,247,227,0.07); color: var(--text-muted); }
    .prio-5 { background: rgba(255,247,227,0.05); color: var(--text-muted); }

    .child-countdown {
      font-family: 'Anta', sans-serif;
      font-size: 12px;
    }

    .child-countdown.overdue { color: var(--red); }
    .child-countdown.at-risk  { color: var(--orange); }
    .child-countdown.on-track { color: var(--green); }

    /* Empty state */
    .empty-track {
      padding: 60px 20px;
      text-align: center;
      color: var(--text-muted);
    }

    .empty-track .empty-icon {
      font-size: 40px; margin-bottom: 12px;
    }

    .empty-track p {
      font-family: 'Unica One', sans-serif;
      font-size: 15px; letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    @media (max-width: 820px) {
      .tracking-metrics { grid-template-columns: 1fr; }
      .group-meta-grid  { gap: 16px; }
      .deadline-block   { text-align: left; }
    }
  </style>
</head>

<body>
  <?php include('../components/nav.php'); ?>

  <div class="main-wrap">
    <main class="content">

      <div class="page-header">
        <div>
          <h1>Tracking</h1>
          <p><?php echo date('l, d F Y'); ?></p>
        </div>
      </div>

      <!-- Status metric cards -->
      <div class="tracking-metrics">
        <div class="track-card c-red">
          <div class="track-icon">🔴</div>
          <div class="track-meta">
            <div class="track-label">Overdue</div>
            <div class="track-count"><?= $overdue_count ?></div>
          </div>
        </div>
        <div class="track-card c-gold">
          <div class="track-icon">🟡</div>
          <div class="track-meta">
            <div class="track-label">At Risk (&lt;2h)</div>
            <div class="track-count"><?= $at_risk_count ?></div>
          </div>
        </div>
        <div class="track-card c-green">
          <div class="track-icon">🟢</div>
          <div class="track-meta">
            <div class="track-label">On Track</div>
            <div class="track-count"><?= $on_track_count ?></div>
          </div>
        </div>
      </div>

      <!-- Group cards -->
      <?php if (!empty($groups)): ?>

        <?php foreach ($groups as $group):
          $deadline   = strtotime($group['group_deadline']);
          $now        = time();
          $diff       = $deadline - $now;
          $abs_h      = floor(abs($diff) / 3600);
          $abs_m      = floor((abs($diff) % 3600) / 60);
          $total      = (int)$group['total_orders'];
          $done       = (int)$group['completed_orders'];
          $pct        = $total > 0 ? round($done / $total * 100) : 0;

          if ($diff < 0) {
            $status_cls  = 'overdue';
            $cdot_cls    = 'overdue';
            $cd_text     = "OVERDUE {$abs_h}h {$abs_m}m";
            $bar_cls     = 'danger';
          } elseif ($diff < 7200) {
            $status_cls  = 'at-risk';
            $cdot_cls    = 'at-risk';
            $cd_text     = "{$abs_h}h {$abs_m}m left";
            $bar_cls     = 'warn';
          } else {
            $status_cls  = 'on-track';
            $cdot_cls    = 'on-track';
            $cd_text     = "{$abs_h}h {$abs_m}m left";
            $bar_cls     = $pct >= 100 ? 'done' : '';
          }
        ?>

        <div class="group-card">

          <!-- Header -->
          <div class="group-card-header status-<?= $status_cls ?>">
            <div>
              <div class="group-title-row">
                <span class="group-id-badge">#<?= htmlspecialchars($group['group_id']) ?></span>
                <span class="group-name"><?= htmlspecialchars($group['group_name']) ?></span>
              </div>
              <div class="group-meta-grid">
                <div class="meta-block">
                  <div class="meta-label">Driver</div>
                  <div class="meta-value"><?= htmlspecialchars($group['driver_name'] ?? '—') ?></div>
                </div>
                <div class="meta-block">
                  <div class="meta-label">Vehicle</div>
                  <div class="meta-value"><?= htmlspecialchars($group['vehicle_plate'] ?? '—') ?>
                    <?php if ($group['vehicle_type']): ?>
                      <span style="color:var(--text-muted);font-size:11px;">(<?= htmlspecialchars($group['vehicle_type']) ?>)</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="meta-block">
                  <div class="meta-label">Weight</div>
                  <div class="meta-value"><?= round($group['total_weight'] ?? 0, 1) ?> kg</div>
                </div>
                <div class="meta-block">
                  <div class="meta-label">Orders</div>
                  <div class="meta-value"><?= $done ?> / <?= $total ?></div>
                </div>
                <?php if ($group['needs_cooling']): ?>
                <div class="meta-block" style="align-self:flex-end;">
                  <span class="cooling-pill">❄️ Cooling</span>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="deadline-block">
              <div class="deadline-date">Deadline</div>
              <div style="font-family:'Anta',sans-serif;font-size:13px;color:var(--text-main);margin-bottom:6px;">
                <?= date('M d, H:i', $deadline) ?>
              </div>
              <span class="countdown-pill <?= $cdot_cls ?>">
                <span class="countdown-dot"></span>
                <?= htmlspecialchars($cd_text) ?>
              </span>
            </div>
          </div>

          <!-- Progress bar -->
          <div class="progress-row">
            <span class="progress-label">Progress</span>
            <div class="progress-track">
              <div class="progress-fill <?= $bar_cls ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="progress-pct"><?= $pct ?>%</span>
          </div>

          <!-- Child deliveries -->
          <?php
            $sql_c = "
              SELECT d.id AS order_id, d.priority, d.status, d.deadline
              FROM delivery_group_items dgi
              JOIN deliveries d ON d.id = dgi.delivery_id
              WHERE dgi.group_id = :gid
              " . ($isDriverView ? "AND EXISTS (SELECT 1 FROM delivery_assignments da WHERE da.delivery_id = d.id AND da.driver_id = :driver_id)" : "") . "
              ORDER BY d.priority ASC, d.deadline ASC
            ";
            $st = $con->prepare($sql_c);
            $childParams = [':gid' => $group['group_id']];
            if ($isDriverView) {
              $childParams[':driver_id'] = (int)$_SESSION['driver_id'];
            }
            $st->execute($childParams);
            $children = $st->fetchAll(PDO::FETCH_ASSOC);

            $prio_labels = ['', 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'VERY LOW'];
          ?>

          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Deadline</th>
                <th>Countdown</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($children as $c):
                $cd  = strtotime($c['deadline']);
                $df  = $cd - $now;
                $ch  = floor(abs($df) / 3600);
                $cm  = floor((abs($df) % 3600) / 60);
                $p   = (int)$c['priority'];

                if ($df < 0) {
                  $cc  = 'overdue';
                  $ct  = "OVERDUE {$ch}h {$cm}m";
                } elseif ($df < 3600) {
                  $cc  = 'at-risk';
                  $ct  = "{$ch}h {$cm}m";
                } else {
                  $cc  = 'on-track';
                  $ct  = "{$ch}h {$cm}m";
                }

                $pill_cls = match($c['status']) {
                  'assigned'    => 'pill-blue',
                  'in_progress' => 'pill-orange',
                  'done'        => 'pill-green',
                  default       => 'pill-gray'
                };
              ?>
              <tr>
                <td style="font-family:'Anta',sans-serif;color:var(--horizon);">#<?= htmlspecialchars($c['order_id']) ?></td>
                <td>
                  <span class="prio-badge prio-<?= $p ?>">
                    <?= htmlspecialchars($prio_labels[$p] ?? 'UNKNOWN') ?>
                  </span>
                </td>
                <td>
                  <span class="status-pill <?= $pill_cls ?>">
                    <span class="pill-dot"></span>
                    <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                  </span>
                </td>
                <td style="font-size:12px;color:var(--text-muted);"><?= date('M d, H:i', $cd) ?></td>
                <td>
                  <span class="child-countdown <?= $cc ?>">
                    <?= htmlspecialchars($ct) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

        </div>

        <?php endforeach; ?>

      <?php else: ?>
        <div class="panel">
          <div class="empty-track">
            <div class="empty-icon">📭</div>
            <p>No active deliveries to track</p>
          </div>
        </div>
      <?php endif; ?>

    </main>
  </div>
</body>
</html>