
<?php
$url = "http://localhost:3000/api/message";

// Configure the HTTP request
$options = [
    "http" => [
        "method" => "GET",
        "header" => "Accept: application/json\r\n",
        "timeout" => 5,
        "ignore_errors" => true
    ]
];

$context = stream_context_create($options);

// Call the Express REST API
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    exit(
        "<h3>API Connection Failed</h3>
        <p>Make sure your Express server is running on port 3000.</p>"
    );
}

// Convert JSON response into a PHP array
$data = json_decode($response, true);

if (!is_array($data)) {
    exit("Error: Invalid JSON response from Express API.");
}

if (!($data["success"] ?? false)) {
    exit("The Express API returned an unsuccessful response.");
}

// Display the API response
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Calling Express API</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #212529;
            margin: 0;
            padding: 30px 15px;
        }

        .container {
            max-width: 650px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        h2 {
            margin-top: 0;
        }

        .success {
            background: #d1e7dd;
            color: #0f5132;
            padding: 12px;
            border-radius: 5px;
        }

        .detail {
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .detail strong {
            display: inline-block;
            min-width: 125px;
        }

        .button {
            display: inline-block;
            margin-top: 18px;
            background: #0d6efd;
            color: white;
            padding: 10px 16px;
            border-radius: 5px;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="container">
    <h2>PHP Calling Express REST API</h2>

    <p class="success">
        API connected successfully!
    </p>

    <div class="detail">
        <strong>Message:</strong>
        <?= e($data["message"] ?? "") ?>
    </div>

    <div class="detail">
        <strong>Application:</strong>
        <?= e($data["application"] ?? "") ?>
    </div>

    <div class="detail">
        <strong>Technology:</strong>
        <?= e($data["technology"] ?? "") ?>
    </div>

    <a href="index.php" class="button">Back to Home</a>
</div>

</body>
</html>
