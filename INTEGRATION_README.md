# Kathakali Bridge — PHP + MySQL + Moodle Integration Guide

## What this package adds

Your existing `.html` files stay **100% intact** and keep working as plain HTML.
This package wraps them with a PHP skeleton so that when you flip the switch,
real data flows in from MySQL and Moodle — with zero UI changes.

---

## File structure

```
your-project/
├── assests/                     ← your existing image folder (keep as-is)
├── styles.css                   ← unchanged
├── schedule.css                 ← unchanged
├── students.css                 ← unchanged
├── shared.js                    ← updated (reads window.__KB, calls api/)
├── script.js                    ← updated (Moodle bridge + live data loaders)
├── students.js                  ← unchanged
├── schedule.js                  ← unchanged
│
├── teacher-dashboard.html       ← still works as plain HTML (fallback)
├── students.html                ← still works as plain HTML (fallback)
├── schedule.html                ← still works as plain HTML (fallback)
├── settings.html                ← still works as plain HTML (fallback)
│
├── teacher-dashboard.php        ← PHP version (replaces .html in production)
│
├── auth.php                     ← SESSION gate — include at top of every .php page
├── schema.sql                   ← run once to create DB + seed data
│
├── config/
│   ├── config.php               ← DB credentials + Moodle URL/token
│   └── db.php                   ← PDO singleton
│
└── api/
    ├── teacher.php              ← GET/POST: teacher profile + notifications
    ├── classes.php              ← GET: schedule, next class, heatmap metrics
    ├── students.php             ← GET: student list + detail
    ├── assignments.php          ← GET/POST: critique queue + mark reviewed
    └── moodle_bridge.php        ← GET: Moodle SSO launch URL + enrolled users
```

---

## Step-by-step integration algorithm

### STEP 0 — Prerequisites

You need:
- PHP 8.0+ with PDO and cURL extensions
- MySQL 5.7+ or MariaDB 10.4+
- A running Moodle instance (3.9+)
- Apache or Nginx (or PHP built-in server for local dev)

---

### STEP 1 — Create the MySQL database

```bash
mysql -u root -p < schema.sql
```

This creates the `kathakali_bridge` database with all tables and seeds
the same mock data your HTML files currently show (Student-1 through Student-7,
Class 1–4, 4 pending assignments, 7 notifications).

---

### STEP 2 — Set your credentials

Edit `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'kathakali_bridge');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('MOODLE_URL',   'https://your-moodle-site.com');
define('MOODLE_TOKEN', 'paste_your_webservice_token_here');
```

---

### STEP 3 — Set up Moodle web services (one-time)

In Moodle admin panel:

1. **Site admin → Advanced features** → enable "Web services" ✓
2. **Site admin → Plugins → Web services → Manage protocols** → enable REST ✓
3. **Site admin → Plugins → Web services → External services → Add**
   - Name: `Kathakali Bridge`
   - Add these functions:
     - `core_webservice_get_site_info`
     - `core_enrol_get_enrolled_users`
     - `core_user_get_users_by_field`
     - `auth_userkey_request_login_url`  ← for SSO auto-login
4. **Site admin → Web services → Manage tokens → Add token**
   - User: your teacher account
   - Service: Kathakali Bridge
   - Copy the token → paste into `config/config.php`
5. **Link the teacher's Moodle user ID** in MySQL:
   ```sql
   UPDATE teachers
   SET moodle_user_id = 42,   -- replace 42 with the actual Moodle user id
       moodle_token   = 'your_token_here'
   WHERE teacher_id = 1;
   ```
   Find the Moodle user id: Moodle → Site admin → Users → find user → check URL for `id=42`.

---

### STEP 4 — Link your Moodle courses to DB classes

```sql
UPDATE classes SET moodle_course_id = 101 WHERE name LIKE '%Navarasas%';
UPDATE classes SET moodle_course_id = 102 WHERE name LIKE '%Mudra Basics%';
UPDATE classes SET moodle_course_id = 103 WHERE name LIKE '%Thandava%';
UPDATE classes SET moodle_course_id = 104 WHERE name LIKE '%Expressions%';
```

Find a Moodle course id: open any course in Moodle → check URL for `id=101`.

---

### STEP 5 — Serve with PHP

**Local development:**
```bash
cd your-project/
php -S localhost:8080
# Open http://localhost:8080/teacher-dashboard.php
```

**Apache (production):** drop files in `/var/www/html/kathakali/`, done.
**Nginx:** point root to project folder, ensure `.php` files are passed to php-fpm.

---

### STEP 6 — Test the integration layers (in order)

```
1. Visit http://localhost:8080/api/teacher.php
   → Should return JSON with teacher name and 7 notifications.
   → If you see "Database unavailable" — fix DB credentials in config.php.

2. Visit http://localhost:8080/api/classes.php?scope=today
   → Should return JSON array of today's 4 classes.

3. Visit http://localhost:8080/api/classes.php?scope=next
   → Should return the next upcoming class with moodle_course_id.

4. Visit http://localhost:8080/api/moodle_bridge.php?action=launch&course_id=102
   → With moodle_user_id set: returns {"url":"https://moodle/...","mode":"sso"}
   → Without moodle_user_id: returns {"url":"https://moodle/...","mode":"manual_login"}
   → Moodle not configured yet: returns {"url":"...","mode":"fallback","note":"..."}

5. Open http://localhost:8080/teacher-dashboard.php
   → Header shows real teacher name (from DB, not hardcoded)
   → Notifications loaded from DB
   → Click "Enter Studio" → Moodle demo overlay appears (plain HTML mode)
     OR → Moodle course opens in new tab (PHP mode with real moodle_course_id)
```

---

### STEP 7 — Add PHP to remaining pages

Copy the auth.php include pattern to each page:

```php
<?php require_once 'auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    ...
    <?php echo kb_inject_script($teacher); ?>   ← add this line
</head>
```

Rename files: `students.html` → `students.php`, etc.
Update internal links to point to `.php` extensions.

---

## How the Enter Studio → Moodle flow works

```
[Teacher clicks "Enter Studio"]
        |
        v
script.js: fetch('api/moodle_bridge.php?action=launch&course_id=102')
        |
        v
moodle_bridge.php:
  1. Checks $_SESSION['teacher_id'] (auth.php already ran)
  2. Reads teacher's moodle_user_id from DB
  3. Calls Moodle REST: auth_userkey_request_login_url
  4. Moodle returns a one-time URL valid for ~60 seconds
  5. PHP appends ?wantsurl=.../course/view.php?id=102
  6. Returns {"url": "https://moodle/...", "mode": "sso"}
        |
        v
script.js: window.open(data.url, '_blank')
        |
        v
[Teacher lands on Moodle course page — already logged in — no re-auth needed]
```

If Moodle is not yet configured: the demo overlay shows a mock Moodle
interface so stakeholders can see exactly what the integration will look like.

---

## What stays the same (the promise)

- All `.html` files continue to work as before
- All CSS is unchanged
- The UI, layout, widgets, and design are untouched
- `shared.js` reads `window.__KB` first; falls back to hardcoded values
  when not on a PHP server — so the same JS file works in both modes
- The Moodle demo overlay only appears when PHP is not connected;
  once connected it is bypassed silently

---

## Security checklist before going live

- [ ] Remove the demo auto-login block from `auth.php` (lines 28–32)
- [ ] Add a real login page and redirect unauthenticated requests there
- [ ] Set `SSL_VERIFYPEER => true` in `moodle_bridge.php` (already true)
- [ ] Change `CORS_ORIGIN` from `'*'` to your actual domain
- [ ] Use HTTPS on both your app and Moodle
- [ ] Store `MOODLE_TOKEN` in an environment variable, not in `config.php`
- [ ] Rotate Moodle token every 90 days
