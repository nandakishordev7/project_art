# Kathakali Bridge - Teacher Dashboard

> *"Preserving tradition through technology - A bridge between ancient heritage and modern silicon"*

## 🪷 Overview

This is the **Teacher Module Dashboard** for the Kathakali Bridge platform - a remote learning system that uses AI mudra recognition to bridge the feedback gap in traditional arts education.

**Design Philosophy: "Temple Courtyard Modernism"**
- Inspired by Kerala's traditional architecture
- Warm terracotta and brass tones
- Organic, flowing layouts
- Glass morphism with backdrop filters
- Accessible across all age groups

---

## 📁 Project Structure

```
teacher-module/
├── teacher-dashboard.html    # Main dashboard HTML
├── styles.css                # Complete styling
├── script.js                 # Interactive functionality
└── README.md                 # This file
```

---

## 🚀 Quick Start Guide

### Method 1: Standalone HTML (For Testing/Preview)

1. **Download all three files** to the same folder:
   - `teacher-dashboard.html`
   - `styles.css`
   - `script.js`

2. **Open `teacher-dashboard.html`** in any modern browser:
   - Chrome (recommended)
   - Firefox
   - Safari
   - Edge

3. **That's it!** The dashboard will load with all styling and interactions.

---

### Method 2: Integration with PHP Project

#### Step 1: File Organization

Place files in your PHP project structure:

```
your-php-project/
├── public/
│   ├── css/
│   │   └── teacher-dashboard.css    # Rename styles.css
│   └── js/
│       └── teacher-dashboard.js     # Rename script.js
├── views/
│   └── teacher/
│       └── dashboard.php            # Create from HTML
└── index.php
```

#### Step 2: Convert HTML to PHP

**Create `views/teacher/dashboard.php`:**

```php
<?php
// Session check (add your authentication logic)
session_start();
if (!isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch teacher data from database
$teacher_name = $_SESSION['teacher_name'] ?? 'Guru Madhavan';
$teacher_specialty = $_SESSION['teacher_specialty'] ?? 'Kathakali Master';
$teacher_avatar = $_SESSION['teacher_avatar'] ?? 'https://api.dicebear.com/7.x/avataaars/svg?seed=teacher';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kathakali Bridge - Teacher Dashboard</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=Philosopher:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Your CSS -->
    <link rel="stylesheet" href="/public/css/teacher-dashboard.css">
</head>
<body>
    <!-- Glass Header -->
    <header class="glass-header">
        <div class="teacher-identity">
            <div class="avatar-ring">
                <img src="<?php echo htmlspecialchars($teacher_avatar); ?>" alt="Teacher Avatar">
            </div>
            <div class="teacher-info">
                <h2 class="teacher-name"><?php echo htmlspecialchars($teacher_name); ?></h2>
                <span class="teacher-specialty"><?php echo htmlspecialchars($teacher_specialty); ?></span>
            </div>
        </div>
        
        <!-- Rest of the header remains the same -->
        <div class="search-container">
            <button class="search-btn" onclick="openSearch()">
                <!-- SVG icon -->
                <span>Search anything...</span>
            </button>
        </div>
        
        <div class="header-actions">
            <button class="icon-btn notification-btn" onclick="openNotifications()">
                <!-- SVG icon -->
                <span class="notification-dot"></span>
            </button>
            <button class="icon-btn profile-btn" onclick="openProfile()">
                <!-- SVG icon -->
            </button>
        </div>
    </header>

    <!-- Copy rest of HTML here -->
    <!-- ... -->

    <script src="/public/js/teacher-dashboard.js"></script>
</body>
</html>
```

#### Step 3: Database Integration

**Fetch upcoming classes from database:**

```php
<?php
// In your dashboard.php, before the HTML

// Fetch next class
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(e.student_id) as student_count
    FROM classes c
    LEFT JOIN enrollments e ON c.class_id = e.class_id
    WHERE c.teacher_id = ? 
    AND c.class_date >= NOW()
    ORDER BY c.class_date ASC
    LIMIT 1
");
$stmt->execute([$_SESSION['teacher_id']]);
$next_class = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch today's schedule
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(e.student_id) as student_count
    FROM classes c
    LEFT JOIN enrollments e ON c.class_id = e.class_id
    WHERE c.teacher_id = ? 
    AND DATE(c.class_date) = CURDATE()
    ORDER BY c.class_time ASC
");
$stmt->execute([$_SESSION['teacher_id']]);
$todays_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending assignments
$stmt = $pdo->prepare("
    SELECT a.*, s.student_name, s.student_avatar
    FROM assignments a
    JOIN students s ON a.student_id = s.student_id
    WHERE a.teacher_id = ? 
    AND a.status = 'pending'
    ORDER BY a.submitted_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['teacher_id']]);
$pending_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
```

**Use in HTML:**

```php
<!-- Widget A: Next Class -->
<div class="widget widget-next-class">
    <?php if ($next_class): ?>
        <div class="next-class-header">
            <span class="widget-label">Upcoming Session</span>
            <span class="time-until" data-class-time="<?php echo $next_class['class_time']; ?>">
                in 45 minutes
            </span>
        </div>
        <div class="class-details">
            <h3 class="class-title"><?php echo htmlspecialchars($next_class['class_name']); ?></h3>
            <div class="class-meta">
                <span class="class-time">
                    <!-- SVG -->
                    <?php echo date('g:i A', strtotime($next_class['class_time'])); ?>
                </span>
                <span class="class-students">
                    <!-- SVG -->
                    <?php echo $next_class['student_count']; ?> Students
                </span>
            </div>
        </div>
        <button class="enter-studio-btn" onclick="enterStudio(<?php echo $next_class['class_id']; ?>)">
            <span>Enter Studio</span>
            <!-- SVG -->
        </button>
    <?php else: ?>
        <p>No upcoming classes scheduled</p>
    <?php endif; ?>
</div>
```

#### Step 4: JavaScript Functions

**Update `teacher-dashboard.js` for PHP integration:**

```javascript
// Enter Studio - navigate to video classroom
function enterStudio(classId) {
    const btn = document.querySelector('.enter-studio-btn');
    btn.innerHTML = `
        <svg>...</svg>
        <span>Entering Studio...</span>
    `;
    
    // Navigate to studio page
    setTimeout(() => {
        window.location.href = `/teacher/studio.php?class_id=${classId}`;
    }, 1500);
}

// Open search modal
function openSearch() {
    // Implement search modal
    console.log('Open search');
}

// Open notifications panel
function openNotifications() {
    // Implement notifications
    console.log('Open notifications');
}

// Open profile menu
function openProfile() {
    // Implement profile dropdown
    console.log('Open profile');
}
```

---

## 🎨 Design Features Explained

### 1. **Glass Header**
- Uses `backdrop-filter: blur()` for the frosted glass effect
- Fixed position with smooth slide-down animation
- Responsive search bar in the center

### 2. **Floating Navigation (macOS-style Dock)**
- Detached from edge by 20px
- Icons-only with hover labels
- Active state with gradient background and glow

### 3. **Widget System**

**Widget A (Next Class):**
- Gradient background (terracotta to brass)
- Large "Enter Studio" CTA button
- Avatar stack for students
- Real-time countdown

**Widget B (Quick Insights):**
- Three insight cards with icons
- Color-coded by category
- Inspirational quote section

**Widget C (Schedule Flow):**
- Vertical timeline with nodes
- Past (faded), Active (glowing), Upcoming (subtle)
- Click nodes to see student lists
- No traditional calendar - shows flow

**Widget D (Assignments):**
- Horizontal scrolling cards
- Video thumbnails with play overlays
- Drag-to-scroll functionality

**Widget E (AI Assistant):**
- Floating lotus icon (cultural reference)
- Bottom-right position
- Glow effect on hover

### 4. **Color Psychology**
- **Terracotta & Brass**: Warmth, tradition, Kerala heritage
- **Sandalwood**: Calm, focus
- **Palm Green**: Growth, learning
- **Lamp Glow**: Guidance, enlightenment

### 5. **Typography**
- **Philosopher**: Display font (headings) - philosophical, refined
- **Cormorant Garamond**: Body font - classical, elegant, readable

### 6. **Zero-Border Policy**
- Uses subtle shadows and color shades instead of hard borders
- Creates depth without harsh lines
- Aligns with Kerala's architectural philosophy

---

## 🔧 Customization Guide

### Change Color Scheme

In `styles.css`, modify the CSS variables:

```css
:root {
    /* Your custom colors */
    --terracotta: #YOUR_COLOR;
    --brass-light: #YOUR_COLOR;
    --brass-dark: #YOUR_COLOR;
    /* ... */
}
```

### Change Fonts

Replace Google Fonts import:

```html
<link href="https://fonts.googleapis.com/css2?family=YOUR_FONT&display=swap" rel="stylesheet">
```

Update CSS variables:

```css
:root {
    --font-display: 'Your Display Font', serif;
    --font-body: 'Your Body Font', serif;
}
```

### Adjust Widget Sizes

In `styles.css`, modify the grid:

```css
.dashboard-container {
    grid-template-columns: 1.5fr 1fr; /* Change ratios */
    gap: var(--space-md); /* Adjust spacing */
}
```

---

## 📱 Responsive Behavior

- **Desktop (>1200px)**: Full 2-column grid layout
- **Tablet (768px-1200px)**: Single column, stacked widgets
- **Mobile (<768px)**: 
  - Search bar hidden
  - Floating nav repositioned
  - Cards scroll horizontally

---

## 🧪 Browser Support

| Browser | Version | Support |
|---------|---------|---------|
| Chrome  | 90+     | ✅ Full  |
| Firefox | 88+     | ✅ Full  |
| Safari  | 14+     | ✅ Full  |
| Edge    | 90+     | ✅ Full  |

**Required Features:**
- CSS Grid
- Flexbox
- Backdrop Filter (for glass effect)
- CSS Variables
- CSS Animations

---

## 🎯 Next Steps

### Pages to Build:

1. **Studio Page** (`studio.php`)
   - Split-screen video layout
   - AI mudra recognition overlay
   - Focus mode (hides UI)

2. **Gallery Page** (`gallery.php`)
   - Masonry grid (Pinterest-style)
   - Lightbox with "Red Pen" critique tool
   - Visual badges instead of grades

3. **Students Page** (`students.php`)
   - Profile cards
   - Growth sparklines (mini graphs)
   - Quick message feature

4. **Schedule Page** (`schedule.php`)
   - Timeline flow (vertical)
   - Past sessions faded
   - Today highlighted

### Features to Add:

- Real-time WebRTC video integration
- AI mudra accuracy overlay
- Notification system
- Search functionality
- Profile settings
- Assignment review modal

---

## 💡 Pro Tips

1. **Performance**: 
   - Images load lazily
   - Animations use CSS transforms (GPU-accelerated)
   - Debounced scroll events

2. **Accessibility**:
   - Add ARIA labels to icon buttons
   - Ensure keyboard navigation works
   - Maintain color contrast ratios

3. **Security**:
   - Always sanitize PHP output with `htmlspecialchars()`
   - Use prepared statements for database queries
   - Validate session data

4. **UX**:
   - Add loading states to all buttons
   - Provide feedback for all interactions
   - Keep animations subtle and purposeful

---

## 🐛 Troubleshooting

**Issue: Glass effect not working**
- Solution: Use Chrome/Firefox. Safari requires `-webkit-backdrop-filter`

**Issue: Fonts not loading**
- Solution: Check internet connection (Google Fonts CDN)

**Issue: Layout broken on mobile**
- Solution: Ensure viewport meta tag is present

**Issue: Animations laggy**
- Solution: Reduce number of animated elements, use `will-change` CSS property

---

## 📄 License

This design is created for the Kathakali Bridge educational platform.
Feel free to modify and adapt for your specific needs.

---

## 🙏 Design Credits

**Inspired by:**
- Kerala's traditional temple architecture
- Jaali (latticed) screen patterns
- Classical Indian art forms
- Modern glass morphism trends

**Cultural References:**
- Lotus icon (spiritual growth in Kathakali)
- Terracotta colors (Kerala's earthen heritage)
- Flowing timelines (the rhythm of practice)

---

## 📞 Support

For questions or customization requests, refer to your project documentation or contact your development team.

---

**Built with ❤️ for preserving tradition through technology**

*"Every mudra is a meditation in motion"*
# project_art
