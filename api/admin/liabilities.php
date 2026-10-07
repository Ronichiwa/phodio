<?php 
include 'functions.php'; 
// Fetching liabilities - grouped by date
$liabilities = $conn->query("SELECT * FROM liabilities ORDER BY due_date ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liabilities | StudioPro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="ri-bank-card-line me-2"></i>Liabilities & Debts</span>
                        <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#liabilityModal">
                            <i class="ri-add-circle-line me-1"></i> Add Liability
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead class="table-light text-dark">
                                    <tr>
                                        <th>Due Date</th>
                                        <th>Creditor</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($liabilities->num_rows > 0): ?>
                                        <?php while($row = $liabilities->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($row['due_date'])) ?></td>
                                            <td class="fw-bold text-info"><?= $row['creditor'] ?></td>
                                            <td><?= $row['description'] ?></td>
                                            <td class="text-warning fw-bold">₱<?= number_format($row['amount'], 2) ?></td>
                                            <td class="text-center">
                                                <a href="delete_liability.php?id=<?= $row['id'] ?>" class="text-muted" onclick="return confirm('Mark as settled or delete?')">
                                                    <i class="ri-delete-bin-line"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No pending liabilities.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="liabilityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="process_liability.php" method="POST">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title">New Liability Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Creditor (Who do you owe?)</label>
                    <input type="text" name="creditor" class="form-control" placeholder="e.g. Camera Store, Bank, Supplier" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. Lens Installment, Balance for Studio Lights" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Amount (₱)</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save Liability</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>