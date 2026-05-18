# Digital Art School - Student Module Documentation

## Project Overview

**Project Name:** Digital Art School - Student Module  
**Purpose:** A comprehensive student registration and management system for a digital art school that integrates with Moodle LMS  
**Technology Stack:** PHP 8.1+, MySQL 8.0+, HTML5, CSS3, JavaScript  
**Database:** das_student (MySQL)  
**Integration:** Moodle LMS via REST API

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation & Setup](#installation--setup)
3. [Database Configuration](#database-configuration)
4. [Moodle Integration Setup](#moodle-integration-setup)
5. [Project Structure](#project-structure)
6. [Features & Functionality](#features--functionality)
7. [User Flow](#user-flow)
8. [API Integration](#api-integration)
9. [Security Features](#security-features)
10. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Server Requirements
- **Web Server:** Apache 2.4+ (WAMP/XAMPP/LAMP)
- **PHP:** Version 8.1.13 or 8.2.30
- **Database:** MySQL 8.0.31 (Port 3306) OR MariaDB 10.x (Port 3307)
- **Operating System:** Windows/Linux/macOS

### PHP Extensions Required
- mysqli
- curl
- json
- session
- fileinfo
- gd (for image processing)

### Browser Requirements
- Modern browsers (Chrome, Firefox, Safari, Edge)
- JavaScript enabled
- Cookies enabled

---

## Installation & Setup

### Step 1: Extract Project Files

1. Extract the `digital-art-school` folder to your web server directory:
   - **WAMP:** `C:\wamp64\www\digital-art-school`
   - **XAMPP:** `C:\xampp\htdocs\digital-art-school`
   - **Linux:** `/var/www/html/digital-art-school`

### Step 2: Set Folder Permissions

Ensure the `uploads` folder is writable:

```bash
# Linux/Mac
chmod 755 uploads/

# Windows: Right-click folder > Properties > Security > Edit permissions
```

### Step 3: Import Database Schema

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `das_student`
3. Set collation to `utf8mb4_unicode_ci`
4. Import the `database_schema.sql` file:
   - Click on the `das_student` database
   - Click "Import" tab
   - Choose `database_schema.sql`
   - Click "Go"

### Step 4: Configure Database Connection

Edit `includes/config.php` and update the following constants:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306'); // Change to 3307 if using MariaDB
define('DB_USER', 'root');
define('DB_PASS', 'your_password_here'); // Your MySQL password
define('DB_NAME', 'das_student');
```

### Step 5: Test the Installation

1. Start your web server (Apache) and MySQL
2. Open browser and navigate to: `http://localhost/digital-art-school`
3. You should see the splash screen animation
4. After 2 seconds, you'll be redirected to the login page

---

## Database Configuration

### Connection Specifications

**Standard Configuration:**
- Host: `127.0.0.1` (preferred over `localhost`)
- Port: `3306` (MySQL) or `3307` (MariaDB)
- User: `root`
- Password: Your database password
- Database: `das_student`
- Charset: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`

### Database Tables

1. **students** - Stores student registration data
2. **teachers** - Teacher information (pre-populated with 5 sample teachers)
3. **teacher_requests** - Student requests to join teacher classes
4. **session_logs** - Login/logout tracking
5. **moodle_sync_log** - Moodle API synchronization logs

### Sample Teachers (Pre-loaded)

The database comes with 5 sample teachers:
- Dr. Lakshmi Nair (Classical Dance - Kathakali, Mohiniyattam)
- Prof. Ravi Kumar (Vocal Music - Carnatic Vocal)
- Smt. Priya Menon (Visual Arts - Mural Painting)
- Sri. Anand Krishnan (Classical Dance - Mohiniyattam)
- Smt. Geetha Sharma (Vocal Music - Hindustani Classical)

---

## Moodle Integration Setup

### Prerequisites

1. Moodle installation running (e.g., http://localhost/moodle)
2. Admin access to Moodle

### Step 1: Enable Web Services in Moodle

1. Login to Moodle as Admin
2. Go to: **Site administration** > **Advanced features**
3. Enable "Enable web services" checkbox
4. Click "Save changes"

### Step 2: Enable REST Protocol

1. Go to: **Site administration** > **Server** > **Web services** > **Manage protocols**
2. Enable the "REST protocol"

### Step 3: Create Web Service User

1. Go to: **Site administration** > **Users** > **Accounts** > **Add a new user**
2. Create a user (e.g., username: `webservice`, password: strong password)
3. Assign this user the "Web Service" role

### Step 4: Create a Web Service

1. Go to: **Site administration** > **Server** > **Web services** > **External services**
2. Click "Add"
3. Name: "Digital Art School API"
4. Short name: "das_api"
5. Enabled: Yes
6. Authorized users only: No (or add specific users)
7. Click "Add service"

### Step 5: Add Functions to Web Service

1. Click "Add functions" for the service you just created
2. Add these functions:
   - `core_user_create_users`
   - `core_user_get_users`
   - `core_webservice_get_site_info`
   - `core_course_get_courses`
   - `enrol_manual_enrol_users`
3. Click "Add functions"

### Step 6: Generate Token

1. Go to: **Site administration** > **Server** > **Web services** > **Manage tokens**
2. Click "Add"
3. User: Select the webservice user created in Step 3
4. Service: Digital Art School API
5. Click "Save changes"
6. **Copy the generated token** - you'll need this!

### Step 7: Configure Moodle in DAS

Edit `includes/moodle_config.php`:

```php
define('MOODLE_URL', 'http://localhost/moodle'); // Your Moodle URL
define('MOODLE_WS_TOKEN', 'paste_your_token_here'); // Token from Step 6
define('MOODLE_DB_NAME', 'moodle_db'); // Your Moodle database name
define('MOODLE_DB_PASS', 'your_password'); // Your MySQL password
```

### Step 8: Test Moodle Connection

Create a test PHP file: `test_moodle.php`

```php
<?php
require_once 'includes/config.php';
require_once 'includes/moodle_config.php';

// Test if Moodle is reachable
if (isMoodleReachable()) {
    echo "✓ Moodle is reachable<br>";
} else {
    echo "✗ Cannot reach Moodle<br>";
}

// Test if token is valid
if (validateMoodleToken(MOODLE_WS_TOKEN)) {
    echo "✓ Moodle token is valid<br>";
} else {
    echo "✗ Invalid Moodle token<br>";
}
?>
```

---

## Project Structure

```
digital-art-school/
├── index.html                 # Splash screen (entry point)
├── database_schema.sql        # Database schema
│
├── assets/
│   ├── css/
│   │   ├── splash.css         # Splash screen styles
│   │   ├── auth.css           # Login/registration styles
│   │   ├── register.css       # Multi-step form styles
│   │   └── dashboard.css      # Dashboard styles
│   │
│   └── js/
│       ├── splash.js          # Splash screen animation
│       └── register.js        # Registration form logic
│
├── includes/
│   ├── config.php             # Database configuration & helper functions
│   ├── moodle_config.php      # Moodle integration configuration
│   └── moodle_api_helper.php  # Moodle API functions
│
├── pages/
│   ├── login.php              # Login page
│   ├── register.php           # Registration page (multi-step)
│   ├── dashboard.php          # Student dashboard
│   └── logout.php             # Logout handler
│
└── uploads/                   # User uploaded files (showcase)
```

---

## Features & Functionality

### 1. Splash Screen
- **Duration:** 2 seconds
- **Animation:** Minimalistic logo animation with SVG
- **Brand Colors:** Following branding guidelines
- **Transition:** Smooth slide-left animation to login

### 2. Login System
- **Email/Password Authentication**
- **Password Hashing:** BCrypt (cost factor: 12)
- **Session Management:** Secure session handling
- **Login Tracking:** Records IP, user agent, timestamp
- **Auto-redirect:** If already logged in, redirect to dashboard

### 3. Registration System (Multi-Step)

#### Step 1: Basic Account Info
- Full Name (required)
- Email Address (required, validated)
- Password (required, min 8 chars, hashed)
- Confirm Password (must match)
- Age (optional)
- Location (with auto-detection using Geolocation API)

#### Step 2: Art Selection & Interests
- Art Category (required) - Dropdown
  - Classical Dance
  - Vocal Music
  - Instrumental Music
  - Visual Arts
- Art Discipline (required) - Dynamic based on category
- Secondary Interest (optional)

#### Step 3: Experience & Background
- Skill Level (required) - complete_beginner, basic, intermediate, advanced
- Years of Experience (optional)
- Previous Training (optional, textarea)
- Showcase File (optional, max 10MB, jpg/png/pdf/mp4/mov/avi)

#### Step 4: Learning Goals & Schedule
- Learning Purpose (required) - hobby, professional, performance, certification, therapy
- Time Commitment (optional, e.g., "5-7 hours/week")
- Preferred Schedule (required) - weekday/weekend morning/evening
- Specific Goals (optional, textarea)
- Physical Constraints (optional, textarea)
- Learning Accommodations (optional, textarea)

### 4. Moodle Integration
- **Automatic Sync:** On successful registration
- **User Creation:** Creates user in Moodle via REST API
- **Profile Data:** Syncs art category, discipline, skill level
- **Error Handling:** Registration succeeds even if Moodle sync fails
- **Sync Logging:** All sync attempts logged in `moodle_sync_log` table
- **Status Display:** Dashboard shows Moodle sync status

### 5. Dashboard
- **Profile Information Card**
- **Art Selection Card**
- **Experience & Background Card**
- **Learning Goals Card**
- **Moodle Sync Status Badge**
- **Logout Functionality**

---

## User Flow

### New User Journey

1. **Splash Screen** (2 seconds)
   ↓
2. **Login Page**
   - Click "Register here"
   ↓
3. **Registration (4 Steps)**
   - Step 1: Account Info
   - Step 2: Art Selection
   - Step 3: Experience
   - Step 4: Goals
   ↓
4. **Moodle Sync** (automatic, background)
   ↓
5. **Dashboard** (auto-login after registration)

### Returning User Journey

1. **Splash Screen** (2 seconds)
   ↓
2. **Login Page**
   - Enter Email & Password
   ↓
3. **Dashboard** (if credentials valid)

---

## API Integration

### Moodle REST API Functions Used

1. **core_user_create_users**
   - Creates new user in Moodle
   - Parameters: username, password, firstname, lastname, email, etc.

2. **core_user_get_users**
   - Retrieves user information
   - Used to check if user already exists

3. **core_webservice_get_site_info**
   - Tests API connectivity
   - Validates web service token

### API Call Example

```php
$studentData = [
    'student_id' => 123,
    'full_name' => 'John Doe',
    'email' => 'john@example.com',
    'art_category' => 'Classical Dance',
    'art_discipline' => 'Kathakali',
    'skill_level' => 'intermediate'
];

$result = syncStudentToMoodle($studentData);

if ($result['success']) {
    echo "Synced! Moodle User ID: " . $result['moodle_user_id'];
} else {
    echo "Failed: " . $result['message'];
}
```

---

## Security Features

1. **Password Security**
   - BCrypt hashing (cost: 12)
   - Minimum 8 characters
   - Stored as hash, never plain text

2. **SQL Injection Prevention**
   - Prepared statements for all database queries
   - Parameter binding

3. **XSS Prevention**
   - Input sanitization with `htmlspecialchars()`
   - Output escaping

4. **CSRF Protection**
   - Token generation and validation functions available
   - Session-based token storage

5. **Session Security**
   - Session ID regeneration
   - Secure session configuration
   - Session timeout (1 hour)

6. **File Upload Security**
   - File type validation
   - File size limits (10MB max)
   - Unique filename generation
   - Allowed extensions only

7. **Input Validation**
   - Email validation
   - Required field checking
   - Data type validation

---

## Troubleshooting

### Database Connection Issues

**Problem:** "Database connection error"

**Solutions:**
1. Check MySQL/MariaDB is running
2. Verify credentials in `includes/config.php`
3. Check database port (3306 or 3307)
4. Ensure `das_student` database exists
5. Test connection:
   ```bash
   mysql -u root -p -h 127.0.0.1 -P 3306
   ```

### Moodle Sync Failures

**Problem:** "Moodle sync failed"

**Solutions:**
1. Check Moodle is running: http://localhost/moodle
2. Verify `MOODLE_WS_TOKEN` in `includes/moodle_config.php`
3. Check web services are enabled in Moodle
4. Check `moodle_sync_log` table for error details:
   ```sql
   SELECT * FROM moodle_sync_log ORDER BY sync_timestamp DESC LIMIT 10;
   ```
5. Test with `test_moodle.php` file

### File Upload Issues

**Problem:** "File upload failed"

**Solutions:**
1. Check `uploads/` folder exists
2. Verify folder permissions (755 or writable)
3. Check PHP upload settings in `php.ini`:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```
4. Restart Apache after changing `php.ini`

### Session Issues

**Problem:** "User not staying logged in"

**Solutions:**
1. Check session folder permissions
2. Verify cookies are enabled in browser
3. Check `php.ini` session settings:
   ```ini
   session.save_path = "C:\wamp64\tmp"  # Windows
   session.gc_maxlifetime = 3600
   ```

### Location Auto-Detection Not Working

**Problem:** "Cannot detect location"

**Solutions:**
1. Use HTTPS (geolocation requires secure context)
2. Allow location access in browser
3. Fallback: Enter location manually

---

## Configuration Files Reference

### config.php Constants

```php
DB_HOST            # Database host (127.0.0.1)
DB_PORT            # Database port (3306 or 3307)
DB_USER            # Database username (root)
DB_PASS            # Database password
DB_NAME            # Database name (das_student)
DB_CHARSET         # Character set (utf8mb4)
DB_COLLATE         # Collation (utf8mb4_unicode_ci)

SESSION_LIFETIME   # Session duration (3600 = 1 hour)
PASSWORD_MIN_LENGTH # Minimum password length (8)
MAX_LOGIN_ATTEMPTS # Maximum login attempts (5)
LOCKOUT_TIME       # Account lockout time (900 = 15 min)

UPLOAD_DIR         # Upload directory path
MAX_FILE_SIZE      # Max file size (10485760 = 10MB)
ALLOWED_FILE_TYPES # Allowed extensions array
```

### moodle_config.php Constants

```php
MOODLE_DB_HOST           # Moodle database host
MOODLE_DB_PORT           # Moodle database port
MOODLE_DB_NAME           # Moodle database name
MOODLE_URL               # Moodle installation URL
MOODLE_WS_TOKEN          # Web service token
MOODLE_AUTO_SYNC         # Auto-sync enabled (true/false)
MOODLE_STUDENT_ROLE_ID   # Student role ID in Moodle (5)
```

---

## Explaining to Another Chatbot

If you need to continue this project with another AI assistant, provide them with:

1. **This documentation file**
2. **The project structure overview**
3. **Current implementation status:**
   - ✅ Splash screen with animation
   - ✅ Login page with validation
   - ✅ Multi-step registration (4 steps)
   - ✅ Location auto-detection
   - ✅ Moodle API integration
   - ✅ Dashboard with student profile
   - ✅ Database schema with all tables
   - ✅ Security features (password hashing, SQL injection prevention)
   - ⏳ Teacher selection page (pending)
   - ⏳ Teacher request system (pending)

4. **Key context:**
   - Project uses PHP 8.1+, MySQL, vanilla JavaScript
   - Follows branding guidelines (Brandon Grotesque, Campton fonts, specific color palette)
   - Integrates with Moodle LMS via REST API
   - Registration flow: Splash → Login → Register → Dashboard
   - All passwords are hashed with BCrypt
   - Moodle sync happens automatically on registration

---

## Support & Maintenance

### Regular Maintenance Tasks

1. **Database Backup:** Weekly backups of `das_student` database
2. **Log Monitoring:** Check `moodle_sync_log` for sync failures
3. **Upload Folder:** Periodically clean up old showcase files
4. **Session Cleanup:** PHP handles automatically, but monitor disk space
5. **Security Updates:** Keep PHP and MySQL updated

### Performance Optimization

1. Add database indexes if queries slow down
2. Implement caching for teacher lists
3. Optimize image uploads (compression, thumbnails)
4. Monitor Moodle API response times

---

## Credits & License

**Developed for:** Digital Art School  
**Technology:** PHP, MySQL, Moodle LMS  
**Version:** 1.0.0  
**Last Updated:** 2024

---

## Quick Start Checklist

- [ ] Extract files to web server directory
- [ ] Create `das_student` database
- [ ] Import `database_schema.sql`
- [ ] Update `includes/config.php` with database credentials
- [ ] Set `uploads/` folder permissions
- [ ] Configure Moodle web services
- [ ] Update `includes/moodle_config.php` with Moodle token
- [ ] Test connection: http://localhost/digital-art-school
- [ ] Register a test student
- [ ] Verify Moodle sync in phpMyAdmin

**Project is ready when:**
- Splash screen loads and animates
- Login page appears after 2 seconds
- Registration works (all 4 steps)
- Dashboard displays student data
- Moodle shows "Synced" badge (if configured)

---

**End of Documentation**
