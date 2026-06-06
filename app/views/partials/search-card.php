<div class="card card-filter mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="">
            <?= $searchFields ?? '' ?>
            <div class="col-12 col-sm-auto">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
            <?php if (!empty($showReset)): ?>
            <div class="col-12 col-sm-auto">
                <a href="<?= e($resetUrl ?? '') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
