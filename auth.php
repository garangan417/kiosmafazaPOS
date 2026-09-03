<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek status login user.
 */
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        // Jika request dari HTMX
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            header("HX-Redirect: /login.php");
            exit;
        }
        
        // Redirect ke halaman login root
        header("Location: /login.php");
        exit;
    }
}

function check_role($allowed_roles = []) {
    check_login();
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        http_response_code(403);
        die("Akses Ditolak: Anda tidak memiliki wewenang.");
    }
}