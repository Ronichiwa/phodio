<?php 
include 'functions.php'; 
// Assuming you have a function to get expenses, otherwise use a standard SQL query
$expenses = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses | StudioPro</title>
    
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
                        <span><i class="ri-wallet-3-line me-2"></i>Expense Management</span>
                        <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#expenseModal">
                            <i class="ri-add-circle-line me-1"></i> Add Expense
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead class="table-light text-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $expenses->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($row['expense_date'])) ?></td>
                                        <td><?= $row['description'] ?></td>
                                        <td class="text-danger fw-bold">₱<?= number_format($row['amount'], 2) ?></td>
                                        <td class="text-center">
                                            <a href="delete_expense.php?id=<?= $row['id'] ?>" class="text-muted" onclick="return confirm('Delete this record?')">
                                                <i class="ri-delete-bin-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="process_expense.php" method="POST">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title">Record New Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. Electricity Bill, Studio Rent" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Date</label>
                        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount (₱)</label>
                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>