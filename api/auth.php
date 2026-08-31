<?php
// api/auth.php
// JIGPOLY Polytechnic - Authentication API.
//
// Uses config/db.php so the PHP/MySQL timezone is pinned to Africa/Lagos.
// (Timezone is not directly involved in login, but keeping every endpoint on
// the same central connection guarantees session timestamps, audit logs, and
// date-based queries all agree.)

ob_start(); // Start output buffering to catch any stray output
session_start();
header('Content-Type: application/json');

// Include database connection (sets timezone + $pdo).
try {
    require '../config/db.php';
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/auth_error.log', "DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Server error: Unable to connect to database']);
    ob_end_flush();
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
            ob_end_flush();
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
        try {
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session id to prevent session fixation.
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];

                $redirect = match ($user['role']) {
                    'admin'    => 'admin.php',
                    'lecturer' => 'lecturer.php',
                    'student'  => 'student.php',
                    default    => 'login.php',
                };
                echo json_encode(['status' => 'success', 'message' => 'Login successful', 'redirect' => $redirect]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
            }
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/auth_error.log', "Query Error: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['status' => 'error', 'message' => 'Database query failed']);
        }
        break;

    case 'logout':
        $_SESSION = [];
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified']);
        break;
}

ob_end_flush(); // Send the buffered output
