# Digital Art School - Student Module

A comprehensive student registration and management system with Moodle LMS integration.

## 🚀 Quick Setup

### 1. Prerequisites
- WAMP/XAMPP/LAMP server
- PHP 8.1+ 
- MySQL 8.0+ (Port 3306) or MariaDB 10.x (Port 3307)
- Moodle LMS (optional, for integration)

### 2. Installation Steps

1. **Extract** the project to your web root:
   ```
   C:\wamp64\www\digital-art-school  (Windows)
   /var/www/html/digital-art-school  (Linux)
   ```

2. **Create Database**:
   - Open phpMyAdmin
   - Create database: `das_student`
   - Import: `database_schema.sql`

3. **Configure**:
   - Edit `includes/config.php`:
     ```php
     define('DB_PASS', 'your_mysql_password');
     ```

4. **Access**:
   - Navigate to: `http://localhost/digital-art-school`

## ✨ Features

- **Elegant Splash Screen** - 2-second animated brand introduction
- **Secure Authentication** - BCrypt password hashing, session management
- **Multi-Step Registration** - 4-step guided registration process
- **Location Auto-Detection** - Browser geolocation API integration
- **Moodle Integration** - Automatic user sync via REST API
- **Responsive Dashboard** - Clean student profile interface

## 📋 Registration Fields

### Step 1: Basic Info
- Full Name, Email, Password, Age, Location

### Step 2: Art Selection
- Art Category (Classical Dance, Vocal Music, etc.)
- Art Discipline (dynamic based on category)
- Secondary Interests

### Step 3: Experience
- Skill Level (Beginner to Advanced)
- Years of Experience
- Previous Training
- Showcase File Upload

### Step 4: Goals
- Learning Purpose
- Time Commitment
- Preferred Schedule
- Specific Goals
- Health/Accessibility (optional)

## 🎨 Design

Based on Digital University Kerala branding guidelines:
- **Fonts**: Brandon Grotesque (headings), Campton (body)
- **Colors**: 
  - Primary: #0366B0 (Blue), #02B393 (Teal), #A3CE47 (Green)
  - Accent: #F3C73B (Yellow), #606060 (Gray)

## 🔐 Security

- Password hashing (BCrypt, cost 12)
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- Session security (regeneration, timeout)
- File upload validation

## 🔗 Moodle Integration

### Setup (Optional)
1. Enable Web Services in Moodle
2. Create API user and generate token
3. Update `includes/moodle_config.php`:
   ```php
   define('MOODLE_URL', 'http://localhost/moodle');
   define('MOODLE_WS_TOKEN', 'your_token_here');
   ```

Registered students automatically sync to Moodle!

## 📂 Project Structure

```
digital-art-school/
├── index.html              # Entry point (splash screen)
├── database_schema.sql     # Database schema
├── assets/
│   ├── css/               # Stylesheets
│   └── js/                # JavaScript
├── includes/
│   ├── config.php         # DB configuration
│   ├── moodle_config.php  # Moodle settings
│   └── moodle_api_helper.php
├── pages/
│   ├── login.php          # Login page
│   ├── register.php       # Registration
│   ├── dashboard.php      # Student dashboard
│   └── logout.php         # Logout
└── uploads/               # Showcase files
```

## 🐛 Troubleshooting

**Database Error?**
- Check MySQL is running
- Verify password in `includes/config.php`
- Ensure `das_student` database exists

**Moodle Not Syncing?**
- Check Moodle is running
- Verify web service token
- Check `moodle_sync_log` table for errors

**File Upload Failing?**
- Check `uploads/` folder permissions (755)
- Verify PHP upload settings (10MB limit)

## 📖 Documentation

For detailed setup and configuration, see:
- **SETUP_DOCUMENTATION.md** - Complete setup guide
- Includes Moodle integration, API details, security info

## 🎯 User Flow

1. **Splash Screen** (2s animation)
2. **Login** (or click "Register")
3. **Registration** (4-step process)
4. **Moodle Sync** (automatic)
5. **Dashboard** (student profile)

## 🔧 Configuration Files

### config.php
- Database credentials
- Session settings
- Upload configuration
- Security parameters

### moodle_config.php
- Moodle database settings
- API URL and token
- Integration options

## 📝 Database Tables

- `students` - Student registration data
- `teachers` - Teacher information (5 pre-loaded)
- `teacher_requests` - Student-teacher requests
- `session_logs` - Login tracking
- `moodle_sync_log` - API sync logs

## 🎓 Sample Teachers (Pre-loaded)

1. Dr. Lakshmi Nair - Classical Dance
2. Prof. Ravi Kumar - Vocal Music
3. Smt. Priya Menon - Visual Arts
4. Sri. Anand Krishnan - Classical Dance
5. Smt. Geetha Sharma - Vocal Music

## 🚧 Development Status

- ✅ Splash screen with brand animation
- ✅ Login/logout system
- ✅ Multi-step registration
- ✅ Moodle API integration
- ✅ Student dashboard
- ⏳ Teacher selection (planned)
- ⏳ Teacher request system (planned)

## 💡 Tips

- Use `127.0.0.1` instead of `localhost` for stability
- Default password min length: 8 characters
- Session timeout: 1 hour
- Max file upload: 10MB
- Supports: JPG, PNG, PDF, MP4, MOV, AVI

## 📞 Support

Check the logs:
- PHP errors: `error.log`
- Moodle sync: `moodle_sync_log` table in database

## 📜 License

Developed for Digital Art School educational purposes.

---

**Version:** 1.0.0  
**Last Updated:** May 2024  
**PHP Version:** 8.1+  
**Database:** MySQL 8.0+ / MariaDB 10.x
