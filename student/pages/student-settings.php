<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';

$student = null;
$conn = getDatabaseConnection();
if ($conn) {
    $stmt = $conn->prepare("
        SELECT s.*, tr.request_status, tr.teacher_id AS assigned_teacher_id,
               t.teacher_name, t.specialization AS teacher_specialization,
               t.teacher_id
        FROM students s
        LEFT JOIN teacher_requests tr
            ON tr.student_id = s.student_id AND tr.request_status = 'accepted'
        LEFT JOIN teachers t ON t.teacher_id = tr.teacher_id
        WHERE s.student_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $_SESSION['student_id']);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    closeDatabaseConnection($conn);
}

if (!$student) {
    $student = [
        'full_name' => $_SESSION['full_name'] ?? 'Student',
        'email'     => $_SESSION['email']     ?? '',
        'art_category'   => '',
        'art_discipline' => '',
        'skill_level'    => '',
        'location'       => '',
        'years_of_experience' => 0,
        'teacher_name'   => null,
        'request_status' => null,
        'preferred_schedule' => '',
        'learning_purpose'   => '',
    ];
}

function getInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $ini = '';
    foreach ($parts as $p) { if ($p) $ini .= strtoupper($p[0]); }
    return substr($ini, 0, 2) ?: 'ST';
}

$skillMap = [
    'complete_beginner' => 'Beginner',
    'basic'       => 'Basic',
    'intermediate'=> 'Intermediate',
    'advanced'    => 'Advanced',
];
$skillDisplay = $skillMap[$student['skill_level'] ?? ''] ?? ucfirst($student['skill_level'] ?? 'Beginner');
$initials     = getInitials($student['full_name']);
$firstName    = explode(' ', trim($student['full_name']))[0];
$hasTeacher   = !empty($student['teacher_name']);
$activeTab    = $_GET['tab'] ?? 'appearance';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings — Digital Art School</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,500&display=swap" rel="stylesheet">
  <style>
  /* ═══════════════════════════════════════════════════════════
     THEME TOKENS — light & dark
  ═══════════════════════════════════════════════════════════ */
  :root {
    --blue:   #0366B0;
    --teal:   #02B393;
    --lime:   #A3CE47;
    --gold:   #F3C73B;
    --slate:  #606060;

    /* Light theme defaults */
    --bg:          #f4faff;
    --bg2:         #ffffff;
    --bg3:         rgba(244,250,255,0.9);
    --surface:     #ffffff;
    --surface2:    rgba(244,250,255,0.7);
    --border:      rgba(3,102,176,0.10);
    --border2:     rgba(3,102,176,0.18);
    --text:        #0d1f35;
    --text2:       #4d6b85;
    --text3:       #8da8be;
    --shadow:      0 8px 32px rgba(3,102,176,0.10);
    --shadow-md:   0 16px 48px rgba(3,102,176,0.14);
    --header-bg:   rgba(255,255,255,0.94);
    --nav-bg:      rgba(255,255,255,0.93);
    --card-bg:     #ffffff;
    --locked-bg:   rgba(244,250,255,0.5);
    --locked-text: rgba(13,31,53,0.35);
    --g-warm:      linear-gradient(135deg,#0366B0 0%,#02B393 60%,#606060 100%);
    --g-hero:      linear-gradient(135deg,#0366B0 0%,#02B393 50%,#606060 100%);
    --g-main:      linear-gradient(135deg,#0366B0 0%,#02B393 100%);
    --font:        'Poppins', sans-serif;
    --font-serif:  'Playfair Display', Georgia, serif;
    --nav-w:  80px;
    --hdr-h:  80px;
    --r-sm:   12px;
    --r-md:   20px;
    --r-lg:   28px;
  }

  [data-theme="dark"] {
    --bg:          #0a1628;
    --bg2:         #0f1e30;
    --bg3:         rgba(15,30,48,0.95);
    --surface:     #132236;
    --surface2:    rgba(19,34,54,0.7);
    --border:      rgba(2,179,147,0.12);
    --border2:     rgba(2,179,147,0.22);
    --text:        #e8f4ff;
    --text2:       #8bb8d4;
    --text3:       #4a7a9b;
    --shadow:      0 8px 32px rgba(0,0,0,0.35);
    --shadow-md:   0 16px 48px rgba(0,0,0,0.4);
    --header-bg:   rgba(10,22,40,0.96);
    --nav-bg:      rgba(15,30,48,0.96);
    --card-bg:     #132236;
    --locked-bg:   rgba(10,22,40,0.6);
    --locked-text: rgba(232,244,255,0.25);
  }

  /* ═══════════════════════════════════════════════════════════
     RESET & BASE
  ═══════════════════════════════════════════════════════════ */
  *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

  body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
    transition: background 0.6s ease, color 0.4s ease;
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
  /* ═══════════════════════════════════════════════════════════
     HEADER
  ═══════════════════════════════════════════════════════════ */
  .glass-header {
    position: fixed; top:0; left:0; right:0;
    height: var(--hdr-h);
    background: var(--header-bg);
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 2px solid transparent;
    border-image: linear-gradient(90deg,#0366B0,#02B393,#606060) 1;
    display: flex; align-items:center; justify-content:space-between;
    padding: 0 28px; z-index: 1000;
    box-shadow: var(--shadow);
    animation: slideDown 0.5s cubic-bezier(0.16,1,0.3,1);
    transition: background 0.6s ease;
  }
  @keyframes slideDown { from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1} }

  .header-logos { display:flex; align-items:center; gap:14px; flex-shrink:0; }
  .logo-block {
    display:flex; align-items:center; gap:9px; text-decoration:none;
    padding:6px 10px; border-radius:10px; transition:background 0.2s;
  }
  .logo-block:hover { background:rgba(3,102,176,0.07); }
  .logo-block img { width:120px; height:120px; object-fit:contain; }
  .logo-divider { width:1px; height:36px; background:linear-gradient(180deg,transparent,var(--border2),transparent); flex-shrink:0; }

  .search-container { flex:1; max-width:460px; margin:0 40px; }
  .search-btn {
    all:unset; width:100%; padding:11px 18px;
    background:var(--surface2); border:1px solid var(--border);
    border-radius:var(--r-sm); display:flex; align-items:center; gap:10px;
    font-family:var(--font); font-size:0.92rem; color:var(--text2);
    cursor:text; transition:all 0.2s;
  }
  .search-btn:hover { border-color:var(--teal); }
  .search-btn kbd { font-size:0.68rem; padding:2px 6px; background:var(--surface); border-radius:5px; color:var(--blue); border:1px solid var(--border); }

  .header-right { display:flex; align-items:center; gap:14px; flex-shrink:0; }
  .student-identity { display:flex; align-items:center; gap:10px; }
  .avatar-ring { width:46px; height:46px; border-radius:18%; background:var(--g-warm); padding:3px; }
  .avatar-ring .avatar-initials { display:flex; align-items:center; justify-content:center; width:100%; height:100%; font-weight:700; font-size:0.82rem; color:white; }
  .student-name { font-size:1rem; font-weight:700; color:var(--text); }
  .student-role { font-size:0.82rem; color:var(--blue); font-weight:500; }
  .header-actions { display:flex; gap:7px; }
  .icon-btn {
    width:42px; height:42px; border-radius:10px;
    background:var(--surface); border:1px solid var(--border);
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all 0.25s; position:relative; color:var(--text2);
  }
  .icon-btn:hover { transform:translateY(-2px); box-shadow:var(--shadow); }
  .notif-dot { position:absolute; top:8px; right:8px; width:7px; height:7px; background:var(--gold); border-radius:50%; border:2px solid var(--bg); animation:pulse 2s infinite; }
  @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.6;transform:scale(1.15)} }

  /* ═══════════════════════════════════════════════════════════
     FLOATING NAV
  ═══════════════════════════════════════════════════════════ */
  .floating-nav {
    position:fixed; left:20px; top:50%; transform:translateY(-50%);
    background: var(--nav-bg);
    backdrop-filter:blur(20px);
    border-radius:var(--r-md);
    height:calc(100vh - var(--hdr-h) - 40px);
    display:flex; flex-direction:column; justify-content:center;
    padding:14px; box-shadow:var(--shadow-md), 0 0 0 1px var(--border);
    z-index:999; animation:slideInLeft 0.5s cubic-bezier(0.16,1,0.3,1) 0.2s both;
    transition: background 0.6s ease;
  }
  @keyframes slideInLeft {
    from{transform:translateX(-100%) translateY(-50%);opacity:0}
    to{transform:translateX(0) translateY(-50%);opacity:1}
  }
  .nav-items { display:flex; flex-direction:column; gap:6px; }
  .nav-item {
    width:52px; height:52px; border-radius:var(--r-md);
    display:flex; align-items:center; justify-content:center;
    color:var(--text2); text-decoration:none; position:relative; transition:all 0.25s;
  }
  .nav-item::before {
    content:attr(data-label); position:absolute; left:68px;
    background:var(--text); color:var(--bg);
    padding:7px 14px; border-radius:var(--r-sm);
    font-size:0.82rem; font-weight:500; white-space:nowrap;
    opacity:0; pointer-events:none; transform:translateX(-6px); transition:all 0.25s; z-index:10;
  }
  .nav-item:hover::before { opacity:1; transform:translateX(0); }
  .nav-item:hover { background:rgba(3,102,176,0.09); color:var(--blue); }
  .nav-item.active { background:var(--g-main); color:white; box-shadow:0 4px 16px rgba(3,102,176,0.35); }
  .nav-logout:hover { background:rgba(199,74,60,0.08) !important; }
  .nav-logout:hover svg { stroke:#c74a3c !important; }

  /* ═══════════════════════════════════════════════════════════
     MAIN LAYOUT
  ═══════════════════════════════════════════════════════════ */
  .settings-container {
    margin-left: calc(var(--nav-w) + 40px);
    margin-top:  calc(var(--hdr-h) + 32px);
    margin-right: 32px;
    margin-bottom: 60px;
    position: relative; z-index: 1;
    max-width: 1400px;
    animation: fadeUp 0.6s cubic-bezier(0.16,1,0.3,1) 0.1s both;
  }
  @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }

  /* Page hero */
  .settings-hero {
    background: var(--g-hero);
    border-radius: var(--r-lg);
    padding: 36px 40px;
    margin-bottom: 28px;
    color: white;
    position: relative; overflow: hidden;
    display: flex; align-items: center; justify-content: space-between;
  }
  .settings-hero::before {
    content:''; position:absolute; width:280px; height:280px; border-radius:50%;
    border:1.5px solid rgba(255,255,255,0.10); bottom:-80px; right:-80px; pointer-events:none;
  }
  .settings-hero::after {
    content:''; position:absolute; width:160px; height:160px; border-radius:50%;
    border:1.5px solid rgba(255,255,255,0.07); top:-50px; right:160px;
  }
  .hero-title {
    font-family: var(--font-serif); font-size:2.1rem; font-weight:700; margin-bottom:5px;
  }
  .hero-sub { font-size:0.88rem; opacity:0.78; }
  .hero-sky-pill {
    display:flex; align-items:center; gap:9px; padding:10px 18px;
    background:rgba(255,255,255,0.16); border-radius:40px;
    font-size:0.84rem; font-weight:600; backdrop-filter:blur(6px);
    flex-shrink:0;
  }
  .sky-time-icon { font-size:1.4rem; }

  /* Grid: sidebar + content */
  .settings-grid {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 24px;
    align-items: start;
  }

  /* ── SIDEBAR ── */
  .settings-sidebar {
    background: var(--card-bg);
    border-radius: var(--r-md);
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    overflow: hidden;
    position: sticky;
    top: calc(var(--hdr-h) + 24px);
    transition: background 0.6s ease, border-color 0.4s ease;
  }

  .sidebar-profile-strip {
    background: var(--g-main);
    padding: 18px 18px 14px;
    display: flex; align-items: center; gap: 12px;
  }
  .sps-avatar {
    width:42px; height:42px; border-radius:12px;
    background:rgba(255,255,255,0.22); display:flex; align-items:center; justify-content:center;
    font-weight:700; font-size:0.85rem; color:white; flex-shrink:0;
  }
  .sps-name { font-size:0.88rem; font-weight:700; color:white; }
  .sps-role { font-size:0.7rem; color:rgba(255,255,255,0.72); margin-top:1px; }

  .sidebar-nav { padding:10px 8px; }
  .snav-group { font-size:0.63rem; font-weight:700; text-transform:uppercase; letter-spacing:1.2px; color:var(--text3); padding:8px 10px 4px; }
  .snav-item {
    display:flex; align-items:center; gap:10px; padding:10px 12px;
    border-radius:10px; font-size:0.84rem; font-weight:500; color:var(--text2);
    cursor:pointer; transition:all 0.18s; text-decoration:none; position:relative;
    margin-bottom:2px;
  }
  .snav-item:hover { background:rgba(3,102,176,0.07); color:var(--blue); }
  .snav-item.active { background:rgba(3,102,176,0.10); color:var(--blue); font-weight:600; }
  .snav-item.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--g-main); border-radius:0 3px 3px 0; }
  .snav-item svg { flex-shrink:0; }
  .snav-lock-badge {
    margin-left:auto; font-size:0.6rem; font-weight:700; padding:2px 6px;
    background:rgba(243,199,59,0.15); color:var(--gold); border-radius:20px;
    border:1px solid rgba(243,199,59,0.3); white-space:nowrap;
  }

  /* ── CONTENT PANEL ── */
  .settings-content { display:flex; flex-direction:column; gap:24px; }

  /* Section card */
  .s-card {
    background: var(--card-bg);
    border-radius: var(--r-md);
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    overflow: hidden;
    transition: background 0.6s ease, border-color 0.4s ease;
    animation: fadeUp 0.5s cubic-bezier(0.16,1,0.3,1) both;
  }
  .s-card-head {
    display:flex; align-items:center; gap:12px;
    padding:18px 22px; border-bottom:1px solid var(--border);
    background: var(--surface2);
  }
  .s-card-icon {
    width:36px; height:36px; border-radius:10px;
    background:var(--g-main); display:flex; align-items:center; justify-content:center; flex-shrink:0;
  }
  .s-card-title { font-size:0.95rem; font-weight:700; color:var(--text); }
  .s-card-sub   { font-size:0.74rem; color:var(--text2); margin-top:1px; }
  .s-card-body  { padding:22px; }

  /* Setting row */
  .s-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:14px 0; border-bottom:1px solid var(--border);
    gap:16px;
  }
  .s-row:last-child { border-bottom:none; padding-bottom:0; }
  .s-row-info { flex:1; }
  .s-row-label { font-size:0.88rem; font-weight:600; color:var(--text); margin-bottom:3px; }
  .s-row-desc  { font-size:0.74rem; color:var(--text2); line-height:1.5; }
  .s-row-ctrl  { flex-shrink:0; }

  /* ── TOGGLE SWITCH ── */
  .toggle {
    width:48px; height:26px; border-radius:13px;
    background:rgba(3,102,176,0.15); border:none; cursor:pointer;
    position:relative; transition:background 0.3s; flex-shrink:0;
  }
  .toggle::after {
    content:''; position:absolute; width:20px; height:20px;
    border-radius:50%; background:white; top:3px; left:3px;
    transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1), background 0.3s;
    box-shadow:0 2px 6px rgba(0,0,0,0.18);
  }
  .toggle.on { background:var(--g-main); }
  .toggle.on::after { transform:translateX(22px); }

  /* ── SELECT ── */
  .s-select {
    padding:8px 32px 8px 12px; border-radius:var(--r-sm);
    border:1.5px solid var(--border2); background:var(--surface);
    font-family:var(--font); font-size:0.85rem; color:var(--text);
    outline:none; cursor:pointer; appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%234d6b85' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 10px center;
    transition:border-color 0.2s;
  }
  .s-select:focus { border-color:var(--teal); }

  /* ── INPUT ── */
  .s-input {
    padding:9px 13px; border-radius:var(--r-sm);
    border:1.5px solid var(--border2); background:var(--surface);
    font-family:var(--font); font-size:0.85rem; color:var(--text);
    outline:none; width:100%; transition:border-color 0.2s, box-shadow 0.2s;
  }
  .s-input:focus { border-color:var(--teal); box-shadow:0 0 0 4px rgba(2,179,147,0.1); }

  /* ── TEXTAREA ── */
  .s-textarea { resize:vertical; min-height:70px; }

  /* ── THEME SELECTOR ── */
  .theme-options {
    display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-top:4px;
  }
  .theme-opt {
    border-radius:var(--r-sm); padding:12px 10px;
    border:2px solid var(--border); cursor:pointer; transition:all 0.22s;
    text-align:center; position:relative;
    background: var(--surface);
  }
  .theme-opt:hover { border-color:var(--teal); transform:translateY(-2px); }
  .theme-opt.selected { border-color:var(--blue); box-shadow:0 0 0 3px rgba(3,102,176,0.15); }
  .theme-opt.selected::after {
    content:'✓'; position:absolute; top:6px; right:8px;
    font-size:0.7rem; font-weight:700; color:var(--blue);
  }
  .theme-preview {
    width:100%; height:38px; border-radius:7px; margin-bottom:7px; overflow:hidden;
    display:flex; gap:3px; padding:4px;
  }
  .tp-sidebar { width:30%; border-radius:4px; }
  .tp-content { flex:1; border-radius:4px; }
  .theme-preview.light-prev { background:#e8f4ff; }
  .theme-preview.light-prev .tp-sidebar { background:#0366B0; }
  .theme-preview.light-prev .tp-content { background:#fff; }
  .theme-preview.dark-prev  { background:#0a1628; }
  .theme-preview.dark-prev .tp-sidebar  { background:#02B393; }
  .theme-preview.dark-prev .tp-content  { background:#132236; }
  .theme-preview.auto-prev  { background:linear-gradient(135deg,#e8f4ff 50%,#0a1628 50%); }
  .theme-preview.auto-prev .tp-sidebar  { background:linear-gradient(180deg,#0366B0,#02B393); }
  .theme-preview.auto-prev .tp-content  { background:linear-gradient(180deg,#fff,#132236); }
  .theme-opt-name { font-size:0.75rem; font-weight:600; color:var(--text2); }

  /* ── COLOR ACCENT ── */
  .accent-options { display:flex; gap:10px; margin-top:4px; }
  .accent-opt {
    width:36px; height:36px; border-radius:50%; cursor:pointer;
    border:3px solid transparent; transition:all 0.22s;
    position:relative;
  }
  .accent-opt:hover { transform:scale(1.12); }
  .accent-opt.selected { border-color:var(--text); }
  .accent-opt.selected::after {
    content:''; position:absolute; inset:5px; border-radius:50%;
    background:rgba(255,255,255,0.6);
  }
  .accent-opt[data-accent="blue"]   { background:linear-gradient(135deg,#0366B0,#0288d1); }
  .accent-opt[data-accent="teal"]   { background:linear-gradient(135deg,#02B393,#00897b); }
  .accent-opt[data-accent="violet"] { background:linear-gradient(135deg,#7c3aed,#a855f7); }
  .accent-opt[data-accent="rose"]   { background:linear-gradient(135deg,#e11d48,#f43f5e); }
  .accent-opt[data-accent="amber"]  { background:linear-gradient(135deg,#d97706,#f59e0b); }

  /* ── FONT SIZE ── */
  .font-size-row { display:flex; align-items:center; gap:12px; }
  .font-size-slider {
    flex:1; appearance:none; height:5px; border-radius:5px;
    background:linear-gradient(90deg, var(--g-main));
    outline:none; cursor:pointer;
  }
  .font-size-slider::-webkit-slider-thumb {
    appearance:none; width:18px; height:18px; border-radius:50%;
    background:white; border:3px solid var(--blue); cursor:pointer;
    box-shadow:0 2px 6px rgba(3,102,176,0.3);
  }
  .font-size-val { font-size:0.78rem; font-weight:700; color:var(--blue); width:32px; text-align:right; }

  /* ── LOCKED OVERLAY ── */
  .locked-card {
    position: relative; overflow: hidden;
  }
  .lock-overlay {
    position: absolute; inset: 0; z-index: 10;
    background: var(--locked-bg);
    backdrop-filter: blur(4px);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 14px; padding: 24px; text-align: center;
    border-radius: inherit;
  }
  .lock-icon {
    width: 56px; height: 56px; border-radius: 16px;
    background: rgba(243,199,59,0.12); border: 2px solid rgba(243,199,59,0.3);
    display: flex; align-items: center; justify-content: center;
  }
  .lock-title { font-size:0.95rem; font-weight:700; color:var(--text); }
  .lock-desc  { font-size:0.78rem; color:var(--text2); line-height:1.6; max-width:260px; }
  .lock-cta {
    padding:10px 22px; background:var(--g-gold,linear-gradient(135deg,#F3C73B,#f0a500));
    border:none; border-radius:var(--r-sm); font-family:var(--font);
    font-size:0.84rem; font-weight:700; color:#1a0800; cursor:pointer;
    transition:opacity 0.2s, transform 0.15s; display:flex; align-items:center; gap:7px;
  }
  .lock-cta:hover { opacity:0.9; transform:translateY(-2px); }

  /* ── CLASS CARD (Google Classroom inspired) ── */
  .class-card {
    border-radius:var(--r-md); overflow:hidden; border:1px solid var(--border);
    transition:transform 0.22s, box-shadow 0.22s;
  }
  .class-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-md); }
  .cc-header {
    padding:24px 22px 18px; color:white; position:relative; overflow:hidden; min-height:120px;
  }
  .cc-header::after {
    content:''; position:absolute; width:120px; height:120px; border-radius:50%;
    border:1.5px solid rgba(255,255,255,0.12); bottom:-30px; right:-30px;
  }
  .cc-subject  { font-family:var(--font-serif); font-size:1.2rem; font-weight:700; margin-bottom:3px; }
  .cc-teacher  { font-size:0.78rem; opacity:0.78; margin-bottom:12px; }
  .cc-code-wrap { display:flex; align-items:center; gap:7px; }
  .cc-code {
    font-size:0.78rem; font-weight:700; padding:4px 11px; border-radius:20px;
    background:rgba(255,255,255,0.2); letter-spacing:1px; font-family:monospace;
  }
  .cc-copy {
    background:none; border:none; cursor:pointer; color:rgba(255,255,255,0.75); padding:3px;
    transition:color 0.2s;
  }
  .cc-copy:hover { color:white; }
  .cc-body { background:var(--card-bg); padding:16px 18px; }
  .cc-stats { display:flex; gap:20px; }
  .cc-stat { text-align:center; }
  .cc-stat-val { display:block; font-size:1.1rem; font-weight:700; color:var(--blue); }
  .cc-stat-lbl { font-size:0.65rem; color:var(--text2); text-transform:uppercase; letter-spacing:0.5px; }
  .cc-actions { display:flex; gap:8px; margin-top:14px; }
  .cc-btn {
    flex:1; padding:9px; border-radius:var(--r-sm); font-family:var(--font);
    font-size:0.78rem; font-weight:600; cursor:pointer; transition:all 0.2s;
    display:flex; align-items:center; justify-content:center; gap:5px;
  }
  .cc-btn-primary { background:var(--g-main); color:white; border:none; }
  .cc-btn-primary:hover { opacity:0.88; }
  .cc-btn-ghost { background:none; border:1.5px solid var(--border2); color:var(--text2); }
  .cc-btn-ghost:hover { border-color:var(--blue); color:var(--blue); }

  /* Schedule grid */
  .schedule-grid {
    display:grid; grid-template-columns:repeat(7,1fr); gap:3px;
  }
  .sg-head { text-align:center; font-size:0.62rem; font-weight:700; text-transform:uppercase; color:var(--text3); padding:4px 0; }
  .sg-cell {
    aspect-ratio:1; border-radius:6px; display:flex; align-items:center; justify-content:center;
    font-size:0.65rem; font-weight:500; color:var(--text3); background:var(--surface2); border:1px solid var(--border);
    transition:all 0.2s; cursor:pointer;
  }
  .sg-cell:hover { background:rgba(3,102,176,0.07); }
  .sg-cell.has-class { background:rgba(2,179,147,0.12); color:var(--teal); font-weight:700; border-color:rgba(2,179,147,0.25); }
  .sg-cell.today { background:var(--g-main); color:white; font-weight:700; border-color:transparent; }

  /* Notification toggles */
  .notif-group { display:flex; flex-direction:column; gap:0; }
  .notif-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:12px 14px; border-bottom:1px solid var(--border); gap:12px;
  }
  .notif-row:last-child { border-bottom:none; }
  .notif-ico {
    width:32px; height:32px; border-radius:9px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
  }
  .notif-text { flex:1; }
  .notif-label { font-size:0.83rem; font-weight:600; color:var(--text); }
  .notif-desc  { font-size:0.71rem; color:var(--text2); margin-top:1px; }

  /* Button styles */
  .btn-primary {
    padding:10px 22px; background:var(--g-main); border:none; border-radius:var(--r-sm);
    font-family:var(--font); font-size:0.86rem; font-weight:700; color:white;
    cursor:pointer; transition:all 0.2s; display:inline-flex; align-items:center; gap:7px;
  }
  .btn-primary:hover { opacity:0.9; transform:translateY(-1px); box-shadow:0 4px 14px rgba(3,102,176,0.3); }
  .btn-ghost {
    padding:10px 22px; background:none; border:1.5px solid var(--border2); border-radius:var(--r-sm);
    font-family:var(--font); font-size:0.86rem; font-weight:600; color:var(--text2);
    cursor:pointer; transition:all 0.2s; display:inline-flex; align-items:center; gap:7px;
  }
  .btn-ghost:hover { border-color:var(--blue); color:var(--blue); }
  .btn-danger {
    padding:10px 22px; background:none; border:1.5px solid rgba(199,74,60,0.3); border-radius:var(--r-sm);
    font-family:var(--font); font-size:0.86rem; font-weight:600; color:#c74a3c;
    cursor:pointer; transition:all 0.2s; display:inline-flex; align-items:center; gap:7px;
  }
  .btn-danger:hover { background:rgba(199,74,60,0.07); }

  /* Save row */
  .save-row {
    display:flex; align-items:center; justify-content:flex-end; gap:10px;
    padding-top:18px; border-top:1px solid var(--border); margin-top:6px;
  }

  /* Profile photo area */
  .photo-upload-area {
    display:flex; align-items:center; gap:20px; padding:4px 0;
  }
  .photo-circle {
    width:70px; height:70px; border-radius:20px;
    background:var(--g-warm); display:flex; align-items:center; justify-content:center;
    font-weight:700; font-size:1.2rem; color:white; flex-shrink:0;
  }
  .photo-btns { display:flex; gap:8px; }

  /* 2-col grid for fields */
  .field-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
  @media (max-width:640px) { .field-grid-2 { grid-template-columns:1fr; } }

  /* Toast */
  .toast-wrap {
    position:fixed; bottom:28px; left:50%; transform:translateX(-50%);
    z-index:10000; display:flex; flex-direction:column; gap:8px; align-items:center;
  }
  .toast {
    background:var(--text); color:var(--bg); padding:10px 22px; border-radius:40px;
    font-size:0.82rem; font-weight:500; white-space:nowrap;
    opacity:0; transform:translateY(10px);
    transition:all 0.3s cubic-bezier(0.16,1,0.3,1); pointer-events:none;
  }
  .toast.show { opacity:1; transform:translateY(0); }
  .toast.success { background:var(--teal); color:white; }
  .toast.error   { background:#c74a3c; color:white; }

  /* Pane show/hide */
  .settings-pane { display:none; }
  .settings-pane.active { display:flex; flex-direction:column; gap:24px; animation:fadeUp 0.35s cubic-bezier(0.16,1,0.3,1) both; }

  /* ── RESPONSIVE ── */
  @media (max-width:1100px) {
    .settings-grid { grid-template-columns:1fr; }
    .settings-sidebar { position:static; }
  }
  @media (max-width:768px) {
    .settings-container { margin-left:76px; margin-right:12px; }
    .field-grid-2 { grid-template-columns:1fr; }
  }
  </style>
</head>
<body>



<!-- TOAST -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- HEADER -->
<header class="glass-header">
  <div class="header-logos">
    <a href="#" class="logo-block">
      <img src="../assets/images/DUK Logo.png" alt="DUK" onerror="this.style.display='none'">
    </a>
    <div class="logo-divider"></div>
    <a href="student-dashboard.php" class="logo-block">
      <img src="../assets/images/cdtc_logo.png" alt="Digital Art School" onerror="this.style.display='none'">
    </a>
  </div>
  <div class="search-container">
    <button class="search-btn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <span>Search settings…</span>
      <kbd>Ctrl K</kbd>
    </button>
  </div>
  <div class="header-right">
    <div class="student-identity">
      <div class="avatar-ring"><div class="avatar-initials"><?= htmlspecialchars($initials) ?></div></div>
      <div>
        <div class="student-name"><?= htmlspecialchars($student['full_name']) ?></div>
        <div class="student-role"><?= htmlspecialchars($student['art_discipline'] ?: ($student['art_category'] ?: 'Art Student')) ?></div>
      </div>
    </div>
    <div class="header-actions">
      <button class="icon-btn" aria-label="Notifications">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="notif-dot"></span>
      </button>
    </div>
  </div>
</header>

<!-- NAV -->
<nav class="floating-nav">
  <div class="nav-items">
    <a href="student-dashboard.php" class="nav-item" data-label="Dashboard">
      <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    </a>
    <a href="student-schedule.php" class="nav-item" data-label="My Schedule">
      <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    </a>
    <a href="student-courses.php" class="nav-item" data-label="My Courses">
      <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
    </a>
    <a href="student-progress.php" class="nav-item" data-label="My Progress">
      <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    </a>
    <a href="student-profile.php" class="nav-item" data-label="My Profile">
      <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </a>
    <a href="student-settings.php" class="nav-item active" data-label="Settings">
      <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </a>
    <a href="#" class="nav-item nav-logout" id="logoutBtn" data-label="Log Out">
      <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="rgba(199,74,60,0.65)" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</nav>

<!-- ================================================================
     MAIN SETTINGS
================================================================ -->
<div class="settings-container">

  <!-- Hero -->
  <div class="settings-hero">
    <div>
      <div class="hero-title">Settings</div>
      <div class="hero-sub">Personalise your Digital Art School experience</div>
    </div>
    <div class="hero-sky-pill" id="heroPill">
      <span class="sky-time-icon" id="skyIcon"></span>
      <span id="skyLabel">Loading…</span>
    </div>
  </div>

  <div class="settings-grid">

    <!-- ── SIDEBAR ── -->
    <aside class="settings-sidebar">
      <div class="sidebar-profile-strip">
        <div class="sps-avatar"><?= htmlspecialchars($initials) ?></div>
        <div>
          <div class="sps-name"><?= htmlspecialchars($firstName) ?></div>
          <div class="sps-role"><?= htmlspecialchars($student['art_discipline'] ?: 'Art Student') ?></div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <div class="snav-group">Personalisation</div>
        <div class="snav-item active" data-pane="appearance" onclick="switchPane('appearance',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9"/></svg>
          Appearance
        </div>
        <div class="snav-item" data-pane="account" onclick="switchPane('account',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Account & Profile
        </div>
        <div class="snav-item" data-pane="notifications" onclick="switchPane('notifications',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          Notifications
        </div>
        <div class="snav-item" data-pane="privacy" onclick="switchPane('privacy',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Privacy
        </div>

        <?php if ($hasTeacher): ?>
        <div class="snav-group">My Class</div>
        <div class="snav-item" data-pane="class" onclick="switchPane('class',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Class Settings
        </div>
        <div class="snav-item" data-pane="schedule" onclick="switchPane('schedule',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Schedule & Calendar
        </div>
        <div class="snav-item" data-pane="studio" onclick="switchPane('studio',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
          Studio & Sessions
        </div>
        <div class="snav-item" data-pane="submissions" onclick="switchPane('submissions',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Assignments
        </div>
        <?php else: ?>
        <div class="snav-group">My Class</div>
        <div class="snav-item" data-pane="class-locked" onclick="switchPane('class-locked',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Class Settings
          <span class="snav-lock-badge">Locked</span>
        </div>
        <?php endif; ?>

        <div class="snav-group">Account</div>
        <div class="snav-item" data-pane="security" onclick="switchPane('security',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Security
        </div>
        <div class="snav-item" data-pane="language" onclick="switchPane('language',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          Language & Region
        </div>
        <div class="snav-item" data-pane="data" onclick="switchPane('data',this)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
          Data & Storage
        </div>
      </nav>
    </aside>

    <!-- ── CONTENT PANES ── -->
    <div class="settings-content" id="settingsContent">

      <!-- ══ APPEARANCE ══ -->
      <div class="settings-pane active" id="pane-appearance">

        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            </div>
            <div>
              <div class="s-card-title">Theme & Display</div>
              <div class="s-card-sub">Choose how Digital Art School looks on your device</div>
            </div>
          </div>
          <div class="s-card-body">

          


            <div class="s-row">
              <div class="s-row-info">
                <div class="s-row-label">Text Size</div>
                <div class="s-row-desc">Adjust the base font size across all pages</div>
              </div>
              <div class="s-row-ctrl" style="min-width:180px">
                <div class="font-size-row">
                  <span style="font-size:0.7rem;color:var(--text3)">A</span>
                  <input type="range" class="font-size-slider" id="fontSizeSlider" min="12" max="18" value="14" oninput="setFontSize(this.value)">
                  <span style="font-size:1rem;font-weight:700;color:var(--text3)">A</span>
                  <span class="font-size-val" id="fontSizeVal">14px</span>
                </div>
              </div>
            </div>

            <div class="s-row">
              <div class="s-row-info">
                <div class="s-row-label">Reduced Motion</div>
                <div class="s-row-desc">Minimise animations and transitions across the platform</div>
              </div>
              <div class="s-row-ctrl"><button class="toggle" id="toggleReducedMotion" onclick="toggleSwitch(this)"></button></div>
            </div>

            <div class="s-row">
              <div class="s-row-info">
                <div class="s-row-label">Sky Background</div>
                <div class="s-row-desc">Show the animated day/night sky on this page</div>
              </div>
              <div class="s-row-ctrl"><button class="toggle on" id="toggleSky" onclick="toggleSwitch(this);toggleSkyBg(this)"></button></div>
            </div>

            <div class="s-row">
              <div class="s-row-info">
                <div class="s-row-label">Compact View</div>
                <div class="s-row-desc">Reduce spacing and padding for a denser layout</div>
              </div>
              <div class="s-row-ctrl"><button class="toggle" id="toggleCompact" onclick="toggleSwitch(this)"></button></div>
            </div>

          </div>
        </div>

      </div><!-- /appearance -->

      <!-- ══ ACCOUNT ══ -->
      <div class="settings-pane" id="pane-account">
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
            <div><div class="s-card-title">Personal Information</div><div class="s-card-sub">Update your name, email, and profile details</div></div>
          </div>
          <div class="s-card-body">
            <div class="photo-upload-area" style="margin-bottom:22px">
              <div class="photo-circle"><?= htmlspecialchars($initials) ?></div>
              <div>
                <div style="font-size:0.82rem;font-weight:600;color:var(--text);margin-bottom:8px">Profile Photo</div>
                <div class="photo-btns">
                  <button class="btn-ghost" style="padding:7px 14px;font-size:0.78rem">Upload Photo</button>
                  <button class="btn-ghost" style="padding:7px 14px;font-size:0.78rem">Remove</button>
                </div>
                <div style="font-size:0.68rem;color:var(--text3);margin-top:5px">JPG or PNG, max 5 MB</div>
              </div>
            </div>

            <div class="field-grid-2" style="margin-bottom:16px">
              <div>
                <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text2);margin-bottom:6px">Full Name</div>
                <input type="text" class="s-input" value="<?= htmlspecialchars($student['full_name']) ?>">
              </div>
              <div>
                <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text2);margin-bottom:6px">Email Address</div>
                <input type="email" class="s-input" value="<?= htmlspecialchars($student['email']) ?>">
              </div>
              <div>
                <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text2);margin-bottom:6px">Location</div>
                <input type="text" class="s-input" value="<?= htmlspecialchars($student['location'] ?? '') ?>" placeholder="City, Country">
              </div>
              <div>
                <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text2);margin-bottom:6px">Age</div>
                <input type="number" class="s-input" value="<?= htmlspecialchars($student['age'] ?? '') ?>" placeholder="Your age">
              </div>
            </div>

            <div style="margin-bottom:16px">
              <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text2);margin-bottom:6px">About Me <span style="text-transform:none;letter-spacing:0;font-weight:400;color:var(--text3)">(optional)</span></div>
              <textarea class="s-input s-textarea" placeholder="Tell your teacher a bit about yourself, your background, and what you hope to achieve…"><?= htmlspecialchars($student['previous_training'] ?? '') ?></textarea>
            </div>

            <div class="save-row">
              <button class="btn-ghost">Discard Changes</button>
              <button class="btn-primary" onclick="showToast('Profile updated successfully.','success')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save Changes
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ NOTIFICATIONS ══ -->
      <div class="settings-pane" id="pane-notifications">
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
            <div><div class="s-card-title">Notification Preferences</div><div class="s-card-sub">Control what alerts you receive and how</div></div>
          </div>
          <div class="s-card-body" style="padding:0">
            <div class="notif-group">
              <?php
              $notifs = [
                ['icon'=>'🏫','bg'=>'rgba(3,102,176,0.09)','label'=>'Class Reminders','desc'=>'Get reminders 30 minutes before a scheduled session','on'=>true],
                ['icon'=>'💬','bg'=>'rgba(2,179,147,0.09)','label'=>'Teacher Messages','desc'=>'Notify when your teacher sends a message or feedback','on'=>true],
                ['icon'=>'📝','bg'=>'rgba(243,199,59,0.09)','label'=>'Assignment Deadlines','desc'=>'Remind me of upcoming submission deadlines','on'=>true],
                ['icon'=>'✅','bg'=>'rgba(163,206,71,0.09)','label'=>'Grade Released','desc'=>'Notify when your teacher releases grades or feedback','on'=>true],
                ['icon'=>'🔔','bg'=>'rgba(96,96,96,0.09)','label'=>'Platform Updates','desc'=>'News and updates from Digital Art School','on'=>false],
                ['icon'=>'📧','bg'=>'rgba(3,102,176,0.09)','label'=>'Email Digest','desc'=>'Weekly summary of your activity sent to your email','on'=>false],
              ];
              foreach ($notifs as $n):
              ?>
              <div class="notif-row">
                <div class="notif-ico" style="background:<?= $n['bg'] ?>;font-size:1rem"><?= $n['icon'] ?></div>
                <div class="notif-text">
                  <div class="notif-label"><?= $n['label'] ?></div>
                  <div class="notif-desc"><?= $n['desc'] ?></div>
                </div>
                <button class="toggle <?= $n['on'] ? 'on' : '' ?>" onclick="toggleSwitch(this)"></button>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ PRIVACY ══ -->
      <div class="settings-pane" id="pane-privacy">
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
            <div><div class="s-card-title">Privacy Settings</div><div class="s-card-sub">Manage what information is visible and shared</div></div>
          </div>
          <div class="s-card-body">
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Show My Profile to Teachers</div><div class="s-row-desc">Teachers can see your full profile including background and goals</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Show Learning Progress</div><div class="s-row-desc">Allow your teacher to view detailed progress analytics</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Share Activity with Platform</div><div class="s-row-desc">Help improve the platform by sharing anonymous usage data</div></div>
              <div class="s-row-ctrl"><button class="toggle" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Allow Portfolio Display</div><div class="s-row-desc">Show your submitted work in the school's public gallery (with permission)</div></div>
              <div class="s-row-ctrl"><button class="toggle" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="save-row">
              <button class="btn-primary" onclick="showToast('Privacy preferences saved.','success')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save Preferences
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ CLASS SETTINGS (unlocked) ══ -->
      <?php if ($hasTeacher): ?>
      <div class="settings-pane" id="pane-class">

        <!-- My Class Card (Google Classroom style) -->
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
            <div><div class="s-card-title">My Enrolled Class</div><div class="s-card-sub">Manage your class, teacher connection, and communication</div></div>
          </div>
          <div class="s-card-body" style="padding:0">
            <div class="class-card" style="margin:18px">
              <div class="cc-header" style="background:var(--g-hero)">
                <div class="cc-subject"><?= htmlspecialchars($student['art_discipline'] ?: $student['art_category'] ?: 'Art Class') ?></div>
                <div class="cc-teacher">with <?= htmlspecialchars($student['teacher_name']) ?> · <?= htmlspecialchars($student['teacher_specialization'] ?? '') ?></div>
                <div class="cc-code-wrap">
                  <span class="cc-code" id="classCode">DAS-<?= str_pad($_SESSION['student_id'] ?? 0, 4, '0', STR_PAD_LEFT) ?></span>
                  <button class="cc-copy" onclick="copyCode()" title="Copy class code">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                  </button>
                </div>
              </div>
              <div class="cc-body">
                <div class="cc-stats">
                  <div class="cc-stat"><span class="cc-stat-val">0</span><span class="cc-stat-lbl">Classes Attended</span></div>
                  <div class="cc-stat"><span class="cc-stat-val">0</span><span class="cc-stat-lbl">Assignments</span></div>
                  <div class="cc-stat"><span class="cc-stat-val">—</span><span class="cc-stat-lbl">Avg. Grade</span></div>
                </div>
                <div class="cc-actions">
                  <button class="cc-btn cc-btn-primary" onclick="showToast('Opening studio…')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                    Enter Studio
                  </button>
                  <button class="cc-btn cc-btn-ghost" onclick="showToast('Opening messages…')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Message Teacher
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Class preferences -->
        <div class="s-card" style="animation-delay:0.1s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div>
            <div><div class="s-card-title">Class Preferences</div><div class="s-card-sub">Control how your class sessions work</div></div>
          </div>
          <div class="s-card-body">
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Camera On by Default</div><div class="s-row-desc">Automatically enable your camera when you join a session</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Microphone On by Default</div><div class="s-row-desc">Start sessions with your microphone enabled</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Session Recording Consent</div><div class="s-row-desc">Allow your teacher to record sessions for your later review</div></div>
              <div class="s-row-ctrl"><button class="toggle" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Preferred Language in Class</div><div class="s-row-desc">Language preference for your teacher's instructions</div></div>
              <div class="s-row-ctrl">
                <select class="s-select">
                  <option>English</option><option>Malayalam</option><option>Hindi</option><option>Tamil</option><option>Telugu</option>
                </select>
              </div>
            </div>
            <div class="save-row">
              <button class="btn-primary" onclick="showToast('Class preferences saved.','success')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save Preferences
              </button>
            </div>
          </div>
        </div>

        <!-- Leave class -->
        <div class="s-card" style="animation-delay:0.15s;border-color:rgba(199,74,60,0.2)">
          <div class="s-card-head" style="background:rgba(199,74,60,0.04)">
            <div class="s-card-icon" style="background:linear-gradient(135deg,#c74a3c,#e74c3c)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>
            <div><div class="s-card-title" style="color:#c74a3c">Danger Zone</div><div class="s-card-sub">These actions cannot be undone</div></div>
          </div>
          <div class="s-card-body">
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Leave this Class</div><div class="s-row-desc">You will be removed from your current teacher's class. You can request a new teacher afterwards.</div></div>
              <div class="s-row-ctrl"><button class="btn-danger" onclick="if(confirm('Leave class? This will remove you from your teacher\'s roster.'))showToast('Class leave request submitted.','error')">Leave Class</button></div>
            </div>
          </div>
        </div>

      </div><!-- /pane-class -->
      <?php endif; ?>

      <!-- ══ CLASS LOCKED ══ -->
      <?php if (!$hasTeacher): ?>
      <div class="settings-pane" id="pane-class-locked">
        <div class="s-card locked-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <div><div class="s-card-title">Class Settings</div><div class="s-card-sub">Enrol in a class to unlock these settings</div></div>
          </div>
          <!-- Blurred preview content -->
          <div class="s-card-body" style="filter:blur(4px);pointer-events:none;user-select:none;opacity:0.4">
            <div class="s-row"><div class="s-row-info"><div class="s-row-label">Camera On by Default</div><div class="s-row-desc">Automatically enable your camera when you join a session</div></div><div class="s-row-ctrl"><button class="toggle on"></button></div></div>
            <div class="s-row"><div class="s-row-info"><div class="s-row-label">Session Recording Consent</div><div class="s-row-desc">Allow your teacher to record sessions for your later review</div></div><div class="s-row-ctrl"><button class="toggle"></button></div></div>
            <div class="s-row"><div class="s-row-info"><div class="s-row-label">Preferred Language</div></div><div class="s-row-ctrl"><select class="s-select"><option>English</option></select></div></div>
          </div>

          <div class="lock-overlay">
            <div class="lock-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F3C73B" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <div class="lock-title">Class Settings Locked</div>
            <div class="lock-desc">These settings become available once a teacher accepts your request and you're enrolled in a class. Browse teachers on the dashboard to get started.</div>
            <button class="lock-cta" onclick="window.location='student-dashboard.php#teacherWidget'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
              Find a Teacher
            </button>
          </div>
        </div>

        <!-- Schedule locked -->
        <div class="s-card locked-card" style="animation-delay:0.1s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div><div class="s-card-title">Schedule & Sessions</div><div class="s-card-sub">Your class timetable will appear here once enrolled</div></div>
          </div>
          <div class="s-card-body" style="filter:blur(4px);pointer-events:none;user-select:none;opacity:0.4">
            <div class="schedule-grid">
              <?php $days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; ?>
              <?php foreach($days as $d): ?><div class="sg-head"><?= $d ?></div><?php endforeach; ?>
              <?php for($i=0;$i<21;$i++): ?><div class="sg-cell">–</div><?php endfor; ?>
            </div>
          </div>
          <div class="lock-overlay">
            <div class="lock-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F3C73B" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <div class="lock-title">No Class Enrolled</div>
            <div class="lock-desc">Your personalised weekly schedule appears here once a teacher accepts your request.</div>
            <button class="lock-cta" onclick="window.location='student-dashboard.php#teacherWidget'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              Request a Teacher
            </button>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ══ SCHEDULE (enrolled) ══ -->
      <?php if ($hasTeacher): ?>
      <div class="settings-pane" id="pane-schedule">
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div><div class="s-card-title">Weekly Schedule</div><div class="s-card-sub">Your confirmed class times with <?= htmlspecialchars($student['teacher_name']) ?></div></div>
          </div>
          <div class="s-card-body">
            <div class="schedule-grid">
              <?php $days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; ?>
              <?php foreach($days as $d): ?><div class="sg-head"><?= $d ?></div><?php endforeach; ?>
              <?php
              $times = ['8am','9am','10am','11am','12pm','1pm','2pm','3pm','4pm','5pm','6pm','7pm','8pm','9pm','10pm'];
              $classDays = [1,4]; // example: Tue + Fri
              foreach($times as $ti=>$t):
                foreach($days as $di=>$d):
                  $isClass = in_array($di,$classDays) && $ti===2;
                  $isToday = date('N')-1 === $di;
                  echo '<div class="sg-cell'.($isClass?' has-class':'').($isToday&&!$isClass?' today':'').'">'.$t.'</div>';
                endforeach;
              endforeach; ?>
            </div>
            <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap">
              <div style="display:flex;align-items:center;gap:5px;font-size:0.72rem;color:var(--text2)"><span style="width:12px;height:12px;border-radius:3px;background:rgba(2,179,147,0.25);display:inline-block"></span>Scheduled Class</div>
              <div style="display:flex;align-items:center;gap:5px;font-size:0.72rem;color:var(--text2)"><span style="width:12px;height:12px;border-radius:3px;background:var(--g-main);display:inline-block"></span>Today</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ STUDIO ══ -->
      <div class="settings-pane" id="pane-studio">
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></div>
            <div><div class="s-card-title">Virtual Studio Settings</div><div class="s-card-sub">Configure your audio, video, and session environment</div></div>
          </div>
          <div class="s-card-body">
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Video Quality</div><div class="s-row-desc">Higher quality uses more bandwidth</div></div>
              <div class="s-row-ctrl"><select class="s-select"><option>Auto (Recommended)</option><option>720p HD</option><option>480p</option><option>360p</option></select></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Mirror My Video</div><div class="s-row-desc">Flip your camera preview (like a mirror) during sessions</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Background Blur</div><div class="s-row-desc">Automatically blur your background in video sessions</div></div>
              <div class="s-row-ctrl"><button class="toggle" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Noise Cancellation</div><div class="s-row-desc">Filter background noise from your microphone during class</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Session Join Sound</div><div class="s-row-desc">Play a chime when your class session starts</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="save-row">
              <button class="btn-primary" onclick="showToast('Studio settings saved.','success')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save Settings
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ SUBMISSIONS ══ -->
      <div class="settings-pane" id="pane-submissions">
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
            <div><div class="s-card-title">Assignment Preferences</div><div class="s-card-sub">Configure how you submit and receive work</div></div>
          </div>
          <div class="s-card-body">
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Default Submission Format</div><div class="s-row-desc">Preferred file format for submitting practice recordings</div></div>
              <div class="s-row-ctrl"><select class="s-select"><option>MP4 Video</option><option>Audio (MP3)</option><option>PDF Document</option><option>Any Format</option></select></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Submission Reminders</div><div class="s-row-desc">Remind me 24 hours before an assignment deadline</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Auto-save Drafts</div><div class="s-row-desc">Automatically save assignment drafts while you work</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Show Feedback Inline</div><div class="s-row-desc">Display teacher comments directly on your submitted work</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ══ SECURITY ══ -->
      <div class="settings-pane" id="pane-security">
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <div><div class="s-card-title">Security</div><div class="s-card-sub">Protect your account with a strong password</div></div>
          </div>
          <div class="s-card-body">
            <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:20px">
              <div><div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text2);margin-bottom:6px">Current Password</div><input type="password" class="s-input" placeholder="Enter current password"></div>
              <div><div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text2);margin-bottom:6px">New Password</div><input type="password" class="s-input" placeholder="Min. 8 characters"></div>
              <div><div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text2);margin-bottom:6px">Confirm New Password</div><input type="password" class="s-input" placeholder="Repeat new password"></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Session Alerts</div><div class="s-row-desc">Email me when a new device signs into my account</div></div>
              <div class="s-row-ctrl"><button class="toggle on" onclick="toggleSwitch(this)"></button></div>
            </div>
            <div class="save-row">
              <button class="btn-ghost">Cancel</button>
              <button class="btn-primary" onclick="showToast('Password updated successfully.','success')">Update Password</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ LANGUAGE ══ -->
      <div class="settings-pane" id="pane-language">
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
            <div><div class="s-card-title">Language & Region</div><div class="s-card-sub">Set your preferred language and time zone</div></div>
          </div>
          <div class="s-card-body">
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Interface Language</div><div class="s-row-desc">Language used across the Digital Art School platform</div></div>
              <div class="s-row-ctrl"><select class="s-select"><option>English (Default)</option><option>Malayalam</option><option>Hindi</option><option>Tamil</option><option>Telugu</option></select></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Time Zone</div></div>
              <div class="s-row-ctrl"><select class="s-select"><option>Asia/Kolkata (IST +5:30)</option><option>UTC</option><option>Asia/Dubai</option></select></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Date Format</div></div>
              <div class="s-row-ctrl"><select class="s-select"><option>DD/MM/YYYY</option><option>MM/DD/YYYY</option><option>YYYY-MM-DD</option></select></div>
            </div>
            <div class="save-row">
              <button class="btn-primary" onclick="showToast('Language & region saved.','success')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ DATA ══ -->
      <div class="settings-pane" id="pane-data">
        <div class="s-card" style="animation-delay:0.05s">
          <div class="s-card-head">
            <div class="s-card-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg></div>
            <div><div class="s-card-title">Data & Storage</div><div class="s-card-sub">Manage your data, exports, and account deletion</div></div>
          </div>
          <div class="s-card-body">
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Download My Data</div><div class="s-row-desc">Export all your profile data, submissions, and messages as a ZIP archive</div></div>
              <div class="s-row-ctrl"><button class="btn-ghost" style="padding:8px 14px;font-size:0.78rem" onclick="showToast('Your data export is being prepared. You will receive an email when ready.')">Request Export</button></div>
            </div>
            <div class="s-row">
              <div class="s-row-info"><div class="s-row-label">Clear App Cache</div><div class="s-row-desc">Remove locally cached data to free up space</div></div>
              <div class="s-row-ctrl"><button class="btn-ghost" style="padding:8px 14px;font-size:0.78rem" onclick="showToast('Cache cleared successfully.')">Clear Cache</button></div>
            </div>
            <div class="s-row" style="border-color:rgba(199,74,60,0.15)">
              <div class="s-row-info"><div class="s-row-label" style="color:#c74a3c">Delete Account</div><div class="s-row-desc">Permanently delete your account and all associated data. This cannot be undone.</div></div>
              <div class="s-row-ctrl"><button class="btn-danger" style="padding:8px 14px;font-size:0.78rem" onclick="if(confirm('Permanently delete your account? This is IRREVERSIBLE.'))showToast('Delete request submitted. You will receive a confirmation email.','error')">Delete Account</button></div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /settings-content -->
  </div><!-- /settings-grid -->
</div><!-- /settings-container -->

<!-- ================================================================
     JAVASCRIPT
================================================================ -->
<script>
(function () {
'use strict';

/* ══════════════════════════════════════════════════════════
   SKY ENGINE — living day/night atmosphere
══════════════════════════════════════════════════════════ */
var SKY_PREFS_KEY = 'das_sky_theme';
var THEME_KEY     = 'das_theme';
var ACCENT_KEY    = 'das_accent';

function getSkyState() {
  var h = new Date().getHours();
  if (h >= 5  && h < 7)  return 'dawn';
  if (h >= 7  && h < 11) return 'morning';
  if (h >= 11 && h < 17) return 'day';
  if (h >= 17 && h < 20) return 'sunset';
  return 'night';
}

var skyData = {
  dawn:    { icon:'🌅', label:'Golden Dawn',    sunTop:'55%', sunRight:'15%', moonOpacity:0, starsOpacity:0.1 },
  morning: { icon:'🌤️', label:'Morning Light',  sunTop:'25%', sunRight:'18%', moonOpacity:0, starsOpacity:0   },
  day:     { icon:'☀️',  label:'Clear Day',      sunTop:'12%', sunRight:'22%', moonOpacity:0, starsOpacity:0   },
  sunset:  { icon:'🌇', label:'Sunset Glow',    sunTop:'45%', sunRight:'8%',  moonOpacity:0, starsOpacity:0.2 },
  night:   { icon:'🌙', label:'Starlit Night',  sunTop:'80%', sunRight:'50%', moonOpacity:1, starsOpacity:1   },
};

var currentSkyState = getSkyState();
var skyEnabled = localStorage.getItem('das_sky_enabled') !== 'false';

function applySky(state) {
  var layers = ['dawn','morning','day','sunset','night'];
  layers.forEach(function(l) {
    var el = document.getElementById('sky' + l.charAt(0).toUpperCase() + l.slice(1));
    if (el) el.style.opacity = l === state ? '1' : '0';
  });
  var d = skyData[state] || skyData.day;

  // Celestial
  var sun  = document.getElementById('celSun');
  var moon = document.getElementById('celMoon');
  var stars = document.getElementById('skyStars');
  if (sun)  { sun.style.top = d.sunTop; sun.style.right = d.sunRight; }
  if (moon) { moon.style.opacity = d.moonOpacity; }
  if (stars){ stars.style.opacity = d.starsOpacity; }

  // Hero pill
  var icon  = document.getElementById('skyIcon');
  var label = document.getElementById('skyLabel');
  if (icon)  icon.textContent = d.icon;
  if (label) label.textContent = d.label + ' — ' + new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});

  // Dark theme when night/sunset (auto mode)
  var themeMode = localStorage.getItem(THEME_KEY) || 'auto';
  if (themeMode === 'auto') {
    var dark = (state === 'night' || state === 'sunset');
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    // Update theme selector UI
    document.querySelectorAll('.theme-opt').forEach(function(o){ o.classList.remove('selected'); });
    var autoOpt = document.querySelector('[data-theme-opt="auto"]');
    if (autoOpt) autoOpt.classList.add('selected');
  }
}

function setSkyVisible(on) {
  var canvas = document.querySelector('.sky-canvas');
  if (canvas) canvas.style.opacity = on ? '1' : '0';
  localStorage.setItem('das_sky_enabled', on ? 'true' : 'false');
}

// ── EXPORTED: called by toggle button ──
window.toggleSkyBg = function(btn) {
  setSkyVisible(btn.classList.contains('on'));
};

// Refresh label every minute
setInterval(function() {
  var d = skyData[currentSkyState] || skyData.day;
  var label = document.getElementById('skyLabel');
  if (label) label.textContent = d.label + ' — ' + new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
}, 60000);

// Check for sky state change every 5 min
setInterval(function() {
  var newState = getSkyState();
  if (newState !== currentSkyState) {
    currentSkyState = newState;
    var themeMode = localStorage.getItem(THEME_KEY) || 'auto';
    if (themeMode === 'auto') applySky(currentSkyState);
  }
}, 300000);

/* ══════════════════════════════════════════════════════════
   THEME
══════════════════════════════════════════════════════════ */
window.setTheme = function(mode, el) {
  localStorage.setItem(THEME_KEY, mode);
  document.querySelectorAll('.theme-opt').forEach(function(o){ o.classList.remove('selected'); });
  if (el) el.classList.add('selected');

  if (mode === 'light') {
    document.documentElement.setAttribute('data-theme','light');
    applySky(currentSkyState === 'night' ? 'day' : currentSkyState);
  } else if (mode === 'dark') {
    document.documentElement.setAttribute('data-theme','dark');
    applySky('night');
  } else { // auto
    applySky(currentSkyState);
  }
  showToast('Theme updated.');
};

/* ══════════════════════════════════════════════════════════
   ACCENT
══════════════════════════════════════════════════════════ */
var accentMap = {
  blue:   ['#0366B0','#0288d1'],
  teal:   ['#02B393','#00897b'],
  violet: ['#7c3aed','#a855f7'],
  rose:   ['#e11d48','#f43f5e'],
  amber:  ['#d97706','#f59e0b'],
};

window.setAccent = function(name, el) {
  localStorage.setItem(ACCENT_KEY, name);
  document.querySelectorAll('.accent-opt').forEach(function(o){ o.classList.remove('selected'); });
  if (el) el.classList.add('selected');
  var colors = accentMap[name];
  if (colors) {
    document.documentElement.style.setProperty('--blue', colors[0]);
    document.documentElement.style.setProperty('--g-main','linear-gradient(135deg,'+colors[0]+','+colors[1]+')');
    document.documentElement.style.setProperty('--g-hero','linear-gradient(135deg,'+colors[0]+' 0%,'+colors[1]+' 50%,#606060 100%)');
  }
  showToast('Accent colour applied.');
};

/* ══════════════════════════════════════════════════════════
   FONT SIZE
══════════════════════════════════════════════════════════ */
window.setFontSize = function(val) {
  document.documentElement.style.fontSize = val + 'px';
  var el = document.getElementById('fontSizeVal');
  if (el) el.textContent = val + 'px';
  localStorage.setItem('das_font_size', val);
};

/* ══════════════════════════════════════════════════════════
   TOGGLES
══════════════════════════════════════════════════════════ */
window.toggleSwitch = function(btn) {
  btn.classList.toggle('on');
};

/* ══════════════════════════════════════════════════════════
   PANE SWITCHING
══════════════════════════════════════════════════════════ */
window.switchPane = function(paneId, navEl) {
  document.querySelectorAll('.settings-pane').forEach(function(p){ p.classList.remove('active'); });
  document.querySelectorAll('.snav-item').forEach(function(n){ n.classList.remove('active'); });
  var pane = document.getElementById('pane-' + paneId);
  if (pane) pane.classList.add('active');
  if (navEl) navEl.classList.add('active');
};

/* ══════════════════════════════════════════════════════════
   COPY CLASS CODE
══════════════════════════════════════════════════════════ */
window.copyCode = function() {
  var code = document.getElementById('classCode');
  if (!code) return;
  navigator.clipboard.writeText(code.textContent).then(function() {
    showToast('Class code copied!');
  }).catch(function() {
    showToast('Copy failed — please copy manually.');
  });
};

/* ══════════════════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════════════════ */
window.showToast = function(msg, type) {
  var wrap = document.getElementById('toastWrap');
  var t = document.createElement('div');
  t.className = 'toast' + (type ? ' ' + type : '');
  t.textContent = msg;
  wrap.appendChild(t);
  requestAnimationFrame(function(){ requestAnimationFrame(function(){ t.classList.add('show'); }); });
  setTimeout(function(){
    t.classList.remove('show');
    setTimeout(function(){ t.remove(); }, 350);
  }, 3200);
};

/* ══════════════════════════════════════════════════════════
   LOGOUT
══════════════════════════════════════════════════════════ */
document.getElementById('logoutBtn')?.addEventListener('click', function(e) {
  e.preventDefault();
  if (confirm('Log out of Digital Art School?')) window.location.href = '../pages/logout.php';
});

/* ══════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════ */
(function init() {
  // Apply saved theme
  var savedTheme  = localStorage.getItem(THEME_KEY)  || 'auto';
  var savedAccent = localStorage.getItem(ACCENT_KEY) || 'blue';
  var savedFont   = localStorage.getItem('das_font_size');

  if (savedFont) {
    document.documentElement.style.fontSize = savedFont + 'px';
    var slider = document.getElementById('fontSizeSlider');
    var val    = document.getElementById('fontSizeVal');
    if (slider) slider.value = savedFont;
    if (val)    val.textContent = savedFont + 'px';
  }

  if (savedAccent !== 'blue') {
    var accentEl = document.querySelector('[data-accent="'+savedAccent+'"]');
    if (accentEl) setAccent(savedAccent, accentEl);
  }

  var themeEl = document.querySelector('[data-theme-opt="'+savedTheme+'"]');
  if (themeEl) setTheme(savedTheme, themeEl);
  else         applySky(currentSkyState);

  if (!skyEnabled) {
    setSkyVisible(false);
    var skyToggle = document.getElementById('toggleSky');
    if (skyToggle) skyToggle.classList.remove('on');
  }

  // Activate URL-specified pane
  var urlParams = new URLSearchParams(window.location.search);
  var tabParam  = urlParams.get('tab');
  var startPane = tabParam || 'appearance';
  var navItem   = document.querySelector('[data-pane="'+startPane+'"]');
  if (navItem) switchPane(startPane, navItem);

})();

})();
</script>
</body>
</html>
