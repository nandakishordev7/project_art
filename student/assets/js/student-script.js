/**
 * student-script.js
 * Digital Art School — Student Module
 *
 * Responsibilities:
 *   1. Fetch student + teacher data from api/student_dashboard.php
 *   2. Build the Welcome widget (quote, status badge, Enter Studio)
 *   3. Build Teacher Selection cards
 *   4. Build the mini Calendar
 *   5. Wire the right-column flip (calendar ↔ chatbot)
 *   6. Power the AI chatbot (mock replies + Anthropic API option)
 *   7. Handle Teacher Request modal
 *   8. Show toast notifications
 *
 * Reads:
 *   window.DAS_STUDENT_SESSION  — set inline by dashboard.php <head>
 *   window.sharedStudent        — public API from shared-student.js
 */
(function () {
  'use strict';

  /* ── Session shortcut ──────────────────────────────────────── */
  var SESSION = window.DAS_STUDENT_SESSION || {
    student_id: 0,
    full_name:  'Student',
    first_name: 'Student',
    email:      '',
    initials:   'ST',
  };

  /* ── Art-category icon paths (SVG path data) ───────────────── */
  var CATEGORY_ICONS = {
    'Classical Dance':    'M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z',
    'Folk Dance':         'M9 18V5l12-2v13M6 15.75A2.25 2.25 0 1 1 3.75 18 2.25 2.25 0 0 1 6 15.75zM18 13.75A2.25 2.25 0 1 1 15.75 16 2.25 2.25 0 0 1 18 13.75z',
    'Vocal Music':        'M9 18V5l12-2v13M6 15.75A2.25 2.25 0 1 1 3.75 18 2.25 2.25 0 0 1 6 15.75zM18 13.75A2.25 2.25 0 1 1 15.75 16 2.25 2.25 0 0 1 18 13.75z',
    'Instrumental Music': 'M9 18V5l12-2v13',
    'Visual Arts':        'M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
    'Theatre':            'M2 20h20M6 20V10M18 20V10M12 4l8 6H4l8-6z',
    'Martial Arts':       'M14.5 10c-.83 0-1.5-.67-1.5-1.5V5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v3.5c0 .83-.67 1.5-1.5 1.5zM20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM9.5 14c.83 0 1.5.67 1.5 1.5V19c0 .83-.67 1.5-1.5 1.5S8 19.83 8 19v-3.5c0-.83.67-1.5 1.5-1.5z',
    'Yoga & Meditation':  'M12 2a7 7 0 0 1 7 7c0 5-7 13-7 13S5 14 5 9a7 7 0 0 1 7-7z',
    'Craft & Handicraft': 'M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z',
    'default':            'M12 2l3.09 8.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
  };

  /* ── Rotating art quotes ─────────────────────────────────────── */
  var QUOTES = [
    '"Every master was once a beginner. Your journey starts today."',
    '"Art is not what you see, but what you make others see."',
    '"The dancer\'s body is the luminous manifestation of the soul."',
    '"In art, the joy is in the doing — every practice counts."',
    '"Tradition is not the worship of ashes, but the preservation of fire."',
    '"Creativity takes courage. Step forward."',
    '"One step at a time — the arts reward consistent practice."',
    '"Your instrument is your body, your voice, your spirit."',
  ];

  /* ── Avatar gradient palette ──────────────────────────────────── */
  var AVATAR_GRADIENTS = [
    'linear-gradient(135deg,#0366B0,#02B393)',
    'linear-gradient(135deg,#02B393,#A3CE47)',
    'linear-gradient(135deg,#0366B0,#A3CE47)',
    'linear-gradient(135deg,#0050a0,#02B393)',
  ];

  /* ── AI chatbot replies ───────────────────────────────────────── */
  var AI_REPLIES = [
    'Great question! Based on your art form, the teachers in your list are all excellent choices for beginners.',
    'Once your teacher accepts your request, your first class schedule will appear right here on the dashboard.',
    'You can track your practice hours and progress on the My Progress page.',
    'Consistent short sessions (20–30 minutes daily) are more effective than long irregular ones.',
    'The calendar on the left will highlight your class days once you\'re enrolled.',
    'If you have a specific goal in mind, share it with your teacher in your introduction message — it helps them tailor their approach.',
    'Your skill level and experience are visible to teachers when they review your request.',
  ];
  var aiReplyIndex = 0;

  /* ── Module state ─────────────────────────────────────────────── */
  var pendingRequestTeacherId = null;
  var calendarDate = new Date();
  var eventDates = [];   /* populated from API: array of day numbers (current month) */

  /* ══════════════════════════════════════════════════════════════
     1. BOOT
  ══════════════════════════════════════════════════════════════ */
  function init() {
    buildCalendar();
    wireFlip();
    wireChatbot();
    wireModal();

    /* Fetch real data from PHP API endpoint */
    loadDashboard();
  }

  /* ══════════════════════════════════════════════════════════════
     2. DATA FETCH
     api/student_dashboard.php must return JSON:
     {
       student: {
         student_id, full_name, first_name, email, location,
         art_category, art_discipline, skill_level,
         years_of_experience, has_teacher, pending_request,
         scheduled_class: { name, time } | null
       },
       teachers: [
         { teacher_id, name, initials, specialty, art_form,
           years_experience, student_count, bio, is_approved }
       ],
       events: [1, 8, 15, 22]   ← day numbers with classes/events
     }
  ══════════════════════════════════════════════════════════════ */
  function loadDashboard() {
    fetch('api/student_dashboard.php?student_id=' + SESSION.student_id, {
      credentials: 'same-origin',
    })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        if (!data || !data.student) throw new Error('Invalid payload');
        buildWelcomeWidget(data.student);
        buildTeacherCards(data.teachers || []);
        buildProfilePanel(data.student);

        /* Update calendar event markers */
        if (data.events && data.events.length) {
          eventDates = data.events;
          renderCalendar();
        }

        /* Update shared header discipline label */
        if (window.sharedStudent && data.student.art_discipline) {
          window.sharedStudent.setDiscipline(data.student.art_discipline);
        }

        /* Signal that the profile panel is ready (triggers height equaliser) */
        document.dispatchEvent(new Event('dasProfileReady'));
      })
      .catch(function (err) {
        console.warn('[DAS] Dashboard API failed, using session fallback.', err);
        useFallback();
      });
  }

  /* Fallback when API is unavailable (dev / offline) */
  function useFallback() {
    var mockStudent = {
      full_name:          SESSION.full_name,
      first_name:         SESSION.first_name,
      email:              SESSION.email,
      location:           '—',
      art_category:       'Classical Dance',
      art_discipline:     'Bharatanatyam',
      skill_level:        'Beginner',
      years_of_experience: '0',
      has_teacher:        false,
      pending_request:    false,
      scheduled_class:    null,
    };

    var mockTeachers = [
      {
        teacher_id: 1, name: 'Teacher 1',
        specialty: 'Classical Dance',
        art_form: 'Bharatanatyam, Mohiniyattam',
        years_experience: '15 years',
        student_count: 18,
        bio: 'Renowned performer and teacher specialising in Kerala classical dance forms.',
        is_approved: 1,
      },
      {
        teacher_id: 2, name: 'Teacher 2',
        specialty: 'Classical Dance',
        art_form: 'Kathakali, Kuchipudi',
        years_experience: '20 years',
        student_count: 12,
        bio: 'Award-winning artist with two decades of teaching experience.',
        is_approved: 1,
      },
      {
        teacher_id: 3, name: 'Teacher 3',
        specialty: 'Classical Dance',
        art_form: 'Odissi, Bharatanatyam',
        years_experience: '10 years',
        student_count: 22,
        bio: 'Contemporary approach to traditional forms. Warm and patient with beginners.',
        is_approved: 1,
      },
    ];

    buildWelcomeWidget(mockStudent);
    buildTeacherCards(mockTeachers);
    buildProfilePanel(mockStudent);
    document.dispatchEvent(new Event('dasProfileReady'));
  }

  /* ══════════════════════════════════════════════════════════════
     3. WELCOME WIDGET
  ══════════════════════════════════════════════════════════════ */
  function buildWelcomeWidget(student) {
    var skeleton = document.getElementById('welcomeSkeleton');
    var content  = document.getElementById('welcomeContent');
    var status   = document.getElementById('welcomeStatus');
    var studioBtn = document.getElementById('enterStudioBtn');

    /* ── First name ─────────────────────────────────────────── */
    var firstName = (student.first_name || '').trim() ||
                    (student.full_name  || '').split(' ')[0] ||
                    SESSION.first_name;

    var fnEl = document.getElementById('studentFirstName');
    if (fnEl) fnEl.textContent = firstName;

    /* ── Category icon ────────────────────────────────────────── */
    var cat  = student.art_category || 'default';
    var icon = document.getElementById('welcomeCategoryIcon');
    if (icon) {
      var pathData = CATEGORY_ICONS[cat] || CATEGORY_ICONS['default'];
      icon.innerHTML = '<path d="' + pathData + '"/>';
    }

    /* ── Quote ────────────────────────────────────────────────── */
    var quoteEl = document.getElementById('welcomeQuote');
    if (quoteEl) {
      quoteEl.textContent = QUOTES[Math.floor(Math.random() * QUOTES.length)];
    }

    /* ── Show real content, hide skeleton ─────────────────────── */
    if (skeleton) skeleton.style.display = 'none';
    if (content)  content.style.display  = 'flex';

    /* ── Status badge ─────────────────────────────────────────── */
    var badge    = document.getElementById('statusBadge');
    var statusTx = document.getElementById('statusText');

    if (status) {
      status.style.display = 'block';

      if (student.scheduled_class) {
        /* Class is live / upcoming — show Enter Studio */
        badge.className = 'status-badge has-class';
        statusTx.textContent = 'Class scheduled: ' + student.scheduled_class.name +
                               ' · ' + student.scheduled_class.time;
        if (studioBtn) studioBtn.style.display = 'flex';
      } else if (student.has_teacher) {
        badge.className = 'status-badge has-teacher';
        statusTx.textContent = 'Enrolled — no class scheduled today';
      } else if (student.pending_request) {
        badge.className = 'status-badge';
        statusTx.textContent = 'Request pending — awaiting teacher confirmation';
      } else {
        badge.className = 'status-badge';
        statusTx.textContent = 'Select a teacher below to begin your journey';
      }
    }
  }

  /* ══════════════════════════════════════════════════════════════
     4. TEACHER CARDS
  ══════════════════════════════════════════════════════════════ */
  function buildTeacherCards(teachers) {
    var container = document.getElementById('teacherCards');
    var countEl   = document.getElementById('teacherCount');

    if (!container) return;

    /* Update count badge */
    if (countEl) {
      countEl.innerHTML = '';
      countEl.textContent = teachers.length
        ? teachers.length + ' teacher' + (teachers.length > 1 ? 's' : '') + ' available'
        : 'No matches yet';
    }

    if (!teachers.length) {
      container.innerHTML =
        '<p style="font-size:0.88rem;color:var(--duk-slate,#606060);padding:8px 0;">' +
          'No teachers found for your art form yet. Please check back soon.' +
        '</p>';
      return;
    }

    container.innerHTML = '';

    teachers.forEach(function (t, i) {
      /* Derive initials from teacher name */
      var parts    = (t.name || 'T').split(' ').filter(Boolean);
      var initials = parts.length >= 2
        ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
        : (t.name || 'T').slice(0, 2).toUpperCase();

      var gradient = AVATAR_GRADIENTS[i % AVATAR_GRADIENTS.length];

      var card = document.createElement('div');
      card.className = 'teacher-card';
      card.setAttribute('data-teacher-id', t.teacher_id);
      card.innerHTML =
        '<div class="teacher-card-header">' +
          '<div class="teacher-avatar" style="background:' + gradient + '">' +
            initials +
          '</div>' +
          '<div class="teacher-info">' +
            '<div class="teacher-name">' + escHtml(t.name) + '</div>' +
            '<div class="teacher-spec">' + escHtml(t.specialty || '') + '</div>' +
          '</div>' +
        '</div>' +

        '<div class="teacher-card-body">' +
          (t.art_form
            ? '<p class="teacher-disciplines">' +
                '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"' +
                     ' stroke="currentColor" stroke-width="2">' +
                  '<path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6' +
                           '0-4 2-6 4-8-2 0-4-2-4-6z"/>' +
                '</svg>' +
                escHtml(t.art_form) +
              '</p>'
            : '') +

          (t.years_experience
            ? '<p class="teacher-exp">' +
                '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"' +
                     ' stroke="currentColor" stroke-width="2">' +
                  '<circle cx="12" cy="12" r="10"/>' +
                  '<polyline points="12 6 12 12 16 14"/>' +
                '</svg>' +
                escHtml(t.years_experience) + ' experience' +
              '</p>'
            : '') +

          (t.bio
            ? '<p class="teacher-bio">' + escHtml(t.bio) + '</p>'
            : '') +

          '<div class="teacher-availability">' +
            '<span class="avail-badge">' +
              '<span class="avail-dot"></span>Accepting students' +
            '</span>' +
          '</div>' +
        '</div>' +

        '<button class="teacher-request-btn"' +
                ' data-teacher-id="' + t.teacher_id + '">' +
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"' +
               ' stroke="currentColor" stroke-width="2">' +
            '<line x1="22" y1="2"  x2="11" y2="13"/>' +
            '<polygon points="22 2 15 22 11 13 2 9 22 2"/>' +
          '</svg>' +
          'Request Teacher' +
        '</button>';

      container.appendChild(card);

      /* Wire request button */
      card.querySelector('.teacher-request-btn')
          .addEventListener('click', function () {
            openRequestModal(t, initials, gradient);
          });
    });
  }

  /* ══════════════════════════════════════════════════════════════
     5. PROFILE PANEL
  ══════════════════════════════════════════════════════════════ */
  function buildProfilePanel(student) {
    var skeleton = document.getElementById('profileSkeleton');
    var card     = document.getElementById('profileCardMini');
    if (!card) return;

    /* Initials */
    var name = student.full_name || SESSION.full_name;
    var parts = name.split(' ').filter(Boolean);
    var ini   = parts.length >= 2
      ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
      : name.slice(0, 2).toUpperCase();

    setEl('profileAvatarLarge', ini);
    setEl('profileNameLarge',   name);
    setEl('profileDiscipline',  student.art_discipline || student.art_category || '—');
    setEl('profileEmail',       student.email || SESSION.email);
    setEl('profileLocation',    student.location || '—');

    /* Skill level — pretty-print enum values */
    var skillMap = {
      complete_beginner: 'Beginner',
      basic:             'Basic',
      intermediate:      'Intermediate',
      advanced:          'Advanced',
    };
    setEl('statSkillLevel',
      skillMap[student.skill_level] || student.skill_level || '—');

    /* Experience */
    var exp = parseFloat(student.years_of_experience);
    setEl('statExperience',
      isNaN(exp) || exp === 0
        ? '< 1 yr'
        : (exp % 1 === 0 ? exp : exp.toFixed(1)) + ' yr' + (exp !== 1 ? 's' : ''));

    /* Show card, hide skeleton */
    if (skeleton) skeleton.style.display = 'none';
    if (card)     card.style.display     = 'flex';
  }

  /* ══════════════════════════════════════════════════════════════
     6. CALENDAR
  ══════════════════════════════════════════════════════════════ */
  var MONTHS = [
    'January','February','March','April','May','June',
    'July','August','September','October','November','December',
  ];
  var selectedCalDay = null;

  function buildCalendar() {
    renderCalendar();

    document.getElementById('calPrevBtn').addEventListener('click', function () {
      calendarDate.setMonth(calendarDate.getMonth() - 1);
      renderCalendar();
    });
    document.getElementById('calNextBtn').addEventListener('click', function () {
      calendarDate.setMonth(calendarDate.getMonth() + 1);
      renderCalendar();
    });
  }

  function renderCalendar() {
    var y = calendarDate.getFullYear();
    var m = calendarDate.getMonth();

    /* Title */
    var titleEl = document.getElementById('calMonthYear');
    if (titleEl) titleEl.textContent = MONTHS[m] + ' ' + y;

    var grid  = document.getElementById('calendarDays');
    if (!grid) return;
    grid.innerHTML = '';

    var today         = new Date();
    var firstDayOfWeek = new Date(y, m, 1).getDay();    /* 0 = Sunday */
    var daysInMonth    = new Date(y, m + 1, 0).getDate();
    var daysInPrev     = new Date(y, m, 0).getDate();

    var dayCount = 1 - firstDayOfWeek;  /* may be negative */

    /* We render 6 rows max */
    for (var i = 0; i < 42; i++, dayCount++) {
      var cell = document.createElement('div');
      cell.className = 'cal-day';

      var isCurrentMonth = (dayCount >= 1 && dayCount <= daysInMonth);

      if (dayCount < 1) {
        cell.textContent = daysInPrev + dayCount;
        cell.classList.add('other-month');
      } else if (dayCount > daysInMonth) {
        cell.textContent = dayCount - daysInMonth;
        cell.classList.add('other-month');
      } else {
        cell.textContent = dayCount;
      }

      /* Today highlight */
      if (
        isCurrentMonth &&
        dayCount === today.getDate() &&
        m === today.getMonth() &&
        y === today.getFullYear()
      ) {
        cell.classList.add('today');
      }

      /* Event dot */
      if (isCurrentMonth && eventDates.indexOf(dayCount) !== -1) {
        cell.classList.add('has-event');
      }

      /* Click to select */
      (function (d, isCur) {
        cell.addEventListener('click', function () {
          if (!isCur) return;
          grid.querySelectorAll('.cal-day.selected').forEach(function (c) {
            c.classList.remove('selected');
          });
          if (!this.classList.contains('today')) {
            this.classList.add('selected');
          }
          selectedCalDay = d;
        });
      }(dayCount, isCurrentMonth));

      grid.appendChild(cell);
    }
  }

  /* ══════════════════════════════════════════════════════════════
     7. FLIP  (Calendar ↔ Chatbot)
  ══════════════════════════════════════════════════════════════ */
  function wireFlip() {
    var container = document.getElementById('flipContainer');

    /* Chat trigger on front face */
    var toChatBtn = document.getElementById('flipToChatBtn');
    if (toChatBtn) {
      toChatBtn.addEventListener('click', function () {
        container.classList.add('flipped');
        /* Focus the chat input after the flip animation */
        setTimeout(function () {
          var inp = document.getElementById('chatbotInput');
          if (inp) inp.focus();
        }, 420);
      });
    }

    /* Back button on chatbot face */
    var toInfoBtn = document.getElementById('flipToInfoBtn');
    if (toInfoBtn) {
      toInfoBtn.addEventListener('click', function () {
        container.classList.remove('flipped');
      });
    }
  }

  /* ══════════════════════════════════════════════════════════════
     8. CHATBOT
  ══════════════════════════════════════════════════════════════ */
  function wireChatbot() {
    var sendBtn  = document.getElementById('chatbotSendBtn');
    var inputEl  = document.getElementById('chatbotInput');

    if (sendBtn) {
      sendBtn.addEventListener('click', sendChatMessage);
    }
    if (inputEl) {
      inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') sendChatMessage();
      });
    }
  }

  function sendChatMessage() {
    var inputEl = document.getElementById('chatbotInput');
    var text    = (inputEl && inputEl.value.trim()) || '';
    if (!text) return;

    addChatBubble(text, 'user');
    if (inputEl) inputEl.value = '';

    /* Typing indicator */
    var indicator = addTypingIndicator();

    /* Simulated AI response — replace with Anthropic API call if needed */
    setTimeout(function () {
      if (indicator && indicator.parentNode) indicator.remove();
      addChatBubble(AI_REPLIES[aiReplyIndex % AI_REPLIES.length], 'bot');
      aiReplyIndex++;
    }, 1500 + Math.random() * 600);
  }

  function addChatBubble(text, role) {
    var messages = document.getElementById('chatbotMessages');
    if (!messages) return;

    var wrap = document.createElement('div');
    wrap.className = 'chatbot-message ' + (role === 'bot' ? 'bot-msg' : 'user-msg');

    if (role === 'bot') {
      wrap.innerHTML =
        '<div class="bot-avatar">' +
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"' +
               ' stroke="currentColor" stroke-width="1.5">' +
            '<path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6' +
                     '0-4 2-6 4-8-2 0-4-2-4-6z"/>' +
          '</svg>' +
        '</div>' +
        '<div class="bot-msg-content"><p>' + escHtml(text) + '</p></div>';
    } else {
      wrap.innerHTML =
        '<div class="user-msg-content"><p>' + escHtml(text) + '</p></div>';
    }

    messages.appendChild(wrap);
    messages.scrollTop = messages.scrollHeight;
  }

  function addTypingIndicator() {
    var messages = document.getElementById('chatbotMessages');
    if (!messages) return null;

    var wrap = document.createElement('div');
    wrap.className = 'chatbot-message bot-msg';
    wrap.innerHTML =
      '<div class="bot-avatar">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"' +
             ' stroke="currentColor" stroke-width="1.5">' +
          '<path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6' +
                   '0-4 2-6 4-8-2 0-4-2-4-6z"/>' +
        '</svg>' +
      '</div>' +
      '<div class="bot-msg-content">' +
        '<div style="display:flex;gap:5px;padding:4px 2px;">' +
          '<span style="width:7px;height:7px;border-radius:50%;' +
                       'background:#0366B0;display:inline-block;' +
                       'animation:dotBounce 1.2s ease-in-out 0s infinite;"></span>' +
          '<span style="width:7px;height:7px;border-radius:50%;' +
                       'background:#02B393;display:inline-block;' +
                       'animation:dotBounce 1.2s ease-in-out 0.2s infinite;"></span>' +
          '<span style="width:7px;height:7px;border-radius:50%;' +
                       'background:#A3CE47;display:inline-block;' +
                       'animation:dotBounce 1.2s ease-in-out 0.4s infinite;"></span>' +
        '</div>' +
      '</div>';

    /* Inject keyframes once */
    if (!document.getElementById('dotBounceKF')) {
      var style = document.createElement('style');
      style.id = 'dotBounceKF';
      style.textContent =
        '@keyframes dotBounce{' +
          '0%,60%,100%{transform:translateY(0)}' +
          '30%{transform:translateY(-6px)}' +
        '}';
      document.head.appendChild(style);
    }

    messages.appendChild(wrap);
    messages.scrollTop = messages.scrollHeight;
    return wrap;
  }

  /* ══════════════════════════════════════════════════════════════
     9. TEACHER REQUEST MODAL
  ══════════════════════════════════════════════════════════════ */
  function wireModal() {
    document.getElementById('requestModalClose')
      .addEventListener('click', closeModal);
    document.getElementById('requestCancelBtn')
      .addEventListener('click', closeModal);
    document.getElementById('requestModal')
      .addEventListener('click', function (e) {
        if (e.target.id === 'requestModal') closeModal();
      });
    document.getElementById('requestSubmitBtn')
      .addEventListener('click', submitRequest);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeModal();
    });
  }

  function openRequestModal(teacher, initials, gradient) {
    pendingRequestTeacherId = teacher.teacher_id;

    /* Update modal title */
    var titleEl = document.getElementById('requestModalTitle');
    if (titleEl) titleEl.textContent = 'Request: ' + teacher.name;

    /* Teacher info block */
    var infoEl = document.getElementById('requestTeacherInfo');
    if (infoEl) {
      infoEl.innerHTML =
        '<div style="width:46px;height:46px;border-radius:12px;' +
                     'background:' + gradient + ';' +
                     'display:flex;align-items:center;justify-content:center;' +
                     'font-weight:700;font-size:0.88rem;color:white;flex-shrink:0;">' +
          initials +
        '</div>' +
        '<div>' +
          '<div style="font-weight:700;font-size:0.92rem;' +
                       'color:var(--deepwood,#0d1f35);margin-bottom:3px;">' +
            escHtml(teacher.name) +
          '</div>' +
          '<div style="font-size:0.78rem;color:var(--duk-teal,#02B393);font-weight:600;">' +
            escHtml(teacher.specialty || '') +
          '</div>' +
          (teacher.art_form
            ? '<div style="font-size:0.76rem;color:var(--duk-slate,#606060);margin-top:2px;">' +
                escHtml(teacher.art_form) +
              '</div>'
            : '') +
        '</div>';
    }

    /* Clear previous message */
    var msgArea = document.getElementById('requestMessage');
    if (msgArea) msgArea.value = '';

    /* Open */
    var overlay = document.getElementById('requestModal');
    if (overlay) overlay.classList.add('open');

    /* Focus textarea */
    setTimeout(function () {
      if (msgArea) msgArea.focus();
    }, 120);
  }

  function closeModal() {
    var overlay = document.getElementById('requestModal');
    if (overlay) overlay.classList.remove('open');
    pendingRequestTeacherId = null;
  }

  function submitRequest() {
    if (!pendingRequestTeacherId) return;

    var submitBtn = document.getElementById('requestSubmitBtn');
    var message   = (document.getElementById('requestMessage').value || '').trim();
    var teacherId = pendingRequestTeacherId;

    /* Disable button while sending */
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"' +
             ' stroke="currentColor" stroke-width="2">' +
          '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>' +
          '<polyline points="22 4 12 14.01 9 11.01"/>' +
        '</svg> Sending…';
    }

    fetch('api/teacher_request.php', {
      method:      'POST',
      credentials: 'same-origin',
      headers:     { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        student_id: SESSION.student_id,
        teacher_id: teacherId,
        message:    message,
      }),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.success) {
          closeModal();
          showToast('Request sent! Your teacher will review and confirm enrolment.');
          markCardRequested(teacherId);

          /* Update status badge */
          var badge = document.getElementById('statusBadge');
          var tx    = document.getElementById('statusText');
          if (badge) badge.className = 'status-badge';
          if (tx)    tx.textContent  = 'Request pending — awaiting teacher confirmation';

          /* Add notification */
          if (window.sharedStudent) {
            window.sharedStudent.addNotification(
              'Your teacher request has been sent.',
              'Just now'
            );
          }
        } else {
          showToast(data.message || 'Something went wrong. Please try again.');
        }
      })
      .catch(function () {
        /* Dev/offline fallback */
        closeModal();
        showToast('Request sent! (dev mode — no server)');
        markCardRequested(teacherId);
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML =
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"' +
                 ' stroke="currentColor" stroke-width="2">' +
              '<line x1="22" y1="2"  x2="11" y2="13"/>' +
              '<polygon points="22 2 15 22 11 13 2 9 22 2"/>' +
            '</svg> Send Request';
        }
      });
  }

  function markCardRequested(teacherId) {
    var card = document.querySelector(
      '.teacher-card[data-teacher-id="' + teacherId + '"]'
    );
    if (!card) return;

    var btn = card.querySelector('.teacher-request-btn');
    if (btn) {
      btn.disabled = true;
      btn.style.background = 'rgba(2,179,147,0.12)';
      btn.style.color      = '#027a60';
      btn.innerHTML =
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"' +
             ' stroke="currentColor" stroke-width="2.5">' +
          '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>' +
          '<polyline points="22 4 12 14.01 9 11.01"/>' +
        '</svg> Request Sent';
    }
  }

  /* ══════════════════════════════════════════════════════════════
     10. ENTER STUDIO
  ══════════════════════════════════════════════════════════════ */
  var enterBtn = document.getElementById('enterStudioBtn');
  if (enterBtn) {
    enterBtn.addEventListener('click', function () {
      var orig = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<span>Connecting to Studio…</span>';
      /* In production: redirect to the studio / video conferencing URL */
      setTimeout(function () {
        enterBtn.disabled = false;
        enterBtn.innerHTML = orig;
      }, 3000);
    });
  }

  /* ══════════════════════════════════════════════════════════════
     UTILITIES
  ══════════════════════════════════════════════════════════════ */
  function setEl(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /* Toast */
  function showToast(message) {
    var existing = document.querySelector('.das-toast');
    if (existing) existing.remove();

    var toast = document.createElement('div');
    toast.className  = 'das-toast';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(function () {
      toast.classList.add('out');
      setTimeout(function () { if (toast.parentNode) toast.remove(); }, 400);
    }, 3200);
  }

  /* ══════════════════════════════════════════════════════════════
     BOOT
  ══════════════════════════════════════════════════════════════ */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();