// ===================================
// DIGITAL ART SCHOOL — SHARED STUDENT MODULE
// Injects: Glass Header, Floating Nav,
//          Notification Panel, Profile Dropdown, Search Overlay
// Include on EVERY student page before page-specific script.
// ===================================

(function () {
  'use strict';

  // Student identity — filled by fetchStudentShell()
  let STUDENT = { name: '', discipline: '', initials: '' };
  let NOTIFICATIONS = [];

  // ===================================
  // FETCH student identity + notifications from the server.
  // Backend: api/student_shell.php (session-authenticated)
  // Expected JSON response:
  // {
  //   "full_name": "...",
  //   "art_discipline": "...",
  //   "art_category": "...",
  //   "notifications": [
  //     { "id": 1, "text": "...", "time": "5 min ago", "unread": true },
  //     ...
  //   ]
  // }
  // ===================================
  async function fetchStudentShell() {
    try {
      const res = await fetch('../api/student_shell.php', {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();

      const parts = (data.full_name || '').trim().split(' ');
      STUDENT.name       = data.full_name  || '';
      STUDENT.discipline = data.art_discipline || data.art_category || '';
      STUDENT.initials   = parts.length > 1
        ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
        : (parts[0] || '').substring(0, 2).toUpperCase();

      NOTIFICATIONS = Array.isArray(data.notifications) ? data.notifications : [];
    } catch (err) {
      console.warn('[DAS] Shell fetch failed:', err.message);
    }
  }

  function getActivePage() {
    const p = window.location.pathname.split('/').pop() || '';
    if (p.includes('courses'))  return 'courses';
    if (p.includes('practice')) return 'practice';
    if (p.includes('schedule')) return 'schedule';
    if (p.includes('progress')) return 'progress';
    if (p.includes('settings')) return 'settings';
    return 'dashboard';
  }

  // ===================================
  // HEADER
  // ===================================
  function injectHeader() {
    document.querySelector('.glass-header')?.remove();
    const h = document.createElement('header');
    h.className = 'glass-header';
    h.innerHTML = `
      <div class="header-logos">
        <a href="#" class="logo-block" title="Digital University Kerala">
          <img src="../assets/images/DUK Logo.png" alt="DUK Logo" width="140" height="140" style="object-fit:contain;">
        </a>
        <div class="logo-divider"></div>
        <a href="../pages/student-dashboard.php" class="logo-block" title="Digital Art School">
          <img src="../assets/images/cdtc_logo.png" alt="Digital Art School" width="140" height="140" style="object-fit:contain;">
        </a>
      </div>

      <div class="search-container">
        <button class="search-btn" id="sharedSearchBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <span>Search anything...</span>
          <kbd>Ctrl K</kbd>
        </button>
      </div>

      <div class="header-right">
        <div class="teacher-identity">
          <div class="avatar-ring">
            <span class="avatar-initials" id="sharedAvatarInitials"></span>
          </div>
          <div class="teacher-info">
            <h2 class="teacher-name"     id="sharedStudentName"></h2>
            <span class="teacher-specialty" id="sharedStudentDiscipline"></span>
          </div>
        </div>
        <div class="header-actions">
          <button class="icon-btn" id="sharedNotifBtn" aria-label="Notifications">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span class="notification-dot sh-dot-hidden" id="sharedNotifDot"></span>
          </button>
          <button class="icon-btn" id="sharedProfileBtn" aria-label="Profile">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </button>
        </div>
      </div>
    `;
    document.body.prepend(h);
  }

  function updateHeader() {
    document.getElementById('sharedStudentName').textContent       = STUDENT.name;
    document.getElementById('sharedStudentDiscipline').textContent = STUDENT.discipline;
    document.getElementById('sharedAvatarInitials').textContent    = STUDENT.initials;
  }

  // ===================================
  // NAV
  // ===================================
  function injectNav() {
    document.querySelector('.floating-nav')?.remove();
    const active = getActivePage();
    const nav = document.createElement('nav');
    nav.className = 'floating-nav';
    nav.innerHTML = `
      <div class="nav-items">
        <a href="../pages/student-dashboard.html" class="nav-item ${active==='dashboard'?'active':''}" data-label="Dashboard" aria-label="Dashboard">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
          </svg>
        </a>
        <a href="../pages/courses.html" class="nav-item ${active==='courses'?'active':''}" data-label="Courses" aria-label="Courses">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
          </svg>
        </a>
        <a href="../pages/practice.html" class="nav-item ${active==='practice'?'active':''}" data-label="Practice" aria-label="Practice">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <polygon points="10 8 16 12 10 16 10 8"/>
          </svg>
        </a>
        <a href="../pages/schedule.html" class="nav-item ${active==='schedule'?'active':''}" data-label="Schedule" aria-label="Schedule">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8"  y1="2" x2="8"  y2="6"/>
            <line x1="3"  y1="10" x2="21" y2="10"/>
          </svg>
        </a>
        <a href="../pages/progress.html" class="nav-item ${active==='progress'?'active':''}" data-label="Progress" aria-label="Progress">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="20" x2="18" y2="10"/>
            <line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6"  y1="20" x2="6"  y2="14"/>
          </svg>
        </a>
        <a href="../pages/settings.html" class="nav-item ${active==='settings'?'active':''}" data-label="Settings" aria-label="Settings">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
          </svg>
        </a>
        <a href="#" class="nav-item nav-logout" id="sharedLogoutBtn" data-label="Log Out" aria-label="Log Out">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </a>
      </div>
    `;
    document.body.appendChild(nav);
    nav.querySelector('#sharedLogoutBtn')?.addEventListener('click', e => {
      e.preventDefault();
      if (confirm('Log out of Digital Art School?')) window.location.href = '../logout.php';
    });
  }

  // ===================================
  // NOTIFICATION PANEL
  // ===================================
  function injectNotificationPanel() {
    if (document.getElementById('sharedNotifPanel')) return;
    const panel = document.createElement('div');
    panel.id = 'sharedNotifPanel';
    panel.className = 'sh-notif-panel';
    panel.innerHTML = `
      <div class="sh-notif-header">
        <span class="sh-notif-title">Notifications</span>
        <span class="sh-notif-badge sh-dot-hidden" id="sharedNotifBadge"></span>
        <button class="sh-notif-mark sh-dot-hidden" id="sharedMarkAllRead">Mark all as read</button>
      </div>
      <div class="sh-notif-list" id="sharedNotifList">
        <div class="sh-notif-empty">Loading…</div>
      </div>
    `;
    document.body.appendChild(panel);
  }

  function populateNotifications() {
    const list    = document.getElementById('sharedNotifList');
    const badge   = document.getElementById('sharedNotifBadge');
    const markBtn = document.getElementById('sharedMarkAllRead');
    const dot     = document.getElementById('sharedNotifDot');
    if (!list) return;

    if (!NOTIFICATIONS.length) {
      list.innerHTML = '<div class="sh-notif-empty">No notifications yet.</div>';
      return;
    }

    const unread = NOTIFICATIONS.filter(n => n.unread).length;
    list.innerHTML = NOTIFICATIONS.map(n => `
      <div class="sh-notif-item ${n.unread ? 'unread' : ''}" data-id="${n.id}">
        <span class="sh-notif-indicator"></span>
        <div class="sh-notif-body">
          <p class="sh-notif-text">${n.text}</p>
          <span class="sh-notif-time">${n.time}</span>
        </div>
      </div>
    `).join('');

    if (unread > 0) {
      badge.textContent = unread;
      badge.classList.remove('sh-dot-hidden');
      markBtn.classList.remove('sh-dot-hidden');
      dot?.classList.remove('sh-dot-hidden');
    }

    markBtn.addEventListener('click', () => {
      NOTIFICATIONS.forEach(n => n.unread = false);
      list.querySelectorAll('.sh-notif-item').forEach(el => el.classList.remove('unread'));
      badge.classList.add('sh-dot-hidden');
      markBtn.classList.add('sh-dot-hidden');
      dot?.classList.add('sh-dot-hidden');
    });
  }

  // ===================================
  // PROFILE DROPDOWN
  // ===================================
  function injectProfileDropdown() {
    if (document.getElementById('sharedProfileMenu')) return;
    const menu = document.createElement('div');
    menu.id = 'sharedProfileMenu';
    menu.className = 'sh-profile-menu';
    menu.innerHTML = `
      <div class="sh-profile-head">
        <div class="sh-profile-avatar" id="sharedMenuAvatar"></div>
        <div>
          <div class="sh-profile-name" id="sharedMenuName"></div>
          <div class="sh-profile-role" id="sharedMenuRole"></div>
        </div>
      </div>
      <div class="sh-profile-items">
        <a href="../pages/profile.html"   class="sh-profile-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>View Profile
        </a>
        <a href="../pages/settings.html" class="sh-profile-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
          </svg>Settings
        </a>
        <div class="sh-profile-divider"></div>
        <a href="#" class="sh-profile-item sh-danger" id="sharedProfileLogout">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>Log Out
        </a>
      </div>
    `;
    document.body.appendChild(menu);
  }

  function updateProfileDropdown() {
    const a = document.getElementById('sharedMenuAvatar');
    const n = document.getElementById('sharedMenuName');
    const r = document.getElementById('sharedMenuRole');
    if (a) a.textContent = STUDENT.initials;
    if (n) n.textContent = STUDENT.name;
    if (r) r.textContent = STUDENT.discipline;
  }

  // ===================================
  // SEARCH OVERLAY
  // ===================================
  function injectSearchOverlay() {
    if (document.getElementById('sharedSearchOverlay')) return;
    const ov = document.createElement('div');
    ov.id = 'sharedSearchOverlay';
    ov.className = 'sh-search-overlay';
    ov.innerHTML = `
      <div class="sh-search-modal">
        <div class="sh-search-input-wrap">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input type="text" id="sharedSearchInput" placeholder="Search courses, sessions, teachers...">
          <kbd>ESC</kbd>
        </div>
        <div class="sh-search-suggestions">
          <div class="sh-search-group">Quick Links</div>
          <a href="../pages/courses.html"  class="sh-search-item">My Courses</a>
          <a href="../pages/practice.html" class="sh-search-item">Practice Sessions</a>
          <a href="../pages/schedule.html" class="sh-search-item">Class Schedule</a>
          <a href="../pages/progress.html" class="sh-search-item">My Progress</a>
        </div>
      </div>
    `;
    document.body.appendChild(ov);
  }

  // ===================================
  // EVENTS
  // ===================================
  function wireEvents() {
    const notifBtn    = () => document.getElementById('sharedNotifBtn');
    const notifPanel  = () => document.getElementById('sharedNotifPanel');
    const profileBtn  = () => document.getElementById('sharedProfileBtn');
    const profileMenu = () => document.getElementById('sharedProfileMenu');
    const searchBtn   = () => document.getElementById('sharedSearchBtn');
    const searchOv    = () => document.getElementById('sharedSearchOverlay');
    const searchInput = () => document.getElementById('sharedSearchInput');

    function below(trigger, panel) {
      const r = trigger.getBoundingClientRect();
      panel.style.cssText += `;position:fixed;top:${r.bottom + 8}px;right:${window.innerWidth - r.right}px`;
    }

    document.addEventListener('click', function (e) {
      const nb = notifBtn(), np = notifPanel(), pb = profileBtn(), pm = profileMenu();
      if (nb && nb.contains(e.target)) {
        e.stopPropagation();
        pm?.classList.remove('open');
        if (np?.classList.contains('open')) np.classList.remove('open');
        else { below(nb, np); np?.classList.add('open'); }
        return;
      }
      if (pb && pb.contains(e.target)) {
        e.stopPropagation();
        np?.classList.remove('open');
        if (pm?.classList.contains('open')) pm.classList.remove('open');
        else { below(pb, pm); pm?.classList.add('open'); }
        return;
      }
      if (np && !np.contains(e.target)) np.classList.remove('open');
      if (pm && !pm.contains(e.target)) pm.classList.remove('open');
    });

    document.getElementById('sharedProfileLogout')?.addEventListener('click', e => {
      e.preventDefault();
      if (confirm('Log out of Digital Art School?')) window.location.href = '../logout.php';
    });

    const sb = searchBtn();
    sb?.addEventListener('click', () => {
      searchOv()?.classList.add('open');
      setTimeout(() => searchInput()?.focus(), 80);
    });

    document.addEventListener('click', e => {
      const ov = searchOv();
      if (ov && e.target === ov) ov.classList.remove('open');
    });

    document.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        searchOv()?.classList.add('open');
        setTimeout(() => searchInput()?.focus(), 80);
      }
      if (e.key === 'Escape') searchOv()?.classList.remove('open');
    });
  }

  // ===================================
  // SHARED STYLES
  // ===================================
  function injectSharedStyles() {
    if (document.getElementById('sharedStyles')) return;
    const s = document.createElement('style');
    s.id = 'sharedStyles';
    s.textContent = `
      .glass-header{display:flex;align-items:center;justify-content:space-between;padding:0 28px;gap:16px;}
      .header-logos{display:flex;align-items:center;gap:14px;flex-shrink:0;}
      .logo-block{display:flex;align-items:center;gap:9px;text-decoration:none;padding:6px 10px;border-radius:10px;transition:background .2s;}
      .logo-block:hover{background:rgba(3,102,176,.06);}
      .logo-divider{width:1px;height:36px;background:linear-gradient(180deg,transparent,rgba(3,102,176,.2),transparent);flex-shrink:0;}
      .header-right{display:flex;align-items:center;gap:16px;flex-shrink:0;}
      .teacher-identity{display:flex;align-items:center;gap:10px;}
      .header-actions{display:flex;gap:8px;}
      .avatar-initials{display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-family:var(--font-display,'Poppins',sans-serif);font-weight:700;font-size:.85rem;color:white;letter-spacing:.5px;}
      .sh-dot-hidden{display:none!important;}
      /* Notification Panel */
      .sh-notif-panel{position:fixed;width:360px;background:white;border-radius:16px;box-shadow:0 20px 60px rgba(3,102,176,.14),0 4px 16px rgba(3,102,176,.07);border:1px solid rgba(3,102,176,.12);z-index:9999;opacity:0;transform:translateY(-8px) scale(.97);pointer-events:none;transition:all .22s cubic-bezier(.16,1,.3,1);overflow:hidden;}
      .sh-notif-panel.open{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;}
      .sh-notif-header{display:flex;align-items:center;gap:8px;padding:15px 18px;border-bottom:1px solid rgba(3,102,176,.08);}
      .sh-notif-title{font-family:var(--font-display,'Poppins',sans-serif);font-weight:700;font-size:.95rem;color:var(--deepwood,#0d1f35);flex:1;}
      .sh-notif-badge{font-size:.7rem;font-weight:700;padding:2px 8px;background:rgba(3,102,176,.1);color:#0366B0;border-radius:20px;}
      .sh-notif-mark{font-size:.73rem;color:#02B393;background:none;border:none;cursor:pointer;text-decoration:underline;padding:0;}
      .sh-notif-list{max-height:360px;overflow-y:auto;}
      .sh-notif-empty{padding:24px 18px;font-size:.88rem;color:#999;text-align:center;}
      .sh-notif-item{display:flex;align-items:flex-start;gap:12px;padding:13px 18px;cursor:pointer;transition:background .15s;}
      .sh-notif-item:hover{background:rgba(3,102,176,.03);}
      .sh-notif-item.unread{background:rgba(3,102,176,.04);}
      .sh-notif-indicator{width:7px;height:7px;border-radius:50%;background:transparent;flex-shrink:0;margin-top:6px;}
      .sh-notif-item.unread .sh-notif-indicator{background:#02B393;}
      .sh-notif-body{flex:1;}
      .sh-notif-text{font-size:.86rem;color:var(--deepwood,#0d1f35);margin:0 0 3px;line-height:1.45;}
      .sh-notif-time{font-size:.73rem;color:#606060;}
      /* Profile Dropdown */
      .sh-profile-menu{position:fixed;width:230px;background:white;border-radius:14px;box-shadow:0 20px 60px rgba(3,102,176,.14),0 4px 16px rgba(3,102,176,.07);border:1px solid rgba(3,102,176,.1);z-index:9999;opacity:0;transform:translateY(-8px) scale(.97);pointer-events:none;transition:all .22s cubic-bezier(.16,1,.3,1);overflow:hidden;}
      .sh-profile-menu.open{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;}
      .sh-profile-head{display:flex;align-items:center;gap:11px;padding:15px 16px;background:linear-gradient(135deg,rgba(3,102,176,.08),rgba(2,179,147,.05));border-bottom:1px solid rgba(3,102,176,.08);}
      .sh-profile-avatar{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#0366B0,#02B393);display:flex;align-items:center;justify-content:center;font-family:var(--font-display,'Poppins',sans-serif);font-weight:700;font-size:.8rem;color:white;flex-shrink:0;}
      .sh-profile-name{font-family:var(--font-display,'Poppins',sans-serif);font-weight:700;font-size:.88rem;color:var(--deepwood,#0d1f35);}
      .sh-profile-role{font-size:.72rem;color:#606060;margin-top:1px;}
      .sh-profile-items{padding:6px 0;}
      .sh-profile-item{display:flex;align-items:center;gap:9px;padding:10px 16px;font-size:.86rem;color:var(--deepwood,#0d1f35);text-decoration:none;cursor:pointer;transition:background .15s;}
      .sh-profile-item:hover{background:rgba(3,102,176,.05);}
      .sh-profile-item.sh-danger{color:#c74a3c;}
      .sh-profile-item.sh-danger:hover{background:rgba(199,74,60,.06);}
      .sh-profile-divider{height:1px;background:rgba(3,102,176,.07);margin:5px 0;}
      /* Search Overlay */
      .sh-search-overlay{position:fixed;inset:0;background:rgba(13,31,53,.42);backdrop-filter:blur(6px);z-index:9998;display:flex;align-items:flex-start;justify-content:center;padding-top:14vh;opacity:0;pointer-events:none;transition:opacity .2s ease;}
      .sh-search-overlay.open{opacity:1;pointer-events:auto;}
      .sh-search-modal{width:540px;background:white;border-radius:18px;box-shadow:0 32px 80px rgba(3,102,176,.22);overflow:hidden;transform:translateY(-16px);transition:transform .25s cubic-bezier(.16,1,.3,1);}
      .sh-search-overlay.open .sh-search-modal{transform:translateY(0);}
      .sh-search-input-wrap{display:flex;align-items:center;gap:11px;padding:17px 18px;border-bottom:1px solid rgba(3,102,176,.1);}
      .sh-search-input-wrap svg{color:#0366B0;flex-shrink:0;}
      #sharedSearchInput{flex:1;border:none;outline:none;font-family:var(--font-body,'Poppins',sans-serif);font-size:1rem;color:var(--deepwood,#0d1f35);background:transparent;}
      .sh-search-input-wrap kbd{font-size:.7rem;padding:3px 7px;background:rgba(3,102,176,.06);border-radius:5px;color:#0366B0;border:1px solid rgba(3,102,176,.15);}
      .sh-search-suggestions{padding:10px 8px 14px;}
      .sh-search-group{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#606060;padding:4px 12px 8px;}
      .sh-search-item{display:block;padding:9px 12px;border-radius:9px;font-size:.88rem;color:var(--deepwood,#0d1f35);text-decoration:none;cursor:pointer;transition:background .15s;}
      .sh-search-item:hover{background:rgba(3,102,176,.05);}
      .nav-logout svg{stroke:rgba(199,74,60,.65);}
      .nav-logout:hover svg{stroke:#c74a3c;}
      .avatar-initials-pill{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:var(--font-display,'Poppins',sans-serif);font-weight:700;font-size:.72rem;color:white;border:2px solid white;flex-shrink:0;}
    `;
    document.head.appendChild(s);
  }

  // ===================================
  // BOOT
  // ===================================
  async function init() {
    injectSharedStyles();
    injectHeader();
    injectNav();
    injectNotificationPanel();
    injectProfileDropdown();
    injectSearchOverlay();
    wireEvents();
    await fetchStudentShell();
    updateHeader();
    updateProfileDropdown();
    populateNotifications();
    document.dispatchEvent(new CustomEvent('dasShellReady', { detail: STUDENT }));
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();
})();