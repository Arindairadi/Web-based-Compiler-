<?php
// Admin config - reuse main db connection
require_once __DIR__ . '/../includes/db.php';

function requireAdmin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}
?>