// Digital Art School - Student Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // ========== SIDEBAR NAVIGATION ==========
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const navItems = document.querySelectorAll('.nav-item');
    
    // Mobile menu toggle
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Navigation active state
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
            
            const page = this.getAttribute('data-page');
            console.log('Navigate to:', page);
            // Future: Load different content based on page
        });
    });
    
    // ========== TEACHER CAROUSEL ==========
    const carouselPrev = document.querySelector('.carousel-btn.prev');
    const carouselNext = document.querySelector('.carousel-btn.next');
    const teachersTrack = document.getElementById('teachersTrack');
    
    let currentPosition = 0;
    const cardWidth = 300; // 280px + 20px gap
    
    if (carouselPrev && carouselNext && teachersTrack) {
        carouselPrev.addEventListener('click', function() {
            const maxScroll = 0;
            currentPosition = Math.min(currentPosition + cardWidth, maxScroll);
            teachersTrack.style.transform = `translateX(${currentPosition}px)`;
        });
        
        carouselNext.addEventListener('click', function() {
            const trackWidth = teachersTrack.scrollWidth;
            const containerWidth = teachersTrack.parentElement.offsetWidth;
            const maxScroll = -(trackWidth - containerWidth);
            currentPosition = Math.max(currentPosition - cardWidth, maxScroll);
            teachersTrack.style.transform = `translateX(${currentPosition}px)`;
        });
    }
    
    // ========== CHATBOT ==========
    const chatbotBtn = document.getElementById('chatbotBtn');
    const chatbotPanel = document.getElementById('chatbotPanel');
    const chatbotClose = document.getElementById('chatbotClose');
    const chatbotInput = document.getElementById('chatbotInput');
    const chatbotSend = document.getElementById('chatbotSend');
    const chatbotMessages = document.getElementById('chatbotMessages');
    
    if (chatbotBtn) {
        chatbotBtn.addEventListener('click', function() {
            chatbotPanel.classList.add('active');
            chatbotInput.focus();
        });
    }
    
    if (chatbotClose) {
        chatbotClose.addEventListener('click', function() {
            chatbotPanel.classList.remove('active');
        });
    }
    
    // Send message function
    function sendChatMessage() {
        const message = chatbotInput.value.trim();
        if (!message) return;
        
        // Add user message
        const userMessageHTML = `
            <div class="chat-message user-message">
                <div class="message-avatar">You</div>
                <div class="message-content">
                    <p>${escapeHtml(message)}</p>
                </div>
            </div>
        `;
        chatbotMessages.insertAdjacentHTML('beforeend', userMessageHTML);
        
        // Clear input
        chatbotInput.value = '';
        
        // Scroll to bottom
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        
        // Simulate bot response (replace with actual API call)
        setTimeout(function() {
            const botResponse = getBotResponse(message);
            const botMessageHTML = `
                <div class="chat-message bot-message">
                    <div class="message-avatar">AI</div>
                    <div class="message-content">
                        <p>${botResponse}</p>
                    </div>
                </div>
            `;
            chatbotMessages.insertAdjacentHTML('beforeend', botMessageHTML);
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }, 1000);
    }
    
    if (chatbotSend) {
        chatbotSend.addEventListener('click', sendChatMessage);
    }
    
    if (chatbotInput) {
        chatbotInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
    }
    
    // Simple bot responses
    function getBotResponse(message) {
        const lowerMessage = message.toLowerCase();
        
        if (lowerMessage.includes('teacher') || lowerMessage.includes('instructor')) {
            return "I can help you find the perfect teacher! Browse the teacher carousel below to see available instructors. Each teacher specializes in different art forms and skill levels.";
        } else if (lowerMessage.includes('class') || lowerMessage.includes('course')) {
            return "To enroll in a class, first select a teacher from the carousel. Click 'View Profile' to see their full details and course offerings, then click 'Apply' to send a request.";
        } else if (lowerMessage.includes('assignment') || lowerMessage.includes('homework')) {
            return "Your assignments will appear in the 'Assignments' section once you're enrolled in a course. You can access it from the sidebar navigation.";
        } else if (lowerMessage.includes('schedule')) {
            return "Check the 'Schedule' section in the sidebar to view your calendar, upcoming classes, and important dates.";
        } else if (lowerMessage.includes('help') || lowerMessage.includes('support')) {
            return "I'm here to help! You can ask me about finding teachers, enrolling in courses, viewing assignments, or navigating the platform. What would you like to know?";
        } else {
            return "I'm your learning assistant! I can help you with finding teachers, understanding courses, checking schedules, and navigating the platform. What would you like to know?";
        }
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ========== TEACHER MODAL ==========
    const teacherModal = document.getElementById('teacherModal');
    const closeTeacherModal = document.getElementById('closeTeacherModal');
    
    if (closeTeacherModal) {
        closeTeacherModal.addEventListener('click', function() {
            teacherModal.classList.remove('active');
        });
    }
    
    // Close modal on overlay click
    if (teacherModal) {
        teacherModal.addEventListener('click', function(e) {
            if (e.target === teacherModal) {
                teacherModal.classList.remove('active');
            }
        });
    }
    
    // ========== CALENDAR GENERATION ==========
    generateCalendar();
    
    function generateCalendar() {
        const calendarGrid = document.querySelector('.calendar-grid');
        if (!calendarGrid) return;
        
        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();
        const currentDay = today.getDate();
        
        // Get first day of month and number of days
        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        
        // Clear existing days (keep labels)
        const existingDays = calendarGrid.querySelectorAll('.calendar-day');
        existingDays.forEach(day => day.remove());
        
        // Add empty cells for days before month starts
        for (let i = 0; i < firstDay; i++) {
            const emptyDay = document.createElement('span');
            emptyDay.className = 'calendar-day empty';
            calendarGrid.appendChild(emptyDay);
        }
        
        // Add days of month
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('button');
            dayElement.className = 'calendar-day';
            dayElement.textContent = day;
            
            if (day === currentDay) {
                dayElement.classList.add('today');
            }
            
            // Mark days with events (example: 15th and 20th)
            if (day === 15 || day === 20) {
                dayElement.classList.add('has-event');
            }
            
            calendarGrid.appendChild(dayElement);
        }
    }
    
    // ========== MASTERY PROGRESS ANIMATION ==========
    animateMasteryProgress(0); // Start at 0%
    
    function animateMasteryProgress(percent) {
        const progressRing = document.querySelector('.progress-ring-fill');
        const progressText = document.querySelector('.progress-percent');
        
        if (!progressRing || !progressText) return;
        
        const circumference = 2 * Math.PI * 52; // radius = 52
        const offset = circumference - (percent / 100) * circumference;
        
        progressRing.style.strokeDashoffset = offset;
        progressText.textContent = percent + '%';
    }
    
    // ========== NOTIFICATION CLICK ==========
    const notificationBtn = document.querySelector('.notification-btn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            console.log('Show notifications');
        });
    }
    
    // ========== PROFILE MENU ==========
    const profileMenu = document.querySelector('.profile-menu');
    if (profileMenu) {
        profileMenu.addEventListener('click', function() {
            console.log('Show profile menu');
        });
    }
});

// ========== GLOBAL FUNCTIONS ==========

// View teacher profile (called from PHP)
function viewTeacher(teacherId) {
    const modal = document.getElementById('teacherModal');
    const modalContent = document.getElementById('teacherModalContent');
    
    // Show loading state
    modalContent.innerHTML = '<div style="text-align: center; padding: 40px;">Loading...</div>';
    modal.classList.add('active');
    
    // Fetch teacher details via AJAX
    fetch(`get_teacher_details.php?id=${teacherId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTeacherModal(data.teacher);
            } else {
                modalContent.innerHTML = '<div style="text-align: center; padding: 40px;">Error loading teacher details.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = '<div style="text-align: center; padding: 40px;">Error loading teacher details.</div>';
        });
}

// Display teacher in modal
function displayTeacherModal(teacher) {
    const modalContent = document.getElementById('teacherModalContent');
    const artformColor = getArtformColor(teacher.specialization);
    
    const html = `
        <div class="teacher-detail">
            <div class="teacher-detail-header">
                <div class="teacher-detail-image">
                    ${teacher.profile_image 
                        ? `<img src="../uploads/${teacher.profile_image}" alt="${teacher.teacher_name}">` 
                        : `<div class="teacher-avatar-large">${teacher.teacher_name.charAt(0)}</div>`
                    }
                    <span class="teacher-tag-large" style="background: ${artformColor}">
                        ${teacher.specialization}
                    </span>
                </div>
                <div class="teacher-detail-info">
                    <h2>${teacher.teacher_name}</h2>
                    <p class="teacher-email">${teacher.email}</p>
                    <div class="teacher-stats">
                        <div class="stat">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                            <span>${teacher.experience_years} years experience</span>
                        </div>
                        <div class="stat">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <span>${teacher.current_students}/${teacher.max_students} students</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="teacher-detail-body">
                <div class="detail-section">
                    <h3>About</h3>
                    <p>${teacher.bio || 'No bio available.'}</p>
                </div>
                
                <div class="detail-section">
                    <h3>Art Disciplines</h3>
                    <div class="disciplines-tags">
                        ${teacher.art_disciplines.split(',').map(d => 
                            `<span class="discipline-tag" style="border-color: ${artformColor}">${d.trim()}</span>`
                        ).join('')}
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Available Slots</h3>
                    <div class="time-slots">
                        ${teacher.available_slots.split(',').map(slot => 
                            `<span class="time-slot">${formatSlot(slot.trim())}</span>`
                        ).join('')}
                    </div>
                </div>
                
                ${teacher.current_students < teacher.max_students 
                    ? `<button class="apply-btn-large" onclick="applyToTeacher(${teacher.teacher_id})">
                        Apply to Join Class
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>`
                    : `<div class="class-full-message">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        This class is currently full
                    </div>`
                }
            </div>
        </div>
    `;
    
    modalContent.innerHTML = html;
}

// Apply to teacher
function applyToTeacher(teacherId) {
    if (!confirm('Are you sure you want to apply to join this teacher\'s class?')) {
        return;
    }
    
    // Send application via AJAX
    fetch('apply_to_teacher.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            teacher_id: teacherId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Application sent successfully! The teacher will review your request.');
            document.getElementById('teacherModal').classList.remove('active');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

// Helper functions
function getArtformColor(category) {
    const colors = {
        'Classical Dance': '#0366B0',
        'Vocal Music': '#02B393',
        'Instrumental Music': '#02B393',
        'Visual Arts': '#F3C73B'
    };
    return colors[category] || '#606060';
}

function formatSlot(slot) {
    const slots = {
        'weekday_morning': 'Weekday Morning',
        'weekday_evening': 'Weekday Evening',
        'weekend_morning': 'Weekend Morning',
        'weekend_evening': 'Weekend Evening',
        'weekday_all_day': 'Weekday (Flexible)',
        'weekend_all_day': 'Weekend (Flexible)'
    };
    return slots[slot] || slot;
}
