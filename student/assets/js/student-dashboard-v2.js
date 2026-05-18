// Digital Art School - Student Dashboard V2 JavaScript

document.addEventListener('DOMContentLoaded', function() {
    
    // ========== MOBILE MENU ==========
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    // ========== TEACHER CAROUSEL ==========
    const prevBtn = document.getElementById('teacherPrev');
    const nextBtn = document.getElementById('teacherNext');
    const track = document.getElementById('teachersTrack');
    
    let position = 0;
    const cardWidth = 276; // 260px + 16px gap
    
    if (prevBtn && nextBtn && track) {
        prevBtn.addEventListener('click', function() {
            position = Math.min(position + cardWidth, 0);
            track.style.transform = `translateX(${position}px)`;
        });
        
        nextBtn.addEventListener('click', function() {
            const maxScroll = -(track.scrollWidth - track.parentElement.offsetWidth);
            position = Math.max(position - cardWidth, maxScroll);
            track.style.transform = `translateX(${position}px)`;
        });
    }
    
    // ========== AI ASSISTANT CHAT ==========
    const assistantBtn = document.getElementById('assistantBtn');
    const chatPanel = document.getElementById('chatPanel');
    const chatCloseBtn = document.getElementById('chatCloseBtn');
    const chatInput = document.getElementById('chatInput');
    const chatSendBtn = document.getElementById('chatSendBtn');
    const chatMessages = document.getElementById('chatMessages');
    
    const responses = [
        'Your next class starts in about 45 minutes.',
        'Based on this week, you\'re showing great progress!',
        'You have 1 pending assignment due tomorrow.',
        'Would you like help finding practice materials?',
        'Teacher 2 has availability for one-on-one sessions.',
        'Your attendance rate is excellent this month!',
        'Consider reviewing Chapter 3 before the next class.'
    ];
    let responseIndex = 0;
    
    if (assistantBtn) {
        assistantBtn.addEventListener('click', function() {
            chatPanel.classList.toggle('open');
            if (chatPanel.classList.contains('open')) {
                chatInput.focus();
            }
        });
    }
    
    if (chatCloseBtn) {
        chatCloseBtn.addEventListener('click', function() {
            chatPanel.classList.remove('open');
        });
    }
    
    function sendMessage() {
        const text = chatInput.value.trim();
        if (!text) return;
        
        // Add user message
        const userMsg = document.createElement('div');
        userMsg.className = 'chat-message user-msg';
        userMsg.innerHTML = `<p>${escapeHtml(text)}</p>`;
        chatMessages.appendChild(userMsg);
        
        chatInput.value = '';
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Simulate bot response
        setTimeout(function() {
            const botMsg = document.createElement('div');
            botMsg.className = 'chat-message assistant-msg';
            botMsg.innerHTML = `<p>${responses[responseIndex % responses.length]}</p>`;
            chatMessages.appendChild(botMsg);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            responseIndex++;
        }, 1000);
    }
    
    if (chatSendBtn) {
        chatSendBtn.addEventListener('click', sendMessage);
    }
    
    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ========== MINI CALENDAR ==========
    generateMiniCalendar();
    
    function generateMiniCalendar() {
        const calendarDiv = document.getElementById('miniCalendar');
        if (!calendarDiv) return;
        
        const today = new Date();
        const month = today.toLocaleString('default', { month: 'long', year: 'numeric' });
        
        let html = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="font-size: 14px; font-weight: 600;">${month}</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center;">
        `;
        
        // Day labels
        ['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach(day => {
            html += `<div style="font-size: 11px; font-weight: 600; color: #606060; padding: 6px 0;">${day}</div>`;
        });
        
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).getDay();
        const daysInMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
        
        // Empty cells
        for (let i = 0; i < firstDay; i++) {
            html += '<div></div>';
        }
        
        // Days
        for (let day = 1; day <= daysInMonth; day++) {
            const isToday = day === today.getDate();
            const style = isToday 
                ? 'background: #0366B0; color: #fff; font-weight: 700;' 
                : 'color: #0d1f35;';
            html += `<div style="padding: 6px; border-radius: 6px; font-size: 13px; cursor: pointer; ${style}">${day}</div>`;
        }
        
        html += '</div>';
        calendarDiv.innerHTML = html;
    }
    
    // ========== ENTER STUDIO BUTTON ==========
    const studioBtn = document.querySelector('.enter-studio-btn');
    if (studioBtn) {
        studioBtn.addEventListener('click', function() {
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span>Loading...</span>';
            this.disabled = true;
            setTimeout(() => {
                this.innerHTML = originalHTML;
                this.disabled = false;
                window.location.href = 'my_studio.php';
            }, 1500);
        });
    }
    
});

// ========== GLOBAL FUNCTIONS ==========

function viewTeacher(teacherId) {
    const modal = document.getElementById('teacherModal');
    const modalContent = document.getElementById('modalContent');
    
    modalContent.innerHTML = '<div style="text-align: center; padding: 40px;">Loading...</div>';
    modal.classList.add('open');
    
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

function displayTeacherModal(teacher) {
    const modalContent = document.getElementById('modalContent');
    const artformColor = getArtformColor(teacher.specialization);
    const teacherNum = teacher.teacher_id;
    
    const html = `
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #0366B0, #02B393); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 36px; font-weight: 700; margin-bottom: 16px;">
                ${teacherNum}
            </div>
            <h2 style="font-size: 24px; font-weight: 700; color: #0d1f35; margin-bottom: 8px;">Teacher ${teacherNum}</h2>
            <span style="display: inline-block; padding: 6px 16px; background: ${artformColor}; color: #fff; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase;">
                ${teacher.specialization}
            </span>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 12px; color: #0d1f35;">About</h3>
            <p style="color: #606060; line-height: 1.6;">${teacher.bio || 'Experienced instructor specializing in traditional art forms.'}</p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 12px; color: #0d1f35;">Disciplines</h3>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                ${teacher.art_disciplines.split(',').map(d => 
                    `<span style="padding: 6px 12px; border: 2px solid ${artformColor}; border-radius: 16px; font-size: 13px; font-weight: 600;">${d.trim()}</span>`
                ).join('')}
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 12px; color: #0d1f35;">Experience</h3>
            <p style="color: #606060;">${teacher.experience_years} years of teaching experience</p>
        </div>
        
        ${teacher.current_students < teacher.max_students 
            ? `<button onclick="applyToTeacher(${teacher.teacher_id})" style="width: 100%; padding: 16px; background: #0366B0; color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer;">
                Apply to Join Class
            </button>`
            : `<div style="padding: 16px; background: rgba(96, 96, 96, 0.1); border-radius: 12px; text-align: center; color: #606060; font-weight: 600;">
                Class Currently Full
            </div>`
        }
    `;
    
    modalContent.innerHTML = html;
}

function applyToTeacher(teacherId) {
    if (!confirm('Apply to join this teacher\'s class?')) {
        return;
    }
    
    fetch('apply_to_teacher.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ teacher_id: teacherId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Application sent successfully!');
            document.getElementById('teacherModal').classList.remove('open');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function getArtformColor(category) {
    const colors = {
        'Classical Dance': '#0366B0',
        'Vocal Music': '#02B393',
        'Instrumental Music': '#02B393',
        'Visual Arts': '#F3C73B'
    };
    return colors[category] || '#606060';
}

// Close modal
document.getElementById('closeModal')?.addEventListener('click', function() {
    document.getElementById('teacherModal').classList.remove('open');
});

document.getElementById('teacherModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('open');
    }
});
