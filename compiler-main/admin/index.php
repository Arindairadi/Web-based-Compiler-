<?php
session_start();
require_once __DIR__ . '/config.php';
requireAdmin();

$pageTitle = 'Dashboard';
include 'includes/header.php';

// ---------- Basic Stats ----------
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalCompilations = $pdo->query("SELECT COUNT(*) FROM compilation_logs")->fetchColumn();
$totalErrors = $pdo->query("SELECT SUM(errors_count) FROM compilation_logs")->fetchColumn();
$successRate = $pdo->query("SELECT AVG(CASE WHEN success = 1 THEN 100 ELSE 0 END) FROM compilation_logs")->fetchColumn();

// Guest vs Registered compilations
$guestCompilations = $pdo->query("SELECT COUNT(*) FROM compilation_logs WHERE user_id IS NULL")->fetchColumn();
$registeredCompilations = $totalCompilations - $guestCompilations;

// Compilations per language (for pie chart)
$langStats = $pdo->query("SELECT language, COUNT(*) as count FROM compilation_logs GROUP BY language")->fetchAll();
$langLabels = array_column($langStats, 'language');
$langCounts = array_column($langStats, 'count');

// Success/Failure counts (for donut chart)
$successCount = $pdo->query("SELECT COUNT(*) FROM compilation_logs WHERE success = 1")->fetchColumn();
$failureCount = $totalCompilations - $successCount;

// Top 5 users by compilation count (excluding guests)
$topUsers = $pdo->query("
    SELECT u.username, COUNT(c.id) as compilations 
    FROM compilation_logs c 
    JOIN users u ON c.user_id = u.id 
    GROUP BY c.user_id 
    ORDER BY compilations DESC 
    LIMIT 5
")->fetchAll();

// Daily compilation trend (last 14 days)
$dailyTrend = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM compilation_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
")->fetchAll();
$trendDates = array_column($dailyTrend, 'date');
$trendCounts = array_column($dailyTrend, 'count');

// Average compilation time per language
$avgTimePerLang = $pdo->query("
    SELECT language, AVG(compilation_time_ms) as avg_time 
    FROM compilation_logs 
    GROUP BY language
")->fetchAll();
$avgLangLabels = array_column($avgTimePerLang, 'language');
$avgTimes = array_column($avgTimePerLang, 'avg_time');

// Activity summary last 7 days (by action)
$actionSummary = $pdo->query("
    SELECT action, COUNT(*) as count 
    FROM user_activity_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY action
    ORDER BY count DESC
")->fetchAll();
$actionLabels = array_column($actionSummary, 'action');
$actionCounts = array_column($actionSummary, 'count');

// Browser/OS breakdown from user_agent (simple extract)
$browserStats = $pdo->query("
    SELECT 
        CASE 
            WHEN user_agent LIKE '%Chrome%' THEN 'Chrome'
            WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
            WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari'
            WHEN user_agent LIKE '%Edge%' THEN 'Edge'
            ELSE 'Other'
        END as browser,
        COUNT(*) as count
    FROM user_activity_logs
    GROUP BY browser
    ORDER BY count DESC
")->fetchAll();
$browserLabels = array_column($browserStats, 'browser');
$browserCounts = array_column($browserStats, 'count');
?>
<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .full-width {
        grid-column: 1 / -1;
    }
    .stat-card {
        background: rgba(17,34,64,0.7);
        backdrop-filter: blur(5px);
        border-radius: 15px;
        padding: 20px;
        border: 1px solid rgba(0,255,157,0.2);
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
    .stat-sub {
        font-size: 0.8rem;
        color: #8892b0;
        margin-top: 8px;
    }
    .chart-container {
        background: rgba(17,34,64,0.5);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid rgba(0,255,157,0.1);
    }
    .chart-container h3 {
        margin-bottom: 20px;
        color: #64ffda;
    }
    canvas {
        max-height: 300px;
        width: 100%;
    }
    .top-users-table {
        width: 100%;
        margin-top: 15px;
    }
    .top-users-table td {
        padding: 8px 0;
        border-bottom: 1px solid rgba(0,255,157,0.1);
    }
    .guest-highlight {
        color: #ffa502;
    }
</style>

<!-- Stats Row -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-title"><i class="fas fa-users"></i> Total Users</div>
        <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
        <div class="stat-sub">Registered accounts</div>
    </div>
    <div class="stat-card">
        <div class="stat-title"><i class="fas fa-code"></i> Compilations</div>
        <div class="stat-number"><?php echo number_format($totalCompilations); ?></div>
        <div class="stat-sub">Guest: <?php echo number_format($guestCompilations); ?> | Registered: <?php echo number_format($registeredCompilations); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title"><i class="fas fa-exclamation-triangle"></i> Total Errors</div>
        <div class="stat-number"><?php echo number_format($totalErrors); ?></div>
        <div class="stat-sub">Avg errors per compilation: <?php echo round($totalErrors / max($totalCompilations,1), 2); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title"><i class="fas fa-chart-line"></i> Success Rate</div>
        <div class="stat-number"><?php echo round($successRate, 1); ?>%</div>
        <div class="stat-sub"><?php echo number_format($successCount); ?> successful / <?php echo number_format($failureCount); ?> failed</div>
    </div>
</div>

<!-- First row of charts -->
<div class="dashboard-grid">
    <div class="chart-container">
        <h3><i class="fas fa-chart-pie"></i> Compilations by Language</h3>
        <canvas id="langPieChart"></canvas>
    </div>
    <div class="chart-container">
        <h3><i class="fas fa-chart-pie"></i> Success vs Failure</h3>
        <canvas id="successDonutChart"></canvas>
    </div>
</div>

<!-- Second row -->
<div class="dashboard-grid">
    <div class="chart-container">
        <h3><i class="fas fa-chart-line"></i> Compilation Trend (Last 14 Days)</h3>
        <canvas id="trendChart"></canvas>
    </div>
    <div class="chart-container">
        <h3><i class="fas fa-tachometer-alt"></i> Top 5 Users by Compilations</h3>
        <table class="top-users-table">
            <?php foreach ($topUsers as $user): ?>
            <tr>
                <td><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username']); ?></td>
                <td style="text-align: right;"><span class="stat-number" style="font-size: 1.5rem;"><?php echo $user['compilations']; ?></span> compilations</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topUsers)): ?>
            <tr><td colspan="2">No registered user compilations yet</td></tr>
            <?php endif; ?>
        </table>
        <div class="stat-sub" style="margin-top: 15px;">
            <i class="fas fa-user-friends guest-highlight"></i> Guest compilations: <?php echo number_format($guestCompilations); ?>
        </div>
    </div>
</div>

<!-- Third row: Average time per language + Browser stats -->
<div class="dashboard-grid">
    <div class="chart-container">
        <h3><i class="fas fa-clock"></i> Average Compilation Time (ms) per Language</h3>
        <canvas id="avgTimeChart"></canvas>
    </div>
    <div class="chart-container">
        <h3><i class="fas fa-chart-bar"></i> Recent Activity by Action (Last 7 Days)</h3>
        <canvas id="actionChart"></canvas>
    </div>
</div>

<!-- Browser breakdown -->
<div class="chart-container full-width">
    <h3><i class="fas fa-globe"></i> Browser Usage</h3>
    <canvas id="browserChart" style="max-height: 250px;"></canvas>
</div>

<script>
    // Language Pie Chart
    const langCtx = document.getElementById('langPieChart').getContext('2d');
    new Chart(langCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($langLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($langCounts); ?>,
                backgroundColor: ['#00ff9d', '#6c63ff', '#ff6b6b', '#ffa502', '#9b59b6', '#3498db'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#ccd6f6' } } } }
    });

    // Success/Failure Donut Chart
    const successCtx = document.getElementById('successDonutChart').getContext('2d');
    new Chart(successCtx, {
        type: 'doughnut',
        data: {
            labels: ['Successful', 'Failed'],
            datasets: [{
                data: [<?php echo $successCount; ?>, <?php echo $failureCount; ?>],
                backgroundColor: ['#00ff9d', '#ff6b6b'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#ccd6f6' } } } }
    });

    // Trend Line Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trendDates); ?>,
            datasets: [{
                label: 'Compilations',
                data: <?php echo json_encode($trendCounts); ?>,
                borderColor: '#00ff9d',
                backgroundColor: 'rgba(0,255,157,0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { color: '#ccd6f6' } }, x: { ticks: { color: '#ccd6f6' } } } }
    });

    // Average Time Bar Chart
    const avgTimeCtx = document.getElementById('avgTimeChart').getContext('2d');
    new Chart(avgTimeCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($avgLangLabels); ?>,
            datasets: [{
                label: 'Average Time (ms)',
                data: <?php echo json_encode($avgTimes); ?>,
                backgroundColor: '#6c63ff',
                borderRadius: 6
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { color: '#ccd6f6' } }, x: { ticks: { color: '#ccd6f6' } } } }
    });

    // Action Bar Chart
    const actionCtx = document.getElementById('actionChart').getContext('2d');
    new Chart(actionCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($actionLabels); ?>,
            datasets: [{
                label: 'Count',
                data: <?php echo json_encode($actionCounts); ?>,
                backgroundColor: '#ffa502',
                borderRadius: 6
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { color: '#ccd6f6' } }, x: { ticks: { color: '#ccd6f6' } } } }
    });

    // Browser Pie Chart
    const browserCtx = document.getElementById('browserChart').getContext('2d');
    new Chart(browserCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($browserLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($browserCounts); ?>,
                backgroundColor: ['#00ff9d', '#6c63ff', '#ff6b6b', '#ffa502', '#9b59b6'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#ccd6f6' } } } }
    });
</script>

<?php include 'includes/footer.php'; ?>