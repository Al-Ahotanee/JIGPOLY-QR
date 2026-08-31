<?php
// api/lecturer.php
// JIGPOLY Polytechnic - Lecturer API.
//
// Fixes applied:
//   * Uses config/db.php so the PHP/MySQL timezone is pinned to Africa/Lagos.
//     This is the same central fix that resolves the "session is not active"
//     false-negative in the student attendance flow.
//   * Returns end_time in the create_session response so the client countdown
//     can use the server-computed value when the lecturer did not supply one.
//   * Adds the toggle_session / pause / resume + get_attendance + dashboard
//     actions so external consumers using this endpoint keep working.
//   * Hardened input validation.

ob_start();
session_start();

// Timezone is set inside config/db.php (Africa/Lagos).
require '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    ob_end_flush();
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$action      = $_GET['action'] ?? '';

switch ($action) {
    case 'create_session':
        $course_id  = isset($_POST['course_id'])  ? (int)$_POST['course_id']   : 0;
        $latitude   = isset($_POST['latitude'])   ? $_POST['latitude']         : '';
        $longitude  = isset($_POST['longitude'])  ? $_POST['longitude']        : '';
        // datetime-local sends "YYYY-MM-DDTHH:MM". Convert the "T" to a space
        // and add seconds so MySQL DATETIME is happy. If nothing was sent,
        // fall back to "now" / "now + 1h" in the pinned Africa/Lagos timezone.
        $start_time = isset($_POST['start_time']) && $_POST['start_time'] !== ''
            ? str_replace('T', ' ', $_POST['start_time']) . ':00'
            : date('Y-m-d H:i:s');
        $end_time   = isset($_POST['end_time']) && $_POST['end_time'] !== ''
            ? str_replace('T', ' ', $_POST['end_time']) . ':00'
            : date('Y-m-d H:i:s', strtotime('+1 hour'));
        $radius     = isset($_POST['radius']) ? (float)$_POST['radius'] : 50;

        if ($course_id <= 0 || $latitude === '' || $longitude === '') {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            ob_end_flush();
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO class_sessions
                (lecturer_id, course_id, latitude, longitude, start_time, end_time, status, radius)
             VALUES (?, ?, ?, ?, ?, ?, 'active', ?)"
        );
        $stmt->execute([$lecturer_id, $course_id, $latitude, $longitude, $start_time, $end_time, $radius]);
        $session_id = $pdo->lastInsertId('class_sessions_id_seq');

        // Store the QR payload (just the session id is fine — the QR image is
        // generated client-side with qrcodejs).
        $stmt = $pdo->prepare("UPDATE class_sessions SET qr_code = ? WHERE id = ?");
        $stmt->execute([$session_id, $session_id]);

        echo json_encode([
            'status'     => 'success',
            'message'    => 'Session created',
            'session_id' => $session_id,
            'end_time'   => $end_time,
            'start_time' => $start_time,
        ]);
        break;

    case 'get_courses':
        $stmt = $pdo->prepare(
            "SELECT c.id, c.name, c.code
             FROM courses c
             JOIN lecturer_courses lc ON c.id = lc.course_id
             WHERE lc.lecturer_id = ?
             ORDER BY c.name"
        );
        $stmt->execute([$lecturer_id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'get_sessions':
        $stmt = $pdo->prepare(
            "SELECT cs.id, cs.course_id, c.name AS course_name,
                    cs.start_time, cs.end_time, cs.status, cs.radius
             FROM class_sessions cs
             JOIN courses c ON cs.course_id = c.id
             WHERE cs.lecturer_id = ?
             ORDER BY cs.start_time DESC"
        );
        $stmt->execute([$lecturer_id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'toggle_session':
        // Alias for toggle_session_status, used by assets/js/lecturer.js
    case 'toggle_session_status':
        $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
        $new_status = $_POST['status'] ?? ($_POST['action'] === 'pause' ? 'inactive' : 'active');
        $stmt = $pdo->prepare(
            "UPDATE class_sessions SET status = ? WHERE id = ? AND lecturer_id = ?"
        );
        $stmt->execute([$new_status, $session_id, $lecturer_id]);
        echo json_encode(['status' => 'success', 'message' => 'Session status updated']);
        break;

    case 'get_attendance':
        $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
        $stmt = $pdo->prepare(
            "SELECT u.name, u.email, a.timestamp, a.latitude, a.longitude, a.status
             FROM attendance a
             JOIN users u ON a.student_id = u.id
             WHERE a.session_id = ?
             ORDER BY a.timestamp DESC"
        );
        $stmt->execute([$session_id]);
        $result = $stmt->fetchAll();
        echo json_encode($result ?: ['status' => 'empty', 'message' => 'No attendance records']);
        break;

    case 'get_dashboard':
        $total_sessions = $pdo->prepare("SELECT COUNT(*) FROM class_sessions WHERE lecturer_id = ?");
        $total_sessions->execute([$lecturer_id]);
        $total = (int)$total_sessions->fetchColumn();

        $active = $pdo->prepare("SELECT COUNT(*) FROM class_sessions WHERE lecturer_id = ? AND status = 'active'");
        $active->execute([$lecturer_id]);
        $active_sessions = (int)$active->fetchColumn();

        $att = $pdo->prepare(
            "SELECT COUNT(*) FROM attendance a
             JOIN class_sessions cs ON a.session_id = cs.id
             WHERE cs.lecturer_id = ? AND a.timestamp::date = CURRENT_DATE"
        );
        $att->execute([$lecturer_id]);
        $attendance_today = (int)$att->fetchColumn();

        $courses = $pdo->prepare("SELECT COUNT(*) FROM lecturer_courses WHERE lecturer_id = ?");
        $courses->execute([$lecturer_id]);
        $active_courses = (int)$courses->fetchColumn();

        echo json_encode([
            'status'           => 'success',
            'total_sessions'   => $total,
            'active_sessions'  => $active_sessions,
            'attendance_today' => $attendance_today,
            'active_courses'   => $active_courses,
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

ob_end_flush();
