
<?php
require_once "../includes/db.php";

header("Content-Type: application/json; charset=UTF-8");

$categoryId = filter_input(
    INPUT_GET,
    "category_id",
    FILTER_VALIDATE_INT
);

$page = filter_input(
    INPUT_GET,
    "page",
    FILTER_VALIDATE_INT
);

$page = max(1, $page ?: 1);
$limit = 6;
$offset = ($page - 1) * $limit;

if ($categoryId !== null && $categoryId !== false && $categoryId < 1) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid category ID"]);
    exit;
}

// Count matching products
if ($categoryId) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM products
         WHERE category_id = ?"
    );

    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();
} else {
    $result = $conn->query(
        "SELECT COUNT(*) AS total FROM products"
    );

    $total = (int) $result->fetch_assoc()["total"];
}

$totalPages = (int) ceil($total / $limit);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Fetch products
if ($categoryId) {
    $stmt = $conn->prepare(
        "SELECT id, name, price, description, image, category_id
         FROM products
         WHERE category_id = ?
         ORDER BY id DESC
         LIMIT ? OFFSET ?"
    );

    $stmt->bind_param("iii", $categoryId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare(
        "SELECT id, name, price, description, image, category_id
         FROM products
         ORDER BY id DESC
         LIMIT ? OFFSET ?"
    );

    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

echo json_encode([
    "products" => $products,
    "currentPage" => $page,
    "totalPages" => $totalPages,
    "totalProducts" => $total
]);
?>
