
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../includes/db.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Invalid category ID.");
}

$stmt = $conn->prepare(
    "SELECT name FROM categories WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();

$category = $stmt->get_result()->fetch_assoc();

if (!$category) {
    exit("Category not found.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");

    if ($name === "") {
        $error = "Category name is required.";
    } else {
        $update = $conn->prepare(
            "UPDATE categories SET name = ? WHERE id = ?"
        );
        $update->bind_param("si", $name, $id);

        if ($update->execute()) {
            header("Location: categories.php?updated=1");
            exit;
        }

        $error = "Unable to update. This category name may already exist.";
        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Category</title>
</head>
<body>

<h2>Edit Category</h2>

<p style="color:red">
    <?= htmlspecialchars($error) ?>
</p>

<form method="POST">
    <label>Category Name</label>

    <input
        type="text"
        name="name"
        maxlength="100"
        value="<?= htmlspecialchars($category["name"]) ?>"
        required
    >

    <button type="submit">Update Category</button>
</form>

<p><a href="categories.php">Back to Categories</a></p>

</body>
</html>
