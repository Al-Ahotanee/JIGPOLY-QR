<?php
// api/student.php
// JIGPOLY Polytechnic - Student API.
//
// NOTE: This file is kept for backwards compatibility / external API consumers.
// The main student dashboard (student.php) handles its own mark_attendance via
// JSON POST to itself and is the primary path used by the UI.
//
// Fixes applied:
//   * Timezone is now pinned to Africa/Lagos through config/db.php (see file).
//     This fixes the "session is not active" false-negative caused by PHP
//     comparing UTC "now" against local wall-clock session times.
//   * Field name corrected from `device_id` to `device_info` to match the
//     `attendance` table schema and the payload sent by student.js.
//   * New attendance rows are inserted with status = 'valid' (previously the
//     NOT NULL column would either default incorrectly or fail).
//   * Missing-field validation added for robustness.

ob_start();
session_start();

// Timezone is set inside config/db.php (Africa/Lagos).
require '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    ob_end_flush();
    exit;
}

$action     = $_GET['action'] ?? '';
$student_id = $_SESSION['user_id'];

switch ($action) {
    case 'mark_attendance':
        $session_id  = isset($_POST['session_id'])  ? (int)$_POST['session_id']        : 0;
        $latitude    = isset($_POST['latitude'])    ? (float)$_POST['latitude']        : 0.0;
        $longitude   = isset($_POST['longitude'])   ? (float)$_POST['longitude']       : 0.0;
        $device_info = $_POST['device_info'] ?? $_POST['device_id'] ?? '';

        if ($session_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid session id']);
            ob_end_flush();
            exit;
        }

        // Fetch the session. A session is "active" only if status = 'active'
        // (the lecturer can pause/deactivate it independently of the time window).
        $stmt = $pdo->prepare(
            "SELECT id, latitude, longitude, radius, start_time, end_time, status
             FROM class_sessions
             WHERE id = ?"
        );
        $stmt->execute([$session_id]);
        $session = $stmt->fetch();

        if (!$session) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid session']);
            ob_end_flush();
            exit;
        }

        if ($session['status'] !== 'active') {
            echo json_encode(['status' => 'error', 'message' => 'Session is not active']);
            ob_end_flush();
            exit;
        }

        // Time-window check. Both `now` and the stored DATETIME values are
        // interpreted in Africa/Lagos (set in config/db.php), so the
        // comparison is now correct.
        $now   = new DateTime();                          // Africa/Lagos
        $start = new DateTime($session['start_time']);    // parsed as Africa/Lagos
        $end   = new DateTime($session['end_time']);      // parsed as Africa/Lagos

        if ($now < $start || $now > $end) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Attendance window closed',
                'debug'   => [
                    'now'             => $now->format('Y-m-d H:i:s'),
                    'start'           => $start->format('Y-m-d H:i:s'),
                    'end'             => $end->format('Y-m-d H:i:s'),
                    'server_timezone' => date_default_timezone_get(),
                ],
            ]);
            ob_end_flush();
            exit;
        }

        // Distance check (Haversine, metres).
        $distance = getDistance($latitude, $longitude, $session['latitude'], $session['longitude']);
        if ($distance > (float)$session['radius']) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'You are not in the class location',
                'distance'=> round($distance, 2),
                'radius'  => (float)$session['radius'],
            ]);
            ob_end_flush();
            exit;
        }

        // Prevent double-marking by the same student.
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND session_id = ?");
        $stmt->execute([$student_id, $session_id]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Attendance already marked']);
            ob_end_flush();
            exit;
        }

        // Prevent the same device being used twice for the same session.
        if ($device_info !== '') {
            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE device_info = ? AND session_id = ?");
            $stmt->execute([$device_info, $session_id]);
            if ($stmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Device already used for this session']);
                ob_end_flush();
                exit;
            }
        }

        $stmt = $pdo->prepare(
            "INSERT INTO attendance (student_id, session_id, latitude, longitude, device_info, status)
             VALUES (?, ?, ?, ?, ?, 'valid')"
        );
        $stmt->execute([$student_id, $session_id, $latitude, $longitude, $device_info]);

        echo json_encode(['status' => 'success', 'message' => 'Attendance marked successfully']);
        ob_end_flush();
        break;

    case 'get_attendance':
        $stmt = $pdo->prepare(
            "SELECT c.name AS course_name, a.timestamp, a.latitude, a.longitude, a.status
             FROM attendance a
             JOIN class_sessions cs ON a.session_id = cs.id
             JOIN courses c ON cs.course_id = c.id
             WHERE a.student_id = ?
             ORDER BY a.timestamp DESC"
        );
        $stmt->execute([$student_id]);
        echo json_encode($stmt->fetchAll());
        ob_end_flush();
        break;

    case 'get_dashboard':
        $total = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ?");
        $total->execute([$student_id]);
        $total_attendance = (int)$total->fetchColumn();

        $today = $pdo->prepare(
            "SELECT COUNT(*) FROM attendance
             WHERE student_id = ? AND timestamp::date = CURRENT_DATE"
        );
        $today->execute([$student_id]);
        $today_status = (int)$today->fetchColumn() > 0 ? 'Present' : 'Absent';

        $courses = $pdo->prepare(
            "SELECT COUNT(*) FROM student_courses WHERE student_id = ?"
        );
        $courses->execute([$student_id]);
        $courses_enrolled = (int)$courses->fetchColumn();

        echo json_encode([
            'status'           => 'success',
            'total_attendance' => $total_attendance,
            'today_status'     => $today_status,
            'courses_enrolled' => $courses_enrolled,
        ]);
        ob_end_flush();
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        ob_end_flush();
        break;
}

/**
 * Haversine distance in metres between two lat/lon points.
 */
function getDistance($lat1, $lon1, $lat2, $lon2) {
    $R  = 6371000; // Earth radius in metres
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a  = sin($dp / 2) * sin($dp / 2) + cos($p1) * cos($p2) * sin($dl / 2) * sin($dl / 2);
    $c  = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}
