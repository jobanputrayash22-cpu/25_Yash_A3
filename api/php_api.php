
<?php
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only GET requests are allowed."
    ]);
    exit;
}

require_once __DIR__ . "/../includes/db.php";

$result = $conn->query(
    "SELECT id, name, email, course FROM students ORDER BY id ASC"
);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve students."
    ]);
    exit;
}

$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode([
    "success" => true,
    "message" => "Students retrieved successfully.",
    "count" => count($students),
    "students" => $students
], JSON_UNESCAPED_UNICODE);
?>
