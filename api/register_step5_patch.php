<?php
/**
 * REGISTER.PHP — Step 5 replacement block
 * =========================================
 * Replace the existing "if ($step === 5)" block in register.php
 * with this version. It adds MoodleSync::createTeacher() call
 * immediately after the DB insert succeeds.
 *
 * PASTE THIS into register.php replacing the entire if ($step === 5) block.
 */

// At the very top of register.php, after the DB require, add:
// require_once __DIR__ . '/api/MoodleSync.php';

if ($step === 5) {
    if (empty($errors)) {
        $portfolioFiles = [];
        for ($i = 1; $i <= 3; $i++) {
            $p = handleUpload("portfolio_$i");
            if ($p) $portfolioFiles[] = $p;
        }
        $certPath = handleUpload('cert_file', ['image/jpeg','image/png','application/pdf']);
        if ($certPath) $data['cert_path'] = $certPath;

        $studentLevels = implode(',', (array)($_POST['student_levels'] ?? []));

        $parts    = explode(' ', trim($data['name'] ?? ''));
        $initials = '';
        foreach ($parts as $p) { if ($p) $initials .= strtoupper($p[0]); }
        $initials = substr($initials, 0, 2) ?: 'T';

        try {
            $ins = DB::conn()->prepare("
                INSERT INTO teachers
                    (name, email, phone, dob, gender, location, timezone,
                     avatar_path, art_category, art_form, years_experience, awards,
                     qualification, institution, cert_path,
                     student_levels, languages, age_group_pref,
                     instagram, linkedin, youtube, portfolio_url, bio, equipment_needed,
                     specialty, initials, is_approved)
                VALUES
                    (:name,:email,:phone,:dob,:gender,:location,:timezone,
                     :avatar_path,:art_category,:art_form,:years_experience,:awards,
                     :qualification,:institution,:cert_path,
                     :student_levels,:languages,:age_group_pref,
                     :instagram,:linkedin,:youtube,:portfolio_url,:bio,:equipment_needed,
                     :specialty,:initials, 0)
            ");
            $ins->execute([
                ':name'             => $data['name'],
                ':email'            => $data['email'],
                ':phone'            => $data['phone']            ?? null,
                ':dob'              => $data['dob']              ?? null,
                ':gender'           => $data['gender']           ?? null,
                ':location'         => $data['location']         ?? null,
                ':timezone'         => $data['timezone']         ?? 'Asia/Kolkata',
                ':avatar_path'      => $data['avatar_path']      ?? null,
                ':art_category'     => $data['art_category']     ?? null,
                ':art_form'         => $data['art_form']         ?? null,
                ':years_experience' => $data['years_experience'] ?? null,
                ':awards'           => $data['awards']           ?? null,
                ':qualification'    => $data['qualification']    ?? null,
                ':institution'      => $data['institution']      ?? null,
                ':cert_path'        => $data['cert_path']        ?? null,
                ':student_levels'   => $studentLevels            ?: null,
                ':languages'        => $data['languages']        ?? null,
                ':age_group_pref'   => $data['age_group_pref']   ?? null,
                ':instagram'        => $data['instagram']        ?? null,
                ':linkedin'         => $data['linkedin']         ?? null,
                ':youtube'          => $data['youtube']          ?? null,
                ':portfolio_url'    => $data['portfolio_url']    ?? null,
                ':bio'              => $data['bio']              ?? null,
                ':equipment_needed' => $data['equipment_needed'] ?? null,
                ':specialty'        => $data['art_form'] ?? ($data['art_category'] ?? 'Instructor'),
                ':initials'         => $initials,
            ]);
            $newId = (int)DB::conn()->lastInsertId();

            // Save portfolio images
            foreach ($portfolioFiles as $pf) {
                DB::conn()->prepare(
                    'INSERT INTO portfolio_images (teacher_id, file_path) VALUES (?,?)'
                )->execute([$newId, $pf]);
            }

            // ─────────────────────────────────────────────────
            // MOODLE SYNC — create teacher account in Moodle
            // Runs immediately after DB insert succeeds.
            // Failures are logged but do NOT block registration.
            // ─────────────────────────────────────────────────
            try {
                $sync = new MoodleSync();
                $teacherRow = array_merge($data, [
                    'teacher_id' => $newId,
                    'name'       => $data['name'],
                    'email'      => $data['email'],
                    'timezone'   => $data['timezone'] ?? 'Asia/Kolkata',
                    'bio'        => $data['bio']      ?? '',
                    'location'   => $data['location'] ?? '',
                    'art_form'   => $data['art_form'] ?? '',
                ]);
                $moodleResult = $sync->createTeacher($teacherRow);
                // $moodleResult['moodle_user_id'] is now stored in teachers.moodle_user_id
                // $moodleResult['temp_password']  is the temporary Moodle password
                // You can email the teacher their temp_password here if needed
            } catch (Exception $e) {
                // Moodle sync failed — registration still succeeds
                // Error is logged in sync_log table
            }
            // ─────────────────────────────────────────────────

            unset($_SESSION['reg_step'], $_SESSION['reg_data']);
            $success = true;
            $step    = 6;

        } catch (PDOException $e) {
            $errors[] = 'Registration failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}