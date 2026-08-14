<?php
// pages/auth/login.php
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4 fw-bold">Login PHMX</h3>
                    <div id="login-alert"></div>
                    <form hx-post="?act=auth/login" hx-target="#login-alert" hx-swap="innerHTML">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Alamat Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Login</button>
                            <a href="auth/register" class="btn btn-outline-secondary">Belum punya akun? Daftar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
