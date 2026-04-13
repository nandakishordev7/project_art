// ===================================
// KATHAKALI BRIDGE — SHARED MODULE v3
// Injects: Glass Header, Floating Nav,
//          Notification Panel, Profile Dropdown, Search Overlay
// Include on EVERY page before page-specific script.
// ===================================

(function () {
  'use strict';

  // ── DATA SOURCE ──────────────────────────────────────────────
  var _KB = (typeof window !== 'undefined' && window.__KB) ? window.__KB : {};

  var TEACHER = {
    name:      (_KB.teacher && _KB.teacher.name)      || 'Teacher Name',
    specialty: (_KB.teacher && _KB.teacher.specialty)  || 'Kathakali Instructor',
    initials:  (_KB.teacher && _KB.teacher.initials)   || 'TN',
    avatar:    (_KB.teacher && _KB.teacher.avatar)     || '',
  };

  var NOTIFICATIONS = [
    { id: 1, text: 'Student-1 submitted a mudra practice video',     time: '5 min ago',  unread: true  },
    { id: 2, text: 'Class 2 starts in 45 minutes',                   time: '12 min ago', unread: true  },
    { id: 3, text: 'Student-2 sent a message about today class',     time: '30 min ago', unread: true  },
    { id: 4, text: 'Student-3 completed all assignments this week',   time: '1 hr ago',   unread: false },
    { id: 5, text: '3 new assignment submissions are pending review', time: '2 hrs ago',  unread: false },
    { id: 6, text: 'Student-4 accuracy improved to 92% this week',   time: 'Yesterday',  unread: false },
    { id: 7, text: 'Class 4 schedule confirmed for 7:00 PM',         time: 'Yesterday',  unread: false },
  ];

  // ── LIVE DATA LOADER ─────────────────────────────────────────
  function loadLiveData() {
    fetch('api/teacher.php', { credentials: 'same-origin' })
      .then(function(res) { return res.ok ? res.json() : Promise.reject(); })
      .then(function(data) {
        if (data.teacher) {
          TEACHER.name      = data.teacher.name      || TEACHER.name;
          TEACHER.specialty = data.teacher.specialty  || TEACHER.specialty;
          TEACHER.initials  = data.teacher.initials   || TEACHER.initials;
          var nameEl = document.getElementById('sharedTeacherName');
          var roleEl = document.getElementById('sharedTeacherRole');
          var initEl = document.querySelector('.avatar-ring-compact .avatar-initials');
          if (nameEl) nameEl.textContent = TEACHER.name;
          if (roleEl) roleEl.textContent = TEACHER.specialty;
          if (initEl) initEl.textContent = TEACHER.initials;
          var pName = document.querySelector('.sh-profile-name');
          var pRole = document.querySelector('.sh-profile-role');
          if (pName) pName.textContent = TEACHER.name;
          if (pRole) pRole.textContent = TEACHER.specialty;
        }
        if (data.notifications && data.notifications.length) {
          NOTIFICATIONS = data.notifications.map(function(n) {
            return { id: n.notif_id, text: n.text, time: n.time, unread: !!n.is_unread };
          });
          var panel = document.getElementById('sharedNotifPanel');
          if (panel) {
            var list  = panel.querySelector('.sh-notif-list');
            var badge = panel.querySelector('.sh-notif-badge');
            if (list) {
              list.innerHTML = NOTIFICATIONS.map(function(n) {
                return '<div class="sh-notif-item ' + (n.unread ? 'unread' : '') + '" data-id="' + n.id + '">'
                  + '<div class="sh-notif-indicator"></div>'
                  + '<div class="sh-notif-body">'
                  + '<p class="sh-notif-text">' + n.text + '</p>'
                  + '<span class="sh-notif-time">' + n.time + '</span>'
                  + '</div></div>';
              }).join('');
            }
            var unreadCount = NOTIFICATIONS.filter(function(n) { return n.unread; }).length;
            if (badge) badge.textContent = unreadCount + ' new';
            if (unreadCount === 0) {
              var dot = document.getElementById('sharedNotifDot');
              if (dot) dot.classList.add('sh-dot-hidden');
            }
          }
        }
      })
      .catch(function() {
        // Network or 404 — fallback data stays, nothing breaks
      });
  }

  // ── ACTIVE PAGE DETECTION ─────────────────────────────────────
  function getActivePage() {
    var path = window.location.pathname.split('/').pop() || 'teacher-dashboard.php';
    if (path.includes('schedule'))  return 'schedule';
    if (path.includes('students'))  return 'students';
    if (path.includes('settings'))  return 'settings';
    return 'dashboard';
  }

  // ── INJECT HEADER ─────────────────────────────────────────────
  function injectHeader() {
    var existing = document.querySelector('.glass-header');
    if (existing) existing.remove();

    var header = document.createElement('header');
    header.className = 'glass-header';
    header.innerHTML = `
      <div class="header-logos">
        <a href="teacher-dashboard.php" class="header-logo-link" aria-label="Digital University Kerala">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130 44" width="130" height="44">
            <path d="M18 6 A14 14 0 1 0 18 38" fill="none" stroke="#0366B0" stroke-width="4" stroke-linecap="round"/>
            <line x1="26" y1="6" x2="26" y2="38" stroke="#02B393" stroke-width="4" stroke-linecap="round"/>
            <line x1="26" y1="22" x2="36" y2="6" stroke="#02B393" stroke-width="4" stroke-linecap="round"/>
            <line x1="26" y1="22" x2="36" y2="38" stroke="#02B393" stroke-width="4" stroke-linecap="round"/>
            <circle cx="42" cy="12" r="2.2" fill="#A3CE47"/>
            <circle cx="42" cy="22" r="2.2" fill="#A3CE47"/>
            <circle cx="42" cy="32" r="2.2" fill="#A3CE47"/>
            <line x1="38" y1="12" x2="42" y2="12" stroke="#A3CE47" stroke-width="1.4"/>
            <line x1="38" y1="22" x2="42" y2="22" stroke="#A3CE47" stroke-width="1.4"/>
            <line x1="38" y1="32" x2="42" y2="32" stroke="#A3CE47" stroke-width="1.4"/>
            <text x="48" y="16" font-family="Raleway,sans-serif" font-weight="800" font-size="7.5" fill="#0366B0" letter-spacing="0.5">DIGITAL</text>
            <text x="48" y="26" font-family="Raleway,sans-serif" font-weight="800" font-size="7.5" fill="#0366B0" letter-spacing="0.5">UNIVERSITY</text>
            <text x="48" y="36" font-family="Raleway,sans-serif" font-weight="800" font-size="7.5" fill="#0366B0" letter-spacing="0.5">KERALA</text>
          </svg>
        </a>
        <div class="header-logo-divider"></div>
        <a href="teacher-dashboard.php" class="header-logo-link" aria-label="Digital Arts School">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130 44" width="130" height="44">
            <circle cx="22" cy="22" r="15" fill="none" stroke="#0366B0" stroke-width="1.8"/>
            <path d="M14 22 Q22 12 30 22" fill="none" stroke="#02B393" stroke-width="2.2" stroke-linecap="round"/>
            <path d="M14 22 Q22 32 30 22" fill="none" stroke="#A3CE47" stroke-width="2.2" stroke-linecap="round"/>
            <circle cx="22" cy="22" r="2.5" fill="#0366B0"/>
            <text x="42" y="19" font-family="Raleway,sans-serif" font-weight="800" font-size="11" fill="#0366B0" letter-spacing="-0.2">Digital Arts</text>
            <text x="42" y="34" font-family="Raleway,sans-serif" font-weight="400" font-size="11" fill="#02B393" letter-spacing="0.3">School</text>
          </svg>
        </a>
      </div>

      <div class="search-container">
        <button class="search-btn" id="sharedSearchBtn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <span>Search anything...</span>
          <kbd>Ctrl K</kbd>
        </button>
      </div>

      <div class="header-actions">
        <div class="teacher-identity-compact">
          <div class="avatar-ring-compact" id="sharedAvatarRing">
            <span class="avatar-initials" id="sharedAvatarInitials">${TEACHER.initials}</span>
          </div>
          <div class="teacher-info-compact">
            <span class="teacher-name" id="sharedTeacherName">${TEACHER.name}</span>
            <span class="teacher-specialty" id="sharedTeacherRole">${TEACHER.specialty}</span>
          </div>
        </div>
        <div class="header-actions-icons">
          <button class="icon-btn" id="sharedNotifBtn" aria-label="Notifications">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span class="notification-dot" id="sharedNotifDot"></span>
          </button>
          <button class="icon-btn" id="sharedProfileBtn" aria-label="Profile">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </button>
        </div>
      </div>
    `;
    document.body.prepend(header);
  }

  // ── INJECT NAV ────────────────────────────────────────────────
  function injectNav() {
    var existing = document.querySelector('.floating-nav');
    if (existing) existing.remove();

    var active = getActivePage();
    var nav = document.createElement('nav');
    nav.className = 'floating-nav';
    nav.innerHTML = `
      <div class="nav-items">
        <a href="teacher-dashboard.php" class="nav-item ${active === 'dashboard' ? 'active' : ''}" data-label="Dashboard" aria-label="Dashboard">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
          </svg>
        </a>
        <a href="schedule.html" class="nav-item ${active === 'schedule' ? 'active' : ''}" data-label="Schedule" aria-label="Schedule">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
        </a>
        <a href="students.html" class="nav-item ${active === 'students' ? 'active' : ''}" data-label="Students" aria-label="Students">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </a>
        <a href="settings.html" class="nav-item ${active === 'settings' ? 'active' : ''}" data-label="Settings" aria-label="Settings">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
          </svg>
        </a>
        <a href="#" class="nav-item nav-logout" id="sharedLogoutBtn" data-label="Log Out" aria-label="Log Out">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </a>
      </div>
    `;
    document.body.appendChild(nav);
    nav.querySelector('#sharedLogoutBtn').addEventListener('click', function (e) {
      e.preventDefault();
      if (confirm('Log out of Digital Arts School?')) {
        window.location.href = 'login.php';
      }
    });
  }

  // ── INJECT NOTIFICATION PANEL ─────────────────────────────────
  function injectNotificationPanel() {
    if (document.getElementById('sharedNotifPanel')) return;
    var unreadCount = NOTIFICATIONS.filter(function(n) { return n.unread; }).length;
    var panel = document.createElement('div');
    panel.id = 'sharedNotifPanel';
    panel.className = 'sh-notif-panel';
    panel.innerHTML = `
      <div class="sh-notif-header">
        <span class="sh-notif-title">Notifications</span>
        ${unreadCount > 0 ? `<span class="sh-notif-badge">${unreadCount} new</span>` : ''}
        <button class="sh-notif-mark" id="sharedMarkAll">Mark all read</button>
      </div>
      <div class="sh-notif-list">
        ${NOTIFICATIONS.map(function(n) {
          return '<div class="sh-notif-item ' + (n.unread ? 'unread' : '') + '" data-id="' + n.id + '">'
            + '<div class="sh-notif-indicator"></div>'
            + '<div class="sh-notif-body">'
            + '<p class="sh-notif-text">' + n.text + '</p>'
            + '<span class="sh-notif-time">' + n.time + '</span>'
            + '</div></div>';
        }).join('')}
      </div>
    `;
    document.body.appendChild(panel);

    // ✅ FIX: single clean addEventListener — no orphaned braces
    panel.querySelector('#sharedMarkAll').addEventListener('click', function () {
      panel.querySelectorAll('.sh-notif-item.unread').forEach(function(el) {
        el.classList.remove('unread');
      });
      var badge = panel.querySelector('.sh-notif-badge');
      if (badge) badge.textContent = '0 new';
      var dot = document.getElementById('sharedNotifDot');
      if (dot) dot.classList.add('sh-dot-hidden');
      fetch('api/teacher.php?action=mark_read', {
        method: 'POST',
        credentials: 'same-origin'
      }).catch(function() {});
    });
  }

  // ── INJECT PROFILE DROPDOWN ───────────────────────────────────
  function injectProfileDropdown() {
    if (document.getElementById('sharedProfileMenu')) return;
    var menu = document.createElement('div');
    menu.id = 'sharedProfileMenu';
    menu.className = 'sh-profile-menu';
    menu.innerHTML = `
      <div class="sh-profile-head">
        <div class="sh-profile-avatar">${TEACHER.initials}</div>
        <div>
          <div class="sh-profile-name">${TEACHER.name}</div>
          <div class="sh-profile-role">${TEACHER.specialty}</div>
        </div>
      </div>
      <div class="sh-profile-items">
        <a href="settings.html" class="sh-profile-item">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
          </svg> Settings
        </a>
        <a href="teacher-dashboard.php" class="sh-profile-item">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
          </svg> Dashboard
        </a>
        <div class="sh-profile-divider"></div>
        <a href="#" class="sh-profile-item sh-danger" id="sharedProfileLogout">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg> Log Out
        </a>
      </div>
    `;
    document.body.appendChild(menu);
    menu.querySelector('#sharedProfileLogout').addEventListener('click', function (e) {
      e.preventDefault();
      closeProfileMenu();
      if (confirm('Log out of Digital Arts School?')) {
        window.location.href = 'login.php';
      }
    });
  }

  // ── INJECT SEARCH OVERLAY ─────────────────────────────────────
  function injectSearchOverlay() {
    if (document.getElementById('sharedSearchOverlay')) return;
    var overlay = document.createElement('div');
    overlay.id = 'sharedSearchOverlay';
    overlay.className = 'sh-search-overlay';
    overlay.innerHTML = `
      <div class="sh-search-modal">
        <div class="sh-search-input-wrap">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input type="text" id="sharedSearchInput" placeholder="Search students, classes, resources..." autocomplete="off">
          <kbd>Esc</kbd>
        </div>
        <div class="sh-search-suggestions">
          <div class="sh-search-group">Quick Links</div>
          <a href="teacher-dashboard.php" class="sh-search-item">Dashboard</a>
          <a href="schedule.html" class="sh-search-item">Schedule</a>
          <a href="students.html" class="sh-search-item">Students</a>
          <a href="settings.html" class="sh-search-item">Settings</a>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeSearch();
    });
  }

  // ── WIRE EVENTS ───────────────────────────────────────────────
  function wireEvents() {
    document.getElementById('sharedNotifBtn').addEventListener('click', function (e) {
      e.stopPropagation(); toggleNotifPanel();
    });
    document.getElementById('sharedProfileBtn').addEventListener('click', function (e) {
      e.stopPropagation(); toggleProfileMenu();
    });
    document.getElementById('sharedSearchBtn').addEventListener('click', openSearch);
    document.addEventListener('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); openSearch(); }
      if (e.key === 'Escape') { closeSearch(); closeNotifPanel(); closeProfileMenu(); }
    });
    document.addEventListener('click', function () { closeNotifPanel(); closeProfileMenu(); });
    var unread = NOTIFICATIONS.filter(function(n) { return n.unread; }).length;
    if (unread === 0) {
      var dot = document.getElementById('sharedNotifDot');
      if (dot) dot.classList.add('sh-dot-hidden');
    }
  }

  function toggleNotifPanel() {
    var panel = document.getElementById('sharedNotifPanel');
    var btn   = document.getElementById('sharedNotifBtn');
    if (!panel) return;
    var isOpen = panel.classList.contains('open');
    closeProfileMenu();
    panel.classList.toggle('open', !isOpen);
    if (btn && !isOpen) {
      var r = btn.getBoundingClientRect();
      panel.style.top   = (r.bottom + 8) + 'px';
      panel.style.right = (window.innerWidth - r.right) + 'px';
    }
  }
  function closeNotifPanel() {
    var p = document.getElementById('sharedNotifPanel');
    if (p) p.classList.remove('open');
  }
  function toggleProfileMenu() {
    var menu = document.getElementById('sharedProfileMenu');
    var btn  = document.getElementById('sharedProfileBtn');
    if (!menu) return;
    var isOpen = menu.classList.contains('open');
    closeNotifPanel();
    menu.classList.toggle('open', !isOpen);
    if (btn && !isOpen) {
      var r = btn.getBoundingClientRect();
      menu.style.top   = (r.bottom + 8) + 'px';
      menu.style.right = (window.innerWidth - r.right) + 'px';
    }
  }
  function closeProfileMenu() {
    var m = document.getElementById('sharedProfileMenu');
    if (m) m.classList.remove('open');
  }
  function openSearch() {
    var o = document.getElementById('sharedSearchOverlay');
    if (o) o.classList.add('open');
    setTimeout(function() {
      var i = document.getElementById('sharedSearchInput');
      if (i) i.focus();
    }, 50);
  }
  function closeSearch() {
    var o = document.getElementById('sharedSearchOverlay');
    if (o) o.classList.remove('open');
  }

  window.sharedOpenSearch        = openSearch;
  window.sharedOpenNotifications = toggleNotifPanel;
  window.sharedOpenProfile       = toggleProfileMenu;

  // ── INJECT STYLES ─────────────────────────────────────────────
  function injectSharedStyles() {
    if (document.getElementById('sharedStyles')) return;
    var s = document.createElement('style');
    s.id = 'sharedStyles';
    s.textContent = `
      .avatar-initials {
        display:flex;align-items:center;justify-content:center;
        width:100%;height:100%;
        font-family:var(--font-display,'Raleway',sans-serif);
        font-weight:700;font-size:0.85rem;color:white;letter-spacing:0.5px;
      }
      .sh-dot-hidden { display:none !important; }

      .sh-notif-panel {
        position:fixed;width:360px;background:white;border-radius:16px;
        box-shadow:0 20px 60px rgba(61,47,40,0.14),0 4px 16px rgba(61,47,40,0.07);
        border:1px solid rgba(3,102,176,0.12);z-index:9999;
        opacity:0;transform:translateY(-8px) scale(0.97);pointer-events:none;
        transition:all 0.22s cubic-bezier(0.16,1,0.3,1);overflow:hidden;
      }
      .sh-notif-panel.open{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;}
      .sh-notif-header{display:flex;align-items:center;gap:8px;padding:15px 18px;border-bottom:1px solid rgba(212,165,116,0.1);}
      .sh-notif-title{font-family:var(--font-display,'Raleway',sans-serif);font-weight:700;font-size:0.95rem;color:var(--deepwood,#2d241f);flex:1;}
      .sh-notif-badge{font-size:0.7rem;font-weight:700;padding:2px 8px;background:rgba(2,179,147,0.15);color:#015e4a;border-radius:20px;}
      .sh-notif-mark{font-size:0.73rem;color:var(--brass-dark,#0255a0);background:none;border:none;cursor:pointer;text-decoration:underline;padding:0;}
      .sh-notif-list{max-height:360px;overflow-y:auto;}
      .sh-notif-item{display:flex;align-items:flex-start;gap:12px;padding:13px 18px;cursor:pointer;transition:background 0.15s;}
      .sh-notif-item:hover{background:rgba(3,102,176,0.03);}
      .sh-notif-item.unread{background:rgba(3,102,176,0.03);}
      .sh-notif-indicator{width:7px;height:7px;border-radius:50%;background:transparent;flex-shrink:0;margin-top:6px;transition:background 0.2s;}
      .sh-notif-item.unread .sh-notif-indicator{background:#02B393;}
      .sh-notif-body{flex:1;}
      .sh-notif-text{font-size:0.86rem;color:var(--deepwood,#2d241f);margin:0 0 3px;line-height:1.45;}
      .sh-notif-time{font-size:0.73rem;color:#0255a0;}

      .sh-profile-menu {
        position:fixed;width:230px;background:white;border-radius:14px;
        box-shadow:0 20px 60px rgba(61,47,40,0.14),0 4px 16px rgba(61,47,40,0.07);
        border:1px solid rgba(3,102,176,0.12);z-index:9999;
        opacity:0;transform:translateY(-8px) scale(0.97);pointer-events:none;
        transition:all 0.22s cubic-bezier(0.16,1,0.3,1);overflow:hidden;
      }
      .sh-profile-menu.open{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;}
      .sh-profile-head{display:flex;align-items:center;gap:11px;padding:15px 16px;background:linear-gradient(135deg,rgba(3,102,176,0.07),rgba(2,179,147,0.04));border-bottom:1px solid rgba(3,102,176,0.08);}
      .sh-profile-avatar{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#0366B0,#02B393);display:flex;align-items:center;justify-content:center;font-family:'Raleway',sans-serif;font-weight:700;font-size:0.8rem;color:white;flex-shrink:0;}
      .sh-profile-name{font-family:'Raleway',sans-serif;font-weight:700;font-size:0.88rem;color:#2d241f;}
      .sh-profile-role{font-size:0.72rem;color:#0255a0;margin-top:1px;}
      .sh-profile-items{padding:6px 0;}
      .sh-profile-item{display:flex;align-items:center;gap:9px;padding:10px 16px;font-size:0.86rem;color:#2d241f;text-decoration:none;cursor:pointer;transition:background 0.15s;}
      .sh-profile-item:hover{background:rgba(3,102,176,0.05);}
      .sh-profile-item.sh-danger{color:#c74a3c;}
      .sh-profile-item.sh-danger:hover{background:rgba(199,74,60,0.06);}
      .sh-profile-divider{height:1px;background:rgba(212,165,116,0.1);margin:5px 0;}

      .sh-search-overlay{
        position:fixed;inset:0;background:rgba(45,36,31,0.38);backdrop-filter:blur(6px);
        z-index:9998;display:flex;align-items:flex-start;justify-content:center;
        padding-top:14vh;opacity:0;pointer-events:none;transition:opacity 0.2s ease;
      }
      .sh-search-overlay.open{opacity:1;pointer-events:auto;}
      .sh-search-modal{width:540px;background:white;border-radius:18px;box-shadow:0 32px 80px rgba(61,47,40,0.22);overflow:hidden;transform:translateY(-16px);transition:transform 0.25s cubic-bezier(0.16,1,0.3,1);}
      .sh-search-overlay.open .sh-search-modal{transform:translateY(0);}
      .sh-search-input-wrap{display:flex;align-items:center;gap:11px;padding:17px 18px;border-bottom:1px solid rgba(3,102,176,0.08);}
      .sh-search-input-wrap svg{color:#0255a0;flex-shrink:0;}
      #sharedSearchInput{flex:1;border:none;outline:none;font-family:'Work Sans',sans-serif;font-size:1rem;color:#2d241f;background:transparent;}
      .sh-search-input-wrap kbd{font-size:0.7rem;padding:3px 7px;background:rgba(3,102,176,0.06);border-radius:5px;color:#0255a0;border:1px solid rgba(3,102,176,0.12);}
      .sh-search-suggestions{padding:10px 8px 14px;}
      .sh-search-group{font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0255a0;padding:4px 12px 8px;}
      .sh-search-item{display:block;padding:9px 12px;border-radius:9px;font-size:0.88rem;color:#2d241f;text-decoration:none;cursor:pointer;transition:background 0.15s;}
      .sh-search-item:hover{background:rgba(3,102,176,0.05);}

      .nav-logout svg{stroke:rgba(199,74,60,0.65);}
      .nav-logout:hover svg{stroke:#c74a3c;}

      .avatar-initials-pill {
        width:34px;height:34px;border-radius:10px;
        display:flex;align-items:center;justify-content:center;
        font-family:'Raleway',sans-serif;
        font-weight:700;font-size:0.72rem;color:white;
        border:2px solid white;flex-shrink:0;
      }
    `;
    document.head.appendChild(s);
  }

  // ── BACKGROUND ────────────────────────────────────────────────
  function injectDoodleBg() {
    if (document.getElementById('doodleBg')) return;
    var div = document.createElement('div');
    div.id = 'doodleBg';
    div.style.cssText = [
      'position:fixed','inset:0',
      "background-image:url('assests/community_portal_bg.png')",
      'background-size:cover','background-position:center',
      'background-repeat:no-repeat',
      'opacity:0.05','pointer-events:none','z-index:-1',
    ].join(';');
    document.body.prepend(div);
  }

  // ── INIT ──────────────────────────────────────────────────────
  function init() {
    injectSharedStyles();
    injectDoodleBg();
    injectHeader();
    injectNav();
    injectNotificationPanel();
    injectProfileDropdown();
    injectSearchOverlay();
    wireEvents();
    loadLiveData(); // ✅ FIXED: was incorrectly calling loadProfileFromApi() and loadNotificationsFromApi()
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();