
<?php
session_start();
require_once "includes/db.php";

function escapeHtml($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Only logged-in customers can view their cart
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

// Generate CSRF token if necessary
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
$errors = [];

// Handle quantity updates and item removal
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        http_response_code(403);
        exit("Invalid request. Please refresh the page and try again.");
    }

    $action = $_POST["action"] ?? "";
    $cartId = filter_input(INPUT_POST, "cart_id", FILTER_VALIDATE_INT);

    if (!$cartId || $cartId < 1) {
        $errors[] = "Invalid cart item.";
    } elseif ($action === "update") {
        $quantity = filter_input(
            INPUT_POST,
            "quantity",
            FILTER_VALIDATE_INT
        );

        if (!$quantity || $quantity < 1 || $quantity > 99) {
            $errors[] = "Quantity must be between 1 and 99.";
        } else {
            $stmt = $conn->prepare(
                "UPDATE cart SET quantity = ?
                 WHERE id = ? AND user_id = ?"
            );

            $stmt->bind_param("iii", $quantity, $cartId, $userId);
            $stmt->execute();
            $stmt->close();

            header("Location: cart.php?updated=1");
            exit;
        }
    } elseif ($action === "remove") {
        $stmt = $conn->prepare(
            "DELETE FROM cart WHERE id = ? AND user_id = ?"
        );

        $stmt->bind_param("ii", $cartId, $userId);
        $stmt->execute();
        $stmt->close();

        header("Location: cart.php?removed=1");
        exit;
    } else {
        $errors[] = "Invalid action.";
    }
}

// Fetch cart items belonging to this customer
$stmt = $conn->prepare(
    "SELECT
        c.id AS cart_id,
        c.quantity,
        p.id AS product_id,
        p.name,
        p.price,
        p.image,
        p.description
     FROM cart c
     INNER JOIN products p ON p.id = c.product_id
     WHERE c.user_id = ?
     ORDER BY c.id DESC"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$cartItems = [];

$subtotal = 0;

while ($row = $result->fetch_assoc()) {
    $row["line_total"] = (float)$row["price"] * (int)$row["quantity"];
    $subtotal += $row["line_total"];
    $cartItems[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Cart | Shopping Store</title>

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
            width: 90%;
            max-width: 1200px;
            margin: 35px auto;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h2 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-title p {
            color: #666;
        }

        .message {
            padding: 13px 16px;
            border-radius: 6px;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .error {
            background: #f8d7da;
            color: #842029;
        }

        .cart-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 25px;
            align-items: start;
        }

        .cart-items,
        .summary {
            background: white;
            border-radius: 8px;
            padding: 22px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .cart-item {
            display: grid;
            grid-template-columns: 110px minmax(0, 1fr);
            gap: 18px;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }

        .cart-item:first-child {
            padding-top: 0;
        }

        .cart-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .cart-item img {
            width: 110px;
            height: 110px;
            object-fit: contain;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .item-details h3 {
            margin-bottom: 9px;
            font-size: 17px;
            overflow-wrap: anywhere;
        }

        .item-price {
            color: #198754;
            font-weight: bold;
            margin-bottom: 13px;
        }

        .quantity-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .quantity-form input {
            width: 75px;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 5px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 5px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #0b5ed7;
        }

        .remove-btn {
            background: #dc3545;
        }

        .remove-btn:hover {
            background: #bb2d3b;
        }

        .summary h3 {
            font-size: 20px;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            color: #555;
        }

        .summary-total {
            border-top: 1px solid #ddd;
            padding-top: 17px;
            font-size: 21px;
            font-weight: bold;
            color: #198754;
        }

        .checkout-btn {
            display: block;
            width: 100%;
            text-align: center;
            margin-top: 18px;
        }

        .continue-link {
            display: inline-block;
            margin-top: 18px;
            color: #0d6efd;
            text-decoration: none;
        }

        .empty-cart {
            text-align: center;
            background: white;
            padding: 45px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .empty-cart h3 {
            margin-bottom: 12px;
        }

        .empty-cart p {
            color: #666;
            margin-bottom: 20px;
        }

        footer {
            margin-top: 50px;
            padding: 22px;
            background: #212529;
            color: white;
            text-align: center;
        }

        @media (max-width: 850px) {
            .cart-layout {
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

            .container {
                width: 92%;
            }

            .cart-items,
            .summary {
                padding: 16px;
            }

            .cart-item {
                grid-template-columns: 80px minmax(0, 1fr);
                gap: 12px;
            }

            .cart-item img {
                width: 80px;
                height: 90px;
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
        <h2>My Shopping Cart</h2>
        <p>Review your products and update quantities before checkout.</p>
    </div>

    <?php if (isset($_GET["added"])): ?>
        <div class="message success">Product added to your cart.</div>
    <?php endif; ?>

    <?php if (isset($_GET["updated"])): ?>
        <div class="message success">Cart quantity updated.</div>
    <?php endif; ?>

    <?php if (isset($_GET["removed"])): ?>
        <div class="message success">Product removed from your cart.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <?php foreach ($errors as $error): ?>
                <div><?= escapeHtml($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>

        <section class="empty-cart">
            <h3>Your cart is empty</h3>
            <p>Browse our products and add something you like.</p>
            <a href="products.php" class="btn">Browse Products</a>
        </section>

    <?php else: ?>

        <div class="cart-layout">

            <section class="cart-items">

                <?php foreach ($cartItems as $item): ?>

                    <?php
                    $imageFile = trim((string)($item["image"] ?? ""));
                    $imageFile = str_replace("\\", "/", $imageFile);
                    $imageFile = basename($imageFile);

                    $imageUrl = "uploads/" . rawurlencode($imageFile);

                    $imageExists = $imageFile !== "" &&
                        is_file(__DIR__ . "/uploads/" . $imageFile);
                    ?>

                    <article class="cart-item">

                        <?php if ($imageExists): ?>
                            <img
                                src="<?= escapeHtml($imageUrl) ?>"
                                alt="<?= escapeHtml($item["name"]) ?>"
                            >
                        <?php else: ?>
                            <div class="image-placeholder"
                                 style="width:100%; min-height:90px; display:flex; align-items:center; justify-content:center; background:#eee; color:#666; font-size:12px;">
                                No image
                            </div>
                        <?php endif; ?>

                        <div class="item-details">

                            <h3><?= escapeHtml($item["name"]) ?></h3>

                            <p class="item-price">
                                ₹<?= number_format((float)$item["price"], 2) ?> each
                            </p>

                            <form method="POST" action="cart.php" class="quantity-form">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escapeHtml($csrfToken) ?>"
                                >

                                <input type="hidden" name="cart_id"
                                       value="<?= (int)$item["cart_id"] ?>">

                                <input type="hidden" name="action" value="update">

                                <label>
                                    Qty:
                                    <input
                                        type="number"
                                        name="quantity"
                                        value="<?= (int)$item["quantity"] ?>"
                                        min="1"
                                        max="99"
                                        required
                                    >
                                </label>

                                <button type="submit" class="btn">
                                    Update
                                </button>
                            </form>

                            <p>
                                Subtotal:
                                <strong>
                                    ₹<?= number_format($item["line_total"], 2) ?>
                                </strong>
                            </p>

                            <form method="POST" action="cart.php"
                                  onsubmit="return confirm('Remove this product from your cart?');"
                                  style="margin-top:10px;">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escapeHtml($csrfToken) ?>"
                                >

                                <input type="hidden" name="cart_id"
                                       value="<?= (int)$item["cart_id"] ?>">

                                <input type="hidden" name="action" value="remove">

                                <button type="submit" class="btn remove-btn">
                                    Remove
                                </button>
                            </form>

                        </div>
                    </article>

                <?php endforeach; ?>

                <a href="products.php" class="continue-link">
                    &larr; Continue Shopping
                </a>

            </section>

            <aside class="summary">

                <h3>Order Summary</h3>

                <div class="summary-row">
                    <span>Items</span>
                    <span><?= count($cartItems) ?></span>
                </div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₹<?= number_format($subtotal, 2) ?></span>
                </div>

                <div class="summary-row">
                    <span>Shipping</span>
                    <span>Calculated at checkout</span>
                </div>

                <div class="summary-row summary-total">
                    <span>Total</span>
                    <span>₹<?= number_format($subtotal, 2) ?></span>
                </div>

                <a href="checkout.php" class="btn checkout-btn">
                    Proceed to Checkout
                </a>

                <a href="products.php" class="continue-link">
                    Continue Shopping
                </a>

            </aside>

        </div>

    <?php endif; ?>

</main>

<footer>
    <p>&copy; <?= date("Y") ?> My Shopping Store. All rights reserved.</p>
</footer>

</body>
</html>
