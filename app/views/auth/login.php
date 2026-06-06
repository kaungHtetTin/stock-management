<?php
$pageTitle = 'Login — ' . APP_NAME;
$bodyClass = 'login-body';
require APP_PATH . '/views/layouts/header.php';
?>
<div class="login-wrapper">
    <div class="login-brand-panel">
        <div class="login-mesh"></div>
        <div class="login-brand-content">
            <div class="login-brand-icon"><i class="bi bi-boxes"></i></div>
            <span class="login-brand-badge">Production System</span>
            <h1 class="login-brand-title"><?= e(APP_COMPANY) ?></h1>
            <p class="login-brand-sub">Stock Management Platform<br><span>Company ID: <?= e(APP_COMPANY_ID) ?></span></p>
            <ul class="login-features">
                <li><i class="bi bi-check-circle-fill"></i> Real-time stock in &amp; out tracking</li>
                <li><i class="bi bi-check-circle-fill"></i> Admin approval workflow</li>
                <li><i class="bi bi-check-circle-fill"></i> Balance reports &amp; analytics</li>
                <li><i class="bi bi-check-circle-fill"></i> Fruits, Gelato &amp; Icecream</li>
            </ul>
        </div>
    </div>

    <div class="login-form-panel">
        <div class="login-card">
            <div class="login-mobile-brand">
                <div class="login-brand-icon login-brand-icon--sm"><i class="bi bi-boxes"></i></div>
                <div class="fw-bold"><?= e(APP_NAME) ?></div>
                <small class="text-muted"><?= e(APP_COMPANY) ?></small>
            </div>

            <h2>Welcome back</h2>
            <p class="text-muted mb-4">Sign in to manage your inventory</p>

            <?php require APP_PATH . '/views/partials/flash.php'; ?>

            <form method="post" action="" class="login-form">
                <?= csrf_field() ?>
                <div class="form-floating-custom">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" class="form-control" name="username" id="username"
                           placeholder="Username" value="<?= e($username ?? '') ?>" required autocomplete="username">
                </div>
                <div class="form-floating-custom">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" class="form-control" name="password" id="password"
                           placeholder="Password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-login">
                    <span>Sign In</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
