<?php
// student.php
// JIGPOLY Polytechnic (OND, ND, HND)
// Student Dashboard.
//
// Fixes applied:
//   * Uses config/db.php so the PHP/MySQL timezone is pinned to Africa/Lagos.
//     This is the central fix for the "session is not active" / "Attendance
//     window closed" false-negative: previously `new DateTime()` used the
//     server default (UTC) while session start/end times were stored as local
//     wall-clock strings, so the time-window comparison was off by one hour.
//   * Improved the time-window diagnostic so the error message includes the
//     server timezone and the exact now/start/end values, making future
//     timezone issues easy to spot.
//   * Rebranded from "FCIT BUK" to "CST JIGPOLY Polytechnic".

ob_start();
session_start();

// Central connection — sets Africa/Lagos timezone for PHP + MySQL.
require 'config/db.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

// Distance calculation function (Haversine, metres)
function getDistance($lat1, $lon1, $lat2, $lon2) {
    $R  = 6371000;
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a  = sin($dp / 2) * sin($dp / 2) + cos($p1) * cos($p2) * sin($dl / 2) * sin($dl / 2);
    $c  = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

// Fetch initial data
$attendance_history = $pdo->prepare(
    "SELECT cs.id, c.name AS course_name, a.timestamp, a.latitude, a.longitude, a.status
     FROM attendance a
     JOIN class_sessions cs ON a.session_id = cs.id
     JOIN courses c ON cs.course_id = c.id
     WHERE a.student_id = ?
     ORDER BY a.timestamp DESC"
);
$attendance_history->execute([$student_id]);
$attendance_list = $attendance_history->fetchAll();

$profile = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'student'");
$profile->execute([$student_id]);
$student_profile = $profile->fetch();

$total_attendance = count($attendance_list);
$present_count    = count(array_filter($attendance_list, fn($r) => $r['status'] === 'valid'));
$attendance_rate  = $total_attendance > 0 ? round(($present_count / $total_attendance) * 100) : 0;

$streak = 0;
if (!empty($attendance_list)) {
    $dates = array_unique(array_map(fn($r) => date('Y-m-d', strtotime($r['timestamp'])), $attendance_list));
    sort($dates);
    $current_streak = 1;
    for ($i = 1; $i < count($dates); $i++) {
        $prev = new DateTime($dates[$i - 1]);
        $curr = new DateTime($dates[$i]);
        $diff = $prev->diff($curr)->days;
        if ($diff == 1) $current_streak++;
        else if ($diff > 1) $current_streak = 1;
    }
    $streak = $current_streak;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $raw    = file_get_contents('php://input');
    $input  = json_decode($raw, true);
    if (!is_array($input)) {
        // Allow form-encoded POST as a fallback.
        $input = $_POST;
    }
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'mark_attendance':
            $session_id  = isset($input['session_id']) ? (int)$input['session_id'] : 0;
            $latitude    = isset($input['latitude']) ? (float)$input['latitude'] : 0.0;
            $longitude   = isset($input['longitude']) ? (float)$input['longitude'] : 0.0;
            $device_info = $input['device_info'] ?? ($input['device_id'] ?? '');

            if ($session_id <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid session id']);
                break;
            }

            // Look up the session regardless of status, so we can give a more
            // specific error message than a generic "invalid session".
            $stmt = $pdo->prepare(
                "SELECT id, latitude, longitude, radius, start_time, end_time, status
                 FROM class_sessions
                 WHERE id = ?"
            );
            $stmt->execute([$session_id]);
            $session = $stmt->fetch();

            if (!$session) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid session']);
                break;
            }

            if ($session['status'] !== 'active') {
                echo json_encode(['status' => 'error', 'message' => 'Session is not active']);
                break;
            }

            // TIME-WINDOW CHECK
            // Both `now` and the stored DATETIME values are interpreted in
            // Africa/Lagos (set in config/db.php), so this comparison is now
            // correct. Previously PHP defaulted to UTC while the stored times
            // were local wall-clock, producing a 1-hour mismatch that made
            // every active session look "closed".
            $now   = new DateTime();                       // Africa/Lagos
            $start = new DateTime($session['start_time']); // parsed as Africa/Lagos
            $end   = new DateTime($session['end_time']);   // parsed as Africa/Lagos

            $debug = [
                'session_id'      => $session_id,
                'now'             => $now->format('Y-m-d H:i:s'),
                'start'           => $start->format('Y-m-d H:i:s'),
                'end'             => $end->format('Y-m-d H:i:s'),
                'server_timezone' => date_default_timezone_get(),
            ];
            error_log("Attendance Debug: " . json_encode($debug));

            if ($now < $start || $now > $end) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Attendance window closed',
                    'debug'   => $debug,
                ]);
                break;
            }

            $distance = getDistance($latitude, $longitude, $session['latitude'], $session['longitude']);
            if ($distance > (float)$session['radius']) {
                echo json_encode([
                    'status'   => 'error',
                    'message'  => 'You are not in the class location',
                    'distance' => round($distance, 2),
                    'radius'   => (float)$session['radius'],
                ]);
                break;
            }

            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND session_id = ?");
            $stmt->execute([$student_id, $session_id]);
            if ($stmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Attendance already marked']);
                break;
            }

            if ($device_info !== '') {
                $stmt = $pdo->prepare("SELECT id FROM attendance WHERE device_info = ? AND session_id = ?");
                $stmt->execute([$device_info, $session_id]);
                if ($stmt->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Device already used for this session']);
                    break;
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO attendance (student_id, session_id, latitude, longitude, device_info, status)
                 VALUES (?, ?, ?, ?, ?, 'valid')"
            );
            $stmt->execute([$student_id, $session_id, $latitude, $longitude, $device_info]);

            $attendance_history->execute([$student_id]);
            $attendance_list = $attendance_history->fetchAll();
            $total_attendance = count($attendance_list);
            $present_count    = count(array_filter($attendance_list, fn($r) => $r['status'] === 'valid'));
            $attendance_rate  = $total_attendance > 0 ? round(($present_count / $total_attendance) * 100) : 0;

            $dates = array_unique(array_map(fn($r) => date('Y-m-d', strtotime($r['timestamp'])), $attendance_list));
            sort($dates);
            $current_streak = 1;
            for ($i = 1; $i < count($dates); $i++) {
                $prev = new DateTime($dates[$i - 1]);
                $curr = new DateTime($dates[$i]);
                $diff = $prev->diff($curr)->days;
                if ($diff == 1) $current_streak++;
                else if ($diff > 1) $current_streak = 1;
            }

            echo json_encode([
                'status'           => 'success',
                'message'          => 'Attendance marked successfully',
                'attendance'       => $attendance_list,
                'totalAttendance'  => $total_attendance,
                'presentCount'     => $present_count,
                'attendanceRate'   => $attendance_rate,
                'streak'           => $current_streak,
            ]);
            break;

        case 'submit_excuse':
            $session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
            $reason     = $input['reason'] ?? '';
            $document   = $input['document'] ?? null;

            if (!$session_id || !$reason) {
                echo json_encode(['status' => 'error', 'message' => 'Missing session ID or reason']);
                break;
            }

            $upload_path = null;
            if ($document && isset($document['name'], $document['data'])) {
                $ext = strtolower(pathinfo($document['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                if (!in_array($ext, $allowed)) {
                    echo json_encode(['status' => 'error', 'message' => 'Unsupported document type']);
                    break;
                }
                $upload_path = 'uploads/excuses/' . $student_id . '_' . time() . '.' . $ext;
                file_put_contents($upload_path, base64_decode($document['data']));
            }

            $stmt = $pdo->prepare(
                "INSERT INTO attendance_excuses (student_id, session_id, reason, document_path, status)
                 VALUES (?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([$student_id, $session_id, $reason, $upload_path]);

            echo json_encode(['status' => 'success', 'message' => 'Excuse submitted for review']);
            break;

        case 'submit_feedback':
            $session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
            $feedback   = trim($input['feedback'] ?? '');
            if (!$session_id || $feedback === '') {
                echo json_encode(['status' => 'error', 'message' => 'Missing session ID or feedback']);
                break;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO session_feedback (student_id, session_id, feedback)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$student_id, $session_id, $feedback]);
            echo json_encode(['status' => 'success', 'message' => 'Feedback submitted']);
            break;

        case 'update_profile':
            $name  = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid name or email']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'student'");
            $stmt->execute([$name, $email, $student_id]);
            $profile->execute([$student_id]);
            $updated_profile = $profile->fetch();
            echo json_encode(['status' => 'success', 'message' => 'Profile updated', 'profile' => $updated_profile]);
            break;

        case 'get_schedule':
            $stmt = $pdo->prepare(
                "SELECT cs.id, c.name AS course_name, cs.start_time, cs.end_time, cs.latitude, cs.longitude, cs.radius
                 FROM class_sessions cs
                 JOIN courses c ON cs.course_id = c.id
                 JOIN student_courses sc ON c.id = sc.course_id
                 WHERE sc.student_id = ? AND cs.start_time > (CURRENT_TIMESTAMP AT TIME ZONE 'Africa/Lagos') AND cs.status = 'active'
                 ORDER BY cs.start_time ASC LIMIT 5"
            );
            $stmt->execute([$student_id]);
            $schedule = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'schedule' => $schedule]);
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
    <title>Student Dashboard - <?php echo $BRAND_SHORT; ?></title>
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
        .btn-danger { background: #dc3545; border: none; border-radius: 8px; }
        .table { color: var(--text-dark); }
        .table th, .table td { border-color: rgba(30, 60, 114, 0.2); }
        .table-hover tbody tr:hover { background: rgba(247, 201, 72, 0.1); }
        .countdown { font-weight: 600; color: #f7c948; }
        .status-valid { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .form-control, .form-select { border-radius: 8px; border-color: var(--blue-start); color: var(--text-dark); }
        .form-control:focus, .form-select:focus { border-color: #f7c948; box-shadow: 0 0 8px rgba(247, 201, 72, 0.5); }
        .chart-container { max-width: 500px; margin: 20px auto; }
        #qr-reader { width: 100%; max-width: 500px; margin: 20px auto; border: 2px solid var(--blue-start); border-radius: 10px; }
        #qr-status { margin-top: 15px; font-weight: 600; color: var(--text-dark); }
        .quote { font-style: italic; color: #f7c948; margin-top: 10px; font-weight: 500; }
        .progress { height: 20px; margin-top: 10px; }
        .progress-bar { transition: width 0.5s ease-in-out; }
        #streak-calendar span { width: 30px; height: 30px; display: inline-block; margin: 5px; }
        @media (max-width: 768px) {
            .content-wrapper { margin: 10px; }
            .small-box { margin-bottom: 15px; }
            .sidebar { width: 70px; }
            .nav-link p { display: none; }
            .nav-link { justify-content: center; }
            .nav-icon { margin-right: 0; }
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
            <span class="brand-text"><?php echo $BRAND_SHORT; ?> Student</span>
        </a>
        <div class="sidebar">
            <nav class="mt-3">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#" class="nav-link active" data-section="dashboard"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="scan-qr"><i class="nav-icon fas fa-qrcode"></i><p>Scan QR</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="attendance"><i class="nav-icon fas fa-check"></i><p>Attendance</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="schedule"><i class="nav-icon fas fa-calendar"></i><p>Schedule</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="excuses"> <i class="nav-icon fas fa-file-signature"></i><p>Excuses</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="feedback"><i class="nav-icon fas fa-comment"></i><p>Feedback</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="profile"><i class="nav-icon fas fa-user"></i><p>Profile</p></a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <h1 class="m-0">Student Dashboard</h1>
                <p class="text-muted mb-0"><?php echo $BRAND_FULL; ?> &middot; <?php echo $BRAND_PROG; ?></p>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">
                <!-- Dashboard -->
                <div id="dashboard-section" class="section" style="display: block;">
                    <div class="row">
                        <div class="col-lg-4 col-6">
                            <div class="small-box">
                                <div class="inner">
                                    <h3 id="total-attendance"><?php echo $total_attendance; ?></h3>
                                    <p>Total Attendance</p>
                                </div>
                                <div class="icon"><i class="fas fa-users"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-6">
                            <div class="small-box">
                                <div class="inner">
                                    <h3 id="attendance-rate"><?php echo $attendance_rate; ?>%</h3>
                                    <p>Attendance Rate</p>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $attendance_rate; ?>%;" aria-valuenow="<?php echo $attendance_rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                <div class="icon"><i class="fas fa-chart-pie"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-12">
                            <div class="small-box">
                                <div class="inner">
                                    <h3 id="streak"><?php echo $streak; ?></h3>
                                    <p>Streak (days)</p>
                                </div>
                                <div class="icon"><i class="fas fa-fire"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Welcome, <?php echo htmlspecialchars($student_profile['name']); ?>!</h3>
                        </div>
                        <div class="card-body">
                            <p>Scan a QR code or check your schedule to mark attendance.</p>
                            <div class="chart-container"><canvas id="attendanceChart"></canvas></div>
                            <div id="streak-calendar" class="mt-3"></div>
                            <p class="quote" id="motivational-quote"></p>
                        </div>
                    </div>
                </div>

                <!-- Scan QR -->
                <div id="scan-qr-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Scan QR Code</h3>
                        </div>
                        <div class="card-body text-center">
                            <div id="qr-reader"></div>
                            <button class="btn btn-primary mt-3" id="start-scan"><i class="fas fa-camera"></i> Start Camera Scan</button>
                            <label class="btn btn-primary mt-3">
                                <i class="fas fa-upload"></i> Upload QR Code
                                <input type="file" id="qr-upload" accept="image/*" hidden>
                            </label>
                            <p id="qr-status">Use your camera or upload a QR code to mark attendance.</p>
                        </div>
                    </div>
                </div>

                <!-- Attendance -->
                <div id="attendance-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Attendance History</h3>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" id="course-filter" placeholder="Filter by course">
                                </div>
                                <div class="col-md-4">
                                    <input type="date" class="form-control" id="date-from" placeholder="From">
                                </div>
                                <div class="col-md-4">
                                    <input type="date" class="form-control" id="date-to" placeholder="To">
                                </div>
                            </div>
                            <table class="table table-bordered table-hover">
                                <thead><tr><th>Course</th><th>Timestamp</th><th>Location</th><th>Status</th></tr></thead>
                                <tbody id="attendance-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Schedule -->
                <div id="schedule-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Sessions</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-hover">
                                <thead><tr><th>Course</th><th>Start Time</th><th>End Time</th><th>Countdown</th><th>Action</th></tr></thead>
                                <tbody id="schedule-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Excuses -->
                <div id="excuses-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Submit Excuse</h3>
                        </div>
                        <div class="card-body">
                            <form id="excuse-form">
                                <div class="form-group mb-3">
                                    <label>Session</label>
                                    <select class="form-select" name="session_id" required>
                                        <option value="">Select Missed Session</option>
                                        <?php foreach ($attendance_list as $record): ?>
                                            <?php if ($record['status'] !== 'valid'): ?>
                                                <option value="<?php echo $record['id']; ?>"><?php echo htmlspecialchars($record['course_name']) . ' - ' . $record['timestamp']; ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Reason</label>
                                    <textarea class="form-control" name="reason" placeholder="Reason for absence" required></textarea>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Document (optional)</label>
                                    <input type="file" class="form-control" name="document" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Excuse</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Feedback -->
                <div id="feedback-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Session Feedback</h3>
                        </div>
                        <div class="card-body">
                            <form id="feedback-form">
                                <div class="form-group mb-3">
                                    <label>Session</label>
                                    <select class="form-select" name="session_id" required>
                                        <option value="">Select Session</option>
                                        <?php foreach ($attendance_list as $record): ?>
                                            <option value="<?php echo $record['id']; ?>"><?php echo htmlspecialchars($record['course_name']) . ' - ' . $record['timestamp']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Feedback</label>
                                    <textarea class="form-control" name="feedback" placeholder="Your feedback" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-comment"></i> Submit Feedback</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Profile -->
                <div id="profile-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Profile</h3>
                        </div>
                        <div class="card-body">
                            <form id="profile-form">
                                <div class="form-group mb-3">
                                    <label>Name</label>
                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($student_profile['name']); ?>" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($student_profile['email']); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <strong><?php echo $BRAND_SHORT; ?> &copy; 2025</strong> All rights reserved.
    </footer>
</div>

<audio id="success-sound" src="https://www.soundjay.com/buttons/beep-01a.mp3"></audio>
<audio id="error-sound" src="https://www.soundjay.com/buttons/beep-02.mp3"></audio>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    console.log('Script loaded');
    const initialData = {
        attendance: <?php echo json_encode($attendance_list); ?>,
        schedule: [],
        profile: <?php echo json_encode($student_profile); ?>,
        totalAttendance: <?php echo $total_attendance; ?>,
        presentCount: <?php echo $present_count; ?>,
        attendanceRate: <?php echo $attendance_rate; ?>,
        streak: <?php echo $streak; ?>
    };
    let attendanceChart = null;

    // Server timezone, used so the browser parses stored DATETIME strings
    // (which have no tz suffix) the same way the server does.
    const serverTimezone = '<?php echo date_default_timezone_get(); ?>';

    $(document).ready(function() {
        console.log('Document ready');
        $('.section').hide();
        $('#dashboard-section').show();

        $('.nav-link').click(function(e) {
            e.preventDefault();
            console.log('Section clicked:', $(this).data('section'));
            $('.nav-link').removeClass('active');
            $(this).addClass('active');
            $('.section').hide();
            const section = $(this).data('section');
            $(`#${section}-section`).show();
            if (section === 'dashboard') loadDashboard();
            if (section === 'attendance') loadAttendance();
            if (section === 'schedule') loadSchedule();
        });

        const html5QrCode = new Html5Qrcode("qr-reader");
        let isScanning = false;

        $('#start-scan').click(function() {
            console.log('Start scan clicked');
            if (!isScanning) {
                html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    (decodedText) => {
                        console.log('QR scanned:', decodedText);
                        $('#qr-status').text('QR Code Scanned Successfully!');
                        handleQrScan(decodedText);
                        html5QrCode.stop().then(() => isScanning = false);
                    },
                    (error) => console.warn('QR scan error:', error)
                ).then(() => {
                    isScanning = true;
                    $(this).html('<i class="fas fa-stop"></i> Stop Scan');
                    $('#qr-status').text('Scanning with camera...');
                }).catch(err => {
                    console.error('Camera error:', err);
                    Swal.fire('Error', 'Camera access denied: ' + err, 'error');
                    document.getElementById('error-sound').play();
                });
            } else {
                html5QrCode.stop().then(() => {
                    isScanning = false;
                    $(this).html('<i class="fas fa-camera"></i> Start Camera Scan');
                    $('#qr-status').text('Camera scan stopped.');
                });
            }
        });

        $('#qr-upload').change(debounce(function(e) {
            console.log('QR upload triggered');
            const file = e.target.files[0];
            if (!file) return;
            $('#qr-status').html('<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>');
            html5QrCode.scanFile(file, true)
                .then(decodedText => {
                    console.log('QR uploaded:', decodedText);
                    $('#qr-status').text('QR Code Scanned Successfully!');
                    handleQrScan(decodedText);
                })
                .catch(err => {
                    console.error('QR upload error:', err);
                    $('#qr-status').text('Failed to scan QR code.');
                    Swal.fire('Error', 'Invalid QR code: ' + err, 'error');
                    document.getElementById('error-sound').play();
                });
        }, 300));

        function handleQrScan(decodedText) {
            console.log('Handling QR scan:', decodedText);
            const sessionId = decodedText;
            $('#qr-status').text('Fetching session data...');
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const data = {
                        action: 'mark_attendance',
                        session_id: sessionId,
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        device_info: navigator.userAgent
                    };
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(data),
                        beforeSend: () => $('#qr-status').text('Validating location and session...'),
                        success: function(res) {
                            console.log('Attendance response:', res);
                            $('#qr-status').text(res.message);
                            if (res.status === 'success') {
                                document.getElementById('success-sound').play();
                                Swal.fire({ icon: 'success', title: 'Success', text: res.message }).then(() => {
                                    initialData.attendance = res.attendance;
                                    initialData.totalAttendance = res.totalAttendance;
                                    initialData.presentCount = res.presentCount;
                                    initialData.attendanceRate = res.attendanceRate;
                                    initialData.streak = res.streak;
                                    loadDashboard();
                                    loadAttendance();
                                    loadSchedule();
                                });
                            } else {
                                document.getElementById('error-sound').play();
                                // Surface the debug payload if the server sent one.
                                const extra = res.debug ? `\n(now=${res.debug.now}, start=${res.debug.start}, end=${res.debug.end}, tz=${res.debug.server_timezone})` : '';
                                Swal.fire({ icon: 'error', title: 'Error', text: res.message + extra });
                            }
                        },
                        error: function(xhr) {
                            console.error('Attendance AJAX error:', xhr);
                            $('#qr-status').text('Failed to process attendance.');
                            document.getElementById('error-sound').play();
                            Swal.fire('Error', 'Failed to mark attendance: ' + xhr.responseText, 'error');
                        }
                    });
                },
                () => {
                    console.error('GPS error');
                    $('#qr-status').text('GPS access denied.');
                    document.getElementById('error-sound').play();
                    Swal.fire('Error', 'Please enable GPS', 'error');
                }
            );
        }

        function loadDashboard() {
            console.log('Loading dashboard');
            $('#total-attendance').text(initialData.totalAttendance);
            $('#attendance-rate').text(initialData.attendanceRate + '%');
            $('.progress-bar').css('width', initialData.attendanceRate + '%').attr('aria-valuenow', initialData.attendanceRate);
            $('#streak').text(initialData.streak);
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            if (attendanceChart) attendanceChart.destroy();
            attendanceChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Present', 'Absent'],
                    datasets: [{
                        data: [initialData.presentCount, initialData.totalAttendance - initialData.presentCount],
                        backgroundColor: ['#28a745', '#dc3545'],
                        borderColor: '#fff',
                        borderWidth: 1
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'right', labels: { color: '#1e3c72' } } } }
            });

            const streakDates = initialData.attendance.map(r => {
                // Guard against missing timestamp (e.g. freshly inserted rows
                // before reload). Avoids `Cannot read properties of undefined`.
                if (!r || !r.timestamp) return '';
                return new Date(String(r.timestamp).replace(' ', 'T') + '+01:00').toDateString();
            }).filter(Boolean);
            let calendarHtml = '<h5>Streak Calendar (Last 7 Days)</h5><div class="d-flex flex-wrap">';
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const isPresent = streakDates.includes(date.toDateString());
                calendarHtml += `<span class="m-1 p-2 rounded ${isPresent ? 'bg-success' : 'bg-secondary'}" title="${date.toLocaleDateString()}"></span>`;
            }
            calendarHtml += '</div>';
            $('#streak-calendar').html(calendarHtml);

            const quotes = [
                "Success is the sum of small efforts, repeated day in and day out.",
                "The only way to do great work is to love what you do.",
                "Consistency is the key to mastery.",
                "Every step forward counts, no matter how small."
            ];
            $('#motivational-quote').text(quotes[Math.floor(Math.random() * quotes.length)]);
        }

        function loadAttendance() {
            console.log('Loading attendance');
            $('#attendance-table').html('<tr><td colspan="4" class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>');
            let html = '';
            let filtered = initialData.attendance;

            const courseFilter = $('#course-filter').val().toLowerCase();
            const dateFrom = $('#date-from').val() ? new Date($('#date-from').val()) : null;
            const dateTo = $('#date-to').val() ? new Date($('#date-to').val()) : null;

            filtered = filtered.filter(record => {
                if (!record || !record.timestamp) return false;
                const matchesCourse = !courseFilter || record.course_name.toLowerCase().includes(courseFilter);
                const timestamp = new Date(String(record.timestamp).replace(' ', 'T') + '+01:00');
                const matchesDate = (!dateFrom || timestamp >= dateFrom) && (!dateTo || timestamp <= dateTo);
                return matchesCourse && matchesDate;
            }).sort((a, b) => new Date(String(b.timestamp).replace(' ', 'T')) - new Date(String(a.timestamp).replace(' ', 'T')));

            filtered.forEach(record => {
                html += `<tr>
                    <td>${record.course_name}</td>
                    <td>${record.timestamp}</td>
                    <td>${record.latitude}, ${record.longitude}</td>
                    <td><span class="status-${record.status}">${record.status}</span></td>
                </tr>`;
            });
            $('#attendance-table').html(html || '<tr><td colspan="4">No records found</td></tr>');
        }

        function loadSchedule() {
            console.log('Loading schedule');
            $('#schedule-table').html('<tr><td colspan="5" class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>');
            $.ajax({
                url: window.location.href,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ action: 'get_schedule' }),
                success: function(res) {
                    if (res.status === 'success') {
                        initialData.schedule = res.schedule;
                        let html = '';
                        res.schedule.forEach(session => {
                            html += `<tr data-id="${session.id}">
                                <td>${session.course_name}</td>
                                <td>${session.start_time}</td>
                                <td>${session.end_time}</td>
                                <td class="countdown" data-start="${session.start_time}"></td>
                                <td><button class="btn btn-primary btn-sm mark-now" data-id="${session.id}"><i class="fas fa-check"></i> Mark Now</button></td>
                            </tr>`;
                        });
                        $('#schedule-table').html(html || '<tr><td colspan="5">No upcoming sessions</td></tr>');
                        updateCountdowns();
                    }
                },
                error: function(xhr) {
                    console.error('Schedule load error:', xhr);
                    $('#schedule-table').html('<tr><td colspan="5">Failed to load schedule</td></tr>');
                }
            });
        }

        function updateCountdowns() {
            $('.countdown').each(function() {
                const raw = $(this).data('start');
                // Skip elements without data-start (avoids
                // "Cannot read properties of undefined (reading 'replace')").
                if (raw === undefined || raw === null || raw === '') {
                    return; // acts as `continue` inside $.fn.each
                }
                // Stored wall-clock time -> parse as Africa/Lagos to match server.
                const start = new Date(String(raw).replace(' ', 'T') + '+01:00');
                const now = new Date();
                const diff = start - now;
                if (diff > 0) {
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    $(this).text(`${hours}h ${minutes}m ${seconds}s`);
                } else {
                    $(this).text('Started');
                }
            });
        }
        setInterval(updateCountdowns, 1000);

        $('#excuse-form').submit(function(e) {
            console.log('Excuse form submitted');
            e.preventDefault();
            const formData = new FormData(this);
            const data = { action: 'submit_excuse' };
            formData.forEach((value, key) => data[key] = value);
            if (data.document instanceof File) {
                if (data.document.size === 0) {
                    delete data.document;
                    submitExcuse(data);
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    data.document = { name: data.document.name, data: e.target.result.split(',')[1] };
                    submitExcuse(data);
                };
                reader.readAsDataURL(data.document);
            } else {
                submitExcuse(data);
            }
        });

        function submitExcuse(data) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                success: function(res) {
                    console.log('Excuse response:', res);
                    Swal.fire(res.status, res.message, res.status).then(() => {
                        if (res.status === 'success') {
                            $('#excuse-form')[0].reset();
                            loadAttendance();
                        }
                    });
                },
                error: function(xhr) {
                    console.error('Excuse error:', xhr);
                    Swal.fire('Error', 'Failed to submit excuse: ' + xhr.responseText, 'error');
                }
            });
        }

        $('#feedback-form').submit(function(e) {
            console.log('Feedback form submitted');
            e.preventDefault();
            const data = { action: 'submit_feedback' };
            $(this).serializeArray().forEach(item => data[item.name] = item.value);
            $.ajax({
                url: window.location.href,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                success: function(res) {
                    console.log('Feedback response:', res);
                    Swal.fire(res.status, res.message, res.status).then(() => {
                        if (res.status === 'success') $('#feedback-form')[0].reset();
                    });
                },
                error: function(xhr) {
                    console.error('Feedback error:', xhr);
                    Swal.fire('Error', 'Failed to submit feedback: ' + xhr.responseText, 'error');
                }
            });
        });

        $('#profile-form').submit(function(e) {
            console.log('Profile form submitted');
            e.preventDefault();
            const data = { action: 'update_profile' };
            $(this).serializeArray().forEach(item => data[item.name] = item.value);
            $.ajax({
                url: window.location.href,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                success: function(res) {
                    console.log('Profile response:', res);
                    Swal.fire(res.status, res.message, res.status).then(() => {
                        if (res.status === 'success') {
                            initialData.profile = res.profile;
                            $('#profile-form [name="name"]').val(res.profile.name);
                            $('#profile-form [name="email"]').val(res.profile.email);
                        }
                    });
                },
                error: function(xhr) {
                    console.error('Profile error:', xhr);
                    Swal.fire('Error', 'Failed to update profile: ' + xhr.responseText, 'error');
                }
            });
        });

        $(document).on('click', '.mark-now', function() {
            console.log('Mark now clicked:', $(this).data('id'));
            const sessionId = $(this).data('id');
            handleQrScan(sessionId);
        });

        $('#logout').click(function() {
            console.log('Logout clicked');
            $.ajax({
                url: window.location.href,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ action: 'logout' }),
                success: function(res) {
                    console.log('Logout response:', res);
                    if (res.status === 'success') window.location = 'login.php';
                },
                error: function(xhr) {
                    console.error('Logout error:', xhr);
                }
            });
        });

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        $('#course-filter, #date-from, #date-to').on('input', debounce(loadAttendance, 300));

        console.log('Initial load');
        loadDashboard();
        loadAttendance();
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
