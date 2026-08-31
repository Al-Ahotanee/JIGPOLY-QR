 <?php
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

// Branding constants
$BRAND_SHORT = 'JIGPOLY Polytechnic';
$BRAND_FULL  = 'JIGPOLY Polytechnic';
$BRAND_PROG  = 'OND / ND / HND';

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    switch ($action) {
        case 'get_courses':
            $stmt = $pdo->prepare("SELECT c.id, c.name, c.code 
                                   FROM courses c 
                                   JOIN lecturer_courses lc ON c.id = lc.course_id 
                                   WHERE lc.lecturer_id = ?");
            $stmt->execute([$lecturer_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'get_registered_students':
            $course_id = $_POST['course_id'] ?? 0;
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, 
                       (SELECT COUNT(a.id) FROM attendance a 
                        JOIN class_sessions cs ON a.session_id = cs.id 
                        WHERE a.student_id = u.id AND cs.course_id = ?) AS attendance_count,
                       (SELECT COUNT(*) FROM class_sessions cs 
                        WHERE cs.course_id = ? AND cs.lecturer_id = ?) AS total_sessions
                FROM users u 
                JOIN student_courses sc ON u.id = sc.student_id 
                WHERE sc.course_id = ? AND u.role = 'student'");
            $stmt->execute([$course_id, $course_id, $lecturer_id, $course_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as &$row) {
                $row['percentage'] = $row['total_sessions'] > 0 ? round(($row['attendance_count'] / $row['total_sessions']) * 100, 2) : 0;
            }
            echo json_encode($result);
            break;

        case 'get_session_attendance':
            $session_id = $_POST['session_id'] ?? 0;
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, 
                       CASE WHEN a.id IS NOT NULL THEN 'Present' ELSE 'Absent' END AS status,
                       a.timestamp
                FROM student_courses sc 
                JOIN users u ON sc.student_id = u.id 
                LEFT JOIN attendance a ON u.id = a.student_id AND a.session_id = ?
                JOIN class_sessions cs ON cs.id = ?
                WHERE sc.course_id = cs.course_id AND u.role = 'student'");
            $stmt->execute([$session_id, $session_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'get_attendance_analytics':
            $course_id = $_POST['course_id'] ?? 0;
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email,
                       (SELECT COUNT(a.id) FROM attendance a 
                        JOIN class_sessions cs ON a.session_id = cs.id 
                        WHERE a.student_id = u.id AND cs.course_id = ?) AS attendance_count,
                       (SELECT COUNT(*) FROM class_sessions cs 
                        WHERE cs.course_id = ? AND cs.lecturer_id = ?) AS total_sessions
                FROM users u 
                JOIN student_courses sc ON u.id = sc.student_id 
                WHERE sc.course_id = ? AND u.role = 'student'");
            $stmt->execute([$course_id, $course_id, $lecturer_id, $course_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as &$row) {
                $row['percentage'] = $row['total_sessions'] > 0 ? round(($row['attendance_count'] / $row['total_sessions']) * 100, 2) : 0;
            }
            echo json_encode($result);
            break;

        case 'get_sessions':
            $course_id = $_POST['course_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT id, start_time, end_time, status 
                                   FROM class_sessions 
                                   WHERE course_id = ? AND lecturer_id = ?");
            $stmt->execute([$course_id, $lecturer_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'logout':
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

// Fetch initial data
$stmt = $pdo->prepare("SELECT c.id, c.name, c.code 
                       FROM courses c 
                       JOIN lecturer_courses lc ON c.id = lc.course_id 
                       WHERE lc.lecturer_id = ?");
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Register - JIGPOLY Polytechnic</title>
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
            --hover-scale: scale(1.05);
        }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: var(--gradient); 
            color: var(--text-dark); 
            min-height: 100vh; 
            overflow-x: hidden; 
        }
        .wrapper { background: none; }
        .main-header { 
            background: var(--gradient); 
            border-bottom: 2px solid rgba(255, 255, 255, 0.2); 
            box-shadow: var(--shadow); 
            padding: 10px 20px; 
        }
        .navbar-nav .nav-link { 
            color: var(--text-light) !important; 
            font-weight: 600; 
            transition: color 0.3s ease; 
        }
        .navbar-nav .nav-link:hover { 
            color: #f7c948 !important; 
        }
        .logout-btn { 
            background: #dc3545; 
            color: var(--text-light); 
            border: none; 
            padding: 8px 16px; 
            border-radius: 8px; 
            transition: var(--hover-scale) 0.3s ease, background 0.3s ease; 
        }
        .logout-btn:hover { 
            background: #c82333; 
            transform: var(--hover-scale); 
        }
        .sidebar { 
            background: var(--gradient); 
            box-shadow: var(--shadow); 
            transition: width 0.3s ease; 
        }
        .brand-link { 
            background: rgba(255, 255, 255, 0.1); 
            color: var(--text-light); 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            text-align: center; 
            padding: 15px; 
        }
        .nav-sidebar .nav-link { 
            color: var(--text-light) !important; 
            transition: all 0.3s ease; 
            border-radius: 8px; 
            margin: 5px 10px; 
            display: flex; 
            align-items: center; 
        }
        .nav-sidebar .nav-link:hover, .nav-sidebar .nav-link.active { 
            background: rgba(255, 255, 255, 0.2); 
            color: #f7c948 !important; 
            transform: var(--hover-scale); 
        }
        .nav-icon { margin-right: 10px; }
        .content-wrapper { 
            background: rgba(255, 255, 255, 0.95); 
            border-radius: 15px; 
            margin: 20px; 
            box-shadow: var(--shadow); 
            animation: fadeIn 0.5s ease-in; 
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .content-header h1 { 
            color: var(--blue-start); 
            font-weight: 700; 
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1); 
            animation: slideIn 0.5s ease-out; 
        }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .card { 
            background: rgba(255, 255, 255, 0.9); 
            border: none; 
            border-radius: 12px; 
            box-shadow: var(--shadow); 
            overflow: hidden; 
            transition: transform 0.3s ease; 
        }
        .card:hover { transform: var(--hover-scale); }
        .card-header { 
            background: var(--gradient); 
            color: var(--text-light); 
            font-weight: 600; 
            border-bottom: none; 
            padding: 15px; 
        }
        .btn-primary { 
            background: var(--gradient); 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            padding: 10px 20px; 
            transition: all 0.3s ease; 
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, #f7c948, #1e3c72); 
            box-shadow: var(--shadow); 
            transform: var(--hover-scale); 
        }
        .btn-success { 
            background: #28a745; 
            border: none; 
            transition: var(--hover-scale) 0.3s ease; 
        }
        .btn-success:hover { transform: var(--hover-scale); }
        .btn-info { 
            background: #17a2b8; 
            border: none; 
            transition: var(--hover-scale) 0.3s ease; 
        }
        .btn-info:hover { transform: var(--hover-scale); }
        .table { 
            color: var(--text-dark); 
            animation: fadeIn 0.5s ease-in; 
        }
        .table th, .table td { 
            border-color: rgba(30, 60, 114, 0.2); 
            padding: 12px; 
        }
        .table-hover tbody tr { 
            transition: background 0.3s ease, transform 0.3s ease; 
        }
        .table-hover tbody tr:hover { 
            background: rgba(247, 201, 72, 0.1); 
            transform: var(--hover-scale); 
        }
        .form-control, .form-select { 
            border-radius: 8px; 
            border-color: var(--blue-start); 
            color: var(--text-dark); 
            padding: 10px; 
            transition: all 0.3s ease; 
        }
        .form-control:focus, .form-select:focus { 
            border-color: #f7c948; 
            box-shadow: 0 0 8px rgba(247, 201, 72, 0.5); 
            transform: scale(1.02); 
        }
        .modal-content { 
            background: rgba(255, 255, 255, 0.95); 
            border-radius: 12px; 
            box-shadow: var(--shadow); 
            animation: zoomIn 0.3s ease-in; 
        }
        @keyframes zoomIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header { 
            background: var(--gradient); 
            color: var(--text-light); 
            border-bottom: none; 
            padding: 15px; 
        }
        .modal-body { 
            color: var(--text-dark); 
            padding: 20px; 
        }
        .badge-present { 
            background-color: #28a745; 
            padding: 6px 12px; 
            border-radius: 12px; 
        }
        .badge-absent { 
            background-color: #dc3545; 
            padding: 6px 12px; 
            border-radius: 12px; 
        }
        .badge-high { 
            background-color: #28a745; 
            padding: 6px 12px; 
            border-radius: 12px; 
        }
        .badge-low { 
            background-color: #dc3545; 
            padding: 6px 12px; 
            border-radius: 12px; 
        }
        .action-buttons .btn { 
            margin: 0 5px; 
            transition: all 0.3s ease; 
        }
        .action-buttons .btn:hover { 
            transform: var(--hover-scale); 
        }
        @media (max-width: 768px) {
            .content-wrapper { margin: 10px; }
            .sidebar { width: 70px; }
            .nav-link p { display: none; }
            .nav-link { justify-content: center; }
            .brand-link { font-size: 14px; padding: 10px; }
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
                <button class="logout-btn" id="logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="#" class="brand-link">
            <span class="brand-text">Attendance Register</span>
        </a>
        <div class="sidebar">
            <nav class="mt-3">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#" class="nav-link active" data-section="register"><i class="nav-icon fas fa-book"></i><p>Register</p></a>
                    </li>
                    <li class="nav-item">
                        <a href="lecturer.php" class="nav-link"><i class="nav-icon fas fa-arrow-left"></i><p>Back to Dashboard</p></a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <h1 class="m-0">Attendance Register</h1>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">
                <div id="register-section" class="section" style="display: block;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Course Attendance Overview</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Select Course</label>
                                <select class="form-control" id="course-select">
                                    <option value="">Select a Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>"><?php echo $course['name'] . ' (' . $course['code'] . ')'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mt-4 action-buttons">
                                <button class="btn btn-primary" id="view-registered"><i class="fas fa-users"></i> View Registered Students</button>
                                <button class="btn btn-primary" id="view-session-analytics"><i class="fas fa-chart-bar"></i> Session Analytics</button>
                                <button class="btn btn-primary" id="view-attendance-analytics"><i class="fas fa-analytics"></i> Attendance Analytics</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="registeredStudentsModal" tabindex="-1" aria-labelledby="registeredStudentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registeredStudentsModalLabel">Registered Students</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-hover">
                        <thead><tr><th>Name</th><th>Email</th><th>Attendance Count</th><th>Total Sessions</th><th>Percentage</th></tr></thead>
                        <tbody id="registered-students-table"></tbody>
                    </table>
                    <div class="mt-3 action-buttons">
                        <button class="btn btn-success" id="export-csv"><i class="fas fa-file-csv"></i> Export CSV</button>
                        <button class="btn btn-success" id="export-doc"><i class="fas fa-file-word"></i> Export DOC</button>
                        <button class="btn btn-success" id="export-pdf"><i class="fas fa-file-pdf"></i> Export PDF</button>
                        <button class="btn btn-info" id="print-register"><i class="fas fa-print"></i> Print</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sessionAnalyticsModal" tabindex="-1" aria-labelledby="sessionAnalyticsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sessionAnalyticsModalLabel">Session Attendance Analytics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Session</label>
                        <select class="form-control" id="session-select">
                            <option value="">Select a Session</option>
                        </select>
                    </div>
                    <table class="table table-bordered table-hover mt-3">
                        <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Timestamp</th></tr></thead>
                        <tbody id="session-attendance-table"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attendanceAnalyticsModal" tabindex="-1" aria-labelledby="attendanceAnalyticsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attendanceAnalyticsModalLabel">Attendance Analytics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h5>Students with 70%+ Attendance</h5>
                    <table class="table table-bordered table-hover">
                        <thead><tr><th>Name</th><th>Email</th><th>Percentage</th></tr></thead>
                        <tbody id="high-attendance-table"></tbody>
                    </table>
                    <h5 class="mt-4">Students with Less than 70% Attendance</h5>
                    <table class="table table-bordered table-hover">
                        <thead><tr><th>Name</th><th>Email</th><th>Percentage</th></tr></thead>
                        <tbody id="low-attendance-table"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <strong>JIGPOLY Polytechnic &copy; 2025</strong> All rights reserved.
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    $(document).ready(function() {
        console.log('Document ready');

        // Sidebar toggle animation
        $('[data-widget="pushmenu"]').on('click', function() {
            $('.sidebar').toggleClass('sidebar-collapse');
        });

        // Load course sessions when course changes
        $('#course-select').change(function() {
            const courseId = $(this).val();
            if (courseId) {
                $.post('?action=get_sessions', { course_id: courseId }, function(data) {
                    let options = '<option value="">Select a Session</option>';
                    data.forEach(session => {
                        options += `<option value="${session.id}">${session.start_time} - ${session.status}</option>`;
                    });
                    $('#session-select').html(options).trigger('change');
                }, 'json').fail(() => Swal.fire('Error', 'Failed to load sessions', 'error'));
            } else {
                $('#session-select').html('<option value="">Select a Session</option>');
            }
        });

        // View Registered Students
        $('#view-registered').click(function() {
            const courseId = $('#course-select').val();
            if (!courseId) return Swal.fire('Error', 'Please select a course', 'error');
            $.post('?action=get_registered_students', { course_id: courseId }, function(data) {
                let html = '';
                data.forEach(student => {
                    html += `
                        <tr>
                            <td>${student.name}</td>
                            <td>${student.email}</td>
                            <td>${student.attendance_count}</td>
                            <td>${student.total_sessions}</td>
                            <td>${student.percentage}%</td>
                        </tr>`;
                });
                $('#registered-students-table').html(html || '<tr><td colspan="5">No students registered</td></tr>');
                $('#registeredStudentsModal').modal('show');
            }, 'json').fail(() => Swal.fire('Error', 'Failed to load students', 'error'));
        });

        // Export CSV
        $('#export-csv').click(function() {
            const courseId = $('#course-select').val();
            $.post('?action=get_registered_students', { course_id: courseId }, function(data) {
                const csv = 'Name,Email,Attendance Count,Total Sessions,Percentage\n' + 
                            data.map(row => `${row.name},${row.email},${row.attendance_count},${row.total_sessions},${row.percentage}`).join('\n');
                const blob = new Blob([csv], { type: 'text/csv' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `register_course_${courseId}.csv`;
                link.click();
            }, 'json');
        });

        // Export DOC
        $('#export-doc').click(function() {
            const courseId = $('#course-select').val();
            $.post('?action=get_registered_students', { course_id: courseId }, function(data) {
                let content = 'Registered Students\n\nName\tEmail\tAttendance Count\tTotal Sessions\tPercentage\n';
                data.forEach(row => {
                    content += `${row.name}\t${row.email}\t${row.attendance_count}\t${row.total_sessions}\t${row.percentage}%\n`;
                });
                const blob = new Blob([content], { type: 'text/plain' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `register_course_${courseId}.doc`;
                link.click();
            }, 'json');
        });

        // Export PDF
        $('#export-pdf').click(function() {
            const element = document.getElementById('registered-students-table').parentElement;
            html2pdf().from(element).set({
                margin: 1,
                filename: `register_course_${$('#course-select').val()}.pdf`,
                html2canvas: { scale: 2 },
                jsPDF: { orientation: 'landscape' }
            }).save();
        });

        // Print Register
        $('#print-register').click(function() {
            const printContent = document.getElementById('registered-students-table').parentElement.innerHTML;
            const newWin = window.open('');
            newWin.document.write('<html><head><title>Print Register</title>');
            newWin.document.write('<style>table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #000; padding: 8px; }</style>');
            newWin.document.write('</head><body>' + printContent + '</body></html>');
            newWin.document.close();
            newWin.print();
        });

        // View Session Analytics
        $('#view-session-analytics').click(function() {
            const courseId = $('#course-select').val();
            if (!courseId) return Swal.fire('Error', 'Please select a course', 'error');
            $('#sessionAnalyticsModal').modal('show');
        });

        $('#session-select').change(function() {
            const sessionId = $(this).val();
            if (sessionId) {
                $.post('?action=get_session_attendance', { session_id: sessionId }, function(data) {
                    let html = '';
                    data.forEach(student => {
                        html += `
                            <tr>
                                <td>${student.name}</td>
                                <td>${student.email}</td>
                                <td><span class="badge badge-${student.status === 'Present' ? 'present' : 'absent'}">${student.status}</span></td>
                                <td>${student.timestamp || '-'}</td>
                            </tr>`;
                    });
                    $('#session-attendance-table').html(html || '<tr><td colspan="4">No attendance data</td></tr>');
                }, 'json').fail(() => Swal.fire('Error', 'Failed to load session attendance', 'error'));
            }
        });

        // View Attendance Analytics
        $('#view-attendance-analytics').click(function() {
            const courseId = $('#course-select').val();
            if (!courseId) return Swal.fire('Error', 'Please select a course', 'error');
            $.post('?action=get_attendance_analytics', { course_id: courseId }, function(data) {
                let highHtml = '', lowHtml = '';
                data.forEach(student => {
                    const row = `
                        <tr>
                            <td>${student.name}</td>
                            <td>${student.email}</td>
                            <td><span class="badge badge-${student.percentage >= 70 ? 'high' : 'low'}">${student.percentage}%</span></td>
                        </tr>`;
                    if (student.percentage >= 70) highHtml += row;
                    else lowHtml += row;
                });
                $('#high-attendance-table').html(highHtml || '<tr><td colspan="3">No students with 70%+ attendance</td></tr>');
                $('#low-attendance-table').html(lowHtml || '<tr><td colspan="3">No students with <70% attendance</td></tr>');
                $('#attendanceAnalyticsModal').modal('show');
            }, 'json').fail(() => Swal.fire('Error', 'Failed to load analytics', 'error'));
        });

        // Logout
        $('#logout').click(function() {
            console.log('Logging out');
            Swal.fire({
                title: 'Logout?',
                text: "Are you sure you want to log out?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#1e3c72',
                confirmButtonText: 'Yes, log out!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('?action=logout', function(res) {
                        if (res.status === 'success') window.location = 'login.php';
                    }, 'json').fail(xhr => Swal.fire('Error', 'Logout failed: ' + xhr.responseText, 'error'));
                }
            });
        });
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