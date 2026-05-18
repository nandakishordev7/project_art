<?php
session_start();

// ── Auth guard ────────────────────────────────────────────────────
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';

$student   = null;
$success   = '';
$error     = '';

// ── Handle profile update ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $conn = getDatabaseConnection();
    if ($conn) {
        $fields = [
            'full_name'              => sanitizeInput($_POST['full_name'] ?? ''),
            'age'                    => (int)($_POST['age'] ?? 0),
            'location'               => sanitizeInput($_POST['location'] ?? ''),
            'art_category'           => sanitizeInput($_POST['art_category'] ?? ''),
            'art_discipline'         => sanitizeInput($_POST['art_discipline'] ?? ''),
            'secondary_interest'     => sanitizeInput($_POST['secondary_interest'] ?? ''),
            'skill_level'            => sanitizeInput($_POST['skill_level'] ?? ''),
            'years_of_experience'    => (float)($_POST['years_of_experience'] ?? 0),
            'previous_training'      => sanitizeInput($_POST['previous_training'] ?? ''),
            'learning_purpose'       => sanitizeInput($_POST['learning_purpose'] ?? ''),
            'time_commitment'        => sanitizeInput($_POST['time_commitment'] ?? ''),
            'preferred_schedule'     => sanitizeInput($_POST['preferred_schedule'] ?? ''),
            'specific_goals'         => sanitizeInput($_POST['specific_goals'] ?? ''),
            'physical_constraints'   => sanitizeInput($_POST['physical_constraints'] ?? ''),
            'learning_accommodations'=> sanitizeInput($_POST['learning_accommodations'] ?? ''),
        ];

        // Password change
        $pw_sql = '';
        $pw_param = '';
        if (!empty($_POST['new_password']) && strlen($_POST['new_password']) >= 8) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $pw_hash = hashPassword($_POST['new_password']);
                $pw_sql = ', password_hash = ?';
                $pw_param = $pw_hash;
            } else {
                $error = 'New passwords do not match.';
            }
        }

        if (!$error) {
            $sql = "UPDATE students SET
                full_name=?, age=?, location=?, art_category=?, art_discipline=?,
                secondary_interest=?, skill_level=?, years_of_experience=?,
                previous_training=?, learning_purpose=?, time_commitment=?,
                preferred_schedule=?, specific_goals=?, physical_constraints=?,
                learning_accommodations=?" . $pw_sql . "
                WHERE student_id=?";

            $stmt = $conn->prepare($sql);
            if ($pw_param) {
                $stmt->bind_param("sisssssdsssssss" . "si",
                    $fields['full_name'], $fields['age'], $fields['location'],
                    $fields['art_category'], $fields['art_discipline'],
                    $fields['secondary_interest'], $fields['skill_level'],
                    $fields['years_of_experience'], $fields['previous_training'],
                    $fields['learning_purpose'], $fields['time_commitment'],
                    $fields['preferred_schedule'], $fields['specific_goals'],
                    $fields['physical_constraints'], $fields['learning_accommodations'],
                    $pw_param, $_SESSION['student_id']
                );
            } else {
                $stmt->bind_param("sisssssdsssssss" . "i",
                    $fields['full_name'], $fields['age'], $fields['location'],
                    $fields['art_category'], $fields['art_discipline'],
                    $fields['secondary_interest'], $fields['skill_level'],
                    $fields['years_of_experience'], $fields['previous_training'],
                    $fields['learning_purpose'], $fields['time_commitment'],
                    $fields['preferred_schedule'], $fields['specific_goals'],
                    $fields['physical_constraints'], $fields['learning_accommodations'],
                    $_SESSION['student_id']
                );
            }

            if ($stmt->execute()) {
                $_SESSION['full_name'] = $fields['full_name'];
                $success = 'Profile updated successfully!';
            } else {
                $error = 'Update failed. Please try again.';
            }
            $stmt->close();
        }
        closeDatabaseConnection($conn);
    }
}

// ── Fetch current student record ──────────────────────────────────
$conn = getDatabaseConnection();
if ($conn) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $_SESSION['student_id']);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    closeDatabaseConnection($conn);
}

if (!$student) {
    $student = [
        'full_name' => $_SESSION['full_name'] ?? 'Student',
        'email'     => $_SESSION['email'] ?? '',
        'age'       => '', 'location' => '',
        'art_category' => '', 'art_discipline' => '',
        'secondary_interest' => '', 'skill_level' => '',
        'years_of_experience' => 0, 'previous_training' => '',
        'learning_purpose' => '', 'time_commitment' => '',
        'preferred_schedule' => '', 'specific_goals' => '',
        'physical_constraints' => '', 'learning_accommodations' => '',
        'registration_date' => '',
    ];
}

function getInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $ini = '';
    foreach ($parts as $p) { if ($p) $ini .= strtoupper($p[0]); }
    return substr($ini, 0, 2) ?: 'ST';
}

$initials    = getInitials($student['full_name']);
$firstName   = explode(' ', trim($student['full_name']))[0];
$skillMap    = [
    'complete_beginner' => 'Beginner', 'basic' => 'Basic',
    'intermediate' => 'Intermediate', 'advanced' => 'Advanced',
];
$skillDisplay = $skillMap[$student['skill_level'] ?? ''] ?? ucfirst($student['skill_level'] ?? '');
$purposeMap  = [
    'hobby' => 'Hobby / Personal Enrichment',
    'professional' => 'Professional Development',
    'performance' => 'Performance',
    'certification' => 'Certification',
    'therapy' => 'Therapy / Wellness',
];
$scheduleMap = [
    'weekday_morning' => 'Weekday Morning',
    'weekday_evening' => 'Weekday Evening',
    'weekend_morning' => 'Weekend Morning',
    'weekend_evening' => 'Weekend Evening',
    'weekday_all_day' => 'Weekday (Flexible)',
    'weekend_all_day' => 'Weekend (Flexible)',
];
$regDate = $student['registration_date'] ? date('d M Y', strtotime($student['registration_date'])) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — Digital Art School</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
  /* ============================================================
     CSS VARIABLES — identical to dashboard
  ============================================================ */
  :root {
    --duk-blue:      #0366B0;
    --duk-teal:      #02B393;
    --duk-lime:      #A3CE47;
    --duk-gold:      #F3C73B;
    --duk-slate:     #606060;
    --brass-dark:    #0050a0;
    --brass-light:   #02B393;
    --deepwood:      #0d1f35;
    --coconut-white: #f4faff;
    --palm-green:    #02B393;
    --gradient-warm:   linear-gradient(135deg, #0366B0 0%, #02B393 60%);
    --gradient-active: linear-gradient(135deg, #0366B0 0%, #02B393 100%);
    --gradient-hero:   linear-gradient(135deg, #0376cc 0%, #02c7a2 100%);
    --shadow-soft:   0 8px 32px rgba(3,102,176,0.10);
    --shadow-medium: 0 16px 48px rgba(3,102,176,0.14);
    --font-display:  'Poppins', sans-serif;
    --font-body:     'Poppins', sans-serif;
    --sidebar-width: 80px;
    --header-height: 80px;
    --radius-sm: 12px;
    --radius-md: 24px;
    --radius-lg: 32px;
    --space-sm: 16px;
    --space-md: 24px;
    --space-lg: 32px;
  }

  *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

  body {
    font-family: var(--font-body);
    background: var(--coconut-white);
    color: var(--deepwood);
    overflow-x: hidden;
    min-height: 100vh;
    background-image: radial-gradient(rgba(3,102,176,0.06) 1px, transparent 1px);
    background-size: 24px 24px;
  }
  body::before {
    content:''; position:fixed; inset:0;
    background-image: url('../assets/images/community_portal_bg-52339207.png');
    background-size:contain; background-position:center;
    opacity:0.769; pointer-events:none; z-index:0;
  }
  body::after {
    content:''; position:fixed; inset:0;
    background-image:
      radial-gradient(circle at 15% 40%, rgba(3,102,176,0.04) 0%, transparent 55%),
      radial-gradient(circle at 85% 70%, rgba(2,179,147,0.04) 0%, transparent 55%);
    pointer-events:none; z-index:0;
  }

  /* ============================================================
     GLASS HEADER — identical
  ============================================================ */
  .glass-header {
    position:fixed; top:0; left:0; right:0;
    height:var(--header-height);
    background:rgba(255,255,255,0.92);
    backdrop-filter:blur(20px) saturate(180%);
    box-shadow:0 4px 24px rgba(3,102,176,0.08),0 1px 0 rgba(3,102,176,0.12);
    border-bottom:2px solid transparent; background-clip:padding-box;
    border-image:linear-gradient(90deg,#0366B0,#02B393,#A3CE47) 1;
    display:flex; align-items:center; justify-content:space-between;
    padding:0 28px; z-index:1000;
    animation:slideDown 0.6s cubic-bezier(0.16,1,0.3,1);
  }
  @keyframes slideDown {
    from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1}
  }
  .header-logos{display:flex;align-items:center;gap:14px;flex-shrink:0}
  .logo-block{display:flex;align-items:center;gap:9px;text-decoration:none;padding:6px 10px;border-radius:10px;transition:background 0.2s}
  .logo-block:hover{background:rgba(3,102,176,0.06)}
  .logo-block img{width:120px;height:120px;object-fit:contain}
  .logo-divider{width:1px;height:36px;background:linear-gradient(180deg,transparent,rgba(3,102,176,0.2),transparent);flex-shrink:0}
  .header-right{display:flex;align-items:center;gap:16px;flex-shrink:0}
  .student-identity{display:flex;align-items:center;gap:10px}
  .avatar-ring{width:48px;height:48px;border-radius:20%;background:var(--gradient-warm);padding:3px;flex-shrink:0}
  .avatar-initials{display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-weight:700;font-size:0.85rem;color:white}
  .student-name{font-size:1.05rem;font-weight:700;color:var(--deepwood)}
  .student-role{font-size:0.85rem;color:var(--brass-dark);font-weight:500}
  .student-info{display:flex;flex-direction:column}
  .header-actions{display:flex;gap:8px}
  .icon-btn{width:44px;height:44px;border-radius:10px;background:rgba(255,255,255,0.6);border:1px solid rgba(13,31,53,0.08);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.3s;position:relative}
  .icon-btn:hover{background:white;transform:translateY(-2px);box-shadow:var(--shadow-soft)}

  /* ============================================================
     FLOATING NAV — identical
  ============================================================ */
  .floating-nav {
    position:fixed; left:20px; top:50%; transform:translateY(-50%);
    background:rgba(255,255,255,0.92); backdrop-filter:blur(20px) saturate(180%);
    border-radius:var(--radius-md);
    height:calc(100vh - var(--header-height) - 40px);
    display:flex; flex-direction:column; justify-content:center;
    padding:var(--space-sm);
    box-shadow:var(--shadow-medium),0 0 0 1px rgba(3,102,176,0.08);
    z-index:999;
    animation:slideInLeft 0.6s cubic-bezier(0.16,1,0.3,1) 0.2s both;
  }
  @keyframes slideInLeft{from{transform:translateX(-100%) translateY(-50%);opacity:0}to{transform:translateX(0) translateY(-50%);opacity:1}}
  .nav-items{display:flex;flex-direction:column;gap:8px}
  .nav-item{width:56px;height:56px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;color:var(--brass-dark);text-decoration:none;position:relative;transition:all 0.3s;overflow:visible}
  .nav-item::before{content:attr(data-label);position:absolute;left:72px;background:var(--deepwood);color:var(--coconut-white);padding:8px 16px;border-radius:var(--radius-sm);font-size:0.85rem;font-weight:500;white-space:nowrap;opacity:0;pointer-events:none;transform:translateX(-8px);transition:all 0.3s}
  .nav-item:hover::before{opacity:1;transform:translateX(0)}
  .nav-item:hover{background:rgba(3,102,176,0.08);color:var(--duk-blue)}
  .nav-item.active{background:var(--gradient-active);color:white;box-shadow:0 4px 16px rgba(3,102,176,0.35)}
  .nav-item svg{transition:transform 0.3s}
  .nav-item:hover svg{transform:scale(1.1)}
  .nav-logout svg{stroke:rgba(199,74,60,0.65)}
  .nav-logout:hover{background:rgba(199,74,60,0.08) !important}
  .nav-logout:hover svg{stroke:#c74a3c}

  /* ============================================================
     PAGE LAYOUT
  ============================================================ */
  .profile-container {
    margin-left: calc(var(--sidebar-width) + 40px);
    margin-top: calc(var(--header-height) + 24px);
    margin-right: 24px;
    margin-bottom: 48px;
    padding: var(--space-md);
    position: relative; z-index: 1;
    max-width: 1200px;
    animation: fadeInScale 0.5s cubic-bezier(0.16,1,0.3,1) 0.2s both;
  }
  @keyframes fadeInScale {
    from{opacity:0;transform:scale(0.98)} to{opacity:1;transform:scale(1)}
  }

  /* ── BACK BREADCRUMB ── */
  .breadcrumb {
    display:flex; align-items:center; gap:8px; margin-bottom:22px;
    font-size:0.82rem; color:var(--brass-dark);
  }
  .breadcrumb a { color:var(--duk-blue); text-decoration:none; font-weight:600; }
  .breadcrumb a:hover { text-decoration:underline; }
  .breadcrumb svg { color:var(--duk-slate); }

  /* ── TWO COLUMN LAYOUT ── */
  .profile-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 24px;
    align-items: start;
  }

  /* ── LEFT: IDENTITY CARD ── */
  .identity-card {
    background: white;
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-soft);
    border: 1px solid rgba(3,102,176,0.10);
    position: sticky;
    top: calc(var(--header-height) + 24px);
  }

  .id-hero {
    background: var(--gradient-hero);
    padding: 32px 24px 24px;
    color: white; text-align: center;
    position: relative;
  }
  .id-hero::after {
    content:''; position:absolute; width:180px; height:180px; border-radius:50%;
    border:1.5px solid rgba(255,255,255,0.10); bottom:-50px; right:-50px;
  }
  .id-avatar-wrap {
    display:inline-flex; align-items:center; justify-content:center;
    width:80px; height:80px; border-radius:22px;
    background:rgba(255,255,255,0.22); border:3px solid rgba(255,255,255,0.35);
    font-weight:700; font-size:1.4rem; color:white; margin-bottom:14px;
  }
  .id-name  { font-size:1.15rem; font-weight:700; margin-bottom:4px; }
  .id-email { font-size:0.75rem; opacity:0.78; margin-bottom:12px; word-break:break-all; }
  .id-chips { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; }
  .id-chip {
    font-size:0.68rem; font-weight:600; padding:3px 11px; border-radius:20px;
    background:rgba(255,255,255,0.16); color:white;
  }

  .id-stats {
    display:flex; border-top:1px solid rgba(3,102,176,0.08);
  }
  .id-stat {
    flex:1; padding:14px 8px; text-align:center;
    border-right:1px solid rgba(3,102,176,0.08);
  }
  .id-stat:last-child { border-right:none; }
  .id-stat-val { font-size:1rem; font-weight:700; color:var(--duk-blue); display:block; }
  .id-stat-lbl { font-size:0.62rem; text-transform:uppercase; letter-spacing:0.8px; color:var(--duk-slate); margin-top:2px; }

  .id-meta { padding:18px; }
  .id-meta-row {
    display:flex; align-items:center; gap:9px; padding:8px 0;
    border-bottom:1px solid rgba(3,102,176,0.06); font-size:0.82rem; color:var(--deepwood);
  }
  .id-meta-row:last-child { border-bottom:none; }
  .id-meta-row svg { color:var(--duk-teal); flex-shrink:0; }
  .id-meta-lbl { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--duk-slate); display:block; margin-bottom:1px; }

  /* ── RIGHT: FORM SECTIONS ── */
  .profile-form-wrap { display:flex; flex-direction:column; gap:20px; }

  /* Alert banners */
  .alert {
    display:flex; align-items:flex-start; gap:10px; padding:13px 16px;
    border-radius:var(--radius-sm); font-size:0.85rem; margin-bottom:4px;
  }
  .alert-success { background:rgba(2,179,147,0.09); color:#016b59; border:1px solid rgba(2,179,147,0.22); }
  .alert-error   { background:rgba(220,53,69,0.07);  color:#b02030; border:1px solid rgba(220,53,69,0.18); }
  .alert svg { flex-shrink:0; margin-top:1px; }

  /* Section card */
  .section-card {
    background: white;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-soft);
    border: 1px solid rgba(3,102,176,0.10);
    overflow: hidden;
    transition: box-shadow 0.2s;
  }
  .section-card:hover { box-shadow: 0 12px 40px rgba(3,102,176,0.12); }

  .section-head {
    padding: 18px 24px;
    border-bottom: 1px solid rgba(3,102,176,0.08);
    display: flex; align-items: center; gap: 10px;
    background: rgba(3,102,176,0.02);
  }
  .section-head-icon {
    width:34px; height:34px; border-radius:10px;
    background:var(--gradient-active); display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
  }
  .section-title { font-weight:700; font-size:0.92rem; color:var(--deepwood); }
  .section-sub   { font-size:0.75rem; color:var(--duk-slate); margin-top:1px; }

  .section-body { padding:24px; }

  /* Field grid */
  .field-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px 20px; }
  .span2 { grid-column:1/span 2; }

  .field { display:flex; flex-direction:column; gap:5px; }
  .field label {
    font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.9px;
    color:var(--duk-slate);
  }
  .field label .req { color:#e74c3c; margin-left:2px; }

  .field input, .field select, .field textarea {
    padding:10px 13px;
    border:1.5px solid rgba(3,102,176,0.16);
    border-radius:var(--radius-sm);
    font-family:var(--font-body); font-size:0.9rem; color:var(--deepwood);
    background:rgba(244,250,255,0.6); outline:none;
    transition:border-color 0.2s,box-shadow 0.2s; width:100%;
  }
  .field input:focus, .field select:focus, .field textarea:focus {
    border-color:var(--duk-teal); box-shadow:0 0 0 4px rgba(2,179,147,0.1);
    background: white;
  }
  .field textarea { resize:vertical; min-height:80px; }
  .field input[readonly] { background:rgba(3,102,176,0.04); color:var(--duk-slate); cursor:not-allowed; }

  /* Password toggle */
  .pw-field { position:relative; }
  .pw-field input { padding-right:44px; }
  .pw-toggle {
    position:absolute; right:13px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; color:var(--duk-slate); padding:0;
    display:flex; align-items:center;
  }
  .pw-toggle:hover { color:var(--duk-blue); }

  /* Save button */
  .form-actions {
    padding:18px 24px; border-top:1px solid rgba(3,102,176,0.08);
    display:flex; align-items:center; justify-content:space-between;
    background:rgba(3,102,176,0.02);
  }
  .btn-save {
    padding:11px 28px; background:var(--gradient-active); color:white; border:none;
    border-radius:var(--radius-sm); font-family:var(--font-display); font-size:0.9rem;
    font-weight:700; cursor:pointer; display:flex; align-items:center; gap:8px;
    transition:opacity 0.2s,transform 0.15s;
    box-shadow:0 4px 14px rgba(3,102,176,0.22);
  }
  .btn-save:hover { opacity:0.9; transform:translateY(-2px); }
  .btn-save:active { transform:translateY(0); }
  .form-actions-hint { font-size:0.75rem; color:var(--duk-slate); }

  /* Profile menu (reused from dashboard) */
  .profile-menu {
    position:fixed; width:230px; background:white; border-radius:14px;
    box-shadow:0 20px 60px rgba(3,102,176,0.14); border:1px solid rgba(3,102,176,0.1);
    z-index:9999; opacity:0; transform:translateY(-8px) scale(0.97); pointer-events:none;
    transition:all 0.22s cubic-bezier(0.16,1,0.3,1); overflow:hidden;
  }
  .profile-menu.open{opacity:1;transform:translateY(0) scale(1);pointer-events:auto}
  .pm-head{display:flex;align-items:center;gap:11px;padding:15px 16px;background:linear-gradient(135deg,rgba(3,102,176,0.08),rgba(2,179,147,0.05));border-bottom:1px solid rgba(3,102,176,0.08)}
  .pm-avatar{width:38px;height:38px;border-radius:10px;background:var(--gradient-active);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;color:white;flex-shrink:0}
  .pm-name{font-weight:700;font-size:0.88rem;color:var(--deepwood)}
  .pm-role{font-size:0.72rem;color:#606060;margin-top:1px}
  .pm-items{padding:6px 0}
  .pm-item{display:flex;align-items:center;gap:9px;padding:10px 16px;font-size:0.86rem;color:var(--deepwood);text-decoration:none;cursor:pointer;transition:background 0.15s}
  .pm-item:hover{background:rgba(3,102,176,0.05)}
  .pm-item.danger{color:#c74a3c}
  .pm-item.danger:hover{background:rgba(199,74,60,0.06)}
  .pm-divider{height:1px;background:rgba(3,102,176,0.07);margin:5px 0}

  /* Responsive */
  @media (max-width:1000px) {
    .profile-grid{grid-template-columns:1fr}
    .identity-card{position:static}
  }
  @media (max-width:768px) {
    .floating-nav{left:10px}
    .profile-container{margin-left:80px;margin-right:12px}
    .field-grid{grid-template-columns:1fr}
    .span2{grid-column:1}
  }
  </style>
</head>
<body>

<!-- ================================================================
     GLASS HEADER — identical to dashboard
================================================================ -->
<header class="glass-header">
  <div class="header-logos">
    <a href="#" class="logo-block" title="Digital University Kerala">
      <img src="../assets/images/DUK Logo.png" alt="DUK Logo" onerror="this.style.display='none'">
    </a>
    <div class="logo-divider"></div>
    <a href="student-dashboard.php" class="logo-block" title="Digital Art School">
      <img src="../assets/images/cdtc_logo.png" alt="Digital Art School Logo" onerror="this.style.display='none'">
    </a>
  </div>

  <div class="header-right">
    <div class="student-identity">
      <div class="avatar-ring">
        <div class="avatar-initials"><?php echo htmlspecialchars($initials); ?></div>
      </div>
      <div class="student-info">
        <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
        <div class="student-role"><?php echo htmlspecialchars($student['art_discipline'] ?: $student['art_category'] ?: 'Art Student'); ?></div>
      </div>
    </div>
    <div class="header-actions">
      <button class="icon-btn" id="profileBtn" aria-label="Profile">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
      </button>
    </div>
  </div>
</header>

<!-- ================================================================
     FLOATING NAV — identical to dashboard, profile active
================================================================ -->
<nav class="floating-nav">
  <div class="nav-items">
    <a href="student-dashboard.php" class="nav-item" data-label="Dashboard">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
      </svg>
    </a>
    <a href="student-schedule.php" class="nav-item" data-label="My Schedule">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
    </a>
    <a href="student-courses.php" class="nav-item" data-label="My Courses">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
      </svg>
    </a>
    <a href="student-progress.php" class="nav-item" data-label="My Progress">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
      </svg>
    </a>
    <a href="student-profile.php" class="nav-item active" data-label="My Profile">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
      </svg>
    </a>
    <a href="student-settings.php" class="nav-item" data-label="Settings">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
      </svg>
    </a>
    <a href="#" class="nav-item nav-logout" id="logoutBtn" data-label="Log Out">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
    </a>
  </div>
</nav>

<!-- ================================================================
     PROFILE PAGE BODY
================================================================ -->
<div class="profile-container">

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <a href="student-dashboard.php">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
      </svg>
      Dashboard
    </a>
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    My Profile
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom:16px">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    <?php echo htmlspecialchars($success); ?>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error" style="margin-bottom:16px">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>

  <div class="profile-grid">

    <!-- LEFT: Identity Card (read-only summary) -->
    <div class="identity-card">
      <div class="id-hero">
        <div class="id-avatar-wrap"><?php echo htmlspecialchars($initials); ?></div>
        <div class="id-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
        <div class="id-email"><?php echo htmlspecialchars($student['email']); ?></div>
        <div class="id-chips">
          <?php if (!empty($student['art_discipline'])): ?>
            <span class="id-chip"><?php echo htmlspecialchars($student['art_discipline']); ?></span>
          <?php elseif (!empty($student['art_category'])): ?>
            <span class="id-chip"><?php echo htmlspecialchars($student['art_category']); ?></span>
          <?php endif; ?>
          <?php if ($skillDisplay): ?>
            <span class="id-chip"><?php echo htmlspecialchars($skillDisplay); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="id-stats">
        <div class="id-stat">
          <span class="id-stat-val"><?php echo number_format((float)($student['years_of_experience'] ?? 0), 0); ?>y</span>
          <span class="id-stat-lbl">Experience</span>
        </div>
        <div class="id-stat">
          <span class="id-stat-val"><?php echo $skillDisplay ?: '—'; ?></span>
          <span class="id-stat-lbl">Level</span>
        </div>
        <div class="id-stat">
          <span class="id-stat-val"><?php echo $regDate; ?></span>
          <span class="id-stat-lbl">Joined</span>
        </div>
      </div>

      <div class="id-meta">
        <?php if (!empty($student['location'])): ?>
        <div class="id-meta-row">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
          </svg>
          <div>
            <span class="id-meta-lbl">Location</span>
            <?php echo htmlspecialchars($student['location']); ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($student['learning_purpose'])): ?>
        <div class="id-meta-row">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          <div>
            <span class="id-meta-lbl">Learning Purpose</span>
            <?php echo htmlspecialchars($purposeMap[$student['learning_purpose']] ?? ucfirst($student['learning_purpose'])); ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($student['preferred_schedule'])): ?>
        <div class="id-meta-row">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
          <div>
            <span class="id-meta-lbl">Schedule</span>
            <?php echo htmlspecialchars($scheduleMap[$student['preferred_schedule']] ?? $student['preferred_schedule']); ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($student['time_commitment'])): ?>
        <div class="id-meta-row">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>
            <line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/>
          </svg>
          <div>
            <span class="id-meta-lbl">Time Commitment</span>
            <?php echo htmlspecialchars($student['time_commitment']); ?> hrs/week
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: Editable form sections -->
    <form method="POST" action="" class="profile-form-wrap" id="profileForm">

      <!-- ── SECTION 1: Account Details ── -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-head-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
          <div>
            <div class="section-title">Account Details</div>
            <div class="section-sub">Basic personal information</div>
          </div>
        </div>
        <div class="section-body">
          <div class="field-grid">
            <div class="field span2">
              <label>Full Name <span class="req">*</span></label>
              <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
            </div>
            <div class="field">
              <label>Email Address</label>
              <input type="email" value="<?php echo htmlspecialchars($student['email']); ?>" readonly>
            </div>
            <div class="field">
              <label>Age</label>
              <input type="number" name="age" min="5" max="100" value="<?php echo htmlspecialchars($student['age'] ?? ''); ?>" placeholder="Your age">
            </div>
            <div class="field span2">
              <label>Location</label>
              <input type="text" name="location" id="locationInput" value="<?php echo htmlspecialchars($student['location'] ?? ''); ?>" placeholder="City, Country">
              <button type="button" style="margin-top:6px;display:inline-flex;align-items:center;gap:6px;padding:6px 11px;background:rgba(3,102,176,0.06);border:1.5px solid rgba(3,102,176,0.18);border-radius:8px;font-family:var(--font-body);font-size:0.76rem;font-weight:600;color:var(--duk-blue);cursor:pointer;" onclick="detectLocation()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                Detect my location
              </button>
            </div>
          </div>
        </div>
        <div class="form-actions">
          <span class="form-actions-hint">Changes save across all sections</span>
          <button type="submit" name="update_profile" class="btn-save">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Save Changes
          </button>
        </div>
      </div>

      <!-- ── SECTION 2: Art & Interests ── -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-head-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
          </div>
          <div>
            <div class="section-title">Art &amp; Interests</div>
            <div class="section-sub">Your chosen art form and disciplines</div>
          </div>
        </div>
        <div class="section-body">
          <div class="field-grid">
            <div class="field">
              <label>Art Category</label>
              <select name="art_category" id="artCategory" onchange="updateDisciplines()">
                <option value="">Select category…</option>
                <?php
                $cats = ['Classical Dance','Vocal Music','Instrumental Music','Visual Arts','Theatre','Martial Arts'];
                foreach ($cats as $c):
                  $sel = ($student['art_category'] ?? '') === $c ? 'selected' : '';
                ?>
                <option value="<?php echo $c; ?>" <?php echo $sel; ?>><?php echo $c; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Art Discipline</label>
              <select name="art_discipline" id="artDiscipline">
                <option value="<?php echo htmlspecialchars($student['art_discipline'] ?? ''); ?>">
                  <?php echo htmlspecialchars($student['art_discipline'] ?: 'Select category first…'); ?>
                </option>
              </select>
            </div>
            <div class="field span2">
              <label>Secondary Interest</label>
              <input type="text" name="secondary_interest" value="<?php echo htmlspecialchars($student['secondary_interest'] ?? ''); ?>" placeholder="Any other art forms you're curious about">
            </div>
          </div>
        </div>
        <div class="form-actions">
          <span class="form-actions-hint"></span>
          <button type="submit" name="update_profile" class="btn-save">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Save Changes
          </button>
        </div>
      </div>

      <!-- ── SECTION 3: Experience & Background ── -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-head-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z"/>
            </svg>
          </div>
          <div>
            <div class="section-title">Experience &amp; Background</div>
            <div class="section-sub">Your training history and skill level</div>
          </div>
        </div>
        <div class="section-body">
          <div class="field-grid">
            <div class="field">
              <label>Skill Level</label>
              <select name="skill_level">
                <option value="">Select level…</option>
                <?php
                $levels = ['complete_beginner'=>'Complete Beginner','basic'=>'Basic','intermediate'=>'Intermediate','advanced'=>'Advanced'];
                foreach ($levels as $v => $l):
                  $sel = ($student['skill_level'] ?? '') === $v ? 'selected' : '';
                ?>
                <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Years of Experience</label>
              <input type="number" name="years_of_experience" min="0" max="50" step="0.5"
                     value="<?php echo htmlspecialchars($student['years_of_experience'] ?? '0'); ?>">
            </div>
            <div class="field span2">
              <label>Previous Training</label>
              <textarea name="previous_training" placeholder="Describe any previous training or teachers you've studied with…"><?php echo htmlspecialchars($student['previous_training'] ?? ''); ?></textarea>
            </div>
            <?php if (!empty($student['showcase_file'])): ?>
            <div class="field span2">
              <label>Showcase File</label>
              <div style="padding:10px 14px;background:rgba(2,179,147,0.06);border:1.5px solid rgba(2,179,147,0.2);border-radius:var(--radius-sm);font-size:0.84rem;color:var(--deepwood);display:flex;align-items:center;gap:9px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--duk-teal)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?php echo htmlspecialchars($student['showcase_file']); ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="form-actions">
          <span class="form-actions-hint"></span>
          <button type="submit" name="update_profile" class="btn-save">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Save Changes
          </button>
        </div>
      </div>

      <!-- ── SECTION 4: Learning Goals ── -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-head-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <div>
            <div class="section-title">Learning Goals &amp; Schedule</div>
            <div class="section-sub">Your purpose, availability, and aspirations</div>
          </div>
        </div>
        <div class="section-body">
          <div class="field-grid">
            <div class="field">
              <label>Learning Purpose</label>
              <select name="learning_purpose">
                <option value="">Select purpose…</option>
                <?php
                foreach ($purposeMap as $v => $l):
                  $sel = ($student['learning_purpose'] ?? '') === $v ? 'selected' : '';
                ?>
                <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Time Commitment (hrs/week)</label>
              <input type="text" name="time_commitment" value="<?php echo htmlspecialchars($student['time_commitment'] ?? ''); ?>" placeholder="e.g. 5–7 hours">
            </div>
            <div class="field span2">
              <label>Preferred Schedule</label>
              <select name="preferred_schedule">
                <option value="">Select schedule…</option>
                <?php
                foreach ($scheduleMap as $v => $l):
                  $sel = ($student['preferred_schedule'] ?? '') === $v ? 'selected' : '';
                ?>
                <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field span2">
              <label>Specific Goals</label>
              <textarea name="specific_goals" placeholder="What do you hope to achieve?"><?php echo htmlspecialchars($student['specific_goals'] ?? ''); ?></textarea>
            </div>
          </div>
        </div>
        <div class="form-actions">
          <span class="form-actions-hint"></span>
          <button type="submit" name="update_profile" class="btn-save">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Save Changes
          </button>
        </div>
      </div>

      <!-- ── SECTION 5: Health & Accessibility ── -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-head-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
          </div>
          <div>
            <div class="section-title">Health &amp; Accessibility</div>
            <div class="section-sub">Optional — helps your teacher tailor sessions for you</div>
          </div>
        </div>
        <div class="section-body">
          <div class="field-grid">
            <div class="field span2">
              <label>Physical Constraints</label>
              <textarea name="physical_constraints" placeholder="Any physical limitations your instructor should know about…"><?php echo htmlspecialchars($student['physical_constraints'] ?? ''); ?></textarea>
            </div>
            <div class="field span2">
              <label>Learning Accommodations</label>
              <textarea name="learning_accommodations" placeholder="Any specific learning needs or preferences…"><?php echo htmlspecialchars($student['learning_accommodations'] ?? ''); ?></textarea>
            </div>
          </div>
        </div>
        <div class="form-actions">
          <span class="form-actions-hint"></span>
          <button type="submit" name="update_profile" class="btn-save">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Save Changes
          </button>
        </div>
      </div>

      <!-- ── SECTION 6: Change Password ── -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-head-icon" style="background:linear-gradient(135deg,#606060,#0d1f35);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </div>
          <div>
            <div class="section-title">Change Password</div>
            <div class="section-sub">Leave blank to keep your current password</div>
          </div>
        </div>
        <div class="section-body">
          <div class="field-grid">
            <div class="field">
              <label>New Password</label>
              <div class="pw-field">
                <input type="password" name="new_password" id="newPw" placeholder="Min. 8 characters" autocomplete="new-password">
                <button type="button" class="pw-toggle" onclick="togglePw('newPw',this)">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="field">
              <label>Confirm New Password</label>
              <div class="pw-field">
                <input type="password" name="confirm_password" id="confirmPw" placeholder="Re-enter new password">
                <button type="button" class="pw-toggle" onclick="togglePw('confirmPw',this)">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="form-actions">
          <span class="form-actions-hint">Min. 8 characters required</span>
          <button type="submit" name="update_profile" class="btn-save" style="background:linear-gradient(135deg,#606060,#0d1f35);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Update Password
          </button>
        </div>
      </div>

    </form>
  </div><!-- /profile-grid -->
</div><!-- /profile-container -->

<!-- Profile dropdown -->
<div class="profile-menu" id="profileMenu">
  <div class="pm-head">
    <div class="pm-avatar"><?php echo htmlspecialchars($initials); ?></div>
    <div>
      <div class="pm-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
      <div class="pm-role"><?php echo htmlspecialchars($student['art_discipline'] ?: 'Art Student'); ?></div>
    </div>
  </div>
  <div class="pm-items">
    <a href="student-dashboard.php" class="pm-item">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <div class="pm-divider"></div>
    <a href="#" class="pm-item danger" id="pmLogout">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </a>
  </div>
</div>

<script>
(function(){
  'use strict';

  // ── Discipline map ─────────────────────────────────────────────
  const disciplines = {
    'Classical Dance':     ['Bharatanatyam','Kathakali','Odissi','Kuchipudi','Mohiniyattam','Manipuri','Kathak'],
    'Vocal Music':         ['Carnatic Vocal','Hindustani Vocal','Dhrupad','Light Classical'],
    'Instrumental Music':  ['Tabla','Mridangam','Veena','Sitar','Violin','Flute','Harmonium'],
    'Visual Arts':         ['Painting','Sculpture','Pottery','Printmaking','Mural Art'],
    'Theatre':             ['Classical Theatre','Contemporary Theatre','Mime & Movement','Puppetry'],
    'Martial Arts':        ['Kalaripayattu','Silambam','Mardani Khel'],
  };

  const currentDiscipline = <?php echo json_encode($student['art_discipline'] ?? ''); ?>;

  window.updateDisciplines = function() {
    const cat = document.getElementById('artCategory').value;
    const sel = document.getElementById('artDiscipline');
    sel.innerHTML = '<option value="">Select discipline…</option>';
    (disciplines[cat] || []).forEach(d => {
      const o = document.createElement('option');
      o.value = d; o.textContent = d;
      if (d === currentDiscipline) o.selected = true;
      sel.appendChild(o);
    });
  };

  // Populate on load
  updateDisciplines();

  // ── Geolocation ────────────────────────────────────────────────
  window.detectLocation = function() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(pos => {
      fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+pos.coords.latitude+'&lon='+pos.coords.longitude)
        .then(r => r.json())
        .then(d => {
          const city    = d.address.city || d.address.town || d.address.village || '';
          const country = d.address.country || '';
          document.getElementById('locationInput').value = [city,country].filter(Boolean).join(', ');
        });
    });
  };

  // ── Password visibility toggle ─────────────────────────────────
  window.togglePw = function(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (input.type === 'password') {
      input.type = 'text';
      btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    } else {
      input.type = 'password';
      btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
  };

  // ── Profile menu toggle ────────────────────────────────────────
  const profileBtn  = document.getElementById('profileBtn');
  const profileMenu = document.getElementById('profileMenu');
  profileBtn?.addEventListener('click', e => {
    e.stopPropagation();
    const open = profileMenu.classList.contains('open');
    profileMenu.classList.toggle('open', !open);
    if (!open) {
      const r = profileBtn.getBoundingClientRect();
      profileMenu.style.top   = (r.bottom + 8) + 'px';
      profileMenu.style.right = (window.innerWidth - r.right) + 'px';
    }
  });
  document.addEventListener('click', () => profileMenu.classList.remove('open'));

  // ── Logout ─────────────────────────────────────────────────────
  ['logoutBtn','pmLogout'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', e => {
      e.preventDefault();
      if (confirm('Log out of Digital Art School?')) {
        window.location.href = '../pages/logout.php';
      }
    });
  });

  // ── Auto-scroll to success/error ──────────────────────────────
  const alertEl = document.querySelector('.alert');
  if (alertEl) alertEl.scrollIntoView({ behavior:'smooth', block:'center' });

})();
</script>

</body>
</html>
