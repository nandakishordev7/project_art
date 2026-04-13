// ===================================
// KATHAKALI BRIDGE — SCHEDULE PAGE (v2)
// Syllabus Injection · Director Mode · Reactive Nodes
// ===================================

document.addEventListener('DOMContentLoaded', function () {

  // ===================================
  // STUDENT DATA (mock — replace with API)
  // ===================================
  const ALL_STUDENTS = [
    { id: 1, name: 'Student-1',   initials: 'S1', color: '#c87941', enrolled: [1,3,4] },
    { id: 2, name: 'Student-2',    initials: 'S2', color: '#6b7c4f', enrolled: [1,2,3] },
    { id: 3, name: 'Student-3',     initials: 'S3', color: '#9e5e3d', enrolled: [2,3,5] },
    { id: 4, name: 'Student-4',    initials: 'S4', color: '#4a6980', enrolled: [1,4,5] },
    { id: 5, name: 'Student-5', initials: 'S5', color: '#7a5c9a', enrolled: [3,4]   },
    { id: 6, name: 'Student-6',   initials: 'S6', color: '#a05050', enrolled: [2,5]   },
    { id: 7, name: 'Student-7',initials: 'S7', color: '#4a8a70', enrolled: [1,2]   },
    { id: 8, name: 'Student-8',   initials: 'S8', color: '#7a6030', enrolled: [3]     },
  ];

  // Slot IDs (index of each .class-slot)
  let SLOT_STUDENT_MAP = {}; // populated from data-students count for now

  // ===================================
  // SYLLABUS LIBRARY DATA
  // ===================================
  const SYLLABUS_TEMPLATES = [
    {
      id: 1, cat: 'kathakali', name: '30-min Vocal Warm-Up',
      duration: '30 min', art: 'Kathakali',
      resources: ['Warmup Audio', 'Breath Guide PDF'],
      nudges: ['vocal', 'water'],
      lastSession: 'Standard opening ritual — pranayama and swaras.'
    },
    {
      id: 2, cat: 'kathakali', name: 'Basic Mudra Introduction',
      duration: '60 min', art: 'Kathakali',
      resources: ['Hasta Viniyoga PDF', 'Mudra Video', 'AI Practice Link'],
      nudges: ['mudra', 'stretch'],
      lastSession: 'Pataka and Tripataka — foundational single-hand gestures.'
    },
    {
      id: 3, cat: 'kathakali', name: 'Navarasas Exploration',
      duration: '90 min', art: 'Kathakali',
      resources: ['Natyashastra Ch.6', 'Expression Chart', 'Recording Link'],
      nudges: ['stretch', 'water'],
      lastSession: 'Shringara and Karuna rasas — emotional depth exercises.'
    },
    {
      id: 4, cat: 'singing', name: 'Carnatic Foundation — Swaras',
      duration: '60 min', art: 'Singing',
      resources: ['Swara Chart PDF', 'Raga Audio', 'Metronome Track'],
      nudges: ['vocal', 'water'],
      lastSession: 'Sa Re Ga Ma — Shankarabharanam scale in three speeds.'
    },
    {
      id: 5, cat: 'singing', name: 'Gamaka Patterns',
      duration: '45 min', art: 'Singing',
      resources: ['Gamaka Guide PDF', 'Audio Examples'],
      nudges: ['vocal'],
      lastSession: 'Janta swaras and gamaka oscillations in Raga Bhairavi.'
    },
    {
      id: 6, cat: 'drawing', name: 'Charcoal Basics',
      duration: '60 min', art: 'Drawing',
      resources: ['Charcoal Guide PDF', 'Kaggle Art Dataset', 'Reference Gallery'],
      nudges: ['sharpen', 'bring_materials'],
      lastSession: 'Gesture lines and basic shading — 2B and 4B pencils used.'
    },
    {
      id: 7, cat: 'drawing', name: 'Portrait Proportions',
      duration: '90 min', art: 'Drawing',
      resources: ['Proportion Chart PDF', 'Reference Photos', 'Kaggle Portraits'],
      nudges: ['sharpen', 'bring_materials'],
      lastSession: 'Golden ratio placement — eyes at midpoint of head.'
    },
  ];

  const RESOURCE_ICONS = {
    pdf:    '', audio: '', video: '', link: '',
    chart:  '', guide: '', playlist: '', dataset: '',
    report: '', image: '', notes: ''
  };
  function getResourceIcon(label) {
    label = label.toLowerCase();
    if (/audio|raga|swara|track|warmup/.test(label)) return '';
    if (/video|recording/.test(label)) return '';
    if (/pdf|chart|guide|notes|report/.test(label)) return '';
    if (/kaggle|dataset/.test(label)) return '';
    if (/link|ai|practice/.test(label)) return '';
    if (/playlist/.test(label)) return '';
    if (/gallery|image|photo|reference/.test(label)) return '';
    return '';
  }

  // ===================================
  // STATE
  // ===================================
  let currentMode    = 'studio';   // 'studio' | 'director'
  let selectedSlot   = null;
  let selectedSlotEl = null;
  let currentSlotId  = null;

  const slots          = document.querySelectorAll('.time-slot.class-slot');
  const insightEmpty   = document.getElementById('insightEmpty');
  const constructorCard= document.getElementById('constructorCard');
  const timeStrip      = document.getElementById('mainTimeStrip');

  // Assign numeric IDs to slots
  slots.forEach((slot, i) => { slot.dataset.slotId = i + 1; });

  // ===================================
  // TIMEZONE CLOCKS
  // ===================================
  function updateClocks() {
    const now = new Date();
    const utc = now.getTime() + now.getTimezoneOffset() * 60000;

    function fmt(ms) {
      const d = new Date(ms);
      let h = d.getUTCHours(), m = d.getUTCMinutes();
      const ap = h >= 12 ? 'PM' : 'AM';
      h = h % 12 || 12;
      return `${h}:${String(m).padStart(2,'0')} ${ap}`;
    }

    const IST = utc + 5.5 * 3600000;
    const GMT = utc;
    const EST = utc - 5 * 3600000;
    const SGT = utc + 8 * 3600000;

    const el = id => document.getElementById(id);
    if (el('clockIST')) el('clockIST').textContent = fmt(IST);
    if (el('clockGMT')) el('clockGMT').textContent = fmt(GMT);
    if (el('clockEST')) el('clockEST').textContent = fmt(EST);
    if (el('clockSGT')) el('clockSGT').textContent = fmt(SGT);
  }
  updateClocks();
  setInterval(updateClocks, 1000);

  // ===================================
  // MODE TOGGLE
  // ===================================
  document.getElementById('btnStudio')?.addEventListener('click', () => setMode('studio'));
  document.getElementById('btnDirector')?.addEventListener('click', () => setMode('director'));

  function setMode(mode) {
    currentMode = mode;
    document.getElementById('btnStudio')?.classList.toggle('active', mode === 'studio');
    document.getElementById('btnDirector')?.classList.toggle('active', mode === 'director');

    if (timeStrip) {
      timeStrip.classList.toggle('director-mode', mode === 'director');
    }

    // Update constructor workspace class
    if (constructorCard) {
      constructorCard.classList.toggle('studio', mode === 'studio');
      constructorCard.classList.toggle('director', mode === 'director');
    }

    // Update mode label
    const lbl = document.getElementById('constructorModeLabel');
    if (lbl) {
      lbl.textContent = mode === 'studio'
        ? 'Studio View — Read Only'
        : 'Director Mode — Editing';
    }

    // Enable/disable title editing
    const titleInput = document.getElementById('constructorTitle');
    if (titleInput) titleInput.readOnly = mode === 'studio';

    // Prep chips
    document.querySelectorAll('.prep-chip').forEach(c => {
      c.style.pointerEvents = mode === 'director' ? 'auto' : 'none';
    });

    showToast(mode === 'director'
      ? ' Director Mode — you can now edit classes'
      : ' Studio View — read-only mode');
  }

  // ===================================
  // TIME SLOT SELECTION → POPULATE CONSTRUCTOR
  // ===================================
  slots.forEach(slot => {
    slot.addEventListener('click', function () {
      selectSlot(this);
    });
  });

  function selectSlot(el) {
    // Deselect previous
    slots.forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    selectedSlotEl = el;
    currentSlotId = parseInt(el.dataset.slotId);

    // Populate
    populateConstructor(el);

    // Show constructor
    insightEmpty.style.display = 'none';
    constructorCard.style.display = 'flex';
    constructorCard.style.flexDirection = 'column';

    // Apply current mode
    constructorCard.classList.toggle('studio', currentMode === 'studio');
    constructorCard.classList.toggle('director', currentMode === 'director');
    const titleInput = document.getElementById('constructorTitle');
    if (titleInput) titleInput.readOnly = currentMode === 'studio';
  }

  function populateConstructor(slot) {
    const name        = slot.dataset.name;
    const time        = slot.dataset.time;
    const students    = parseInt(slot.dataset.students) || 0;
    const energy      = slot.dataset.energy;
    const energyLevel = slot.dataset.energyLevel;
    const lastSession = slot.dataset.lastSession;
    const lastPage    = slot.dataset.lastPage;
    const resources   = slot.dataset.resources.split(',').map(r => r.trim());

    document.getElementById('constructorTitle').value    = name;
    document.getElementById('constructorTime').textContent    = time;
    document.getElementById('constructorStudents').textContent = students;
    document.getElementById('constructorLastSession').textContent = lastSession;
    document.getElementById('constructorLastPage').textContent   = lastPage;

    const badge = document.getElementById('constructorEnergyBadge');
    badge.className = `energy-badge ${energyLevel}`;
    document.getElementById('constructorEnergy').textContent = energy + ' Energy';

    // Mode label
    const lbl = document.getElementById('constructorModeLabel');
    if (lbl) lbl.textContent = currentMode === 'studio' ? 'Studio View — Read Only' : 'Director Mode — Editing';

    buildStudentPicker(currentSlotId, students);
    buildResourceDock(resources);
  }

  // ===================================
  // STUDENT PICKER
  // ===================================
  function buildStudentPicker(slotId, enrolledCount) {
    const container = document.getElementById('studentPickerFaces');
    if (!container) return;

    // Simulate enrolled students (first N students + random)
    const enrolledIds = ALL_STUDENTS
      .filter(s => s.enrolled.includes(slotId))
      .map(s => s.id);

    container.innerHTML = ALL_STUDENTS.map(student => {
      const isEnrolled = enrolledIds.includes(student.id);
      return `
        <div class="student-face ${isEnrolled ? 'enrolled' : 'not-enrolled'}"
             data-student-id="${student.id}"
             data-enrolled="${isEnrolled}"
             title="${student.name}${isEnrolled ? ' (enrolled)' : ' — click to add'}">
          <div class="student-face-avatar" style="background: ${student.color}">
            ${student.initials}
          </div>
          <span class="student-face-name">${student.name.split(' ')[0]}</span>
        </div>
      `;
    }).join('');

    if (currentMode === 'director') {
      container.innerHTML += `
        <div class="add-student-chip" title="Add student">
          <div class="student-face-avatar">＋</div>
          <span class="student-face-name">Add</span>
        </div>
      `;
    }

    // Toggle enrollment on click (director mode only)
    container.querySelectorAll('.student-face').forEach(face => {
      face.addEventListener('click', () => {
        if (currentMode !== 'director') return;
        const isEnrolled = face.dataset.enrolled === 'true';
        face.dataset.enrolled = !isEnrolled;
        face.classList.toggle('enrolled', !isEnrolled);
        face.classList.toggle('not-enrolled', isEnrolled);
        const name = ALL_STUDENTS.find(s => s.id == face.dataset.studentId)?.name || '';
        showToast(isEnrolled ? `Removed ${name.split(' ')[0]}` : `Added ${name.split(' ')[0]}`);
      });
    });
  }

  // ===================================
  // RESOURCE DOCK
  // ===================================
  function buildResourceDock(resources) {
    const grid = document.getElementById('resourceDockGrid');
    if (!grid) return;

    grid.innerHTML = resources.map((r, i) => `
      <div class="resource-card" data-resource-index="${i}">
        <button class="resource-card-remove" onclick="removeResource(this, ${i})" title="Remove"></button>
        <div class="resource-card-icon">${getResourceIcon(r)}</div>
        <div class="resource-card-name">${r}</div>
        <div class="resource-card-type">${detectType(r)}</div>
      </div>
    `).join('');

    // Add-new card (shown in director mode via CSS)
    grid.innerHTML += `
      <div class="resource-card resource-card-add" onclick="addResource()">
        <span style="font-size:1.4rem">＋</span>
        <span>Attach resource</span>
      </div>
    `;
  }

  function detectType(label) {
    label = label.toLowerCase();
    if (/pdf/.test(label)) return 'Document';
    if (/audio|raga|track/.test(label)) return 'Audio';
    if (/video|recording/.test(label)) return 'Video';
    if (/kaggle|dataset/.test(label)) return 'Dataset';
    if (/link|ai/.test(label)) return 'Link';
    return 'File';
  }

  window.removeResource = function(btn, index) {
    btn.closest('.resource-card')?.remove();
    showToast('Resource removed');
  };

  window.addResource = function() {
    const name = prompt('Resource name:');
    if (!name) return;
    const grid = document.getElementById('resourceDockGrid');
    const addBtn = grid.querySelector('.resource-card-add');
    const card = document.createElement('div');
    card.className = 'resource-card';
    card.innerHTML = `
      <button class="resource-card-remove" onclick="removeResource(this)" title="Remove"></button>
      <div class="resource-card-icon">${getResourceIcon(name)}</div>
      <div class="resource-card-name">${name}</div>
      <div class="resource-card-type">${detectType(name)}</div>
    `;
    grid.insertBefore(card, addBtn);
    showToast('Resource attached');
  };

  // ===================================
  // PREP NUDGE CHIPS
  // ===================================
  document.querySelectorAll('.prep-chip').forEach(chip => {
    chip.addEventListener('click', function () {
      if (currentMode !== 'director') return;
      this.classList.toggle('selected');
      const nudge = this.dataset.nudge;
      const isSelected = this.classList.contains('selected');
      showToast(isSelected
        ? ` "${this.textContent.trim()}" nudge scheduled`
        : `Nudge removed`);
    });
  });

  // ===================================
  // GRAVITY POINTS (Director Mode)
  // ===================================
  document.querySelectorAll('.gravity-point').forEach(gp => {
    gp.querySelector('.gravity-btn')?.addEventListener('click', function (e) {
      e.stopPropagation();
      const time = gp.dataset.time || '—';
      openQuickAdd(time, e.clientX, e.clientY);
    });
  });

  document.querySelectorAll('.ghost-block').forEach(gb => {
    gb.addEventListener('click', function () {
      const time = this.dataset.suggestTime || '—';
      openQuickAdd(time, null, null);
    });
  });

  // ===================================
  // QUICK-ADD BUBBLE
  // ===================================
  const quickAddBubble = document.getElementById('quickAddBubble');
  let qaTargetTime = null;

  function openQuickAdd(time, x, y) {
    qaTargetTime = time;
    document.getElementById('quickAddTime').textContent = formatTime(time);
    document.getElementById('quickAddName').value = '';
    document.getElementById('quickAddDuration').value = '';

    if (x && y) {
      quickAddBubble.style.left = Math.min(x, window.innerWidth - 310) + 'px';
      quickAddBubble.style.top = (y + 10) + 'px';
    } else {
      quickAddBubble.style.left = '50%';
      quickAddBubble.style.transform = 'translateX(-50%)';
      quickAddBubble.style.top = '40%';
    }
    quickAddBubble.classList.add('open');
    document.getElementById('quickAddName').focus();
  }

  function formatTime(t) {
    if (!t) return '—';
    const h = parseInt(t.split(':')[0]);
    const ap = h >= 12 ? 'PM' : 'AM';
    return `${h % 12 || 12}:${(t.split(':')[1] || '00')} ${ap}`;
  }

  document.getElementById('quickAddCancel')?.addEventListener('click', () => {
    quickAddBubble.classList.remove('open');
  });

  document.getElementById('quickAddSave')?.addEventListener('click', () => {
    const name = document.getElementById('quickAddName').value.trim();
    const dur  = document.getElementById('quickAddDuration').value.trim() || '60 min';
    if (!name) return;
    quickAddBubble.classList.remove('open');
    addTimeSlotToTimeline(name, formatTime(qaTargetTime), dur);
    showToast(` "${name}" added to schedule`);
  });

  function addTimeSlotToTimeline(name, time, duration) {
    const strip = document.getElementById('mainTimeStrip');
    const newSlot = document.createElement('div');
    const slotId = slots.length + document.querySelectorAll('.time-slot.class-slot').length + 1;
    newSlot.className = 'time-slot class-slot upcoming-slot';
    newSlot.dataset.name       = name;
    newSlot.dataset.time       = time + ' (' + duration + ')';
    newSlot.dataset.students   = '0';
    newSlot.dataset.energy     = 'New';
    newSlot.dataset.energyLevel = 'warn';
    newSlot.dataset.lastSession = 'No previous session.';
    newSlot.dataset.lastPage    = '—';
    newSlot.dataset.resources   = '';
    newSlot.dataset.slotId      = slotId;
    newSlot.innerHTML = `
      <div class="slot-dot upcoming-dot"></div>
      <div class="slot-info">
        <span class="slot-time">${time}</span>
        <span class="slot-name">${name}</span>
        <span class="slot-tag upcoming-tag">${duration}</span>
      </div>
    `;
    newSlot.addEventListener('click', function() { selectSlot(this); });
    strip.appendChild(newSlot);
    newSlot.scrollIntoView({ behavior: 'smooth', block: 'center' });
    selectSlot(newSlot);
  }

  // ===================================
  // SYLLABUS LIBRARY DRAWER
  // ===================================
  const syllabusDrawer  = document.getElementById('syllabusDrawer');
  const syllabusTabBtn  = document.getElementById('syllabusTabBtn');
  const syllabusClose   = document.getElementById('syllabusDrawerClose');

  syllabusTabBtn?.addEventListener('click', () => {
    syllabusDrawer.classList.toggle('open');
  });
  syllabusClose?.addEventListener('click', () => {
    syllabusDrawer.classList.remove('open');
  });

  // Category filter
  let activeCategory = 'all';
  document.querySelectorAll('.syllabus-cat').forEach(cat => {
    cat.addEventListener('click', function () {
      document.querySelectorAll('.syllabus-cat').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      activeCategory = this.dataset.cat;
      renderSyllabusCards();
    });
  });

  function renderSyllabusCards() {
    const list = document.getElementById('syllabusCardsList');
    if (!list) return;
    const filtered = activeCategory === 'all'
      ? SYLLABUS_TEMPLATES
      : SYLLABUS_TEMPLATES.filter(t => t.cat === activeCategory);

    list.innerHTML = filtered.map(t => `
      <div class="syllabus-card" draggable="true" data-template-id="${t.id}">
        <div class="syllabus-card-name">${t.name}</div>
        <div class="syllabus-card-meta">
          <div class="syllabus-card-duration">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            ${t.duration}
          </div>
          <div class="syllabus-card-art">${t.art}</div>
        </div>
        <div class="syllabus-card-resources">
          ${t.resources.slice(0, 3).map(r => `<span class="syllabus-res-chip">${getResourceIcon(r)} ${r}</span>`).join('')}
          ${t.resources.length > 3 ? `<span class="syllabus-res-chip">+${t.resources.length - 3}</span>` : ''}
        </div>
        <button class="syllabus-card-inject-btn" data-template-id="${t.id}">
          Inject →
        </button>
      </div>
    `).join('');

    // Inject buttons
    list.querySelectorAll('.syllabus-card-inject-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        injectSyllabus(parseInt(btn.dataset.templateId));
      });
    });

    // Drag from library
    list.querySelectorAll('.syllabus-card').forEach(card => {
      card.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('template-id', card.dataset.templateId);
        card.style.opacity = '0.6';
      });
      card.addEventListener('dragend', () => {
        card.style.opacity = '1';
      });
    });
  }

  renderSyllabusCards();

  // Create new template
  document.getElementById('syllabusAddNew')?.addEventListener('click', () => {
    showToast(' Template editor coming soon!');
  });

  // ===================================
  // SYLLABUS INJECT LOGIC
  // ===================================
  function injectSyllabus(templateId) {
    const template = SYLLABUS_TEMPLATES.find(t => t.id === templateId);
    if (!template) return;

    // If a slot is selected, inject into it
    if (selectedSlotEl) {
      selectedSlotEl.dataset.name       = template.name;
      selectedSlotEl.dataset.resources  = template.resources.join(', ');
      selectedSlotEl.dataset.lastSession = template.lastSession;
      selectedSlotEl.dataset.energy     = 'Good';
      selectedSlotEl.dataset.energyLevel = 'good';

      // Update display name
      const nameEl = selectedSlotEl.querySelector('.slot-name');
      if (nameEl) nameEl.textContent = template.name;

      // Repopulate constructor
      populateConstructor(selectedSlotEl);
      buildResourceDock(template.resources);

      // Auto-select nudges
      document.querySelectorAll('.prep-chip').forEach(chip => {
        chip.classList.toggle('selected', template.nudges.includes(chip.dataset.nudge));
      });

      showToast(` "${template.name}" injected into slot`);
    } else {
      // No slot selected — add as a new floating slot
      addTimeSlotToTimeline(template.name, 'TBD', template.duration);
      if (selectedSlotEl) {
        selectedSlotEl.dataset.resources = template.resources.join(', ');
        buildResourceDock(template.resources);
      }
      showToast(` "${template.name}" added to schedule`);
    }

    syllabusDrawer.classList.remove('open');
  }

  // ===================================
  // DRAG SLOTS ONTO TIMELINE (drop from library)
  // ===================================
  document.querySelectorAll('.time-slot.class-slot').forEach(slot => {
    slot.addEventListener('dragover', (e) => {
      e.preventDefault();
      slot.classList.add('drag-over');
    });
    slot.addEventListener('dragleave', () => slot.classList.remove('drag-over'));
    slot.addEventListener('drop', (e) => {
      e.preventDefault();
      slot.classList.remove('drag-over');
      const tid = parseInt(e.dataTransfer.getData('template-id'));
      if (!tid) return;
      selectSlot(slot);
      injectSyllabus(tid);
    });
  });

  // ===================================
  // AUTO-SELECT ACTIVE SLOT ON LOAD
  // ===================================
  const activeSlot = document.querySelector('.time-slot.active-slot');
  if (activeSlot) {
    selectSlot(activeSlot);
  }

  // ===================================
  // TOAST NOTIFICATION
  // ===================================
  let toastTimeout;
  function showToast(msg) {
    const toast = document.getElementById('injectToast');
    const msgEl = document.getElementById('injectToastMsg');
    if (!toast || !msgEl) return;
    msgEl.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => toast.classList.remove('show'), 2800);
  }

});