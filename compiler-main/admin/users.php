<?php
session_start();
require_once __DIR__ . '/config.php';
requireAdmin();

$pageTitle = 'User Management';
include 'includes/header.php';

$users = $pdo->query("SELECT id, username, email, created_at, is_admin FROM users ORDER BY created_at DESC")->fetchAll();
?>
<div class="chart-container">
    <h3><i class="fas fa-users"></i> Registered Users</h3>
    <table class="data-table">
        <thead>
            <tr><th>ID</th><th>Username</th><th>Email</th><th>Registered</th><th>Role</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo $user['created_at']; ?></td>
                <td><?php echo $user['is_admin'] ? '<span style="color:#00ff9d;"><i class="fas fa-user-shield"></i> Admin</span>' : '<span style="color:#8892b0;"><i class="fas fa-user"></i> User</span>'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>