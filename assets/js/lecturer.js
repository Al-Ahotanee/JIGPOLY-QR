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

    // Theme Switcher
    $('#themeToggle').click(function() {
        $('body, .navbar, .sidebar, .card, .table-dark, footer').toggleClass('light-theme');
        localStorage.setItem('theme', $('body').hasClass('light-theme') ? 'light' : 'dark');
    });
    if (localStorage.getItem('theme') === 'light') {
        $('body, .navbar, .sidebar, .card, .table-dark, footer').addClass('light-theme');
    }

    // Load Dashboard Stats
    function loadDashboard() {
        $.get('api/lecturer.php?action=get_dashboard', function(data) {
            $('#total-sessions').text(data.total_sessions || 0);
            $('#attendance-today').text(data.attendance_today || 0);
            $('#active-courses').text(data.active_courses || 0);
        }, 'json').fail(() => console.error('Failed to load dashboard stats'));
    }
    loadDashboard();

    // Load Courses
    $.get('api/lecturer.php?action=get_courses', function(data) {
        let html = '<option value="">Select Course</option>';
        data.forEach(course => html += `<option value="${course.id}">${course.name} (${course.code})</option>`);
        $('#course-select').html(html);
    }, 'json').fail(() => Swal.fire('Error', 'Failed to load courses', 'error'));

    // Create Session with QR Timer
    let qrTimerInterval;
    function updateQrTimer(endTime) {
        clearInterval(qrTimerInterval);
        qrTimerInterval = setInterval(() => {
            let now = new Date().getTime();
            let distance = endTime - now;
            if (distance > 0) {
                let hours = Math.floor(distance / (1000 * 60 * 60));
                let minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                let seconds = Math.floor((distance % (1000 * 60)) / 1000);
                $('#qr-timer').text(`Time Left: ${hours}h ${minutes}m ${seconds}s`);
            } else {
                $('#qr-timer').text('Session Expired');
                clearInterval(qrTimerInterval);
            }
        }, 1000);
    }

    $('#session-form').submit(function(e) {
        e.preventDefault();
        let formData = $(this).serialize();
        console.log('Form data before GPS:', formData);

        navigator.geolocation.getCurrentPosition(
            function(position) {
                formData += `&latitude=${position.coords.latitude}&longitude=${position.coords.longitude}`;
                console.log('Form data with GPS:', formData);

                $.post('api/lecturer.php?action=create_session', formData, function(res) {
                    Swal.fire({
                        icon: res.status,
                        title: res.status.charAt(0).toUpperCase() + res.status.slice(1),
                        text: res.message,
                        background: 'rgba(255, 255, 255, 0.1)',
                        color: '#fff'
                    });
                    if (res.status === 'success') {
                        let endTime = new Date($('[name="end_time"]').val()).getTime();
                        $('#qr-code').html(`
                            <img src="${res.qr_code}" alt="QR Code" class="animate__animated animate__zoomIn">
                            <span class="qr-timer" id="qr-timer"></span>
                        `);
                        updateQrTimer(endTime);
                    }
                }, 'json').fail(function(xhr) {
                    console.error('Create session failed:', xhr.responseText);
                    Swal.fire('Error', 'Failed to create session', 'error');
                });
            },
            function(error) {
                Swal.fire('Error', 'Please enable GPS: ' + error.message, 'error');
            }
        );
    });

    // Load Attendance with Notifications and Export
    let attendanceData = [];
    function loadAttendance() {
        $.get('api/lecturer.php?action=get_attendance', function(data) {
            attendanceData = data;
            let html = '';
            data.forEach(record => {
                html += `<tr>
                    <td>${record.student_name}</td>
                    <td>${record.timestamp}</td>
                    <td>${record.latitude}, ${record.longitude}</td>
                    <td>${record.status}</td>
                </tr>`;
                $('#live-ticker').text(`Live Attendance: ${record.student_name} checked in at ${record.timestamp}`);
                showNotification(`New Attendance: ${record.student_name} at ${record.timestamp}`);
            });
            $('#attendance-table').html(html);
        }, 'json').fail(() => Swal.fire('Error', 'Failed to load attendance', 'error'));
    }

    function showNotification(message) {
        let notification = $(`<div class="alert alert-info animate__animated animate__fadeIn">${message}</div>`);
        $('#notification-area').prepend(notification);
        setTimeout(() => notification.removeClass('animate__fadeIn').addClass('animate__fadeOut').fadeOut(() => notification.remove()), 5000);
    }

    $('#export-attendance').click(function() {
        let csv = "Student,Time,Latitude,Longitude,Status\n";
        attendanceData.forEach(record => {
            csv += `${record.student_name},${record.timestamp},${record.latitude},${record.longitude},${record.status}\n`;
        });
        let blob = new Blob([csv], { type: 'text/csv' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'attendance.csv';
        link.click();
    });

    // Load Sessions with Pause/Resume
    function loadSessions() {
        $.get('api/lecturer.php?action=get_sessions', function(data) {
            let html = '';
            data.forEach(session => {
                let endTime = new Date(session.end_time).getTime();
                let countdown = `<span class="countdown" data-end="${endTime}"></span>`;
                let toggle = session.status === 'active' ?
                    `<button class="btn btn-sm btn-warning toggle-session" data-id="${session.id}">Pause</button>` :
                    `<button class="btn btn-sm btn-success toggle-session" data-id="${session.id}">Resume</button>`;
                html += `<tr>
                    <td>${session.course_name}</td>
                    <td>${session.start_time}</td>
                    <td>${session.end_time}</td>
                    <td>${session.status}</td>
                    <td>${countdown}</td>
                    <td>${toggle}</td>
                </tr>`;
            });
            $('#sessions-table').html(html);
            updateCountdowns();

            $('.toggle-session').click(function() {
                let sessionId = $(this).data('id');
                let action = $(this).text() === 'Pause' ? 'pause' : 'resume';
                $.post('api/lecturer.php?action=toggle_session', { session_id: sessionId, action: action }, function(res) {
                    Swal.fire(res.status, res.message, res.status);
                    loadSessions();
                }, 'json');
            });
        }, 'json').fail(() => Swal.fire('Error', 'Failed to load sessions', 'error'));
    }

    function updateCountdowns() {
        $('.countdown').each(function() {
            let endTime = $(this).data('end');
            let now = new Date().getTime();
            let distance = endTime - now;
            if (distance > 0) {
                let hours = Math.floor(distance / (1000 * 60 * 60));
                let minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                let seconds = Math.floor((distance % (1000 * 60)) / 1000);
                $(this).text(`${hours}h ${minutes}m ${seconds}s`);
            } else {
                $(this).text('Expired');
            }
        });
    }
    setInterval(updateCountdowns, 1000);

    // Load data on section switch
    $('.nav-link').on('click', function() {
        const section = $(this).data('section');
        if (section === 'attendance') loadAttendance();
        if (section === 'sessions') loadSessions();
    });

    // Logout
    $('#logout').click(function() {
        $.post('api/auth.php?action=logout', function(res) {
            window.location = 'login.php';
        }, 'json');
    });
});