<?php
// --- index.php (example usage) ---

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
require_once 'student.php';
require_once 'student_repository.php';
require_once 'student_service.php';

use Student\Repository\StudentRepository;
use Student\Service\StudentService;

// Initialize database if needed
if (!file_exists('database_initialized.txt')) {
    require_once 'init_db.php';
    file_put_contents('database_initialized.txt', 'Database has been initialized on ' . date('Y-m-d H:i:s'));
}

// Use both file and database for backward compatibility
$filePath = 'students.json';
$studentRepository = new StudentRepository($filePath);
$studentService = new StudentService($studentRepository);

// Process form submissions
$message = '';
$messageType = '';

// Handle Add Student form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        // Add new student
        $name = $_POST['studentName'];
        $class = $_POST['studentClass'];
        
        // Get the next available ID
        $allStudents = $studentService->getAllStudents();
        $maxId = 0;
        foreach ($allStudents as $student) {
            if ($student->getId() > $maxId) {
                $maxId = $student->getId();
            }
        }
        $newId = $maxId + 1;
        
        if ($studentService->addStudent($newId, $name, $class)) {
            $message = "Student added successfully!";
            $messageType = "success";
        } else {
            $message = "Failed to add student.";
            $messageType = "danger";
        }
    } elseif ($_POST['action'] === 'update') {
        // Update existing student
        $id = $_POST['studentId'];
        $name = $_POST['studentName'];
        $class = $_POST['studentClass'];
        
        if ($studentService->updateStudent($id, $name, $class)) {
            $message = "Student updated successfully!";
            $messageType = "success";
        } else {
            $message = "Failed to update student.";
            $messageType = "danger";
        }
    } elseif ($_POST['action'] === 'delete') {
        // Delete student
        $id = $_POST['studentId'];
        
        if ($studentService->deleteStudent($id)) {
            $message = "Student deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Failed to delete student.";
            $messageType = "danger";
        }
    }
}

// Check if we need to add example students
$exampleStudentsAdded = file_exists('example_students_added.txt');

if (!$exampleStudentsAdded) {
    // Add students
    $studentService->addStudent(1, 'Alice', '10A');
    $studentService->addStudent(2, 'Bob', '11B');
    
    // Mark that we've added the example students
    file_put_contents('example_students_added.txt', 'Example students added on ' . date('Y-m-d H:i:s'));
}

// Get all students
$allStudents = $studentService->getAllStudents();

// Get classes for dropdown
try {
    // Define database constants directly if they don't exist
    if (!defined('DB_HOST')) {
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'student_management');
        define('DB_USER', 'root');
        define('DB_PASS', '');
    }
    
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("SELECT * FROM classes ORDER BY name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently handle error and set empty classes array
    $classes = [];
}

// Filter students based on search and class filter
if ((isset($_GET['search']) && !empty($_GET['search'])) || (isset($_GET['class']) && !empty($_GET['class']))) {
    $filteredStudents = [];
    $searchTerm = isset($_GET['search']) ? strtolower($_GET['search']) : '';
    $classFilter = isset($_GET['class']) ? $_GET['class'] : '';
    
    foreach ($allStudents as $student) {
        $matchesSearch = empty($searchTerm) || 
                        stripos(strtolower($student->getName()), $searchTerm) !== false;
        $matchesClass = empty($classFilter) || 
                        $student->getClass() == $classFilter;
        
        if ($matchesSearch && $matchesClass) {
            $filteredStudents[] = $student;
        }
    }
    
    $allStudents = $filteredStudents;
}

// Check if we're editing a student
$editingStudent = null;
if (isset($_GET['edit'])) {
    $editingStudent = $studentService->getStudentById($_GET['edit']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System</title>
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
        <h1 class="mt-4 mb-4">Student Management System</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>All Students</h2>
                <a href="#addStudentForm" class="btn btn-success">Add New Student</a>
            </div>
            
            <!-- Add search and filter functionality -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Filter Students</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-6">
                            <label for="searchTerm" class="form-label">Search</label>
                            <input type="text" class="form-control" id="searchTerm" name="search" 
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                                   placeholder="Search by name">
                        </div>
                        <div class="col-md-4">
                            <label for="filterClass" class="form-label">Filter by Class</label>
                            <select class="form-select" id="filterClass" name="class">
                                <option value="">All Classes</option>
                                <?php if (!empty($classes)): ?>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['name']; ?>" <?php echo (isset($_GET['class']) && $_GET['class'] == $class['name']) ? 'selected' : ''; ?>>
                                            <?php echo $class['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="Class <?php echo $i; ?>" <?php echo (isset($_GET['class']) && $_GET['class'] == "Class $i") ? 'selected' : ''; ?>>
                                            Class <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <?php if (isset($_GET['search']) || isset($_GET['class'])): ?>
                                <a href="index.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($allStudents) > 0): ?>
                        <?php foreach ($allStudents as $student): ?>
                            <tr>
                                <td><?php echo $student->getId(); ?></td>
                                <td><?php echo $student->getName(); ?></td>
                                <td><?php echo $student->getClass(); ?></td>
                                <td>
                                    <a href="view_student.php?id=<?php echo $student->getId(); ?>" class="btn btn-sm btn-info">View</a>
                                    <a href="?edit=<?php echo $student->getId(); ?>" class="btn btn-sm btn-primary">Edit</a>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="studentId" value="<?php echo $student->getId(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No students found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card" id="addStudentForm">
            <div class="card-header bg-primary text-white">
                <?php echo $editingStudent ? 'Edit Student' : 'Add New Student'; ?>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="<?php echo $editingStudent ? 'update' : 'add'; ?>">
                    
                    <?php if ($editingStudent): ?>
                        <input type="hidden" name="studentId" value="<?php echo $editingStudent->getId(); ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="studentName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="studentName" name="studentName" 
                               value="<?php echo $editingStudent ? $editingStudent->getName() : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="studentClass" class="form-label">Class</label>
                        <select class="form-select" id="studentClass" name="studentClass" required>
                            <option value="" disabled <?php echo !$editingStudent ? 'selected' : ''; ?>>Select a class</option>
                            <?php if (!empty($classes)): ?>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['name']; ?>" <?php echo ($editingStudent && $editingStudent->getClass() == $class['name']) ? 'selected' : ''; ?>>
                                        <?php echo $class['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="Class <?php echo $i; ?>" <?php echo ($editingStudent && $editingStudent->getClass() == "Class $i") ? 'selected' : ''; ?>>
                                        Class <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editingStudent ? 'Update Student' : 'Add Student'; ?>
                        </button>
                        <?php if ($editingStudent): ?>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
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