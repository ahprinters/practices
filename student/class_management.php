<?php
// --- class_management.php ---

// Autoloader function
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'Student\\';
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

// For backward compatibility, include the old files directly
require_once 'config/database.php';

// Initialize database connection
try {
    // Make sure database constants are defined
    if (!defined('DB_HOST')) {
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'student_management');
        define('DB_USER', 'root');
        define('DB_PASS', '');
    }
    
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Initialize class repository
    $classRepository = new Student\Repository\ClassRepository($db);
    
} catch (PDOException $e) {
    // Display a user-friendly error message
    $errorMessage = "Database connection failed: " . $e->getMessage();
    echo '<div style="color: red; background-color: #ffeeee; padding: 15px; margin: 20px; border: 1px solid #ff0000; border-radius: 5px;">';
    echo '<h3>Database Error</h3>';
    echo '<p>' . $errorMessage . '</p>';
    echo '<p>Please check your database configuration in config/database.php</p>';
    echo '</div>';
    exit; // Stop execution but allow the error to be displayed
}

// Process form submissions
$message = '';
$messageType = '';

// Check if classes table exists, if not create it
$checkTableStmt = $db->query("SHOW TABLES LIKE 'classes'");
if ($checkTableStmt->rowCount() == 0) {
    $createTableSQL = "CREATE TABLE classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($createTableSQL);
    
    // Add some default classes
    $defaultClasses = [
        ['Class One', 'First grade students'],
        ['Class Two', 'Second grade students'],
        ['Class Three', 'Third grade students'],
        ['Class Four', 'Fourth grade students'],
        ['Class Five', 'Fifth grade students']
    ];
    
    foreach ($defaultClasses as $class) {
        $classRepository->addClass($class[0], $class[1]);
    }
    
    $message = "Classes table created and populated with default classes.";
    $messageType = "success";
}

// Handle Add Class form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'addClass') {
        // Add new class
        $name = trim($_POST['className']);
        $description = trim($_POST['classDescription']);
        
        // Validate input
        if (empty($name)) {
            $message = "Class name cannot be empty.";
            $messageType = "danger";
        } else {
            try {
                if ($classRepository->addClass($name, $description)) {
                    $message = "Class added successfully!";
                    $messageType = "success";
                } else {
                    $message = "Failed to add class.";
                    $messageType = "danger";
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    } elseif ($_POST['action'] === 'updateClass') {
        // Update existing class
        $id = $_POST['classId'];
        $name = trim($_POST['className']);
        $description = trim($_POST['classDescription']);
        
        // Validate input
        if (empty($name)) {
            $message = "Class name cannot be empty.";
            $messageType = "danger";
        } else {
            try {
                if ($classRepository->updateClass($id, $name, $description)) {
                    $message = "Class updated successfully!";
                    $messageType = "success";
                } else {
                    $message = "Failed to update class.";
                    $messageType = "danger";
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    } elseif ($_POST['action'] === 'deleteClass') {
        // Delete class
        $id = $_POST['classId'];
        
        try {
            if ($classRepository->hasStudents($id)) {
                $message = "Cannot delete class because it is assigned to students.";
                $messageType = "warning";
            } else {
                if ($classRepository->deleteClass($id)) {
                    $message = "Class deleted successfully!";
                    $messageType = "success";
                } else {
                    $message = "Failed to delete class.";
                    $messageType = "danger";
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Get all classes with filtering
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $classes = $classRepository->searchClasses($_GET['search']);
} else {
    $classes = $classRepository->getAllClasses();
}

// Sort classes if needed
if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'id':
            usort($classes, function($a, $b) {
                return $a['id'] - $b['id'];
            });
            break;
        case 'students':
            $studentCounts = $classRepository->getStudentCountByClass();
            $countMap = [];
            foreach ($studentCounts as $count) {
                $countMap[$count['id']] = $count['student_count'];
            }
            
            usort($classes, function($a, $b) use ($countMap) {
                $countA = isset($countMap[$a['id']]) ? $countMap[$a['id']] : 0;
                $countB = isset($countMap[$b['id']]) ? $countMap[$b['id']] : 0;
                return $countB - $countA; // Descending order
            });
            break;
        default: // name
            usort($classes, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
    }
}

// Check if we're editing a class
$editingClass = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $editingClass = $classRepository->getClassById($editId);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management - Student Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 800px;
        }
        .table-container {
            margin-top: 30px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mt-4 mb-4">Class Management</h1>
        
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Class Management</li>
            </ol>
        </nav>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>All Classes</h2>
                <a href="#addClassForm" class="btn btn-success">Add New Class</a>
            </div>
            <!-- Add this after the breadcrumb navigation and before the table -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Filter Classes</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-6">
                            <label for="searchTerm" class="form-label">Search</label>
                            <input type="text" class="form-control" id="searchTerm" name="search" 
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                                   placeholder="Search by class name or description">
                        </div>
                        <div class="col-md-4">
                            <label for="sortBy" class="form-label">Sort By</label>
                            <select class="form-select" id="sortBy" name="sort">
                                <option value="name" <?php echo (!isset($_GET['sort']) || $_GET['sort'] == 'name') ? 'selected' : ''; ?>>Class Name</option>
                                <option value="id" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'id') ? 'selected' : ''; ?>>ID</option>
                                <option value="students" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'students') ? 'selected' : ''; ?>>Student Count</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <!-- Add a clear filter button -->
                        <?php if (isset($_GET['search']) || isset($_GET['sort'])): ?>
                        <div class="col-12 mt-2">
                            <a href="class_management.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <table class="table table-striped table-hover table-bordered">
                <!-- Modify the table header -->
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Class Name</th>
                        <th>Description</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($classes) > 0): ?>
                        <?php 
                        // Get student counts for all classes
                        $studentCounts = $classRepository->getStudentCountByClass();
                        $countMap = [];
                        foreach ($studentCounts as $count) {
                            $countMap[$count['id']] = $count['student_count'];
                        }
                        ?>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?php echo $class['id']; ?></td>
                                <td><?php echo $class['name']; ?></td>
                                <td><?php echo $class['description']; ?></td>
                                <td>
                                    <?php 
                                    $count = isset($countMap[$class['id']]) ? $countMap[$class['id']] : 0;
                                    echo $count;
                                    if ($count > 0) {
                                        echo ' <a href="students.php?class_id=' . $class['id'] . '" class="badge bg-info text-decoration-none">View</a>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="classId" value="<?php echo $class['id']; ?>">
                                        <input type="hidden" name="action" value="deleteClass">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this class?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No classes found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card" id="addClassForm">
            <div class="card-header bg-primary text-white">
                <?php echo $editingClass ? 'Edit Class' : 'Add New Class'; ?>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="<?php echo $editingClass ? 'updateClass' : 'addClass'; ?>">
                    
                    <?php if ($editingClass): ?>
                        <input type="hidden" name="classId" value="<?php echo $editingClass['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="className" class="form-label">Class Name</label>
                        <input type="text" class="form-control" id="className" name="className" 
                               value="<?php echo $editingClass ? $editingClass['name'] : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="classDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="classDescription" name="classDescription" rows="3"><?php echo $editingClass ? $editingClass['description'] : ''; ?></textarea>
                    </div>
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editingClass ? 'Update Class' : 'Add Class'; ?>
                        </button>
                        <?php if ($editingClass): ?>
                            <a href="class_management.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>