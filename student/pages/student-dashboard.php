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
               t.teacher_name, t.specialization AS teacher_specialization
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
    'email' => $_SESSION['email'] ?? '',
    'art_category' => '',
    'art_discipline' => '',
    'skill_level' => '',
    'location' => '',
    'learning_purpose' => '',
    'years_of_experience' => 0,
    'teacher_name' => null,
    'request_status' => null,
  ];
}

function getInitials($name)
{
  $parts = preg_split('/\s+/', trim($name));
  $ini = '';
  foreach ($parts as $p) {
    if ($p)
      $ini .= strtoupper($p[0]);
  }
  return substr($ini, 0, 2) ?: 'ST';
}

$skillMap = [
  'complete_beginner' => 'Beginner',
  'basic' => 'Basic',
  'intermediate' => 'Intermediate',
  'advanced' => 'Advanced',
];
$skillDisplay = $skillMap[$student['skill_level'] ?? ''] ?? ucfirst($student['skill_level'] ?? 'Beginner');
$initials = getInitials($student['full_name']);
$firstName = explode(' ', trim($student['full_name']))[0];
$hasTeacher = !empty($student['teacher_name']);

/* ── Fetch teacher list ── */
$teacherList = [];
$tConn = getDatabaseConnection();
if ($tConn) {
  $artCat = $student['art_category'] ?? '';
  $tStmt = $tConn->prepare("SELECT * FROM teachers WHERE is_active = 1 AND specialization = ? LIMIT 8");
  $tStmt->bind_param("s", $artCat);
  $tStmt->execute();
  $tRes = $tStmt->get_result();
  while ($row = $tRes->fetch_assoc()) {
    $discs = array_values(array_filter(array_map('trim', explode(',', $row['art_disciplines'] ?? ''))));
    $parts = array_filter(explode(' ', $row['teacher_name']));
    $teacherList[] = [
      'id' => (int) $row['teacher_id'],
      'name' => $row['teacher_name'],
      'field' => $row['specialization'],
      'disciplines' => $discs,
      'exp' => ($row['experience_years'] ?? 0) . ' yrs',
      'students' => (int) ($row['current_students'] ?? 0),
      'available' => (bool) (($row['current_students'] ?? 0) < ($row['max_students'] ?? 20)),
      'initials' => implode('', array_map(fn($p) => strtoupper($p[0]), $parts)),
    ];
  }
  $tStmt->close();
  if (empty($teacherList)) {
    $tRes2 = $tConn->query("SELECT * FROM teachers WHERE is_active = 1 LIMIT 6");
    while ($row = $tRes2->fetch_assoc()) {
      $discs = array_values(array_filter(array_map('trim', explode(',', $row['art_disciplines'] ?? ''))));
      $parts = array_filter(explode(' ', $row['teacher_name']));
      $teacherList[] = [
        'id' => (int) $row['teacher_id'],
        'name' => $row['teacher_name'],
        'field' => $row['specialization'],
        'disciplines' => $discs,
        'exp' => ($row['experience_years'] ?? 0) . ' yrs',
        'students' => (int) ($row['current_students'] ?? 0),
        'available' => (bool) (($row['current_students'] ?? 0) < ($row['max_students'] ?? 20)),
        'initials' => implode('', array_map(fn($p) => strtoupper($p[0]), $parts)),
      ];
    }
  }
  closeDatabaseConnection($tConn);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Digital Art School</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* ============================================================
     CSS VARIABLES
  ============================================================ */
    :root {
      --blue: #0366B0;
      --teal: #02B393;
      --lime: #A3CE47;
      --gold: #F3C73B;
      --slate: #606060;
      --deep: #0d1f35;
      --coconut: #f4faff;
      --g-warm: linear-gradient(135deg, #0366B0 0%, #606060 60% );
      --g-hero: linear-gradient(135deg, #0376cc 0%, #606060 100%);
      --g-main: linear-gradient(135deg, #0366B0 0%, #606060 100%);
      --shadow: 0 8px 32px rgba(3, 102, 176, 0.10);
      --shadow-md: 0 16px 48px rgba(3, 102, 176, 0.14);
      --font: 'Poppins', sans-serif;
      --nav-w: 80px;
      --hdr-h: 80px;
      --r-sm: 12px;
      --r-md: 20px;
      --r-lg: 28px;
    }

    *,
    *::before,
    *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: var(--font);
      background: var(--coconut);
      color: var(--deep);
      overflow-x: hidden;
      min-height: 100vh;
      background-image: radial-gradient(rgba(3, 102, 176, 0.055) 1px, transparent 1px);
      background-size: 24px 24px;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image: url('../assets/images/community_portal_bg-52339207.png');
      background-size: contain;
      background-position: center;
      opacity: 0.96;
      pointer-events: none;
      z-index: 0;
    }

    /* ============================================================
     HEADER
  ============================================================ */
    .glass-header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: var(--hdr-h);
      background: rgba(255, 255, 255, 0.94);
      backdrop-filter: blur(6px) saturate(180%);
      border-bottom: 2px solid transparent;
      border-image: linear-gradient(90deg, #0366B0, #606060, #606060) 1;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 28px;
      z-index: 1000;
      box-shadow: 0 4px 24px rgba(3, 102, 176, 0.07);
      animation: slideDown 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideDown {
      from {
        transform: translateY(-100%);
        opacity: 0
      }

      to {
        transform: translateY(0);
        opacity: 1
      }
    }

    .header-logos {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-shrink: 0;
    }

    .logo-block {
      display: flex;
      align-items: center;
      gap: 9px;
      text-decoration: none;
      padding: 6px 10px;
      border-radius: 10px;
      transition: background 0.2s;
    }

    .logo-block:hover {
      background: rgba(3, 102, 176, 0.06);
    }

    .logo-block img {
      width: 120px;
      height: 120px;
      object-fit: contain;
    }

    .logo-text {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
    }

    .logo-name {
      font-weight: 700;
      font-size: 0.8rem;
      background: linear-gradient(90deg, #0366B0, #606060);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      white-space: nowrap;
    }

    .logo-sub {
      font-size: 0.65rem;
      font-weight: 600;
      color: #606060;
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }

    .logo-divider {
      width: 1px;
      height: 36px;
      background: linear-gradient(180deg, transparent, rgba(3, 102, 176, 0.2), transparent);
      flex-shrink: 0;
    }

    .search-container {
      flex: 1;
      max-width: 460px;
      margin: 0 40px;
    }

    .search-btn {
      all: unset;
      width: 100%;
      padding: 11px 18px;
      background: rgba(3, 102, 176, 0.05);
      border: 1px solid rgba(3, 102, 176, 0.14);
      border-radius: var(--r-sm);
      display: flex;
      align-items: center;
      gap: 10px;
      font-family: var(--font);
      font-size: 0.92rem;
      color: var(--deep);
      cursor: text;
      transition: 0.2s;
    }

    .search-btn:hover {
      background: rgba(3, 102, 176, 0.08);
      border-color: var(--teal);
    }

    .search-btn svg {
      color: var(--blue);
      flex-shrink: 0;
    }

    .search-btn span {
      color: #555;
      font-weight: 400;
      flex: 1;
    }

    .search-btn kbd {
      font-size: 0.68rem;
      padding: 2px 6px;
      background: rgba(3, 102, 176, 0.07);
      border-radius: 5px;
      color: #0366B0;
      border: 1px solid rgba(3, 102, 176, 0.15);
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-shrink: 0;
    }

    .student-identity {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .avatar-ring {
      width: 46px;
      height: 46px;
      border-radius: 18%;
      background: var(--g-warm);
      padding: 3px;
      flex-shrink: 0;
    }

    .avatar-ring .avatar-initials {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 100%;
      font-weight: 700;
      font-size: 0.82rem;
      color: white;
    }

    .student-info {
      display: flex;
      flex-direction: column;
    }

    .student-name {
      font-size: 1rem;
      font-weight: 700;
      color: var(--deep);
    }

    .student-role {
      font-size: 0.82rem;
      color: #0050a0;
      font-weight: 500;
    }

    .header-actions {
      display: flex;
      gap: 7px;
    }

    .icon-btn {
      width: 42px;
      height: 42px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.7);
      border: 1px solid rgba(13, 31, 53, 0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.25s;
      position: relative;
    }

    .icon-btn:hover {
      background: white;
      transform: translateY(-2px);
      box-shadow: var(--shadow);
    }

    .notif-dot {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 7px;
      height: 7px;
      background: var(--gold);
      border-radius: 50%;
      border: 2px solid rgba(244, 250, 255, 0.9);
      animation: pulse 2s ease-in-out infinite;
    }

    .notif-dot.hidden {
      display: none;
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
        transform: scale(1)
      }

      50% {
        opacity: 0.6;
        transform: scale(1.15)
      }
    }

    /* ============================================================
     FLOATING NAV
  ============================================================ */
    .floating-nav {
      position: fixed;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255, 255, 255, 0.93);
      backdrop-filter: blur(20px) saturate(180%);
      border-radius: var(--r-md);
      height: calc(100vh - var(--hdr-h) - 40px);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 14px;
      box-shadow: var(--shadow-md), 0 0 0 1px rgba(3, 102, 176, 0.08);
      z-index: 999;
      animation: slideInLeft 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
    }

    @keyframes slideInLeft {
      from {
        transform: translateX(-100%) translateY(-50%);
        opacity: 0
      }

      to {
        transform: translateX(0) translateY(-50%);
        opacity: 1
      }
    }

    .nav-items {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .nav-item {
      width: 52px;
      height: 52px;
      border-radius: var(--r-md);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #0050a0;
      text-decoration: none;
      position: relative;
      transition: all 0.25s;
      overflow: visible;
    }

    .nav-item::before {
      content: attr(data-label);
      position: absolute;
      left: 68px;
      background: var(--deep);
      color: var(--coconut);
      padding: 7px 14px;
      border-radius: var(--r-sm);
      font-size: 0.82rem;
      font-weight: 500;
      white-space: nowrap;
      opacity: 0;
      pointer-events: none;
      transform: translateX(-6px);
      transition: all 0.25s;
      z-index: 10;
    }

    .nav-item:hover::before {
      opacity: 1;
      transform: translateX(0);
    }

    .nav-item:hover {
      background: rgba(3, 102, 176, 0.09);
      color: var(--blue);
    }

    .nav-item.active {
      background: var(--g-main);
      color: white;
      box-shadow: 0 4px 16px rgba(3, 102, 176, 0.35);
    }

    .nav-item svg {
      transition: transform 0.25s;
    }

    .nav-item:hover svg {
      transform: scale(1.08);
    }

    .nav-logout:hover {
      background: rgba(199, 74, 60, 0.08) !important;
    }

    .nav-logout:hover svg {
      stroke: #c74a3c !important;
    }

    /* ============================================================
     MAIN GRID
  ============================================================ */
    .dashboard-container {
      margin-left: calc(var(--nav-w) + 40px);
      margin-top: calc(var(--hdr-h) + 24px);
      margin-right: 24px;
      margin-bottom: 40px;
      padding: 24px;
      display: grid;
      /* KEY: right column is narrower — 300px fixed */
      grid-template-columns: 1fr 300px;
      grid-template-rows: auto auto;
      gap: 22px;
      position: relative;
      z-index: 1;
      max-width: 1500px;
      align-items: start;
    }

    /* ============================================================
     WIDGET BASE
  ============================================================ */
    .widget {
      background: #fff;
      border-radius: var(--r-md);
      padding: 28px;
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(3, 102, 176, 0.09);
      transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
      animation: fadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    .widget:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 40px rgba(3, 102, 176, 0.12);
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(16px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .widget-label {
      display: block;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: #0050a0;
      margin-bottom: 16px;
      font-weight: 700;
    }

    .widget-glow {
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(2, 179, 147, 0.09) 0%, transparent 70%);
      opacity: 0;
      transition: opacity 0.5s;
      pointer-events: none;
    }

    .widget:hover .widget-glow {
      opacity: 1;
    }

    /* ============================================================
     WIDGET A — WELCOME
  ============================================================ */
    .widget-welcome {
      grid-column: 1;
      grid-row: 1;

      /* Gradient with transparency */
      background: linear-gradient(135deg,
          rgba(3, 102, 176, 0.88),
          rgba(2, 179, 147, 0.82));

      color: white;

      /* Glass effect */
      backdrop-filter: blur(1px);
      -webkit-backdrop-filter: blur(1px);

      /* Soft glass border */
      border: 1px solid rgba(255, 255, 255, 0.10);

      /* Better depth */
      box-shadow:
        0 8px 32px rgba(0, 0, 0, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.08);

      animation-delay: 0.1s;

      overflow: hidden;
    }

    .widget-welcome::after {
      content: '';
      position: absolute;
      width: 240px;
      height: 240px;
      border-radius: 50%;
      border: 1.5px solid rgba(255, 255, 255, 0.09);
      bottom: -60px;
      right: -60px;
      pointer-events: none;
    }

    .welcome-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
    }

    .welcome-tag {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      background: rgba(255, 255, 255, 0.18);
      padding: 5px 13px;
      border-radius: 20px;
      backdrop-filter: blur(6px);
    }

    .welcome-time {
      font-size: 0.78rem;
      opacity: 0.75;
      text-align: right;
    }

    .welcome-time span {
      display: block;
      font-size: 0.95rem;
      font-weight: 700;
      opacity: 1;
    }

    .welcome-greeting {
      font-size: 1.9rem;
      font-weight: 700;
      line-height: 1.2;
      margin-bottom: 8px;
    }

    .welcome-greeting em {
      font-style: normal;
      color: rgba(255, 255, 255, 0.82);
    }

    .welcome-quote {
      font-size: 0.88rem;
      line-height: 1.65;
      opacity: 0.82;
      margin-bottom: 22px;
      border-left: 3px solid rgba(255, 255, 255, 0.32);
      padding-left: 13px;
      font-style: italic;
    }

    .welcome-badges {
      display: flex;
      gap: 9px;
      flex-wrap: wrap;
    }

    .welcome-badge {
      display: flex;
      align-items: center;
      gap: 7px;
      background: rgba(255, 255, 255, 0.18);
      border: 1px solid rgba(255, 255, 255, 0.12);
      backdrop-filter: blur(1.5px);
    -webkit-backdrop-filter: blur(1.5px);
      border-radius: 10px;
      padding: 8px 13px;
      font-size: 0.78rem;
      font-weight: 500;
      backdrop-filter: blur(4px);
    }

    .welcome-badge svg {
      flex-shrink: 0;
      opacity: 0.88;
    }

    /* has-class state */
    .widget-welcome.has-class .welcome-quote {
      display: none;
    }

    .widget-welcome.has-class .class-row {
      display: flex;
    }

    .class-row {
      display: none;
      align-items: center;
      justify-content: space-between;
      background: rgba(255, 255, 255, 0.14);
      border-radius: var(--r-sm);
      padding: 14px;
      margin-bottom: 20px;
      backdrop-filter: blur(2px);
    }

    .class-name {
      font-size: 1rem;
      font-weight: 700;
      margin-bottom: 3px;
    }

    .class-time {
      font-size: 0.82rem;
      opacity: 0.8;
    }

    .enter-btn {
      padding: 11px 20px;
      background: white;
      color: var(--blue);
      border: none;
      border-radius: var(--r-sm);
      font-family: var(--font);
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 7px;
      transition: all 0.25s;
      white-space: nowrap;
      flex-shrink: 0;
      box-shadow: 0 4px 14px rgba(13, 31, 53, 0.14);
    }

    .enter-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(13, 31, 53, 0.22);
    }

    /* ============================================================
     ONBOARDING CHECKLIST (new — below welcome)
  ============================================================ */
    .widget-onboarding {
      grid-column: 1;
      grid-row: 2;
      animation-delay: 0.2s;
    }

    .onboard-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
    }

    .onboard-progress-pill {
      font-size: 0.72rem;
      font-weight: 700;
      padding: 3px 11px;
      border-radius: 20px;
      background: rgba(2, 179, 147, 0.1);
      color: var(--teal);
    }

    .onboard-steps {
      display: flex;
      flex-direction: column;
      gap: 9px;
    }

    .onboard-step {
      display: flex;
      align-items: center;
      gap: 13px;
      padding: 12px 14px;
      border-radius: var(--r-sm);
      border: 1px solid rgba(3, 102, 176, 0.09);
      background: rgba(244, 250, 255, 0.7);
      transition: all 0.2s;
      cursor: default;
    }

    .onboard-step.done {
      background: rgba(2, 179, 147, 0.05);
      border-color: rgba(2, 179, 147, 0.22);
    }

    .onboard-step.done .step-icon {
      background: rgba(2, 179, 147, 0.12);
      color: var(--teal);
    }

    .onboard-step.active {
      border-color: rgba(3, 102, 176, 0.28);
      background: rgba(3, 102, 176, 0.04);
    }

    .onboard-step.active .step-icon {
      background: rgba(3, 102, 176, 0.1);
      color: var(--blue);
    }

    .step-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(96, 96, 96, 0.08);
      color: var(--slate);
    }

    .step-body {
      flex: 1;
    }

    .step-title {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--deep);
      margin-bottom: 2px;
    }

    .step-desc {
      font-size: 0.75rem;
      color: var(--slate);
      line-height: 1.4;
    }

    .step-check {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1.5px solid rgba(3, 102, 176, 0.2);
      color: transparent;
    }

    .onboard-step.done .step-check {
      background: var(--teal);
      border-color: var(--teal);
      color: white;
    }

    .onboard-cta {
      margin-top: 16px;
      width: 100%;
      padding: 12px;
      background: var(--g-main);
      color: white;
      border: none;
      border-radius: var(--r-sm);
      font-family: var(--font);
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .onboard-cta:hover {
      opacity: 0.88;
    }

    /* ============================================================
     TEACHER CARDS (col 1, row 3)
  ============================================================ */
    .widget-teachers {
      grid-column: 1;
      grid-row: 3;
      animation-delay: 0.3s;
    }

    .teachers-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
    }

    .teachers-tag {
      font-size: 0.72rem;
      font-weight: 700;
      padding: 4px 11px;
      background: rgba(2, 179, 147, 0.1);
      color: var(--teal);
      border-radius: 20px;
    }

    .teacher-filter-bar {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }

    .tf-btn {
      padding: 5px 13px;
      font-size: 0.73rem;
      font-weight: 600;
      border-radius: 20px;
      border: 1px solid rgba(3, 102, 176, 0.18);
      background: none;
      color: var(--slate);
      cursor: pointer;
      font-family: var(--font);
      transition: all 0.2s;
    }

    .tf-btn.active,
    .tf-btn:hover {
      background: var(--g-main);
      color: white;
      border-color: transparent;
    }

    .teacher-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
      gap: 14px;
    }

    .teacher-card {
      background: rgba(244, 250, 255, 0.8);
      border: 1px solid rgba(3, 102, 176, 0.11);
      border-radius: var(--r-sm);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 11px;
      transition: all 0.22s;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }

    .teacher-card:hover {
      border-color: rgba(3, 102, 176, 0.3);
      box-shadow: var(--shadow);
      transform: translateY(-3px);
    }

    .teacher-card.selected {
      border-color: var(--teal);
      background: rgba(2, 179, 147, 0.05);
      box-shadow: 0 0 0 3px rgba(2, 179, 147, 0.15);
    }

    .tc-top {
      display: flex;
      align-items: center;
      gap: 11px;
    }

    .tc-avatar {
      width: 46px;
      height: 46px;
      border-radius: 13px;
      flex-shrink: 0;
      background: var(--g-main);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.82rem;
      color: white;
    }

    .tc-name {
      font-weight: 700;
      font-size: 0.92rem;
      color: var(--deep);
      margin-bottom: 1px;
    }

    .tc-field {
      font-size: 0.75rem;
      color: #0050a0;
    }

    .tc-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
    }

    .tc-tag {
      font-size: 0.68rem;
      font-weight: 600;
      padding: 2px 9px;
      border-radius: 20px;
      background: rgba(3, 102, 176, 0.08);
      color: var(--blue);
    }

    .tc-meta {
      display: flex;
      gap: 14px;
    }

    .tc-stat {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 0.73rem;
      color: #0050a0;
    }

    .tc-stat svg {
      color: var(--teal);
      flex-shrink: 0;
    }

    .tc-btn {
      width: 100%;
      padding: 8px;
      background: var(--g-main);
      color: white;
      border: none;
      border-radius: var(--r-sm);
      font-family: var(--font);
      font-size: 0.82rem;
      font-weight: 700;
      cursor: pointer;
      transition: opacity 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
    }

    .tc-btn:hover {
      opacity: 0.88;
    }

    .tc-btn.requested {
      background: rgba(2, 179, 147, 0.12);
      color: var(--teal);
      cursor: default;
    }

    /* ============================================================
     RIGHT PANEL — FLIP CARD
     The CARD itself flips. perspective is on the outer wrapper.
  ============================================================ */
    .right-panel {
      grid-column: 2;
      grid-row: 1 / span 3;
      position: sticky;
      top: calc(var(--hdr-h) + 24px);
      animation: fadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.15s both;
      /* perspective here so the card flips in 3D space */
      perspective: 1000px;
    }

    /* The flip scene = the card itself, rotating */
    .flip-scene {
      width: 100%;
      transform-style: preserve-3d;
      transition: transform 0.75s cubic-bezier(0.4, 0.15, 0.2, 1);
      position: relative;
    }

    .flip-scene.is-flipped {
      transform: rotateY(180deg);
    }

    /* Both faces share the same card shell */
    .flip-face {
      width: 100%;
      background: #fff;
      border-radius: var(--r-md);
      border: 1px solid rgba(3, 102, 176, 0.10);
      box-shadow: var(--shadow-md);
      overflow: hidden;
      backface-visibility: hidden;
      -webkit-backface-visibility: hidden;
    }

    .flip-face-front {
      position: relative;
    }

    .flip-face-back {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      transform: rotateY(180deg);
      display: flex;
      flex-direction: column;
      /* height is synced by JS */
    }

    /* Gradient top bar on both faces */
    .rp-topbar {
      background: var(--g-hero);
      padding: 16px 16px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: white;
      flex-shrink: 0;
    }

    .rp-topbar-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .rp-topbar-icon {
      width: 30px;
      height: 30px;
      border-radius: 9px;
      background: rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .rp-topbar-title {
      font-size: 0.85rem;
      font-weight: 700;
    }

    .rp-topbar-sub {
      font-size: 0.68rem;
      opacity: 0.72;
      margin-top: 1px;
    }

    .rp-topbar-btn {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.18);
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      transition: background 0.2s;
      flex-shrink: 0;
    }

    .rp-topbar-btn:hover {
      background: rgba(255, 255, 255, 0.32);
    }

    /* Front content scroll area */
    .rp-scroll {
      overflow-y: auto;
      max-height: calc(100vh - var(--hdr-h) - 48px - 62px);
      scrollbar-width: thin;
      scrollbar-color: rgba(2, 179, 147, 0.3) transparent;
    }

    /* Sections */
    .rp-sec {
      padding: 14px 16px;
    }

    .rp-sec-sep {
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(3, 102, 176, 0.1), transparent);
      margin: 0 14px;
    }

    .rp-sec-label {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.1px;
      color: var(--blue);
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .rp-sec-label svg {
      stroke: var(--teal);
    }

    /* Profile card inside front */
    .rp-profile {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 7px;
      padding: 6px 0 10px;
    }

    .rp-avatar-wrap {
      position: relative;
    }

    .rp-avatar-ring {
      width: 58px;
      height: 58px;
      border-radius: 17px;
      background: var(--g-warm);
      padding: 3px;
    }

    .rp-avatar-initials {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 100%;
      font-weight: 700;
      font-size: 0.95rem;
      color: white;
      text-decoration: none;
      border-radius: 14px;
      transition: transform 0.2s;
    }

    .rp-avatar-initials:hover {
      transform: scale(1.06);
    }

    .rp-status-pip {
      position: absolute;
      bottom: -2px;
      right: -2px;
      width: 11px;
      height: 11px;
      border-radius: 50%;
      border: 2px solid white;
    }

    .rp-status-pip.pending {
      background: var(--gold);
    }

    .rp-status-pip.active {
      background: var(--teal);
    }

    .rp-pname {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--deep);
      text-align: center;
    }

    .rp-psub {
      font-size: 0.71rem;
      color: var(--slate);
      text-align: center;
    }

    .rp-chips {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 2px;
    }

    .rp-chip {
      font-size: 0.65rem;
      font-weight: 600;
      padding: 2px 9px;
      border-radius: 20px;
    }

    .c-blue {
      background: rgba(3, 102, 176, 0.08);
      color: var(--blue);
    }

    .c-teal {
      background: rgba(2, 179, 147, 0.09);
      color: var(--teal);
    }

    .c-slate {
      background: rgba(96, 96, 96, 0.08);
      color: var(--slate);
    }

    /* Stats row */
    .rp-stats {
      display: grid;
      grid-template-columns: 1fr 1px 1fr 1px 1fr;
      border-top: 1px solid rgba(3, 102, 176, 0.09);
      border-bottom: 1px solid rgba(3, 102, 176, 0.09);
    }

    .rp-stat {
      padding: 9px 4px;
      text-align: center;
    }

    .rp-stat-val {
      display: block;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--blue);
    }

    .rp-stat-lbl {
      display: block;
      font-size: 0.6rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--slate);
      margin-top: 1px;
    }

    .rp-sep {
      background: rgba(3, 102, 176, 0.1);
    }

    /* Progress ring */
    .rp-progress-row {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .ring-wrap {
      position: relative;
      flex-shrink: 0;
    }

    .ring-wrap svg {
      transform: rotate(-90deg);
    }

    .ring-label {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .ring-pct {
      font-size: 12px;
      font-weight: 700;
      color: var(--deep);
    }

    .ring-tiny {
      font-size: 9px;
      color: var(--slate);
    }

    .prog-info {
      flex: 1;
    }

    .prog-row-title {
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--deep);
      margin-bottom: 5px;
    }

    .prog-track {
      height: 5px;
      background: rgba(3, 102, 176, 0.08);
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 4px;
    }

    .prog-fill {
      height: 100%;
      border-radius: 10px;
      background: var(--g-main);
      transition: width 1s ease;
    }

    .prog-fill.gold {
      background: linear-gradient(90deg, var(--gold), #606060);
    }

    .prog-lbl {
      font-size: 0.68rem;
      color: var(--slate);
    }

    /* Heatmap */
    .rp-heatmap {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 3px;
    }

    .hm-cell {
      aspect-ratio: 1;
      border-radius: 3px;
      background: rgba(3, 102, 176, 0.06);
    }

    .hm-cell.l1 {
      background: rgba(2, 179, 147, 0.22);
    }

    .hm-cell.l2 {
      background: rgba(2, 179, 147, 0.48);
    }

    .hm-cell.l3 {
      background: rgba(2, 179, 147, 0.78);
    }

    .streak-hdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .streak-badge {
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--gold);
      display: flex;
      align-items: center;
      gap: 3px;
    }

    /* Mini calendar */
    .mini-cal {
      user-select: none;
    }

    .cal-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 9px;
    }

    .cal-month-lbl {
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--deep);
    }

    .cal-btn {
      width: 24px;
      height: 24px;
      border-radius: 7px;
      border: none;
      background: rgba(3, 102, 176, 0.07);
      color: var(--blue);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background 0.18s;
    }

    .cal-btn:hover {
      background: rgba(3, 102, 176, 0.15);
    }

    .cal-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 1px;
    }

    .cal-dname {
      text-align: center;
      font-size: 0.6rem;
      font-weight: 700;
      text-transform: uppercase;
      color: var(--slate);
      padding: 2px 0;
    }

    .cal-d {
      text-align: center;
      padding: 4px 2px;
      border-radius: 6px;
      font-size: 0.72rem;
      font-weight: 500;
      color: var(--deep);
      cursor: pointer;
      transition: all 0.14s;
      position: relative;
    }

    .cal-d:hover:not(.today) {
      background: rgba(3, 102, 176, 0.08);
    }

    .cal-d.other {
      color: rgba(13, 31, 53, 0.2);
    }

    .cal-d.today {
      background: var(--g-main);
      color: white;
      font-weight: 700;
    }

    .cal-d.has-dot::after {
      content: '';
      position: absolute;
      bottom: 1px;
      left: 50%;
      transform: translateX(-50%);
      width: 3px;
      height: 3px;
      border-radius: 50%;
      background: var(--teal);
    }

    .cal-d.today.has-dot::after {
      background: rgba(255, 255, 255, 0.8);
    }

    .cal-d.sel:not(.today) {
      background: rgba(3, 102, 176, 0.11);
      color: var(--blue);
      font-weight: 600;
    }

    /* Upcoming events */
    .ev-item {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 7px 9px;
      border-radius: 9px;
      background: rgba(244, 250, 255, 0.8);
      border: 1px solid rgba(3, 102, 176, 0.09);
      margin-bottom: 5px;
    }

    .ev-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .ev-text {
      font-size: 0.75rem;
      color: var(--deep);
      flex: 1;
      line-height: 1.3;
    }

    .ev-tag {
      font-size: 0.62rem;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 20px;
      background: rgba(2, 179, 147, 0.1);
      color: var(--teal);
      white-space: nowrap;
    }

    /* Quick nav 2×4 grid */
    .rp-qnav {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 5px;
    }

    .rp-qnav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      padding: 9px 3px;
      border-radius: 9px;
      background: rgba(244, 250, 255, 0.8);
      border: 1px solid rgba(3, 102, 176, 0.09);
      font-size: 0.58rem;
      font-weight: 600;
      color: #0050a0;
      text-decoration: none;
      transition: all 0.2s;
    }

    .rp-qnav-item svg {
      stroke: var(--teal);
    }

    .rp-qnav-item:hover {
      background: rgba(3, 102, 176, 0.08);
      color: var(--blue);
      transform: translateY(-2px);
    }

    /* Flip CTA at bottom of front */
    .rp-chat-cta {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      padding: 11px 16px;
      margin: 0 14px 14px;
      background: var(--g-main);
      color: white;
      border: none;
      border-radius: var(--r-sm);
      font-family: var(--font);
      font-size: 0.83rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
      flex-shrink: 0;
    }

    .rp-chat-cta:hover {
      opacity: 0.88;
    }

    /* ── BACK FACE: Chatbot ── */
    .chat-msgs {
      flex: 1;
      overflow-y: auto;
      padding: 11px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-height: 260px;
      max-height: 380px;
      scrollbar-width: thin;
      scrollbar-color: rgba(2, 179, 147, 0.3) transparent;
    }

    .chat-bubble {
      display: flex;
      align-items: flex-start;
      gap: 7px;
      max-width: 90%;
    }

    .chat-bubble.user {
      align-self: flex-end;
      flex-direction: row-reverse;
    }

    .bot-ico {
      width: 22px;
      height: 22px;
      border-radius: 7px;
      flex-shrink: 0;
      background: var(--g-main);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .bubble {
      padding: 8px 11px;
      border-radius: 11px;
      font-size: 0.8rem;
      line-height: 1.5;
    }

    .bubble.bot {
      background: rgba(3, 102, 176, 0.07);
      color: var(--deep);
      border: 1px solid rgba(3, 102, 176, 0.11);
      border-bottom-left-radius: 3px;
    }

    .bubble.user {
      background: var(--g-main);
      color: white;
      border-bottom-right-radius: 3px;
    }

    .sugg-row {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
      padding: 8px 11px;
      border-top: 1px solid rgba(3, 102, 176, 0.08);
      flex-shrink: 0;
    }

    .sugg-chip {
      background: rgba(3, 102, 176, 0.06);
      border: 1px solid rgba(3, 102, 176, 0.15);
      border-radius: 20px;
      padding: 4px 10px;
      font-family: var(--font);
      font-size: 0.68rem;
      font-weight: 500;
      color: var(--blue);
      cursor: pointer;
      transition: all 0.18s;
    }

    .sugg-chip:hover {
      background: rgba(3, 102, 176, 0.12);
      transform: translateY(-1px);
    }

    .chat-input-row {
      display: flex;
      gap: 6px;
      padding: 9px 11px;
      border-top: 1px solid rgba(3, 102, 176, 0.09);
      background: rgba(244, 250, 255, 0.6);
      flex-shrink: 0;
    }

    .chat-input {
      flex: 1;
      padding: 7px 11px;
      border: 1px solid rgba(3, 102, 176, 0.18);
      border-radius: var(--r-sm);
      font-family: var(--font);
      font-size: 0.8rem;
      color: var(--deep);
      background: white;
      outline: none;
      transition: border-color 0.2s;
    }

    .chat-input:focus {
      border-color: var(--teal);
    }

    .chat-send {
      width: 33px;
      height: 33px;
      flex-shrink: 0;
      border-radius: 9px;
      background: var(--g-main);
      border: none;
      color: white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: opacity 0.2s;
    }

    .chat-send:hover {
      opacity: 0.85;
    }

    .online-pill {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 0.65rem;
      font-weight: 600;
      color: var(--teal);
    }

    .online-dot {
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: var(--teal);
      animation: pulse 2s infinite;
    }

    .typing-dots {
      display: flex;
      gap: 3px;
      padding: 2px 0;
    }

    .typing-dots span {
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: var(--slate);
      animation: tdot 1.2s ease-in-out infinite;
    }

    .typing-dots span:nth-child(2) {
      animation-delay: 0.2s;
    }

    .typing-dots span:nth-child(3) {
      animation-delay: 0.4s;
    }

    @keyframes tdot {

      0%,
      60%,
      100% {
        transform: translateY(0)
      }

      30% {
        transform: translateY(-4px)
      }
    }

    /* ============================================================
     MODALS & OVERLAYS
  ============================================================ */
    /* Teacher request modal */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(13, 31, 53, 0.44);
      backdrop-filter: blur(6px);
      z-index: 1300;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s;
    }

    .modal-overlay.open {
      opacity: 1;
      pointer-events: auto;
    }

    .modal-box {
      background: white;
      border-radius: var(--r-lg);
      width: min(92vw, 470px);
      box-shadow: 0 32px 80px rgba(13, 31, 53, 0.2);
      overflow: hidden;
      transform: translateY(14px);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-overlay.open .modal-box {
      transform: translateY(0);
    }

    .modal-head {
      background: var(--g-hero);
      padding: 24px 26px 20px;
      color: white;
      display: flex;
      align-items: center;
      gap: 13px;
    }

    .modal-head-avatar {
      width: 48px;
      height: 48px;
      border-radius: 13px;
      flex-shrink: 0;
      background: rgba(255, 255, 255, 0.24);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.88rem;
      color: white;
    }

    .modal-head-title {
      font-size: 1.05rem;
      font-weight: 700;
      margin-bottom: 2px;
    }

    .modal-head-sub {
      font-size: 0.78rem;
      opacity: 0.8;
    }

    .modal-body {
      padding: 22px 26px;
    }

    .modal-body p {
      font-size: 0.85rem;
      color: #0050a0;
      line-height: 1.6;
      margin-bottom: 14px;
    }

    .modal-textarea {
      width: 100%;
      padding: 11px 13px;
      border: 1.5px solid rgba(3, 102, 176, 0.18);
      border-radius: var(--r-sm);
      font-family: var(--font);
      font-size: 0.85rem;
      color: var(--deep);
      resize: vertical;
      min-height: 85px;
      outline: none;
      transition: border-color 0.2s;
    }

    .modal-textarea:focus {
      border-color: var(--teal);
    }

    .modal-actions {
      display: flex;
      gap: 9px;
      margin-top: 15px;
    }

    .modal-cancel {
      flex: 1;
      padding: 11px;
      background: none;
      border: 1.5px solid rgba(3, 102, 176, 0.18);
      border-radius: var(--r-sm);
      font-family: var(--font);
      font-size: 0.85rem;
      font-weight: 600;
      color: #0050a0;
      cursor: pointer;
      transition: all 0.2s;
    }

    .modal-cancel:hover {
      border-color: var(--blue);
    }

    .modal-send {
      flex: 2;
      padding: 11px;
      background: var(--g-main);
      border: none;
      border-radius: var(--r-sm);
      font-family: var(--font);
      font-size: 0.88rem;
      font-weight: 700;
      color: white;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .modal-send:hover {
      opacity: 0.9;
    }

    /* Notification panel */
    .notif-panel {
      position: fixed;
      width: 350px;
      background: white;
      border-radius: var(--r-md);
      box-shadow: 0 20px 60px rgba(3, 102, 176, 0.14);
      border: 1px solid rgba(3, 102, 176, 0.11);
      z-index: 9999;
      opacity: 0;
      transform: translateY(-8px) scale(0.97);
      pointer-events: none;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      overflow: hidden;
    }

    .notif-panel.open {
      opacity: 1;
      transform: translateY(0) scale(1);
      pointer-events: auto;
    }

    .notif-hd {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 14px 17px;
      border-bottom: 1px solid rgba(3, 102, 176, 0.08);
    }

    .notif-hd-title {
      font-weight: 700;
      font-size: 0.92rem;
      color: var(--deep);
      flex: 1;
    }

    .notif-badge {
      font-size: 0.68rem;
      font-weight: 700;
      padding: 2px 8px;
      background: rgba(3, 102, 176, 0.1);
      color: var(--blue);
      border-radius: 20px;
    }

    .notif-mark {
      font-size: 0.7rem;
      color: var(--teal);
      background: none;
      border: none;
      cursor: pointer;
      text-decoration: underline;
    }

    .notif-list {
      max-height: 300px;
      overflow-y: auto;
    }

    .notif-item {
      display: flex;
      gap: 10px;
      padding: 11px 17px;
      cursor: pointer;
      transition: background 0.15s;
    }

    .notif-item:hover {
      background: rgba(3, 102, 176, 0.03);
    }

    .notif-item.unread {
      background: rgba(3, 102, 176, 0.04);
    }

    .notif-dot-sm {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: transparent;
      flex-shrink: 0;
      margin-top: 6px;
    }

    .notif-item.unread .notif-dot-sm {
      background: var(--teal);
    }

    .notif-text {
      font-size: 0.82rem;
      color: var(--deep);
      margin-bottom: 2px;
    }

    .notif-time {
      font-size: 0.7rem;
      color: var(--slate);
    }

    /* Profile dropdown */
    .profile-menu {
      position: fixed;
      width: 220px;
      background: white;
      border-radius: var(--r-sm);
      box-shadow: 0 20px 60px rgba(3, 102, 176, 0.14);
      border: 1px solid rgba(3, 102, 176, 0.1);
      z-index: 9999;
      opacity: 0;
      transform: translateY(-8px) scale(0.97);
      pointer-events: none;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      overflow: hidden;
    }

    .profile-menu.open {
      opacity: 1;
      transform: translateY(0) scale(1);
      pointer-events: auto;
    }

    .pm-head {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 14px 15px;
      background: linear-gradient(135deg, rgba(3, 102, 176, 0.07), rgba(2, 179, 147, 0.04));
      border-bottom: 1px solid rgba(3, 102, 176, 0.08);
    }

    .pm-avatar {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: var(--g-main);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.78rem;
      color: white;
      flex-shrink: 0;
    }

    .pm-name {
      font-weight: 700;
      font-size: 0.85rem;
      color: var(--deep);
    }

    .pm-role {
      font-size: 0.7rem;
      color: #606060;
      margin-top: 1px;
    }

    .pm-items {
      padding: 5px 0;
    }

    .pm-item {
      display: flex;
      align-items: center;
      gap: 9px;
      padding: 9px 15px;
      font-size: 0.83rem;
      color: var(--deep);
      text-decoration: none;
      cursor: pointer;
      transition: background 0.14s;
    }

    .pm-item:hover {
      background: rgba(3, 102, 176, 0.05);
    }

    .pm-item.danger {
      color: #c74a3c;
    }

    .pm-item.danger:hover {
      background: rgba(199, 74, 60, 0.06);
    }

    .pm-div {
      height: 1px;
      background: rgba(3, 102, 176, 0.07);
      margin: 4px 0;
    }

    /* Search overlay */
    .search-overlay {
      position: fixed;
      inset: 0;
      background: rgba(13, 31, 53, 0.4);
      backdrop-filter: blur(5px);
      z-index: 9998;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding-top: 14vh;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s;
    }

    .search-overlay.open {
      opacity: 1;
      pointer-events: auto;
    }

    .search-modal {
      width: 520px;
      background: white;
      border-radius: var(--r-md);
      box-shadow: 0 32px 80px rgba(3, 102, 176, 0.2);
      overflow: hidden;
      transform: translateY(-14px);
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .search-overlay.open .search-modal {
      transform: translateY(0);
    }

    .search-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 16px 17px;
      border-bottom: 1px solid rgba(3, 102, 176, 0.1);
    }

    .search-row svg {
      color: var(--blue);
      flex-shrink: 0;
    }

    #globalSearchInput {
      flex: 1;
      border: none;
      outline: none;
      font-family: var(--font);
      font-size: 0.96rem;
      color: var(--deep);
      background: transparent;
    }

    .search-row kbd {
      font-size: 0.68rem;
      padding: 2px 6px;
      background: rgba(3, 102, 176, 0.06);
      border-radius: 5px;
      color: var(--blue);
      border: 1px solid rgba(3, 102, 176, 0.15);
    }

    .search-sugg {
      padding: 10px 8px 13px;
    }

    .search-group {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #606060;
      padding: 4px 11px 7px;
    }

    .search-item {
      display: block;
      padding: 8px 11px;
      border-radius: 9px;
      font-size: 0.85rem;
      color: var(--deep);
      text-decoration: none;
      transition: background 0.14s;
    }

    .search-item:hover {
      background: rgba(3, 102, 176, 0.05);
    }

    /* ============================================================
     TOAST
  ============================================================ */
    .toast-wrap {
      position: fixed;
      bottom: 28px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 10000;
      display: flex;
      flex-direction: column;
      gap: 8px;
      align-items: center;
    }

    .toast {
      background: var(--deep);
      color: white;
      padding: 10px 20px;
      border-radius: 40px;
      font-size: 0.82rem;
      font-weight: 500;
      white-space: nowrap;
      opacity: 0;
      transform: translateY(12px);
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      pointer-events: none;
    }

    .toast.show {
      opacity: 1;
      transform: translateY(0);
    }

    .toast.success {
      background: var(--teal);
    }

    .toast.error {
      background: #c74a3c;
    }

    /* ============================================================
     RESPONSIVE
  ============================================================ */
    @media (max-width:1150px) {
      .dashboard-container {
        grid-template-columns: 1fr;
      }

      .right-panel {
        grid-column: 1;
        grid-row: auto;
        position: static;
      }

      .rp-scroll {
        max-height: none;
      }

      .widget-teachers {
        grid-row: auto;
      }

      .widget-onboarding {
        grid-row: auto;
      }
    }

    @media (max-width:768px) {
      .search-container {
        display: none;
      }

      .floating-nav {
        left: 10px;
      }

      .dashboard-container {
        margin-left: 76px;
        margin-right: 10px;
        padding: 16px;
      }

      .welcome-greeting {
        font-size: 1.5rem;
      }
    }
  </style>
</head>

<body>

  <!-- ================================================================
     TOAST CONTAINER
================================================================ -->
  <div class="toast-wrap" id="toastWrap"></div>

  <!-- ================================================================
     GLASS HEADER
================================================================ -->
  <header class="glass-header">
    <div class="header-logos">
      <a href="#" class="logo-block" title="Digital University Kerala">
        <img src="../assets/images/DUK Logo.png" alt="DUK"
          onerror="this.parentElement.querySelector('.logo-text').style.display='flex'; this.style.display='none'">
        <div class="logo-text" style="display:none">
          <span class="logo-name">Digital University<br>Kerala</span>
        </div>
      </a>
      <div class="logo-divider"></div>
      <a href="student-dashboard.php" class="logo-block" title="Digital Art School">
        <img src="../assets/images/cdtc_logo.png" alt="Digital Art School"
          onerror="this.parentElement.querySelector('.logo-text').style.display='flex'; this.style.display='none'">
        <div class="logo-text" style="display:none">
          <span class="logo-name">Digital Art School</span>
          <span class="logo-sub">Student Portal</span>
        </div>
      </a>
    </div>

    <div class="search-container">
      <button class="search-btn" id="searchBtn">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <span>Search classes, teachers, resources…</span>
        <kbd>Ctrl K</kbd>
      </button>
    </div>

    <div class="header-right">
      <div class="student-identity">
        <div class="avatar-ring">
          <div class="avatar-initials"><?= htmlspecialchars($initials) ?></div>
        </div>
        <div class="student-info">
          <div class="student-name"><?= htmlspecialchars($student['full_name']) ?></div>
          <div class="student-role">
            <?= htmlspecialchars($student['art_discipline'] ?: ($student['art_category'] ?: 'Art Student')) ?></div>
        </div>
      </div>
      <div class="header-actions">
        <button class="icon-btn" id="notifBtn" aria-label="Notifications">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          <span class="notif-dot" id="notifDot"></span>
        </button>
        <button class="icon-btn" id="profileBtn" aria-label="Profile">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- ================================================================
     FLOATING NAV
================================================================ -->
  <nav class="floating-nav" aria-label="Main navigation">
    <div class="nav-items">
      <a href="student-dashboard.php" class="nav-item active" data-label="Dashboard">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" rx="1" />
          <rect x="14" y="3" width="7" height="7" rx="1" />
          <rect x="3" y="14" width="7" height="7" rx="1" />
          <rect x="14" y="14" width="7" height="7" rx="1" />
        </svg>
      </a>
      <a href="student-schedule.php" class="nav-item" data-label="My Schedule">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
      </a>
      <a href="student-courses.php" class="nav-item" data-label="My Courses">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
          <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
        </svg>
      </a>
      <a href="student-progress.php" class="nav-item" data-label="My Progress">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
        </svg>
      </a>
      <a href="student-profile.php" class="nav-item" data-label="My Profile">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
          <circle cx="12" cy="7" r="4" />
        </svg>
      </a>
      <a href="student-settings.php" class="nav-item" data-label="Settings">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="3" />
          <path
            d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l-.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
        </svg>
      </a>
      <a href="#" class="nav-item nav-logout" id="logoutBtn" data-label="Log Out">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="rgba(199,74,60,0.65)" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
      </a>
    </div>
  </nav>

  <!-- ================================================================
     MAIN CONTENT
================================================================ -->
  <main class="dashboard-container">

    <!-- ── WIDGET A: WELCOME ── -->
    <div class="widget widget-welcome<?= $hasTeacher ? ' has-class' : '' ?>" id="welcomeWidget"
      style="animation-delay:0.3s">
      <div class="widget-glow"></div>
      <div class="welcome-top">
        <span class="welcome-tag"><?= $hasTeacher ? 'Enrolled' : 'New Student' ?></span>
        <div class="welcome-time">
          <div id="welcomeDate"></div>
          <span id="welcomeClock"></span>
        </div>
      </div>

      <h2 class="welcome-greeting">
        <?= $hasTeacher ? 'Welcome back,' : 'Welcome,' ?>
        <em><?= htmlspecialchars($firstName) ?></em>
      </h2>

      <div class="welcome-quote" id="welcomeQuote">
        "Every master was once a beginner. Your journey in the arts starts here — one practice session at a time."
      </div>

      <div class="class-row" id="classRow">
        <div>
          <div class="class-name" id="className">
            <?= $hasTeacher ? htmlspecialchars($student['teacher_name'] . ' — Class') : '—' ?>
          </div>
          <div class="class-time">Check your schedule for timings</div>
        </div>
        <button class="enter-btn" id="enterStudioBtn">
          Enter Studio
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="5" y1="12" x2="19" y2="12" />
            <polyline points="12 5 19 12 12 19" />
          </svg>
        </button>
      </div>

      <div class="welcome-badges">
        <?php if ($hasTeacher): ?>
          <div class="welcome-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
            </svg>
            Teacher: <?= htmlspecialchars($student['teacher_name']) ?>
          </div>
        <?php else: ?>
          <div class="welcome-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z" />
            </svg>
            Select a teacher to begin
          </div>
        <?php endif; ?>
        <?php if (!empty($student['art_category'])): ?>
          <div class="welcome-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 8v4l3 3" />
            </svg>
            <?= htmlspecialchars($student['art_category']) ?>
          </div>
        <?php endif; ?>
        <?php if ($skillDisplay): ?>
          <div class="welcome-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
            </svg>
            <?= htmlspecialchars($skillDisplay) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── RIGHT PANEL — FLIP CARD ── -->
    <div class="right-panel" id="rightPanel">
      <div class="flip-scene" id="flipScene">

        <!-- ═══ FRONT FACE ═══ -->
        <div class="flip-face flip-face-front">

          <!-- Top bar -->
          <div class="rp-topbar">
            <div class="rp-topbar-left">
              <div class="rp-topbar-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
              </div>
              <div>
                <div class="rp-topbar-title"><?= htmlspecialchars($student['full_name']) ?></div>
                <div class="rp-topbar-sub">Student Portal</div>
              </div>
            </div>
            <button class="rp-topbar-btn" id="openChatBtn" title="Open AI Assistant" aria-label="Open AI Assistant">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
              </svg>
            </button>
          </div>

          <div class="rp-scroll">

            <!-- Profile -->
            <div class="rp-sec">
              <div class="rp-profile">
                <div class="rp-avatar-wrap">
                  <div class="rp-avatar-ring">
                    <a href="student-profile.php" class="rp-avatar-initials"><?= htmlspecialchars($initials) ?></a>
                  </div>
                  <span class="rp-status-pip <?= $hasTeacher ? 'active' : 'pending' ?>"
                    title="<?= $hasTeacher ? 'Enrolled' : 'Awaiting teacher' ?>"></span>
                </div>
                <div class="rp-pname"><?= htmlspecialchars($student['full_name']) ?></div>
                <div class="rp-psub"><?= htmlspecialchars($student['email']) ?></div>
                <div class="rp-chips">
                  <?php if (!empty($student['art_discipline']) || !empty($student['art_category'])): ?>
                    <span
                      class="rp-chip c-blue"><?= htmlspecialchars($student['art_discipline'] ?: $student['art_category']) ?></span>
                  <?php endif; ?>
                  <?php if ($skillDisplay): ?>
                    <span class="rp-chip c-teal"><?= htmlspecialchars($skillDisplay) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($student['location'])): ?>
                    <span class="rp-chip c-slate"><?= htmlspecialchars($student['location']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Stats -->
            <div class="rp-stats">
              <div class="rp-stat">
                <span class="rp-stat-val"><?= $hasTeacher ? '1' : '0' ?></span>
                <span class="rp-stat-lbl">Teacher</span>
              </div>
              <div class="rp-sep"></div>
              <div class="rp-stat">
                <span class="rp-stat-val"><?= htmlspecialchars($skillDisplay ?: '—') ?></span>
                <span class="rp-stat-lbl">Level</span>
              </div>
              <div class="rp-sep"></div>
              <div class="rp-stat">
                <span class="rp-stat-val"><?= number_format((float) ($student['years_of_experience'] ?? 0), 0) ?>y</span>
                <span class="rp-stat-lbl">Exp.</span>
              </div>
            </div>

            <!-- Progress ring -->
            <div class="rp-sec">
              <div class="rp-sec-label">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
                My Progress
              </div>
              <div class="rp-progress-row">
                <div class="ring-wrap">
                  <svg width="60" height="60" viewBox="0 0 60 60">
                    <circle cx="30" cy="30" r="24" fill="none" stroke="rgba(3,102,176,0.09)" stroke-width="6" />
                    <circle cx="30" cy="30" r="24" fill="none" stroke="url(#rg)" stroke-width="6" stroke-linecap="round"
                      stroke-dasharray="150.8" stroke-dashoffset="132.7" />
                    <defs>
                      <linearGradient id="rg" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#0366B0" />
                        <stop offset="100%" stop-color="#02B393" />
                      </linearGradient>
                    </defs>
                  </svg>
                  <div class="ring-label">
                    <div class="ring-pct">12%</div>
                    <div class="ring-tiny">done</div>
                  </div>
                </div>
                <div class="prog-info">
                  <div class="prog-row-title">Profile Setup</div>
                  <div class="prog-track">
                    <div class="prog-fill" style="width:12%"></div>
                  </div>
                  <div class="prog-lbl">Assign a teacher to advance</div>
                  <div style="margin-top:7px">
                    <div class="prog-row-title">Orientation</div>
                    <div class="prog-track">
                      <div class="prog-fill gold" style="width:60%"></div>
                    </div>
                    <div class="prog-lbl">60% complete</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="rp-sec-sep"></div>

            <!-- Streak / heatmap -->
            <div class="rp-sec">
              <div class="streak-hdr">
                <div class="rp-sec-label" style="margin-bottom:0">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2">
                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                  </svg>
                  Practice Activity
                </div>
                <span class="streak-badge">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                    <path
                      d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"
                      fill="none" stroke="currentColor" stroke-width="0" />
                    <path
                      d="M17 12c0-2.76-2.24-5-5-5s-5 2.24-5 5c0 1.74.89 3.27 2.24 4.17L12 22l2.76-5.83C16.11 15.27 17 13.74 17 12z"
                      fill="#F3C73B" />
                  </svg>
                  3-day streak
                </span>
              </div>
              <div class="rp-heatmap" id="rpHeatmap"></div>
            </div>

            <div class="rp-sec-sep"></div>

            <!-- Calendar -->
            <div class="rp-sec">
              <div class="rp-sec-label">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2">
                  <rect x="3" y="4" width="18" height="18" rx="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                Calendar
              </div>
              <div class="mini-cal">
                <div class="cal-nav">
                  <button class="cal-btn" id="calPrev" aria-label="Previous month">‹</button>
                  <span class="cal-month-lbl" id="calLabel"></span>
                  <button class="cal-btn" id="calNext" aria-label="Next month">›</button>
                </div>
                <div class="cal-grid" id="calGrid"></div>
              </div>

              <!-- Upcoming events -->
              <div style="margin-top:12px">
                <?php if ($hasTeacher): ?>
                  <div class="ev-item">
                    <span class="ev-dot" style="background:var(--teal)"></span>
                    <span class="ev-text">Class with <?= htmlspecialchars($student['teacher_name']) ?></span>
                    <span class="ev-tag">Scheduled</span>
                  </div>
                <?php else: ?>
                  <div class="ev-item">
                    <span class="ev-dot" style="background:var(--teal)"></span>
                    <span class="ev-text">Select a teacher to unlock classes</span>
                    <span class="ev-tag">Action</span>
                  </div>
                  <div class="ev-item">
                    <span class="ev-dot" style="background:var(--gold)"></span>
                    <span class="ev-text">Welcome & orientation session</span>
                    <span class="ev-tag">TBD</span>
                  </div>
                  <div class="ev-item">
                    <span class="ev-dot" style="background:var(--blue)"></span>
                    <span class="ev-text">Art form theory — Module 1</span>
                    <span class="ev-tag">Self-paced</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="rp-sec-sep"></div>

            <!-- Quick nav 2×4 -->
            <div class="rp-sec">
              <div class="rp-sec-label">Quick Access</div>
              <div class="rp-qnav">
                <a href="student-schedule.php" class="rp-qnav-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                  Schedule
                </a>
                <a href="student-courses.php" class="rp-qnav-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                  </svg>
                  Courses
                </a>
                <a href="student-progress.php" class="rp-qnav-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                  </svg>
                  Progress
                </a>
                <a href="student-settings.php" class="rp-qnav-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3" />
                    <path
                      d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                  </svg>
                  Settings
                </a>
                <a href="student-courses.php?tab=videos" class="rp-qnav-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="23 7 16 12 23 17 23 7" />
                    <rect x="1" y="5" width="15" height="14" rx="2" />
                  </svg>
                  Lessons
                </a>
                <a href="student-profile.php?tab=badges" class="rp-qnav-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="6" />
                    <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
                  </svg>
                  Badges
                </a>
                <a href="student-messages.php" class="rp-qnav-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                  </svg>
                  Messages
                </a>
                <a href="student-help.php" class="rp-qnav-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                  </svg>
                  Help
                </a>
              </div>
            </div>

          </div><!-- /rp-scroll -->

          <!-- Chat CTA at bottom -->
          <button class="rp-chat-cta" id="openChatBtn2">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
              <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z" />
            </svg>
            Chat with AI learning assistant
          </button>

        </div><!-- /flip-face-front -->

        <!-- ═══ BACK FACE: AI Chatbot ═══ -->
        <div class="flip-face flip-face-back" id="chatFace">

          <div class="rp-topbar">
            <div class="rp-topbar-left">
              <div class="rp-topbar-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
                  <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z" />
                </svg>
              </div>
              <div>
                <div class="rp-topbar-title">AI Learning Assistant</div>
                <div class="online-pill">
                  <span class="online-dot"></span>Online
                </div>
              </div>
            </div>
            <button class="rp-topbar-btn" id="closeChatBtn" title="Back to profile" aria-label="Back to profile">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>

          <!-- Suggested prompts -->
          <div class="sugg-row" id="chatSuggestions">
            <button class="sugg-chip" onclick="rpSendChat(this.textContent.trim(),true)">How do I pick a
              teacher?</button>
            <button class="sugg-chip" onclick="rpSendChat(this.textContent.trim(),true)">Practice tips for
              beginners</button>
            <button class="sugg-chip" onclick="rpSendChat(this.textContent.trim(),true)">What art forms are
              here?</button>
            <button class="sugg-chip" onclick="rpSendChat(this.textContent.trim(),true)">How does enrolment
              work?</button>
          </div>

          <div class="chat-msgs" id="chatMessages">
            <div class="chat-bubble">
              <div class="bot-ico">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
                  <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z" />
                </svg>
              </div>
              <div class="bubble bot">Hello <?= htmlspecialchars($firstName) ?>! I'm your AI assistant. Ask me about
                choosing a teacher, your <?= htmlspecialchars($student['art_discipline'] ?: 'art') ?> practice, or how
                classes work here.</div>
            </div>
          </div>

          <div class="chat-input-row">
            <input type="text" class="chat-input" id="chatInput" placeholder="Ask me anything…"
              aria-label="Chat message">
            <button class="chat-send" id="chatSendBtn" aria-label="Send">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="22" y1="2" x2="11" y2="13" />
                <polygon points="22 2 15 22 11 13 2 9 22 2" />
              </svg>
            </button>
          </div>

        </div><!-- /flip-face-back -->

      </div><!-- /flip-scene -->
    </div><!-- /right-panel -->

    <!-- ── WIDGET B: ONBOARDING CHECKLIST ── -->
    <div class="widget widget-onboarding" style="animation-delay:0.2s">
      <div class="widget-glow"></div>
      <div class="onboard-header">
        <span class="widget-label" style="margin-bottom:0">Getting Started</span>
        <span class="onboard-progress-pill" id="onboardPill">1 of 5 done</span>
      </div>

      <div class="onboard-steps" id="onboardSteps">
        <div class="onboard-step done">
          <div class="step-icon">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
          </div>
          <div class="step-body">
            <div class="step-title">Create your account</div>
            <div class="step-desc">Register and set up your student profile</div>
          </div>
          <div class="step-check">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </div>
        </div>

        <div class="onboard-step active">
          <div class="step-icon">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>
          <div class="step-body">
            <div class="step-title">Request a teacher</div>
            <div class="step-desc">Browse teachers and send your first request</div>
          </div>
          <div class="step-check"></div>
        </div>

        <div class="onboard-step">
          <div class="step-icon">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </div>
          <div class="step-body">
            <div class="step-title">Schedule your first class</div>
            <div class="step-desc">Confirm your session time with your teacher</div>
          </div>
          <div class="step-check"></div>
        </div>

        <div class="onboard-step">
          <div class="step-icon">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="23 7 16 12 23 17 23 7" />
              <rect x="1" y="5" width="15" height="14" rx="2" />
            </svg>
          </div>
          <div class="step-body">
            <div class="step-title">Attend your first lesson</div>
            <div class="step-desc">Join the virtual studio when your class begins</div>
          </div>
          <div class="step-check"></div>
        </div>

        <div class="onboard-step">
          <div class="step-icon">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
            </svg>
          </div>
          <div class="step-body">
            <div class="step-title">Submit your first assignment</div>
            <div class="step-desc">Upload a practice video or written work</div>
          </div>
          <div class="step-check"></div>
        </div>
      </div>

      <button class="onboard-cta"
        onclick="document.getElementById('teacherWidget').scrollIntoView({behavior:'smooth'})">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        Browse &amp; request a teacher
      </button>
    </div>

    <!-- ── WIDGET C: TEACHER SELECTION ── -->
    <div class="widget widget-teachers" id="teacherWidget" style="animation-delay:0.3s">
      <div class="widget-glow"></div>
      <div class="teachers-header">
        <span class="widget-label" style="margin-bottom:0">Find Your Teacher</span>
        <span class="teachers-tag"><?= htmlspecialchars($student['art_category'] ?: 'All Disciplines') ?></span>
      </div>

      <?php if ($hasTeacher): ?>
        <p style="font-size:0.84rem;color:var(--teal);margin-bottom:18px;font-weight:600;">
          ✓ You are enrolled with <?= htmlspecialchars($student['teacher_name']) ?>
          (<?= htmlspecialchars($student['teacher_specialization'] ?? '') ?>)
        </p>
      <?php else: ?>
        <p style="font-size:0.83rem;color:#0050a0;margin-bottom:16px;line-height:1.6;">
          Browse teachers in your art form and send a request. Your teacher will review your intro and confirm your
          enrolment.
        </p>
      <?php endif; ?>

      <!-- Filter bar -->
      <div class="teacher-filter-bar" id="teacherFilterBar">
        <button class="tf-btn active" data-filter="all">All</button>
        <button class="tf-btn" data-filter="available">Available</button>
      </div>

      <div class="teacher-cards-grid" id="teacherCardsGrid"></div>
    </div>

  </main>

  <!-- ================================================================
     TEACHER REQUEST MODAL
================================================================ -->
  <div class="modal-overlay" id="requestModal" role="dialog" aria-modal="true">
    <div class="modal-box">
      <div class="modal-head">
        <div class="modal-head-avatar" id="modalAvatar">T</div>
        <div>
          <div class="modal-head-title" id="modalTeacherName">Teacher</div>
          <div class="modal-head-sub" id="modalTeacherField">Specialisation</div>
        </div>
      </div>
      <div class="modal-body">
        <p>Write a short introduction. Share your background, goals, and why you'd like to learn from this teacher.</p>
        <textarea class="modal-textarea" id="requestMessage"
          placeholder="Hello, I am new to this art form and would love to learn the fundamentals from you…"></textarea>
        <div class="modal-actions">
          <button class="modal-cancel" id="modalCancel">Cancel</button>
          <button class="modal-send" id="modalSend">Send Request</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ================================================================
     NOTIFICATION PANEL
================================================================ -->
  <div class="notif-panel" id="notifPanel" role="dialog" aria-label="Notifications">
    <div class="notif-hd">
      <span class="notif-hd-title">Notifications</span>
      <span class="notif-badge" id="notifBadge">0 new</span>
      <button class="notif-mark" id="markAllRead">Mark all read</button>
    </div>
    <div class="notif-list" id="notifList">
      <div style="padding:18px;text-align:center;font-size:0.82rem;color:var(--slate)">No notifications yet.</div>
    </div>
  </div>

  <!-- ================================================================
     PROFILE DROPDOWN
================================================================ -->
  <div class="profile-menu" id="profileMenu" role="menu">
    <div class="pm-head">
      <div class="pm-avatar"><?= htmlspecialchars($initials) ?></div>
      <div>
        <div class="pm-name"><?= htmlspecialchars($student['full_name']) ?></div>
        <div class="pm-role"><?= htmlspecialchars($student['art_discipline'] ?: 'Art Student') ?></div>
      </div>
    </div>
    <div class="pm-items">
      <a href="student-profile.php" class="pm-item" role="menuitem">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
          <circle cx="12" cy="7" r="4" />
        </svg>
        My Profile
      </a>
      <a href="student-settings.php" class="pm-item" role="menuitem">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="3" />
          <path
            d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
        </svg>
        Settings
      </a>
      <div class="pm-div"></div>
      <a href="#" class="pm-item danger" id="pmLogout" role="menuitem">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
        Log Out
      </a>
    </div>
  </div>

  <!-- ================================================================
     SEARCH OVERLAY
================================================================ -->
  <div class="search-overlay" id="searchOverlay" role="dialog" aria-label="Search">
    <div class="search-modal">
      <div class="search-row">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input type="search" id="globalSearchInput" placeholder="Search teachers, classes, resources…"
          autocomplete="off">
        <kbd>Esc</kbd>
      </div>
      <div class="search-sugg">
        <div class="search-group">Quick Links</div>
        <a href="student-dashboard.php" class="search-item">Dashboard</a>
        <a href="student-schedule.php" class="search-item">My Schedule</a>
        <a href="student-courses.php" class="search-item">My Courses</a>
        <a href="student-profile.php" class="search-item">My Profile</a>
        <a href="student-progress.php" class="search-item">My Progress</a>
        <a href="student-settings.php" class="search-item">Settings</a>
      </div>
    </div>
  </div>

  <!-- ================================================================
     JAVASCRIPT
================================================================ -->
  <script>
    (function () {
      'use strict';

      /* ── DATA ──────────────────────────────────────────────────── */
      const TEACHERS = <?= json_encode($teacherList) ?>;
      const STUDENT_FIRST = <?= json_encode($firstName) ?>;

      const QUOTES = [
        '"Every master was once a beginner. Your journey starts with a single step — today is that step."',
        '"Art is not what you see, but what you make others see. Your eye is already trained by curiosity."',
        '"In all art forms, the joy is in the doing — every practice, every stumble, every small win counts."',
        '"Discipline and consistency are the twin pillars of mastery in any creative field."',
        '"Tradition is not the worship of ashes, but the preservation of fire. You carry it forward."',
        '"Creativity takes courage. Showing up every day is already an act of bravery."',
      ];

      const AI_REPLIES = [
        "Great question! Look for teachers whose specialisation matches your art form and current skill level. A good intro message about your goals goes a long way.",
        "For any art discipline, consistency beats duration. Even 20 focused minutes daily — whether it's scales, footwork, brush strokes, or vocal warmups — compounds into real mastery.",
        "The Digital Art School covers classical and contemporary dance, Indian and Western music, visual arts (painting, sculpture, printmaking, photography), theatre, and more. Use the filter on teacher cards to explore.",
        "Once you send a request with a short intro, the teacher reviews it. After acceptance your first session gets scheduled and appears on your dashboard calendar.",
        "Progress here is tracked through practice logs, assignments, and video submissions. Your teacher sends personalised written feedback after each submission.",
        "A universal practice tip: record yourself and watch it back. Self-observation is the fastest learning accelerator across every art form.",
        "You can explore self-paced theory modules while waiting for a teacher — they cover fundamentals of your chosen discipline and count toward your profile score.",
        "Don't be discouraged by slow early progress — the most visible growth usually comes after the first 3–4 weeks of consistent practice.",
      ];
      let aiIdx = 0;
      let selectedTeacherId = null;
      let filterState = 'all';

      /* ── INIT ──────────────────────────────────────────────────── */
      function init() {
        wireQuote();
        wireClock();
        buildHeatmap();
        buildCalendar();
        buildTeacherCards();
        wireFlip();
        wireChat();
        wireModal();
        wireHeader();
        wireSearch();
        wireLogout();
        wireFilterBar();
        wireEnterStudio();
      }

      /* ── QUOTE ─────────────────────────────────────────────────── */
      function wireQuote() {
        const el = document.getElementById('welcomeQuote');
        if (el) el.textContent = QUOTES[Math.floor(Math.random() * QUOTES.length)];
      }

      /* ── CLOCK ─────────────────────────────────────────────────── */
      function wireClock() {
        function tick() {
          const now = new Date();
          const h = now.getHours(), mn = now.getMinutes();
          const ampm = h >= 12 ? 'PM' : 'AM';
          const hr = ((h % 12) || 12).toString().padStart(2, '0');
          setText('welcomeClock', hr + ':' + mn.toString().padStart(2, '0') + ' ' + ampm);
          setText('welcomeDate', now.toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' }));
        }
        tick(); setInterval(tick, 30000);
      }

      /* ── HEATMAP ───────────────────────────────────────────────── */
      function buildHeatmap() {
        const c = document.getElementById('rpHeatmap');
        if (!c) return;
        const lvls = ['', 'l1', 'l2', 'l3'];
        for (let i = 0; i < 35; i++) {
          const d = document.createElement('div');
          d.className = 'hm-cell';
          const daysAgo = 34 - i;
          if (daysAgo < 3) d.classList.add('l3');
          else if (daysAgo < 8 && Math.random() > 0.45) d.classList.add(lvls[Math.floor(Math.random() * 2) + 1]);
          else if (Math.random() > 0.75) d.classList.add('l1');
          c.appendChild(d);
        }
      }

      /* ── CALENDAR ──────────────────────────────────────────────── */
      const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
      const DAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
      let calDate = new Date();
      const evDays = [new Date().getDate(), new Date().getDate() + 5];

      function buildCalendar() {
        renderCal();
        document.getElementById('calPrev')?.addEventListener('click', () => { calDate.setMonth(calDate.getMonth() - 1); renderCal(); });
        document.getElementById('calNext')?.addEventListener('click', () => { calDate.setMonth(calDate.getMonth() + 1); renderCal(); });
      }

      function renderCal() {
        const y = calDate.getFullYear(), m = calDate.getMonth();
        setText('calLabel', MONTHS[m] + ' ' + y);
        const g = document.getElementById('calGrid');
        if (!g) return;
        g.innerHTML = '';
        DAYS.forEach(d => { const s = document.createElement('div'); s.className = 'cal-dname'; s.textContent = d; g.appendChild(s); });
        const now = new Date();
        const first = new Date(y, m, 1).getDay();
        const dim = new Date(y, m + 1, 0).getDate();
        const dipm = new Date(y, m, 0).getDate();
        let day = 1 - first;
        for (let i = 0; i < 42; i++, day++) {
          const d = document.createElement('div'); d.className = 'cal-d';
          let disp, curr = true;
          if (day < 1) { disp = dipm + day; curr = false; d.classList.add('other'); }
          else if (day > dim) { disp = day - dim; curr = false; d.classList.add('other'); }
          else { disp = day; }
          if (curr && disp === now.getDate() && m === now.getMonth() && y === now.getFullYear()) d.classList.add('today');
          if (curr && evDays.includes(disp)) d.classList.add('has-dot');
          d.textContent = disp;
          d.addEventListener('click', function () {
            g.querySelectorAll('.sel').forEach(c => c.classList.remove('sel'));
            if (!this.classList.contains('today')) this.classList.add('sel');
          });
          g.appendChild(d);
          if (day >= dim && i >= 27) break;
        }
      }

      /* ── TEACHER CARDS ─────────────────────────────────────────── */
      const GRADS = [
        'linear-gradient(135deg,#0366B0,#02B393)',
        'linear-gradient(135deg,#02B393,#606060)',
        'linear-gradient(135deg,#0366B0,#606060)',
        'linear-gradient(135deg,#0366B0,#606060)',
      ];

      function buildTeacherCards() {
        renderCards(TEACHERS);
      }

      function renderCards(list) {
        const grid = document.getElementById('teacherCardsGrid');
        if (!grid) return;
        grid.innerHTML = '';

        if (!list.length) {
          grid.innerHTML = '<p style="font-size:0.83rem;color:var(--slate);padding:12px 0">No teachers found for your art category yet. Please check back soon.</p>';
          return;
        }

        list.forEach((t, i) => {
          const card = document.createElement('div');
          card.className = 'teacher-card';
          card.dataset.id = t.id;
          const ini = ((t.initials || t.name.slice(0, 2))).toUpperCase().slice(0, 2);
          card.innerHTML = `
      <div class="tc-top">
        <div class="tc-avatar" style="background:${GRADS[i % GRADS.length]}">${ini}</div>
        <div><div class="tc-name">${esc(t.name)}</div><div class="tc-field">${esc(t.field)}</div></div>
      </div>
      <div class="tc-tags">${(t.disciplines || []).map(d => `<span class="tc-tag">${esc(d)}</span>`).join('')}</div>
      <div class="tc-meta">
        <div class="tc-stat">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z"/></svg>
          ${esc(t.exp)}
        </div>
        <div class="tc-stat">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          ${t.students} students
        </div>
      </div>
      <button class="tc-btn${t.available ? '' : ' requested'}" data-id="${t.id}">
        ${t.available
              ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Request Teacher'
              : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Class Full'}
      </button>`;
          grid.appendChild(card);
        });

        grid.querySelectorAll('.tc-btn:not(.requested)').forEach(btn => {
          btn.addEventListener('click', function () {
            const tid = parseInt(this.dataset.id);
            const t = TEACHERS.find(x => x.id === tid);
            if (!t) return;
            selectedTeacherId = tid;
            setText('modalAvatar', ((t.initials || t.name.slice(0, 2))).toUpperCase().slice(0, 2));
            setText('modalTeacherName', t.name);
            setText('modalTeacherField', t.field + (t.disciplines.length ? ' · ' + t.disciplines.join(', ') : ''));
            document.getElementById('requestMessage').value = '';
            openModal();
          });
        });
      }

      function wireFilterBar() {
        document.querySelectorAll('.tf-btn').forEach(btn => {
          btn.addEventListener('click', function () {
            document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterState = this.dataset.filter;
            const filtered = filterState === 'available' ? TEACHERS.filter(t => t.available) : TEACHERS;
            renderCards(filtered);
          });
        });
      }

      /* ── FLIP ANIMATION ────────────────────────────────────────── */
      function wireFlip() {
        const scene = document.getElementById('flipScene');
        const front = document.querySelector('.flip-face-front');
        const chatFace = document.getElementById('chatFace');

        function syncHeight() {
          chatFace.style.minHeight = front.offsetHeight + 'px';
        }
        syncHeight();
        new ResizeObserver(syncHeight).observe(front);

        function flipTo(back) {
          if (back) {
            scene.classList.add('is-flipped');
            setTimeout(() => document.getElementById('chatInput')?.focus(), 450);
          } else {
            scene.classList.remove('is-flipped');
          }
        }

        document.getElementById('openChatBtn')?.addEventListener('click', () => flipTo(true));
        document.getElementById('openChatBtn2')?.addEventListener('click', () => flipTo(true));
        document.getElementById('closeChatBtn')?.addEventListener('click', () => flipTo(false));
      }

      /* ── CHATBOT ───────────────────────────────────────────────── */
      function wireChat() {
        document.getElementById('chatSendBtn')?.addEventListener('click', () => rpSendChat());
        document.getElementById('chatInput')?.addEventListener('keydown', e => { if (e.key === 'Enter') rpSendChat(); });
      }

      function rpSendChat(text, fromSugg) {
        const input = document.getElementById('chatInput');
        const txt = (text || input?.value || '').trim();
        if (!txt) return;
        if (input) input.value = '';

        // Hide suggestions after first use
        if (fromSugg || !text) {
          const sugg = document.getElementById('chatSuggestions');
          if (sugg) sugg.style.display = 'none';
        }

        const msgs = document.getElementById('chatMessages');
        if (!msgs) return;

        // User bubble
        const ub = document.createElement('div');
        ub.className = 'chat-bubble user';
        ub.innerHTML = `<div class="bubble user">${esc(txt)}</div>`;
        msgs.appendChild(ub);

        // Typing indicator
        const typing = document.createElement('div');
        typing.className = 'chat-bubble';
        typing.id = '__typing';
        typing.innerHTML = `
    <div class="bot-ico"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/></svg></div>
    <div class="bubble bot"><div class="typing-dots"><span></span><span></span><span></span></div></div>`;
        msgs.appendChild(typing);
        msgs.scrollTop = msgs.scrollHeight;

        setTimeout(() => {
          typing.remove();
          const ab = document.createElement('div');
          ab.className = 'chat-bubble';
          ab.innerHTML = `
      <div class="bot-ico"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/></svg></div>
      <div class="bubble bot">${esc(AI_REPLIES[aiIdx++ % AI_REPLIES.length])}</div>`;
          msgs.appendChild(ab);
          msgs.scrollTop = msgs.scrollHeight;
        }, 1400 + Math.random() * 500);
      }
      window.rpSendChat = rpSendChat;

      /* ── MODAL ─────────────────────────────────────────────────── */
      function wireModal() {
        document.getElementById('modalCancel')?.addEventListener('click', closeModal);
        document.getElementById('requestModal')?.addEventListener('click', e => { if (e.target.id === 'requestModal') closeModal(); });
        document.getElementById('modalSend')?.addEventListener('click', function () {
          const msg = document.getElementById('requestMessage').value.trim();
          if (!msg) { document.getElementById('requestMessage').focus(); return; }

          fetch('submit_teacher_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `teacher_id=${selectedTeacherId}&message=${encodeURIComponent(msg)}`
          }).catch(() => { });

          this.textContent = '✓ Request Sent!';
          this.style.background = 'rgba(2,179,147,0.18)';
          this.style.color = 'var(--teal)';

          const card = document.querySelector(`.teacher-card[data-id="${selectedTeacherId}"]`);
          if (card) {
            card.classList.add('selected');
            const btn = card.querySelector('.tc-btn');
            if (btn) { btn.className = 'tc-btn requested'; btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Request Sent'; }
          }

          showToast('Request sent! Your teacher will respond soon.', 'success');
          setTimeout(closeModal, 1400);
        });
      }
      function openModal() { document.getElementById('requestModal').classList.add('open'); }
      function closeModal() {
        document.getElementById('requestModal').classList.remove('open');
        const s = document.getElementById('modalSend');
        if (s) { s.textContent = 'Send Request'; s.style.background = ''; s.style.color = ''; }
      }

      /* ── HEADER ────────────────────────────────────────────────── */
      function wireHeader() {
        const notifBtn = document.getElementById('notifBtn');
        const profileBtn = document.getElementById('profileBtn');
        const notifPanel = document.getElementById('notifPanel');
        const profileMenu = document.getElementById('profileMenu');

        function positionPanel(panel, btn) {
          const r = btn.getBoundingClientRect();
          panel.style.top = (r.bottom + 8) + 'px';
          panel.style.right = (window.innerWidth - r.right) + 'px';
        }

        notifBtn?.addEventListener('click', e => {
          e.stopPropagation();
          const open = notifPanel.classList.contains('open');
          closeAll();
          if (!open) { positionPanel(notifPanel, notifBtn); notifPanel.classList.add('open'); }
        });

        profileBtn?.addEventListener('click', e => {
          e.stopPropagation();
          const open = profileMenu.classList.contains('open');
          closeAll();
          if (!open) { positionPanel(profileMenu, profileBtn); profileMenu.classList.add('open'); }
        });

        document.addEventListener('click', closeAll);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeAll(); closeSearch(); } });

        document.getElementById('markAllRead')?.addEventListener('click', () => {
          document.getElementById('notifDot')?.classList.add('hidden');
          setText('notifBadge', '0 new');
          showToast('All notifications marked as read.');
        });
      }
      function closeAll() {
        document.getElementById('notifPanel')?.classList.remove('open');
        document.getElementById('profileMenu')?.classList.remove('open');
      }

      /* ── SEARCH ────────────────────────────────────────────────── */
      function wireSearch() {
        document.getElementById('searchBtn')?.addEventListener('click', openSearch);
        document.getElementById('searchOverlay')?.addEventListener('click', e => { if (e.target.id === 'searchOverlay') closeSearch(); });
        document.addEventListener('keydown', e => {
          if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); openSearch(); }
        });
      }
      function openSearch() { document.getElementById('searchOverlay')?.classList.add('open'); setTimeout(() => document.getElementById('globalSearchInput')?.focus(), 50); }
      function closeSearch() { document.getElementById('searchOverlay')?.classList.remove('open'); }

      /* ── LOGOUT ────────────────────────────────────────────────── */
      function wireLogout() {
        ['logoutBtn', 'pmLogout'].forEach(id => {
          document.getElementById(id)?.addEventListener('click', e => {
            e.preventDefault();
            if (confirm('Log out of Digital Art School?')) window.location.href = '../pages/logout.php';
          });
        });
      }

      /* ── ENTER STUDIO ──────────────────────────────────────────── */
      function wireEnterStudio() {
        document.getElementById('enterStudioBtn')?.addEventListener('click', function () {
          const orig = this.innerHTML;
          this.innerHTML = '<span>Connecting…</span>';
          this.disabled = true;
          setTimeout(() => { this.innerHTML = orig; this.disabled = false; }, 2500);
        });
      }

      /* ── TOAST ─────────────────────────────────────────────────── */
      function showToast(msg, type) {
        const wrap = document.getElementById('toastWrap');
        const t = document.createElement('div');
        t.className = 'toast' + (type ? ' ' + type : '');
        t.textContent = msg;
        wrap.appendChild(t);
        requestAnimationFrame(() => { requestAnimationFrame(() => t.classList.add('show')); });
        setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 350); }, 3000);
      }

      /* ── UTILS ─────────────────────────────────────────────────── */
      function setText(id, v) { const e = document.getElementById(id); if (e) e.textContent = v; }
      function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

      /* ── GO ────────────────────────────────────────────────────── */
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
      else init();

    })();
  </script>

</body>

</html>