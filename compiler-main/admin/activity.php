<?php
session_start();
require_once __DIR__ . '/config.php';
requireAdmin();

$pageTitle = 'Activity Logs';
include 'includes/header.php';

// Filter & Pagination parameters
$actionFilter = $_GET['action'] ?? '';
$userFilter = $_GET['user'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = [];
$params = [];
if ($actionFilter) {
    $where[] = "a.action = ?";
    $params[] = $actionFilter;
}
if ($userFilter) {
    $where[] = "u.username LIKE ?";
    $params[] = "%$userFilter%";
}
if ($dateFrom) {
    $where[] = "DATE(a.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = "DATE(a.created_at) <= ?";
    $params[] = $dateTo;
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Valid sort columns
$allowedSort = ['a.action', 'a.created_at', 'u.username', 'a.ip_address'];
if (!in_array($sortBy, $allowedSort)) $sortBy = 'a.created_at';
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Count total
$countQuery = "SELECT COUNT(*) FROM user_activity_logs a LEFT JOIN users u ON a.user_id = u.id $whereSQL";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch activities
$query = "SELECT a.*, u.username FROM user_activity_logs a LEFT JOIN users u ON a.user_id = u.id $whereSQL ORDER BY $sortBy $sortOrder LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get distinct actions for filter
$actions = $pdo->query("SELECT DISTINCT action FROM user_activity_logs")->fetchAll(PDO::FETCH_COLUMN);
?>
<style>
    .filter-bar {
        background: rgba(17,34,64,0.5);
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .filter-group label {
        font-size: 0.8rem;
        color: #64ffda;
    }
    .filter-group select, .filter-group input {
        padding: 8px 12px;
        background: #0a192f;
        border: 1px solid rgba(100,255,218,0.2);
        border-radius: 6px;
        color: #e6f1ff;
    }
    .btn-filter {
        background: #00ff9d;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        color: #0a192f;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-reset {
        background: #8892b0;
    }
    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    .pagination a, .pagination span {
        padding: 8px 12px;
        background: rgba(17,34,64,0.7);
        border-radius: 6px;
        text-decoration: none;
        color: #ccd6f6;
        border: 1px solid rgba(0,255,157,0.2);
    }
    .pagination a:hover {
        background: #00ff9d;
        color: #0a192f;
    }
    .pagination .active {
        background: #00ff9d;
        color: #0a192f;
    }
    .btn-details {
        background: none;
        border: 1px solid #00ff9d;
        padding: 4px 8px;
        border-radius: 4px;
        color: #00ff9d;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-details:hover {
        background: #00ff9d;
        color: #0a192f;
    }
    .btn-export {
        background: #6c63ff;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        color: white;
        cursor: pointer;
        margin-right: 10px;
    }
    .sort-link {
        color: #00ff9d;
        text-decoration: none;
    }
    .sort-link i {
        font-size: 0.8rem;
    }
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        z-index: 2000;
        align-items: center;
        justify-content: center;
    }
    .modal-content {
        background: #112240;
        border-radius: 15px;
        padding: 25px;
        max-width: 600px;
        width: 90%;
        border: 1px solid #00ff9d;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(0,255,157,0.2);
        padding-bottom: 10px;
    }
    .modal-close {
        background: none;
        border: none;
        color: #ff6b6b;
        font-size: 1.5rem;
        cursor: pointer;
    }
</style>

<div class="chart-container">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <h3><i class="fas fa-history"></i> User Activity Logs</h3>
        <div>
            <button class="btn-export" onclick="exportActivity('csv')"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button class="btn-export" onclick="exportActivity('json')"><i class="fas fa-file-code"></i> Export JSON</button>
        </div>
    </div>

    <div class="filter-bar">
        <div class="filter-group">
            <label><i class="fas fa-user"></i> Username</label>
            <input type="text" id="filterUser" placeholder="Search user..." value="<?php echo htmlspecialchars($userFilter); ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-tag"></i> Action</label>
            <select id="filterAction">
                <option value="">All</option>
                <?php foreach ($actions as $act): ?>
                <option value="<?php echo $act; ?>" <?php echo $actionFilter == $act ? 'selected' : ''; ?>><?php echo ucfirst($act); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-calendar"></i> Date From</label>
            <input type="date" id="dateFrom" value="<?php echo $dateFrom; ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-calendar"></i> Date To</label>
            <input type="date" id="dateTo" value="<?php echo $dateTo; ?>">
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button class="btn-filter" onclick="applyFilters()"><i class="fas fa-filter"></i> Apply</button>
            <button class="btn-filter btn-reset" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'u.username', 'order' => ($sortBy == 'u.username' && $sortOrder == 'ASC') ? 'DESC' : 'ASC', 'page' => 1])); ?>" class="sort-link">User <?php if ($sortBy == 'u.username') echo $sortOrder == 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?></a></th>
                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'a.action', 'order' => ($sortBy == 'a.action' && $sortOrder == 'ASC') ? 'DESC' : 'ASC', 'page' => 1])); ?>" class="sort-link">Action <?php if ($sortBy == 'a.action') echo $sortOrder == 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?></a></th>
                <th>Details</th>
                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'a.ip_address', 'order' => ($sortBy == 'a.ip_address' && $sortOrder == 'ASC') ? 'DESC' : 'ASC', 'page' => 1])); ?>" class="sort-link">IP Address <?php if ($sortBy == 'a.ip_address') echo $sortOrder == 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?></a></th>
                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'a.created_at', 'order' => ($sortBy == 'a.created_at' && $sortOrder == 'ASC') ? 'DESC' : 'ASC', 'page' => 1])); ?>" class="sort-link">Date <?php if ($sortBy == 'a.created_at') echo $sortOrder == 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?></a></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activities as $act): ?>
            <tr>
                <td><?php echo $act['username'] ?? 'Guest'; ?></td>
                <td><span class="stat-title" style="font-size:0.8rem;"><?php echo $act['action']; ?></span></td>
                <td><?php echo htmlspecialchars(substr($act['details'] ?? '-', 0, 50)); ?></td>
                <td><?php echo $act['ip_address'] ?? '-'; ?></td>
                <td><?php echo $act['created_at']; ?></td>
                <td><button class="btn-details" onclick="showActivityDetails(<?php echo htmlspecialchars(json_encode($act)); ?>)"><i class="fas fa-info-circle"></i> Details</button></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($activities)): ?>
            <tr><td colspan="6" style="text-align:center;">No activities found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>"><i class="fas fa-chevron-left"></i> Previous</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
            <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next <i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for activity details -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Activity Details</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<script>
    function applyFilters() {
        const user = document.getElementById('filterUser').value;
        const action = document.getElementById('filterAction').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        let url = '?';
        if (user) url += `user=${encodeURIComponent(user)}&`;
        if (action) url += `action=${action}&`;
        if (dateFrom) url += `date_from=${dateFrom}&`;
        if (dateTo) url += `date_to=${dateTo}&`;
        url += 'page=1';
        window.location.href = url;
    }
    function resetFilters() {
        window.location.href = '?';
    }
    function showActivityDetails(act) {
        const modal = document.getElementById('detailsModal');
        const body = document.getElementById('modalBody');
        body.innerHTML = `
            <table style="width:100%; border-collapse:collapse;">
                <tr><td style="padding:8px;"><strong>User:</strong></td><td>${act.username || 'Guest'}</td></tr>
                <tr><td style="padding:8px;"><strong>Action:</strong></td><td>${act.action}</td></tr>
                <tr><td style="padding:8px;"><strong>Details:</strong></td><td>${escapeHtml(act.details || '-')}</td></tr>
                <tr><td style="padding:8px;"><strong>IP Address:</strong></td><td>${act.ip_address || '-'}</td></tr>
                <tr><td style="padding:8px;"><strong>User Agent:</strong></td><td style="word-break:break-all;">${escapeHtml(act.user_agent || '-')}</td></tr>
                <tr><td style="padding:8px;"><strong>Date:</strong></td><td>${act.created_at}</td></tr>
            </table>
        `;
        modal.style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('detailsModal').style.display = 'none';
    }
    function exportActivity(format) {
        const params = new URLSearchParams(window.location.search);
        params.set('export', format);
        window.location.href = 'export_activity.php?' + params.toString();
    }
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    window.onclick = function(event) {
        const modal = document.getElementById('detailsModal');
        if (event.target === modal) closeModal();
    }
</script>

<?php include 'includes/footer.php'; ?>