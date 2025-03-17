<?php
namespace Student\Service;

use Student\Model\Student;
use Student\Repository\StudentRepository;

class StudentService {
    private $studentRepository;

    public function __construct(StudentRepository $studentRepository) {
        $this->studentRepository = $studentRepository;
    }

    public function getAllStudents() {
        return $this->studentRepository->getAllStudents();
    }

    public function getStudentById($id) {
        return $this->studentRepository->getStudentById($id);
    }

    public function addStudent($id, $name, $class) {
        $student = new Student($id, $name, $class);
        return $this->studentRepository->addStudent($student);
    }

    public function updateStudent($id, $name, $class) {
        $student = new Student($id, $name, $class);
        return $this->studentRepository->updateStudent($student);
    }

    public function deleteStudent($id) {
        return $this->studentRepository->deleteStudent($id);
    }
}
?>