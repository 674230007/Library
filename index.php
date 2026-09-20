<?php
session_start();
require_once 'catalog.php';
require_once 'transaction.php';

$catalogManager = new BookCatalogManagement();
$transactionManager = new CheckoutReturnProcessing();

// กำหนดสิทธิ์บทบาท (Borrower หรือ Admin)
$currentRole = $_GET['role'] ?? 'Borrower';
if (!in_array($currentRole, ['Borrower', 'Admin'])) {
    $currentRole = 'Borrower';
}

$message = "";
$msgType = "success";

// จัดการการเปลี่ยน Role และระบบรหัสผ่านแอดมิน
if (isset($_GET['role'])) {
    $targetRole = $_GET['role'];
    if ($targetRole === 'Admin') {
        if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
            $currentRole = 'Borrower';
            $showAdminLoginModal = true; 
        } else {
            $currentRole = 'Admin';
        }
    } else {
        unset($_SESSION['admin_auth']);
        $currentRole = 'Borrower';
    }
} else {
    $currentRole = $_SESSION['current_role'] ?? 'Borrower';
}

// ตรวจสอบการยืนยันรหัสผ่านแอดมินผ่าน POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_admin') {
    $adminPassword = $_POST['admin_password'] ?? '';
    $correctPassword = '1234'; 

    if ($adminPassword === $correctPassword) {
        $_SESSION['admin_auth'] = true;
        $_SESSION['current_role'] = 'Admin';
        header("Location: index.php?role=Admin");
        exit;
    } else {
        $message = "รหัสผ่านผู้ดูแลระบบไม่ถูกต้อง! (รหัสผ่านเริ่มต้นคือ: 1234)";
        $msgType = "error";
        $showAdminLoginModal = true;
    }
}

$_SESSION['current_role'] = $currentRole;
$isAdmin = ($currentRole === 'Admin');

// จัดการการส่งฟอร์มอื่นๆ (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'checkout') {
        $bookId = $_POST['book_id'];
        $studentId = trim($_POST['student_id'] ?? '');
        $borrowDays = intval($_POST['borrow_days'] ?? 3);
        
        $cardImageName = "";
        if (isset($_FILES['student_card']) && $_FILES['student_card']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['student_card']['tmp_name'];
            $fileName = $_FILES['student_card']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $newFileName = 'npru_card_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/uploads/';
            
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $dest_path = $uploadFileDir . $newFileName;
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $cardImageName = $newFileName;
            }
        }

        if (empty($studentId)) {
            $message = "กรุณากรอกรหัสนักศึกษา NPRU ก่อนทำรายการยืม";
            $msgType = "error";
        } elseif (!preg_match('/^[0-9]{9,12}$/', $studentId)) {
            $message = "รหัสนักศึกษา NPRU ไม่ถูกต้อง (กรุณากรอกเป็นตัวเลข 9-12 หลัก)";
            $msgType = "error";
        } elseif (empty($cardImageName)) {
            $message = "กรุณาแนบรูปภาพบัตรนักศึกษามหาวิทยาลัยราชภัฏนครปฐมเพื่อยืนยันตัวตน";
            $msgType = "error";
        } elseif ($borrowDays < 1) {
            $message = "กรุณากำหนดจำนวนวันยืมอย่างน้อย 1 วันขึ้นไป";
            $msgType = "error";
        } else {
            if ($transactionManager->checkoutBook($bookId, 'นักศึกษา NPRU (ยืนยันบัตรแล้ว)', $studentId, $cardImageName, $borrowDays)) {
                $message = "ส่งคำขอยืมหนังสือสำเร็จ! (แนบบัตรนักศึกษาเรียบร้อย) รอการอนุมัติจากผู้ดูแลระบบ";
            } else {
                $message = "ไม่สามารถยืมหนังสือเล่มนี้ได้ (อาจถูกยืมไปแล้ว)";
                $msgType = "error";
            }
        }
    } elseif ($action === 'approve_tx') {
        $txId = $_POST['tx_id'];
        $bookId = $_POST['book_id'];
        $decision = $_POST['decision'];
        if ($transactionManager->updateApprovalStatus($txId, $bookId, $decision)) {
            $message = $decision === 'Approved' ? "อนุมัติคำขอยืมหนังสือเรียบร้อยแล้ว" : "ปฏิเสธคำขอยืมหนังสือแล้ว";
        }
    } elseif ($action === 'return') {
        $bookId = $_POST['book_id'];
        if ($transactionManager->returnBook($bookId)) {
            $message = "คืนหนังสือสำเร็จเรียบร้อยแล้ว หนังสือกลับสู่สถานะพร้อมให้บริการ";
        } else {
            $message = "เกิดข้อผิดพลาดในการคืนหนังสือ";
            $msgType = "error";
        }
    } elseif ($action === 'save_book' && $isAdmin) {
        $id = $_POST['book_id'] ?? '';
        $title = trim($_POST['title']);
        $category = trim($_POST['category']);
        $imageUrl = trim($_POST['image_url'] ?? '');
        if (!empty($title) && !empty($category)) {
            $catalogManager->saveBook($id, $title, $category, $imageUrl);
            $message = !empty($id) ? "อัปเดตข้อมูลหนังสือสำเร็จ" : "เพิ่มหนังสือใหม่เข้าแคตตาล็อกสำเร็จ";
        }
    } elseif ($action === 'delete_book' && $isAdmin) {
        $id = $_POST['book_id'];
        $catalogManager->deleteBook($id);
        $message = "ลบหนังสือออกจากระบบสำเร็จ";
    }
}

$searchKeyword = $_GET['search'] ?? '';
$selectedCategory = $_GET['category'] ?? '';
$books = $catalogManager->browseBooks($searchKeyword, $selectedCategory);
$categories = $catalogManager->getAllCategories();
$pendingTransactions = $transactionManager->getPendingTransactions();
$activeBorrowers = $transactionManager->getActiveBorrowedTransactions();

$myStudentId = trim($_GET['my_student_id'] ?? '');
$myBorrowedBooks = [];
if (!empty($myStudentId)) {
    $myBorrowedBooks = $transactionManager->getTransactionsByStudentId($myStudentId);
}

$allBooks = $catalogManager->browseBooks();
$totalBooks = count($allBooks);
$availableBooks = count(array_filter($allBooks, fn($b) => $b['status'] === 'Available'));
$borrowedBooks = $totalBooks - $availableBooks;

$editBook = ['id' => '', 'title' => '', 'category' => '', 'image_url' => ''];
if (isset($_GET['edit_id']) && $isAdmin) {
    $editBook = $catalogManager->getBookById($_GET['edit_id']);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หอสมุดดิจิทัลสำหรับนักศึกษา NPRU</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Font (Prompt) -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style> 
        body { font-family: 'Prompt', sans-serif; } 
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-[#f4f6f9] text-slate-800 min-h-screen flex flex-col selection:bg-orange-500 selection:text-white">

    <!-- Top Navbar NPRU -->
    <header class="bg-gradient-to-r from-orange-600 via-amber-600 to-orange-700 text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-6 py-3.5 flex justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-xl shadow-inner border border-white/30 font-bold">
                    🏛️
                </div>
                <div>
                    <h1 class="text-base font-bold tracking-wide leading-none">หอสมุดดิจิทัลสำหรับนักศึกษา NPRU</h1>
                    <p class="text-[11px] text-orange-100 mt-1">Nakhon Pathom Rajabhat University E-Library</p>
                </div>
            </div>
            
            <div class="flex items-center gap-1 bg-black/15 p-1 rounded-2xl backdrop-blur-md text-xs border border-white/10">
                <a href="?role=Borrower" class="px-4 py-2 rounded-xl font-medium transition-all <?= !$isAdmin ? 'bg-white text-orange-700 font-bold shadow-md' : 'text-orange-100 hover:text-white' ?>">
                    <span class="flex items-center gap-1.5"><i data-lucide="book-open" class="w-3.5 h-3.5"></i> หน้าแรกผู้ใช้</span>
                </a>
                <a href="#" onclick="promptAdminPassword()" class="px-4 py-2 rounded-xl font-medium transition-all <?= $isAdmin ? 'bg-white text-orange-700 font-bold shadow-md' : 'text-orange-100 hover:text-white' ?>">
                    <span class="flex items-center gap-1.5"><i data-lucide="shield-check" class="w-3.5 h-3.5"></i> ผู้ดูแลระบบ 🔒</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Sub Header Status Bar -->
    <div class="bg-white border-b border-slate-200/80 shadow-xs py-2.5">
        <div class="container mx-auto px-6 flex justify-between items-center text-xs text-slate-600">
            <div class="flex items-center gap-2 font-medium">
                <a href="index.php?role=<?= $currentRole ?>" class="text-orange-600 flex items-center gap-1.5 bg-orange-50 px-3 py-1.5 rounded-xl border border-orange-100 font-semibold shadow-2xs">
                    <i data-lucide="home" class="w-3.5 h-3.5"></i> หน้าแรกห้องสมุด
                </a>
                <span class="text-slate-300">|</span>
                <span class="text-slate-500 hidden sm:inline">ระบบยืม-คืนและจัดการสารสนเทศมหาวิทยาลัยราชภัฏนครปฐม</span>
            </div>
            <div class="flex items-center gap-4 text-[11px]">
                <span class="hidden md:inline text-slate-500">📚 หนังสือทั้งหมด: <strong class="text-slate-700"><?= $totalBooks ?></strong> เล่ม</span>
                <span class="text-emerald-600 font-medium">● ระบบฐานข้อมูลเชื่อมต่อปกติ</span>
            </div>
        </div>
    </div>

    <!-- Main Workspace -->
    <main class="container mx-auto px-6 py-8 flex-grow max-w-7xl space-y-8">
        
        <?php if (!empty($message)): ?>
            <div class="p-4 rounded-2xl shadow-md border flex items-center justify-between transition-all duration-300 <?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                <div class="flex items-center gap-3">
                    <i data-lucide="<?= $msgType === 'success' ? 'check-circle-2' : 'alert-circle' ?>" class="w-5 h-5 <?= $msgType === 'success' ? 'text-emerald-600' : 'text-rose-600' ?>"></i>
                    <span class="text-sm font-medium"><?= $message ?></span>
                </div>
                <button onclick="this.parentElement.remove();" class="text-slate-400 hover:text-slate-600 p-1">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Hero Banner NPRU -->
        <div class="p-8 rounded-3xl bg-gradient-to-br from-orange-600 via-amber-600 to-rose-600 text-white shadow-xl relative overflow-hidden flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="absolute -right-12 -bottom-12 w-80 h-80 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 space-y-3 max-w-2xl text-center md:text-left">
                <span class="px-3.5 py-1.5 bg-white/20 backdrop-blur-md rounded-full text-xs font-semibold tracking-wide uppercase border border-white/20 inline-block shadow-sm">
                    🎓 Nakhon Pathom Rajabhat University (NPRU)
                </span>
                <h2 class="text-3xl font-bold tracking-tight leading-snug">
                    <?php if($isAdmin): ?>
                        แผงควบคุมระบบสารสนเทศห้องสมุด NPRU (Admin)
                    <?php else: ?>
                        ค้นหา อ่าน และยืมหนังสืออีบุ๊ก สำหรับนักศึกษา NPRU
                    <?php endif; ?>
                </h2>
                <p class="text-xs text-orange-100 leading-relaxed">
                    <?php if($isAdmin): ?>
                        ตรวจสอบและอนุมัติคำขอยืมหนังสือ พร้อมดูรายชื่อผู้กำลังยืมหนังสือในปัจจุบัน
                    <?php else: ?>
                        บริการยืมหนังสือออนไลน์สำหรับนักศึกษา NPRU ยืนยันตัวตนด้วยรหัสนักศึกษาและแนบรูปบัตรนักศึกษา
                    <?php endif; ?>
                </p>
            </div>

            <div class="relative z-10 grid grid-cols-2 gap-3 w-full md:w-auto">
                <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/20 text-center shadow-inner">
                    <span class="text-2xl font-bold block"><?= $availableBooks ?></span>
                    <span class="text-[11px] text-orange-100">หนังสือพร้อมยืม</span>
                </div>
                <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/20 text-center shadow-inner">
                    <span class="text-2xl font-bold block"><?= $borrowedBooks ?></span>
                    <span class="text-[11px] text-orange-100">กำลังถูกยืม</span>
                </div>
            </div>
        </div>

        <!-- Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- MAIN CONTENT AREA -->
            <div class="<?= $isAdmin ? 'lg:col-span-12' : 'lg:col-span-8' ?> space-y-6">
                
                <!-- ส่วนแอดมิน: อนุมัติคำขอยืม -->
                <?php if ($isAdmin): ?>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-purple-200 bg-gradient-to-br from-purple-50/60 to-white space-y-6">
                    <!-- 1. คำขอที่รออนุมัติ -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 bg-purple-100 text-purple-700 rounded-2xl shadow-2xs">
                                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-base">ระบบอนุมัติคำขอยืมหนังสือนักศึกษา NPRU</h3>
                                    <p class="text-xs text-slate-500">ตรวจสอบรหัสนักศึกษาและคลิกดูรูปภาพบัตรนักศึกษาก่อนอนุมัติ</p>
                                </div>
                            </div>
                            <span class="text-xs font-semibold bg-purple-100 text-purple-700 px-3.5 py-1 rounded-full">Admin Control</span>
                        </div>

                        <?php if (empty($pendingTransactions)): ?>
                            <div class="p-6 text-center text-slate-400 bg-white rounded-2xl border border-slate-100 text-xs shadow-2xs">
                                ไม่มีรายการคำขอยืมหนังสือที่รออนุมัติในขณะนี้
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($pendingTransactions as $ptx): ?>
                                    <div class="bg-white p-4.5 rounded-2xl border border-purple-100 shadow-sm flex flex-col justify-between gap-3 text-xs">
                                        <div class="space-y-1.5">
                                            <div class="flex justify-between items-start font-bold text-slate-800">
                                                <span class="line-clamp-1"><?= htmlspecialchars($ptx['book_title']) ?></span>
                                                <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[10px] font-semibold">รออนุมัติ</span>
                                            </div>
                                            <p class="text-slate-600">ผู้ยืม: <strong class="text-slate-800"><?= htmlspecialchars($ptx['borrower_name']) ?></strong></p>
                                            <p class="text-slate-500">รหัสนักศึกษา NPRU: <strong class="text-purple-600"><?= htmlspecialchars($ptx['student_id']) ?></strong></p>
                                            <p class="text-slate-400 text-[11px]">กำหนดคืน: <?= $ptx['due_date'] ?></p>
                                            
                                            <?php if (!empty($ptx['student_card_image'])): ?>
                                                <div class="pt-1">
                                                    <button onclick="openCardModal('./uploads/<?= htmlspecialchars($ptx['student_card_image']) ?>', '<?= htmlspecialchars($ptx['student_id']) ?>')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-xl font-semibold transition border border-purple-200 shadow-2xs">
                                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> ตรวจสอบรูปบัตรนักศึกษา
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <form method="POST" class="flex gap-2 pt-2 border-t border-slate-100">
                                            <input type="hidden" name="action" value="approve_tx">
                                            <input type="hidden" name="tx_id" value="<?= $ptx['id'] ?>">
                                            <input type="hidden" name="book_id" value="<?= $ptx['book_id'] ?>">
                                            <button type="submit" name="decision" value="Approved" class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-medium transition shadow-sm">อนุมัติ</button>
                                            <button type="submit" name="decision" value="Rejected" class="flex-1 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-medium transition shadow-sm">ปฏิเสธ</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 2. รายชื่อผู้ที่กำลังยืมหนังสืออยู่ในปัจจุบัน (Active Borrowers List) -->
                    <div class="pt-6 border-t border-purple-100">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2.5 bg-emerald-100 text-emerald-700 rounded-2xl shadow-2xs">
                                <i data-lucide="users" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">รายชื่อผู้ที่กำลังยืมหนังสือในปัจจุบัน (Active Borrowers)</h3>
                                <p class="text-xs text-slate-500">แสดงรายชื่อนักศึกษาที่ยืมหนังสือไปและยังไม่ได้คืน</p>
                            </div>
                        </div>

                        <?php if (empty($activeBorrowers)): ?>
                            <div class="p-6 text-center text-slate-400 bg-white rounded-2xl border border-slate-100 text-xs shadow-2xs">
                                ไม่มีนักศึกษาที่กำลังยืมหนังสืออยู่ในขณะนี้
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto rounded-2xl border border-purple-100 bg-white">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-purple-50/70 text-purple-900 font-semibold uppercase text-[11px]">
                                            <th class="p-3.5">ชื่อหนังสือ</th>
                                            <th class="p-3.5">รหัสนักศึกษา NPRU</th>
                                            <th class="p-3.5">วันที่ยืม</th>
                                            <th class="p-3.5">กำหนดคืน</th>
                                            <th class="p-3.5 text-center">หลักฐานบัตร</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-purple-50">
                                        <?php foreach ($activeBorrowers as $ab): ?>
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="p-3.5 font-bold text-slate-800"><?= htmlspecialchars($ab['book_title']) ?></td>
                                                <td class="p-3.5 text-purple-600 font-semibold"><?= htmlspecialchars($ab['student_id']) ?></td>
                                                <td class="p-3.5 text-slate-500"><?= htmlspecialchars($ab['borrow_date']) ?></td>
                                                <td class="p-3.5 text-rose-600 font-medium"><?= htmlspecialchars($ab['due_date']) ?></td>
                                                <td class="p-3.5 text-center">
                                                    <?php if (!empty($ab['student_card_image'])): ?>
                                                        <button onclick="openCardModal('./uploads/<?= htmlspecialchars($ab['student_card_image']) ?>', '<?= htmlspecialchars($ab['student_id']) ?>')" class="px-2.5 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg font-medium transition border border-purple-200 inline-flex items-center gap-1">
                                                            <i data-lucide="eye" class="w-3 h-3"></i> ดูรูปบัตร
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-slate-400">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Books Catalog Section -->
                <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-slate-200/80 space-y-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 bg-orange-50 text-orange-600 rounded-2xl shadow-2xs">
                                <i data-lucide="book-open" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">คลังหนังสือแนะนำ NPRU (Featured E-Books)</h3>
                                <p class="text-xs text-slate-400">เลือกหนังสือในแคตตาล็อกเพื่ออ่านหรือทำรายการยืม</p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold bg-orange-50 text-orange-600 px-3.5 py-1 rounded-full border border-orange-100 shadow-2xs">Member 1 Module</span>
                    </div>

                    <form method="GET" class="space-y-4">
                        <input type="hidden" name="role" value="<?= htmlspecialchars($currentRole) ?>">
                        <input type="hidden" name="my_student_id" value="<?= htmlspecialchars($myStudentId) ?>">
                        
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="relative flex-grow">
                                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-4 top-3.5"></i>
                                <input type="text" name="search" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="ค้นหาชื่อหนังสือ หรือหมวดหมู่..." 
                                       class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 focus:bg-white transition shadow-2xs">
                            </div>
                            <div class="sm:w-56">
                                <select name="category" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 focus:bg-white transition shadow-2xs">
                                    <option value="">ทุกหมวดหมู่หนังสือ</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="w-full sm:w-auto bg-orange-600 hover:bg-orange-700 text-white font-medium py-3 px-6 rounded-2xl text-xs transition shadow-md shadow-orange-100 flex items-center justify-center gap-2">
                                    <i data-lucide="search" class="w-4 h-4"></i> ค้นหา
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($books)): ?>
                        <div class="p-16 text-center text-slate-400 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                            <i data-lucide="book-x" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                            <p class="text-sm font-semibold text-slate-600">ไม่พบรายการหนังสือที่คุณค้นหา</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                            <?php foreach ($books as $book): ?>
                                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col justify-between group">
                                    <div>
                                        <div class="h-52 overflow-hidden bg-slate-100 relative">
                                            <img src="<?= htmlspecialchars($book['image_url']) ?>" alt="Book Cover" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                            <div class="absolute top-3 right-3">
                                                <?php if ($book['status'] === 'Available'): ?>
                                                    <span class="px-3 py-1 bg-emerald-500/90 backdrop-blur-md text-white text-[10px] font-bold rounded-full shadow-md">พร้อมให้ยืม</span>
                                                <?php else: ?>
                                                    <span class="px-3 py-1 bg-rose-500/90 backdrop-blur-md text-white text-[10px] font-bold rounded-full shadow-md">ไม่ว่าง</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="p-4 space-y-1.5">
                                            <span class="text-[10px] font-bold text-orange-600 bg-orange-50 px-2.5 py-0.5 rounded-full border border-orange-100 inline-block">
                                                <?= htmlspecialchars($book['category']) ?>
                                            </span>
                                            <h4 class="font-bold text-slate-800 text-sm line-clamp-1 pt-0.5"><?= htmlspecialchars($book['title']) ?></h4>
                                        </div>
                                    </div>

                                    <div class="p-4 pt-0">
                                        <?php if (!$isAdmin): ?>
                                            <?php if ($book['status'] === 'Available'): ?>
                                                <button onclick="openCheckoutModal(<?= $book['id'] ?>, '<?= htmlspecialchars($book['title'], ENT_QUOTES) ?>')" class="w-full py-2.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-xl transition shadow-md shadow-orange-100 flex items-center justify-center gap-1.5">
                                                    <i data-lucide="bookmark-plus" class="w-4 h-4"></i> ยืมหนังสือ (Borrow)
                                                </button>
                                            <?php else: ?>
                                                <button disabled class="w-full py-2.5 bg-slate-100 text-slate-400 text-xs font-medium rounded-xl cursor-not-allowed">ถูกยืมไปแล้ว</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="flex gap-2">
                                                <a href="?role=Admin&edit_id=<?= $book['id'] ?>#catalog-form" class="flex-1 py-2 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold rounded-xl text-center transition border border-amber-200/50">แก้ไข</a>
                                                <form method="POST" class="flex-1" onsubmit="return confirm('ยืนยันการลบหนังสือเล่มนี้ออกจากระบบ?');">
                                                    <input type="hidden" name="action" value="delete_book">
                                                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                                    <button type="submit" class="w-full py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold rounded-xl transition border border-rose-200/50">ลบ</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($isAdmin): ?>
                <div id="catalog-form" class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-slate-200/80 space-y-6">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 bg-purple-50 text-purple-600 rounded-2xl shadow-2xs">
                                <i data-lucide="folder-cog" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">จัดการแคตตาล็อกหนังสือ (Update Catalog)</h3>
                                <p class="text-xs text-slate-400">เพิ่มหรือแก้ไขรายการหนังสือและกำหนดลิงก์รูปภาพปก</p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold bg-purple-50 text-purple-600 px-3.5 py-1 rounded-full border border-purple-100 shadow-2xs">Admin Module</span>
                    </div>

                    <form method="POST" class="grid grid-cols-1 md:grid-cols-12 gap-4 text-xs">
                        <input type="hidden" name="action" value="save_book">
                        <input type="hidden" name="book_id" value="<?= $editBook['id'] ?>">
                        
                        <div class="md:col-span-4">
                            <label class="block font-medium text-slate-600 mb-1.5">ชื่อหนังสือ</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($editBook['title']) ?>" placeholder="ระบุชื่อหนังสือ..." required 
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-purple-400 transition shadow-2xs">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block font-medium text-slate-600 mb-1.5">หมวดหมู่</label>
                            <input type="text" name="category" value="<?= htmlspecialchars($editBook['category']) ?>" placeholder="ระบุหมวดหมู่..." required 
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-purple-400 transition shadow-2xs">
                        </div>
                        <div class="md:col-span-5">
                            <label class="block font-medium text-slate-600 mb-1.5">ลิงก์รูปภาพปกหนังสือ (Image URL)</label>
                            <input type="url" name="image_url" value="<?= htmlspecialchars($editBook['image_url'] ?? '') ?>" placeholder="https://images.unsplash.com/..." 
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-purple-400 transition shadow-2xs">
                        </div>
                        <div class="md:col-span-12 flex justify-end gap-3 pt-3">
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-8 rounded-2xl transition shadow-md shadow-purple-100 flex items-center gap-2">
                                <i data-lucide="<?= !empty($editBook['id']) ? 'save' : 'plus-circle' ?>" class="w-4 h-4"></i>
                                <?= !empty($editBook['id']) ? 'บันทึกการแก้ไข' : 'เพิ่มหนังสือใหม่เข้าคลัง' ?>
                            </button>
                            <?php if(!empty($editBook['id'])): ?>
                                <a href="index.php?role=Admin" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-6 py-3 rounded-2xl font-medium transition flex items-center justify-center">ยกเลิก</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

            </div>

            <!-- SIDEBAR: STUDENT ID VERIFICATION NPRU -->
            <?php if (!$isAdmin): ?>
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200/80 space-y-5 sticky top-24">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 bg-orange-50 text-orange-600 rounded-2xl shadow-2xs">
                                <i data-lucide="user-check" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">ตรวจสอบหนังสือที่ยืม (NPRU)</h3>
                                <p class="text-xs text-slate-400">ยืนยันตัวตนด้วยรหัสนักศึกษา NPRU</p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold bg-orange-50 text-orange-600 px-3 py-1 rounded-full border border-orange-100 shadow-2xs">NPRU Student</span>
                    </div>

                    <form method="GET" class="space-y-3 text-xs">
                        <input type="hidden" name="role" value="Borrower">
                        <div>
                            <label class="block font-medium text-slate-600 mb-1.5">รหัสนักศึกษา NPRU ของคุณ</label>
                            <div class="relative">
                                <i data-lucide="id-card" class="w-4 h-4 text-slate-400 absolute left-4 top-3.5"></i>
                                <input type="text" name="my_student_id" value="<?= htmlspecialchars($myStudentId) ?>" placeholder="เช่น 674230007" required
                                       class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:bg-white transition shadow-2xs">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-medium py-3 rounded-2xl transition shadow-md shadow-orange-100 flex items-center justify-center gap-2">
                            <i data-lucide="search" class="w-4 h-4"></i> ค้นหาหนังสือที่ฉันยืม
                        </button>
                    </form>

                    <div class="space-y-3 max-h-[450px] overflow-y-auto pr-1 text-xs">
                        <?php if (empty($myStudentId)): ?>
                            <div class="p-6 text-center text-slate-400 bg-slate-50/70 rounded-2xl border border-dashed border-slate-200">
                                <i data-lucide="info" class="w-8 h-8 text-slate-300 mx-auto mb-2"></i>
                                <p class="text-xs">กรอกรหัสนักศึกษา NPRU แล้วกดค้นหา เพื่อตรวจสอบสถานะการยืมและกดคืนหนังสือ</p>
                            </div>
                        <?php elseif (empty($myBorrowedBooks)): ?>
                            <div class="p-6 text-center text-slate-400 bg-slate-50 rounded-2xl border border-slate-100">
                                <p class="text-xs font-medium">ไม่พบรายการยืมหนังสือภายใต้รหัส: <span class="text-orange-600 font-bold"><?= htmlspecialchars($myStudentId) ?></span></p>
                            </div>
                        <?php else: ?>
                            <p class="font-bold text-slate-600 mb-2">ผลการค้นหาสำหรับรหัส: <span class="text-orange-600"><?= htmlspecialchars($myStudentId) ?></span></p>
                            <?php foreach ($myBorrowedBooks as $tx): 
                                $isPending = $tx['approval_status'] === 'Pending';
                                $isApproved = $tx['approval_status'] === 'Approved';
                            ?>
                                <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl transition space-y-2 shadow-2xs">
                                    <div class="flex justify-between items-start gap-2">
                                        <span class="font-bold text-slate-800 line-clamp-1"><?= htmlspecialchars($tx['book_title']) ?></span>
                                        <?php if ($isPending): ?>
                                            <span class="px-2.5 py-0.5 bg-amber-100 text-amber-700 text-[10px] rounded-full font-semibold">รออนุมัติ</span>
                                        <?php elseif ($isApproved): ?>
                                            <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] rounded-full font-semibold">อนุมัติแล้ว</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-0.5 bg-slate-200 text-slate-600 text-[10px] rounded-full font-semibold">คืนแล้ว</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="space-y-1 text-slate-500 text-[11px]">
                                        <p class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> วันยืม: <?= $tx['borrow_date'] ?></p>
                                        <p class="flex items-center gap-1.5 text-rose-600 font-medium"><i data-lucide="calendar-clock" class="w-3.5 h-3.5"></i> กำหนดคืน: <?= $tx['due_date'] ?? '-' ?></p>
                                    </div>

                                    <?php if ($isApproved && $tx['status'] === 'Borrowed'): ?>
                                        <form method="POST" class="pt-1">
                                            <input type="hidden" name="action" value="return">
                                            <input type="hidden" name="book_id" value="<?= $tx['book_id'] ?>">
                                            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-xl transition shadow-sm flex items-center justify-center gap-1.5">
                                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> คืนหนังสือ (Return)
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Modal ยืมหนังสือ NPRU -->
    <div id="checkoutModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl border border-slate-100 overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="bg-gradient-to-r from-orange-600 to-amber-600 p-6 text-white flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <i data-lucide="calendar-plus" class="w-5 h-5 text-orange-100"></i>
                    <h3 class="font-bold text-base">ยืมหนังสือออนไลน์ (NPRU Student)</h3>
                </div>
                <button onclick="closeCheckoutModal()" class="text-white/80 hover:text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4 text-xs">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="book_id" id="modalBookId">
                
                <div>
                    <label class="block font-medium text-slate-600 mb-1">ชื่อหนังสือที่ต้องการยืม</label>
                    <input type="text" id="modalBookTitle" disabled class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-2xl font-semibold text-slate-700 shadow-2xs">
                </div>

                <div>
                    <label class="block font-medium text-slate-600 mb-1">รหัสประจำตัวนักศึกษามหาวิทยาลัยราชภัฏนครปฐม (NPRU) <span class="text-rose-500">*</span></label>
                    <input type="text" name="student_id" placeholder="เช่น 674230007" required pattern="[0-9]{9,12}" title="กรุณากรอกรหัสนักศึกษาเป็นตัวเลข 9-12 หลัก"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-orange-500 transition shadow-2xs">
                </div>

                <div>
                    <label class="block font-medium text-slate-600 mb-1">แนบรูปถ่ายบัตรนักศึกษา NPRU (Student ID Card) <span class="text-rose-500">*</span></label>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-slate-300 border-dashed rounded-2xl cursor-pointer bg-slate-50 hover:bg-slate-100 transition">
                            <div class="flex flex-col items-center justify-center pt-3 pb-4">
                                <i data-lucide="upload-cloud" class="w-6 h-6 text-slate-400 mb-1"></i>
                                <p class="mb-1 text-[11px] text-slate-500"><span class="font-semibold">คลิกเพื่ออัปโหลดรูปบัตร</span> หรือลากไฟล์มาวาง</p>
                                <p class="text-[10px] text-slate-400">PNG, JPG หรือ JPEG (ขนาดไม่เกิน 5MB)</p>
                            </div>
                            <input type="file" name="student_card" accept="image/*" required class="hidden" onchange="updateFileName(this)" />
                        </label>
                    </div>
                    <p id="fileNameDisplay" class="text-[11px] text-emerald-600 font-medium mt-1 truncate"></p>
                </div>

                <div>
                    <label class="block font-medium text-slate-600 mb-1">ต้องการยืมกี่วัน (พิมพ์ระบุจำนวนวันได้เลย)</label>
                    <div class="relative">
                        <i data-lucide="clock" class="w-4 h-4 text-slate-400 absolute left-4 top-3.5"></i>
                        <input type="number" name="borrow_days" value="3" min="1" max="365" required
                               class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-orange-500 transition shadow-2xs" placeholder="เช่น 3 วัน">
                    </div>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" onclick="closeCheckoutModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium py-3 rounded-2xl transition">ยกเลิก</button>
                    <button type="submit" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white font-medium py-3 rounded-2xl transition shadow-md shadow-orange-100">ส่งคำขอยืม</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal สำหรับแสดงรูปภาพบัตรนักศึกษาขนาดใหญ่ (Image Preview Modal) -->
    <div id="cardPreviewModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden animate-in fade-in zoom-in duration-200 flex flex-col">
            <div class="bg-gradient-to-r from-purple-700 to-indigo-700 p-4 text-white flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <i data-lucide="id-card" class="w-5 h-5 text-purple-200"></i>
                    <h3 class="font-bold text-sm">หลักฐานบัตรนักศึกษา NPRU (รหัส: <span id="previewStudentId"></span>)</h3>
                </div>
                <button onclick="closeCardModal()" class="text-white/80 hover:text-white p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6 bg-slate-900 flex items-center justify-center overflow-auto max-h-[70vh]">
                <img id="previewCardImage" src="" alt="Student ID Card" class="max-w-full max-h-[60vh] object-contain rounded-xl shadow-lg border border-slate-700">
            </div>
            <div class="p-4 bg-white border-t border-slate-100 flex justify-end">
                <button onclick="closeCardModal()" class="px-6 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium text-xs rounded-xl transition">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- Modal ยืนยันรหัสผ่านแอดมิน -->
    <div id="adminPasswordModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 <?= isset($showAdminLoginModal) ? '' : 'hidden' ?>">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl border border-slate-100 overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="bg-gradient-to-r from-purple-700 to-indigo-700 p-6 text-white flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <i data-lucide="lock" class="w-5 h-5 text-purple-200"></i>
                    <h3 class="font-bold text-base">ยืนยันสิทธิ์ผู้ดูแลระบบ NPRU (Admin Access)</h3>
                </div>
                <a href="index.php?role=Borrower" class="text-white/80 hover:text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>
            
            <form method="POST" class="p-6 space-y-4 text-xs">
                <input type="hidden" name="action" value="verify_admin">
                
                <div class="p-4 bg-purple-50 text-purple-800 rounded-2xl border border-purple-100 space-y-1">
                    <p class="font-bold text-sm">🔒 ยืนยันตัวตนผู้ดูแลระบบห้องสมุด</p>
                    <p class="text-purple-600">กรุณากรอกรหัสผ่านเพื่อเข้าสู่โหมด Admin (รหัสผ่านเริ่มต้น: <strong class="text-purple-900">1234</strong>)</p>
                </div>

                <div>
                    <label class="block font-medium text-slate-600 mb-1">รหัสผ่านแอดมิน (Admin Password)</label>
                    <input type="password" name="admin_password" placeholder="••••" required autofocus
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-purple-500 transition shadow-2xs">
                </div>

                <div class="pt-2 flex gap-3">
                    <a href="index.php?role=Borrower" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium py-3 rounded-2xl transition text-center flex items-center justify-center">ยกเลิก</a>
                    <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 rounded-2xl transition shadow-md shadow-purple-100">ยืนยันรหัสผ่าน</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="bg-white border-t border-slate-200/85 py-8 text-center text-xs text-slate-400">
        Nakhon Pathom Rajabhat University E-Library Portal &copy; 2026 - Active Borrowers List Enabled
    </footer>

    <script>
        lucide.createIcons();

        function openCheckoutModal(bookId, bookTitle) {
            document.getElementById('modalBookId').value = bookId;
            document.getElementById('modalBookTitle').value = bookTitle;
            document.getElementById('checkoutModal').classList.remove('hidden');
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').classList.add('hidden');
        }

        function promptAdminPassword() {
            document.getElementById('adminPasswordModal').classList.remove('hidden');
        }

        function updateFileName(input) {
            const display = document.getElementById('fileNameDisplay');
            if (input.files && input.files[0]) {
                display.innerText = "📁 ไฟล์ที่เลือก: " + input.files[0].name;
            } else {
                display.innerText = "";
            }
        }

        function openCardModal(imageSrc, studentId) {
            document.getElementById('previewCardImage').src = imageSrc;
            document.getElementById('previewStudentId').innerText = studentId;
            document.getElementById('cardPreviewModal').classList.remove('hidden');
        }

        function closeCardModal() {
            document.getElementById('cardPreviewModal').classList.add('hidden');
        }
    </script>
</body>
</html>