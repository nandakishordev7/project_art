<?php
// teacher-dashboard.php
// Include auth.php ONCE at the very top — it loads config, db, and $teacher.
require_once 'auth.php';

// Fetch next upcoming class for Widget A
$nextClass = null;
try {
    $stmt = DB::conn()->prepare("
        SELECT class_id, name, moodle_course_id, class_time, duration_min
        FROM   classes
        WHERE  teacher_id = ?
          AND  status IN ('active','upcoming')
          AND  CONCAT(class_date,' ',class_time) >= NOW()
        ORDER  BY class_date ASC, class_time ASC
        LIMIT  1
    ");
    $stmt->execute([$teacher['teacher_id']]);
    $nextClass = $stmt->fetch();
} catch (PDOException $e) {
    $nextClass = null;
}

// Fetch critique queue (pending assignments)
$assignments = [];
try {
    $aStmt = DB::conn()->prepare("
        SELECT a.assignment_id, a.title, a.diff_score,
               a.ref_label, a.sub_label,
               a.ref_image, a.sub_image,
               s.label AS student_label,
               c.name  AS class_name
        FROM   assignments a
        JOIN   students s ON s.student_id = a.student_id
        JOIN   classes  c ON c.class_id   = a.class_id
        WHERE  a.teacher_id    = ?
          AND  a.review_status = 'pending'
        ORDER  BY a.diff_score DESC
        LIMIT  8
    ");
    $aStmt->execute([$teacher['teacher_id']]);
    $assignments = $aStmt->fetchAll();
} catch (PDOException $e) {
    $assignments = [];
}

// Helper: diff badge class
function diffClass($d) {
    return $d >= 30 ? 'high' : ($d >= 15 ? 'medium' : 'low');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Kathakali Bridge</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800;900&family=Work+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php echo kb_inject_script($teacher); ?>
</head>
<body>

    <!-- Header + Nav injected by shared.js (reads window.__KB) -->

    <div class="content-wrapper" id="contentWrapper">
        <main class="dashboard-container">

            <!-- Widget A: Next Class -->
            <div class="widget widget-next-class">
                <div class="widget-glow"></div>
                <div class="next-class-header">
                    <span class="widget-label">Upcoming Session</span>
                    <span class="time-until" id="timeUntil">calculating...</span>
                </div>
                <div class="class-details">
                    <h3 class="class-title" id="widgetClassTitle">
                        <?php echo $nextClass
                            ? htmlspecialchars($nextClass['name'])
                            : 'No upcoming classes'; ?>
                    </h3>
                    <div class="class-meta">
                        <span class="class-time" id="widgetClassTime">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <?php echo $nextClass ? date('g:i A', strtotime($nextClass['class_time'])) . ' IST' : '—'; ?>
                        </span>
                        <span class="class-students" id="widgetClassStudents">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>
                            8 Students
                        </span>
                    </div>
                    <div class="student-avatars">
                        <div class="avatar-stack">
                            <div class="avatar-initials-pill" style="background:#0366B0">S1</div>
                            <div class="avatar-initials-pill" style="background:#02B393">S2</div>
                            <div class="avatar-initials-pill" style="background:#A3CE47">S3</div>
                            <div class="avatar-initials-pill" style="background:#0255a0">S4</div>
                            <div class="avatar-more" id="widgetAvatarMore">+4</div>
                        </div>
                    </div>
                    <!-- moodle_course_id picked up by script.js Enter Studio handler -->
                    <span style="display:none"
                          data-moodle-course-id="<?php echo (int)($nextClass['moodle_course_id'] ?? 0); ?>">
                    </span>
                </div>
                <button class="enter-studio-btn">
                    <span>Enter Studio</span>
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </div>

            <!-- Widget B: Class Vibe Heatmap -->
            <div class="widget widget-heatmap">
                <div class="widget-glow"></div>
                <div class="heatmap-header">
                    <span class="widget-label">Class Vibe</span>
                    <span class="heatmap-date">Today</span>
                </div>
                <div class="heatmap-grid">
                    <div class="heatmap-cell good" data-metric="focus"
                         data-tip="Focus metric: ratio of submitted vs expected work this week.">
                        <div class="heatmap-cell-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </div>
                        <span class="heatmap-cell-label">Focus</span>
                        <span class="heatmap-cell-value">78%</span>
                        <span class="heatmap-cell-sub">video watched</span>
                    </div>
                    <div class="heatmap-cell warn" data-metric="speed"
                         data-tip="Submission speed: pending reviews awaiting your attention.">
                        <div class="heatmap-cell-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <span class="heatmap-cell-label">Submission Speed</span>
                        <span class="heatmap-cell-value">+1.4d</span>
                        <span class="heatmap-cell-sub">avg delay</span>
                    </div>
                    <div class="heatmap-cell bad" data-metric="friction"
                         data-tip="Tech friction: average AI deviation score across submissions.">
                        <div class="heatmap-cell-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                <line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                        </div>
                        <span class="heatmap-cell-label">Tech Friction</span>
                        <span class="heatmap-cell-value">5 errors</span>
                        <span class="heatmap-cell-sub">AI accuracy</span>
                    </div>
                    <div class="heatmap-cell good" data-metric="pulse"
                         data-tip="Overall pulse: active student ratio across your classes.">
                        <div class="heatmap-cell-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                            </svg>
                        </div>
                        <span class="heatmap-cell-label">Overall Pulse</span>
                        <span class="heatmap-cell-value">Good</span>
                        <span class="heatmap-cell-sub">3 of 4 on track</span>
                    </div>
                </div>
                <p class="heatmap-tip" id="heatmapTip">Hover a metric for details.</p>
            </div>

            <!-- Widget C: Priority Review Queue (from DB) -->
            <div class="widget widget-critique">
                <div class="widget-glow"></div>
                <div class="critique-header">
                    <span class="widget-label">Priority Review Queue</span>
                    <span class="critique-count"><?php echo count($assignments); ?> pending</span>
                </div>
                <div class="critique-queue">

                    <?php if (empty($assignments)): ?>
                    <div style="padding:32px;text-align:center;color:var(--brass-dark);font-size:0.9rem;">
                        No pending reviews. All caught up.
                    </div>
                    <?php else: ?>
                    <?php foreach ($assignments as $a):
                        $diff   = (int)$a['diff_score'];
                        $refImg = !empty($a['ref_image']) ? htmlspecialchars($a['ref_image']) : 'assests/mudra-practice-1.jpeg';
                        $subImg = !empty($a['sub_image']) ? htmlspecialchars($a['sub_image']) : 'assests/mudra-practice-2.jpeg';
                    ?>
                    <div class="critique-card"
                         data-student="<?php echo htmlspecialchars($a['student_label']); ?>"
                         data-assignment="<?php echo htmlspecialchars($a['title']); ?>"
                         data-submitted="—"
                         data-diff="<?php echo $diff; ?>"
                         data-class="<?php echo htmlspecialchars($a['class_name']); ?>"
                         data-ref-label="<?php echo htmlspecialchars($a['ref_label']); ?>"
                         data-sub-label="<?php echo htmlspecialchars($a['sub_label']); ?>">
                        <div class="critique-thumb reference-thumb">
                            <img src="<?php echo $refImg; ?>" alt="Reference"
                                 onerror="this.parentElement.style.background='rgba(3,102,176,0.1)'">
                            <span class="thumb-tag">Reference</span>
                        </div>
                        <div class="critique-divider">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                        <div class="critique-thumb submission-thumb">
                            <img src="<?php echo $subImg; ?>" alt="Submission"
                                 onerror="this.parentElement.style.background='rgba(3,102,176,0.1)'">
                            <span class="thumb-tag">Submission</span>
                        </div>
                        <div class="critique-meta">
                            <span class="critique-student-name"><?php echo htmlspecialchars($a['student_label']); ?></span>
                            <span class="critique-assignment-name"><?php echo htmlspecialchars($a['title']); ?></span>
                            <span class="critique-time"><?php echo htmlspecialchars($a['class_name']); ?></span>
                            <div class="diff-score <?php echo diffClass($diff); ?>">
                                <?php if ($diff >= 30): ?>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                </svg>
                                <?php endif; ?>
                                <?php echo $diff; ?>% deviation
                            </div>
                        </div>
                        <button class="critique-open-btn">Open Critique</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Widget D: Today's Schedule (JS rebuilds from api/classes.php) -->
            <div class="widget widget-schedule">
                <div class="widget-glow"></div>
                <div class="schedule-widget-header">
                    <span class="widget-label">Today's Schedule</span>
                    <a href="schedule.html" class="schedule-view-all">
                        View full schedule
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
                <div class="timeline-flow timeline-horizontal">
                    <div class="timeline-line-h"></div>
                    <div class="timeline-node active">
                        <div class="node-dot"></div>
                        <div class="node-content">
                            <span class="node-time">Loading...</span>
                            <span class="node-title">—</span>
                            <span class="node-meta">—</span>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Critique Overlay -->
    <div class="critique-overlay" id="critiqueOverlay">
        <div class="critique-modal" id="critiqueModal">
            <div class="critique-modal-header">
                <div>
                    <h3 id="critiqueModalTitle">Assignment</h3>
                    <span id="critiqueModalMeta">Student · Class</span>
                </div>
                <button class="critique-modal-close" id="critiqueModalClose">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="critique-split-pane">
                <div class="split-side">
                    <div class="split-label" id="splitLabelLeft">Teacher Reference</div>
                    <div class="split-media">
                        <img id="splitImgLeft" src="assests/mudra-practice-1.jpeg" alt="Reference">
                        <div class="split-play-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="split-divider-bar">
                    <div class="split-diff-badge" id="splitDiffBadge">— deviation</div>
                </div>
                <div class="split-side">
                    <div class="split-label" id="splitLabelRight">Student Submission</div>
                    <div class="split-media">
                        <img id="splitImgRight" src="assests/mudra-practice-2.jpeg" alt="Submission">
                        <div class="split-play-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="critique-notes-bar">
                <input type="text" class="critique-note-input" placeholder="Add feedback for this student...">
                <button class="critique-note-send">Send Feedback</button>
            </div>
        </div>
    </div>

    <!-- AI Assistant -->
    <button class="widget-assistant" id="assistantBtn" aria-label="AI Assistant">
        <div class="assistant-glow"></div>
        <svg class="lotus-icon" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/>
            <path d="M8 11c-2-2-4-2-6-1 4 1 6 3 6 7 0-4-2-6-6-7 2-1 4-1 6 1z"/>
            <path d="M16 11c2-2 4-2 6-1-4 1-6 3-6 7 0-4 2-6 6-7-2-1-4-1-6 1z"/>
        </svg>
        <span class="assistant-tooltip">AI Assistant</span>
    </button>

    <!-- AI Chat Panel -->
    <div class="chat-panel" id="chatPanel">
        <div class="chat-panel-header">
            <div class="chat-panel-title">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/>
                </svg>
                <span>AI Assistant</span>
            </div>
            <button class="chat-close-btn" id="chatCloseBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="chat-messages" id="chatMessages">
            <div class="chat-message assistant-msg">
                <p>Hello <?php echo htmlspecialchars($teacher['name']); ?>! I am your AI teaching assistant.</p>
            </div>
        </div>
        <div class="chat-input-area">
            <input type="text" class="chat-input" id="chatInput" placeholder="Ask something...">
            <button class="chat-send-btn" id="chatSendBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
    </div>

    <script src="shared.js"></script>
    <script src="script.js"></script>
    <!--
=================================================================
ADD STUDENT MODAL
=================================================================
Paste this HTML just before </body> in teacher-dashboard.php
It gives teachers a modal to add students directly from the dashboard.
The modal calls api/add_student.php and syncs to Moodle in real time.
=================================================================
-->

<!-- Add Student Button — place this wherever you want in the dashboard -->
<button class="add-student-fab" id="addStudentBtn" title="Add Student">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <line x1="19" y1="8" x2="19" y2="14"/>
        <line x1="16" y1="11" x2="22" y2="11"/>
    </svg>
    Add Student
</button>

<!-- Add Student Modal Overlay -->
<div class="add-student-overlay" id="addStudentOverlay">
    <div class="add-student-modal">
        <div class="add-student-header">
            <div>
                <h3 class="add-student-title">Add New Student</h3>
                <p class="add-student-sub">Student will be created in Moodle instantly</p>
            </div>
            <button class="add-student-close" id="addStudentClose">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="add-student-body">
            <!-- Alert area -->
            <div class="add-student-alert" id="addStudentAlert" style="display:none"></div>

            <!-- Form fields -->
            <div class="as-field">
                <label>Full Name <span class="as-req">*</span></label>
                <input type="text" id="asName" placeholder="Student's full name">
            </div>
            <div class="as-field">
                <label>Email Address <span class="as-req">*</span></label>
                <input type="email" id="asEmail" placeholder="student@example.com">
            </div>
            <div class="as-field">
                <label>Phone Number</label>
                <input type="tel" id="asPhone" placeholder="+91 98765 43210">
            </div>
            <div class="as-field">
                <label>Assign to Class <span class="as-req">*</span></label>
                <select id="asClass">
                    <option value="">— Select class —</option>
                    <!-- Populated by JS from api/classes.php -->
                </select>
            </div>

            <!-- Moodle sync indicator -->
            <div class="as-moodle-note">
                <div class="as-moodle-dot"></div>
                <span>Moodle account + enrollment created automatically on save</span>
            </div>
        </div>

        <div class="add-student-footer">
            <button class="as-btn-cancel" id="addStudentCancel">Cancel</button>
            <button class="as-btn-save" id="addStudentSave">
                <span id="addStudentSaveText">Add Student</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<!-- Success toast -->
<div class="as-toast" id="asToast"></div>

<style>
/* ── Add Student FAB ── */
.add-student-fab {
    position:fixed;
    bottom:100px; right:28px;
    background:linear-gradient(135deg,#0366B0,#02B393);
    border:none; border-radius:50px;
    padding:12px 20px;
    font-family:'Raleway',sans-serif;
    font-weight:700; font-size:0.85rem;
    color:white; cursor:pointer;
    display:flex; align-items:center; gap:8px;
    box-shadow:0 6px 24px rgba(3,102,176,0.35);
    transition:all 0.2s;
    z-index:500;
}
.add-student-fab:hover {
    transform:translateY(-2px);
    box-shadow:0 10px 32px rgba(3,102,176,0.4);
}

/* ── Overlay ── */
.add-student-overlay {
    position:fixed; inset:0;
    background:rgba(30,45,40,0.45);
    backdrop-filter:blur(6px);
    z-index:1100;
    display:flex; align-items:center; justify-content:center;
    padding:20px;
    opacity:0; pointer-events:none;
    transition:opacity 0.25s ease;
}
.add-student-overlay.open {
    opacity:1; pointer-events:auto;
}

/* ── Modal ── */
.add-student-modal {
    background:white;
    border-radius:20px;
    width:100%; max-width:460px;
    box-shadow:0 32px 80px rgba(30,50,40,0.2);
    overflow:hidden;
    transform:translateY(16px) scale(0.97);
    transition:transform 0.25s cubic-bezier(0.16,1,0.3,1);
}
.add-student-overlay.open .add-student-modal {
    transform:translateY(0) scale(1);
}

/* Header */
.add-student-header {
    padding:24px 28px 20px;
    border-bottom:1px solid rgba(3,102,176,0.08);
    display:flex; align-items:flex-start; justify-content:space-between;
    background:linear-gradient(135deg,rgba(3,102,176,0.04),rgba(2,179,147,0.03));
}
.add-student-title {
    font-family:'Cormorant Garamond','Raleway',sans-serif;
    font-size:1.2rem; font-weight:700; color:#1e2d3d; margin-bottom:2px;
}
.add-student-sub {
    font-size:0.75rem; color:#5a7a6a;
    display:flex; align-items:center; gap:6px;
}
.add-student-sub::before {
    content:'';
    width:6px; height:6px; border-radius:50%;
    background:#02B393;
    display:inline-block;
    animation:pulse2 1.5s ease-in-out infinite;
}
@keyframes pulse2 {
    0%,100%{opacity:1;transform:scale(1)}
    50%{opacity:0.5;transform:scale(0.8)}
}
.add-student-close {
    background:rgba(3,102,176,0.06); border:none;
    border-radius:8px; width:32px; height:32px;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; color:#5a7a9a;
    transition:background 0.15s;
    flex-shrink:0;
}
.add-student-close:hover { background:rgba(3,102,176,0.12); }

/* Body */
.add-student-body { padding:24px 28px 16px; }

.add-student-alert {
    padding:10px 14px; border-radius:10px;
    font-size:0.82rem; margin-bottom:16px;
    background:rgba(231,76,60,0.08);
    color:#c0392b; border:1px solid rgba(231,76,60,0.2);
}
.add-student-alert.success {
    background:rgba(2,179,147,0.08);
    color:#027a60; border-color:rgba(2,179,147,0.2);
}

.as-field { display:flex; flex-direction:column; gap:6px; margin-bottom:16px; }
.as-field:last-of-type { margin-bottom:0; }
.as-field label {
    font-size:0.72rem; font-weight:700;
    text-transform:uppercase; letter-spacing:0.8px;
    color:#5a7a9a;
}
.as-req { color:#e05030; }
.as-field input, .as-field select {
    padding:11px 14px;
    border:1.5px solid rgba(3,102,176,0.18);
    border-radius:12px;
    font-family:'Work Sans',sans-serif; font-size:0.92rem;
    color:#1e2d3d; background:#f5fdfc; outline:none;
    transition:border-color 0.2s, box-shadow 0.2s;
    width:100%;
}
.as-field input:focus, .as-field select:focus {
    border-color:#02B393;
    box-shadow:0 0 0 3px rgba(2,179,147,0.12);
    background:white;
}
.as-field input::placeholder { color:#b0c4bc; }

/* Moodle note */
.as-moodle-note {
    display:flex; align-items:center; gap:8px;
    margin-top:16px; padding:10px 14px;
    background:rgba(2,179,147,0.06);
    border-radius:10px;
    font-size:0.75rem; color:#4a7a6a;
}
.as-moodle-dot {
    width:7px; height:7px; border-radius:50%;
    background:#02B393; flex-shrink:0;
    animation:pulse2 1.5s ease-in-out infinite;
}

/* Footer */
.add-student-footer {
    padding:16px 28px 24px;
    display:flex; gap:10px; justify-content:flex-end;
    border-top:1px solid rgba(3,102,176,0.06);
}
.as-btn-cancel {
    padding:10px 20px; background:none;
    border:1.5px solid rgba(3,102,176,0.18);
    border-radius:12px; font-family:'Work Sans',sans-serif;
    font-weight:600; font-size:0.88rem;
    color:#5a7a9a; cursor:pointer;
    transition:all 0.2s;
}
.as-btn-cancel:hover { border-color:#0366B0; color:#1e2d3d; }
.as-btn-save {
    padding:10px 22px;
    background:linear-gradient(135deg,#0366B0,#02B393);
    border:none; border-radius:12px;
    font-family:'Work Sans',sans-serif;
    font-weight:700; font-size:0.88rem;
    color:white; cursor:pointer;
    display:flex; align-items:center; gap:8px;
    transition:opacity 0.2s, transform 0.15s;
}
.as-btn-save:hover { opacity:0.9; transform:translateY(-1px); }
.as-btn-save:disabled { opacity:0.6; cursor:not-allowed; transform:none; }

/* Toast */
.as-toast {
    position:fixed; bottom:30px; left:50%;
    transform:translateX(-50%) translateY(16px);
    background:#1e2d3d; color:white;
    padding:12px 22px; border-radius:20px;
    font-size:0.85rem; font-weight:600;
    z-index:9999; opacity:0;
    transition:all 0.3s cubic-bezier(0.16,1,0.3,1);
    pointer-events:none; white-space:nowrap;
}
.as-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
</style>

<script>
(function () {
    var overlay   = document.getElementById('addStudentOverlay');
    var openBtn   = document.getElementById('addStudentBtn');
    var closeBtn  = document.getElementById('addStudentClose');
    var cancelBtn = document.getElementById('addStudentCancel');
    var saveBtn   = document.getElementById('addStudentSave');
    var saveText  = document.getElementById('addStudentSaveText');
    var alert     = document.getElementById('addStudentAlert');
    var toast     = document.getElementById('asToast');
    var classSelect = document.getElementById('asClass');

    // Open / close
    function openModal() {
        overlay.classList.add('open');
        loadClasses();
        document.getElementById('asName').focus();
    }
    function closeModal() {
        overlay.classList.remove('open');
        resetForm();
    }

    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    overlay?.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // Load classes from API
    function loadClasses() {
        if (classSelect.options.length > 1) return; // already loaded
        fetch('api/classes.php?scope=today', { credentials:'same-origin' })
            .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function(data) {
                (data.classes || []).forEach(function(cls) {
                    var opt = document.createElement('option');
                    opt.value = cls.class_id;
                    opt.textContent = cls.name;
                    classSelect.appendChild(opt);
                });
            })
            .catch(function() {
                // Fallback — leave dropdown with placeholder only
            });
    }

    // Save student
    saveBtn?.addEventListener('click', function() {
        var name     = document.getElementById('asName').value.trim();
        var email    = document.getElementById('asEmail').value.trim();
        var phone    = document.getElementById('asPhone').value.trim();
        var classId  = parseInt(classSelect.value) || 0;

        // Client-side validation
        if (!name || !email || !classId) {
            showAlert('Please fill in Name, Email and Class.', false);
            return;
        }

        // Loading state
        saveBtn.disabled = true;
        saveText.textContent = 'Adding...';
        hideAlert();

        fetch('api/add_student.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name:name, email:email, phone:phone, class_id:classId })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                var moodleMsg = data.moodle && data.moodle.ok
                    ? ' Moodle account created.'
                    : ' (Moodle sync pending)';
                closeModal();
                showToast('✓ ' + name + ' added!' + moodleMsg);
                // Refresh page after 2s to show new student
                setTimeout(function() { window.location.reload(); }, 2000);
            } else {
                var errMsg = (data.errors || [data.error || 'Unknown error']).join(', ');
                showAlert(errMsg, false);
            }
        })
        .catch(function() {
            showAlert('Network error — please try again.', false);
        })
        .finally(function() {
            saveBtn.disabled = false;
            saveText.textContent = 'Add Student';
        });
    });

    function showAlert(msg, isSuccess) {
        alert.textContent = msg;
        alert.className = 'add-student-alert' + (isSuccess ? ' success' : '');
        alert.style.display = 'block';
    }
    function hideAlert() { alert.style.display = 'none'; }

    function resetForm() {
        document.getElementById('asName').value  = '';
        document.getElementById('asEmail').value = '';
        document.getElementById('asPhone').value = '';
        classSelect.value = '';
        hideAlert();
    }

    var toastTimer;
    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { toast.classList.remove('show'); }, 3500);
    }
})();
</script>
</body>
</html>
