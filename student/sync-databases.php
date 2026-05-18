<?php
/**
 * Data Sync Between das_teacher and das_student
 * Run this periodically (cron job every 5 minutes)
 */

// Connect to both databases
$teacher_db = new PDO('mysql:host=localhost;dbname=das_teacher', 'root', '');
$student_db = new PDO('mysql:host=localhost;dbname=das_student', 'root', '');

$teacher_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$student_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "🔄 Starting database sync...\n\n";

// ============================================================
// SYNC 1: Teachers → Student Database (for display)
// ============================================================
echo "📤 Syncing teachers to student database...\n";

$teachers = $teacher_db->query("
    SELECT teacher_id, name, specialty, email 
    FROM teachers
")->fetchAll(PDO::FETCH_ASSOC);

$synced = 0;
foreach ($teachers as $teacher) {
    $stmt = $student_db->prepare("
        INSERT INTO teacher_reference (teacher_id, name, specialty, email)
        VALUES (:id, :name, :specialty, :email)
        ON DUPLICATE KEY UPDATE
            name = :name,
            specialty = :specialty,
            email = :email
    ");

    $stmt->execute([
        'id' => $teacher['teacher_id'],
        'name' => $teacher['name'],
        'specialty' => $teacher['specialty'],
        'email' => $teacher['email']
    ]);
    $synced++;
}
echo "  ✓ Synced $synced teachers\n";

// ============================================================
// SYNC 2: Classes → Student Database (for enrollment)
// ============================================================
echo "📤 Syncing classes to student database...\n";

$classes = $teacher_db->query("
    SELECT class_id, teacher_id, name, class_date, class_time, status
    FROM classes
")->fetchAll(PDO::FETCH_ASSOC);

$synced = 0;
foreach ($classes as $class) {
    $stmt = $student_db->prepare("
        INSERT INTO class_reference (class_id, teacher_id, name, class_date, class_time, status)
        VALUES (:id, :teacher_id, :name, :date, :time, :status)
        ON DUPLICATE KEY UPDATE
            teacher_id = :teacher_id,
            name = :name,
            class_date = :date,
            class_time = :time,
            status = :status
    ");

    $stmt->execute([
        'id' => $class['class_id'],
        'teacher_id' => $class['teacher_id'],
        'name' => $class['name'],
        'date' => $class['class_date'],
        'time' => $class['class_time'],
        'status' => $class['status']
    ]);
    $synced++;
}
echo "  ✓ Synced $synced classes\n";

// ============================================================
// SYNC 3: Students → Teacher Database (for teacher view)
// ============================================================
echo "📤 Syncing students to teacher database...\n";

$students = $student_db->query("
    SELECT student_id, label, email, class_id, accuracy_pct, attendance_pct, status
    FROM students
    WHERE status = 'active'
")->fetchAll(PDO::FETCH_ASSOC);

$synced = 0;
foreach ($students as $student) {
    $stmt = $teacher_db->prepare("
        INSERT INTO student_reference (student_id, label, email, class_id, accuracy_pct, attendance_pct, status)
        VALUES (:id, :label, :email, :class_id, :accuracy, :attendance, :status)
        ON DUPLICATE KEY UPDATE
            label = :label,
            email = :email,
            class_id = :class_id,
            accuracy_pct = :accuracy,
            attendance_pct = :attendance,
            status = :status
    ");

    $stmt->execute([
        'id' => $student['student_id'],
        'label' => $student['label'],
        'email' => $student['email'],
        'class_id' => $student['class_id'],
        'accuracy' => $student['accuracy_pct'],
        'attendance' => $student['attendance_pct'],
        'status' => $student['status']
    ]);
    $synced++;
}
echo "  ✓ Synced $synced students\n";

echo "\n✅ Sync completed successfully!\n";
echo "Last sync: " . date('Y-m-d H:i:s') . "\n";
?>