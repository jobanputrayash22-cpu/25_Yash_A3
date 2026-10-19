
<?php
session_start();
require_once "includes/db.php";

// Only logged-in customers can add products
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Accept POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: products.php");
    exit;
}

// Validate CSRF token
$csrfToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($csrfToken) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    http_response_code(403);
    exit("Invalid request. Please refresh the page and try again.");
}

$productId = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

if (!$productId || $productId < 1 || !$quantity || $quantity < 1 || $quantity > 99) {
    header("Location: products.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

// Confirm that the product exists
$check = $conn->prepare("SELECT id FROM products WHERE id = ?");
$check->bind_param("i", $productId);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $check->close();
    header("Location: products.php");
    exit;
}

$check->close();

// Add product or increase its existing quantity
$stmt = $conn->prepare(
    "INSERT INTO cart (user_id, product_id, quantity)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), 99)"
);

$stmt->bind_param("iii", $userId, $productId, $quantity);
$stmt->execute();
$stmt->close();

header("Location: cart.php?added=1");
exit;
?>
