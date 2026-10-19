
<?php
require_once "includes/db.php";

$result = $conn->query(
    "SELECT id, name, email, course FROM students ORDER BY id ASC"
);

if (!$result) {
    exit("Database error: " . $conn->error);
}

$xml = new DOMDocument("1.0", "UTF-8");
$xml->formatOutput = true;

$root = $xml->createElement("students");
$xml->appendChild($root);

while ($row = $result->fetch_assoc()) {
    $student = $xml->createElement("student");

    $student->appendChild(
        $xml->createElement("id", (string)$row["id"])
    );
    $student->appendChild(
        $xml->createElement("name", $row["name"])
    );
    $student->appendChild(
        $xml->createElement("email", $row["email"])
    );
    $student->appendChild(
        $xml->createElement("course", $row["course"])
    );

    $root->appendChild($student);
}

$file = __DIR__ . "/students.xml";

if ($xml->save($file) === false) {
    exit("Error: Could not save students.xml. Check folder permissions.");
}

echo "Export completed successfully.<br>";
echo "Total students exported: " . $result->num_rows . "<br>";
echo "File saved at: shopping_cart/students.xml";
?>
