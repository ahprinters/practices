<?php
namespace Student\Model;

class Student {
    private $id;
    private $name;
    private $class;

    public function __construct($id, $name, $class) {
        $this->id = $id;
        $this->name = $name;
        $this->class = $class;
    }

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getClass() {
        return $this->class;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setClass($class) {
        $this->class = $class;
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'class' => $this->class,
        ];
    }
}
?>