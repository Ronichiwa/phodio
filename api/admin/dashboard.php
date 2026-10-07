<?php 
include 'functions.php'; 
checkLogin(); // This stops unauthorized admin immediately

$range = isset($_GET['range']) ? $_GET['range'] : 'month';
$data = getDashboardData($conn, $range);
$stats = $data['stats'];
$rental_breakdown = $data['rentals'];
$liability_breakdown = $data['liabilities_list'];

// 2. Fetch the very latest Daily Tracker entry for the highlight card
$today_track = $conn->query("SELECT * FROM daily_tracker ORDER BY track_date DESC LIMIT 1")->fetch_assoc();
$gap = ($today_track) ? ($today_track['target'] - $today_track['income_today']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SOULPRINT</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --bg-darker: #0f0f0f;
            --card-bg: #1a1a1a;
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
            --text-muted: #a1a1aa;
        }

        body {
            background-color: var(--bg-darker);
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }

        /* Card Styling */
        .card {
            background-color: var(--card-bg);
            border-radius: 12px;
            transition: transform 0.2s ease;
        }

        .summary-box {
            border-radius: 12px;
            overflow: hidden;
        }

        /* Filter Button Styling */
        .filter-group .btn {
            padding: 6px 16px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #333;
            color: var(--text-muted);
        }

        .filter-group .btn-active {
            background-color: var(--accent-blue) !important;
            color: white !important;
            border-color: var(--accent-blue) !important;
        }

        /* Table Styling */
        .table-custom {
            font-size: 0.9rem;
        }
        
        .table-custom tbody tr {
            border-bottom: 1px solid #2d2d2d;
        }

        .table-custom td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        /* Icons */
        .icon-circle {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0 text-uppercase" style="letter-spacing: 1.5px;">Business Overview</h4>
                <small class="text-muted">Viewing data for: <span class="text-info fw-bold"><?= ucfirst($range) ?></span></small>
            </div>
            
            <div class="btn-group filter-group shadow">
                <a href="dashboard.php?range=today" class="btn btn-dark <?= ($range == 'today') ? 'btn-active' : '' ?>">Today</a>
                <a href="dashboard.php?range=month" class="btn btn-dark <?= ($range == 'month') ? 'btn-active' : '' ?>">Monthly</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card p-4 border-0 shadow-sm text-center">
                    <small class="text-uppercase fw-bold text-muted">Income</small>
                    <h2 class="mb-0 mt-2 text-white">₱<?= number_format($stats['income']) ?></h2>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-4 border-0 shadow-sm text-center">
                    <small class="text-uppercase fw-bold text-muted">Expenses</small>
                    <h2 class="mb-0 mt-2 text-white">₱<?= number_format($stats['expense']) ?></h2>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-4 border-0 shadow-sm text-center bg-warning">
                    <small class="text-uppercase fw-bold text-dark">Liabilities</small>
                    <h2 class="mb-0 mt-2 text-dark">₱<?= number_format($stats['liabilities']) ?></h2>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-4 border-0 shadow-sm text-center">
                    <small class="text-uppercase fw-bold text-muted">Net Profit</small>
                    <h2 class="mb-0 mt-2 <?= ($stats['profit'] < 0) ? 'text-danger' : 'text-success' ?>">
                        ₱<?= number_format($stats['profit']) ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                
                <div class="card border-0 shadow-lg mb-4" style="background: linear-gradient(145deg, #1a1a1a, #252525);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="text-white mb-0"><i class="ri-flashlight-line text-warning me-2"></i>Daily Goal Progress</h5>
                            <span class="badge bg-dark border border-secondary p-2"><?= $today_track['track_date'] ?? 'No Data' ?></span>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <small class="text-muted d-block mb-1">Income Today</small>
                                <span class="h3 fw-bold text-white">₱<?= number_format($today_track['income_today'] ?? 0) ?></span>
                            </div>
                            <div class="col-4 border-start border-secondary">
                                <small class="text-muted d-block mb-1">Remaining Gap</small>
                                <span class="h3 fw-bold <?= ($gap > 0) ? 'text-danger' : 'text-success' ?>">
                                    <?= ($gap <= 0 && $today_track) ? 'CLEARED' : '₱'.number_format($gap) ?>
                                </span>
                            </div>
                            <div class="col-4 border-start border-secondary">
                                <small class="text-muted d-block mb-1">Total Clients</small>
                                <span class="h3 fw-bold text-white"><?= $today_track['client_today'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 h-100">
                            <div class="card-header bg-transparent border-secondary text-white py-3">
                                <i class="ri-pie-chart-line me-2 text-info"></i>Revenue by Package
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-dark table-custom mb-0">
                                    <tbody>
                                        <?php while($rb = $rental_breakdown->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $rb['package_type'] ?></td>
                                            <td class="text-end text-info fw-bold">₱<?= number_format($rb['amt']) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 h-100">
                            <div class="card-header bg-transparent border-secondary text-white py-3">
                                <i class="ri-user-received-2-line me-2 text-warning"></i>Creditor Breakdown
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-dark table-custom mb-0">
                                    <tbody>
                                        <?php while($lb = $liability_breakdown->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $lb['creditor'] ?></td>
                                            <td class="text-end text-warning fw-bold">₱<?= number_format($lb['amt']) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-secondary text-white py-3">
                        <i class="ri-rocket-2-line me-2 text-primary"></i>Quick Access
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="bookings.php" class="btn btn-outline-light text-start py-2 px-3 border-secondary hover-effect">
                                <i class="ri-calendar-event-line me-2 text-info"></i> Bookings Page
                            </a>
                            <a href="expenses.php" class="btn btn-outline-light text-start py-2 px-3 border-secondary">
                                <i class="ri-wallet-3-line me-2 text-danger"></i> Expenses Page
                            </a>
                            <a href="tracker.php" class="btn btn-outline-light text-start py-2 px-3 border-secondary">
                                <i class="ri-line-chart-line me-2 text-success"></i> Daily Tracker
                            </a>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div> </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>