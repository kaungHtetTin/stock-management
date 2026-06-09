<?php
/** @var array $line */
/** @var int $index */
/** @var array $items */
$index = $index ?? 0;
$line = $line ?? [];
$lineKey = ($index === '__INDEX__') ? '__INDEX__' : (int) $index;
$lineNum = is_int($lineKey) ? $lineKey + 1 : '#';
?>
<div class="line-item-row border rounded-3 p-3 mb-3 bg-light-subtle" data-line-row>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small fw-semibold text-muted">Item line <span data-line-number><?= $lineNum ?></span></span>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-line title="Remove line" aria-label="Remove line">
            <i class="bi bi-trash"></i>
        </button>
    </div>
    <div class="row g-2">
        <div class="col-12 col-md-6">
            <label class="form-label">Item <span class="required">*</span></label>
            <select class="form-select" name="lines[<?= $lineKey ?>][item_id]" data-item-select required>
                <option value="">Select item</option>
                <?php foreach ($items as $it): ?>
                <option value="<?= $it['id'] ?>"
                        data-unit="<?= e($it['unit']) ?>"
                        data-balance="<?= $it['balance'] ?>"
                        <?= (int) ($line['item_id'] ?? 0) === (int) $it['id'] ? 'selected' : '' ?>>
                    <?= e($it['item_no']) ?> — <?= e($it['item_name']) ?> (Bal: <?= format_number($it['balance']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">MFD Date</label>
            <input type="date" class="form-control" name="lines[<?= $lineKey ?>][mfd_date]"
                   value="<?= e($line['mfd_date'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Qty <span class="required">*</span></label>
            <input type="number" class="form-control" name="lines[<?= $lineKey ?>][qty]" min="0.01" step="0.01"
                   value="<?= e($line['qty'] ?? '') ?>" required>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Unit <span class="required">*</span></label>
            <input type="text" class="form-control" name="lines[<?= $lineKey ?>][unit]" data-item-unit
                   value="<?= e($line['unit'] ?? '') ?>" required>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Balance</label>
            <input type="text" class="form-control" data-item-balance readonly
                   value="<?= e($line['item_balance'] ?? '') ?>" placeholder="—">
        </div>
    </div>
</div>
