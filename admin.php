<?php
ob_start();
session_start();

// Central connection — sets Africa/Lagos timezone for PHP + MySQL.
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];

// Branding constants
$BRAND_SHORT = 'JIGPOLY Polytechnic';
$BRAND_FULL  = 'JIGPOLY Polytechnic';
$BRAND_PROG  = 'OND / ND / HND';

function logAudit($pdo, $admin_id, $action, $details) {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$admin_id, $action, $details]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    if ($action) {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'Invalid action'];

        switch ($action) {
            case 'get_dashboard':
                $response = [
                    'status' => 'success',
                    'total_lecturers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'lecturer'")->fetchColumn(),
                    'total_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
                    'total_courses' => $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
                    'attendance_today' => $pdo->query("SELECT COUNT(*) FROM attendance WHERE timestamp::date = CURRENT_DATE")->fetchColumn()
                ];
                break;

            case 'get_attendance':
                $date = $_GET['date'] ?? '';
                $query = "SELECT a.timestamp, a.latitude, a.longitude, a.device_info, a.status, u.name AS student_name, c.name AS course_name 
                          FROM attendance a JOIN users u ON a.student_id = u.id JOIN class_sessions cs ON a.session_id = cs.id JOIN courses c ON cs.course_id = c.id";
                if ($date) $query .= " WHERE DATE(a.timestamp) = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute($date ? [$date] : []);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'get_sessions':
                $stmt = $pdo->prepare("SELECT cs.id, cs.start_time, cs.end_time, cs.latitude, cs.longitude, cs.status, u.name AS lecturer_name, c.name AS course_name 
                                       FROM class_sessions cs JOIN users u ON cs.lecturer_id = u.id JOIN courses c ON cs.course_id = c.id");
                $stmt->execute();
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'get_lecturers':
                $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, STRING_AGG(c.name, ',') AS courses 
                                       FROM users u LEFT JOIN lecturer_courses lc ON u.id = lc.lecturer_id LEFT JOIN courses c ON lc.course_id = c.id 
                                       WHERE u.role = 'lecturer' GROUP BY u.id, u.name, u.email");
                $stmt->execute();
                $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($lecturers as &$lecturer) {
                    $lecturer['courses'] = $lecturer['courses'] ? explode(',', $lecturer['courses']) : [];
                }
                $response = $lecturers;
                break;

            case 'get_students':
                $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, STRING_AGG(c.name, ',') AS courses 
                                       FROM users u LEFT JOIN student_courses sc ON u.id = sc.student_id LEFT JOIN courses c ON sc.course_id = c.id 
                                       WHERE u.role = 'student' GROUP BY u.id, u.name, u.email");
                $stmt->execute();
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($students as &$student) {
                    $student['courses'] = $student['courses'] ? explode(',', $student['courses']) : [];
                }
                $response = $students;
                break;

            case 'get_courses':
                $stmt = $pdo->prepare("SELECT c.id, c.name, c.code, STRING_AGG(DISTINCT l.name, ',') AS lecturers, STRING_AGG(DISTINCT s.name, ',') AS students 
                                       FROM courses c 
                                       LEFT JOIN lecturer_courses lc ON c.id = lc.course_id LEFT JOIN users l ON lc.lecturer_id = l.id 
                                       LEFT JOIN student_courses sc ON c.id = sc.course_id LEFT JOIN users s ON sc.student_id = s.id 
                                       GROUP BY c.id, c.name, c.code");
                $stmt->execute();
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($courses as &$course) {
                    $course['lecturers'] = $course['lecturers'] ? explode(',', $course['lecturers']) : [];
                    $course['students'] = $course['students'] ? explode(',', $course['students']) : [];
                }
                $response = $courses;
                break;

            case 'get_all_courses':
                $stmt = $pdo->prepare("SELECT id, name, code FROM courses");
                $stmt->execute();
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'get_departments':
                $stmt = $pdo->prepare("SELECT id, name FROM departments");
                $stmt->execute();
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'add_lecturer':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
                if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $response = ['status' => 'error', 'message' => 'Invalid or missing name/email'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'lecturer')");
                    $stmt->execute([$name, $email, $password]);
                    logAudit($pdo, $admin_id, 'Add Lecturer', "Added lecturer: $email");
                    $response = ['status' => 'success', 'message' => 'Lecturer added successfully'];
                }
                break;

            case 'add_student':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
                if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $response = ['status' => 'error', 'message' => 'Invalid or missing name/email'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
                    $stmt->execute([$name, $email, $password]);
                    logAudit($pdo, $admin_id, 'Add Student', "Added student: $email");
                    $response = ['status' => 'success', 'message' => 'Student added successfully'];
                }
                break;

            case 'add_course':
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $dept_id = trim($_POST['department_id'] ?? '');
                if (empty($name) || empty($code) || empty($dept_id)) {
                    $response = ['status' => 'error', 'message' => "All fields required"];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO courses (name, code, department_id) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $code, $dept_id]);
                    logAudit($pdo, $admin_id, 'Add Course', "Added course: $name ($code)");
                    $response = ['status' => 'success', 'message' => 'Course added successfully'];
                }
                break;

            case 'delete_course':
                $id = $_POST['id'] ?? '';
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $pdo->prepare("DELETE FROM lecturer_courses WHERE course_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM student_courses WHERE course_id = ?")->execute([$id]);
                logAudit($pdo, $admin_id, 'Delete Course', "Deleted course ID: $id");
                $response = ['status' => 'success', 'message' => 'Course deleted successfully'];
                break;

            case 'assign_lecturer_course':
                $lecturer_id = $_POST['user_id'] ?? '';
                $course_id = $_POST['course_id'] ?? '';
                $stmt = $pdo->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
                $stmt->execute([$lecturer_id, $course_id]);
                logAudit($pdo, $admin_id, 'Assign Lecturer Course', "Assigned lecturer $lecturer_id to course $course_id");
                $response = ['status' => 'success', 'message' => 'Course assigned to lecturer'];
                break;

            case 'assign_student_course':
                $student_id = $_POST['user_id'] ?? '';
                $course_id = $_POST['course_id'] ?? '';
                $stmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
                $stmt->execute([$student_id, $course_id]);
                logAudit($pdo, $admin_id, 'Assign Student Course', "Assigned student $student_id to course $course_id");
                $response = ['status' => 'success', 'message' => 'Course assigned to student'];
                break;

            case 'bulk_upload_lecturers':
            case 'bulk_upload_students':
                $csv = $_POST['csv'] ?? '';
                $role = str_replace('bulk_upload_', '', $action);
                $lines = explode("\n", trim($csv));
                $header = array_shift($lines);
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    list($name, $email, $password) = array_map('trim', explode(',', $line));
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                    $password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?) ON CONFLICT (email) DO NOTHING");
                    $stmt->execute([$name, $email, $password, $role]);
                }
                logAudit($pdo, $admin_id, "Bulk Upload $role", "Uploaded " . count($lines) . " $role");
                $response = ['status' => 'success', 'message' => ucfirst($role) . ' uploaded successfully'];
                break;

            case 'cancel_session':
                $id = $_POST['id'] ?? '';
                $stmt = $pdo->prepare("UPDATE class_sessions SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, $admin_id, 'Cancel Session', "Cancelled session ID: $id");
                $response = ['status' => 'success', 'message' => 'Session cancelled successfully'];
                break;

            case 'generate_report':
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $type = $_POST['type'] ?? '';
                $format = $_POST['format'] ?? 'csv';

                if ($type === 'attendance') {
                    $query = "SELECT a.timestamp, u.name AS student_name, c.name AS course_name, a.status 
                              FROM attendance a JOIN users u ON a.student_id = u.id JOIN class_sessions cs ON a.session_id = cs.id JOIN courses c ON cs.course_id = c.id 
                              WHERE a.timestamp BETWEEN ? AND ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$start_date, $end_date]);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $csv = "Timestamp,Student,Course,Status\n";
                    foreach ($data as $row) {
                        $csv .= "{$row['timestamp']},{$row['student_name']},{$row['course_name']},{$row['status']}\n";
                    }
                } else {
                    $query = "SELECT cs.start_time, cs.end_time, u.name AS lecturer_name, c.name AS course_name, cs.status 
                              FROM class_sessions cs JOIN users u ON cs.lecturer_id = u.id JOIN courses c ON cs.course_id = c.id 
                              WHERE cs.start_time BETWEEN ? AND ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$start_date, $end_date]);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $csv = "Start Time,End Time,Lecturer,Course,Status\n";
                    foreach ($data as $row) {
                        $csv .= "{$row['start_time']},{$row['end_time']},{$row['lecturer_name']},{$row['course_name']},{$row['status']}\n";
                    }
                }
                logAudit($pdo, $admin_id, 'Generate Report', "Generated $type report from $start_date to $end_date");
                $response = ['status' => 'success', 'message' => 'Report generated', 'data' => $csv, 'type' => $type];
                break;

            case 'get_audit_logs':
                $stmt = $pdo->prepare("SELECT a.created_at, u.name AS user_name, a.action, a.details 
                                       FROM audit_logs a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 100");
                $stmt->execute();
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'logout':
                session_destroy();
                $response = ['status' => 'success', 'message' => 'Logged out'];
                break;
        }
        echo json_encode($response);
        ob_end_flush();
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JIGPOLY Polytechnic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .btn-danger { background: #dc3545; border: none; }
        .btn-success { background: #28a745; border: none; }
        .table { color: var(--text-dark); }
        .table th, .table td { border-color: rgba(30, 60, 114, 0.2); }
        .table-hover tbody tr:hover { background: rgba(247, 201, 72, 0.1); }
        .form-control, .form-select { border-radius: 8px; border-color: var(--blue-start); color: var(--text-dark); }
        .form-control:focus, .form-select:focus { border-color: #f7c948; box-shadow: 0 0 8px rgba(247, 201, 72, 0.5); }
        .modal-content { background: rgba(255, 255, 255, 0.95); border-radius: 12px; box-shadow: var(--shadow); }
        .modal-header { background: var(--gradient); color: var(--text-light); border-bottom: none; }
        .modal-body { color: var(--text-dark); }
        .chart-container { max-width: 500px; margin: 20px auto; }
        .main-footer { background: var(--gradient); color: var(--text-light); }
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
                <a class="nav-link" href="#" id="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="#" class="brand-link">
            <span class="brand-text">JIGPOLY Polytechnic Admin</span>
        </a>
        <div class="sidebar">
            <nav class="mt-3">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item"><a href="#" class="nav-link active" data-section="dashboard"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-section="attendance"><i class="nav-icon fas fa-check"></i><p>Attendance</p></a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-section="sessions"><i class="nav-icon fas fa-calendar"></i><p>Sessions</p></a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-section="lecturers"><i class="nav-icon fas fa-user-tie"></i><p>Lecturers</p></a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-section="students"><i class="nav-icon fas fa-users"></i><p>Students</p></a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-section="courses"><i class="nav-icon fas fa-book"></i><p>Courses</p></a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-section="reports"><i class="nav-icon fas fa-file-alt"></i><p>Reports</p></a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-section="audit"><i class="nav-icon fas fa-history"></i><p>Audit Logs</p></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <h1 class="m-0">Admin Dashboard</h1>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">
                <div id="dashboard-section" class="section" style="display: block;">
                    <div class="row">
                        <div class="col-lg-3 col-6"><div class="small-box"><div class="inner"><h3 id="total-lecturers">0</h3><p>Total Lecturers</p></div><div class="icon"><i class="fas fa-user-tie"></i></div></div></div>
                        <div class="col-lg-3 col-6"><div class="small-box"><div class="inner"><h3 id="total-students">0</h3><p>Total Students</p></div><div class="icon"><i class="fas fa-users"></i></div></div></div>
                        <div class="col-lg-3 col-6"><div class="small-box"><div class="inner"><h3 id="total-courses">0</h3><p>Total Courses</p></div><div class="icon"><i class="fas fa-book"></i></div></div></div>
                        <div class="col-lg-3 col-6"><div class="small-box"><div class="inner"><h3 id="attendance-today">0</h3><p>Attendance Today</p></div><div class="icon"><i class="fas fa-check-circle"></i></div></div></div>
                    </div>
                    <div class="card mt-4"><div class="card-header"><h3 class="card-title">Attendance Trends</h3></div><div class="card-body"><div class="chart-container"><canvas id="attendanceChart"></canvas></div></div></div>
                    <div class="card mt-4"><div class="card-header"><h3 class="card-title">Course Enrollment</h3></div><div class="card-body"><div class="chart-container"><canvas id="enrollmentChart"></canvas></div></div></div>
                </div>

                <div id="attendance-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Attendance Records</h3></div>
                        <div class="card-body">
                            <div class="mb-3"><input type="date" class="form-control" id="attendance-date-filter"><button class="btn btn-primary mt-2" id="export-attendance"><i class="fas fa-file-export"></i> Export</button></div>
                            <table class="table table-bordered table-hover"><thead><tr><th>Student</th><th>Course</th><th>Time</th><th>Location</th><th>Device</th><th>Status</th></tr></thead><tbody id="attendance-table"></tbody></table>
                        </div>
                    </div>
                </div>

                <div id="sessions-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Class Sessions</h3></div>
                        <div class="card-body">
                            <table class="table table-bordered table-hover"><thead><tr><th>Lecturer</th><th>Course</th><th>Start</th><th>End</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead><tbody id="sessions-table"></tbody></table>
                        </div>
                    </div>
                </div>

                <div id="lecturers-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Lecturers</h3></div>
                        <div class="card-body">
                            <div class="mb-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLecturerModal"><i class="fas fa-plus"></i> Add</button> <label class="btn btn-primary"><i class="fas fa-upload"></i> Bulk Upload<input type="file" id="bulk-lecturers" accept=".csv" hidden></label></div>
                            <table class="table table-bordered table-hover"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Courses</th><th>Actions</th></tr></thead><tbody id="lecturers-table"></tbody></table>
                        </div>
                    </div>
                </div>

                <div id="students-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Students</h3></div>
                        <div class="card-body">
                            <div class="mb-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="fas fa-plus"></i> Add</button> <label class="btn btn-primary"><i class="fas fa-upload"></i> Bulk Upload<input type="file" id="bulk-students" accept=".csv" hidden></label></div>
                            <table class="table table-bordered table-hover"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Courses</th><th>Actions</th></tr></thead><tbody id="students-table"></tbody></table>
                        </div>
                    </div>
                </div>

                <div id="courses-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Courses</h3></div>
                        <div class="card-body">
                            <div class="mb-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal"><i class="fas fa-plus"></i> Add</button></div>
                            <table class="table table-bordered table-hover"><thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Lecturers</th><th>Students</th><th>Actions</th></tr></thead><tbody id="courses-table"></tbody></table>
                        </div>
                    </div>
                </div>

                <div id="reports-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Generate Reports</h3></div>
                        <div class="card-body">
                            <form id="report-form">
                                <div class="row g-3">
                                    <div class="col-md-3"><input type="date" class="form-control" name="start_date" required></div>
                                    <div class="col-md-3"><input type="date" class="form-control" name="end_date" required></div>
                                    <div class="col-md-3"><select class="form-control" name="type" required><option value="attendance">Attendance</option><option value="sessions">Sessions</option></select></div>
                                    <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-file-export"></i> Generate</button></div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="audit-section" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Audit Logs</h3></div>
                        <div class="card-body">
                            <table class="table table-bordered table-hover"><thead><tr><th>User</th><th>Action</th><th>Timestamp</th><th>Details</th></tr></thead><tbody id="audit-table"></tbody></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <strong>JIGPOLY Polytechnic &copy; 2025</strong> All rights reserved.
    </footer>

    <!-- Modals -->
    <div class="modal fade" id="addLecturerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add Lecturer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="add-lecturer-form">
                        <div class="mb-3"><input type="text" class="form-control" name="name" placeholder="Name" required></div>
                        <div class="mb-3"><input type="email" class="form-control" name="email" placeholder="Email" required></div>
                        <div class="mb-3"><input type="password" class="form-control" name="password" placeholder="Password" required></div>
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="add-student-form">
                        <div class="mb-3"><input type="text" class="form-control" name="name" placeholder="Name" required></div>
                        <div class="mb-3"><input type="email" class="form-control" name="email" placeholder="Email" required></div>
                        <div class="mb-3"><input type="password" class="form-control" name="password" placeholder="Password" required></div>
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add Course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="add-course-form">
                        <div class="mb-3"><input type="text" class="form-control" name="name" placeholder="Course Name" required></div>
                        <div class="mb-3"><input type="text" class="form-control" name="code" placeholder="Course Code" required></div>
                        <div class="mb-3"><select class="form-control" name="department_id" id="course-department" required><option value="">Select Department</option></select></div>
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Assign Course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="assign-course-form">
                        <input type="hidden" name="user_id" id="assign-user-id">
                        <input type="hidden" name="role" id="assign-role">
                        <div class="mb-3"><select class="form-control" name="course_id" id="assign-course-id" required><option value="">Select Course</option></select></div>
                        <button type="submit" class="btn btn-primary w-100">Assign</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
$(document).ready(function() {
    $('.section').hide();
    $('#dashboard-section').show();

    $('.nav-link').click(function(e) {
        e.preventDefault();
        $('.nav-link').removeClass('active');
        $(this).addClass('active');
        $('.section').hide();
        $(`#${$(this).data('section')}-section`).show();
    });

    function loadDashboard() {
        $.get('admin.php?action=get_dashboard', function(data) {
            $('#total-lecturers').text(data.total_lecturers);
            $('#total-students').text(data.total_students);
            $('#total-courses').text(data.total_courses);
            $('#attendance-today').text(data.attendance_today);
        }, 'json');

        $.get('admin.php?action=get_attendance', function(data) {
            const dates = [...new Set(data.map(item => item.timestamp.split(' ')[0]))].slice(-7);
            const counts = dates.map(date => data.filter(item => item.timestamp.startsWith(date)).length);
            new Chart(document.getElementById('attendanceChart').getContext('2d'), {
                type: 'line',
                data: { labels: dates, datasets: [{ label: 'Attendance', data: counts, borderColor: '#1e3c72', backgroundColor: '#f7c948', fill: false }] },
                options: { responsive: true, plugins: { legend: { labels: { color: '#1e3c72' } } }, scales: { x: { ticks: { color: '#1a2a44' } }, y: { ticks: { color: '#1a2a44' } } } }
            });
        }, 'json');

        $.get('admin.php?action=get_courses', function(data) {
            const labels = data.map(item => item.name);
            const counts = data.map(item => item.students.length);
            new Chart(document.getElementById('enrollmentChart').getContext('2d'), {
                type: 'pie',
                data: { labels: labels, datasets: [{ data: counts, backgroundColor: ['#1e3c72', '#f7c948', '#2a5298', '#b78628'], borderColor: '#fff', borderWidth: 1 }] },
                options: { responsive: true, plugins: { legend: { position: 'right', labels: { color: '#1e3c72' } } } }
            });
        }, 'json');
    }

    let attendanceData = [];
    function loadAttendance(dateFilter = '') {
        $.get(`admin.php?action=get_attendance${dateFilter ? '&date=' + dateFilter : ''}`, function(data) {
            attendanceData = data;
            let html = '';
            data.forEach(record => {
                html += `<tr><td>${record.student_name}</td><td>${record.course_name}</td><td>${record.timestamp}</td><td>${record.latitude}, ${record.longitude}</td><td>${record.device_info.slice(0, 10)}...</td><td>${record.status}</td></tr>`;
            });
            $('#attendance-table').html(html || '<tr><td colspan="6">No records</td></tr>');
        }, 'json');
    }
    $('#attendance-date-filter').change(function() { loadAttendance($(this).val()); });
    $('#export-attendance').click(function() {
        let csv = "Student,Course,Time,Latitude,Longitude,Device,Status\n";
        attendanceData.forEach(record => {
            csv += `${record.student_name},${record.course_name},${record.timestamp},${record.latitude},${record.longitude},${record.device_info},${record.status}\n`;
        });
        let blob = new Blob([csv], { type: 'text/csv' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'attendance.csv';
        link.click();
    });

    function loadSessions() {
        $.get('admin.php?action=get_sessions', function(data) {
            let html = '';
            data.forEach(session => {
                html += `<tr><td>${session.lecturer_name}</td><td>${session.course_name}</td><td>${session.start_time}</td><td>${session.end_time}</td><td>${session.latitude}, ${session.longitude}</td><td>${session.status}</td><td><button class="btn btn-sm btn-danger cancel-session" data-id="${session.id}">Cancel</button></td></tr>`;
            });
            $('#sessions-table').html(html || '<tr><td colspan="7">No sessions</td></tr>');
            $('.cancel-session').click(function() {
                Swal.fire({
                    title: 'Cancel Session?',
                    text: "This will mark the session as cancelled!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, cancel it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('admin.php?action=cancel_session', { id: $(this).data('id') }, function(res) {
                            Swal.fire(res.status, res.message, res.status);
                            loadSessions();
                        }, 'json');
                    }
                });
            });
        }, 'json');
    }

    function loadLecturers() {
        $.get('admin.php?action=get_lecturers', function(data) {
            let html = '';
            data.forEach(lecturer => {
                html += `<tr><td>${lecturer.id}</td><td>${lecturer.name}</td><td>${lecturer.email}</td><td>${lecturer.courses.join(', ')}</td><td><button class="btn btn-sm btn-primary assign-course" data-id="${lecturer.id}" data-role="lecturer">Assign Course</button></td></tr>`;
            });
            $('#lecturers-table').html(html || '<tr><td colspan="5">No lecturers</td></tr>');
            $('.assign-course').click(function() { showAssignCourseModal($(this).data('id'), $(this).data('role')); });
        }, 'json');
    }

    function loadStudents() {
        $.get('admin.php?action=get_students', function(data) {
            let html = '';
            data.forEach(student => {
                html += `<tr><td>${student.id}</td><td>${student.name}</td><td>${student.email}</td><td>${student.courses.join(', ')}</td><td><button class="btn btn-sm btn-primary assign-course" data-id="${student.id}" data-role="student">Assign Course</button></td></tr>`;
            });
            $('#students-table').html(html || '<tr><td colspan="5">No students</td></tr>');
            $('.assign-course').click(function() { showAssignCourseModal($(this).data('id'), $(this).data('role')); });
        }, 'json');
    }

    function loadCourses() {
        $.get('admin.php?action=get_courses', function(data) {
            let html = '';
            data.forEach(course => {
                html += `<tr><td>${course.id}</td><td>${course.name}</td><td>${course.code}</td><td>${course.lecturers.join(', ')}</td><td>${course.students.join(', ')}</td><td><button class="btn btn-sm btn-danger delete-course" data-id="${course.id}">Delete</button></td></tr>`;
            });
            $('#courses-table').html(html || '<tr><td colspan="6">No courses</td></tr>');
            $('.delete-course').click(function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This will delete the course permanently!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('admin.php?action=delete_course', { id: $(this).data('id') }, function(res) {
                            Swal.fire(res.status, res.message, res.status);
                            loadCourses();
                        }, 'json');
                    }
                });
            });
        }, 'json');
    }

    function showAssignCourseModal(userId, role) {
        $.get('admin.php?action=get_all_courses', function(courses) {
            let options = '<option value="">Select Course</option>';
            courses.forEach(course => options += `<option value="${course.id}">${course.name} (${course.code})</option>`);
            $('#assign-user-id').val(userId);
            $('#assign-role').val(role);
            $('#assign-course-id').html(options);
            $('#assignCourseModal').modal('show');
        }, 'json');
    }

    $('#add-lecturer-form').submit(function(e) {
        e.preventDefault();
        $.post('admin.php?action=add_lecturer', $(this).serialize(), function(res) {
            Swal.fire(res.status, res.message, res.status);
            if (res.status === 'success') {
                $('#addLecturerModal').modal('hide');
                loadLecturers();
            }
        }, 'json');
    });

    $('#add-student-form').submit(function(e) {
        e.preventDefault();
        $.post('admin.php?action=add_student', $(this).serialize(), function(res) {
            Swal.fire(res.status, res.message, res.status);
            if (res.status === 'success') {
                $('#addStudentModal').modal('hide');
                loadStudents();
            }
        }, 'json');
    });

    $('#add-course-form').submit(function(e) {
        e.preventDefault();
        $.post('admin.php?action=add_course', $(this).serialize(), function(res) {
            Swal.fire(res.status, res.message, res.status);
            if (res.status === 'success') {
                $('#addCourseModal').modal('hide');
                loadCourses();
            }
        }, 'json');
    });

    $('#assign-course-form').submit(function(e) {
        e.preventDefault();
        const role = $('#assign-role').val();
        $.post(`admin.php?action=assign_${role}_course`, $(this).serialize(), function(res) {
            Swal.fire(res.status, res.message, res.status);
            if (res.status === 'success') {
                $('#assignCourseModal').modal('hide');
                if (role === 'lecturer') loadLecturers(); else loadStudents();
            }
        }, 'json');
    });

    function handleBulkUpload(inputId, role) {
        $(`#${inputId}`).change(function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(event) {
                $.post(`admin.php?action=bulk_upload_${role}`, { csv: event.target.result }, function(res) {
                    Swal.fire(res.status, res.message, res.status);
                    if (role === 'lecturers') loadLecturers(); else loadStudents();
                }, 'json');
            };
            reader.readAsText(file);
        });
    }
    handleBulkUpload('bulk-lecturers', 'lecturers');
    handleBulkUpload('bulk-students', 'students');

    $('#addCourseModal').on('show.bs.modal', function() {
        $.get('admin.php?action=get_departments', function(data) {
            let options = '<option value="">Select Department</option>';
            data.forEach(dept => options += `<option value="${dept.id}">${dept.name}</option>`);
            $('#course-department').html(options);
        }, 'json');
    });

    $('#report-form').submit(function(e) {
        e.preventDefault();
        let formData = $(this).serialize() + '&format=csv';
        $.post('admin.php?action=generate_report', formData, function(res) {
            if (res.status === 'success') {
                let blob = new Blob([res.data], { type: 'text/csv' });
                let link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `${res.type}_report_${new Date().toISOString().slice(0,10)}.csv`;
                link.click();

                Swal.fire({
                    title: 'Also download as PDF?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, generate PDF'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const { jsPDF } = window.jspdf;
                        const doc = new jsPDF();
                        doc.text(`${res.type.charAt(0).toUpperCase() + res.type.slice(1)} Report`, 10, 10);
                        doc.text(`From: ${$('input[name="start_date"]').val()} To: ${$('input[name="end_date"]').val()}`, 10, 20);
                        let y = 30;
                        res.data.split('\n').forEach(line => {
                            if (y > 280) { doc.addPage(); y = 10; }
                            doc.text(line, 10, y);
                            y += 10;
                        });
                        doc.save(`${res.type}_report_${new Date().toISOString().slice(0,10)}.pdf`);
                    }
                });
            }
            Swal.fire(res.status, res.message, res.status);
        }, 'json');
    });

    function loadAuditLogs() {
        $.get('admin.php?action=get_audit_logs', function(data) {
            let html = '';
            data.forEach(log => {
                html += `<tr><td>${log.user_name}</td><td>${log.action}</td><td>${log.timestamp}</td><td>${log.details}</td></tr>`;
            });
            $('#audit-table').html(html || '<tr><td colspan="4">No logs</td></tr>');
        }, 'json');
    }

    $('.nav-link').on('click', function() {
        const section = $(this).data('section');
        if (section === 'dashboard') loadDashboard();
        if (section === 'attendance') loadAttendance();
        if (section === 'sessions') loadSessions();
        if (section === 'lecturers') loadLecturers();
        if (section === 'students') loadStudents();
        if (section === 'courses') loadCourses();
        if (section === 'audit') loadAuditLogs();
    });

    $('#logout').click(function() {
        $.post('admin.php?action=logout', function(res) {
            window.location = 'login.php';
        }, 'json');
    });
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
