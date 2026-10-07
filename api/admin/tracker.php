<?php 
include 'functions.php'; 

// Fetch tracker data - latest first
$tracker = $conn->query("SELECT *, (target - income_today) as gap FROM daily_tracker ORDER BY track_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Tracker | StudioPro</title>
    
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
                        <span><i class="ri-line-chart-line me-2"></i>Performance Tracker</span>
                        <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#trackerModal">
                            <i class="ri-add-circle-line me-1"></i> Log Today
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead class="table-light text-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Income Today</th>
                                        <th>Clients</th>
                                        <th>Target</th>
                                        <th>Gap</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $tracker->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($row['track_date'])) ?></td>
                                        <td class="text-success fw-bold">₱<?= number_format($row['income_today'], 2) ?></td>
                                        <td><?= $row['client_today'] ?></td>
                                        <td class="text-info">₱<?= number_format($row['target'], 2) ?></td>
                                        <td class="<?= ($row['gap'] > 0) ? 'text-danger' : 'text-success fw-bold' ?>">
                                            <?= ($row['gap'] <= 0) ? 'Goal Met!' : '₱' . number_format($row['gap'], 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="delete_tracker.php?id=<?= $row['id'] ?>" class="text-muted" onclick="return confirm('Delete this log?')">
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

<div class="modal fade" id="trackerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="process_tracker.php" method="POST">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title">Log Daily Performance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="track_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Income Today (₱)</label>
                        <input type="number" step="0.01" name="income_today" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Total Clients</label>
                        <input type="number" name="client_today" class="form-control" placeholder="0" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Daily Target (₱)</label>
                    <input type="number" step="0.01" name="target" class="form-control" value="5000.00" required>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>