
<?php
session_start();
require_once "includes/db.php";

// Redirect already logged-in customers
if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$email = "";
$errors = [];

// Process login form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    // Validate CSRF token
    if (
        !is_string($csrfToken) ||
        empty($_SESSION["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $csrfToken)
    ) {
        $errors[] = "Invalid form submission. Please refresh and try again.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    if ($password === "") {
        $errors[] = "Enter your password.";
    }

    if (empty($errors)) {

        // Find customer by email
        $stmt = $conn->prepare(
            "SELECT id, name, email, password
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        // Verify the password hash
        if ($user && password_verify($password, $user["password"])) {

            // Prevent session fixation
            session_regenerate_id(true);

            $_SESSION["user_id"] = (int)$user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];

            // Create a new CSRF token for the authenticated session
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            header("Location: index.php");
            exit;

        } else {
            $errors[] = "Incorrect email or password.";
        }
    }
}

// Create CSRF token for the form
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Login | My Shopping Store</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f6fa;
            color: #222;
        }

        header {
            background: #212529;
            color: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        header h1 {
            font-size: 25px;
        }

        header h1 a {
            color: white;
            text-decoration: none;
        }

        nav {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        nav a:hover {
            color: #ffc107;
        }

        .container {
            width: 92%;
            max-width: 500px;
            margin: 45px auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, 0.08);
        }

        h2 {
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            margin-bottom: 18px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        }

        .btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 5px;
            background: #0d6efd;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .btn:hover {
            background: #0b5ed7;
        }

        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        .error {
            background: #f8d7da;
            color: #842029;
        }

        .success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .bottom-link {
            margin-top: 20px;
            text-align: center;
            line-height: 1.8;
        }

        .bottom-link a {
            color: #0d6efd;
            text-decoration: none;
        }

        footer {
            background: #212529;
            color: white;
            padding: 20px;
            text-align: center;
            margin-top: 40px;
        }

        @media (max-width: 480px) {
            header {
                align-items: flex-start;
                flex-direction: column;
            }

            nav {
                gap: 12px;
            }

            .card {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1><a href="index.php">My Shopping Store</a></h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="products.php">Products</a>
        <a href="register.php">Register</a>
    </nav>
</header>

<main class="container">
    <section class="card">

        <h2>Customer Login</h2>

        <p class="subtitle">
            Sign in to your account to continue shopping.
        </p>

        <?php if (isset($_GET["registered"]) && $_GET["registered"] === "1"): ?>
            <div class="message success">
                Registration successful! You can now log in.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="message error" role="alert">
                <?php foreach ($errors as $error): ?>
                    <div>
                        <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
            >

            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                placeholder="Enter your registered email"
                autocomplete="email"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >

            <button type="submit" class="btn">
                Login
            </button>

        </form>

        <div class="bottom-link">
            Don't have an account?
            <a href="register.php">Create an account</a>
            <br>
            <a href="products.php">Continue browsing products</a>
        </div>

    </section>
</main>

<footer>
    <p>&copy; <?= date("Y") ?> My Shopping Store. All rights reserved.</p>
</footer>

</body>
</html>
