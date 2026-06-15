<?php
session_start();
require_once __DIR__ . '/config.php';
requireAdmin();

$pageTitle = 'Language Statistics';
include 'includes/header.php';

// Filter & Pagination parameters
$langFilter = $_GET['language'] ?? '';
$successFilter = isset($_GET['success']) ? (int)$_GET['success'] : -1;
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = [];
$params = [];
if ($langFilter) {
    $where[] = "language = ?";
    $params[] = $langFilter;
}
if ($successFilter !== -1) {
    $where[] = "success = ?";
    $params[] = $successFilter;
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Valid sort columns (whitelist)
$allowedSort = ['language', 'created_at', 'success', 'errors_count', 'compilation_time_ms'];
if (!in_array($sortBy, $allowedSort)) $sortBy = 'created_at';
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Fetch total count for pagination
$countQuery = "SELECT COUNT(*) FROM compilation_logs $whereSQL";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch compilations
$query = "SELECT * FROM compilation_logs $whereSQL ORDER BY $sortBy $sortOrder LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$compilations = $stmt->fetchAll();

// Get distinct languages for filter dropdown
$languages = $pdo->query("SELECT DISTINCT language FROM compilation_logs")->fetchAll(PDO::FETCH_COLUMN);

// Language summary stats (same as before)
$langDetails = $pdo->query("
    SELECT 
        language,
        COUNT(*) as total_compilations,
        SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
        SUM(errors_count) as total_errors,
        AVG(compilation_time_ms) as avg_time_ms
    FROM compilation_logs
    GROUP BY language
    ORDER BY total_compilations DESC
")->fetchAll();
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
    /* Modal styles */
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
        max-width: 800px;
        width: 90%;
        max-height: 80%;
        overflow-y: auto;
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
    .sort-link {
        color: #00ff9d;
        text-decoration: none;
    }
    .sort-link i {
        font-size: 0.8rem;
    }
</style>

<div class="stats-grid">
    <?php foreach ($langDetails as $lang): ?>
    <div class="stat-card">
        <div class="stat-title"><i class="fas fa-code"></i> <?php echo strtoupper($lang['language']); ?></div>
        <div class="stat-number"><?php echo $lang['total_compilations']; ?></div>
        <div style="font-size:0.8rem; margin-top:10px;">
            <div><i class="fas fa-check-circle" style="color:#00ff9d;"></i> Success: <?php echo $lang['successful']; ?></div>
            <div><i class="fas fa-times-circle" style="color:#ff6b6b;"></i> Errors: <?php echo $lang['total_errors']; ?></div>
            <div><i class="fas fa-clock"></i> Avg time: <?php echo round($lang['avg_time_ms']); ?> ms</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="chart-container">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <h3><i class="fas fa-table"></i> Compilation Logs</h3>
        <div>
            <button class="btn-export" onclick="exportData('csv')"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button class="btn-export" onclick="exportData('json')"><i class="fas fa-file-code"></i> Export JSON</button>
        </div>
    </div>

    <div class="filter-bar">
        <div class="filter-group">
            <label><i class="fas fa-code"></i> Language</label>
            <select name="language" id="filterLang">
                <option value="">All</option>
                <?php foreach ($languages as $lang): ?>
                <option value="<?php echo $lang; ?>" <?php echo $langFilter == $lang ? 'selected' : ''; ?>><?php echo strtoupper($lang); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-check-circle"></i> Status</label>
            <select name="success" id="filterSuccess">
                <option value="-1">All</option>
                <option value="1" <?php echo $successFilter === 1 ? 'selected' : ''; ?>>Success</option>
                <option value="0" <?php echo $successFilter === 0 ? 'selected' : ''; ?>>Failed</option>
            </select>
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
                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'language', 'order' => ($sortBy == 'language' && $sortOrder == 'ASC') ? 'DESC' : 'ASC', 'page' => 1])); ?>" class="sort-link">Language <?php if ($sortBy == 'language') echo $sortOrder == 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?></a></th>
                <th>Session ID</th>
                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => ($sortBy == 'created_at' && $sortOrder == 'ASC') ? 'DESC' : 'ASC', 'page' => 1])); ?>" class="sort-link">Date <?php if ($sortBy == 'created_at') echo $sortOrder == 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?></a></th>
                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'success', 'order' => ($sortBy == 'success' && $sortOrder == 'ASC') ? 'DESC' : 'ASC', 'page' => 1])); ?>" class="sort-link">Status <?php if ($sortBy == 'success') echo $sortOrder == 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?></a></th>
                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'errors_count', 'order' => ($sortBy == 'errors_count' && $sortOrder == 'ASC') ? 'DESC' : 'ASC', 'page' => 1])); ?>" class="sort-link">Errors <?php if ($sortBy == 'errors_count') echo $sortOrder == 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?></a></th>
                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'compilation_time_ms', 'order' => ($sortBy == 'compilation_time_ms' && $sortOrder == 'ASC') ? 'DESC' : 'ASC', 'page' => 1])); ?>" class="sort-link">Time (ms) <?php if ($sortBy == 'compilation_time_ms') echo $sortOrder == 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?></a></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($compilations as $comp): ?>
            <tr>
                <td><?php echo strtoupper($comp['language']); ?></td>
                <td><?php echo substr($comp['session_id'], 0, 15); ?>...</td>
                <td><?php echo $comp['created_at']; ?></td>
                <td><?php echo $comp['success'] ? '<span style="color:#00ff9d;"><i class="fas fa-check-circle"></i> Success</span>' : '<span style="color:#ff6b6b;"><i class="fas fa-times-circle"></i> Failed</span>'; ?></td>
                <td><?php echo $comp['errors_count']; ?></td>
                <td><?php echo $comp['compilation_time_ms']; ?></td>
                <td><button class="btn-details" onclick="showDetails(<?php echo htmlspecialchars(json_encode($comp)); ?>)"><i class="fas fa-info-circle"></i> Details</button></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($compilations)): ?>
            <tr><td colspan="7" style="text-align:center;">No compilations found</td></tr>
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

<!-- Modal for details -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Compilation Details</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<script>
    function applyFilters() {
        const lang = document.getElementById('filterLang').value;
        const success = document.getElementById('filterSuccess').value;
        let url = '?';
        if (lang) url += `language=${lang}&`;
        if (success !== '-1') url += `success=${success}&`;
        url += 'page=1';
        window.location.href = url;
    }
    function resetFilters() {
        window.location.href = '?';
    }
    function showDetails(comp) {
        const modal = document.getElementById('detailsModal');
        const body = document.getElementById('modalBody');
        body.innerHTML = `
            <table style="width:100%; border-collapse:collapse;">
                <tr><td style="padding:8px;"><strong>Session ID:</strong></td><td>${comp.session_id}</td></tr>
                <tr><td style="padding:8px;"><strong>Language:</strong></td><td>${comp.language}</td></tr>
                <tr><td style="padding:8px;"><strong>Date:</strong></td><td>${comp.created_at}</td></tr>
                <tr><td style="padding:8px;"><strong>Success:</strong></td><td>${comp.success ? 'Yes' : 'No'}</td></tr>
                <tr><td style="padding:8px;"><strong>Errors Count:</strong></td><td>${comp.errors_count}</td></tr>
                <tr><td style="padding:8px;"><strong>Compilation Time:</strong></td><td>${comp.compilation_time_ms} ms</td></tr>
                <tr><td style="padding:8px;"><strong>Source Code Preview:</strong></td><td><pre style="background:#0a192f; padding:10px; border-radius:6px; overflow-x:auto;">${escapeHtml(comp.source_code_preview || 'N/A')}</pre></td></tr>
                <tr><td style="padding:8px;"><strong>IP Address:</strong></td><td>${comp.ip_address || 'N/A'}</td></tr>
                <tr><td style="padding:8px;"><strong>User Agent:</strong></td><td style="word-break:break-all;">${comp.user_agent || 'N/A'}</td></tr>
            </table>
        `;
        modal.style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('detailsModal').style.display = 'none';
    }
    function exportData(format) {
        const params = new URLSearchParams(window.location.search);
        params.set('export', format);
        window.location.href = 'export_compilations.php?' + params.toString();
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
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('detailsModal');
        if (event.target === modal) closeModal();
    }
</script>

<?php include 'includes/footer.php'; ?>