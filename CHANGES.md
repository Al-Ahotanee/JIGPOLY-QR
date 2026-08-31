# Changes Applied — GPS Attendance System

Rebranded for **JIGPOLY Polytechnic (OND / ND / HND)**
and fixed the **"session is not active"** attendance-marking bug (timezone).

---

## 1. ROOT CAUSE — Timezone Bug (the main issue)

### What was happening
Students could not mark attendance even though the session was clearly active.
The system reported *"Attendance window closed"* / *"session is not active"*.

### Why
- `config/db.php` did **not** set a PHP timezone, so PHP fell back to the
  server default (usually **UTC**).
- `lecturer.php` set the **MySQL** session timezone to `+01:00` (WAT) but
  **not** the **PHP** timezone.
- The lecturer's `<input type="datetime-local">` sends a local wall-clock
  string with **no timezone suffix** (e.g. `2025-03-26T15:50`), which MySQL
  stores verbatim into a `DATETIME` column (no conversion).
- When a student marked attendance, `api/student.php` / `student.php` ran:
  ```php
  $now   = new DateTime();                       // UTC
  $start = new DateTime($session['start_time']); // parsed as UTC
  ```
  So `now` was 1 hour behind the stored local start time, and the check
  `$now < $start` was true → "window closed".

### The fix
Pin **both PHP and MySQL** to `Africa/Lagos` on every connection, in the
single shared file `config/db.php`:
```php
date_default_timezone_set('Africa/Lagos');
$pdo->exec("SET time_zone = '+01:00'");
```
Now `new DateTime()` and the stored `DATETIME` values are interpreted in
the same timezone, so the comparison is correct.

---

## 2. Files Changed

| File | What changed |
|------|--------------|
| `config/db.php` | **Central timezone fix.** Sets `date_default_timezone_set('Africa/Lagos')` + `SET time_zone = '+01:00'`. Also switched to `utf8mb4`, `PDO::FETCH_ASSOC`, real prepared statements, and safer error handling. **All other PHP files now `require` this file** instead of opening their own connection. |
| `api/student.php` | Uses `config/db.php` (timezone). Fixed field name `device_id` → `device_info` to match the `attendance` table column and the payload sent by `student.js`. Insert now sets `status = 'valid'`. Added input validation, separate "session not found" vs "session not active" messages, and a debug payload on time-window failure. |
| `api/lecturer.php` | Uses `config/db.php` (timezone). Normalises `datetime-local` strings to MySQL `DATETIME` format. Returns `start_time` + `end_time` to the client. Added `toggle_session`, `get_attendance`, `get_dashboard` actions. Hardened input validation. |
| `api/auth.php` | Uses `config/db.php` (timezone). Added `session_regenerate_id(true)` on login to prevent session fixation. Trims email input. |
| `lecturer.php` | Uses `config/db.php` (timezone). Added `normalise_datetime()` helper so `datetime-local` values are stored in proper MySQL format. Client-side countdowns now parse stored wall-clock times as `Africa/Lagos` (`+01:00`) so the browser and server agree. Rebranded "FCIT BUK" → "JIGPOLY Polytechnic". |
| `student.php` | Uses `config/db.php` (timezone). The `mark_attendance` time-window check now compares in the same timezone. Improved error messages with debug payload. Fixed excuse-document validation (extension allow-list). Fixed `FileReader` path for the excuse form. Client-side date parsing uses `+01:00` to match the server. Rebranded. |
| `admin.php` | Uses `config/db.php` (timezone). Rebranded. |
| `api/admin.php` | Uses `config/db.php` (timezone). Rebranded. |
| `attendance_register.php` | Uses `config/db.php` (timezone). Rebranded. |
| `index.php` | Rebranded: "FCIT BUK" → "JIGPOLY Polytechnic", "College of Computing and Information Technology, Bayero Polytechnic Kano" → "JIGPOLY Polytechnic (OND / ND / HND)". |
| `login.php` | Rebranded. |
| `database.sql` | **NEW.** Full schema + seed data, for fresh installs. The `attendance` table uses `device_info` (matching the app), `status` defaults to `valid`, and the MySQL session timezone is pinned to `+01:00`. |

---

## 3. How to Install the Fix

### Option A — You already have a running install
1. Back up your current files.
2. Copy these files over your existing installation:
   - `config/db.php`
   - `api/student.php`, `api/lecturer.php`, `api/auth.php`, `api/admin.php`
   - `lecturer.php`, `student.php`, `admin.php`, `attendance_register.php`
   - `index.php`, `login.php`
3. Open `config/db.php` and confirm the `$db_host / $db_user / $db_pass / $db_name` match your server.
4. Done. Existing sessions will now be recognised as active at the correct local time.

### Option B — Fresh install
1. Create the database: `mysql -u root -p < database.sql`
2. Drop all the PHP files into your web root (e.g. `htdocs/gps-attendance/`).
3. Edit `config/db.php` if your DB credentials differ from `root` / `` / `gps_attendance_db`.
4. Visit `login.php` and log in with one of the seed accounts
   (e.g. `student@jigpoly.edu.ng` / `123`).

---

## 4. Verifying the Fix

After deploying, create a session in the lecturer dashboard with a
start time a couple of minutes in the future and an end time ~1 hour out,
then log in as a student and scan the QR code. Attendance should now be
marked successfully.

If you ever see *"Attendance window closed"* again, the response now
includes a `debug` payload:
```json
{
  "status": "error",
  "message": "Attendance window closed",
  "debug": {
    "now": "2025-08-28 14:05:00",
    "start": "2025-08-28 14:00:00",
    "end": "2025-08-28 15:00:00",
    "server_timezone": "Africa/Lagos"
  }
}
```
If `server_timezone` is anything other than `Africa/Lagos`, the fix has
not been applied to the file handling that request — check that it
`require`s `config/db.php`.

---

## 5. Other Issues Fixed Along the Way

- **`device_id` vs `device_info` mismatch** — `api/student.php` was reading
  `$_POST['device_id']` and querying `WHERE device_id = ?`, but the table
  column and the JS payload both use `device_info`. Fixed.
- **Missing `status` on insert** — `api/student.php` inserted a new
  attendance row without setting `status`, which would fail on a NOT NULL
  column. Now sets `status = 'valid'`.
- **Session fixation** — `api/auth.php` now calls `session_regenerate_id(true)`
  on successful login.
- **Excuse document validation** — `student.php` now restricts uploads to
  `pdf/jpg/jpeg/png` and handles the `FileReader` path correctly when no
  file is attached.
- **Client-side countdown drift** — `lecturer.php` and `student.php` now
  parse stored wall-clock times with an explicit `+01:00` offset so the
  browser countdown matches the server-side window check.
- **Inconsistent DB connections** — `lecturer.php`, `admin.php`,
  `api/admin.php`, `attendance_register.php` each opened their own PDO
  connection with no timezone set. They now all go through `config/db.php`.
