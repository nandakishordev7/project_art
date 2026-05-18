<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Digital Art School</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="assets\css\student-styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

  <!-- Header + Nav injected by shared-student.js -->

  <div class="content-wrapper" id="contentWrapper">
    <main class="dashboard-container student-dashboard">

      <!-- =====================
           LEFT COLUMN
           ===================== -->
      <div class="left-column">

        <!-- WELCOME WIDGET -->
        <div class="widget widget-welcome" id="welcomeWidget">
          <div class="widget-glow"></div>

          <!-- Loading skeleton shown until DB data arrives -->
          <div class="welcome-skeleton" id="welcomeSkeleton">
            <div class="skel skel-avatar"></div>
            <div class="skel-lines">
              <div class="skel skel-line w70"></div>
              <div class="skel skel-line w50"></div>
            </div>
          </div>

          <!-- Real content, hidden until data loads -->
          <div class="welcome-content" id="welcomeContent" style="display:none;">
            <div class="welcome-icon">
              <!-- Icon swapped dynamically based on art_category -->
              <svg id="welcomeCategoryIcon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
              </svg>
            </div>
            <div class="welcome-text">
              <h2 class="welcome-greeting">
                Welcome, <span id="studentFirstName"></span>
              </h2>
              <p class="welcome-quote" id="welcomeQuote"></p>
            </div>
          </div>

          <!-- Status row -->
          <div class="welcome-status" id="welcomeStatus" style="display:none;">
            <div class="status-badge" id="statusBadge">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <span id="statusText">Loading…</span>
            </div>
          </div>

          <!-- Enter Studio (only when a class is scheduled) -->
          <button class="enter-studio-btn" id="enterStudioBtn" style="display:none;">
            <span>Enter Studio</span>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="5" y1="12" x2="19" y2="12"/>
              <polyline points="12 5 19 12 12 19"/>
            </svg>
          </button>
        </div>
        <!-- /WELCOME WIDGET -->

        <!-- TEACHER SELECTION WIDGET -->
        <div class="widget widget-teacher-selection" id="teacherSelectionWidget">
          <div class="widget-glow"></div>

          <div class="teacher-selection-header">
            <span class="widget-label">Find Your Teacher</span>
            <span class="teacher-count" id="teacherCount">
              <span class="skel skel-pill"></span>
            </span>
          </div>
          <p class="teacher-selection-desc">
            Teachers below match your chosen art form. Send a request to get started.
          </p>

          <!-- Teacher cards injected here by student-script.js after DB fetch -->
          <div class="teacher-cards" id="teacherCards">
            <!-- Loading skeletons -->
            <div class="teacher-card teacher-card-skeleton">
              <div class="skel skel-avatar-sm"></div>
              <div class="skel-lines" style="flex:1;">
                <div class="skel skel-line w60"></div>
                <div class="skel skel-line w40"></div>
              </div>
            </div>
            <div class="teacher-card teacher-card-skeleton">
              <div class="skel skel-avatar-sm"></div>
              <div class="skel-lines" style="flex:1;">
                <div class="skel skel-line w60"></div>
                <div class="skel skel-line w40"></div>
              </div>
            </div>
            <div class="teacher-card teacher-card-skeleton">
              <div class="skel skel-avatar-sm"></div>
              <div class="skel-lines" style="flex:1;">
                <div class="skel skel-line w60"></div>
                <div class="skel skel-line w40"></div>
              </div>
            </div>
          </div>
        </div>
        <!-- /TEACHER SELECTION WIDGET -->

      </div>
      <!-- /LEFT COLUMN -->


      <!-- =====================
           RIGHT COLUMN
           ===================== -->
      <div class="right-column">
        <div class="flip-container" id="flipContainer">

          <!-- FRONT: Calendar + Profile -->
          <div class="flip-face flip-front">
            <div class="widget widget-info">
              <div class="widget-glow"></div>

              <!-- Calendar section -->
              <div class="info-section calendar-section">
                <div class="section-header">
                  <span class="widget-label">Calendar</span>
                  <button class="flip-trigger-btn" id="flipToChatBtn" title="Open AI Assistant">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                  </button>
                </div>

                <div class="mini-calendar">
                  <div class="calendar-header">
                    <button class="cal-nav-btn" id="calPrevBtn">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                      </svg>
                    </button>
                    <span class="cal-month-year" id="calMonthYear"></span>
                    <button class="cal-nav-btn" id="calNextBtn">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                      </svg>
                    </button>
                  </div>
                  <div class="calendar-weekdays">
                    <span>S</span><span>M</span><span>T</span><span>W</span>
                    <span>T</span><span>F</span><span>S</span>
                  </div>
                  <div class="calendar-days" id="calendarDays"></div>
                </div>
              </div>

              <div class="info-divider"></div>

              <!-- Profile section -->
              <div class="info-section profile-section">
                <div class="section-header">
                  <span class="widget-label">My Profile</span>
                </div>

                <!-- Skeleton until data loads -->
                <div id="profileSkeleton">
                  <div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
                    <div class="skel skel-avatar-lg"></div>
                    <div class="skel skel-line w50"></div>
                    <div class="skel skel-line w35"></div>
                  </div>
                </div>

                <!-- Real profile, hidden until loaded -->
                <div class="profile-card-mini" id="profileCardMini" style="display:none;">
                  <div class="profile-avatar-large" id="profileAvatarLarge"></div>
                  <h4 class="profile-name-large"   id="profileNameLarge"></h4>
                  <p  class="profile-discipline"   id="profileDiscipline"></p>

                  <div class="profile-stats">
                    <div class="stat-item">
                      <span class="stat-value" id="statSkillLevel"></span>
                      <span class="stat-label">Skill Level</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                      <span class="stat-value" id="statExperience"></span>
                      <span class="stat-label">Experience</span>
                    </div>
                  </div>

                  <div class="profile-details">
                    <div class="detail-row">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                      </svg>
                      <span id="profileEmail"></span>
                    </div>
                    <div class="detail-row">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                      </svg>
                      <span id="profileLocation"></span>
                    </div>
                  </div>
                </div>
              </div>
              <!-- /profile section -->

            </div>
          </div>
          <!-- /FRONT FACE -->

          <!-- BACK: AI Chatbot -->
          <div class="flip-face flip-back">
            <div class="widget widget-chatbot">
              <div class="widget-glow"></div>

              <div class="chatbot-header">
                <div class="chatbot-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/>
                    <path d="M8 11c-2-2-4-2-6-1 4 1 6 3 6 7 0-4-2-6-6-7 2-1 4-1 6 1z"/>
                    <path d="M16 11c2-2 4-2 6-1-4 1-6 3-6 7 0-4 2-6 6-7-2-1-4-1-6 1z"/>
                  </svg>
                  <span>AI Learning Assistant</span>
                </div>
                <button class="flip-trigger-btn" id="flipToInfoBtn" title="Back to Calendar">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6"  y1="6" x2="18" y2="18"/>
                  </svg>
                </button>
              </div>

              <div class="chatbot-messages" id="chatbotMessages">
                <div class="chatbot-message bot-msg">
                  <div class="bot-avatar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/>
                    </svg>
                  </div>
                  <div class="bot-msg-content">
                    <p>Hello! I'm your AI learning assistant. Ask me anything about your courses, practice schedule, or upcoming sessions.</p>
                  </div>
                </div>
              </div>

              <div class="chatbot-input-area">
                <input type="text" class="chatbot-input" id="chatbotInput" placeholder="Ask me anything…">
                <button class="chatbot-send-btn" id="chatbotSendBtn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                  </svg>
                </button>
              </div>

            </div>
          </div>
          <!-- /BACK FACE -->

        </div>
      </div>
      <!-- /RIGHT COLUMN -->

    </main>
  </div>

  <!-- TEACHER REQUEST MODAL -->
  <div class="modal-overlay" id="requestModal">
    <div class="modal-content request-modal">
      <div class="modal-header">
        <h3 id="requestModalTitle">Request Teacher</h3>
        <button class="modal-close-btn" id="requestModalClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6"  y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="request-teacher-info" id="requestTeacherInfo"></div>
        <div class="form-group">
          <label for="requestMessage">Message to Teacher <span style="font-weight:400;color:#888;">(optional)</span></label>
          <textarea id="requestMessage" rows="4"
            placeholder="Introduce yourself or share why you'd like to study with this teacher…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" id="requestCancelBtn">Cancel</button>
        <button class="btn-primary"   id="requestSubmitBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="22" y1="2" x2="11" y2="13"/>
            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
          Send Request
        </button>
      </div>
    </div>
  </div>

  <script src="shared-student.js"></script>
  <script src="student-script.js"></script>
</body>
</html>