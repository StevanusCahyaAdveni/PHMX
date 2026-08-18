<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/functions/index.php';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="welcome">PHMX</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $hal == 'welcome' ? 'active fw-bold' : '' ?>" href="welcome">Welcome</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $hal == 'generate-crud' ? 'active fw-bold' : '' ?>" href="generate-crud">Generate CRUD</a>
                </li>
                <?php if(isset($_SESSION['user_id'])){?>
                    <li class="nav-item">
                        <a class="nav-link <?= $hal == 'users/user-management' ? 'active fw-bold' : '' ?>" href="users/user-management">User Management</a>
                    </li>
                <?php }?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <span class="nav-link text-light me-3">Halo, <strong><?= htmlspecialchars($_SESSION['fullname'] ?? 'User') ?></strong></span>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-outline-danger btn-sm" hx-post="?act=auth/logout" hx-target="#global-alert" hx-swap="innerHTML">Logout</button>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $hal == 'auth/login' ? 'active fw-bold' : '' ?>" href="auth/login">Login</a>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-primary btn-sm" href="auth/register">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div id="global-alert" class="container mt-2"></div>
