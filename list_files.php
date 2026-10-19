
<?php
$directory = __DIR__ . "/uploads";

echo "<h2>Files in the uploads directory</h2>";

if (!is_dir($directory)) {
    exit("Error: uploads directory does not exist.");
}

$files = scandir($directory);

echo "<ul>";

$count = 0;

foreach ($files as $file) {
    if ($file === "." || $file === "..") {
        continue;
    }

    $fullPath = $directory . DIRECTORY_SEPARATOR . $file;

    if (is_file($fullPath)) {
        echo "<li>" . htmlspecialchars(
            $file,
            ENT_QUOTES,
            "UTF-8"
        ) . "</li>";

        $count++;
    }
}

echo "</ul>";
echo "<p>Total files: " . $count . "</p>";
?>
