<?php
declare(strict_types=1);

$isProduction = (getenv('APP_ENV') ?: 'production') === 'production';
date_default_timezone_set('Africa/Lagos');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

$databaseUrl = getenv('DATABASE_URL') ?: '';
if ($databaseUrl === '') {
    $host = getenv('PGHOST') ?: '127.0.0.1';
    $port = getenv('PGPORT') ?: '5432';
    $db = getenv('PGDATABASE') ?: 'gps_attendance_db';
    $user = getenv('PGUSER') ?: 'postgres';
    $pass = getenv('PGPASSWORD') ?: '';
    $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";
} else {
    $parts = parse_url($databaseUrl);
    if ($parts === false || empty($parts['host'])) {
        throw new RuntimeException('Invalid DATABASE_URL');
    }
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        $parts['host'],
        $parts['port'] ?? 5432,
        ltrim($parts['path'] ?? '/postgres', '/')
    );
    $user = urldecode($parts['user'] ?? '');
    $pass = urldecode($parts['pass'] ?? '');
}

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET TIME ZONE 'Africa/Lagos'");

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'student' CHECK (role IN ('admin','lecturer','student')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS colleges (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    code VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS departments (
    id BIGSERIAL PRIMARY KEY,
    college_id BIGINT REFERENCES colleges(id) ON DELETE RESTRICT,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS courses (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    department_id BIGINT NULL REFERENCES departments(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS lecturer_courses (
    lecturer_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    course_id BIGINT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    PRIMARY KEY (lecturer_id, course_id)
);
CREATE TABLE IF NOT EXISTS student_courses (
    student_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    course_id BIGINT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    PRIMARY KEY (student_id, course_id)
);
CREATE TABLE IF NOT EXISTS class_sessions (
    id BIGSERIAL PRIMARY KEY,
    lecturer_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    course_id BIGINT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    latitude NUMERIC(10,7) NOT NULL,
    longitude NUMERIC(10,7) NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    status VARCHAR(15) NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive','expired','cancelled')),
    radius NUMERIC(10,2) NOT NULL DEFAULT 50,
    qr_code VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS attendance (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id BIGINT NOT NULL REFERENCES class_sessions(id) ON DELETE CASCADE,
    latitude NUMERIC(10,7) NOT NULL,
    longitude NUMERIC(10,7) NOT NULL,
    device_info VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'valid',
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uniq_student_session UNIQUE (student_id, session_id)
);
CREATE TABLE IF NOT EXISTS session_feedback (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id BIGINT NOT NULL REFERENCES class_sessions(id) ON DELETE CASCADE,
    feedback TEXT NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS attendance_excuses (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id BIGINT NOT NULL REFERENCES class_sessions(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    document_path VARCHAR(500) NULL,
    status VARCHAR(15) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
    lecturer_comment TEXT NULL,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
SQL
    );

    $pdo->exec("INSERT INTO colleges (name, code) VALUES ('College of Computing and Information Technology', 'CCIT'), ('College of Science and Technology', 'CST') ON CONFLICT DO NOTHING");
    $pdo->exec("INSERT INTO departments (college_id, name, code) SELECT c.id, v.name, v.code FROM colleges c CROSS JOIN (VALUES ('Computer Science','CSC'),('Information Technology','IT'),('Mathematics','MTH')) AS v(name,code) WHERE c.code = 'CCIT' ON CONFLICT DO NOTHING");
    $pdo->exec("INSERT INTO courses (name, code, department_id) SELECT v.name, v.code, d.id FROM (VALUES ('Introduction to Programming','CST101','CSC'),('Mathematics for Computing','CST102','MTH')) AS v(name,code,dept_code) JOIN departments d ON d.code = v.dept_code ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name");

    $hash = password_hash('123', PASSWORD_DEFAULT);
    $seed = [
        ['Admin User', 'admin@jigpoly.edu.ng', 'admin'],
        ['Lecturer User', 'lecturer@jigpoly.edu.ng', 'lecturer'],
        ['Student User', 'student@jigpoly.edu.ng', 'student'],
    ];
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?) ON CONFLICT (email) DO NOTHING");
    foreach ($seed as $row) $stmt->execute([$row[0], $row[1], $hash, $row[2]]);
    $pdo->exec("INSERT INTO lecturer_courses (lecturer_id, course_id) SELECT u.id, c.id FROM users u CROSS JOIN courses c WHERE u.email = 'lecturer@jigpoly.edu.ng' AND c.code IN ('CST101','CST102') ON CONFLICT DO NOTHING");
    $pdo->exec("INSERT INTO student_courses (student_id, course_id) SELECT u.id, c.id FROM users u CROSS JOIN courses c WHERE u.email = 'student@jigpoly.edu.ng' AND c.code IN ('CST101','CST102') ON CONFLICT DO NOTHING");
} catch (Throwable $e) {
    error_log('Database bootstrap failed: ' . $e->getMessage());
    throw new RuntimeException('Database connection or migration failed.');
}
