<?php
$flashError   = flash('error');
$flashSuccess = flash('success');
?>
<?php if ($flashError): ?>
<div class="alert alert-danger alert-dismissible fade show flash-alert" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i><?= e($flashError) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<div class="alert alert-success alert-dismissible fade show flash-alert" role="alert">
    <i class="bi bi-check-circle me-2"></i><?= e($flashSuccess) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
