# Delivery Auto-Assignment App — Simplified Team Checklist

**Goal:** Reduce confusion, avoid overlap, and make dependencies clearer before the May 31 demo.

---

## 📊 Current Status Summary

### ✅ Completed This Session
- Percentile-based dynamic gap calculation (replaces static thresholds)
- Driver queuing system (FIFO per driver)
- Vehicle reusability (6 vehicles, unlimited groups)
- Work hours tracking across queued groups
- Queue position tracking in database
- RunAssignment.php restructured to display queues

### ⚠️ Next Priority Tasks
1. **Database Migration** — Add `queue_position` column to `delivery_assignments`
2. **Frontend Modal** — Display assignment results with queue info
3. **Manager UI Updates** — Show driver queues during reassignment
4. **Validation** — Enforce work hour limits in UI
5. **Driver Portal** — Show queue positions to drivers

---

# Team Structure

### Person A — Backend & Database

Database, APIs, authentication, deployment setup

### Person B — Frontend & User Interaction

Pages, forms, dashboards, UX, API integration

### Person C — Algorithm, Testing & QA

Assignment logic, test data, scenario testing, validation

---

# Core Rules (Important)

## Before Starting Any Task

* Pull latest code from GitHub
* Check if another task depends on yours
* If blocked, message the team immediately

## Handoff Rule

When a task is completed:

1. Push code
2. Test it
3. Tell the next person exactly what changed
4. Share endpoint/file names if needed

## To Avoid Conflicts

* One person per file whenever possible
* Do not edit another person's feature without telling them
* Use separate branches:

  * `backend/...`
  * `frontend/...`
  * `algorithm/...`
* Merge only after testing

---

# PHASE 1 — Foundation (Days 1–2)

## A. Database Setup (Person A)

**Must finish before most backend/frontend work begins**

### Tasks

* [x] Create `drivers` table
* [x] Create `vehicles` table
* [x] Create `products` table
* [x] Create `deliveries` table
* [x] Create reusable DB connection (`Database.php`)
* [x] **⚠️ PENDING: Add `queue_position` column to `delivery_assignments`**
  ```sql
  ALTER TABLE delivery_assignments 
  MODIFY COLUMN scheduled_start TIMESTAMP NULL DEFAULT NULL,
  MODIFY COLUMN scheduled_end TIMESTAMP NULL DEFAULT NULL;
  
  ALTER TABLE delivery_assignments 
  ADD COLUMN queue_position INT NOT NULL DEFAULT 1;
  ```
  Or drop/recreate with updated schema.sql

### Deliverable

* Database works ✅
* Tables accessible ✅
* Other teammates can connect safely ✅
* Queue position tracking added (**BLOCKING frontend modal**)

---

## B. Algorithm Planning (Person C)

**Design only — no coding yet**

### Tasks

* [x] Define delivery priority rules
* [x] Define driver selection rules
* [x] Define vehicle matching rules
* [x] Define product grouping rules
* [x] Write pseudocode for `AssignmentEngine.php`

### Deliverable

* Clear algorithm document everyone can follow

### Needed By

* Person A for API endpoint
* Person C later for implementation

---

## C. Static Frontend Setup (Person B)

**UI skeleton only**

### Tasks

* [X] Create:

  * `index.html`
  * `drivers.html`
  * `products.html`
  * `deliveries.html`
  * `vehicles.html`
* [X] Add navigation menu
* [X] Add base CSS styling

### Deliverable

* Pages open correctly done
* Navigation works done
* Layout ready for real data done

---

# PHASE 2 — APIs + Integration (Days 3–5)

## A. Backend APIs (Person A)

### Tasks

* [X] Drivers API
* [X] Products API
* [X] Deliveries API
* [X] Vehicles API
* [X] Auto-assign endpoint

### Requirements Before Starting

* Database complete
* Algorithm pseudocode complete

### Deliverable

* APIs tested in Postman
* Database ↔ API working

---

## B. Frontend Integration (Person B)

### Tasks

* [X] Connect drivers page to API
* [X] Connect products page to API
* [X] Connect deliveries page to API
* [ ] Create auto-assign modal with queue visualization
* [ ] Add loading/error states
* [ ] Display driver queue positions on UI

### Requirements Before Starting

* Static pages complete ✅
* APIs available ✅
* Queue position tracking in DB ✅

### Deliverable

* Real data visible on frontend ✅
* Auto-assign button shows results with queue info
* Queue positions displayed per driver (**PENDING**)

---

## C. Algorithm Implementation & Testing (Person C)

### Tasks

* [X] Build `AssignmentEngine.php`
* [X] Create test dataset
* [X] Run assignment tests
* [X] Document edge cases
* [X] Implement percentile-based dynamic gaps (instead of static thresholds)
* [X] Implement driver queuing system (FIFO stack per driver)
* [X] Implement vehicle reusability (6 vehicles serve unlimited groups)
* [X] Add work hours tracking across queued groups

### Deliverable

* Auto-assignment works correctly with dynamic gaps
* Drivers can queue multiple delivery groups
* Vehicles can serve multiple driver queues
* No crashes with sample data
* ✅ **RunAssignment.php displays queue positions**

---

# PHASE 3 — Manager Features (Days 6–8)

## A. Manager Backend (Person A)

### Tasks

* [X] Status update endpoint
* [X] Manual reassignment endpoint
* [X] Delivery grouping endpoint
* [X] Dashboard route
* [X] Stats endpoint

### Deliverable

* Manager actions supported by backend

---

## B. Manager Frontend (Person B)

### Tasks

* [X] Build dashboard UI
* [ ] Create reassignment modal (support multiple queued groups)
* [ ] Create grouping interface (show queue positions)
* [ ] Add validation + notifications (work hour violations)

### Notes

* Need to display driver queue when reassigning
* Show work hours impact of reassignments
* Validate new assignments don't exceed 10hr limit

### Deliverable

* Managers can control deliveries visually
* Queue information available during reassignment (**PENDING**)

---

## C. Delivery Tracking UI (Person C)

### Tasks

* [X] Parent/child delivery display
* [X] Progress indicators (countdown timers per delivery)
* [X] Priority + overdue alerts (color-coded + summary counts)
* [X] Add tracking page to navigation

### Deliverable

* ✅ Full delivery lifecycle visible at `src/frontend/pages/tracking.php`
* ✅ Parent view: Group ID, Driver, Vehicle, Weight, Cooling, Deadline, Countdown
* ✅ Child view: Order ID, Priority, Status, Countdown
* ✅ Alerts: Color-coded (red=overdue, yellow=at-risk, green=on-track) + summary counts
* ✅ Navigation link added

---

# PHASE 4 — Driver Portal + Testing (Days 9–11)

## A. Driver Backend (Person A)

### Tasks

* [x] Driver login
* [x] Driver queue endpoint
* [x] Day-off request endpoint
* [x] Vehicle info endpoint

### Deliverable

* Drivers can access personal delivery data

---

## B. Driver Frontend + Browser Testing (Person B)

### Tasks

* [ ] Build driver queue page
* [ ] Build day-off request page
* [ ] Test on Chrome/Firefox/Safari
* [ ] Fix UI bugs
* [ ] Ensure responsive design

### Deliverable

* Stable frontend on all major devices/browsers

---

## C. End-to-End Testing (Person C)

### Test Scenarios

* [ ] Auto-assign products
* [ ] Driver requests day off
* [ ] Manager reassigns delivery
* [ ] Overdue delivery alert
* [ ] Full delivery lifecycle

### Deliverable

* All workflows tested successfully

---

# PHASE 5 — Demo Prep (Days 12–13)

## A. Setup & Deployment (Person A)

### Tasks

* [x] Create seed data
* [x] Write `SETUP.md`
* [x] Test setup on fresh machine

### Deliverable

* Anyone can run project quickly

---

## B. Documentation (Person B)

### Tasks

* [ ] Write README
* [ ] Explain features
* [ ] Explain limitations
* [ ] Add usage examples

### Deliverable

* Project easy to understand

---

## C. Final Demo Prep (Person C)

### Tasks

* [ ] Final regression tests
* [ ] Verify all buttons/forms
* [ ] Create demo walkthrough
* [ ] Practice demo

### Deliverable

* Demo-ready application

---

# Simplified Dependency Flow

```text
DATABASE (A)
   ↓
APIs (A)
   ↓
FRONTEND CONNECTIONS (B)
   ↓
MANAGER + DRIVER FEATURES
   ↓
TESTING (C)
   ↓
DEMO PREP
```

Algorithm flow:

```text
Algorithm Design (C)
   ↓
AssignmentEngine.php (C)
   ↓
Auto-Assign API (A)
   ↓
Frontend Auto-Assign UI (B)
   ↓
Testing (C)
```

---

# Recommended Daily Workflow

## Morning

* 10-minute standup
* Mention:

  * What you finished
  * What you're doing today
  * What is blocking you

## During Work

* Push commits regularly
* Avoid giant untested merges

## End of Day

* Update checklist
* Notify next person if handoff is ready

---

# Biggest Risk Areas (Avoid These)

## 1. Backend Changes Breaking Frontend

Solution:

* Agree on API response format early
* Do not rename fields randomly

## 2. Multiple People Editing Same Files

Solution:

* Assign ownership per file/page

## 3. Algorithm Delays Blocking Everything

Solution:

* Finish pseudocode early
* Start with simple logic first

## 4. Testing Left Too Late

Solution:

* Test every phase immediately after completion

---

# Final Success Criteria (May 31)

By demo day:

* Manager can auto-assign deliveries
* Driver can view assigned queue
* Reassignment works
* Status updates work
* Overdue alerts visible
* No major crashes/errors
* Setup works on another machine

✅ If all of these work, the MVP is successful.
