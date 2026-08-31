<?php
// lecturer.php
// JIGPOLY Polytechnic (OND, ND, HND)
// Lecturer Dashboard.
//
// Fixes applied:
//   * Replaced the inline DB connection with config/db.php so the PHP/MySQL
//     timezone is pinned to Africa/Lagos. This is the central fix for the
//     "session is not active" false-negative: previously `new DateTime()`
//     used the server default (UTC) while session start/end times were
//     stored as local wall-clock strings, so the time-window comparison was
//     off by one hour and the student's attendance was rejected.
//   * Normalised `datetime-local` values ("YYYY-MM-DDTHH:MM") to MySQL
//     DATETIME format ("YYYY-MM-DD HH:MM:SS") before storing, and return
//     start_time + end_time to the client.
//   * Rebranded from "FCIT BUK" to "CST JIGPOLY Polytechnic".

ob_start();
session_start();

// Central connection — sets Africa/Lagos timezone for PHP + MySQL.
require 'config/db.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: login.php');
    exit;
}

$lecturer_id = $_SESSION['user_id'];

/**
 * Convert an HTML datetime-local value ("YYYY-MM-DDTHH:MM") into a MySQL
 * DATETIME string ("YYYY-MM-DD HH:MM:SS"). Falls back to $default when the
 * input is empty.
 */
function normalise_datetime(?string $value, string $default): string {
    if ($value === null || $value === '') {
        return $default;
    }
    // Replace the "T" separator with a space and append seconds if missing.
    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }
    return $value;
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    switch ($action) {
        case 'create_session':
            $course_id  = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
            $latitude   = $_POST['latitude'] ?? '';
            $longitude  = $_POST['longitude'] ?? '';
            $start_time = normalise_datetime($_POST['start_time'] ?? null, date('Y-m-d H:i:s'));
            $end_time   = normalise_datetime($_POST['end_time'] ?? null,   date('Y-m-d H:i:s', strtotime('+1 hour')));
            $radius     = isset($_POST['radius']) ? (float)$_POST['radius'] : 50;

            if ($course_id <= 0 || $latitude === '' || $longitude === '') {
                echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
                break;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO class_sessions
                    (lecturer_id, course_id, latitude, longitude, start_time, end_time, status, radius)
                 VALUES (?, ?, ?, ?, ?, ?, 'active', ?)"
            );
            $stmt->execute([$lecturer_id, $course_id, $latitude, $longitude, $start_time, $end_time, $radius]);
            $session_id = $pdo->lastInsertId('class_sessions_id_seq');

            $stmt = $pdo->prepare("UPDATE class_sessions SET qr_code = ? WHERE id = ?");
            $stmt->execute([$session_id, $session_id]);

            echo json_encode([
                'status'     => 'success',
                'message'    => 'Session created',
                'session_id' => $session_id,
                'start_time' => $start_time,
                'end_time'   => $end_time,
            ]);
            break;

        case 'toggle_session_status':
            $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
            $new_status = $_POST['status'] ?? 'inactive';
            $stmt = $pdo->prepare("UPDATE class_sessions SET status = ? WHERE id = ? AND lecturer_id = ?");
            $stmt->execute([$new_status, $session_id, $lecturer_id]);
            echo json_encode(['status' => 'success', 'message' => 'Session status updated']);
            break;

        case 'get_attendance':
            $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
            $stmt = $pdo->prepare(
                "SELECT u.name, u.email, a.timestamp
                 FROM attendance a
                 JOIN users u ON a.student_id = u.id
                 WHERE a.session_id = ?"
            );
            $stmt->execute([$session_id]);
            $result = $stmt->fetchAll();
            echo json_encode($result ?: ['status' => 'empty', 'message' => 'No attendance records']);
            break;

        case 'update_session':
            $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
            $course_id  = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
            $start_time = normalise_datetime($_POST['start_time'] ?? null, date('Y-m-d H:i:s'));
            $end_time   = normalise_datetime($_POST['end_time'] ?? null,   date('Y-m-d H:i:s', strtotime('+1 hour')));
            $radius     = isset($_POST['radius']) ? (float)$_POST['radius'] : 50;

            $stmt = $pdo->prepare(
                "UPDATE class_sessions
                 SET course_id = ?, start_time = ?, end_time = ?, radius = ?
                 WHERE id = ? AND lecturer_id = ?"
            );
            $stmt->execute([$course_id, $start_time, $end_time, $radius, $session_id, $lecturer_id]);
            echo json_encode(['status' => 'success', 'message' => 'Session updated']);
            break;

        case 'delete_session':
            $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
            $stmt = $pdo->prepare("DELETE FROM class_sessions WHERE id = ? AND lecturer_id = ?");
            $stmt->execute([$session_id, $lecturer_id]);
            echo json_encode(['status' => 'success', 'message' => 'Session deleted']);
            break;

        case 'get_attendance_stats':
            $stmt = $pdo->prepare(
                "SELECT c.name AS course_name, COUNT(a.id) AS attendance_count
                 FROM class_sessions cs
                 LEFT JOIN attendance a ON cs.id = a.session_id
                 JOIN courses c ON cs.course_id = c.id
                 WHERE cs.lecturer_id = ?
                 GROUP BY cs.course_id, c.name"
            );
            $stmt->execute([$lecturer_id]);
            $result = $stmt->fetchAll();
            echo json_encode($result ?: [['course_name' => 'No Data', 'attendance_count' => 0]]);
            break;

        case 'get_feedback':
            $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
            $stmt = $pdo->prepare(
                "SELECT u.name, sf.feedback, sf.submitted_at
                 FROM session_feedback sf
                 JOIN users u ON sf.student_id = u.id
                 WHERE sf.session_id = ?"
            );
            $stmt->execute([$session_id]);
            $result = $stmt->fetchAll();
            echo json_encode($result ?: ['status' => 'empty', 'message' => 'No feedback']);
            break;

        case 'get_excuses':
            $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
            $stmt = $pdo->prepare(
                "SELECT ae.id, u.name, ae.reason, ae.document_path, ae.status, ae.timestamp
                 FROM attendance_excuses ae
                 JOIN users u ON ae.student_id = u.id
                 WHERE ae.session_id = ?"
            );
            $stmt->execute([$session_id]);
            $result = $stmt->fetchAll();
            echo json_encode($result ?: ['status' => 'empty', 'message' => 'No excuses']);
            break;

        case 'update_excuse':
            $excuse_id = isset($_POST['excuse_id']) ? (int)$_POST['excuse_id'] : 0;
            $status    = $_POST['status'] ?? 'pending';
            $comment   = $_POST['comment'] ?? '';
            $stmt = $pdo->prepare("UPDATE attendance_excuses SET status = ?, lecturer_comment = ? WHERE id = ?");
            $stmt->execute([$status, $comment, $excuse_id]);
            echo json_encode(['status' => 'success', 'message' => 'Excuse updated']);
            break;

        case 'logout':
            $_SESSION = [];
            session_destroy();
            echo json_encode(['status' => 'success', 'message' => 'Logged out']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
    ob_end_flush();
    exit;
}

// Fetch data for frontend
$courses = $pdo->prepare(
    "SELECT c.id, c.name, c.code
     FROM courses c
     JOIN lecturer_courses lc ON c.id = lc.course_id
     WHERE lc.lecturer_id = ?"
);
$courses->execute([$lecturer_id]);
$course_list = $courses->fetchAll();

$sessions = $pdo->prepare(
    "SELECT cs.id, c.name AS course_name, cs.start_time, cs.end_time, cs.status, cs.course_id, cs.radius
     FROM class_sessions cs
     JOIN courses c ON cs.course_id = c.id
     WHERE cs.lecturer_id = ?
     ORDER BY cs.start_time DESC"
);
$sessions->execute([$lecturer_id]);
$session_list = $sessions->fetchAll();

// Stats for dashboard
$total_sessions  = count($session_list);
$active_sessions = count(array_filter($session_list, fn($s) => $s['status'] === 'active'));
$total_attendance_stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM attendance a JOIN class_sessions cs ON a.session_id = cs.id WHERE cs.lecturer_id = ?"
);
$total_attendance_stmt->execute([$lecturer_id]);
$total_attendance = (int)$total_attendance_stmt->fetchColumn();

// Branding constants
$BRAND_SHORT = 'JIGPOLY Polytechnic';
$BRAND_FULL  = 'JIGPOLY Polytechnic';
$BRAND_PROG  = 'OND / ND / HND';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - <?php echo $BRAND_SHORT; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue-start: #1e3c72;
            --gold-end: #f7c948;
            --gradient: linear-gradient(135deg, var(--blue-start), var(--gold-end));
            --text-dark: #1a2a44;
            --text-light: #ffffff;
            --shadow: 0 8px 24px rgba(30, 60, 114, 0.3);
        }
        body { font-family: 'Poppins', sans-serif; background: var(--gradient); color: var(--text-dark); min-height: 100vh; }
        .wrapper { background: none; }
        .main-header { background: var(--gradient); border-bottom: 2px solid rgba(255, 255, 255, 0.2); box-shadow: var(--shadow); }
        .navbar-nav .nav-link { color: var(--text-light) !important; font-weight: 600; }
        .navbar-nav .nav-link:hover { color: #f7c948 !important; }
        .sidebar { background: var(--gradient); box-shadow: var(--shadow); }
        .brand-link { background: rgba(255, 255, 255, 0.1); color: var(--text-light); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .nav-sidebar .nav-link { color: var(--text-light) !important; transition: all 0.3s ease; border-radius: 8px; margin: 5px 10px; }
        .nav-sidebar .nav-link:hover, .nav-sidebar .nav-link.active { background: rgba(255, 255, 255, 0.2); color: #f7c948 !important; transform: translateX(5px); }
        .nav-icon { margin-right: 10px; }
        .content-wrapper { background: rgba(255, 255, 255, 0.95); border-radius: 15px; margin: 20px; box-shadow: var(--shadow); }
        .content-header h1 { color: var(--blue-start); font-weight: 700; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1); }
        .small-box { background: var(--gradient); color: var(--text-light); border-radius: 12px; box-shadow: var(--shadow); transition: transform 0.3s ease; }
        .small-box:hover { transform: translateY(-5px); }
        .small-box .inner h3 { font-weight: 700; }
        .small-box .icon { font-size: 2.5rem; opacity: 0.8; }
        .card { background: rgba(255, 255, 255, 0.9); border: none; border-radius: 12px; box-shadow: var(--shadow); overflow: hidden; }
        .card-header { background: var(--gradient); color: var(--text-light); font-weight: 600; border-bottom: none; }
        .btn-primary { background: var(--gradient); border: none; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
        .btn-primary:hover { background: linear-gradient(135deg, #f7c948, #1e3c72); box-shadow: var(--shadow); }
        .btn-success { background: #28a745; border: none; }
        .btn-danger { background: #dc3545; border: none; }
        .btn-info { background: #17a2b8; border: none; }
        .btn-warning { background: #ffc107; border: none; color: var(--text-dark); }
        .table { color: var(--text-dark); }
        .table th, .table td { border-color: rgba(30, 60, 114, 0.2); }
        .table-hover tbody tr:hover { background: rgba(247, 201, 72, 0.1); }
        .countdown { font-weight: 600; color: #f7c948; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }
        .status-expired { color: #6c757d; font-weight: bold; }
        .form-control, .form-select { border-radius: 8px; border-color: var(--blue-start); color: var(--text-dark); }
        .form-control:focus, .form-select:focus { border-color: #f7c948; box-shadow: 0 0 8px rgba(247, 201, 72, 0.5); }
        .modal-content { background: rgba(255, 255, 255, 0.95); border-radius: 12px; box-shadow: var(--shadow); }
        .modal-header { background: var(--gradient); color: var(--text-light); border-bottom: none; }
        .modal-body { color: var(--text-dark); }
        .chart-container { max-width: 500px; margin: 20px auto; }
        #qr-display .btn { margin: 0 10px; }
        @media (max-width: 768px) {
            .content-wrapper { margin: 10px; }
            .small-box { margin-bottom: 15px; }
            .sidebar { width: 70px; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="#" id="logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="#" class="brand-link">
            <span class="brand-text"><?php echo $BRAND_SHORT; ?> Lecturer</span>
        </a>
        <div class="sidebar">
            <nav class="mt-3">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#" class="nav-link active" data-section="dashboard"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="create-session"><i class="nav-icon fas fa-plus-circle"></i><p>Create Session</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="sessions"><i class="nav-icon fas fa-list-ul"></i><p>My Sessions</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="attendance_register.php" class="nav-link"><i class="nav-icon fas fa-book"></i><p>Attendance Register</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="feedback"><i class="nav-icon fas fa-comment-dots"></i><p>Feedback</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="excuses"><i class="nav-icon fas fa-file-signature"></i><p>Excuses</p></a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <h1 class="m-0">Lecturer Dashboard</h1>
                <p class="text-muted mb-0"><?php echo $BRAND_FULL; ?> &middot; <?php echo $BRAND_PROG; ?></p>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">
                <div id="dashboard-section" class="section" style="display: block;">
                    <div class="row">
                        <div class="col-lg-4 col-6">
                            <div class="small-box">
                                <div class="inner">
                                    <h3><?php echo $total_sessions; ?></h3>
                                    <p>Total Sessions</p>
                                </div>
                                <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-6">
                            <div class="small-box">
                                <div class="inner">
                                    <h3><?php echo $active_sessions; ?></h3>
                                    <p>Active Sessions</p>
                                </div>
                                <div class="icon"><i class="fas fa-play-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-12">
                            <div class="small-box">
                                <div class="inner">
                                    <h3><?php echo $total_attendance; ?></h3>
                                    <p>Total Attendance</p>
                                </div>
                                <div class="icon"><i class="fas fa-users"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Attendance Analytics</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container"><canvas id="attendanceChart"></canvas></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Attendance Summary</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-hover">
                                <thead><tr><th>Course</th><th>Attendance Count</th></tr></thead>
                                <tbody id="attendance-summary-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="create-session-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Create New Session</h3>
                        </div>
                        <div class="card-body">
                            <form id="create-session-form">
                                <div class="form-group">
                                    <label>Course</label>
                                    <select class="form-control" name="course_id" required>
                                        <option value="">Select Course</option>
                                        <?php foreach ($course_list as $course): ?>
                                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']) . ' (' . htmlspecialchars($course['code']) . ')'; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Start Time (local time, <?php echo date_default_timezone_get(); ?>)</label>
                                    <input type="datetime-local" class="form-control" name="start_time" required>
                                </div>
                                <div class="form-group">
                                    <label>End Time (local time, <?php echo date_default_timezone_get(); ?>)</label>
                                    <input type="datetime-local" class="form-control" name="end_time" required>
                                </div>
                                <div class="form-group">
                                    <label>Radius (meters)</label>
                                    <input type="number" class="form-control" name="radius" value="50" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-plus"></i> Create Session</button>
                            </form>
                            <div id="qr-display" class="mt-4 text-center" style="display: none;">
                                <h3>Session QR Code</h3>
                                <div id="qr-code"></div>
                                <p class="mt-3">Time Remaining: <span id="session-timer" class="countdown">00:00:00</span></p>
                                <div class="mt-3">
                                    <button class="btn btn-primary" id="save-qr"><i class="fas fa-download"></i> Save as Image</button>
                                    <button class="btn btn-primary" id="fullscreen-qr"><i class="fas fa-expand-arrows-alt"></i> Project QR</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="sessions-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">My Sessions</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-hover">
                                <thead><tr><th>Course</th><th>Start</th><th>End</th><th>Status</th><th>Countdown</th><th>Actions</th></tr></thead>
                                <tbody id="sessions-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="feedback-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Session Feedback</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <select class="form-control" id="feedback-session-select">
                                    <option value="">Select Session</option>
                                    <?php foreach ($session_list as $session): ?>
                                        <option value="<?php echo $session['id']; ?>"><?php echo htmlspecialchars($session['course_name']) . ' - ' . $session['start_time']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <table class="table table-bordered table-hover">
                                <thead><tr><th>Student</th><th>Feedback</th><th>Submitted At</th></tr></thead>
                                <tbody id="feedback-table"></tbody>
                            </table>
                            <button class="btn btn-success mt-3" id="export-feedback"><i class="fas fa-file-export"></i> Export Feedback</button>
                        </div>
                    </div>
                </div>

                <div id="excuses-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Student Excuses</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <select class="form-control" id="excuse-session-select">
                                    <option value="">Select Session</option>
                                    <?php foreach ($session_list as $session): ?>
                                        <option value="<?php echo $session['id']; ?>"><?php echo htmlspecialchars($session['course_name']) . ' - ' . $session['start_time']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <table class="table table-bordered table-hover">
                                <thead><tr><th>Student</th><th>Reason</th><th>Document</th><th>Status</th><th>Timestamp</th><th>Action</th></tr></thead>
                                <tbody id="excuses-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attendanceModal" tabindex="-1" aria-labelledby="attendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attendanceModalLabel">Session Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <thead><tr><th>Name</th><th>Email</th><th>Timestamp</th></tr></thead>
                        <tbody id="attendance-table"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editSessionModal" tabindex="-1" aria-labelledby="editSessionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSessionModalLabel">Edit Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-session-form">
                        <input type="hidden" name="session_id" id="edit-session-id">
                        <div class="form-group">
                            <label>Course</label>
                            <select class="form-control" name="course_id" id="edit-course-id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($course_list as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']) . ' (' . htmlspecialchars($course['code']) . ')'; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="datetime-local" class="form-control" name="start_time" id="edit-start-time" required>
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="datetime-local" class="form-control" name="end_time" id="edit-end-time" required>
                        </div>
                        <div class="form-group">
                            <label>Radius (meters)</label>
                            <input type="number" class="form-control" name="radius" id="edit-radius" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Update Session</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="excuseReviewModal" tabindex="-1" aria-labelledby="excuseReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="excuseReviewModalLabel">Review Excuse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="excuse-review-form">
                        <input type="hidden" name="excuse_id" id="excuse-id">
                        <div class="form-group">
                            <label>Student</label>
                            <input type="text" class="form-control" id="excuse-student" readonly>
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <textarea class="form-control" id="excuse-reason" readonly></textarea>
                        </div>
                        <div class="form-group">
                            <label>Document</label>
                            <div id="excuse-document"></div>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status" id="excuse-status">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Comment</label>
                            <textarea class="form-control" name="comment" id="excuse-comment" placeholder="Optional comment"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-gavel"></i> Update Excuse</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <strong><?php echo $BRAND_SHORT; ?> &copy; 2025</strong> All rights reserved.
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    console.log('Script loaded');
    const initialData = {
        sessions: <?php echo json_encode($session_list); ?>,
        courses: <?php echo json_encode($course_list); ?>
    };
    let attendanceChart = null;

    // Server timezone (for diagnostics / countdowns). Must match PHP's
    // date_default_timezone_set in config/db.php.
    const serverTimezone = '<?php echo date_default_timezone_get(); ?>';

    $(document).ready(function() {
        console.log('Document ready');
        $('.section').hide();
        $('#dashboard-section').show();

        $('.nav-link').click(function(e) {
            e.preventDefault();
            console.log('Section clicked:', $(this).data('section'));
            if ($(this).attr('href') === 'attendance_register.php') {
                window.location = 'attendance_register.php';
                return;
            }
            $('.nav-link').removeClass('active');
            $(this).addClass('active');
            $('.section').hide();
            $(`#${$(this).data('section')}-section`).show();
            if ($(this).data('section') === 'dashboard') loadDashboard();
            if ($(this).data('section') === 'sessions') loadSessions();
            if ($(this).data('section') === 'feedback') loadFeedback();
            if ($(this).data('section') === 'excuses') loadExcuses();
        });

        // Create Session
        let qrCodeInstance = null;
        $('#create-session-form').submit(function(e) {
            e.preventDefault();
            console.log('Creating session');
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const formData = $(this).serialize() + `&latitude=${position.coords.latitude}&longitude=${position.coords.longitude}`;
                    $.ajax({
                        url: '?action=create_session',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            console.log('Session created:', res);
                            if (res.status === 'success') {
                                if (qrCodeInstance) qrCodeInstance.clear();
                                qrCodeInstance = new QRCode(document.getElementById('qr-code'), {
                                    text: res.session_id.toString(),
                                    width: 200,
                                    height: 200,
                                    colorDark: '#000000',
                                    colorLight: '#ffffff'
                                });
                                $('#create-session-form').hide();
                                $('#qr-display').show();
                                startTimer(res.session_id, res.end_time);
                                loadSessions();
                                Swal.fire('Success', 'Session created', 'success');
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        },
                        error: function(xhr) {
                            console.error('Create session error:', xhr);
                            Swal.fire('Error', 'Failed to create session: ' + xhr.responseText, 'error');
                        }
                    });
                },
                () => Swal.fire('Error', 'Geolocation required', 'error')
            );
        });

        $('#save-qr').click(function() {
            console.log('Saving QR');
            const canvas = $('#qr-code canvas')[0];
            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = 'session_qr.png';
            link.click();
        });

        $('#fullscreen-qr').click(function() {
            console.log('Fullscreen QR');
            const canvas = $('#qr-code canvas')[0];
            const dataUrl = canvas.toDataURL('image/png');
            Swal.fire({
                imageUrl: dataUrl,
                imageWidth: 400,
                imageHeight: 400,
                showConfirmButton: false,
                background: 'rgba(30, 60, 114, 0.9)'
            });
        });

        // Countdown uses the server-returned end_time. The end_time string has
        // no timezone suffix, so we tell the browser it is in Africa/Lagos to
        // match the server. This keeps the countdown in sync with the
        // server-side attendance-window check.
        function startTimer(sessionId, endTime) {
            // Treat the stored wall-clock time as Africa/Lagos. Guard against
            // a missing/empty endTime to avoid `replace` on undefined.
            if (!endTime) {
                console.warn('startTimer: no endTime provided, timer disabled');
                $('#session-timer').text('--:--:--');
                return;
            }
            const endTimestamp = new Date(String(endTime).replace(' ', 'T') + '+01:00').getTime();
            const interval = setInterval(() => {
                const now = new Date().getTime();
                const distance = endTimestamp - now;
                if (distance <= 0) {
                    clearInterval(interval);
                    $('#session-timer').text('Expired');
                    $('#qr-display').hide();
                    $('#create-session-form').show();
                    initialData.sessions = initialData.sessions.map(s =>
                        s.id === sessionId ? { ...s, status: 'expired' } : s
                    );
                    loadSessions();
                    return;
                }
                const hours = Math.floor(distance / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                $('#session-timer').text(`${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`);
            }, 1000);
        }

        function loadDashboard() {
            console.log('Loading dashboard');
            $.post('?action=get_attendance_stats', function(data) {
                const labels = data.map(item => item.course_name);
                const counts = data.map(item => item.attendance_count);
                const ctx = document.getElementById('attendanceChart').getContext('2d');
                if (attendanceChart) attendanceChart.destroy();
                attendanceChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: counts,
                            backgroundColor: ['#1e3c72', '#f7c948', '#2a5298', '#b78628'],
                            borderColor: '#fff',
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'right', labels: { color: '#1e3c72' } } } }
                });

                let html = '';
                data.forEach(item => {
                    html += `<tr><td>${item.course_name}</td><td>${item.attendance_count}</td></tr>`;
                });
                $('#attendance-summary-table').html(html || '<tr><td colspan="2">No attendance data</td></tr>');
            }, 'json').fail(() => Swal.fire('Error', 'Failed to load stats', 'error'));
        }

        function loadSessions() {
            console.log('Loading sessions');
            $('#sessions-table').html('<tr><td colspan="6" class="text-center"><div class="spinner-border" role="status"></div></td></tr>');
            let html = '';
            initialData.sessions.forEach(session => {
                const now = new Date();
                // Treat stored wall-clock time as Africa/Lagos for the client comparison.
                // Guard against missing end_time to avoid Date parsing NaN.
                const endStr = session.end_time ? String(session.end_time).replace(' ', 'T') + '+01:00' : null;
                const endTime = endStr ? new Date(endStr) : new Date(0);
                const status = (session.status === 'active' && endTime < now) ? 'expired' : session.status;
                html += `
                    <tr data-session-id="${session.id}" data-course-id="${session.course_id}" data-start-time="${session.start_time}" data-end-time="${session.end_time}" data-radius="${session.radius}">
                        <td>${session.course_name}</td>
                        <td>${session.start_time}</td>
                        <td>${session.end_time}</td>
                        <td><span class="session-status status-${status}">${status}</span></td>
                        <td class="countdown" data-end="${session.end_time}" data-status="${status}"></td>
                        <td>
                            <button class="btn btn-sm btn-primary toggle-status" data-status="${status === 'active' ? 'inactive' : 'active'}">
                                <i class="fas fa-power-off"></i> ${status === 'active' ? 'Deactivate' : 'Activate'}
                            </button>
                            <button class="btn btn-sm btn-info view-attendance"><i class="fas fa-eye"></i> View</button>
                            <button class="btn btn-sm btn-warning edit-session"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-sm btn-danger delete-session"><i class="fas fa-trash-alt"></i> Delete</button>
                            <button class="btn btn-sm btn-success export-attendance"><i class="fas fa-file-export"></i> Export</button>
                        </td>
                    </tr>`;
            });
            $('#sessions-table').html(html || '<tr><td colspan="6">No sessions found</td></tr>');
            updateSessionCountdowns();
        }

        function updateSessionCountdowns() {
            $('.countdown').each(function() {
                const raw = $(this).data('end');
                // Skip elements without a data-end attribute (e.g. the
                // #session-timer span, which is updated separately by
                // startTimer). Without this guard, `.replace` throws
                // "Cannot read properties of undefined".
                if (raw === undefined || raw === null || raw === '') {
                    return; // `return` inside $.fn.each acts like `continue`
                }
                const status = $(this).data('status');
                const end = new Date(String(raw).replace(' ', 'T') + '+01:00').getTime();
                const now = new Date().getTime();
                const diff = end - now;
                if (status === 'inactive') {
                    $(this).text('Inactive');
                } else if (diff > 0 && status === 'active') {
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    $(this).text(`${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`);
                } else {
                    $(this).text('Expired');
                    $(this).closest('tr').find('.session-status').text('expired').removeClass('status-active').addClass('status-expired');
                }
            });
        }
        setInterval(updateSessionCountdowns, 1000);

        $(document).on('click', '.toggle-status', function() {
            console.log('Toggling status');
            const sessionId = $(this).closest('tr').data('session-id');
            const newStatus = $(this).data('status');
            $.post('?action=toggle_session_status', { session_id: sessionId, status: newStatus }, function(res) {
                if (res.status === 'success') {
                    initialData.sessions = initialData.sessions.map(s => s.id === sessionId ? { ...s, status: newStatus } : s);
                    loadSessions();
                    Swal.fire('Success', 'Session status updated', 'success');
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json').fail(xhr => Swal.fire('Error', 'Failed to toggle status: ' + xhr.responseText, 'error'));
        });

        $(document).on('click', '.view-attendance', function() {
            console.log('Viewing attendance');
            const sessionId = $(this).closest('tr').data('session-id');
            $.post('?action=get_attendance', { session_id: sessionId }, function(data) {
                let html = '';
                if (data.status === 'empty') {
                    html = '<tr><td colspan="3">No attendance recorded</td></tr>';
                } else {
                    data.forEach(student => {
                        html += `<tr><td>${student.name}</td><td>${student.email}</td><td>${student.timestamp}</td></tr>`;
                    });
                }
                $('#attendance-table').html(html);
                $('#attendanceModal').modal('show');
            }, 'json').fail(xhr => Swal.fire('Error', 'Failed to load attendance: ' + xhr.responseText, 'error'));
        });

        $(document).on('click', '.edit-session', function() {
            console.log('Editing session');
            const row = $(this).closest('tr');
            $('#edit-session-id').val(row.data('session-id'));
            $('#edit-course-id').val(row.data('course-id'));
            $('#edit-start-time').val(row.data('start-time').replace(' ', 'T').slice(0, 16));
            $('#edit-end-time').val(row.data('end-time').replace(' ', 'T').slice(0, 16));
            $('#edit-radius').val(row.data('radius'));
            $('#editSessionModal').modal('show');
        });

        $('#edit-session-form').submit(function(e) {
            e.preventDefault();
            console.log('Updating session');
            $.ajax({
                url: '?action=update_session',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        initialData.sessions = initialData.sessions.map(s =>
                            s.id == $('#edit-session-id').val() ? { ...s,
                                course_id: $('#edit-course-id').val(),
                                start_time: $('#edit-start-time').val(),
                                end_time: $('#edit-end-time').val(),
                                radius: $('#edit-radius').val()
                            } : s
                        );
                        loadSessions();
                        $('#editSessionModal').modal('hide');
                        Swal.fire('Success', 'Session updated', 'success');
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function(xhr) {
                    console.error('Update session error:', xhr);
                    Swal.fire('Error', 'Failed to update session: ' + xhr.responseText, 'error');
                }
            });
        });

        $(document).on('click', '.delete-session', function() {
            console.log('Deleting session');
            const sessionId = $(this).closest('tr').data('session-id');
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete the session!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#1e3c72',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('?action=delete_session', { session_id: sessionId }, function(res) {
                        if (res.status === 'success') {
                            initialData.sessions = initialData.sessions.filter(s => s.id !== sessionId);
                            loadSessions();
                            Swal.fire('Deleted!', 'Session has been deleted.', 'success');
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }, 'json').fail(xhr => Swal.fire('Error', 'Failed to delete: ' + xhr.responseText, 'error'));
                }
            });
        });

        $(document).on('click', '.export-attendance', function() {
            console.log('Exporting attendance');
            const sessionId = $(this).closest('tr').data('session-id');
            $.post('?action=get_attendance', { session_id: sessionId }, function(data) {
                if (data.status === 'empty') return;
                const csv = 'Name,Email,Timestamp\n' + data.map(row => `${row.name},${row.email},${row.timestamp}`).join('\n');
                const blob = new Blob([csv], { type: 'text/csv' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `attendance_session_${sessionId}.csv`;
                link.click();
            }, 'json').fail(() => Swal.fire('Error', 'Failed to export attendance', 'error'));
        });

        function loadFeedback(sessionId = '') {
            console.log('Loading feedback for session:', sessionId);
            if (!sessionId) sessionId = $('#feedback-session-select').val();
            if (!sessionId) return;
            $.post('?action=get_feedback', { session_id: sessionId }, function(data) {
                let html = '';
                if (data.status === 'empty') {
                    html = '<tr><td colspan="3">No feedback recorded</td></tr>';
                } else {
                    data.forEach(feedback => {
                        html += `<tr><td>${feedback.name}</td><td>${feedback.feedback}</td><td>${feedback.submitted_at}</td></tr>`;
                    });
                }
                $('#feedback-table').html(html);
            }, 'json').fail(xhr => Swal.fire('Error', 'Failed to load feedback: ' + xhr.responseText, 'error'));
        }

        $('#feedback-session-select').change(function() {
            loadFeedback($(this).val());
        });

        $('#export-feedback').click(function() {
            console.log('Exporting feedback');
            const sessionId = $('#feedback-session-select').val();
            if (!sessionId) return Swal.fire('Error', 'Please select a session', 'error');
            $.post('?action=get_feedback', { session_id: sessionId }, function(data) {
                if (data.status === 'empty') return;
                const csv = 'Student,Feedback,Submitted At\n' + data.map(row => `${row.name},${row.feedback},${row.submitted_at}`).join('\n');
                const blob = new Blob([csv], { type: 'text/csv' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `feedback_session_${sessionId}.csv`;
                link.click();
            }, 'json').fail(() => Swal.fire('Error', 'Failed to export feedback', 'error'));
        });

        function loadExcuses(sessionId = '') {
            console.log('Loading excuses for session:', sessionId);
            if (!sessionId) sessionId = $('#excuse-session-select').val();
            if (!sessionId) return;
            $.post('?action=get_excuses', { session_id: sessionId }, function(data) {
                let html = '';
                if (data.status === 'empty') {
                    html = '<tr><td colspan="6">No excuses recorded</td></tr>';
                } else {
                    data.forEach(excuse => {
                        const docLink = excuse.document_path ? `<a href="${excuse.document_path}" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a>` : 'None';
                        html += `
                            <tr data-excuse-id="${excuse.id}">
                                <td>${excuse.name}</td>
                                <td>${excuse.reason}</td>
                                <td>${docLink}</td>
                                <td>${excuse.status}</td>
                                <td>${excuse.timestamp}</td>
                                <td><button class="btn btn-sm btn-primary review-excuse"><i class="fas fa-gavel"></i> Review</button></td>
                            </tr>`;
                    });
                }
                $('#excuses-table').html(html);
            }, 'json').fail(xhr => Swal.fire('Error', 'Failed to load excuses: ' + xhr.responseText, 'error'));
        }

        $('#excuse-session-select').change(function() {
            loadExcuses($(this).val());
        });

        $(document).on('click', '.review-excuse', function() {
            console.log('Reviewing excuse');
            const row = $(this).closest('tr');
            const excuseId = row.data('excuse-id');
            $.post('?action=get_excuses', { session_id: $('#excuse-session-select').val() }, function(data) {
                const excuse = data.find(e => e.id == excuseId);
                if (excuse) {
                    $('#excuse-id').val(excuse.id);
                    $('#excuse-student').val(excuse.name);
                    $('#excuse-reason').val(excuse.reason);
                    $('#excuse-status').val(excuse.status);
                    $('#excuse-comment').val('');
                    $('#excuse-document').html(excuse.document_path ? `<a href="${excuse.document_path}" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View Document</a>` : 'No document');
                    $('#excuseReviewModal').modal('show');
                }
            }, 'json');
        });

        $('#excuse-review-form').submit(function(e) {
            e.preventDefault();
            console.log('Updating excuse');
            $.ajax({
                url: '?action=update_excuse',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        loadExcuses();
                        $('#excuseReviewModal').modal('hide');
                        Swal.fire('Success', 'Excuse updated', 'success');
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function(xhr) {
                    console.error('Update excuse error:', xhr);
                    Swal.fire('Error', 'Failed to update excuse: ' + xhr.responseText, 'error');
                }
            });
        });

        $('#logout').click(function() {
            console.log('Logging out');
            $.post('?action=logout', function(res) {
                if (res.status === 'success') window.location = 'login.php';
            }, 'json').fail(xhr => Swal.fire('Error', 'Logout failed: ' + xhr.responseText, 'error'));
        });

        console.log('Initial load');
        loadDashboard();
        loadSessions();
    });

    window.onerror = function(msg, url, lineNo, columnNo, error) {
        console.error('Global error:', msg, url, lineNo, columnNo, error);
        Swal.fire('Error', 'Something broke! Check the console (F12) for details.', 'error');
        return false;
    };
</script>
</body>
</html>
<?php ob_end_flush(); ?>
