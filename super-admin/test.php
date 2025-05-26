<?php
session_start();
require_once '../includes/db-conn.php';

// Fetch user details (optional)
$user_id = $_SESSION['admin_id'] ?? 0;
$sql = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_user = $stmt->get_result();
$user = $result_user->fetch_assoc();
$stmt->close();

// Add return book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_number'])) {
    $book_number = trim($_POST['book_number']);
    if (!empty($book_number)) {
        $stmt = $conn->prepare("INSERT INTO return_books (book_number) VALUES (?)");
        $stmt->bind_param("s", $book_number);
        if ($stmt->execute()) {
            $_SESSION['status'] = 'success';
            $_SESSION['message'] = "Return book number added.";
        } else {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: pages-return-books.php");
    exit();
}

// Edit return book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['edit_book_number'])) {
    $edit_id = intval($_POST['edit_id']);
    $edit_book_number = trim($_POST['edit_book_number']);

    if (!empty($edit_book_number)) {
        $stmt = $conn->prepare("UPDATE return_books SET book_number = ? WHERE id = ?");
        $stmt->bind_param("si", $edit_book_number, $edit_id);
        if ($stmt->execute()) {
            $_SESSION['status'] = 'success';
            $_SESSION['message'] = "Return book number updated.";
        } else {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = "Error updating: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: pages-return-books.php");
    exit();
}

// Toggle status
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE return_books SET status = IF(status='available','disabled','available') WHERE id = $id");
    header("Location: pages-return-books.php");
    exit();
}

// Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM return_books WHERE id = $id");
    header("Location: pages-return-books.php");
    exit();
}

// Fetch all return books
$result = $conn->query("SELECT * FROM return_books ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Return Books</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
</head>

<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<body>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Return Books</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                <li class="breadcrumb-item">Pages</li>
                <li class="breadcrumb-item active">Manage Return Books</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body pt-4">
                <div class="container mt-1">
                    <h4 class="mb-4">Manage Return Books</h4>

                    <form method="POST" class="d-flex mb-4">
                        <input type="text" name="book_number" class="form-control me-2" placeholder="Enter Book Number" required>
                        <button type="submit" class="btn btn-success">Add</button>
                    </form>

                    <?php if (isset($_SESSION['status'])): ?>
                        <div class="alert alert-<?= $_SESSION['status'] === 'success' ? 'success' : 'danger' ?>">
                            <?= $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['status'], $_SESSION['message']); ?>
                    <?php endif; ?>

                    <table class="table table-bordered table-striped text-center align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Book Number</th>
                                <th>Created At</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="<?= $row['status'] === 'disabled' ? 'table-danger' : '' ?>">
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['book_number']) ?></td>
                                <td><?= $row['created_at'] ?></td>
                                <td>
                                    <?php if ($row['status'] === 'available'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?toggle=<?= $row['id'] ?>" class="btn btn-sm <?= $row['status'] === 'available' ? 'btn-danger' : 'btn-success' ?>">
                                        <?= $row['status'] === 'available' ? 'Disable' : 'Activate' ?>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">Edit</button>
                                    <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this book?')" class="btn btn-sm btn-outline-danger">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Edit Modals -->
<?php
$result->data_seek(0);
while ($row = $result->fetch_assoc()):
?>
<div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $row['id'] ?>" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel<?= $row['id'] ?>">Edit Return Book Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_id" value="<?= $row['id'] ?>">
                <div class="mb-3">
                    <label class="form-label">Book Number</label>
                    <input type="text" name="edit_book_number" value="<?= htmlspecialchars($row['book_number']) ?>" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>
<?php endwhile; ?>

<?php include_once("../includes/footer.php"); ?>
<?php include_once("../includes/js-links-inc.php"); ?>
</body>
</html>
