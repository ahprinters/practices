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

// Check if loan ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$loanId = (int)$_GET['id'];

try {
    // Get database connection
    $db = Database::getConnection();
    
    // Initialize repositories and services
    $loanRepository = new LoanRepository($db);
    $loanService = new LoanService($loanRepository);
    
    // Get loan details
    $loan = $loanService->getLoanById($loanId);
    
    if (!$loan) {
        // Loan not found
        header('Location: index.php');
        exit;
    }
    
    // Get user details
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$loan->getUserId()]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate loan details
    $monthlyPayment = $loanService->calculateMonthlyPayment(
        $loan->getAmount(), 
        $loan->getInterestRate(), 
        $loan->getTermMonths()
    );
    
    $totalPayment = $loanService->calculateTotalRepayment(
        $loan->getAmount(), 
        $loan->getInterestRate(), 
        $loan->getTermMonths()
    );
    
    $totalInterest = $loanService->calculateTotalInterest(
        $loan->getAmount(), 
        $loan->getInterestRate(), 
        $loan->getTermMonths()
    );
    
    // Get payment history
    $stmt = $db->prepare("SELECT * FROM payments WHERE loan_id = ? ORDER BY payment_date DESC");
    $stmt->execute([$loanId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total paid so far
    $totalPaid = 0;
    foreach ($payments as $payment) {
        $totalPaid += $payment['amount'];
    }
    
    // Calculate remaining balance
    $remainingBalance = $totalPayment - $totalPaid;
    if ($remainingBalance < 0) {
        $remainingBalance = 0;
    }
    
    // Process payment actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'addPayment':
                $paymentAmount = (float)$_POST['paymentAmount'];
                $paymentDate = $_POST['paymentDate'];
                
                // Insert payment record
                $stmt = $db->prepare("INSERT INTO payments (loan_id, amount, payment_date, created_at) VALUES (?, ?, ?, NOW())");
                $success = $stmt->execute([$loanId, $paymentAmount, $paymentDate]);
                
                if ($success) {
                    // Redirect to refresh the page
                    header("Location: view_loan.php?id=$loanId&success=payment_added");
                    exit;
                } else {
                    $error = "Failed to add payment";
                }
                break;
                
            case 'deletePayment':
                $paymentId = (int)$_POST['paymentId'];
                
                // Delete payment record
                $stmt = $db->prepare("DELETE FROM payments WHERE id = ? AND loan_id = ?");
                $success = $stmt->execute([$paymentId, $loanId]);
                
                if ($success) {
                    // Redirect to refresh the page
                    header("Location: view_loan.php?id=$loanId&success=payment_deleted");
                    exit;
                } else {
                    $error = "Failed to delete payment";
                }
                break;
        }
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Details - Loan Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 1200px;
        }
        .loan-details {
            margin-top: 30px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Loan Details</h1>
            <a href="index.php" class="btn btn-outline-secondary">Back to Loans</a>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    switch ($_GET['success']) {
                        case 'payment_added':
                            echo "Payment was successfully added.";
                            break;
                        case 'payment_deleted':
                            echo "Payment was successfully deleted.";
                            break;
                        default:
                            echo "Operation completed successfully.";
                    }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Loan Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Loan ID:</th>
                                    <td><?php echo $loan->getId(); ?></td>
                                </tr>
                                <tr>
                                    <th>Borrower:</th>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Loan Amount:</th>
                                    <td>$<?php echo number_format($loan->getAmount(), 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Interest Rate:</th>
                                    <td><?php echo $loan->getInterestRate(); ?>%</td>
                                </tr>
                                <tr>
                                    <th>Term:</th>
                                    <td><?php echo $loan->getTermMonths(); ?> months</td>
                                </tr>
                                <tr>
                                    <th>Start Date:</th>
                                    <td><?php echo date('F j, Y', strtotime($loan->getCreatedAt())); ?></td>
                                </tr>
                                <tr>
                                    <th>End Date:</th>
                                    <td><?php echo date('F j, Y', strtotime($loan->getCreatedAt() . " + " . $loan->getTermMonths() . " months")); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Payment Summary</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Monthly Payment:</th>
                                    <td>$<?php echo number_format($monthlyPayment, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Payment:</th>
                                    <td>$<?php echo number_format($totalPayment, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Interest:</th>
                                    <td>$<?php echo number_format($totalInterest, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Paid So Far:</th>
                                    <td>$<?php echo number_format($totalPaid, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Remaining Balance:</th>
                                    <td>$<?php echo number_format($remainingBalance, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Progress:</th>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo ($totalPaid / $totalPayment) * 100; ?>%" 
                                                 aria-valuenow="<?php echo ($totalPaid / $totalPayment) * 100; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo round(($totalPaid / $totalPayment) * 100); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment History -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Payment History</h5>
                    <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        Add Payment
                    </button>
                </div>
                <div class="card-body">
                    <?php if (count($payments) > 0): ?>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo $payment['id']; ?></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="paymentId" value="<?php echo $payment['id']; ?>">
                                                <input type="hidden" name="action" value="deletePayment">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this payment?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-center">No payment history available.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Amortization Schedule -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Amortization Schedule</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Payment #</th>
                                <th>Payment Date</th>
                                <th>Payment Amount</th>
                                <th>Principal</th>
                                <th>Interest</th>
                                <th>Remaining Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $balance = $loan->getAmount();
                            $monthlyInterestRate = $loan->getInterestRate() / 12 / 100;
                            $startDate = new DateTime($loan->getCreatedAt());
                            
                            for ($i = 1; $i <= $loan->getTermMonths(); $i++) {
                                $interest = $balance * $monthlyInterestRate;
                                $principal = $monthlyPayment - $interest;
                                $balance -= $principal;
                                
                                if ($balance < 0) {
                                    $balance = 0;
                                }
                                
                                $paymentDate = clone $startDate;
                                $paymentDate->modify("+$i months");
                                
                                // Check if this payment has been made
                                $isPaid = false;
                                foreach ($payments as $payment) {
                                    $paymentMonth = date('Y-m', strtotime($payment['payment_date']));
                                    $scheduleMonth = $paymentDate->format('Y-m');
                                    
                                    if ($paymentMonth == $scheduleMonth) {
                                        $isPaid = true;
                                        break;
                                    }
                                }
                                
                                $rowClass = $isPaid ? 'table-success' : '';
                                $currentMonth = (new DateTime())->format('Y-m');
                                $scheduleMonth = $paymentDate->format('Y-m');
                                
                                if (!$isPaid && $scheduleMonth == $currentMonth) {
                                    $rowClass = 'table-warning';
                                } else if (!$isPaid && $scheduleMonth < $currentMonth) {
                                    $rowClass = 'table-danger';
                                }
                            ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td><?php echo $i; ?></td>
                                    <td><?php echo $paymentDate->format('F j, Y'); ?></td>
                                    <td>$<?php echo number_format($monthlyPayment, 2); ?></td>
                                    <td>$<?php echo number_format($principal, 2); ?></td>
                                    <td>$<?php echo number_format($interest, 2); ?></td>
                                    <td>$<?php echo number_format($balance, 2); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Add Payment Modal -->
            <div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addPaymentModalLabel">Add Payment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="addPayment">
                                <input type="hidden" name="loanId" value="<?php echo $loan->getId(); ?>">
                                
                                <div class="mb-3">
                                    <label for="paymentAmount" class="form-label">Payment Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control" id="paymentAmount" name="paymentAmount" value="<?php echo $monthlyPayment; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="paymentDate" class="form-label">Payment Date</label>
                                    <input type="date" class="form-control" id="paymentDate" name="paymentDate" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">Add Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>