
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../includes/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Use the category list to delete a category.");
}

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Invalid category ID.");
}

$check = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM products WHERE category_id = ?"
);
$check->bind_param("i", $id);
$check->execute();

$total = (int) $check->get_result()->fetch_assoc()["total"];

if ($total > 0) {
    exit(
        "Cannot delete this category because products use it. " .
        '<a href="categories.php">Go back</a>'
    );
}

$stmt = $conn->prepare(
    "DELETE FROM categories WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: categories.php?deleted=1");
exit;
?>
