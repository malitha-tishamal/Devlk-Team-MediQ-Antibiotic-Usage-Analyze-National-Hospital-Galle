<?php
session_start();
require_once 'includes/db-conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();


// Add book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_number'])) {
    $book_number = trim($_POST['book_number']);
    if (!empty($book_number)) {
        $stmt = $conn->prepare("INSERT INTO book_transactions (book_number) VALUES (?)");
        $stmt->bind_param("s", $book_number);
        if ($stmt->execute()) {
            $_SESSION['status'] = 'success';
            $_SESSION['message'] = "Book added.";
        } else {
            $_SESSION['status'] = 'danger';
            $_SESSION['message'] = "Insert error: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: book-transactions.php");
    exit();
}

// Edit book number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['edit_book_number'])) {
    $edit_id = intval($_POST['edit_id']);
    $edit_book_number = trim($_POST['edit_book_number']);

    if (!empty($edit_book_number)) {
        $stmt = $conn->prepare("UPDATE book_transactions SET book_number = ? WHERE id = ?");
        $stmt->bind_param("si", $edit_book_number, $edit_id);
        if ($stmt->execute()) {
            $_SESSION['status'] = 'success';
            $_SESSION['message'] = "Book updated.";
        } else {
            $_SESSION['status'] = 'danger';
            $_SESSION['message'] = "Update error: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: book-transactions.php");
    exit();
}

// Toggle status
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE book_transactions SET status = IF(status='active','completed','active') WHERE id = $id");
    header("Location: book-transactions.php");
    exit();
}

// Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM book_transactions WHERE id = $id");
    header("Location: book-transactions.php");
    exit();
}

// Fetch all records
$result = $conn->query("SELECT * FROM book_transactions ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Book Transactions</title>
    <?php include_once("includes/css-links-inc.php"); ?>
</head>
<?php include_once("includes/header.php"); ?>
<?php include_once("includes/user-sidebar.php"); ?>

<body>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Book Transactions</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Book Transactions</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body pt-4">
                <form method="POST" class="d-flex mb-4">
                    <input type="text" name="book_number" class="form-control me-2" placeholder="Enter Book Number" required>
                    <button type="submit" class="btn btn-success">Add</button>
                </form>

                <?php if (isset($_SESSION['status'])): ?>
                    <div class="alert alert-<?= $_SESSION['status'] ?>">
                        <?= $_SESSION['message'] ?>
                    </div>
                    <?php unset($_SESSION['status'], $_SESSION['message']); ?>
                <?php endif; ?>

                <table class="table table-bordered text-center align-middle">
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
                        <tr class="<?= $row['status'] === 'completed' ? 'table-danger' : '' ?>">
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['book_number']) ?></td>
                            <td><?= $row['created_at'] ?></td>
                            <td>
                                <span class="badge <?= $row['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="?toggle=<?= $row['id'] ?>" class="btn btn-sm <?= $row['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>">
                                    <?= $row['status'] === 'active' ? 'Disable' : 'Activate' ?>
                                </a>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">Edit</button>
                                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this entry?')" class="btn btn-sm btn-outline-danger">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<!-- Edit Modal Section -->
<?php
$result->data_seek(0);
while ($row = $result->fetch_assoc()):
?>
<div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $row['id'] ?>" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel<?= $row['id'] ?>">Edit Book Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

<?php include_once("includes/footer.php"); ?>
<?php include_once("includes/js-links-inc.php"); ?>
</body>
</html>
