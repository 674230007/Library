<?php
require_once 'db.php';

class BookCatalogManagement {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function browseBooks($keyword = "", $category = "") {
        try {
            $sql = "SELECT * FROM books WHERE 1=1";
            $params = [];

            if (!empty(trim($keyword))) {
                $sql .= " AND (title LIKE :keyword OR category LIKE :keyword)";
                $params[':keyword'] = "%" . trim($keyword) . "%";
            }

            if (!empty($category)) {
                $sql .= " AND category = :category";
                $params[':category'] = $category;
            }

            $sql .= " ORDER BY id DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getAllCategories() {
        try {
            $stmt = $this->conn->prepare("SELECT DISTINCT category FROM books ORDER BY category ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getBookById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM books WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function saveBook($id, $title, $category, $imageUrl = "") {
        if (empty($imageUrl)) {
            $imageUrl = "https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=500&q=80";
        }

        if (!empty($id)) {
            $stmt = $this->conn->prepare("UPDATE books SET title = :title, category = :category, image_url = :image_url WHERE id = :id");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':image_url', $imageUrl);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } else {
            $stmt = $this->conn->prepare("INSERT INTO books (title, category, image_url, status) VALUES (:title, :category, :image_url, 'Available')");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':image_url', $imageUrl);
            return $stmt->execute();
        }
    }

    public function deleteBook($id) {
        $stmt = $this->conn->prepare("DELETE FROM books WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>