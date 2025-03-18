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

// Process form submissions
$message = '';
$messageType = '';

try {
    // Get database connection
    $db = Database::getConnection();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'createUser':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                
                // Check if email already exists
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                $emailExists = $checkStmt->fetchColumn() > 0;
                
                if ($emailExists) {
                    $message = "Email already exists";
                    $messageType = "danger";
                } else {
                    $stmt = $db->prepare("INSERT INTO users (name, email, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                    $success = $stmt->execute([$name, $email]);
                    
                    if ($success) {
                        $message = "User created successfully";
                        $messageType = "success";
                    } else {
                        $message = "Failed to create user";
                        $messageType = "danger";
                    }
                }
                break;
                
            case 'updateUser':
                $userId = (int)$_POST['userId'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                
                // Check if email already exists for another user
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $checkStmt->execute([$email, $userId]);
                $emailExists = $checkStmt->fetchColumn() > 0;
                
                if ($emailExists) {
                    $message = "Email already exists";
                    $messageType = "danger";
                } else {
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                    $success = $stmt->execute([$name, $email, $userId]);
                    
                    if ($success) {
                        $message = "User updated successfully";
                        $messageType = "success";
                    } else {
                        $message = "Failed to update user";
                        $messageType = "danger";
                    }
                }
                break;
                
            case 'deleteUser':
                $userId = (int)$_POST['userId'];
                
                // Check if user has loans
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ?");
                $checkStmt->execute([$userId]);
                $hasLoans = $checkStmt->fetchColumn() > 0;
                
                if ($hasLoans) {
                    $message = "Cannot delete user with active loans";
                    $messageType = "danger";
                } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $success = $stmt->execute([$userId]);
                    
                    if ($success) {
                        $message = "User deleted successfully";
                        $messageType = "success";
                    } else {
                        $message = "Failed to delete user";
                        $messageType = "danger";
                    }
                }
                break;
        }
    }
    
    // Get all users
    $stmt = $db->query("SELECT * FROM users ORDER BY name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user details if editing
    $editingUser = null;
    if (isset($_GET['edit'])) {
        $editId = (int)$_GET['edit'];
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$editId]);
        $editingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "danger";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Loan Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 1200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>User Management</h1>
            <a href="index.php" class="btn btn-outline-secondary">Back to Loans</a>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">All Users</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="userId" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="deleteUser">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <?php echo $editingUser ? 'Edit User' : 'Add New User'; ?>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="<?php echo $editingUser ? 'updateUser' : 'createUser'; ?>">
                            
                            <?php if ($editingUser): ?>
                                <input type="hidden" name="userId" value="<?php echo $editingUser['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo $editingUser ? htmlspecialchars($editingUser['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo $editingUser ? htmlspecialchars($editingUser['email']) : ''; ?>" required>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $editingUser ? 'Update User' : 'Add User'; ?>
                                </button>
                                <?php if ($editingUser): ?>
                                    <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>