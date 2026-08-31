 $(document).ready(function() {
    // Particles.js
    particlesJS('particles-js', {
        particles: {
            number: { value: 120, density: { enable: true, value_area: 800 } },
            color: { value: '#00ffea' },
            shape: { type: 'circle' },
            opacity: { value: 0.8, random: true },
            size: { value: 3, random: true },
            move: { enable: true, speed: 4, direction: 'none', random: true }
        },
        interactivity: {
            events: { onhover: { enable: true, mode: 'repulse' }, onclick: { enable: true, mode: 'push' } }
        }
    });

    // AOS Initialization
    AOS.init({ duration: 1000, once: true });

    // Sidebar Toggle
    $('#sidebarToggle').click(function() {
        $('#sidebar').toggleClass('collapsed');
        $('#content, footer').toggleClass('expanded');
    });

    // Section Switching
    $('.nav-link').click(function(e) {
        e.preventDefault();
        $('.nav-link').removeClass('active');
        $(this).addClass('active');
        $('.section').addClass('d-none');
        $(`#${$(this).data('section')}-section`).removeClass('d-none');
        AOS.refresh();
    });

    // Live Clock
    function updateClock() {
        $('#live-clock').text(new Date().toLocaleString('en-US', { timeZone: 'Africa/Lagos' }));
    }
    updateClock();
    setInterval(updateClock, 1000);

     function loadDashboard() {
    // Load stats
    $.get('api/admin.php?action=get_dashboard', function(data) {
        $('#total-lecturers').text(data.total_lecturers);
        $('#total-students').text(data.total_students);
        $('#total-courses').text(data.total_courses);
        $('#attendance-today').text(data.attendance_today);
    }, 'json');

    // Attendance Trends (Line Chart - Existing)
    $.get('api/admin.php?action=get_attendance', function(data) {
        const dates = [...new Set(data.map(item => item.timestamp.split(' ')[0]))].slice(-7);
        const counts = dates.map(date => data.filter(item => item.timestamp.startsWith(date)).length);
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{ label: 'Attendance', data: counts, borderColor: '#00ffea', fill: false }]
            },
            options: { 
                responsive: true, 
                plugins: { legend: { labels: { color: '#e0e0e0' } } }, 
                scales: { x: { ticks: { color: '#e0e0e0' } }, y: { ticks: { color: '#e0e0e0' } } }
            }
        });
    }, 'json');

    // Course Enrollment (Pie Chart - New)
    $.get('api/admin.php?action=get_course_enrollment', function(data) {
        const labels = data.map(item => item.name);
        const counts = data.map(item => item.student_count);
        const ctx = document.getElementById('enrollmentChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: ['#00ffea', '#007bff', '#ff007a', '#ff6b6b', '#ffd700', '#00cc00'],
                    borderColor: '#0d1b2a',
                    borderWidth: 2
                }]
            },
            options: { 
                responsive: true, 
                plugins: { legend: { position: 'right', labels: { color: '#e0e0e0' } } }
            }
        });
    }, 'json');

    // Attendance by Day (Bar Chart - New)
    $.get('api/admin.php?action=get_attendance_by_day', function(data) {
        const labels = data.map(item => item.date);
        const counts = data.map(item => item.attendance_count);
        const ctx = document.getElementById('attendanceByDayChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Attendance',
                    data: counts,
                    backgroundColor: '#00ffea',
                    borderColor: '#007bff',
                    borderWidth: 1
                }]
            },
            options: { 
                responsive: true, 
                plugins: { legend: { labels: { color: '#e0e0e0' } } }, 
                scales: { 
                    x: { ticks: { color: '#e0e0e0' } }, 
                    y: { ticks: { color: '#e0e0e0' }, beginAtZero: true } 
                }
            }
        });
    }, 'json');
}
    // Load Attendance
    let attendanceData = [];
    function loadAttendance(dateFilter = '') {
        $.get(`api/admin.php?action=get_attendance${dateFilter ? '&date=' + dateFilter : ''}`, function(data) {
            attendanceData = data;
            let html = '';
            data.forEach(record => {
                html += `<tr>
                    <td>${record.student_name}</td>
                    <td>${record.course_name}</td>
                    <td>${record.timestamp}</td>
                    <td>${record.latitude}, ${record.longitude}</td>
                    <td>${record.device_info.slice(0, 10)}...</td>
                    <td>${record.status}</td>
                </tr>`;
            });
            $('#attendance-table').html(html);
        }, 'json').fail(() => Swal.fire('Error', 'Failed to load attendance', 'error'));
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

    // Load Sessions
    function loadSessions() {
        $.get('api/admin.php?action=get_sessions', function(data) {
            let html = '';
            data.forEach(session => {
                html += `<tr>
                    <td>${session.lecturer_name}</td>
                    <td>${session.course_name}</td>
                    <td>${session.start_time}</td>
                    <td>${session.end_time}</td>
                    <td>${session.latitude}, ${session.longitude}</td>
                    <td>${session.status}</td>
                    <td><button class="btn btn-sm btn-danger cancel-session" data-id="${session.id}">Cancel</button></td>
                </tr>`;
            });
            $('#sessions-table').html(html);
            $('.cancel-session').click(function() {
                Swal.fire({
                    title: 'Cancel Session?',
                    text: "This will mark the session as cancelled!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, cancel it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('api/admin.php?action=cancel_session', { id: $(this).data('id') }, function(res) {
                            Swal.fire(res.status, res.message, res.status);
                            loadSessions();
                        }, 'json');
                    }
                });
            });
        }, 'json').fail(() => Swal.fire('Error', 'Failed to load sessions', 'error'));
    }

    // Load Lecturers
    function loadLecturers() {
        $.get('api/admin.php?action=get_lecturers', function(data) {
            let html = '';
            data.forEach(lecturer => {
                html += `<tr>
                    <td>${lecturer.id}</td>
                    <td>${lecturer.name}</td>
                    <td>${lecturer.email}</td>
                    <td>${lecturer.courses.join(', ')}</td>
                    <td><button class="btn btn-sm btn-primary assign-course" data-id="${lecturer.id}">Assign Course</button></td>
                </tr>`;
            });
            $('#lecturers-table').html(html);
            $('.assign-course').click(function() { showAssignCourseModal($(this).data('id'), 'lecturer'); });
        }, 'json').fail(() => Swal.fire('Error', 'Failed to load lecturers', 'error'));
    }

    // Load Students
    function loadStudents() {
        $.get('api/admin.php?action=get_students', function(data) {
            let html = '';
            data.forEach(student => {
                html += `<tr>
                    <td>${student.id}</td>
                    <td>${student.name}</td>
                    <td>${student.email}</td>
                    <td>${student.courses.join(', ')}</td>
                    <td><button class="btn btn-sm btn-primary assign-course" data-id="${student.id}">Assign Course</button></td>
                </tr>`;
            });
            $('#students-table').html(html);
            $('.assign-course').click(function() { showAssignCourseModal($(this).data('id'), 'student'); });
        }, 'json').fail(() => Swal.fire('Error', 'Failed to load students', 'error'));
    }

    // Load Courses
    function loadCourses() {
        $.get('api/admin.php?action=get_courses', function(data) {
            let html = '';
            data.forEach(course => {
                html += `<tr>
                    <td>${course.id}</td>
                    <td>${course.name}</td>
                    <td>${course.code}</td>
                    <td>${course.lecturers.join(', ')}</td>
                    <td>${course.students.join(', ')}</td>
                    <td><button class="btn btn-sm btn-danger delete-course" data-id="${course.id}">Delete</button></td>
                </tr>`;
            });
            $('#courses-table').html(html);
            $('.delete-course').click(function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This will delete the course permanently!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('api/admin.php?action=delete_course', { id: $(this).data('id') }, function(res) {
                            Swal.fire(res.status, res.message, res.status);
                            loadCourses();
                        }, 'json');
                    }
                });
            });
        }, 'json').fail(() => Swal.fire('Error', 'Failed to load courses', 'error'));
    }

    // Assign Course Modal
    function showAssignCourseModal(userId, role) {
        $.get('api/admin.php?action=get_all_courses', function(courses) {
            let html = `<form id="assign-course-form" data-id="${userId}" data-role="${role}">
                <div class="mb-3">
                    <select class="form-control" name="course_id" required>
                        <option value="">Select Course</option>`;
            courses.forEach(course => html += `<option value="${course.id}">${course.name} (${course.code})</option>`);
            html += `</select></div><button type="submit" class="btn btn-primary w-100">Assign</button></form>`;
            Swal.fire({
                title: `Assign Course to ${role.charAt(0).toUpperCase() + role.slice(1)}`,
                html: html,
                showConfirmButton: false,
                background: 'rgba(255, 255, 255, 0.1)',
                color: '#fff'
            });
            $('#assign-course-form').submit(function(e) {
                e.preventDefault();
                $.post(`api/admin.php?action=assign_${role}_course`, $(this).serialize() + `&user_id=${userId}`, function(res) {
                    Swal.fire(res.status, res.message, res.status);
                    if (role === 'lecturer') loadLecturers(); else loadStudents();
                }, 'json');
            });
        }, 'json');
    }

    // Add Lecturer
    $('#add-lecturer-form').submit(function(e) {
        e.preventDefault();
        $.post('api/admin.php?action=add_lecturer', $(this).serialize(), function(res) {
            Swal.fire(res.status, res.message, res.status);
            if (res.status === 'success') {
                $('#addLecturerModal').modal('hide');
                loadLecturers();
            }
        }, 'json');
    });

    // Add Student
    $('#add-student-form').submit(function(e) {
        e.preventDefault();
        $.post('api/admin.php?action=add_student', $(this).serialize(), function(res) {
            Swal.fire(res.status, res.message, res.status);
            if (res.status === 'success') {
                $('#addStudentModal').modal('hide');
                loadStudents();
            }
        }, 'json');
    });

    // Add Course
    $('#add-course-form').submit(function(e) {
        e.preventDefault();
        $.post('api/admin.php?action=add_course', $(this).serialize(), function(res) {
            Swal.fire(res.status, res.message, res.status);
            if (res.status === 'success') {
                $('#addCourseModal').modal('hide');
                loadCourses();
            }
        }, 'json');
    });

    // Bulk Upload
    function handleBulkUpload(inputId, role) {
        $(`#${inputId}`).change(function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(event) {
                const csv = event.target.result;
                $.post(`api/admin.php?action=bulk_upload_${role}`, { csv: csv }, function(res) {
                    Swal.fire(res.status, res.message, res.status);
                    if (role === 'lecturers') loadLecturers(); else loadStudents();
                }, 'json');
            };
            reader.readAsText(file);
        });
    }
    handleBulkUpload('bulk-lecturers', 'lecturers');
    handleBulkUpload('bulk-students', 'students');

    // Populate Departments in Add Course Modal
    $('#addCourseModal').on('show.bs.modal', function() {
        $.get('api/admin.php?action=get_departments', function(data) {
            let options = '<option value="">Select Department</option>';
            data.forEach(dept => options += `<option value="${dept.id}">${dept.name}</option>`);
            $('#add-course-form select[name="department_id"]').html(options);
        }, 'json');
    });

    // Generate Report with PDF Option
    $('#report-form').submit(function(e) {
        e.preventDefault();
        let formData = $(this).serialize() + '&format=csv';
        $.post('api/admin.php?action=generate_report', formData, function(res) {
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

    // Load Audit Logs
    function loadAuditLogs() {
        $.get('api/admin.php?action=get_audit_logs', function(data) {
            let html = '';
            data.forEach(log => {
                html += `<tr>
                    <td>${log.user_name}</td>
                    <td>${log.action}</td>
                    <td>${log.timestamp}</td>
                    <td>${log.details}</td>
                </tr>`;
            });
            $('#audit-table').html(html);
        }, 'json').fail(() => Swal.fire('Error', 'Failed to load audit logs', 'error'));
    }

    // Load data on section switch
    $('.nav-link').on('click', function() {
        const section = $(this).data('section');
        if (section === 'attendance') loadAttendance();
        if (section === 'sessions') loadSessions();
        if (section === 'lecturers') loadLecturers();
        if (section === 'students') loadStudents();
        if (section === 'courses') loadCourses();
        if (section === 'audit') loadAuditLogs();
    });

    // Logout
    $('#logout').click(function() {
        $.post('api/auth.php?action=logout', function(res) {
            window.location = 'login.php';
        }, 'json');
    });
});