
<?php
session_start();
require_once "includes/db.php";

function escapeHtml($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Customer must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$errors = [];
$customerName = $_SESSION["user_name"] ?? "";
$email = $_SESSION["user_email"] ?? "";
$address = "";
$city = "";
$postalCode = "";

// Fetch customer details from database
$stmt = $conn->prepare(
    "SELECT name, email FROM users WHERE id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$customerName = $customer["name"];
$email = $customer["email"];

// Process order submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customerName = trim($_POST["customer_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $city = trim($_POST["city"] ?? "");
    $postalCode = trim($_POST["postal_code"] ?? "");
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $errors[] = "Invalid request. Refresh the page and try again.";
    }

    if ($customerName === "" || strlen($customerName) > 100) {
        $errors[] = "Enter your name (maximum 100 characters).";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150) {
        $errors[] = "Enter a valid email address.";
    }

    if ($address === "" || strlen($address) > 5000) {
        $errors[] = "Enter your delivery address.";
    }

    if ($city === "" || strlen($city) > 100) {
        $errors[] = "Enter your city.";
    }

    if ($postalCode === "" || strlen($postalCode) > 20) {
        $errors[] = "Enter your postal code.";
    }

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            // Lock this customer's cart rows during checkout
            $stmt = $conn->prepare(
                "SELECT c.product_id, c.quantity, p.name, p.price
                 FROM cart c
                 INNER JOIN products p ON p.id = c.product_id
                 WHERE c.user_id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $items = [];
            $total = 0;

            while ($item = $result->fetch_assoc()) {
                $item["quantity"] = (int)$item["quantity"];
                $item["price"] = (float)$item["price"];

                $total += $item["price"] * $item["quantity"];
                $items[] = $item;
            }

            $stmt->close();

            if (empty($items)) {
                throw new Exception(
                    "Your cart is empty. Add products before checkout."
                );
            }

            if ($total <= 0 || $total > 99999999.99) {
                throw new Exception("The order total is invalid.");
            }

            // Save the order
            $stmt = $conn->prepare(
                "INSERT INTO orders
                 (user_id, customer_name, email, address,
                  city, postal_code, total_amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isssssd",
                $userId,
                $customerName,
                $email,
                $address,
                $city,
                $postalCode,
                $total
            );

            $stmt->execute();
            $orderId = $conn->insert_id;
            $stmt->close();

            // Save each product in the order
            $stmt = $conn->prepare(
                "INSERT INTO order_items
                 (order_id, product_id, product_name, price, quantity)
                 VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($items as $item) {
                $productId = (int)$item["product_id"];
                $productName = $item["name"];
                $price = (float)$item["price"];
                $quantity = (int)$item["quantity"];

                $stmt->bind_param(
                    "iisdi",
                    $orderId,
                    $productId,
                    $productName,
                    $price,
                    $quantity
                );

                $stmt->execute();
            }

            $stmt->close();

            // Empty the cart after the order has been saved
            $stmt = $conn->prepare(
                "DELETE FROM cart WHERE user_id = ?"
            );
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            // Prevent accidental form resubmission
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            header("Location: order_success.php?id=" . $orderId);
            exit;

        } catch (Throwable $e) {
            $conn->rollback();

            if ($e instanceof mysqli_sql_exception) {
                error_log("Checkout database error: " . $e->getMessage());
                $errors[] = "Unable to place your order. Please try again.";
            } else {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Load cart preview for the checkout page
$stmt = $conn->prepare(
    "SELECT p.name, p.price, c.quantity
     FROM cart c
     INNER JOIN products p ON p.id = c.product_id
     WHERE c.user_id = ?
     ORDER BY c.id DESC"
);

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$cartItems = [];
$total = 0;

while ($item = $result->fetch_assoc()) {
    $item["quantity"] = (int)$item["quantity"];
    $item["price"] = (float)$item["price"];
    $item["line_total"] = $item["price"] * $item["quantity"];

    $total += $item["line_total"];
    $cartItems[] = $item;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Checkout | My Shopping Store</title>

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

        header a {
            color: white;
            text-decoration: none;
        }

        nav {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }

        nav a {
            font-size: 14px;
        }

        nav a:hover {
            color: #ffc107;
        }

        .container {
            width: 92%;
            max-width: 1100px;
            margin: 35px auto;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h2 {
            margin-bottom: 8px;
            font-size: 28px;
        }

        .page-title p {
            color: #666;
        }

        .checkout-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 25px;
            align-items: start;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .card h3 {
            margin-bottom: 20px;
            font-size: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            margin-bottom: 18px;
            font: inherit;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 5px;
            background: #0d6efd;
            color: white;
            text-align: center;
            text-decoration: none;
            font-size: 15px;
            cursor: pointer;
        }

        .btn:hover {
            background: #0b5ed7;
        }

        .message {
            padding: 13px;
            border-radius: 5px;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        .error {
            background: #f8d7da;
            color: #842029;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 15px;
            color: #555;
        }

        .summary-row span:last-child {
            text-align: right;
        }

        .summary-total {
            border-top: 1px solid #ddd;
            padding-top: 18px;
            font-size: 21px;
            font-weight: bold;
            color: #198754;
        }

        .order-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .order-item:last-of-type {
            border-bottom: none;
        }

        .order-item p {
            margin-bottom: 6px;
            overflow-wrap: anywhere;
        }

        .muted {
            color: #666;
            font-size: 14px;
        }

        .back-link {
            display: inline-block;
            margin-top: 18px;
            color: #0d6efd;
            text-decoration: none;
        }

        footer {
            margin-top: 45px;
            padding: 22px;
            background: #212529;
            color: white;
            text-align: center;
        }

        @media (max-width: 800px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
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
                padding: 18px;
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
        <a href="cart.php">My Cart</a>

        <?php if (isset($_SESSION["user_id"])): ?>
            <a href="my_orders.php">My Orders</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<main class="container">

    <div class="page-title">
        <h2>Checkout</h2>
        <p>Enter your delivery details to place your order.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="message error" role="alert">
            <?php foreach ($errors as $error): ?>
                <div><?= escapeHtml($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>

        <section class="card">
            <h3>Your cart is empty</h3>
            <p class="muted">Add products before proceeding to checkout.</p>
            <a href="products.php" class="back-link">Browse Products</a>
        </section>

    <?php else: ?>

        <div class="checkout-layout">

            <section class="card">
                <h3>Delivery Information</h3>

                <form method="POST" action="checkout.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeHtml($_SESSION["csrf_token"]) ?>"
                    >

                    <label for="customer_name">Full Name</label>
                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        maxlength="100"
                        value="<?= escapeHtml($customerName) ?>"
                        autocomplete="name"
                        required
                    >

                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        value="<?= escapeHtml($email) ?>"
                        autocomplete="email"
                        required
                    >

                    <label for="address">Delivery Address</label>
                    <textarea
                        id="address"
                        name="address"
                        maxlength="5000"
                        autocomplete="street-address"
                        placeholder="House number, street, area..."
                        required
                    ><?= escapeHtml($address) ?></textarea>

                    <label for="city">City</label>
                    <input
                        type="text"
                        id="city"
                        name="city"
                        maxlength="100"
                        value="<?= escapeHtml($city) ?>"
                        autocomplete="address-level2"
                        required
                    >

                    <label for="postal_code">Postal Code / PIN Code</label>
                    <input
                        type="text"
                        id="postal_code"
                        name="postal_code"
                        maxlength="20"
                        value="<?= escapeHtml($postalCode) ?>"
                        autocomplete="postal-code"
                        required
                    >

                    <button
                        type="submit"
                        class="btn"
                        onclick="return confirm('Place this order?');"
                    >
                        Place Order — ₹<?= number_format($total, 2) ?>
                    </button>

                </form>

                <a href="cart.php" class="back-link">
                    &larr; Return to Cart
                </a>
            </section>

            <aside class="card">
                <h3>Order Summary</h3>

                <?php foreach ($cartItems as $item): ?>
                    <div class="order-item">
                        <p>
                            <strong><?= escapeHtml($item["name"]) ?></strong>
                        </p>

                        <div class="summary-row muted">
                            <span>
                                <?= (int)$item["quantity"] ?> ×
                                ₹<?= number_format($item["price"], 2) ?>
                            </span>

                            <span>
                                ₹<?= number_format($item["line_total"], 2) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="summary-row" style="margin-top:18px;">
                    <span>Subtotal</span>
                    <span>₹<?= number_format($total, 2) ?></span>
                </div>

                <div class="summary-row">
                    <span>Shipping</span>
                    <span>Not charged</span>
                </div>

                <div class="summary-row summary-total">
                    <span>Total</span>
                    <span>₹<?= number_format($total, 2) ?></span>
                </div>

                <p class="muted" style="margin-top:15px; line-height:1.6;">
                    Payment is not collected on this page.
                </p>
            </aside>

        </div>

    <?php endif; ?>

</main>

<footer>
    <p>&copy; <?= date("Y") ?> My Shopping Store. All rights reserved.</p>
</footer>

</body>
</html>
