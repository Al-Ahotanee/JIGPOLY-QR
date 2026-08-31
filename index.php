<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Time-Based QR Code Student Attendance Management System for the JIGPOLY Polytechnictechnic.">
    <meta name="theme-color" content="#07111f">
    <title>Time-Based QR Code Student Attendance Management System | CST JIGPOLY Polytechnictechnic</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --ink: #07111f;
            --ink-soft: #10233a;
            --muted: #6c7e91;
            --paper: #f6f9fc;
            --white: #ffffff;
            --line: rgba(12, 35, 61, .10);
            --blue: #1264f5;
            --blue-dark: #0843ad;
            --cyan: #15c7d9;
            --gold: #f4c95d;
            --green: #19ad77;
            --shadow: 0 24px 70px rgba(7, 31, 57, .14);
            --shadow-sm: 0 12px 30px rgba(7, 31, 57, .08);
            --radius-lg: 30px;
            --radius-md: 20px;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--paper);
            font-family: 'DM Sans', sans-serif;
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        .container { max-width: 1180px; }
        .section-kicker {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: var(--blue);
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .section-kicker::before {
            content: '';
            width: 28px;
            height: 2px;
            background: var(--gold);
            border-radius: 10px;
        }
        .display-title, h1, h2, h3, h4, .brand-name {
            font-family: 'Manrope', sans-serif;
            letter-spacing: -.04em;
        }

        /* Header */
        .site-header {
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            padding: 18px 0;
            transition: .3s ease;
        }
        .site-header.scrolled {
            padding: 10px 0;
            background: rgba(7, 17, 31, .88);
            border-bottom: 1px solid rgba(255,255,255,.10);
            box-shadow: 0 12px 30px rgba(0,0,0,.12);
            backdrop-filter: blur(18px);
        }
        .header-shell {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--white);
        }
        .brand-mark {
            position: relative;
            display: grid;
            width: 43px;
            height: 43px;
            place-items: center;
            color: var(--white);
            background: linear-gradient(145deg, var(--cyan), var(--blue));
            border: 1px solid rgba(255,255,255,.35);
            border-radius: 13px;
            box-shadow: 0 10px 25px rgba(21,199,217,.25);
        }
        .brand-mark::after {
            position: absolute;
            right: -4px;
            bottom: -4px;
            width: 12px;
            height: 12px;
            content: '';
            background: var(--gold);
            border: 3px solid var(--ink);
            border-radius: 50%;
        }
        .brand-name { display: block; font-size: 1rem; font-weight: 800; line-height: 1.1; }
        .brand-caption { display: block; margin-top: 3px; color: rgba(255,255,255,.62); font-size: .66rem; font-weight: 600; letter-spacing: .02em; }
        .main-nav { display: flex; align-items: center; gap: 30px; }
        .main-nav a:not(.header-login) { color: rgba(255,255,255,.72); font-size: .9rem; font-weight: 600; transition: .2s ease; }
        .main-nav a:not(.header-login):hover { color: var(--white); }
        .header-actions { display: flex; align-items: center; gap: 14px; }
        .header-login { color: var(--white); font-size: .9rem; font-weight: 700; }
        .main-nav .header-login, .main-nav .header-cta { display: none; }
        .header-cta, .btn-primary-custom {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            color: var(--white);
            background: linear-gradient(135deg, var(--blue), #278cf7);
            border: 0;
            border-radius: 12px;
            box-shadow: 0 12px 25px rgba(18,100,245,.24);
            font-weight: 800;
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .header-cta { padding: 12px 17px; font-size: .82rem; }
        .btn-primary-custom { padding: 16px 22px; font-size: .94rem; }
        .header-cta:hover, .btn-primary-custom:hover { color: var(--white); background: linear-gradient(135deg, #0d56dc, #1b7bea); box-shadow: 0 16px 30px rgba(18,100,245,.32); transform: translateY(-2px); }
        .menu-toggle { display: none; padding: 7px 10px; color: var(--white); background: transparent; border: 1px solid rgba(255,255,255,.25); border-radius: 10px; font-size: 1.35rem; }

        /* Hero */
        .hero {
            position: relative;
            min-height: 790px;
            padding: 165px 0 110px;
            color: var(--white);
            background:
                radial-gradient(circle at 80% 20%, rgba(21,199,217,.17), transparent 24%),
                radial-gradient(circle at 16% 64%, rgba(18,100,245,.19), transparent 28%),
                linear-gradient(135deg, #06101d 0%, #0b1c31 56%, #103865 100%);
            overflow: hidden;
        }
        .hero::before {
            position: absolute;
            inset: 0;
            content: '';
            opacity: .31;
            background-image: linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: linear-gradient(to bottom, black, transparent 88%);
        }
        .hero::after {
            position: absolute;
            right: -120px;
            bottom: -170px;
            width: 560px;
            height: 560px;
            content: '';
            border: 1px solid rgba(21,199,217,.25);
            border-radius: 50%;
            box-shadow: 0 0 0 34px rgba(21,199,217,.035), 0 0 0 68px rgba(21,199,217,.025), 0 0 0 102px rgba(21,199,217,.02);
        }
        .hero-content { position: relative; z-index: 1; }
        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            color: #a9f3ec;
            background: rgba(21,199,217,.10);
            border: 1px solid rgba(91,231,224,.22);
            border-radius: 30px;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .live-dot { width: 7px; height: 7px; background: #60e3aa; border-radius: 50%; box-shadow: 0 0 0 5px rgba(96,227,170,.12); }
        .hero h1 { max-width: 700px; margin: 24px 0 20px; font-size: clamp(2.8rem, 5.5vw, 5.15rem); font-weight: 800; line-height: 1.03; }
        .hero h1 .accent { color: #6be4e4; }
        .hero-copy { max-width: 590px; margin-bottom: 30px; color: rgba(255,255,255,.70); font-size: 1.06rem; line-height: 1.75; }
        .hero-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; }
        .btn-secondary-custom {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 15px 19px;
            color: var(--white);
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.20);
            border-radius: 12px;
            font-size: .94rem;
            font-weight: 800;
            transition: .2s ease;
        }
        .btn-secondary-custom:hover { color: var(--white); background: rgba(255,255,255,.14); transform: translateY(-2px); }
        .hero-proof { display: flex; flex-wrap: wrap; align-items: center; gap: 18px; margin-top: 31px; color: rgba(255,255,255,.56); font-size: .78rem; font-weight: 600; }
        .proof-item { display: inline-flex; align-items: center; gap: 7px; }
        .proof-item i { color: var(--gold); font-size: 1rem; }
        .hero-visual { position: relative; z-index: 1; }
        .dashboard-window {
            position: relative;
            width: min(100%, 485px);
            margin: 0 auto;
            padding: 16px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.20);
            border-radius: 25px;
            box-shadow: 0 35px 90px rgba(0,0,0,.30), inset 0 1px 0 rgba(255,255,255,.16);
            backdrop-filter: blur(15px);
            transform: rotate(1.5deg);
        }
        .dashboard-window::before { position: absolute; inset: -12px; z-index: -1; content: ''; border: 1px solid rgba(104,227,224,.12); border-radius: 31px; transform: rotate(-4deg); }
        .window-bar { display: flex; align-items: center; justify-content: space-between; padding: 2px 5px 14px; }
        .window-dots { display: flex; gap: 5px; }
        .window-dots span { width: 7px; height: 7px; background: rgba(255,255,255,.48); border-radius: 50%; }
        .window-label { color: rgba(255,255,255,.55); font-size: .66rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .dashboard-body { padding: 20px; background: #f7faff; border-radius: 16px; }
        .dashboard-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .dashboard-top small { color: var(--muted); font-size: .67rem; font-weight: 700; }
        .dashboard-top h3 { margin: 5px 0 0; color: var(--ink); font-size: 1.12rem; }
        .status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 7px 9px; color: #08704c; background: #e1f8ed; border-radius: 8px; font-size: .64rem; font-weight: 800; }
        .status-pill::before { width: 6px; height: 6px; content: ''; background: var(--green); border-radius: 50%; }
        .metric-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; margin: 21px 0; }
        .metric { padding: 12px; background: var(--white); border: 1px solid var(--line); border-radius: 12px; }
        .metric span { display: block; color: var(--muted); font-size: .60rem; font-weight: 700; }
        .metric strong { display: block; margin-top: 4px; color: var(--ink); font-family: 'Manrope', sans-serif; font-size: 1.17rem; }
        .metric strong em { color: var(--green); font-size: .58rem; font-style: normal; }
        .dashboard-card { display: flex; align-items: center; gap: 16px; padding: 15px; background: linear-gradient(135deg, #e8f5ff, #eefcfb); border: 1px solid #d8edf6; border-radius: 15px; }
        .qr-visual { display: grid; width: 96px; height: 96px; flex: 0 0 auto; place-items: center; background: var(--white); border: 7px solid var(--white); border-radius: 10px; box-shadow: 0 10px 18px rgba(11, 69, 111, .10); }
        .qr-grid { display: grid; width: 74px; height: 74px; grid-template-columns: repeat(9, 1fr); gap: 3px; padding: 5px; background: var(--ink); }
        .qr-grid span { background: #f7faff; }
        .qr-grid span:nth-child(3n), .qr-grid span:nth-child(5n+1), .qr-grid span:nth-child(11), .qr-grid span:nth-child(23), .qr-grid span:nth-child(47), .qr-grid span:nth-child(59) { background: var(--ink); }
        .scan-copy small { display: block; color: var(--blue); font-size: .65rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .scan-copy h4 { margin: 5px 0; color: var(--ink); font-size: .95rem; }
        .scan-copy p { margin: 0; color: var(--muted); font-size: .68rem; line-height: 1.45; }
        .hero-bottom { position: absolute; right: 0; bottom: 0; left: 0; z-index: 1; padding: 17px 0; background: rgba(3,13,25,.28); border-top: 1px solid rgba(255,255,255,.09); }
        .hero-bottom-inner { display: flex; align-items: center; justify-content: space-between; gap: 20px; color: rgba(255,255,255,.55); font-size: .73rem; font-weight: 600; }
        .hero-bottom strong { color: rgba(255,255,255,.88); }

        /* Sections */
        .trust-strip { padding: 23px 0; background: var(--white); border-bottom: 1px solid var(--line); }
        .trust-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
        .trust-label { color: var(--muted); font-size: .74rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .trust-points { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 24px; }
        .trust-point { display: inline-flex; align-items: center; gap: 7px; color: var(--ink-soft); font-size: .82rem; font-weight: 700; }
        .trust-point i { color: var(--green); font-size: 1rem; }
        .section-padding { padding: 105px 0; }
        .section-heading { max-width: 650px; margin-bottom: 48px; }
        .section-heading h2 { margin: 16px 0 13px; font-size: clamp(2.1rem, 4vw, 3.4rem); font-weight: 800; line-height: 1.1; }
        .section-heading p { margin: 0; color: var(--muted); font-size: 1rem; line-height: 1.75; }
        .feature-card { height: 100%; padding: 27px; background: var(--white); border: 1px solid var(--line); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); transition: .25s ease; }
        .feature-card:hover { border-color: rgba(18,100,245,.22); box-shadow: var(--shadow); transform: translateY(-6px); }
        .feature-icon { display: grid; width: 48px; height: 48px; margin-bottom: 22px; place-items: center; color: var(--blue); background: #e9f1ff; border-radius: 14px; font-size: 1.35rem; }
        .feature-card:nth-child(2) .feature-icon { color: #0a8f95; background: #e4f9f6; }
        .feature-card:nth-child(3) .feature-icon { color: #ac7600; background: #fff6da; }
        .feature-card h3 { margin-bottom: 10px; font-size: 1.16rem; font-weight: 800; }
        .feature-card p { margin: 0; color: var(--muted); font-size: .9rem; line-height: 1.7; }
        .feature-link { display: inline-flex; align-items: center; gap: 7px; margin-top: 21px; color: var(--blue); font-size: .8rem; font-weight: 800; }
        .process-section { color: var(--white); background: var(--ink); }
        .process-section .section-heading p { color: rgba(255,255,255,.59); }
        .process-grid { position: relative; }
        .process-grid::before { position: absolute; top: 34px; right: 15%; left: 15%; content: ''; border-top: 1px dashed rgba(255,255,255,.20); }
        .process-card { position: relative; z-index: 1; height: 100%; padding: 26px 20px; text-align: center; background: rgba(255,255,255,.055); border: 1px solid rgba(255,255,255,.12); border-radius: 18px; }
        .process-number { display: grid; width: 70px; height: 70px; margin: 0 auto 21px; place-items: center; color: var(--ink); background: linear-gradient(145deg, #6be4e4, #f4c95d); border: 7px solid var(--ink); border-radius: 50%; box-shadow: 0 0 0 1px rgba(255,255,255,.18); font-family: 'Manrope', sans-serif; font-size: 1.35rem; font-weight: 800; }
        .process-card h3 { margin-bottom: 10px; font-size: 1.05rem; }
        .process-card p { margin: 0; color: rgba(255,255,255,.58); font-size: .84rem; line-height: 1.65; }
        .about-panel { padding: 38px; background: var(--white); border: 1px solid var(--line); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
        .about-panel h2 { margin: 16px 0; font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; }
        .about-panel p { max-width: 650px; color: var(--muted); line-height: 1.8; }
        .programme-list { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 23px; }
        .programme-pill { display: inline-flex; align-items: center; gap: 7px; padding: 10px 13px; color: var(--ink-soft); background: #f1f6fb; border-radius: 10px; font-size: .78rem; font-weight: 800; }
        .programme-pill i { color: var(--blue); }
        .cta-panel { position: relative; padding: 55px; overflow: hidden; color: var(--white); background: linear-gradient(125deg, #0a3d93, #1475e9 52%, #18b4c4); border-radius: var(--radius-lg); box-shadow: 0 25px 55px rgba(18,100,245,.24); }
        .cta-panel::after { position: absolute; right: -80px; bottom: -135px; width: 370px; height: 370px; content: ''; border: 1px solid rgba(255,255,255,.20); border-radius: 50%; box-shadow: 0 0 0 32px rgba(255,255,255,.05), 0 0 0 64px rgba(255,255,255,.035); }
        .cta-panel h2 { position: relative; z-index: 1; max-width: 650px; margin: 0 0 12px; font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 800; }
        .cta-panel p { position: relative; z-index: 1; max-width: 570px; margin-bottom: 24px; color: rgba(255,255,255,.75); line-height: 1.7; }
        .cta-panel .btn-light-custom { position: relative; z-index: 1; display: inline-flex; align-items: center; gap: 8px; padding: 14px 19px; color: var(--blue-dark); background: var(--white); border-radius: 11px; font-size: .88rem; font-weight: 800; transition: .2s ease; }
        .btn-light-custom:hover { color: var(--blue-dark); transform: translateY(-2px); box-shadow: 0 12px 25px rgba(0,0,0,.14); }

        /* Footer */
        .site-footer { padding: 62px 0 24px; color: rgba(255,255,255,.68); background: #06101d; }
        .footer-top { display: flex; justify-content: space-between; gap: 55px; padding-bottom: 47px; }
        .footer-brand-copy { max-width: 340px; margin-top: 16px; color: rgba(255,255,255,.52); font-size: .83rem; line-height: 1.75; }
        .footer-links { display: grid; grid-template-columns: repeat(2, minmax(110px, 1fr)); gap: 60px; }
        .footer-links h4 { margin-bottom: 17px; color: var(--white); font-size: .83rem; letter-spacing: .02em; }
        .footer-links a { display: block; margin-bottom: 11px; color: rgba(255,255,255,.55); font-size: .78rem; transition: .2s ease; }
        .footer-links a:hover { color: var(--cyan); }
        .footer-bottom { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding-top: 22px; border-top: 1px solid rgba(255,255,255,.10); font-size: .74rem; }
        .footer-bottom p { margin: 0; }
        .footer-status { display: inline-flex; align-items: center; gap: 8px; }
        .footer-status i { color: #58df9e; font-size: .65rem; }

        @media (max-width: 991px) {
            .main-nav { gap: 17px; }
            .main-nav a:not(.header-login) { font-size: .82rem; }
            .hero { min-height: auto; padding-top: 145px; padding-bottom: 140px; }
            .hero-visual { margin-top: 65px; }
            .trust-row { align-items: flex-start; flex-direction: column; }
            .trust-points { justify-content: flex-start; }
            .footer-top { flex-direction: column; }
        }
        @media (max-width: 767px) {
            .site-header { padding: 13px 0; }
            .brand-caption { display: none; }
            .brand-name { font-size: .84rem; }
            .brand-mark { width: 38px; height: 38px; border-radius: 11px; }
            .header-actions { gap: 9px; }
            .header-login { display: none; }
            .menu-toggle { display: inline-flex; }
            .main-nav { position: absolute; top: 70px; right: 12px; left: 12px; display: none; flex-direction: column; align-items: stretch; gap: 0; padding: 12px; background: rgba(7,17,31,.97); border: 1px solid rgba(255,255,255,.13); border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,.23); }
            .main-nav.open { display: flex; }
            .main-nav a:not(.header-login) { padding: 12px 10px; font-size: .88rem; }
            .main-nav .header-login { display: block; margin-top: 4px; padding: 12px 10px; text-align: center; }
            .main-nav .header-cta { display: inline-flex; margin-top: 5px; }
            .hero { padding: 126px 0 110px; }
            .hero h1 { margin-top: 19px; font-size: clamp(2.55rem, 14vw, 4rem); }
            .hero-copy { font-size: .96rem; }
            .hero-bottom-inner { align-items: flex-start; flex-direction: column; gap: 7px; }
            .dashboard-window { transform: none; }
            .dashboard-body { padding: 14px; }
            .dashboard-card { align-items: flex-start; flex-direction: column; }
            .trust-points { display: grid; grid-template-columns: repeat(2, 1fr); width: 100%; gap: 14px; }
            .section-padding { padding: 75px 0; }
            .section-heading { margin-bottom: 32px; }
            .process-grid::before { display: none; }
            .process-card { margin-bottom: 14px; }
            .about-panel, .cta-panel { padding: 28px 22px; }
            .footer-links { gap: 35px; }
            .footer-bottom { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
    <a class="visually-hidden-focusable" href="#main-content">Skip to content</a>

    <header class="site-header" id="site-header">
        <div class="container">
            <div class="header-shell">
                <a class="brand" href="#home" aria-label="CST JIGPOLY Polytechnictechnic home">
                    <span class="brand-mark"><i class="bi bi-qr-code-scan"></i></span>
                    <span>
                        <span class="brand-name">JIGPOLY Polytechnic Poly</span>
                        <span class="brand-caption">College of Science &amp; Technology</span>
                    </span>
                </a>

                <nav class="main-nav" id="main-nav" aria-label="Main navigation">
                    <a href="#features">Capabilities</a>
                    <a href="#how-it-works">How it works</a>
                    <a href="#about">Programmes</a>
                    <a class="header-login" href="login.php">Sign in</a>
                    <a class="header-cta" href="login.php">Open attendance portal <i class="bi bi-arrow-up-right"></i></a>
                </nav>

                <div class="header-actions">
                    <a class="header-login d-none d-md-inline" href="login.php">Sign in</a>
                    <a class="header-cta d-none d-md-inline" href="login.php">Open portal <i class="bi bi-arrow-up-right"></i></a>
                    <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false"><i class="bi bi-list"></i></button>
                </div>
            </div>
        </div>
    </header>

    <main id="main-content">
        <section class="hero" id="home">
            <div class="container hero-content">
                <div class="row align-items-center g-5">
                    <div class="col-lg-7" data-aos="fade-up">
                        <span class="hero-eyebrow"><span class="live-dot"></span> Smart attendance infrastructure</span>
                        <h1>Attendance that moves at the <span class="accent">speed of learning.</span></h1>
                        <p class="hero-copy">The Time-Based QR Code Student Attendance Management System makes every class check-in fast, location-aware, and accountable—from the first scan to the final report.</p>
                        <div class="hero-actions">
                            <a class="btn-primary-custom" href="login.php">Access the attendance portal <i class="bi bi-arrow-right"></i></a>
                            <a class="btn-secondary-custom" href="#how-it-works"><i class="bi bi-play-circle"></i> See how it works</a>
                        </div>
                        <div class="hero-proof">
                            <span class="proof-item"><i class="bi bi-clock-history"></i> Time-bound sessions</span>
                            <span class="proof-item"><i class="bi bi-geo-alt"></i> GPS-aware verification</span>
                            <span class="proof-item"><i class="bi bi-bar-chart-line"></i> Clear reporting</span>
                        </div>
                    </div>
                    <div class="col-lg-5" data-aos="fade-left" data-aos-delay="150">
                        <div class="hero-visual">
                            <div class="dashboard-window" aria-label="Attendance dashboard preview">
                                <div class="window-bar">
                                    <div class="window-dots"><span></span><span></span><span></span></div>
                                    <div class="window-label">Attendance command centre</div>
                                    <i class="bi bi-three-dots" style="color:rgba(255,255,255,.55)"></i>
                                </div>
                                <div class="dashboard-body">
                                    <div class="dashboard-top">
                                        <div><small>Today’s overview</small><h3>Good morning, lecturer</h3></div>
                                        <span class="status-pill">System online</span>
                                    </div>
                                    <div class="metric-row">
                                        <div class="metric"><span>Active sessions</span><strong>04</strong></div>
                                        <div class="metric"><span>Students present</span><strong>186 <em>+12%</em></strong></div>
                                        <div class="metric"><span>Attendance rate</span><strong>94.8%</strong></div>
                                    </div>
                                    <div class="dashboard-card">
                                        <div class="qr-visual">
                                            <div class="qr-grid" aria-hidden="true">
                                                <?php for ($i = 1; $i <= 81; $i++): ?><span></span><?php endfor; ?>
                                            </div>
                                        </div>
                                        <div class="scan-copy"><small>Live session · CST 204</small><h4>Foundations of Computing</h4><p>QR check-in window closes in <strong>18:42</strong><br>Location verification is active.</p></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="hero-bottom">
                <div class="container hero-bottom-inner"><span>Built for the <strong>JIGPOLY Polytechnic</strong></span><span>Supporting <strong>OND · ND · HND</strong> programmes</span></div>
            </div>
        </section>

        <section class="trust-strip" aria-label="Platform highlights">
            <div class="container">
                <div class="trust-row">
                    <span class="trust-label">Designed for confident administration</span>
                    <div class="trust-points">
                        <span class="trust-point"><i class="bi bi-check-circle-fill"></i> Less manual entry</span>
                        <span class="trust-point"><i class="bi bi-check-circle-fill"></i> Faster classroom check-in</span>
                        <span class="trust-point"><i class="bi bi-check-circle-fill"></i> Actionable records</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-padding" id="features">
            <div class="container">
                <div class="section-heading" data-aos="fade-up">
                    <span class="section-kicker">One reliable source of truth</span>
                    <h2>Everything you need to make attendance effortless.</h2>
                    <p>A clear, modern workflow for lecturers, students, and administrators—purpose-built around the rhythm of real classes.</p>
                </div>
                <div class="row g-4">
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="50"><article class="feature-card"><div class="feature-icon"><i class="bi bi-qr-code-scan"></i></div><h3>Time-based QR sessions</h3><p>Generate a focused QR code for each class session. Attendance stays open only for the window you define.</p><a class="feature-link" href="#how-it-works">Explore workflow <i class="bi bi-arrow-right"></i></a></article></div>
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="100"><article class="feature-card"><div class="feature-icon"><i class="bi bi-geo-alt"></i></div><h3>Location-aware check-in</h3><p>Pair the QR scan with GPS verification to help confirm that students are attending from the right place.</p><a class="feature-link" href="#how-it-works">Explore workflow <i class="bi bi-arrow-right"></i></a></article></div>
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="150"><article class="feature-card"><div class="feature-icon"><i class="bi bi-graph-up-arrow"></i></div><h3>Useful, clear reporting</h3><p>Give academic teams a practical view of sessions, records, trends, and student attendance performance.</p><a class="feature-link" href="#how-it-works">Explore workflow <i class="bi bi-arrow-right"></i></a></article></div>
                </div>
            </div>
        </section>

        <section class="section-padding process-section" id="how-it-works">
            <div class="container">
                <div class="section-heading" data-aos="fade-up">
                    <span class="section-kicker" style="color:#6be4e4">Simple by design</span>
                    <h2>From session start to verified record.</h2>
                    <p>Three intentional steps keep the classroom moving while giving administrators the visibility they need.</p>
                </div>
                <div class="row g-3 process-grid">
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="50"><article class="process-card"><div class="process-number">01</div><h3>Set the session</h3><p>The lecturer selects a course, sets a time window, and creates a session-specific QR code.</p></article></div>
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="100"><article class="process-card"><div class="process-number">02</div><h3>Scan and verify</h3><p>The student scans the code and shares their location while the session is active.</p></article></div>
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="150"><article class="process-card"><div class="process-number">03</div><h3>Review the record</h3><p>Attendance is captured for the session and made available for monitoring and reporting.</p></article></div>
                </div>
            </div>
        </section>

        <section class="section-padding" id="about">
            <div class="container">
                <div class="about-panel" data-aos="fade-up">
                    <span class="section-kicker">Built for CST JIGPOLY Polytechnictechnic</span>
                    <div class="row align-items-end g-4">
                        <div class="col-lg-8"><h2>A more dependable attendance culture starts here.</h2><p>The JIGPOLY Polytechnictechnic, can give every class a consistent digital attendance experience—one that respects teaching time, improves record quality, and supports informed academic decisions.</p><div class="programme-list"><span class="programme-pill"><i class="bi bi-mortarboard-fill"></i> OND programmes</span><span class="programme-pill"><i class="bi bi-mortarboard-fill"></i> ND programmes</span><span class="programme-pill"><i class="bi bi-mortarboard-fill"></i> HND programmes</span></div></div>
                        <div class="col-lg-4 text-lg-end"><a class="btn-primary-custom" href="login.php">Enter the portal <i class="bi bi-arrow-up-right"></i></a></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="pb-5">
            <div class="container"><div class="cta-panel" data-aos="zoom-in"><h2>Ready to make every class count?</h2><p>Sign in to manage a session, scan a class QR code, or review attendance records for your programme.</p><a class="btn-light-custom" href="login.php">Go to the attendance portal <i class="bi bi-arrow-right"></i></a></div></div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-top">
                <div>
                    <a class="brand" href="#home"><span class="brand-mark"><i class="bi bi-qr-code-scan"></i></span><span><span class="brand-name">JIGPOLY Polytechnic Poly</span><span class="brand-caption">College of Science &amp; Technology</span></span></a>
                    <p class="footer-brand-copy">Time-Based QR Code Student Attendance Management System for a more connected, accountable learning environment.</p>
                </div>
                <div class="footer-links">
                    <div><h4>Explore</h4><a href="#features">Capabilities</a><a href="#how-it-works">How it works</a><a href="#about">Programmes</a></div>
                    <div><h4>Access</h4><a href="login.php">Student login</a><a href="login.php">Lecturer login</a><a href="login.php">Administrator login</a></div>
                </div>
            </div>
            <div class="footer-bottom"><p>© <span id="current-year">2026</span> JIGPOLY Polytechnictechnic. All rights reserved.</p><p class="footer-status"><i class="bi bi-circle-fill"></i> Attendance platform ready</p></div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 750, once: true, offset: 50 });

        const header = document.getElementById('site-header');
        const nav = document.getElementById('main-nav');
        const menuToggle = document.getElementById('menu-toggle');
        const updateHeader = () => header.classList.toggle('scrolled', window.scrollY > 24);
        updateHeader();
        window.addEventListener('scroll', updateHeader, { passive: true });

        menuToggle.addEventListener('click', () => {
            const isOpen = nav.classList.toggle('open');
            menuToggle.setAttribute('aria-expanded', String(isOpen));
            menuToggle.innerHTML = isOpen ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-list"></i>';
        });
        nav.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
            nav.classList.remove('open');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.innerHTML = '<i class="bi bi-list"></i>';
        }));
        document.getElementById('current-year').textContent = new Date().getFullYear();
    </script>
</body>
</html>
