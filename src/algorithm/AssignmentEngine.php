<?php
require_once __DIR__ . '/../backend/db/Database.php';

class AssignmentEngine {

    // ─── Constants ───────────────────────────────────────────
    const MAX_SHELF_LIFE             = 24;
    const GAP_CRITICAL               = 2;
    const GAP_HIGH                   = 4;
    const GAP_LOW                    = 6;
    const SCORE_CRITICAL_THRESHOLD   = 8;
    const SCORE_HIGH_THRESHOLD       = 5;
    const MAX_DRIVER_HOURS           = 10;
    const SOFT_DRIVER_HOURS          = 8;

    private ?PDO $con;
    private array $flags = []; // manager alerts collected during a run

    public function __construct()
    {
        $this->con = Database::getConnection();
    }


    // =========================================================
    // PUBLIC ENTRY POINT
    // =========================================================

    /**
     * Run the full assignment engine.
     * Returns an array of assigned groups, each with driver, vehicle, and orders.
     * Also populates $this->flags with any manager alerts.
     */
    public function run(): array
    {
        $this->flags = [];

        // Load raw data from DB
        $orders  = $this->fetchPendingOrders();
        $drivers = $this->fetchDrivers();
        $vehicles = $this->fetchVehicles();

        if (empty($orders)) {
            return [];
        }

        // Stage 1 + 2: score and label every order
        $orders = $this->labelOrders($orders);

        // Stage 3: group orders
        $groups = $this->groupOrders($orders);

        // Stage 4: assign driver + vehicle to each group
        $results = $this->assignGroups($groups, $drivers, $vehicles);

        return $results;
    }

    /**
     * Re-evaluate existing groups (called hourly or when a new order arrives).
     * Pulls orders that no longer fit, re-groups them, flags manager if needed.
     */
    public function reEvaluate(array $newOrders = []): array
    {
        $this->flags = [];

        $existingGroups = $this->fetchActiveGroups();
        $pulled         = [];

        foreach ($existingGroups as &$group) {
            if (empty($group['orders'])) continue;

            // Re-score all orders in the group
            foreach ($group['orders'] as &$order) {
                $order['score'] = $this->calcFinalScore($order);
            }
            unset($order);

            // Sort so anchor is still highest score
            usort($group['orders'], fn($a, $b) => $b['score'] <=> $a['score']);
            $anchor = $group['orders'][0];

            // Calculate quartiles from this group's orders for dynamic gap
            $groupScores = array_column($group['orders'], 'score');
            sort($groupScores);
            $q1 = $this->percentile($groupScores, 25);
            $q2 = $this->percentile($groupScores, 50);
            $q3 = $this->percentile($groupScores, 75);

            $maxGap = $this->getMaxGap($anchor['score'], $q1, $q2, $q3);

            $kept = [$anchor];
            for ($i = 1; $i < count($group['orders']); $i++) {
                $order = $group['orders'][$i];
                if (abs($order['score'] - $anchor['score']) > $maxGap) {
                    $pulled[] = $order;
                    // Flag manager if this group already has a vehicle assigned
                    if (!empty($group['vehicle_id'])) {
                        $this->flags[] = [
                            'type'       => 'reroute_needed',
                            'message'    => "Order #{$order['id']} pulled from group. Vehicle {$group['vehicle_id']} may need rerouting.",
                            'order_id'   => $order['id'],
                            'vehicle_id' => $group['vehicle_id']
                        ];
                    }
                } else {
                    $kept[] = $order;
                }
            }
            $group['orders'] = $kept;
        }
        unset($group);

        // Re-score and merge new orders with pulled ones
        $repool = array_merge($pulled, $newOrders);
        if (!empty($repool)) {
            $repool     = $this->labelOrders($repool);
            $newGroups  = $this->groupOrders($repool);
            $drivers    = $this->fetchDrivers();
            $vehicles   = $this->fetchVehicles();
            $assigned   = $this->assignGroups($newGroups, $drivers, $vehicles);
            return array_merge($existingGroups, $assigned);
        }

        return $existingGroups;
    }

    public function getFlags(): array
    {
        return $this->flags;
    }


    // =========================================================
    // STAGE 1 + 2 — SCORING AND LABELLING
    // =========================================================

    private function calcExpiryScore(float $hoursRemaining, float $travelTime): float
    {
        $x = $hoursRemaining - $travelTime;
        if ($x <= 0)                      return 10.0;
        if ($x >= self::MAX_SHELF_LIFE)   return 1.0;
        return 10 * pow(1 - $x / self::MAX_SHELF_LIFE, 2.5) + 1;
    }

    private function calcDeadlineScore(float $hoursUntilDeadline, float $totalWindow, float $travelTime): float
    {
        $x = $hoursUntilDeadline - $travelTime;
        if ($x <= 0)             return 10.0;
        if ($x >= $totalWindow)  return 1.0;
        return 10 * pow(1 - $x / $totalWindow, 2.5) + 1;
    }

    private function calcFinalScore(array $order): float
    {
        $travelTime         = (float)($order['travel_time_hours'] ?? 0);
        $hoursRemaining     = (float)($order['hours_remaining']   ?? 24);
        $hoursUntilDeadline = (float)($order['hours_until_deadline'] ?? 24);
        $totalWindow        = (float)($order['total_window_hours'] ?? 24);
        $urgencyMultiplier  = (float)($order['urgency_multiplier'] ?? 1.0);

        $expiryScore   = $this->calcExpiryScore($hoursRemaining, $travelTime);
        $deadlineScore = $this->calcDeadlineScore($hoursUntilDeadline, $totalWindow, $travelTime);

        $raw = max($expiryScore, $deadlineScore);
        return $raw * $urgencyMultiplier;
    }

    private function labelOrders(array $orders): array
    {
        // Calculate score for every order
        foreach ($orders as &$order) {
            $order['score'] = $this->calcFinalScore($order);
        }
        unset($order);

        // Get all scores to find quartiles
        $scores = array_column($orders, 'score');
        sort($scores);

        $q1 = $this->percentile($scores, 25);
        $q2 = $this->percentile($scores, 50);
        $q3 = $this->percentile($scores, 75);

        foreach ($orders as &$order) {
            $s = $order['score'];
            if ($s > $q3)      $order['label'] = 'critical'; // 🔴
            elseif ($s > $q2)  $order['label'] = 'high';     // 🟠
            elseif ($s > $q1)  $order['label'] = 'medium';   // 🟡
            else               $order['label'] = 'low';      // 🟢
        }
        unset($order);

        return $orders;
    }

    private function percentile(array $sorted, float $p): float
    {
        $count = count($sorted);
        if ($count === 0) return 0;
        $index = ($p / 100) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        if ($lower === $upper) return $sorted[$lower];
        $fraction = $index - $lower;
        return $sorted[$lower] + $fraction * ($sorted[$upper] - $sorted[$lower]);
    }


    // =========================================================
    // STAGE 3 — GROUPING
    // =========================================================

    private function getZone(float $travelTimeMinutes): int
    {
        if ($travelTimeMinutes <= 15) return 1;
        if ($travelTimeMinutes <= 30) return 2;
        if ($travelTimeMinutes <= 60) return 3;
        return 4;
    }

    private function getMaxGap(float $anchorScore, float $q1, float $q2, float $q3): float
    {
        // Dynamic gap based on percentiles
        if ($anchorScore >= $q3) {
            return 0.25 * ($q3 - $q1);  // Top 25%: very tight gap
        }
        if ($anchorScore >= $q2) {
            return 0.5 * ($q3 - $q1);   // Top 50%: moderate gap
        }
        if ($anchorScore >= $q1) {
            return 0.75 * ($q3 - $q1);  // Top 75%: relaxed gap
        }
        return $q3 - $q1;               // Bottom 25%: very relaxed gap (full range)
    }

    private function groupOrders(array $orders): array
    {
        // Sort by score descending — highest score becomes anchor
        usort($orders, fn($a, $b) => $b['score'] <=> $a['score']);

        // Calculate quartiles for dynamic gap calculation
        $scores = array_column($orders, 'score');
        sort($scores);
        $q1 = $this->percentile($scores, 25);
        $q2 = $this->percentile($scores, 50);
        $q3 = $this->percentile($scores, 75);

        $groups    = [];
        $assigned  = []; // track order IDs already grouped

        foreach ($orders as $anchor) {
            if (in_array($anchor['id'], $assigned)) continue;

            $anchorZone  = $this->getZone((float)($anchor['travel_time_minutes'] ?? 0));
            $maxGap      = $this->getMaxGap($anchor['score'], $q1, $q2, $q3);
            $group       = [$anchor];
            $groupWeight = (float)($anchor['total_weight_kg'] ?? 0);
            $assigned[]  = $anchor['id'];

            foreach ($orders as $candidate) {
                if (in_array($candidate['id'], $assigned)) continue;

                $candidateZone = $this->getZone((float)($candidate['travel_time_minutes'] ?? 0));
                $scoreDiff     = abs($candidate['score'] - $anchor['score']);
                $zoneDiff      = abs($candidateZone - $anchorZone);
                $newWeight     = $groupWeight + (float)($candidate['total_weight_kg'] ?? 0);

                // Zone within ±1, score within gap, weight TBD at vehicle selection
                if ($scoreDiff <= $maxGap && $zoneDiff <= 1) {
                    $group[]     = $candidate;
                    $groupWeight = $newWeight;
                    $assigned[]  = $candidate['id'];
                }
            }

            // Sort group: highest score delivered first
            usort($group, fn($a, $b) => $b['score'] <=> $a['score']);

            $groups[] = [
                'orders'       => $group,
                'total_weight' => $groupWeight,
                'needs_cooling'=> $this->groupNeedsCooling($group),
                'max_zone'     => $this->groupMaxZone($group),
                'driver_id'    => null,
                'vehicle_id'   => null,
                'status'       => 'pending'
            ];
        }

        return $groups;
    }

    private function groupNeedsCooling(array $orders): bool
    {
        foreach ($orders as $order) {
            if (!empty($order['temperature_sensitive'])) return true;
        }
        return false;
    }

    private function groupMaxZone(array $orders): int
    {
        $max = 1;
        foreach ($orders as $order) {
            $zone = $this->getZone((float)($order['travel_time_minutes'] ?? 0));
            if ($zone > $max) $max = $zone;
        }
        return $max;
    }


    // =========================================================
    // STAGE 4 — DRIVER AND VEHICLE SELECTION
    // =========================================================

    private function selectDriver(array &$drivers, array $group): ?array
    {
        // Calculate group hours: 1 hour per delivery in group
        $groupHours = count($group['orders']) * 1.0;

        // 1. Free drivers: under 8hrs, not currently on a delivery
        foreach ($drivers as &$d) {
            if ($d['hours_worked'] < self::SOFT_DRIVER_HOURS && !$d['on_delivery']) {
                // Check if driver + this group would exceed MAX_DRIVER_HOURS
                if ($d['hours_worked'] + $groupHours <= self::MAX_DRIVER_HOURS) {
                    $d['hours_worked'] += $groupHours; // Track queued hours
                    $d['on_delivery'] = true;
                    return $d;
                }
            }
        }
        unset($d);

        // 2. Occupied but under 8hrs (can be queued)
        foreach ($drivers as &$d) {
            if ($d['hours_worked'] < self::SOFT_DRIVER_HOURS && $d['on_delivery']) {
                // Check if driver + this group would exceed MAX_DRIVER_HOURS
                if ($d['hours_worked'] + $groupHours <= self::MAX_DRIVER_HOURS) {
                    $d['hours_worked'] += $groupHours; // Track queued hours
                    return $d;
                }
            }
        }
        unset($d);

        // 3. Overtime drivers 8–10hrs, pick least overtime first
        $overtime = [];
        foreach ($drivers as $d) {
            if ($d['hours_worked'] >= self::SOFT_DRIVER_HOURS &&
                $d['hours_worked'] <= self::MAX_DRIVER_HOURS) {
                // Check if driver + this group would exceed MAX_DRIVER_HOURS
                if ($d['hours_worked'] + $groupHours <= self::MAX_DRIVER_HOURS) {
                    $overtime[] = $d;
                }
            }
        }
        if (!empty($overtime)) {
            usort($overtime, fn($a, $b) => $a['hours_worked'] <=> $b['hours_worked']);
            // Mark as on delivery in the original array
            foreach ($drivers as &$d) {
                if ($d['id'] === $overtime[0]['id']) {
                    $d['on_delivery'] = true;
                    $d['hours_worked'] += $groupHours; // Track queued hours
                    break;
                }
            }
            unset($d);
            return $overtime[0];
        }

        // No driver available
        return null;
    }

    private function selectVehicle(array &$vehicles, array $group): ?array
    {
        $eligible = [];

        foreach ($vehicles as $v) {
            if ($v['status'] !== 'available')                   continue;
            if ($v['capacity_kg'] < $group['total_weight'])     continue;
            if ($group['needs_cooling'] && !$v['refrigerated']) continue;
            $eligible[] = $v;
        }

        if (empty($eligible)) return null;

        // Prefer smallest suitable vehicle (save fuel, easier in city)
        usort($eligible, fn($a, $b) => $a['capacity_kg'] <=> $b['capacity_kg']);
        $chosen = $eligible[0];

        // NOTE: Vehicle status stays 'available' for reuse by other groups (FIFO queuing)
        // No marking as 'enroute' — vehicle can serve multiple groups

        return $chosen;
    }

    private function assignGroups(array $groups, array $drivers, array $vehicles): array
    {
        $results = [];

        foreach ($groups as $group) {
            $driver  = $this->selectDriver($drivers, $group);
            $vehicle = $this->selectVehicle($vehicles, $group);

            if ($driver === null) {
                $this->flags[] = [
                    'type'    => 'no_driver',
                    'message' => 'No driver available for a group. Manual assignment needed.',
                    'group'   => $group
                ];
                continue;
            }

            if ($vehicle === null) {
                $this->flags[] = [
                    'type'    => 'no_vehicle',
                    'message' => 'No suitable vehicle for a group. Manual assignment needed.',
                    'group'   => $group
                ];
                continue;
            }

            $group['driver_id']  = $driver['id'];
            $group['vehicle_id'] = $vehicle['id'];
            $group['status']     = 'assigned';

            // Persist assignments to DB
            $this->saveAssignments($group, $driver, $vehicle);

            $results[] = $group;
        }

        return $results;
    }


    // =========================================================
    // DATABASE — READ
    // =========================================================

    private function fetchPendingOrders(): array
    {
        // Fetch pending deliveries with computed time fields
        $sql = "
            SELECT
                d.id,
                d.client_id,
                d.pickup_time,
                d.deadline,
                d.priority,
                d.status,
                d.temperature_sensitive,
                d.total_weight_kg,
                d.total_volume_m3,
                d.notes,
                -- hours from now until deadline
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), d.deadline) / 3600.0) AS hours_until_deadline,
                -- total window: from created_at to deadline
                GREATEST(1, TIMESTAMPDIFF(SECOND, d.created_at, d.deadline) / 3600.0) AS total_window_hours,
                -- hours until product expires (shelf_life from pickup)
                COALESCE(
                    GREATEST(0, (MIN(p.shelf_life_hours) - TIMESTAMPDIFF(SECOND, d.pickup_time, NOW()) / 3600.0)),
                    24
                ) AS hours_remaining,
                -- urgency multiplier: priority 1 = 1.5x, 2 = 1.2x, 3 = 1.0x, 4 = 0.9x
                CASE d.priority
                    WHEN 1 THEN 1.5
                    WHEN 2 THEN 1.2
                    WHEN 4 THEN 0.9
                    ELSE 1.0
                END AS urgency_multiplier,
                -- travel time in minutes (placeholder: use 20 min default until routing API available)
                20.0 AS travel_time_minutes,
                -- travel time in hours
                (20.0 / 60.0) AS travel_time_hours
            FROM deliveries d
            LEFT JOIN delivery_items di ON di.delivery_id = d.id
            LEFT JOIN products p        ON p.id = di.product_id
            WHERE d.status = 'pending'
            GROUP BY d.id
            ORDER BY d.priority, d.deadline
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchDrivers(): array
    {
        // Load drivers with hours worked today from their shifts
        $sql = "
            SELECT
                d.id,
                d.name,
                d.phone,
                d.base_vehicle_id,
                COALESCE(
                    SUM(
                        CASE
                            WHEN ds.shift_date = CURDATE()
                            THEN TIME_TO_SEC(TIMEDIFF(ds.end_time, ds.start_time)) / 3600.0
                            ELSE 0
                        END
                    ), 0
                ) AS hours_worked,
                -- check if currently on a delivery
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM delivery_assignments da
                        WHERE da.driver_id = d.id
                        AND da.assignment_status IN ('assigned','started')
                    ) THEN 1
                    ELSE 0
                END AS on_delivery
            FROM drivers d
            LEFT JOIN driver_shifts ds ON ds.driver_id = d.id
            GROUP BY d.id
            HAVING hours_worked <= :max_hours
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->execute([':max_hours' => self::MAX_DRIVER_HOURS]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cast types
        foreach ($rows as &$r) {
            $r['hours_worked'] = (float) $r['hours_worked'];
            $r['on_delivery']  = (bool)  $r['on_delivery'];
        }
        unset($r);

        return $rows;
    }

    private function fetchVehicles(): array
    {
        $sql = "
            SELECT
                v.id,
                v.plate,
                v.refrigerated,
                v.status,
                vt.capacity_kg,
                vt.capacity_m3,
                vt.max_range_km
            FROM vehicles v
            JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
            WHERE v.status = 'available'
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['refrigerated'] = (bool) $r['refrigerated'];
            $r['capacity_kg']  = (float) $r['capacity_kg'];
        }
        unset($r);

        return $rows;
    }

    private function fetchActiveGroups(): array
    {
        // Load delivery_groups that are still in progress
        $sql = "
            SELECT
                dg.id   AS group_id,
                dg.name,
                da.driver_id,
                da.vehicle_id
            FROM delivery_groups dg
            LEFT JOIN delivery_group_items dgi ON dgi.group_id = dg.id
            LEFT JOIN delivery_assignments da  ON da.delivery_id = dgi.delivery_id
                AND da.assignment_status IN ('assigned','started')
            GROUP BY dg.id
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $groupRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each group, load its orders
        $groups = [];
        foreach ($groupRows as $row) {
            $ordersql = "
                SELECT d.* FROM deliveries d
                JOIN delivery_group_items dgi ON dgi.delivery_id = d.id
                WHERE dgi.group_id = :gid
            ";
            $ostmt = $this->con->prepare($ordersql);
            $ostmt->execute([':gid' => $row['group_id']]);
            $orders = $ostmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute time fields for re-scoring
            foreach ($orders as &$o) {
                $o['hours_until_deadline'] = max(0, (strtotime($o['deadline']) - time()) / 3600);
                $o['total_window_hours']   = max(1, (strtotime($o['deadline']) - strtotime($o['created_at'])) / 3600);
                $o['hours_remaining']      = 24; // simplified — product shelf life calc omitted here
                $o['travel_time_hours']    = 20.0 / 60.0;
                $o['travel_time_minutes']  = 20.0;
                $o['urgency_multiplier']   = match((int)$o['priority']) {
                    1 => 1.5, 2 => 1.2, 4 => 0.9, default => 1.0
                };
            }
            unset($o);

            $groups[] = [
                'group_id'     => $row['group_id'],
                'orders'       => $orders,
                'driver_id'    => $row['driver_id'],
                'vehicle_id'   => $row['vehicle_id'],
                'total_weight' => array_sum(array_column($orders, 'total_weight_kg')),
                'needs_cooling'=> $this->groupNeedsCooling($orders),
                'max_zone'     => $this->groupMaxZone($orders),
                'status'       => 'in_progress'
            ];
        }

        return $groups;
    }


    // =========================================================
    // DATABASE — WRITE
    // =========================================================

    private function saveAssignments(array $group, array $driver, array $vehicle): void
    {
        // 1. Create a delivery_group record
        $sql  = "INSERT INTO delivery_groups (name, created_at) VALUES (:name, NOW())";
        $stmt = $this->con->prepare($sql);
        $stmt->execute([':name' => 'Auto-group ' . date('Y-m-d H:i:s')]);
        $groupId = (int) $this->con->lastInsertId();

        // Get queue position: count existing assignments for this driver + 1
        $sqlQueuePos = "
            SELECT COUNT(*) AS queue_position
            FROM delivery_assignments
            WHERE driver_id = :driver_id
        ";
        $stmtQueuePos = $this->con->prepare($sqlQueuePos);
        $stmtQueuePos->execute([':driver_id' => $driver['id']]);
        $queueResult = $stmtQueuePos->fetch(PDO::FETCH_ASSOC);
        $queuePosition = (int)($queueResult['queue_position'] ?? 0) + 1;

        foreach ($group['orders'] as $order) {
            // 2. Link each order to the group
            $sql  = "INSERT INTO delivery_group_items (group_id, delivery_id) VALUES (:gid, :did)";
            $stmt = $this->con->prepare($sql);
            $stmt->execute([':gid' => $groupId, ':did' => $order['id']]);

            // 3. Create delivery_assignment for each order with queue_position
            $sql  = "
                INSERT INTO delivery_assignments
                    (delivery_id, driver_id, vehicle_id, assigned_at, scheduled_start, assignment_status, queue_position)
                VALUES
                    (:delivery_id, :driver_id, :vehicle_id, NOW(), NOW(), 'assigned', :queue_position)
            ";
            $stmt = $this->con->prepare($sql);
            $stmt->execute([
                ':delivery_id'   => $order['id'],
                ':driver_id'     => $driver['id'],
                ':vehicle_id'    => $vehicle['id'],
                ':queue_position' => $queuePosition
            ]);

            // 4. Update delivery status
            $sql  = "UPDATE deliveries SET status = 'assigned' WHERE id = :id";
            $stmt = $this->con->prepare($sql);
            $stmt->execute([':id' => $order['id']]);
        }

        // NOTE: Vehicle status stays 'available' (no longer marked 'enroute')
        // This allows the same vehicle to serve multiple driver queues
    }
}