
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../includes/db.php";

$message = "";
$messageType = "success";

// Handle category addition
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_category"])) {

    $name = trim($_POST["name"] ?? "");

    if ($name === "") {
        $message = "Please enter a category name.";
        $messageType = "error";
    } elseif (strlen($name) > 100) {
        $message = "Category name cannot exceed 100 characters.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO categories (name) VALUES (?)"
        );

        $stmt->bind_param("s", $name);

        if ($stmt->execute()) {
            header("Location: categories.php?added=1");
            exit;
        } else {
            if ($stmt->errno === 1062) {
                $message = "This category already exists.";
            } else {
                $message = "Unable to add category.";
            }

            $messageType = "error";
        }

        $stmt->close();
    }
}

// Handle category deletion
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_category"])) {

    $id = filter_input(
        INPUT_POST,
        "id",
        FILTER_VALIDATE_INT
    );

    if (!$id || $id < 1) {
        $message = "Invalid category ID.";
        $messageType = "error";
    } else {
        // Check whether products use this category
        $check = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM products
             WHERE category_id = ?"
        );

        $check->bind_param("i", $id);
        $check->execute();

        $total = (int) $check->get_result()
            ->fetch_assoc()["total"];

        $check->close();

        if ($total > 0) {
            $message = "Cannot delete this category because products are assigned to it.";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare(
                "DELETE FROM categories WHERE id = ?"
            );

            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                header("Location: categories.php?deleted=1");
                exit;
            } else {
                $message = "Category not found or could not be deleted.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }
}

// Success messages after redirects
if (isset($_GET["added"])) {
    $message = "Category added successfully!";
} elseif (isset($_GET["updated"])) {
    $message = "Category updated successfully!";
} elseif (isset($_GET["deleted"])) {
    $message = "Category deleted successfully!";
}

// Fetch categories
$result = $conn->query(
    "SELECT id, name FROM categories ORDER BY id DESC"
);

if (!$result) {
    die("Unable to load categories.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Categories | Shopping Cart Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 28px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        h2 {
            margin: 0;
            font-size: 25px;
        }

        h3 {
            margin-top: 30px;
        }

        .back {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .back:hover {
            text-decoration: underline;
        }

        .message {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .add-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .add-form input {
            flex: 1;
            min-width: 200px;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        button {
            padding: 11px 16px;
            border: none;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 14px;
        }

        .add-btn {
            background: #2563eb;
        }

        .add-btn:hover {
            background: #1d4ed8;
        }

        .edit-btn {
            display: inline-block;
            background: #f59e0b;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
        }

        .delete-btn {
            background: #dc2626;
            padding: 8px 12px;
        }

        .delete-btn:hover {
            background: #b91c1c;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
            min-width: 500px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #1e293b;
            color: white;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .actions form {
            margin: 0;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 18px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .add-form {
                flex-direction: column;
            }

            .add-form input {
                width: 100%;
            }

            .add-btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h2>Manage Categories</h2>

        <a class="back" href="dashboard.php">
            &larr; Back to Dashboard
        </a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Add Category Form -->
    <h3>Add New Category</h3>

    <form method="POST" class="add-form">

        <input
            type="text"
            name="name"
            placeholder="Enter category name"
            maxlength="100"
            required
        >

        <button
            type="submit"
            name="add_category"
            class="add-btn"
        >
            + Add Category
        </button>

    </form>

    <!-- Category List -->
    <h3>Category List</h3>

    <div class="table-wrapper">

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>
                        <td>
                            <?= (int) $row["id"] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row["name"]) ?>
                        </td>

                        <td>
                            <div class="actions">

                                <a
                                    class="edit-btn"
                                    href="edit_category.php?id=<?= (int) $row["id"] ?>"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this category?');"
                                >
                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $row["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_category"
                                        class="delete-btn"
                                    >
                                        Delete
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="3" class="empty">
                        No categories found. Add your first category above.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

</div>

</body>
</html>
