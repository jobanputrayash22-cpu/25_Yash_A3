
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../includes/db.php";

// Allow deletion only through POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Invalid request method.");
}

// Validate product ID
$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: products.php?error=invalid");
    exit;
}

// Fetch product image before deleting the record
$stmt = $conn->prepare(
    "SELECT image FROM products WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();

$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: products.php?error=notfound");
    exit;
}

// Delete the product record
$stmt = $conn->prepare(
    "DELETE FROM products WHERE id = ?"
);
$stmt->bind_param("i", $id);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    $stmt->close();

    // Remove the associated image, if it exists
    if (!empty($product["image"])) {
        $imageName = basename($product["image"]);
        $imagePath = __DIR__ . "/../uploads/" . $imageName;

        if (is_file($imagePath)) {
            unlink($imagePath);
        }
    }

    header("Location: products.php?deleted=1");
    exit;
}

$stmt->close();

header("Location: products.php?error=deletefailed");
exit;
?>
