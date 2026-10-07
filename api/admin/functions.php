<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

function checkLogin(): void
{
    if (!isset($_SESSION['admin'])) {
        header('Location: login.php');
        exit;
    }
}

function getDashboardData( string $range = 'month'): array
{
    $startDate = $range === 'today' ? date('Y-m-d') : date('Y-m-d', strtotime('-1 month'));

    $stmt = $conn->prepare("SELECT COALESCE(SUM(price), 0) AS total FROM bookings WHERE booking_date >= ? AND status <> 'Cancelled'");
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $income = (float) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE expense_date >= ?');
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $expense = (float) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM liabilities WHERE created_at >= ?');
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $liabilities = (float) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = $conn->prepare("SELECT package_type, SUM(price) AS amt FROM bookings WHERE booking_date >= ? AND status <> 'Cancelled' GROUP BY package_type ORDER BY amt DESC");
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $rentals = $stmt->get_result();

    $stmt = $conn->prepare('SELECT description, SUM(amount) AS amt FROM expenses WHERE expense_date >= ? GROUP BY description ORDER BY amt DESC');
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $expenses = $stmt->get_result();

    $stmt = $conn->prepare('SELECT creditor, SUM(amount) AS amt FROM liabilities WHERE created_at >= ? GROUP BY creditor ORDER BY amt DESC');
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $liabilitiesList = $stmt->get_result();

    return [
        'stats' => [
            'income' => $income,
            'expense' => $expense,
            'liabilities' => $liabilities,
            'profit' => $income - $expense,
        ],
        'rentals' => $rentals,
        'expenses' => $expenses,
        'liabilities_list' => $liabilitiesList,
    ];
}
