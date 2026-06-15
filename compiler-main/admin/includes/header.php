<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Admin Panel'; ?> | CompilerHub</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a192f 0%, #112240 100%);
            color: #ccd6f6;
            min-height: 100vh;
        }
        /* Admin Navbar */
        .admin-navbar {
            background: rgba(10, 25, 47, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 255, 157, 0.2);
            padding: 15px 20px;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #00ff9d, #6c63ff);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #0a192f;
        }
        .logo-text {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1.4rem;
            background: linear-gradient(135deg, #00ff9d, #6c63ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .admin-nav-links {
            display: flex;
            gap: 25px;
            list-style: none;
        }
        .admin-nav-links a {
            color: #8892b0;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .admin-nav-links a:hover, .admin-nav-links a.active {
            color: #00ff9d;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(0,255,157,0.1);
            padding: 8px 15px;
            border-radius: 30px;
        }
        .user-info span {
            color: #00ff9d;
        }
        .logout-btn {
            background: none;
            border: none;
            color: #ff6b6b;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: 0.3s;
        }
        .logout-btn:hover {
            color: #ff4757;
        }
        /* Main container */
        .admin-container {
            margin-top: 80px;
            padding: 20px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }
        /* Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(17, 34, 64, 0.7);
            backdrop-filter: blur(5px);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(0, 255, 157, 0.2);
            transition: 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #00ff9d;
            box-shadow: 0 0 20px rgba(0,255,157,0.2);
        }
        .stat-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8892b0;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #00ff9d;
            font-family: 'Orbitron', sans-serif;
        }
        /* Tables */
        .data-table {
            width: 100%;
            background: rgba(10, 25, 47, 0.6);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(0,255,157,0.1);
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0,255,157,0.1);
        }
        .data-table th {
            background: rgba(0,255,157,0.1);
            color: #00ff9d;
            font-weight: 600;
        }
        .data-table tr:hover {
            background: rgba(0,255,157,0.05);
        }
        /* Charts */
        .chart-container {
            background: rgba(17,34,64,0.5);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(0,255,157,0.1);
        }
        canvas {
            max-height: 300px;
        }
        /* Buttons */
        .btn-admin {
            background: linear-gradient(135deg, #00ff9d, #6c63ff);
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            color: #0a192f;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px #00ff9d;
        }
        /* Login form */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0a192f, #112240);
        }
        .login-card {
            background: rgba(17,34,64,0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(0,255,157,0.3);
            box-shadow: 0 0 30px rgba(0,255,157,0.1);
        }
        .login-card h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #00ff9d;
        }
        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #64ffda;
        }
        .input-group input {
            width: 100%;
            padding: 12px;
            background: #0a192f;
            border: 1px solid rgba(100,255,218,0.2);
            border-radius: 8px;
            color: #e6f1ff;
        }
        .error-msg {
            background: rgba(255,107,107,0.2);
            border-left: 4px solid #ff6b6b;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        @media (max-width: 768px) {
            .admin-nav-links { display: none; }
            .admin-container { margin-top: 120px; }
        }
    </style>
</head>
<body>
<?php if (basename($_SERVER['PHP_SELF']) !== 'login.php'): ?>
<div class="admin-navbar">
    <div class="logo-area">
        <div class="logo-icon"><i class="fas fa-chart-line"></i></div>
        <div class="logo-text">Admin Panel</div>
    </div>
    <ul class="admin-nav-links">
        <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="languages.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'languages.php' ? 'active' : ''; ?>"><i class="fas fa-code"></i> Languages</a></li>
        <li><a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="activity.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'activity.php' ? 'active' : ''; ?>"><i class="fas fa-history"></i> Activity Logs</a></li>
    </ul>
    <div class="user-info">
        <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
        <form method="post" action="logout.php" style="display: inline;">
            <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </form>
    </div>
</div>
<?php endif; ?>
<div class="admin-container">