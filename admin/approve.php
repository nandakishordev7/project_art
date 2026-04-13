<?php
/**
 * admin/approve.php
 * When admin approves a teacher:
 *   1. Sets is_approved = 1 in kathakali_bridge DB
 *   2. Creates the teacher as a Moodle user via MoodleSync
 *   3. Sends approval email to the teacher
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/mailer.php';
require_once dirname(__DIR__) . '/api/MoodleSync.php';   // ← Moodle sync

session_name(SESSION_NAME);
session_set_cookie_params(['lifetime'=>7200,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
session_start();

$ADMIN_PASS = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : 'admin1234';
$db         = DB::conn();
$sync       = new MoodleSync();
$error      = '';
$notice     = '';

// ── ADMIN LOGIN ───────────────────────────────────────────────
if (!isset($_SESSION['is_admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_login') {
        if ($_POST['password'] === $ADMIN_PASS) {
            $_SESSION['is_admin'] = true;
        } else {
            $error = 'Incorrect password.';
        }
    }
    if (!isset($_SESSION['is_admin'])) {
        showAdminLogin($error); exit;
    }
}

// ── HANDLE APPROVE / REJECT / REVOKE ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tid    = (int)($_POST['teacher_id'] ?? 0);

    // ── APPROVE ───────────────────────────────────────────────
    if ($action === 'approve' && $tid) {

        // 1. Mark approved in local DB
        $db->prepare("UPDATE teachers SET is_approved = 1 WHERE teacher_id = ?")
           ->execute([$tid]);

        // 2. Fetch full teacher row
        $stmt = $db->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
        $stmt->execute([$tid]);
        $teacher = $stmt->fetch();

        $moodleNote = '';

        if ($teacher) {
            // 3. Create teacher in Moodle
            $moodleResult = $sync->createTeacher($teacher);

            if ($moodleResult['ok'] && !($moodleResult['skipped'] ?? false)) {
                $moodleNote = " Moodle account created (ID: {$moodleResult['moodle_user_id']}).";
            } elseif ($moodleResult['skipped'] ?? false) {
                $moodleNote = ' Already in Moodle.';
            } else {
                $moodleNote = ' ⚠️ Moodle sync failed: ' . ($moodleResult['error'] ?? 'unknown');
            }

            // 4. Send approval email
            $loginUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                      . '://' . $_SERVER['HTTP_HOST']
                      . rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\')
                      . '/login.php';
            sendApprovalNotification($teacher['email'], $teacher['name'], $loginUrl);

            $notice = "✅ {$teacher['name']} approved and notified.{$moodleNote}";
        }
    }

    // ── REJECT ────────────────────────────────────────────────
    if ($action === 'reject' && $tid) {
        $stmt = $db->prepare("SELECT name FROM teachers WHERE teacher_id = ?");
        $stmt->execute([$tid]);
        $t = $stmt->fetch();
        $db->prepare("DELETE FROM teachers WHERE teacher_id = ? AND is_approved = 0")
           ->execute([$tid]);
        $notice = "🗑️ " . htmlspecialchars($t['name'] ?? '') . "'s application removed.";
    }

    // ── REVOKE ────────────────────────────────────────────────
    if ($action === 'revoke' && $tid) {
        $db->prepare("UPDATE teachers SET is_approved = 0 WHERE teacher_id = ?")
           ->execute([$tid]);
        $notice = '⚠️ Teacher access revoked.';
    }

    // ── LOGOUT ────────────────────────────────────────────────
    if ($action === 'admin_logout') {
        unset($_SESSION['is_admin']);
        header('Location: approve.php'); exit;
    }
}

// ── FETCH DATA ────────────────────────────────────────────────
$pending  = $db->query("SELECT * FROM teachers WHERE is_approved = 0 ORDER BY created_at DESC")->fetchAll();
$approved = $db->query("SELECT * FROM teachers WHERE is_approved = 1 ORDER BY created_at DESC")->fetchAll();

// ── HELPERS ───────────────────────────────────────────────────
function timeAgo(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 3600)  return floor($d/60)  . ' min ago';
    if ($d < 86400) return floor($d/3600). ' hr ago';
    return floor($d/86400) . ' day' . (floor($d/86400)>1?'s':'') . ' ago';
}

function chip(string $text): string {
    return "<span style='font-size:.72rem;font-weight:600;padding:3px 10px;border-radius:20px;
            background:rgba(3,102,176,.06);color:#5a7a9a;'>" . htmlspecialchars($text) . "</span>";
}

function showAdminLogin(string $error=''): void { ?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Admin — Kathakali Bridge</title>
<link href="https://fonts.googleapis.com/css2?family=Raleway:wght@700;800&family=Work+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Work Sans',sans-serif;background:#f0f9f7;
     background-image:radial-gradient(rgba(2,179,147,.07) 1px,transparent 1px);
     background-size:24px 24px;min-height:100vh;
     display:flex;align-items:center;justify-content:center;padding:24px;}
.card{background:white;border-radius:20px;box-shadow:0 8px 40px rgba(3,102,176,.10);width:360px;overflow:hidden;}
.top{background:linear-gradient(135deg,#0366B0,#02B393);padding:28px 32px;text-align:center;}
.top h1{font-family:Raleway,sans-serif;font-weight:800;font-size:1.2rem;color:white;margin-bottom:4px;}
.top p{font-size:.8rem;color:rgba(255,255,255,.8);}
.body{padding:28px 32px;}
.err{padding:10px 14px;border-radius:8px;font-size:.84rem;margin-bottom:16px;
     background:rgba(231,76,60,.08);color:#c0392b;border:1px solid rgba(231,76,60,.2);}
label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;
      color:#5a7a9a;display:block;margin-bottom:6px;}
input{width:100%;padding:11px 14px;border:1.5px solid rgba(3,102,176,.18);
      border-radius:12px;font-size:.95rem;color:#1e2d3d;background:#f5fdfc;outline:none;}
input:focus{border-color:#02B393;box-shadow:0 0 0 3px rgba(2,179,147,.12);}
button{width:100%;padding:12px;margin-top:16px;
       background:linear-gradient(135deg,#0366B0,#02B393);border:none;
       border-radius:12px;font-family:Raleway,sans-serif;font-weight:700;
       font-size:.9rem;color:white;cursor:pointer;}
button:hover{opacity:.92;}
</style></head><body>
<div class="card">
  <div class="top"><h1>Admin Panel</h1><p>Kathakali Bridge — Teacher Approvals</p></div>
  <div class="body">
    <?php if($error): ?><div class="err"><?php echo htmlspecialchars($error);?></div><?php endif;?>
    <form method="POST">
      <input type="hidden" name="action" value="admin_login">
      <label>Admin Password</label>
      <input type="password" name="password" placeholder="Enter admin password" autofocus required>
      <button type="submit">Sign In →</button>
    </form>
  </div>
</div>
</body></html>
<?php }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Teacher Approvals — Kathakali Bridge</title>
<link href="https://fonts.googleapis.com/css2?family=Raleway:wght@600;700;800;900&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0366B0;--teal:#02B393;--dark:#1e2d3d;--mid:#5a7a9a;
      --light:#f5fdfc;--grad:linear-gradient(135deg,#0366B0,#02B393);
      --font-h:'Raleway',sans-serif;--font-b:'Work Sans',sans-serif;}
body{font-family:var(--font-b);background:#f0f9f7;
     background-image:radial-gradient(rgba(2,179,147,.07) 1px,transparent 1px);
     background-size:24px 24px;min-height:100vh;color:var(--dark);}

/* Header */
.hdr{background:white;border-bottom:3px solid transparent;
     border-image:var(--grad) 1;padding:16px 40px;
     display:flex;align-items:center;justify-content:space-between;
     box-shadow:0 2px 16px rgba(3,102,176,.07);position:sticky;top:0;z-index:100;}
.hdr-brand{font-family:var(--font-h);font-weight:800;font-size:1rem;
           color:var(--dark);display:flex;align-items:center;gap:12px;}
.hdr-icon{width:36px;height:36px;background:var(--grad);border-radius:9px;
          display:flex;align-items:center;justify-content:center;}
.hdr-badge{font-size:.7rem;font-weight:700;background:rgba(231,76,60,.12);
           color:#c0392b;padding:3px 10px;border-radius:20px;
           border:1px solid rgba(231,76,60,.2);}
.btn-logout{padding:8px 18px;background:none;
            border:1.5px solid rgba(3,102,176,.2);border-radius:10px;
            font-size:.82rem;font-weight:600;color:var(--mid);cursor:pointer;}
.btn-logout:hover{border-color:var(--blue);color:var(--dark);}

/* Moodle status bar */
.moodle-bar{background:rgba(2,179,147,.08);border-bottom:1px solid rgba(2,179,147,.2);
            padding:10px 40px;font-size:.8rem;color:#027a60;
            display:flex;align-items:center;gap:8px;}
.moodle-dot{width:8px;height:8px;border-radius:50%;background:#02B393;flex-shrink:0;}

/* Body */
.body{max-width:900px;margin:32px auto;padding:0 24px 60px;}
.notice{padding:13px 18px;border-radius:12px;font-size:.88rem;margin-bottom:24px;
        background:rgba(2,179,147,.08);color:#027a60;
        border:1px solid rgba(2,179,147,.2);font-weight:500;}
.sec-title{font-family:var(--font-h);font-weight:800;font-size:1.1rem;
           color:var(--dark);margin-bottom:14px;
           display:flex;align-items:center;gap:10px;}
.badge{font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:20px;}
.badge-pending{background:rgba(243,199,59,.15);color:#8a6800;}
.badge-approved{background:rgba(2,179,147,.12);color:#027a60;}
.sep{height:1px;background:rgba(3,102,176,.08);margin:32px 0;}

/* Cards */
.card{background:white;border-radius:16px;border:1px solid rgba(3,102,176,.1);
      box-shadow:0 2px 16px rgba(3,102,176,.06);padding:20px 24px;
      margin-bottom:12px;display:flex;align-items:center;gap:16px;}
.card-pending  {border-left:4px solid #f3c73b;}
.card-approved {border-left:4px solid var(--teal);}
.avatar{width:42px;height:42px;border-radius:10px;flex-shrink:0;
        display:flex;align-items:center;justify-content:center;
        font-family:var(--font-h);font-weight:800;font-size:.82rem;color:white;}
.info{flex:1;min-width:0;}
.name{font-family:var(--font-h);font-weight:700;font-size:1rem;
      color:var(--dark);margin-bottom:3px;}
.meta{font-size:.78rem;color:var(--mid);display:flex;flex-wrap:wrap;gap:10px;margin-top:4px;}
.chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.moodle-id{font-size:.7rem;background:rgba(2,179,147,.1);color:#027a60;
           padding:2px 8px;border-radius:8px;font-weight:700;}

/* Action buttons */
.actions{display:flex;gap:8px;flex-shrink:0;}
.btn-approve{padding:9px 18px;background:var(--grad);border:none;border-radius:10px;
             font-family:var(--font-h);font-weight:700;font-size:.82rem;
             color:white;cursor:pointer;white-space:nowrap;}
.btn-approve:hover{opacity:.88;}
.btn-reject{padding:9px 16px;background:rgba(231,76,60,.08);
            border:1.5px solid rgba(231,76,60,.25);border-radius:10px;
            font-family:var(--font-h);font-weight:700;font-size:.82rem;
            color:#c0392b;cursor:pointer;white-space:nowrap;}
.btn-reject:hover{background:rgba(231,76,60,.14);}
.btn-revoke{padding:9px 16px;background:none;
            border:1.5px solid rgba(3,102,176,.2);border-radius:10px;
            font-family:var(--font-h);font-weight:600;font-size:.82rem;
            color:var(--mid);cursor:pointer;}
.btn-revoke:hover{border-color:var(--blue);color:var(--dark);}

/* Empty */
.empty{text-align:center;padding:40px 20px;color:var(--mid);font-size:.9rem;
       background:white;border-radius:16px;border:1px dashed rgba(3,102,176,.15);}
.empty div{font-size:1.8rem;margin-bottom:8px;}

@media(max-width:600px){
  .card{flex-direction:column;align-items:flex-start;}
  .actions{width:100%;}
  .btn-approve,.btn-reject,.btn-revoke{flex:1;text-align:center;}
  .hdr{padding:12px 16px;}
  .moodle-bar{padding:10px 16px;}
}
</style>
</head>
<body>

<!-- Header -->
<header class="hdr">
  <div class="hdr-brand">
    <div class="hdr-icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
        <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/>
      </svg>
    </div>
    Admin — Teacher Approvals
    <?php if (count($pending) > 0): ?>
      <span class="hdr-badge"><?php echo count($pending); ?> pending</span>
    <?php endif; ?>
  </div>
  <form method="POST" style="display:inline">
    <input type="hidden" name="action" value="admin_logout">
    <button type="submit" class="btn-logout">Sign Out</button>
  </form>
</header>

<!-- Moodle connection indicator -->
<div class="moodle-bar">
  <div class="moodle-dot"></div>
  Moodle sync active — approving a teacher will automatically create their Moodle account.
  <a href="../api/sync.php?action=test" target="_blank"
     style="margin-left:auto;color:#027a60;font-weight:600;font-size:.75rem;">
    Test connection →
  </a>
</div>

<div class="body">

  <?php if ($notice): ?>
    <div class="notice"><?php echo $notice; ?></div>
  <?php endif; ?>

  <!-- PENDING -->
  <div class="sec-title">
    Pending Approvals
    <span class="badge badge-pending"><?php echo count($pending); ?></span>
  </div>

  <?php if (empty($pending)): ?>
    <div class="empty"><div>✅</div>No pending applications — all caught up!</div>
  <?php else: ?>
    <?php foreach ($pending as $t): ?>
    <div class="card card-pending">
      <div class="avatar" style="background:#0366B0">
        <?php echo htmlspecialchars($t['initials'] ?? 'T'); ?>
      </div>
      <div class="info">
        <div class="name"><?php echo htmlspecialchars($t['name']); ?></div>
        <div class="meta">
          <span>✉️ <?php echo htmlspecialchars($t['email']); ?></span>
          <?php if (!empty($t['phone'])): ?>
            <span>📞 <?php echo htmlspecialchars($t['phone']); ?></span>
          <?php endif; ?>
          <?php if (!empty($t['created_at'])): ?>
            <span>🕐 <?php echo timeAgo($t['created_at']); ?></span>
          <?php endif; ?>
        </div>
        <div class="chips">
          <?php if (!empty($t['art_form'])):     echo chip('🎭 '.$t['art_form']);        endif; ?>
          <?php if (!empty($t['art_category'])): echo chip($t['art_category']);           endif; ?>
          <?php if (!empty($t['years_experience'])): echo chip('⏳ '.$t['years_experience']); endif; ?>
          <?php if (!empty($t['location'])):     echo chip('📍 '.$t['location']);         endif; ?>
          <?php if (!empty($t['qualification'])): echo chip('🎓 '.$t['qualification']);   endif; ?>
        </div>
      </div>
      <div class="actions">
        <form method="POST">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="teacher_id" value="<?php echo $t['teacher_id']; ?>">
          <button type="submit" class="btn-approve">✓ Approve + Sync to Moodle</button>
        </form>
        <form method="POST" onsubmit="return confirm('Remove this application permanently?')">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="teacher_id" value="<?php echo $t['teacher_id']; ?>">
          <button type="submit" class="btn-reject">✕ Reject</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="sep"></div>

  <!-- APPROVED -->
  <div class="sec-title">
    Approved Teachers
    <span class="badge badge-approved"><?php echo count($approved); ?></span>
  </div>

  <?php if (empty($approved)): ?>
    <div class="empty"><div>👤</div>No approved teachers yet.</div>
  <?php else: ?>
    <?php foreach ($approved as $t): ?>
    <div class="card card-approved">
      <div class="avatar" style="background:#02B393">
        <?php echo htmlspecialchars($t['initials'] ?? 'T'); ?>
      </div>
      <div class="info">
        <div class="name"><?php echo htmlspecialchars($t['name']); ?></div>
        <div class="meta">
          <span>✉️ <?php echo htmlspecialchars($t['email']); ?></span>
          <span>✅ Approved</span>
          <?php if (!empty($t['moodle_user_id']) && (int)$t['moodle_user_id'] > 0): ?>
            <span class="moodle-id">🔗 Moodle ID: <?php echo (int)$t['moodle_user_id']; ?></span>
          <?php else: ?>
            <span style="font-size:.72rem;color:#c0392b;font-weight:600;">
              ⚠️ Not in Moodle yet —
              <a href="../api/sync.php?action=teacher&id=<?php echo $t['teacher_id']; ?>"
                 target="_blank" style="color:#c0392b;">sync now</a>
            </span>
          <?php endif; ?>
        </div>
        <div class="chips">
          <?php if (!empty($t['art_form'])): echo chip('🎭 '.$t['art_form']); endif; ?>
          <?php if (!empty($t['years_experience'])): echo chip('⏳ '.$t['years_experience']); endif; ?>
        </div>
      </div>
      <div class="actions">
        <?php if (empty($t['moodle_user_id']) || (int)$t['moodle_user_id'] === 0): ?>
          <a href="../api/sync.php?action=teacher&id=<?php echo $t['teacher_id']; ?>"
             target="_blank">
            <button type="button" class="btn-approve" style="background:linear-gradient(135deg,#027a60,#02B393);">
              Sync to Moodle
            </button>
          </a>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('Revoke this teacher\'s access?')">
          <input type="hidden" name="action" value="revoke">
          <input type="hidden" name="teacher_id" value="<?php echo $t['teacher_id']; ?>">
          <button type="submit" class="btn-revoke">Revoke Access</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
</body>
</html>