SETUP — Delivery Auto-Assignment (Local XAMPP)
=============================================

This document explains how to set up the project locally using XAMPP (Apache + MySQL + PHP).

NB : For the sources and URL, I took example on how the files are save in my laptop. So make sure to have the correct pathway before starting.

Prerequisites
- XAMPP installed (Windows): https://www.apachefriends.org/index.html
- PHP and MySQL available via the XAMPP control panel
- A MySQL client or admin UI (phpMyAdmin included in XAMPP)

Quick Start (fresh machine)
1. Copy the project to your web root, e.g. `C:\xampp\htdocs\epita2026\rla2`
2. Start XAMPP: Apache and MySQL services
3. Create the database and schema:

   - Open `http://localhost/phpmyadmin` or use the `mysql` CLI.
   - Create a database named `rla_medical_delivery` (or edit `schema.sql` to use your chosen name).
   - Run the schema file:

```sql
SOURCE C:/xampp/htdocs/epita2026/rla2/schema.sql;
```

4. Load seed data (optional but useful for demo/test):

```sql
SOURCE C:/xampp/htdocs/epita2026/rla2/seed.sql;
```

5. Confirm web files are accessible: open `http://localhost/epita2026/rla2/` in your browser. It will redirect to the dashboard at `src/frontend/pages/index.php`.

API Quick Test (Thunder Client / Postman)
- Base entrypoint: `http://localhost/epita2026/rla2/src/backend/api/index.php`

- Example: driver login (POST)

  URL: `http://localhost/epita2026/rla2/src/backend/api/index.php/api/driver/login`
  Body (JSON): `{ "driver_id": 1 }`

- Example: queue (GET)

  URL: `http://localhost/epita2026/rla2/src/backend/api/index.php/api/driver/queue?driver_id=1`

- Day-off request (POST)

  URL: `http://localhost/epita2026/rla2/src/backend/api/index.php/api/driver/day-off`
  Body (JSON): `{ "driver_id":1, "request_date":"2026-06-10", "reason":"Personal" }`

Notes & Troubleshooting
- If endpoints return 404, ensure you call the index.php entrypoint with `/api/...` appended (the router inspects the request path).
- If `Driver not found`, verify the `drivers` table contains the requested `id` (see `src/frontend/pages/drivers.php`).
- If MySQL errors occur when loading the schema/seed, check your `sql_mode` and ensure `STRICT` settings won't silently fail — use phpMyAdmin import or the `SOURCE` command in `mysql` CLI.
- If you prefer a simpler local setup, you can run a Dockerized MySQL and PHP container, but XAMPP is the fastest for Windows.

