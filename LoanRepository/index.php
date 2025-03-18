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

// Initialize database if needed
if (!file_exists('database_initialized.txt')) {
    require_once 'init_db.php';
    file_put_contents('database_initialized.txt', 'Database has been initialized on ' . date('Y-m-d H:i:s'));
}

use Loan\Config\Database;
use Loan\Repository\LoanRepository;
use Loan\Service\LoanService;

// Process form submissions
$message = '';
$messageType = '';

try {
    // Get database connection
    $db = Database::getConnection();
    
    // Initialize repositories and services
    $loanRepository = new LoanRepository($db);
    $loanService = new LoanService($loanRepository);
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'createLoan':
                $userId = (int)$_POST['userId'];
                $amount = (float)$_POST['amount'];
                $interestRate = (float)$_POST['interestRate'];
                $termMonths = (int)$_POST['termMonths'];
                
                $loanId = $loanService->createLoan($userId, $amount, $interestRate, $termMonths);
                
                if ($loanId) {
                    $message = "Loan created successfully with ID: $loanId";
                    $messageType = "success";
                } else {
                    $message = "Failed to create loan";
                    $messageType = "danger";
                }
                break;
                
            case 'updateLoan':
                $loanId = (int)$_POST['loanId'];
                $amount = (float)$_POST['amount'];
                $interestRate = (float)$_POST['interestRate'];
                $termMonths = (int)$_POST['termMonths'];
                
                $success = $loanService->updateLoan($loanId, $amount, $interestRate, $termMonths);
                
                if ($success) {
                    $message = "Loan updated successfully";
                    $messageType = "success";
                } else {
                    $message = "Failed to update loan";
                    $messageType = "danger";
                }
                break;
                
            case 'deleteLoan':
                $loanId = (int)$_POST['loanId'];
                
                $success = $loanService->deleteLoan($loanId);
                
                if ($success) {
                    $message = "Loan deleted successfully";
                    $messageType = "success";
                } else {
                    $message = "Failed to delete loan";
                    $messageType = "danger";
                }
                break;
        }
    }
    
    // Get all users for dropdown
    $stmt = $db->query("SELECT id, name FROM users ORDER BY name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all loans with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    
    $loansData = $loanService->getAllLoans($page, $perPage);
    $loans = $loansData['loans'];
    $totalPages = $loansData['totalPages'];
    
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "danger";
}

// Get loan details if editing
$editingLoan = null;
if (isset($_GET['edit'])) {
    $editingLoan = $loanService->getLoanById((int)$_GET['edit']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 1200px;
        }
        .table-container {
            margin-top: 30px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mt-4 mb-4">Loan Management System</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="table-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1>Loan Management</h1>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">Back to Dashboard</a>
                            <a href="javascript:void(0);" onclick="document.getElementById('loanForm').scrollIntoView({behavior: 'smooth'});" class="btn btn-primary">Create New Loan</a>
                        </div>
                    </div>
                    
                    <!-- Search and filter functionality -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Filter Loans</h5>
                        </div>
                        <div class="card-body">
                            <form method="get" class="row g-3">
                                <div class="col-md-4">
                                    <label for="userId" class="form-label">User</label>
                                    <select class="form-select" id="userId" name="user_id">
                                        <option value="">All Users</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="minAmount" class="form-label">Min Amount</label>
                                    <input type="number" class="form-control" id="minAmount" name="min_amount" 
                                           value="<?php echo isset($_GET['min_amount']) ? htmlspecialchars($_GET['min_amount']) : ''; ?>" 
                                           placeholder="Min Amount">
                                </div>
                                <div class="col-md-3">
                                    <label for="maxAmount" class="form-label">Max Amount</label>
                                    <input type="number" class="form-control" id="maxAmount" name="max_amount" 
                                           value="<?php echo isset($_GET['max_amount']) ? htmlspecialchars($_GET['max_amount']) : ''; ?>" 
                                           placeholder="Max Amount">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                                <?php if (isset($_GET['user_id']) || isset($_GET['min_amount']) || isset($_GET['max_amount'])): ?>
                                <div class="col-12 mt-2">
                                    <a href="index.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Interest Rate</th>
                                <th>Term (Months)</th>
                                <th>Monthly Payment</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($loans) > 0): ?>
                                <?php foreach ($loans as $loan): ?>
                                    <?php 
                                        // Find user name
                                        $userName = "Unknown";
                                        foreach ($users as $user) {
                                            if ($user['id'] == $loan->getUserId()) {
                                                $userName = $user['name'];
                                                break;
                                            }
                                        }
                                        
                                        // Calculate monthly payment
                                        $monthlyPayment = $loanService->calculateMonthlyPayment(
                                            $loan->getAmount(), 
                                            $loan->getInterestRate(), 
                                            $loan->getTermMonths()
                                        );
                                    ?>
                                    <tr>
                                        <td><?php echo $loan->getId(); ?></td>
                                        <td><?php echo htmlspecialchars($userName); ?></td>
                                        <td>$<?php echo number_format($loan->getAmount(), 2); ?></td>
                                        <td><?php echo $loan->getInterestRate(); ?>%</td>
                                        <td><?php echo $loan->getTermMonths(); ?></td>
                                        <td>$<?php echo number_format($monthlyPayment, 2); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($loan->getCreatedAt())); ?></td>
                                        <td>
                                            <a href="?edit=<?php echo $loan->getId(); ?>" class="btn btn-sm btn-primary">Edit</a>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="loanId" value="<?php echo $loan->getId(); ?>">
                                                <input type="hidden" name="action" value="deleteLoan">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this loan?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No loans found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card" id="loanForm">
                    <div class="card-header bg-primary text-white">
                        <?php echo $editingLoan ? 'Edit Loan' : 'Add New Loan'; ?>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="<?php echo $editingLoan ? 'updateLoan' : 'createLoan'; ?>">
                            
                            <?php if ($editingLoan): ?>
                                <input type="hidden" name="loanId" value="<?php echo $editingLoan->getId(); ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="userId" class="form-label">User</label>
                                <select class="form-select" id="userId" name="userId" required <?php echo $editingLoan ? 'disabled' : ''; ?>>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ($editingLoan && $editingLoan->getUserId() == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($editingLoan): ?>
                                    <input type="hidden" name="userId" value="<?php echo $editingLoan->getUserId(); ?>">
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="amount" class="form-label">Loan Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" 
                                           value="<?php echo $editingLoan ? $editingLoan->getAmount() : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="interestRate" class="form-label">Interest Rate (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control" id="interestRate" name="interestRate" 
                                           value="<?php echo $editingLoan ? $editingLoan->getInterestRate() : ''; ?>" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="termMonths" class="form-label">Term (Months)</label>
                                <input type="number" min="1" class="form-control" id="termMonths" name="termMonths" 
                                       value="<?php echo $editingLoan ? $editingLoan->getTermMonths() : ''; ?>" required>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $editingLoan ? 'Update Loan' : 'Add Loan'; ?>
                                </button>
                                <?php if ($editingLoan): ?>
                                    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Loan Calculator -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        Loan Calculator
                    </div>
                    <div class="card-body">
                        <form id="calculatorForm">
                            <div class="mb-3">
                                <label for="calcAmount" class="form-label">Loan Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="calcAmount" value="10000">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="calcInterestRate" class="form-label">Interest Rate (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control" id="calcInterestRate" value="5.0">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="calcTermMonths" class="form-label">Term (Months)</label>
                                <input type="number" min="1" class="form-control" id="calcTermMonths" value="12">
                            </div>
                            
                            <div class="d-grid">
                                <button type="button" id="calculateBtn" class="btn btn-info">Calculate</button>
                            </div>
                        </form>
                        
                        <div id="calculationResults" class="mt-3" style="display: none;">
                            <hr>
                            <h5>Results:</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Monthly Payment:</th>
                                    <td id="monthlyPayment">$0.00</td>
                                </tr>
                                <tr>
                                    <th>Total Payment:</th>
                                    <td id="totalPayment">$0.00</td>
                                </tr>
                                <tr>
                                    <th>Total Interest:</th>
                                    <td id="totalInterest">$0.00</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Loan calculator functionality
        document.getElementById('calculateBtn').addEventListener('click', function() {
            const amount = parseFloat(document.getElementById('calcAmount').value);
            const interestRate = parseFloat(document.getElementById('calcInterestRate').value);
            const termMonths = parseInt(document.getElementById('calcTermMonths').value);
            
            if (isNaN(amount) || isNaN(interestRate) || isNaN(termMonths)) {
                alert('Please enter valid numbers for all fields');
                return;
            }
            
            // Calculate monthly payment
            const monthlyInterestRate = interestRate / 12 / 100;
            let monthlyPayment;
            
            if (monthlyInterestRate === 0) {
                monthlyPayment = amount / termMonths;
            } else {
                monthlyPayment = (amount * monthlyInterestRate) / (1 - Math.pow(1 + monthlyInterestRate, -termMonths));
            }
            
            const totalPayment = monthlyPayment * termMonths;
            const totalInterest = totalPayment - amount;
            
            // Display results
            document.getElementById('monthlyPayment').textContent = '$' + monthlyPayment.toFixed(2);
            document.getElementById('totalPayment').textContent = '$' + totalPayment.toFixed(2);
            document.getElementById('totalInterest').textContent = '$' + totalInterest.toFixed(2);
            
            document.getElementById('calculationResults').style.display = 'block';
        });
        
        // Remove this event listener section since we're using the inline onclick
        // document.addEventListener('DOMContentLoaded', function() {
        //     document.querySelector('a[href="#loanForm"]').addEventListener('click', function(e) {
        //         e.preventDefault();
        //         document.getElementById('loanForm').scrollIntoView({ behavior: 'smooth' });
        //     });
        // });
    </script>
</body>
</html>