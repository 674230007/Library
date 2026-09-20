<?php
require_once 'db.php';

class CheckoutReturnProcessing {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getTransactions() {
        $query = "SELECT * FROM transactions ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingTransactions() {
        $query = "SELECT * FROM transactions WHERE approval_status = 'Pending' ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ดึงรายการที่แอดมินอนุมัติแล้วและกำลังยืมอยู่ (Active Borrowed Transactions)
    public function getActiveBorrowedTransactions() {
        $query = "SELECT * FROM transactions WHERE approval_status = 'Approved' AND status = 'Borrowed' ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTransactionsByStudentId($studentId) {
        $query = "SELECT * FROM transactions WHERE student_id = :student_id ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $studentId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function checkoutBook($bookId, $borrowerName, $studentId, $cardImageName, $borrowDays = 3) {
        $checkQuery = "SELECT status, title FROM books WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bindParam(':id', $bookId);
        $stmt->execute();
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($book && $book['status'] === 'Available') {
            $updateQuery = "UPDATE books SET status = 'Borrowed' WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':id', $bookId);
            $updateStmt->execute();

            $borrowDate = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime("+{$borrowDays} days"));

            $insertQuery = "INSERT INTO transactions (book_id, book_title, borrower_name, student_id, student_card_image, status, approval_status, borrow_date, due_date) 
                            VALUES (:book_id, :book_title, :borrower_name, :student_id, :student_card_image, 'Borrowed', 'Pending', :borrow_date, :due_date)";
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindParam(':book_id', $bookId);
            $insertStmt->bindParam(':book_title', $book['title']);
            $insertStmt->bindParam(':borrower_name', $borrowerName);
            $insertStmt->bindParam(':student_id', $studentId);
            $insertStmt->bindParam(':student_card_image', $cardImageName);
            $insertStmt->bindParam(':borrow_date', $borrowDate);
            $insertStmt->bindParam(':due_date', $dueDate);
            return $insertStmt->execute();
        }
        return false;
    }

    public function updateApprovalStatus($transactionId, $bookId, $status) {
        if ($status === 'Approved') {
            $stmt = $this->conn->prepare("UPDATE transactions SET approval_status = 'Approved' WHERE id = :id");
            $stmt->bindParam(':id', $transactionId);
            return $stmt->execute();
        } else {
            $stmt = $this->conn->prepare("UPDATE transactions SET approval_status = 'Rejected', status = 'Returned' WHERE id = :id");
            $stmt->bindParam(':id', $transactionId);
            $stmt->execute();

            $bookStmt = $this->conn->prepare("UPDATE books SET status = 'Available' WHERE id = :book_id");
            $bookStmt->bindParam(':book_id', $bookId);
            return $bookStmt->execute();
        }
    }

    public function returnBook($bookId) {
        $updateQuery = "UPDATE books SET status = 'Available' WHERE id = :id";
        $updateStmt = $this->conn->prepare($updateQuery);
        $updateStmt->bindParam(':id', $bookId);
        $updateStmt->execute();

        $transQuery = "UPDATE transactions SET status = 'Returned' WHERE book_id = :book_id AND status = 'Borrowed'";
        $transStmt = $this->conn->prepare($transQuery);
        $transStmt->bindParam(':book_id', $bookId);
        return $transStmt->execute();
    }
}
?>