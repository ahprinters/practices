<?php
namespace Student\Repository;

use Student\Model\Student;
use Student\Config\Database;

class StudentRepository {
    private $db;
    private $filePath;
    private $students = [];

    public function __construct($filePath = null) {
        $this->db = Database::getConnection();
        $this->filePath = $filePath;
        
        // Create the students table if it doesn't exist
        $this->initializeTable();
        
        // For backward compatibility, load data from file if provided
        if ($filePath) {
            $this->loadData();
        }
    }

    private function initializeTable() {
        $sql = "CREATE TABLE IF NOT EXISTS students (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            class VARCHAR(50) NOT NULL
        )";
        $this->db->exec($sql);
    }

    private function loadData() {
        if (file_exists($this->filePath)) {
            $jsonData = file_get_contents($this->filePath);
            $data = json_decode($jsonData, true);
            if (is_array($data)) {
                foreach ($data as $studentData) {
                    // Check if student already exists in database
                    $stmt = $this->db->prepare("SELECT * FROM students WHERE id = ?");
                    $stmt->execute([$studentData['id']]);
                    
                    if (!$stmt->fetch()) {
                        // Add to database if not exists
                        $this->addStudent(new Student(
                            $studentData['id'],
                            $studentData['name'],
                            $studentData['class']
                        ));
                    }
                }
            }
        }
    }

    private function saveData() {
        if ($this->filePath) {
            $students = $this->getAllStudents();
            $studentData = array_map(function ($student) {
                return $student->toArray();
            }, $students);
            $jsonData = json_encode($studentData, JSON_PRETTY_PRINT);
            file_put_contents($this->filePath, $jsonData);
        }
    }

    public function getAllStudents() {
        $stmt = $this->db->query("SELECT * FROM students");
        $students = [];
        
        while ($row = $stmt->fetch()) {
            $students[] = new Student($row['id'], $row['name'], $row['class']);
        }
        
        return $students;
    }

    public function getStudentById($id) {
        $stmt = $this->db->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        
        if ($row) {
            return new Student($row['id'], $row['name'], $row['class']);
        }
        
        return null;
    }

    public function addStudent(Student $student) {
        // Check if student already exists
        $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM students WHERE id = ?");
        $checkStmt->execute([$student->getId()]);
        $exists = (int)$checkStmt->fetchColumn() > 0;
        
        if ($exists) {
            // Update instead of insert if student already exists
            return $this->updateStudent($student);
        } else {
            // Insert new student
            $stmt = $this->db->prepare("INSERT INTO students (id, name, class) VALUES (?, ?, ?)");
            $result = $stmt->execute([
                $student->getId(),
                $student->getName(),
                $student->getClass()
            ]);
            
            if ($result && $this->filePath) {
                $this->saveData();
            }
            
            return $result;
        }
    }

    public function updateStudent(Student $updatedStudent) {
        $stmt = $this->db->prepare("UPDATE students SET name = ?, class = ? WHERE id = ?");
        $result = $stmt->execute([
            $updatedStudent->getName(),
            $updatedStudent->getClass(),
            $updatedStudent->getId()
        ]);
        
        if ($result && $this->filePath) {
            $this->saveData();
        }
        
        return $result;
    }

    public function deleteStudent($id) {
        $stmt = $this->db->prepare("DELETE FROM students WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result && $this->filePath) {
            $this->saveData();
        }
        
        return $result;
    }
}
?>