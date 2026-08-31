 <!-- login.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JIGPOLY Polytechnic Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0a0a23, #1a1a3d);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
        }
        .login-container {
            position: relative;
            z-index: 1;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(15px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
        }
        .login-card h2 {
            text-shadow: 0 0 10px #00d4ff;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            border-radius: 10px;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 10px #00d4ff;
        }
        .btn-primary {
            border-radius: 50px;
            padding: 12px;
            background: linear-gradient(45deg, #00d4ff, #007bff);
        }
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100vh;
            z-index: 0;
        }
    </style>
</head>
<body>
    <!-- Particles.js Background -->
    <div id="particles-js"></div>

    <!-- Login Form -->
    <div class="login-container">
        <div class="login-card animate__animated animate__fadeInUp">
            <h2 class="text-center text-white mb-4">Welcome Back</h2>
            <form id="login-form">
                <div class="mb-3">
                    <input type="email" class="form-control" name="email" placeholder="Email" required>
                </div>
                <div class="mb-4">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            <p class="text-center text-white mt-3">JIGPOLY Polytechnic Attendance System</p>
            <p class="text-center"><a href="index.php" class="text-light">Back to Home</a></p>
        </div>
    </div>

 <!-- Replace only the <script> section in login.php -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Particles.js
    particlesJS('particles-js', {
        particles: {
            number: { value: 80, density: { enable: true, value_area: 800 } },
            color: { value: '#00d4ff' },
            shape: { type: 'circle' },
            opacity: { value: 0.5, random: true },
            size: { value: 3, random: true },
            move: { enable: true, speed: 2, direction: 'none', random: true }
        },
        interactivity: {
            events: { onhover: { enable: true, mode: 'repulse' } }
        }
    });

    // Login Form Submission with Debugging
    $(document).ready(function() {
        console.log('jQuery loaded and document ready');

        $('#login-form').on('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');

            let formData = $(this).serialize();
            console.log('Form data:', formData);

            $.ajax({
                url: 'api/auth.php?action=login',
                type: 'POST',
                data: formData,
                dataType: 'json', // Tell jQuery to expect JSON and parse it automatically
                success: function(res) {
                    console.log('Response:', res); // Log the parsed response directly

                    Swal.fire({
                        icon: res.status,
                        title: res.status.charAt(0).toUpperCase() + res.status.slice(1),
                        text: res.message,
                        background: 'rgba(255, 255, 255, 0.1)',
                        color: '#fff',
                        backdrop: 'rgba(0, 0, 0, 0.8)'
                    }).then(() => {
                        if (res.status === 'success') {
                            console.log('Redirecting to:', res.redirect);
                            window.location = res.redirect;
                        }
                    });
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    console.log('Raw response text:', xhr.responseText); // Log raw response on error
                    Swal.fire('Error', 'Server error: ' + (xhr.responseText || 'Unknown error'), 'error');
                }
            });
        });
    });
</script>