
<?php
require_once "includes/db.php";

$xmlFile = __DIR__ . "/students.xml";

if (!file_exists($xmlFile)) {
    exit("Error: students.xml file not found.");
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlFile);

if ($xml === false) {
    exit("Error: Unable to read students.xml. Check the XML format.");
}

$stmt = $conn->prepare(
    "INSERT INTO students (name, email, course) VALUES (?, ?, ?)"
);

if (!$stmt) {
    exit("Database error: " . $conn->error);
}

$count = 0;

foreach ($xml->student as $student) {
    $name = trim((string)$student->name);
    $email = trim((string)$student->email);
    $course = trim((string)$student->course);

    if ($name === "" || $email === "" || $course === "") {
        continue;
    }

    $stmt->bind_param("sss", $name, $email, $course);

    if ($stmt->execute()) {
        $count++;
    }
}

$stmt->close();

echo "XML import completed successfully.<br>";
echo "Students imported: " . $count;
?>
