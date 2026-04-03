// ===================================
// KATHAKALI BRIDGE — STUDENTS PAGE
// shared.js handles header, nav, notifications, profile
// ===================================

document.addEventListener('DOMContentLoaded', function () {

  // ===================================
  // FILTER LOGIC
  // ===================================
  const filterSearch = document.getElementById('filterSearch');
  const filterClass  = document.getElementById('filterClass');
  const filterStatus = document.getElementById('filterStatus');
  const studentRows  = document.querySelectorAll('.student-row');
  const noResults    = document.getElementById('noResults');

  function applyFilters() {
    const searchVal = (filterSearch?.value || '').toLowerCase().trim();
    const classVal  = filterClass?.value  || '';
    const statusVal = filterStatus?.value || '';
    let visible = 0;

    studentRows.forEach(function (row) {
      const name      = (row.dataset.name   || '').toLowerCase();
      const rowClass  = row.dataset.class   || '';
      const rowStatus = row.dataset.status  || '';

      const matchSearch = !searchVal || name.includes(searchVal);
      const matchClass  = !classVal  || rowClass  === classVal;
      const matchStatus = !statusVal || rowStatus === statusVal;

      if (matchSearch && matchClass && matchStatus) {
        row.style.display = 'flex';
        visible++;
      } else {
        row.style.display = 'none';
      }
    });

    if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
  }

  filterSearch?.addEventListener('input',  applyFilters);
  filterClass?.addEventListener('change',  applyFilters);
  filterStatus?.addEventListener('change', applyFilters);


  // ===================================
  // STUDENT DETAIL CARD
  // ===================================
  const studentOverlay   = document.getElementById('studentOverlay');
  const studentCardClose = document.getElementById('studentCardClose');

  function openStudentCard(row) {
    const detailAvatar = document.getElementById('detailAvatar');

    // Set avatar initials and background
    if (detailAvatar) {
      const initials = (row.dataset.name || '').split('-').slice(-1)[0] || '--';
      detailAvatar.textContent = 'S' + initials;
      // Pull background from the row avatar
      const rowAvatar = row.querySelector('.student-row-avatar');
      if (rowAvatar) {
        detailAvatar.style.background = rowAvatar.style.background || 'var(--gradient-warm)';
      }
    }

    // Populate fields
    const fields = {
      detailName:        row.dataset.name,
      detailClass:       row.dataset.classLabel,
      detailAccuracy:    row.dataset.accuracy,
      detailAttendance:  row.dataset.attendance,
      detailSubmissions: row.dataset.submissions,
      detailEmail:       row.dataset.email,
      detailPhone:       row.dataset.phone,
      detailClassLabel:  row.dataset.classLabel,
      detailJoined:      row.dataset.joined,
    };

    Object.entries(fields).forEach(function ([id, val]) {
      const el = document.getElementById(id);
      if (el && val) el.textContent = val;
    });

    // Status badge
    const statusEl = document.getElementById('detailStatus');
    if (statusEl) {
      statusEl.textContent = row.dataset.statusLabel || row.dataset.status;
      statusEl.className   = 'status-badge ' + (row.dataset.status || 'active');
    }

    studentOverlay?.classList.add('open');
  }

  studentRows.forEach(function (row) {
    row.addEventListener('click', function () { openStudentCard(this); });
  });

  function closeStudentCard() {
    studentOverlay?.classList.remove('open');
  }

  studentCardClose?.addEventListener('click', closeStudentCard);

  studentOverlay?.addEventListener('click', function (e) {
    if (e.target === studentOverlay) closeStudentCard();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeStudentCard();
  });

});