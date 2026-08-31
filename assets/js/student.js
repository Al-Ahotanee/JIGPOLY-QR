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

    // Load Dashboard Stats
    function loadDashboard() {
        $.get('api/student.php?action=get_dashboard', function(data) {
            $('#total-attendance').text(data.total_attendance || 0);
            $('#today-status').text(data.today_status || 'N/A');
            $('#courses-enrolled').text(data.courses_enrolled || 0);
        }, 'json').fail(() => console.error('Failed to load dashboard stats'));
    }
    loadDashboard();

    // QR Code Scanner
    const html5QrCode = new Html5Qrcode("qr-reader");
    let isScanning = false;

    $('#start-scan').click(function() {
        if (!isScanning) {
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    $('#qr-status').text('QR Code Scanned Successfully!');
                    handleQrScan(decodedText);
                    html5QrCode.stop().then(() => isScanning = false);
                },
                (error) => console.warn('QR scan error:', error)
            ).then(() => {
                isScanning = true;
                $(this).text('Stop Scan');
                $('#qr-status').text('Scanning with camera...');
            }).catch(err => Swal.fire('Error', 'Camera access denied: ' + err, 'error'));
        } else {
            html5QrCode.stop().then(() => {
                isScanning = false;
                $(this).text('Start Camera Scan');
                $('#qr-status').text('Camera scan stopped.');
            });
        }
    });

    // QR Code Upload
    $('#qr-upload').change(function(e) {
        const file = e.target.files[0];
        if (!file) {
            $('#qr-status').text('No file selected.');
            return;
        }

        $('#qr-status').text('Uploading QR code...');
        console.log('File selected:', file.name);

        const reader = new FileReader();
        reader.onload = function(event) {
            console.log('File loaded as Data URL');
            html5QrCode.scanFile(file, true) // Pass File object directly
                .then(decodedText => {
                    console.log('QR decoded:', decodedText);
                    $('#qr-status').text('QR Code Uploaded and Scanned Successfully!');
                    handleQrScan(decodedText);
                })
                .catch(err => {
                    console.error('QR scan failed:', err);
                    $('#qr-status').text('Failed to scan QR code.');
                    Swal.fire('Error', 'Invalid QR code: ' + err, 'error');
                });
        };
        reader.onerror = function() {
            console.error('File reading failed');
            $('#qr-status').text('Error reading the file.');
            Swal.fire('Error', 'Unable to read the uploaded file', 'error');
        };
        reader.readAsDataURL(file);
    });

    // Handle QR Scan with Location and Device Tracking
    function handleQrScan(decodedText) {
        try {
            const qrData = JSON.parse(decodedText);
            const { session_id, latitude: sessionLat, longitude: sessionLong } = qrData;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const studentLat = position.coords.latitude;
                    const studentLong = position.coords.longitude;
                    const deviceInfo = getDeviceFingerprint();

                    const distance = getDistanceFromLatLonInKm(studentLat, studentLong, sessionLat, sessionLong);
                    if (distance > 0.05) { // 50 meters
                        $('#qr-status').text('Attendance failed: Too far from session location.');
                        Swal.fire('Error', 'You are too far from the session location', 'error');
                        return;
                    }

                    $.post('api/student.php?action=mark_attendance', {
                        session_id: session_id,
                        latitude: studentLat,
                        longitude: studentLong,
                        device_info: deviceInfo
                    }, function(res) {
                        $('#qr-status').text(`Attendance: ${res.message}`);
                        Swal.fire({
                            icon: res.status,
                            title: res.status.charAt(0).toUpperCase() + res.status.slice(1),
                            text: res.message,
                            background: 'rgba(255, 255, 255, 0.1)',
                            color: '#fff'
                        });
                        loadAttendance();
                        loadDashboard();
                    }, 'json').fail((xhr) => {
                        $('#qr-status').text('Attendance failed.');
                        Swal.fire('Error', 'Failed to mark attendance: ' + xhr.responseText, 'error');
                    });
                },
                (error) => {
                    $('#qr-status').text('GPS error.');
                    Swal.fire('Error', 'Please enable GPS: ' + error.message, 'error');
                }
            );
        } catch (e) {
            $('#qr-status').text('Invalid QR code format.');
            Swal.fire('Error', 'Invalid QR code format', 'error');
        }
    }

    // Device Fingerprinting
    function getDeviceFingerprint() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = "top";
        ctx.font = "14px 'Arial'";
        ctx.fillText("fingerprint", 2, 2);
        const canvasHash = canvas.toDataURL();

        return btoa(navigator.userAgent + navigator.language + screen.width + screen.height + canvasHash);
    }

    // Haversine Formula for Distance Calculation
    function getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
        const R = 6371; // Radius of Earth in km
        const dLat = deg2rad(lat2 - lat1);
        const dLon = deg2rad(lon2 - lon1);
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }
    function deg2rad(deg) { return deg * (Math.PI / 180); }

    // Load Attendance
    function loadAttendance() {
        $.get('api/student.php?action=get_attendance', function(data) {
            let html = '';
            data.forEach(record => {
                html += `<tr>
                    <td>${record.course_name}</td>
                    <td>${record.timestamp}</td>
                    <td>${record.latitude}, ${record.longitude}</td>
                    <td>${record.status}</td>
                </tr>`;
            });
            $('#attendance-table').html(html);
        }, 'json').fail(() => Swal.fire('Error', 'Failed to load attendance', 'error'));
    }

    // Load data on section switch
    $('.nav-link').on('click', function() {
        const section = $(this).data('section');
        if (section === 'attendance') loadAttendance();
        if (section === 'scan-qr') $('#qr-status').text('Use your camera or upload a QR code to mark attendance.');
    });

    // Logout
    $('#logout').click(function() {
        $.post('api/auth.php?action=logout', function(res) {
            window.location = 'login.php';
        }, 'json');
    });
});