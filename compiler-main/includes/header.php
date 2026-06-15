<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'CompilerHub'; ?></title>
    
    <!-- Global Styles & Scripts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500&family=Orbitron:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Global reset & base styles (shared across all pages) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a192f 0%, #112240 100%);
            color: #ccd6f6;
            line-height: 1.6;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        
        /* Header styles */
        .header {
            background: rgba(10, 25, 47, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(0, 255, 157, 0.1);
            box-shadow: 0 5px 30px rgba(0,0,0,0.3);
        }
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #00ff9d, #6c63ff);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0a192f;
            font-size: 22px;
            box-shadow: 0 0 20px #00ff9d;
        }
        .logo-text {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            background: linear-gradient(135deg, #00ff9d, #6c63ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-links {
            display: flex;
            gap: 28px;
            list-style: none;
        }
        .nav-links a {
            color: #8892b0;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            font-family: 'Orbitron', sans-serif;
        }
        .nav-links a:hover { color: #00ff9d; }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-info a, .github-btn {
            padding: 8px 16px;
            background: #00ff9d;
            color: #0a192f;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .user-info a:hover, .github-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px #00ff9d;
        }
        .github-btn i, .user-info a i { margin-right: 6px; }
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: #00ff9d;
            font-size: 1.8rem;
            cursor: pointer;
        }
        @media (max-width: 992px) {
            .nav-links { display: none; }
            .mobile-menu-btn { display: block; }
        }
        /* Mobile nav (same as original) */
        .mobile-nav {
            position: fixed;
            top: 0;
            right: -100%;
            width: 300px;
            height: 100%;
            background: rgba(10,25,47,0.98);
            backdrop-filter: blur(20px);
            z-index: 1001;
            transition: right 0.3s;
            padding: 100px 30px 30px;
            border-left: 1px solid rgba(0,255,157,0.1);
        }
        .mobile-nav.active { right: 0; }
        .mobile-nav-close {
            position: absolute;
            top: 25px;
            right: 25px;
            background: none;
            border: none;
            color: #00ff9d;
            font-size: 1.8rem;
            cursor: pointer;
        }
        .mobile-nav-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .mobile-nav-links a {
            color: #8892b0;
            text-decoration: none;
            font-size: 1.2rem;
            font-family: 'Orbitron', sans-serif;
            display: block;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0,255,157,0.1);
        }
        .mobile-nav-links a:hover { color: #00ff9d; }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            display: none;
            backdrop-filter: blur(5px);
        }
        .overlay.active { display: block; }
        /* Footer styles */
        .footer {
            background: rgba(10,25,47,0.98);
            padding: 60px 0 30px;
            border-top: 1px solid rgba(0,255,157,0.1);
            margin-top: 60px;
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 30px;
        }
        .copyright {
            text-align: center;
            padding-top: 30px;
            color: #8892b0;
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .footer-content { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="overlay" id="overlay"></div>
<div class="mobile-nav" id="mobileNav">
    <button class="mobile-nav-close" id="mobileNavClose"><i class="fas fa-times"></i></button>
    <ul class="mobile-nav-links">
        <li><a href="java.php">Java</a></li>
        <li><a href="c.php">C</a></li>
        <li><a href="swift.php">Swift</a></li>
        <li><a href="brain-fuck.php">Brainfuck</a></li>
        <li><a href="go.php">Go</a></li>
        <li><a href="index.php#features">Features</a></li>
        <li><a href="index.php#cta">Get Started</a></li>
    </ul>
    <a href="https://github.com/Agabaofficial/compiler-visualizer-hub" class="github-btn" target="_blank"><i class="fab fa-github"></i> Source Code</a>
</div>

<header class="header">
    <div class="container nav-container">
        <a href="index.php" class="logo">
            <div class="logo-icon"><i class="fas fa-bolt"></i></div>
            <div class="logo-text">CompilerHub</div>
        </a>
        <nav>
            <ul class="nav-links">
                <li><a href="java.php">Java</a></li>
                <li><a href="c.php">C</a></li>
                <li><a href="swift.php">Swift</a></li>
                <li><a href="brain-fuck.php">Brainfuck</a></li>
                <li><a href="go.php">Go</a></li>
 <li><a href="python.php">Python</a></li>
                <li><a href="index.php#features">Features</a></li>
                <li><a href="index.php#cta">Get Started</a></li>
            </ul>
        </nav>
        <div class="user-info">
            <?php if (isLoggedIn()): ?>
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
            <?php endif; ?>
        </div>
        <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    </div>
</header>

<script>
    // Mobile menu (shared)
    const menuBtn = document.getElementById('mobileMenuBtn'), mobileNav = document.getElementById('mobileNav'), closeNav = document.getElementById('mobileNavClose'), overlay = document.getElementById('overlay');
    function openNav() { mobileNav.classList.add('active'); overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeNavFunc() { mobileNav.classList.remove('active'); overlay.classList.remove('active'); document.body.style.overflow = ''; }
    menuBtn?.addEventListener('click', openNav); closeNav?.addEventListener('click', closeNavFunc); overlay?.addEventListener('click', closeNavFunc);
    document.querySelectorAll('.mobile-nav-links a').forEach(l => l.addEventListener('click', closeNavFunc));
</script>

<main class="container" style="margin-top: 100px;">