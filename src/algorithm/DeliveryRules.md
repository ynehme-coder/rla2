Factor 1 — Priority Score (1–4)
ScoreColorMeaning4🔴 RedCritical3🟠 OrangeHigh2🟡 YellowMedium1🟢 GreenLow
Factor 2 — Distance Zone
Rather than just "near/far", split into zones based on travel time:
ZoneTravel TimeZ10–15 minZ215–30 minZ330–60 minZ460+ min

Grouping Rules
Deliveries land in a group based on priority + zone combination:
PriorityZ1Z2Z3Z4🔴 RedSolo or tiny clusterSoloSoloSolo🟠 OrangeSmall clusterSmall clusterSoloSolo🟡 YellowCluster freelyCluster freelySmall clusterSolo🟢 GreenCluster freelyCluster freelyCluster freelyCluster freely
Rules explained:

Solo — gets its own vehicle, no batching (too urgent or too far to risk delays)
Small cluster — group with 1–2 other deliveries in the same zone only
Cluster freely — batch as many as vehicle capacity allows, optimized by proximity

# Priority Scoring
    - We fetch all of the orders, then we sort them by score (high to low). The scores are calculated based on:
        - Expiry date: Follows the formula:
            y = 10*(1-x/24)^2.5+1 {0<=x<=24}
        - Urgency + deadline proximity:
            - Deadline proximity follows the same formula as above, except we may want to adjust: y = 10*(1-x/t)^2.5+1 {0<=x<=t}. With t being the time the order was registed till deadline (in hours)
            - For urgency, it depends whether it has a multiplier or not.
        - Both of these will also have to factor in travel time, so we would want to take x as x - z, with z being expected travel time calculated from below
    - Then, we colorize and label them by using quarters. Using score obtained from the previous board we collected, we calculate Q1, Q2 and Q3
        - Low (<=Q1)
        - Medium (> Q1 but <=Q2)
        - High (>Q2 but <=Q3)
        - Critical (>Q3)

# Distance Zone:
    - Based on expected travel time, we split it into
        + Zone 1: 10-15 min travel
        + Zone 2: 15-30
        + Zone 3: 30-60
        + Zone 4: 60+

# Grouping rules:

The engine picks the highest scored unassigned order as an anchor. It then sweeps through remaining orders and pulls in ones that qualify:
    - Zone must be within ±1 of the anchor's zone (For instance, for vehicles doing zone 2 can do zone 1 and 3)
    - Score gap vs anchor must be within MAX_SCORE_GAP
    - Vehicle must still have capacity taken into account

MAX_SCORE_GAP is dynamic based on anchor score:
    - Critical anchor (score ≥ 8): gap = 2 — very strict
    - High anchor (score ≥ 5): gap = 4 — moderate
    - Low anchor (score < 5): gap = 6 — relaxed

    - After grouping, we sort by score descending. Highest score order gets delivered first, no exceptions.
    - If a new order arrives mid-run, or an existing order's score jumps significantly (deadline approaching fast), the engine re-checks existing groups. If an order's score now exceeds the gap tolerance of its group, it gets pulled out, re-grouped, and the manager is flagged if a vehicle re-route is needed. A check is done every hour

# Driver and vehicle selection:
    Driver Selection (in priority order):
        - Free drivers (under 8hrs, not on a delivery)
        - Occupied drivers (under 8hrs, can be queued)
        - Overtime drivers (8–10hrs, pick least overtime first)
        - Never select over 10hrs

    Vehicle Selection:
        - Must have enough capacity for the group's total weight
        - Must be able to reach the furthest zone in the group within time
        - Prefer smallest suitable vehicle (saves fuel, easier in city)
        - Temperature-sensitive orders require appropriate vehicle


# Rough PseudoCode
CONSTANTS:
  MAX_SHELF_LIFE = 24         // hours
  GAP_CRITICAL = 2            // max score gap when anchor is critical
  GAP_HIGH     = 4            // max score gap when anchor is high
  GAP_LOW      = 6            // max score gap when anchor is low
  SCORE_CRITICAL_THRESHOLD = 8
  SCORE_HIGH_THRESHOLD     = 5


// ─────────────────────────────────────────
// STAGE 1: SCORE EVERY ORDER
// ─────────────────────────────────────────

FUNCTION calcExpiryScore(hoursRemaining, travelTime):
  x = hoursRemaining - travelTime
  IF x <= 0: RETURN 10        // already unreachable in time
  IF x >= 24: RETURN 1
  RETURN 10 * (1 - x/24)^2.5 + 1


FUNCTION calcDeadlineScore(hoursUntilDeadline, totalWindow, travelTime):
  x = hoursUntilDeadline - travelTime
  IF x <= 0: RETURN 10        // already unreachable in time
  IF x >= totalWindow: RETURN 1
  RETURN 10 * (1 - x/totalWindow)^2.5 + 1


FUNCTION calcFinalScore(order):
  expiryScore   = calcExpiryScore(order.hoursRemaining, order.travelTime)
  deadlineScore = calcDeadlineScore(order.hoursUntilDeadline,
                                    order.totalWindow,
                                    order.travelTime)
  rawScore = max(expiryScore, deadlineScore)
  RETURN rawScore * order.urgencyMultiplier   // 1.0 if no urgency flag


// ─────────────────────────────────────────
// STAGE 2: COLORIZE AND LABEL
// ─────────────────────────────────────────

FUNCTION labelOrders(orders):
  scores = [calcFinalScore(o) FOR EACH o in orders]
  
  Q1 = 25th percentile of scores
  Q2 = 50th percentile of scores
  Q3 = 75th percentile of scores

  FOR EACH order in orders:
    order.score = calcFinalScore(order)

    IF order.score >= Q3:     order.label = CRITICAL  // 🔴
    ELSE IF order.score >= Q2: order.label = HIGH      // 🟠
    ELSE IF order.score >= Q1: order.label = MEDIUM    // 🟡
    ELSE:                      order.label = LOW       // 🟢

  RETURN orders


// ─────────────────────────────────────────
// STAGE 3: GROUP ORDERS
// ─────────────────────────────────────────

FUNCTION getZone(travelTime):
  IF travelTime <= 15: RETURN Z1
  IF travelTime <= 30: RETURN Z2
  IF travelTime <= 60: RETURN Z3
  RETURN Z4


FUNCTION getMaxGap(anchorScore):
  IF anchorScore >= SCORE_CRITICAL_THRESHOLD: RETURN GAP_CRITICAL
  IF anchorScore >= SCORE_HIGH_THRESHOLD:     RETURN GAP_HIGH
  RETURN GAP_LOW


FUNCTION groupOrders(orders):
  SORT orders by score DESC
  groups    = []
  unassigned = all orders

  WHILE unassigned is not empty:

    anchor = unassigned[0]
    anchor.zone = getZone(anchor.travelTime)
    maxGap = getMaxGap(anchor.score)
    group  = [anchor]
    remove anchor from unassigned

    FOR EACH candidate in unassigned:
      candidate.zone = getZone(candidate.travelTime)

      IF |candidate.score - anchor.score| <= maxGap
      AND |candidate.zone - anchor.zone|  <= 1
      AND group.totalWeight + candidate.weight <= vehicle.capacity:
        add candidate to group
        remove candidate from unassigned

    // Sequence: highest score first
    SORT group by score DESC

    add group to groups

  RETURN groups


FUNCTION reEvaluateGroups(groups, newOrders):
  // Pull orders that no longer fit their group
  pulled = []

  FOR EACH group in groups:
    anchor = group[0]   // highest score in group
    maxGap = getMaxGap(anchor.score)

    FOR EACH order in group (skip anchor):
      IF |order.score - anchor.score| > maxGap:
        remove order from group
        add order to pulled
        flag manager if vehicle re-route needed

  // Add new orders and pulled orders back into pool
  allPending = pulled + newOrders

  // Re-group them
  newGroups = groupOrders(allPending)
  RETURN groups + newGroups


// ─────────────────────────────────────────
// STAGE 4: ASSIGN DRIVER AND VEHICLE
// ─────────────────────────────────────────

FUNCTION selectDriver(drivers):
  freeDrivers = [d FOR d in drivers IF d.hoursWorked < 8 AND NOT d.onDelivery]
  IF freeDrivers not empty:
    RETURN freeDrivers[0]

  queueableDrivers = [d FOR d in drivers IF d.hoursWorked < 8 AND d.onDelivery]
  IF queueableDrivers not empty:
    RETURN queueableDrivers[0]

  otDrivers = [d FOR d in drivers IF 8 <= d.hoursWorked <= 10 AND NOT d.onDelivery]
  IF otDrivers not empty:
    SORT otDrivers by hoursWorked ASC   // least overtime first
    RETURN otDrivers[0]

  RETURN NULL   // no driver available, flag manager


FUNCTION selectVehicle(vehicles, group):
  totalWeight  = sum of all order weights in group
  maxZone      = highest zone number in group
  needsCooling = any order in group is temperature sensitive

  eligible = [v FOR v in vehicles IF
                v.available
                AND v.capacity >= totalWeight
                AND v.maxRange  >= maxZone
                AND (NOT needsCooling OR v.hasCooling)]

  IF eligible is empty: RETURN NULL   // flag manager

  // Prefer smallest suitable vehicle
  SORT eligible by capacity ASC
  RETURN eligible[0]


// ─────────────────────────────────────────
// MAIN ENGINE ENTRY POINT
// ─────────────────────────────────────────

FUNCTION runAssignmentEngine(orders, drivers, vehicles):

  // Stage 1 + 2
  orders = labelOrders(orders)

  // Stage 3
  groups = groupOrders(orders)

  // Stage 4
  results = []

  FOR EACH group in groups:
    driver  = selectDriver(drivers)
    vehicle = selectVehicle(vehicles, group)

    IF driver is NULL OR vehicle is NULL:
      flag group to manager
      CONTINUE

    // Commit assignment
    driver.onDelivery  = true
    vehicle.available  = false
    group.driver       = driver
    group.vehicle      = vehicle
    group.status       = IN_PROGRESS

    add group to results
    update driver and vehicle availability

  RETURN results

