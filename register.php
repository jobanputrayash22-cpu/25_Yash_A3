
<?php
session_start();
require_once "includes/db.php";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Generate CAPTCHA
if (empty($_SESSION["captcha"])) {
    $_SESSION["captcha"] = (string) random_int(10000, 99999);
}

$name = "";
$email = "";
$errors = [];
$success = "";

// Process registration form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $captchaInput = trim($_POST["captcha"] ?? "");
    $csrfInput = $_POST["csrf_token"] ?? "";

    // Validate CSRF token
    if (
        !is_string($csrfInput) ||
        !hash_equals($_SESSION["csrf_token"], $csrfInput)
    ) {
        $errors[] = "Invalid form submission. Please refresh and try again.";
    }

    // Validate customer name
    if ($name === "" || strlen($name) > 100) {
        $errors[] = "Enter a name with a maximum of 100 characters.";
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = "Enter a valid email address.";
    }

    // Validate password
    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    // Validate CAPTCHA
    $correctCaptcha = (string)($_SESSION["captcha"] ?? "");

    if (
        $captchaInput === "" ||
        !hash_equals($correctCaptcha, $captchaInput)
    ) {
        $errors[] = "Incorrect CAPTCHA. Please try again.";
    }

    // CAPTCHA can only be used once
    unset($_SESSION["captcha"]);

    if (empty($errors)) {

        // Check if email already exists
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = "This email is already registered.";
        }

        $check->close();

        // Create customer account
        if (empty($errors)) {

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param(
                "sss",
                $name,
                $email,
                $hashedPassword
            );

            if ($stmt->execute()) {
                $stmt->close();

                header("Location: login.php?registered=1");
                exit;
            } else {
                if ($conn->errno === 1062) {
                    $errors[] = "This email is already registered.";
                } else {
                    $errors[] = "Registration failed. Please try again.";
                }
            }

            if ($stmt) {
                $stmt->close();
            }
        }
    }

    // Generate a fresh CAPTCHA after submission
    $_SESSION["captcha"] = (string) random_int(10000, 99999);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Registration | Shopping Cart</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
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
            margin: 0;
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
            max-width: 520px;
            margin: 40px auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, 0.08);
        }

        h2 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            line-height: 1.5;
            margin-bottom: 25px;
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
            margin-bottom: 17px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        }

        .captcha-code {
            display: inline-block;
            padding: 12px 20px;
            margin-bottom: 12px;
            background: #e9ecef;
            border: 1px dashed #adb5bd;
            border-radius: 5px;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
            user-select: none;
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
            line-height: 1.5;
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
            line-height: 1.6;
        }

        .bottom-link a {
            color: #0d6efd;
            text-decoration: none;
        }

        footer {
            background: #212529;
            color: white;
            text-align: center;
            padding: 20px;
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
        <a href="login.php">Login</a>
    </nav>
</header>

<main class="container">
    <section class="card">

        <h2>Create Your Account</h2>

        <p class="subtitle">
            Register to access your shopping account.
        </p>

        <?php if (!empty($errors)): ?>
            <div class="message error" role="alert">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
            >

            <label for="name">Full Name</label>
            <input
                type="text"
                id="name"
                name="name"
                maxlength="100"
                value="<?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>"
                placeholder="Enter your full name"
                autocomplete="name"
                required
            >

            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                placeholder="Enter your email"
                autocomplete="email"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                placeholder="Minimum 8 characters"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                placeholder="Re-enter your password"
                autocomplete="new-password"
                required
            >

            <label>CAPTCHA Verification</label>

            <div class="captcha-code">
                <?= htmlspecialchars($_SESSION["captcha"], ENT_QUOTES, "UTF-8") ?>
            </div>

            <input
                type="text"
                name="captcha"
                placeholder="Enter the CAPTCHA code"
                inputmode="numeric"
                maxlength="5"
                pattern="[0-9]{5}"
                required
            >

            <button type="submit" class="btn">
                Create Account
            </button>

        </form>

        <div class="bottom-link">
            Already have an account?
            <a href="login.php">Login here</a>
        </div>

    </section>
</main>

<footer>
    <p>&copy; <?= date("Y") ?> My Shopping Store. All rights reserved.</p>
</footer>

</body>
</html>
