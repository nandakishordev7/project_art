// ===================================
// KATHAKALI BRIDGE — DASHBOARD SCRIPT
// Handles: Critique Engine, AI Chat, Heatmap, Timeline Nodes
// shared.js handles: Header, Nav, Notifications, Profile, Search
// ===================================

document.addEventListener('DOMContentLoaded', function () {

  // ===================================
  // ADD KEYFRAMES
  // ===================================
  const styleEl = document.createElement('style');
  styleEl.textContent = `
    @keyframes ripple { from{transform:scale(0);opacity:1} to{transform:scale(4);opacity:0} }
    @keyframes fadeInScale { from{opacity:0;transform:scale(0.96) translateY(8px)} to{opacity:1;transform:scale(1) translateY(0)} }
    @keyframes typingBounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-6px)} }
  `;
  document.head.appendChild(styleEl);


  // ===================================
  // HEATMAP HOVER TIP
  // ===================================
  const heatmapCells = document.querySelectorAll('.heatmap-cell');
  const heatmapTip   = document.getElementById('heatmapTip');
  const defaultTip   = 'Hover a metric for details.';

  heatmapCells.forEach(cell => {
    cell.addEventListener('mouseenter', function () {
      const tip = this.dataset.tip;
      if (heatmapTip && tip) heatmapTip.textContent = tip;
    });
    cell.addEventListener('mouseleave', function () {
      if (heatmapTip) heatmapTip.textContent = defaultTip;
    });
  });


  // ===================================
  // TIMELINE NODE → WIDGET A SYNC
  // ===================================
  const timelineNodes = document.querySelectorAll('.timeline-node');
  const widgetTitle   = document.getElementById('widgetClassTitle');
  const widgetTime    = document.getElementById('widgetClassTime');
  const widgetStudents = document.getElementById('widgetClassStudents');
  const widgetMore    = document.getElementById('widgetAvatarMore');

  timelineNodes.forEach(node => {
    node.addEventListener('click', function () {
      timelineNodes.forEach(n => n.classList.remove('selected'));
      this.classList.add('selected');

      const title    = this.dataset.title;
      const time     = this.dataset.time;
      const students = this.dataset.students;

      if (widgetTitle)   widgetTitle.textContent = title;
      if (widgetStudents) {
        widgetStudents.innerHTML = `
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
          </svg>
          ${students} Students`;
      }
      if (widgetTime) {
        widgetTime.innerHTML = `
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          ${time}`;
      }
      const extra = Math.max(0, parseInt(students) - 4);
      if (widgetMore) widgetMore.textContent = extra > 0 ? `+${extra}` : '';

      // Ripple effect
      const dot = this.querySelector('.node-dot');
      if (dot) {
        const ripple = document.createElement('span');
        ripple.style.cssText = `
          position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) scale(0);
          width:40px;height:40px;border-radius:50%;
          background:rgba(212,165,116,0.4);pointer-events:none;
          animation:ripple 0.6s ease-out forwards;
        `;
        dot.style.position = 'relative';
        dot.appendChild(ripple);
        setTimeout(() => ripple.remove(), 700);
      }
    });
  });

  // Auto-select active node on load
  const activeNode = document.querySelector('.timeline-node.active');
  if (activeNode) activeNode.dispatchEvent(new Event('click'));


  // ===================================
  // CRITIQUE ENGINE
  // ===================================
  (function initCritiqueEngine() {
    const overlay   = document.getElementById('critiqueOverlay');
    const modal     = document.getElementById('critiqueModal');
    const closeBtn  = document.getElementById('critiqueModalClose');
    const sendBtn   = document.querySelector('.critique-note-send');
    const noteInput = document.querySelector('.critique-note-input');

    if (!overlay || !modal) return;

    document.querySelectorAll('.critique-open-btn').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const card = this.closest('.critique-card');
        if (!card) return;

        const student   = card.dataset.student   || '';
        const assign    = card.dataset.assignment || '';
        const submitted = card.dataset.submitted  || '';
        const diff      = card.dataset.diff       || '--';
        const cls       = card.dataset.class      || '';
        const refLabel  = card.dataset.refLabel   || 'Teacher Reference';
        const subLabel  = card.dataset.subLabel   || 'Student Submission';

        const leftImg  = card.querySelector('.reference-thumb img');
        const rightImg = card.querySelector('.submission-thumb img');

        document.getElementById('critiqueModalTitle').textContent = assign;
        document.getElementById('critiqueModalMeta').textContent  = `${student} · ${cls} · ${submitted}`;
        document.getElementById('splitLabelLeft').textContent     = refLabel;
        document.getElementById('splitLabelRight').textContent    = subLabel;
        document.getElementById('splitDiffBadge').textContent     = `${diff}% deviation`;

        const sLeft  = document.getElementById('splitImgLeft');
        const sRight = document.getElementById('splitImgRight');
        if (sLeft  && leftImg)  sLeft.src  = leftImg.src;
        if (sRight && rightImg) sRight.src = rightImg.src;

        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
      });
    });

    function closeCritique() {
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    }

    closeBtn?.addEventListener('click', closeCritique);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeCritique();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeCritique();
    });

    // Send feedback
    sendBtn?.addEventListener('click', function () {
      const val = noteInput?.value.trim();
      if (!val) return;
      const original = this.textContent;
      this.textContent = 'Sent';
      this.style.background = 'rgba(90,111,76,0.2)';
      this.style.color = '#3d6030';
      setTimeout(() => {
        this.textContent = original;
        this.style.background = '';
        this.style.color = '';
        if (noteInput) noteInput.value = '';
      }, 2000);
    });

    noteInput?.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') sendBtn?.click();
    });
  })();


  // ===================================
  // AI ASSISTANT CHAT
  // ===================================
  const assistantBtn = document.getElementById('assistantBtn');
  const chatPanel    = document.getElementById('chatPanel');
  const chatCloseBtn = document.getElementById('chatCloseBtn');
  const chatInput    = document.getElementById('chatInput');
  const chatSendBtn  = document.getElementById('chatSendBtn');
  const chatMessages = document.getElementById('chatMessages');
  const contentWrapper = document.getElementById('contentWrapper');

  const fakeReplies = [
    'Your next class starts in about 45 minutes. Class 2 has 8 students enrolled.',
    'Based on this week\'s data, Student-4 is showing improvement in submission consistency.',
    'The average mudra accuracy across all classes this week is 75.7%.',
    'Student-1 has the highest deviation score. Consider scheduling a focused review session.',
    'You have 4 pending critiques in your review queue.',
    'Class 3 attendance dropped by 8% compared to last week.',
    'I would recommend sending a nudge to Class 2 about the submission deadline.',
  ];
  let replyIndex = 0;

  function openChat() {
    chatPanel?.classList.add('open');
    contentWrapper?.classList.add('chat-open');
    chatInput?.focus();
  }

  function closeChat() {
    chatPanel?.classList.remove('open');
    contentWrapper?.classList.remove('chat-open');
  }

  assistantBtn?.addEventListener('click', function () {
    chatPanel?.classList.contains('open') ? closeChat() : openChat();
  });
  chatCloseBtn?.addEventListener('click', closeChat);

  function addMessage(text, role) {
    const msg = document.createElement('div');
    msg.className = `chat-message ${role}-msg`;
    msg.innerHTML = `<p>${text}</p>`;
    chatMessages?.appendChild(msg);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function sendMessage() {
    const text = chatInput?.value.trim();
    if (!text) return;
    addMessage(text, 'user');
    if (chatInput) chatInput.value = '';

    // Typing indicator
    const typing = document.createElement('div');
    typing.className = 'chat-message assistant-msg typing-indicator';
    typing.innerHTML = `
      <div style="display:flex;gap:4px;padding:4px 0;">
        <span style="width:7px;height:7px;border-radius:50%;background:var(--brass-light);display:inline-block;animation:typingBounce 1s ease-in-out 0s infinite"></span>
        <span style="width:7px;height:7px;border-radius:50%;background:var(--brass-light);display:inline-block;animation:typingBounce 1s ease-in-out 0.2s infinite"></span>
        <span style="width:7px;height:7px;border-radius:50%;background:var(--brass-light);display:inline-block;animation:typingBounce 1s ease-in-out 0.4s infinite"></span>
      </div>`;
    chatMessages?.appendChild(typing);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    setTimeout(() => {
      typing.remove();
      addMessage(fakeReplies[replyIndex % fakeReplies.length], 'assistant');
      replyIndex++;
    }, 1800);
  }

  chatSendBtn?.addEventListener('click', sendMessage);
  chatInput?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') sendMessage();
  });


  // ===================================
  // ENTER STUDIO — Moodle Bridge
  // When PHP api/ is present: calls api/moodle_bridge.php?action=launch
  // which returns a Moodle SSO URL, then opens it in a new tab.
  // When running as plain HTML: shows a Moodle demo overlay instead.
  // ===================================
  var studioBtn = document.querySelector('.enter-studio-btn');
  if (studioBtn) {
    studioBtn.addEventListener('click', function () {
      var btn = this;
      var originalHTML = btn.innerHTML;

      // Read moodle_course_id from widget data attribute (set by PHP)
      var courseId = btn.closest('.widget-next-class')
                        ?.querySelector('[data-moodle-course-id]')
                        ?.dataset.moodleCourseId || 0;

      // Animate button
      btn.innerHTML = '<span>Connecting to Studio...</span>';
      btn.disabled = true;

      fetch('api/moodle_bridge.php?action=launch&course_id=' + courseId, {
        credentials: 'same-origin'
      })
        .then(function(res) { return res.ok ? res.json() : Promise.reject('http_' + res.status); })
        .then(function(data) {
          if (data.url) {
            // Open Moodle course in a new tab
            window.open(data.url, '_blank', 'noopener');
            btn.innerHTML = '<span>Studio opened</span>';
            setTimeout(function() {
              btn.innerHTML = originalHTML;
              btn.disabled = false;
            }, 2500);
          } else {
            throw new Error(data.error || 'No URL returned');
          }
        })
        .catch(function(err) {
          // PHP not available — show demo Moodle overlay
          showMoodleDemo(courseId);
          btn.innerHTML = originalHTML;
          btn.disabled = false;
        });
    });
  }

  // ===================================
  // MOODLE DEMO OVERLAY
  // Shown when PHP api/ is not yet connected.
  // Demonstrates how the Moodle integration will look.
  // ===================================
  function showMoodleDemo(courseId) {
    var existing = document.getElementById('moodleDemoOverlay');
    if (existing) { existing.classList.add('open'); return; }

    var overlay = document.createElement('div');
    overlay.id = 'moodleDemoOverlay';
    overlay.style.cssText = [
      'position:fixed','inset:0','z-index:2000',
      'background:rgba(30,45,61,0.55)',
      'backdrop-filter:blur(6px)',
      'display:flex','align-items:center','justify-content:center',
      'opacity:0','transition:opacity 0.3s ease',
    ].join(';');

    overlay.innerHTML = [
      '<div style="',
        'background:white;border-radius:20px;',
        'width:min(680px,90vw);max-height:85vh;overflow-y:auto;',
        'box-shadow:0 32px 80px rgba(30,45,61,0.22);',
        'display:flex;flex-direction:column;',
      '">',
        // Header bar matching Moodle brand
        '<div style="',
          'background:linear-gradient(135deg,#0366B0,#02B393);',
          'padding:20px 28px;border-radius:20px 20px 0 0;',
          'display:flex;align-items:center;justify-content:space-between;',
        '">',
          '<div style="display:flex;align-items:center;gap:14px;">',
            // Moodle-style M icon
            '<div style="',
              'width:36px;height:36px;background:rgba(255,255,255,0.2);',
              'border-radius:8px;display:flex;align-items:center;justify-content:center;',
              'font-family:Raleway,sans-serif;font-weight:900;font-size:18px;color:white;',
            '">M</div>',
            '<div>',
              '<div style="font-family:Raleway,sans-serif;font-weight:700;font-size:1rem;color:white;">',
                'Moodle LMS — Course Studio</div>',
              '<div style="font-size:0.75rem;color:rgba(255,255,255,0.8);margin-top:2px;">',
                'Class 2 — Mudra Basics &nbsp;·&nbsp; moodle.example.com</div>',
            '</div>',
          '</div>',
          '<button id="moodleDemoClose" style="',
            'background:rgba(255,255,255,0.15);border:none;border-radius:8px;',
            'width:32px;height:32px;cursor:pointer;color:white;',
            'font-size:18px;display:flex;align-items:center;justify-content:center;',
          '">',
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">',
              '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
            '</svg>',
          '</button>',
        '</div>',

        // Status bar
        '<div style="',
          'background:#e5f8f4;padding:10px 28px;border-bottom:1px solid rgba(2,179,147,0.2);',
          'display:flex;align-items:center;gap:10px;font-size:0.8rem;color:#015e4a;',
        '">',
          '<div style="width:8px;height:8px;border-radius:50%;background:#02B393;',
            'animation:demoPulse 1.5s ease-in-out infinite;flex-shrink:0;"></div>',
          '<span>Integration preview — this is how Moodle will appear after PHP connection.</span>',
          '<span style="margin-left:auto;font-weight:600;">SSO: Auto-login ready</span>',
        '</div>',

        // Content area
        '<div style="padding:24px 28px;display:flex;flex-direction:column;gap:16px;">',

          // Assignment table header
          '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">',
            '<span style="font-family:Raleway,sans-serif;font-weight:700;font-size:0.95rem;color:#1e2d3d;">',
              'Student Submissions</span>',
            '<span style="font-size:0.75rem;color:#0255a0;background:rgba(3,102,176,0.08);',
              'padding:4px 12px;border-radius:20px;font-weight:600;">4 pending review</span>',
          '</div>',

          // Submission rows
          ['Student-1','Hamsasya Mudra — Attempt 3','2 hr ago','38%','#c0392b','Needs attention'],
          ['Student-2','Tripataka — Finger Alignment','5 hr ago','21%','#e67e22','Review'],
          ['Student-3','Ardhachandra — Wrist Angle','1 day ago','9%','#27ae60','Good'],
          ['Student-4','Pataka — Full Sequence','1 day ago','15%','#e67e22','Review'],
        ].flat().map(function(item) {
          if (!Array.isArray(item)) return item;
          var s=item[0],a=item[1],t=item[2],d=item[3],c=item[4],label=item[5];
          return '<div style="display:flex;align-items:center;gap:12px;padding:12px 16px;'
            + 'background:#f5fdfc;border-radius:10px;border:1px solid rgba(2,179,147,0.12);">'
            + '<div style="width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#0366B0,#02B393);'
            + 'display:flex;align-items:center;justify-content:center;font-family:Raleway,sans-serif;'
            + 'font-weight:700;font-size:0.7rem;color:white;flex-shrink:0;">' + s.split('-')[1] + '</div>'
            + '<div style="flex:1;min-width:0;">'
            + '<div style="font-size:0.86rem;font-weight:600;color:#1e2d3d;">' + s + ' — ' + a + '</div>'
            + '<div style="font-size:0.75rem;color:#0255a0;margin-top:2px;">' + t + '</div></div>'
            + '<div style="text-align:center;flex-shrink:0;">'
            + '<div style="font-weight:700;color:' + c + ';font-size:0.9rem;">' + d + '</div>'
            + '<div style="font-size:0.68rem;color:#606060;">' + label + '</div></div>'
            + '<div style="background:linear-gradient(135deg,#0366B0,#02B393);color:white;'
            + 'border:none;border-radius:8px;padding:7px 14px;font-size:0.78rem;font-weight:600;'
            + 'cursor:pointer;flex-shrink:0;">Open in Moodle</div>'
            + '</div>';
        }).join('') +

        // Footer note
        '<div style="margin-top:8px;padding:14px 18px;background:rgba(3,102,176,0.05);'
          + 'border-radius:10px;border-left:3px solid #0366B0;font-size:0.78rem;color:#0255a0;">'
          + '<strong>Integration note:</strong> When PHP is connected, clicking &ldquo;Enter Studio&rdquo; '
          + 'calls <code>api/moodle_bridge.php?action=launch&amp;course_id=' + (courseId||102) + '</code>, '
          + 'which generates a Moodle SSO token and opens this course page automatically. '
          + 'No re-login required.'
        + '</div>',
        '</div>',
      '</div>',
    ].join('');

    // Pulse animation
    var styleEl = document.getElementById('moodleDemoStyle');
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'moodleDemoStyle';
      styleEl.textContent = '@keyframes demoPulse{0%,100%{opacity:1}50%{opacity:0.4}}';
      document.head.appendChild(styleEl);
    }

    document.body.appendChild(overlay);

    // Open animation
    requestAnimationFrame(function() {
      overlay.style.opacity = '1';
    });

    // Close handlers
    function closeMoodleDemo() {
      overlay.style.opacity = '0';
      setTimeout(function() { overlay.remove(); }, 300);
    }
    document.getElementById('moodleDemoClose').addEventListener('click', closeMoodleDemo);
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) closeMoodleDemo();
    });
    document.addEventListener('keydown', function handler(e) {
      if (e.key === 'Escape') { closeMoodleDemo(); document.removeEventListener('keydown', handler); }
    });
  }

  // ===================================
  // DASHBOARD LIVE DATA (PHP API)
  // Fetches real data from PHP endpoints when available.
  // Gracefully no-ops when running as plain HTML files.
  // ===================================

  // ── Next Class (Widget A) ─────────────────────────────────
  function loadNextClass() {
    fetch('api/classes.php?scope=next', { credentials: 'same-origin' })
      .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function(data) {
        var cls = data.class;
        if (!cls) return;

        var titleEl    = document.getElementById('widgetClassTitle');
        var timeEl     = document.getElementById('widgetClassTime');
        var studentsEl = document.getElementById('widgetClassStudents');
        var untilEl    = document.getElementById('timeUntil');
        var moreEl     = document.getElementById('widgetAvatarMore');

        if (titleEl)    titleEl.textContent = cls.name;
        if (untilEl)    untilEl.textContent = cls.time_until;
        if (timeEl)     timeEl.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>' + cls.time_display;
        if (studentsEl) studentsEl.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>' + cls.student_count + ' Students';

        // Store moodle_course_id so Enter Studio can pick it up
        var widget = document.querySelector('.widget-next-class');
        if (widget) {
          var holder = widget.querySelector('[data-moodle-course-id]');
          if (!holder) {
            holder = document.createElement('span');
            holder.style.display = 'none';
            widget.appendChild(holder);
          }
          holder.dataset.moodleCourseId = cls.moodle_course_id;
        }

        // Avatar stack from real students
        var stack = document.querySelector('.avatar-stack');
        if (stack && cls.students && cls.students.length) {
          var pills = cls.students.slice(0, 4).map(function(s, i) {
            var colors = ['#0366B0','#02B393','#A3CE47','#0255a0'];
            var lbl = s.label ? s.label.split('-')[1] || 'S' : ('S' + (i+1));
            return '<div class="avatar-initials-pill" style="background:' + colors[i] + '">' + lbl + '</div>';
          }).join('');
          var extra = cls.student_count > 4 ? '<div class="avatar-more">+' + (cls.student_count - 4) + '</div>' : '';
          stack.innerHTML = pills + extra;
        }
      })
      .catch(function() {});
  }

  // ── Heatmap (Widget B) ────────────────────────────────────
  function loadHeatmap() {
    fetch('api/classes.php?scope=heatmap', { credentials: 'same-origin' })
      .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function(data) {
        var metrics = { focus: data.focus, speed: data.speed, friction: data.friction, pulse: data.pulse };
        Object.keys(metrics).forEach(function(key) {
          var m = metrics[key];
          if (!m) return;
          var cell = document.querySelector('.heatmap-cell[data-metric="' + key + '"]');
          if (!cell) return;
          // Update state class
          cell.classList.remove('good','warn','bad');
          cell.classList.add(m.state);
          // Update value
          var valEl = cell.querySelector('.heatmap-cell-value');
          if (valEl) valEl.textContent = m.value;
        });
      })
      .catch(function() {});
  }

  // ── Critique Queue (Widget C) ─────────────────────────────
  function loadCritiqueQueue() {
    fetch('api/assignments.php', { credentials: 'same-origin' })
      .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function(data) {
        // Update pending count badge
        var countEl = document.querySelector('.critique-count');
        if (countEl && data.total !== undefined) {
          countEl.textContent = data.total + ' pending';
        }
        // Note: the actual cards are server-rendered in PHP version.
        // Here we just update the count. Full card re-rendering would
        // be done by the PHP template, not JS.
      })
      .catch(function() {});
  }

  // ── Today's Schedule (Widget D) ───────────────────────────
  function loadScheduleTimeline() {
    fetch('api/classes.php?scope=today', { credentials: 'same-origin' })
      .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function(data) {
        if (!data.classes || !data.classes.length) return;
        var timeline = document.querySelector('.timeline-flow.timeline-horizontal');
        if (!timeline) return;

        // Rebuild timeline nodes from live data
        var line = timeline.querySelector('.timeline-line-h');
        timeline.innerHTML = '';
        if (line) timeline.appendChild(line); else {
          var newLine = document.createElement('div');
          newLine.className = 'timeline-line-h';
          timeline.appendChild(newLine);
        }

        data.classes.forEach(function(cls) {
          var node = document.createElement('div');
          node.className = 'timeline-node ' + (cls.status || 'upcoming');
          node.dataset.title    = cls.name;
          node.dataset.time     = cls.time_range;
          node.dataset.students = cls.student_count;
          node.innerHTML = '<div class="node-dot"></div>'
            + '<div class="node-content">'
            + '<span class="node-time">' + cls.time_display + '</span>'
            + '<span class="node-title">' + cls.name.split(' —')[0] + '</span>'
            + '<span class="node-meta">' + cls.student_count + ' students</span>'
            + '</div>';
          // Re-attach click handler
          node.addEventListener('click', function() {
            document.querySelectorAll('.timeline-node').forEach(function(n) { n.classList.remove('selected'); });
            this.classList.add('selected');
            var titleEl = document.getElementById('widgetClassTitle');
            var timeEl  = document.getElementById('widgetClassTime');
            var stuEl   = document.getElementById('widgetClassStudents');
            if (titleEl) titleEl.textContent = this.dataset.title;
            if (timeEl)  timeEl.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>' + this.dataset.time;
            if (stuEl)   stuEl.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>' + this.dataset.students + ' Students';
          });
          timeline.appendChild(node);
        });

        // Auto-select active node
        var active = timeline.querySelector('.timeline-node.active');
        if (active) active.click();
      })
      .catch(function() {});
  }

  // Fire all live loaders — each silently no-ops if PHP not present
  loadNextClass();
  loadHeatmap();
  loadCritiqueQueue();
  loadScheduleTimeline();


});
