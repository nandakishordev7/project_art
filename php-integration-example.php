<?php
/**
 * KATHAKALI BRIDGE - Teacher Dashboard
 * PHP Backend Integration Example
 * 
 * This file demonstrates how to integrate the HTML/CSS/JS frontend
 * with your PHP backend and database.
 */

// ============================================
// DATABASE CONFIGURATION
// ============================================

// config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "kathakali_bridge";
    private $username = "your_username";
    private $password = "your_password";
    public $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        return $this->conn;
    }
}


// ============================================
// SUGGESTED DATABASE SCHEMA
// ============================================

/*
-- Teachers Table
CREATE TABLE teachers (
    teacher_id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_name VARCHAR(100) NOT NULL,
    teacher_specialty VARCHAR(100),
    teacher_email VARCHAR(100) UNIQUE,
    teacher_avatar VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Classes Table
CREATE TABLE classes (
    class_id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT,
    class_name VARCHAR(200) NOT NULL,
    class_description TEXT,
    class_date DATE,
    class_time TIME,
    duration_minutes INT DEFAULT 90,
    status ENUM('scheduled', 'active', 'completed', 'cancelled') DEFAULT 'scheduled',
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id)
);

-- Students Table
CREATE TABLE students (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    student_name VARCHAR(100) NOT NULL,
    student_email VARCHAR(100) UNIQUE,
    student_avatar VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enrollments Table
CREATE TABLE enrollments (
    enrollment_id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT,
    student_id INT,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id)
);

-- Assignments Table
CREATE TABLE assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    teacher_id INT,
    assignment_title VARCHAR(200),
    video_url VARCHAR(255),
    thumbnail_url VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'reviewed', 'needs_revision') DEFAULT 'pending',
    ai_accuracy_score DECIMAL(5,2),
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id)
);

-- Session Statistics Table
CREATE TABLE session_stats (
    stat_id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT,
    stat_date DATE,
    total_classes INT DEFAULT 0,
    total_students INT DEFAULT 0,
    avg_accuracy DECIMAL(5,2),
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id)
);
*/


// ============================================
// TEACHER DASHBOARD CONTROLLER
// ============================================

// controllers/TeacherDashboardController.php
class TeacherDashboardController {
    
    private $db;
    private $teacher_id;
    
    public function __construct($teacher_id) {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->teacher_id = $teacher_id;
    }
    
    /**
     * Get teacher profile information
     */
    public function getTeacherProfile() {
        $query = "SELECT * FROM teachers WHERE teacher_id = :teacher_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':teacher_id', $this->teacher_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get next upcoming class for Widget A
     */
    public function getNextClass() {
        $query = "
            SELECT 
                c.*,
                COUNT(e.student_id) as student_count
            FROM classes c
            LEFT JOIN enrollments e ON c.class_id = e.class_id
            WHERE c.teacher_id = :teacher_id 
            AND CONCAT(c.class_date, ' ', c.class_time) >= NOW()
            AND c.status = 'scheduled'
            GROUP BY c.class_id
            ORDER BY c.class_date ASC, c.class_time ASC
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':teacher_id', $this->teacher_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get students enrolled in a specific class
     */
    public function getClassStudents($class_id) {
        $query = "
            SELECT s.*
            FROM students s
            JOIN enrollments e ON s.student_id = e.student_id
            WHERE e.class_id = :class_id
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get today's statistics for Widget B
     */
    public function getTodayStats() {
        // Active classes today
        $query1 = "
            SELECT COUNT(*) as active_classes
            FROM classes
            WHERE teacher_id = :teacher_id
            AND class_date = CURDATE()
            AND status != 'cancelled'
        ";
        $stmt1 = $this->db->prepare($query1);
        $stmt1->bindParam(':teacher_id', $this->teacher_id);
        $stmt1->execute();
        $active_classes = $stmt1->fetch(PDO::FETCH_ASSOC)['active_classes'];
        
        // Pending reviews
        $query2 = "
            SELECT COUNT(*) as pending_reviews
            FROM assignments
            WHERE teacher_id = :teacher_id
            AND status = 'pending'
        ";
        $stmt2 = $this->db->prepare($query2);
        $stmt2->bindParam(':teacher_id', $this->teacher_id);
        $stmt2->execute();
        $pending_reviews = $stmt2->fetch(PDO::FETCH_ASSOC)['pending_reviews'];
        
        // Average accuracy
        $query3 = "
            SELECT AVG(ai_accuracy_score) as avg_accuracy
            FROM assignments
            WHERE teacher_id = :teacher_id
            AND submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        $stmt3 = $this->db->prepare($query3);
        $stmt3->bindParam(':teacher_id', $this->teacher_id);
        $stmt3->execute();
        $avg_accuracy = $stmt3->fetch(PDO::FETCH_ASSOC)['avg_accuracy'];
        
        return [
            'active_classes' => $active_classes,
            'pending_reviews' => $pending_reviews,
            'avg_accuracy' => round($avg_accuracy ?? 0, 0)
        ];
    }
    
    /**
     * Get today's class schedule for Widget C
     */
    public function getTodaySchedule() {
        $query = "
            SELECT 
                c.*,
                COUNT(e.student_id) as student_count,
                CASE
                    WHEN CONCAT(c.class_date, ' ', c.class_time) < NOW() THEN 'completed'
                    WHEN CONCAT(c.class_date, ' ', c.class_time) <= DATE_ADD(NOW(), INTERVAL 1 HOUR) THEN 'active'
                    ELSE 'upcoming'
                END as timeline_status
            FROM classes c
            LEFT JOIN enrollments e ON c.class_id = e.class_id
            WHERE c.teacher_id = :teacher_id
            AND c.class_date = CURDATE()
            AND c.status != 'cancelled'
            GROUP BY c.class_id
            ORDER BY c.class_time ASC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':teacher_id', $this->teacher_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get pending assignments for Widget D
     */
    public function getPendingAssignments($limit = 10) {
        $query = "
            SELECT 
                a.*,
                s.student_name,
                s.student_avatar
            FROM assignments a
            JOIN students s ON a.student_id = s.student_id
            WHERE a.teacher_id = :teacher_id
            AND a.status = 'pending'
            ORDER BY a.submitted_at DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':teacher_id', $this->teacher_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


// ============================================
// USAGE IN DASHBOARD VIEW
// ============================================

// views/teacher/dashboard.php

session_start();

// Check authentication
if (!isset($_SESSION['teacher_id'])) {
    header('Location: /login.php');
    exit;
}

// Initialize controller
require_once '../config/database.php';
require_once '../controllers/TeacherDashboardController.php';

$controller = new TeacherDashboardController($_SESSION['teacher_id']);

// Fetch all data
$teacher = $controller->getTeacherProfile();
$next_class = $controller->getNextClass();
$stats = $controller->getTodayStats();
$schedule = $controller->getTodaySchedule();
$assignments = $controller->getPendingAssignments();

// Get students for next class if exists
$next_class_students = [];
if ($next_class) {
    $next_class_students = $controller->getClassStudents($next_class['class_id']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kathakali Bridge - Teacher Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=Philosopher:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/css/teacher-dashboard.css">
</head>
<body>
    
    <!-- Glass Header -->
    <header class="glass-header">
        <div class="teacher-identity">
            <div class="avatar-ring">
                <img src="<?php echo htmlspecialchars($teacher['teacher_avatar'] ?? '/assets/default-avatar.png'); ?>" alt="Teacher Avatar">
            </div>
            <div class="teacher-info">
                <h2 class="teacher-name"><?php echo htmlspecialchars($teacher['teacher_name']); ?></h2>
                <span class="teacher-specialty"><?php echo htmlspecialchars($teacher['teacher_specialty']); ?></span>
            </div>
        </div>
        
        <div class="search-container">
            <button class="search-btn">
                <!-- SVG icon -->
                <span>Search anything...</span>
            </button>
        </div>
        
        <div class="header-actions">
            <button class="icon-btn notification-btn">
                <!-- SVG icon -->
                <span class="notification-dot"></span>
            </button>
            <button class="icon-btn profile-btn">
                <!-- SVG icon -->
            </button>
        </div>
    </header>

    <!-- Dashboard Content -->
    <main class="dashboard-container">
        
        <!-- Widget A: Next Class -->
        <div class="widget widget-next-class">
            <div class="widget-glow"></div>
            <?php if ($next_class): ?>
                <div class="next-class-header">
                    <span class="widget-label">Upcoming Session</span>
                    <span class="time-until" data-class-time="<?php echo $next_class['class_date'] . ' ' . $next_class['class_time']; ?>">
                        calculating...
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
                    
                    <!-- Student Avatars -->
                    <?php if (!empty($next_class_students)): ?>
                    <div class="student-avatars">
                        <div class="avatar-stack">
                            <?php 
                            $display_count = min(4, count($next_class_students));
                            for ($i = 0; $i < $display_count; $i++): 
                            ?>
                                <img src="<?php echo htmlspecialchars($next_class_students[$i]['student_avatar']); ?>" alt="">
                            <?php endfor; ?>
                            
                            <?php if (count($next_class_students) > 4): ?>
                                <div class="avatar-more">+<?php echo count($next_class_students) - 4; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <button class="enter-studio-btn" onclick="enterStudio(<?php echo $next_class['class_id']; ?>)">
                    <span>Enter Studio</span>
                    <!-- SVG -->
                </button>
            <?php else: ?>
                <div class="next-class-header">
                    <span class="widget-label">No Upcoming Classes</span>
                </div>
                <p>You have no classes scheduled for today.</p>
            <?php endif; ?>
        </div>

        <!-- Widget B: Quick Insights -->
        <div class="widget widget-insights">
            <div class="widget-glow"></div>
            <span class="widget-label">Today's Rhythm</span>
            <div class="insight-grid">
                <div class="insight-card">
                    <div class="insight-icon active-icon"><!-- SVG --></div>
                    <div class="insight-data">
                        <span class="insight-value"><?php echo $stats['active_classes']; ?></span>
                        <span class="insight-label">Active Classes</span>
                    </div>
                </div>
                <div class="insight-card">
                    <div class="insight-icon completion-icon"><!-- SVG --></div>
                    <div class="insight-data">
                        <span class="insight-value"><?php echo $stats['pending_reviews']; ?></span>
                        <span class="insight-label">Pending Reviews</span>
                    </div>
                </div>
                <div class="insight-card">
                    <div class="insight-icon growth-icon"><!-- SVG --></div>
                    <div class="insight-data">
                        <span class="insight-value"><?php echo $stats['avg_accuracy']; ?>%</span>
                        <span class="insight-label">Avg Accuracy</span>
                    </div>
                </div>
            </div>
            <div class="quick-message">
                <p class="quote-text">"Every mudra is a meditation in motion"</p>
            </div>
        </div>

        <!-- Widget C: Schedule Flow -->
        <div class="widget widget-schedule">
            <div class="widget-glow"></div>
            <span class="widget-label">Today's Flow</span>
            <div class="timeline-flow">
                <div class="timeline-line"></div>
                
                <?php foreach ($schedule as $class): ?>
                <div class="timeline-node <?php echo $class['timeline_status']; ?>" 
                     onclick="viewClassDetails(<?php echo $class['class_id']; ?>)">
                    <div class="node-dot"></div>
                    <div class="node-content">
                        <span class="node-time"><?php echo date('g:i A', strtotime($class['class_time'])); ?></span>
                        <span class="node-title"><?php echo htmlspecialchars($class['class_name']); ?></span>
                        <span class="node-meta"><?php echo $class['student_count']; ?> students</span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($schedule)): ?>
                    <p>No classes scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Widget D: Assignments -->
        <div class="widget widget-assignments">
            <div class="widget-glow"></div>
            <div class="assignments-header">
                <span class="widget-label">Awaiting Your Wisdom</span>
                <div class="scroll-hint">
                    <!-- SVG -->
                    <span>Swipe</span>
                    <!-- SVG -->
                </div>
            </div>
            <div class="assignments-scroll">
                <?php foreach ($assignments as $assignment): ?>
                <div class="assignment-card" onclick="reviewAssignment(<?php echo $assignment['assignment_id']; ?>)">
                    <div class="assignment-thumbnail">
                        <div class="play-overlay"><!-- SVG --></div>
                        <img src="<?php echo htmlspecialchars($assignment['thumbnail_url']); ?>" alt="Assignment">
                    </div>
                    <div class="assignment-info">
                        <h4><?php echo htmlspecialchars($assignment['assignment_title']); ?></h4>
                        <span class="student-name"><?php echo htmlspecialchars($assignment['student_name']); ?></span>
                        <span class="submit-time"><?php echo timeAgo($assignment['submitted_at']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Widget E: AI Assistant -->
        <button class="widget widget-assistant">
            <div class="assistant-glow"></div>
            <!-- Lotus SVG icon -->
            <span class="assistant-tooltip">Ask AI Assistant</span>
        </button>

    </main>

    <script src="/public/js/teacher-dashboard.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const teacherId = <?php echo $_SESSION['teacher_id']; ?>;
        
        function enterStudio(classId) {
            window.location.href = `/teacher/studio.php?class_id=${classId}`;
        }
        
        function viewClassDetails(classId) {
            console.log('View class details:', classId);
            // Implement modal or navigation
        }
        
        function reviewAssignment(assignmentId) {
            window.location.href = `/teacher/review.php?assignment_id=${assignmentId}`;
        }
    </script>
</body>
</html>

<?php
// Helper function for time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes') . ' ago';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
    } else {
        $days = floor($difference / 86400);
        return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
    }
}
?>
