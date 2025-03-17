<?php
// --- view_student.php ---

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

// Initialize repository and service
$filePath = 'students.json';
$studentRepository = new StudentRepository($filePath);
$studentService = new StudentService($studentRepository);

// Get student ID from URL parameter
$studentId = isset($_GET['id']) ? $_GET['id'] : null;

// Redirect to index if no ID provided
if ($studentId === null) {
    header('Location: index.php');
    exit;
}

// Get student details
$student = $studentService->getStudentById($studentId);

// Redirect to index if student not found
if ($student === null) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - Student Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 800px;
        }
        .student-details {
            margin-top: 30px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mt-4 mb-4">Student Details</h1>
        
        <div class="card student-details">
            <div class="card-header bg-info text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <span>Student Information</span>
                    <div>
                        <a href="class_management.php" class="btn btn-sm btn-warning me-2">Manage Classes</a>
                        <a href="index.php" class="btn btn-sm btn-light">Back to List</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th style="width: 30%">Student ID</th>
                            <td><?php echo $student->getId(); ?></td>
                        </tr>
                        <tr>
                            <th>Name</th>
                            <td><?php echo $student->getName(); ?></td>
                        </tr>
                        <tr>
                            <th>Class</th>
                            <td><?php echo $student->getClass(); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <a href="index.php?edit=<?php echo $student->getId(); ?>" class="btn btn-warning">Edit Student</a>
                <form method="post" action="index.php" style="display: inline-block; margin-left: 10px;">
                    <input type="hidden" name="studentId" value="<?php echo $student->getId(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this student?')">Delete Student</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>