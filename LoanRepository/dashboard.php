<?php
// Autoloader function
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'Loan\\';
    $base_dir = __DIR__ . '/';
    
    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Convert namespace separators to directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

use Loan\Config\Database;
use Loan\Repository\LoanRepository;
use Loan\Service\LoanService;

try {
    // Get database connection
    $db = Database::getConnection();
    
    // Initialize repositories and services
    $loanRepository = new LoanRepository($db);
    $loanService = new LoanService($loanRepository);
    
    // Get total number of loans
    $stmt = $db->query("SELECT COUNT(*) FROM loans");
    $totalLoans = $stmt->fetchColumn();
    
    // Get total number of users
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();
    
    // Get total loan amount
    $stmt = $db->query("SELECT SUM(amount) FROM loans");
    $totalLoanAmount = $stmt->fetchColumn() ?: 0;
    
    // Get total payments
    $stmt = $db->query("SELECT SUM(amount) FROM payments");
    $totalPayments = $stmt->fetchColumn() ?: 0;
    
    // Get recent loans
    $stmt = $db->query("
        SELECT l.*, u.name as user_name 
        FROM loans l
        JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $recentLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent payments
    $stmt = $db->query("
        SELECT p.*, l.amount as loan_amount, u.name as user_name
        FROM payments p
        JOIN loans l ON p.loan_id = l.id
        JOIN users u ON l.user_id = u.id
        ORDER BY p.payment_date DESC
        LIMIT 5
    ");
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get loans by interest rate (for chart)
    $stmt = $db->query("
        SELECT 
            CASE 
                WHEN interest_rate < 3 THEN 'Below 3%'
                WHEN interest_rate >= 3 AND interest_rate < 5 THEN '3-5%'
                WHEN interest_rate >= 5 AND interest_rate < 7 THEN '5-7%'
                ELSE 'Above 7%'
            END as rate_range,
            COUNT(*) as count
        FROM loans
        GROUP BY rate_range
    ");
    $loansByInterestRate = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get loans by term (for chart)
    $stmt = $db->query("
        SELECT 
            CASE 
                WHEN term_months <= 6 THEN '0-6 months'
                WHEN term_months > 6 AND term_months <= 12 THEN '7-12 months'
                WHEN term_months > 12 AND term_months <= 24 THEN '13-24 months'
                ELSE 'Over 24 months'
            END as term_range,
            COUNT(*) as count
        FROM loans
        GROUP BY term_range
    ");
    $loansByTerm = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Loan Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 1200px;
        }
        .card {
            margin-bottom: 20px;
        }
        .stat-card {
            text-align: center;
            padding: 15px;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-card .label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .chart-container {
            position: relative;
            height: 250px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Dashboard</h1>
            <div>
                <a href="index.php" class="btn btn-outline-primary me-2">Manage Loans</a>
                <a href="users.php" class="btn btn-outline-secondary">Manage Users</a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white stat-card">
                        <div class="value"><?php echo $totalLoans; ?></div>
                        <div class="label">Total Loans</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white stat-card">
                        <div class="value"><?php echo $totalUsers; ?></div>
                        <div class="label">Total Users</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white stat-card">
                        <div class="value">$<?php echo number_format($totalLoanAmount, 2); ?></div>
                        <div class="label">Total Loan Amount</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark stat-card">
                        <div class="value">$<?php echo number_format($totalPayments, 2); ?></div>
                        <div class="label">Total Payments</div>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Loans by Interest Rate</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="interestRateChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Loans by Term</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="termChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Loans -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Recent Loans</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Interest Rate</th>
                                    <th>Term</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentLoans) > 0): ?>
                                    <?php foreach ($recentLoans as $loan): ?>
                                        <tr>
                                            <td><?php echo $loan['id']; ?></td>
                                            <td><?php echo htmlspecialchars($loan['user_name']); ?></td>
                                            <td>$<?php echo number_format($loan['amount'], 2); ?></td>
                                            <td><?php echo $loan['interest_rate']; ?>%</td>
                                            <td><?php echo $loan['term_months']; ?> months</td>
                                            <td><?php echo date('Y-m-d', strtotime($loan['created_at'])); ?></td>
                                            <td>
                                                <a href="view_loan.php?id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-info">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No loans found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="index.php" class="btn btn-outline-primary btn-sm">View All Loans</a>
                </div>
            </div>
            
            <!-- Recent Payments -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Recent Payments</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Loan ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentPayments) > 0): ?>
                                    <?php foreach ($recentPayments as $payment): ?>
                                        <tr>
                                            <td><?php echo $payment['id']; ?></td>
                                            <td>
                                                <a href="view_loan.php?id=<?php echo $payment['loan_id']; ?>">
                                                    Loan #<?php echo $payment['loan_id']; ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['user_name']); ?></td>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No payments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart initialization -->
    <script>
        // Interest Rate Chart
        const interestRateCtx = document.getElementById('interestRateChart').getContext('2d');
        const interestRateChart = new Chart(interestRateCtx, {
            type: 'pie',
            data: {
                labels: [
                    <?php foreach ($loansByInterestRate as $item): ?>
                        '<?php echo $item['rate_range']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($loansByInterestRate as $item): ?>
                            <?php echo $item['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        
        // Term Chart
        const termCtx = document.getElementById('termChart').getContext('2d');
        const termChart = new Chart(termCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($loansByTerm as $item): ?>
                        '<?php echo $item['term_range']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Number of Loans',
                    data: [
                        <?php foreach ($loansByTerm as $item): ?>
                            <?php echo $item['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>