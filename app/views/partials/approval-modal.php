<div class="modal fade" id="approvalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalModalTitle">Approve Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="approvalModalBody" class="mb-3"></p>
                <div id="rejectReasonGroup" class="d-none">
                    <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejectReason" rows="3" placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger d-none" id="confirmRejectBtn">Reject</button>
                <button type="button" class="btn btn-success" id="confirmApproveBtn">Approve</button>
            </div>
        </div>
    </div>
</div>
<form id="approvalRejectForm" method="post" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="id" id="approvalRejectId" value="">
    <input type="hidden" name="reason" id="approvalRejectReason" value="">
</form>
