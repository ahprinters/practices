<?php
namespace Student\Repository;

class ClassRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAllClasses() {
        $stmt = $this->db->query("SELECT * FROM classes ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getClassById($id) {
        $stmt = $this->db->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function addClass($name, $description) {
        $stmt = $this->db->prepare("INSERT INTO classes (name, description) VALUES (?, ?)");
        return $stmt->execute([$name, $description]);
    }

    public function updateClass($id, $name, $description) {
        $stmt = $this->db->prepare("UPDATE classes SET name = ?, description = ? WHERE id = ?");
        return $stmt->execute([$name, $description, $id]);
    }

    public function deleteClass($id) {
        // First check if any students are using this class
        if ($this->hasStudents($id)) {
            return false; // Cannot delete class with students
        }
        
        $stmt = $this->db->prepare("DELETE FROM classes WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function hasStudents($id) {
        $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
        $checkStmt->execute([$id]);
        return $checkStmt->fetchColumn() > 0;
    }
    
    // New methods for filtering and improved UX
    
    public function searchClasses($searchTerm) {
        $searchTerm = "%$searchTerm%";
        $stmt = $this->db->prepare("SELECT * FROM classes WHERE name LIKE ? OR description LIKE ? ORDER BY name");
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getStudentsByClassId($classId) {
        $stmt = $this->db->prepare("SELECT * FROM students WHERE class_id = ? ORDER BY name");
        $stmt->execute([$classId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getStudentCountByClass() {
        $stmt = $this->db->query("SELECT c.id, c.name, COUNT(s.id) as student_count 
                                 FROM classes c 
                                 LEFT JOIN students s ON c.id = s.class_id 
                                 GROUP BY c.id, c.name 
                                 ORDER BY c.name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getClassesForDropdown() {
        $stmt = $this->db->query("SELECT id, name FROM classes ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}