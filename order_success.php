
<?php
session_start();
require_once "includes/db.php";

function escapeHtml($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Only logged-in customers can view their order confirmation
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    header("Location: index.php");
    exit;
}

// Fetch only an order belonging to the logged-in customer
$stmt = $conn->prepare(
    "SELECT id, customer_name, total_amount, order_status, created_at
     FROM orders
     WHERE id = ? AND user_id = ?
     LIMIT 1"
);

$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit("Order not found.");
}

// Fetch order items
$stmt = $conn->prepare(
    "SELECT product_name, price, quantity
     FROM order_items
     WHERE order_id = ?
     ORDER BY id ASC"
);

$stmt->bind_param("i", $orderId);
$stmt->execute();

$result = $stmt->get_result();
$items = [];

while ($item = $result->fetch_assoc()) {
    $items[] = $item;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Order Confirmed | My Shopping Store</title>

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
            max-width: 800px;
            margin: 40px auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, 0.07);
            margin-bottom: 22px;
        }

        .success-icon {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #d1e7dd;
            color: #198754;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 38px;
            font-weight: bold;
        }

        .confirmation {
            text-align: center;
        }

        .confirmation h2 {
            color: #198754;
            margin-bottom: 12px;
        }

        .confirmation p {
            color: #666;
            line-height: 1.7;
        }

        .order-number {
            display: inline-block;
            margin-top: 18px;
            padding: 12px 18px;
            background: #f1f3f5;
            border-radius: 6px;
            font-weight: bold;
        }

        .card h3 {
            margin-bottom: 20px;
            font-size: 20px;
        }

        .details-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .details-row:last-child {
            border-bottom: none;
        }

        .muted {
            color: #666;
        }

        .item {
            padding: 14px 0;
            border-bottom: 1px solid #eee;
        }

        .item:last-child {
            border-bottom: none;
        }

        .item-name {
            font-weight: bold;
            margin-bottom: 8px;
            overflow-wrap: anywhere;
        }

        .item-details {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: #666;
            font-size: 14px;
        }

        .total {
            color: #198754;
            font-size: 22px;
            font-weight: bold;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .btn {
            display: inline-block;
            padding: 12px 18px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn:hover {
            background: #0b5ed7;
        }

        .btn-secondary {
            background: #212529;
        }

        .btn-secondary:hover {
            background: #343a40;
        }

        footer {
            margin-top: 45px;
            padding: 22px;
            background: #212529;
            color: white;
            text-align: center;
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
                padding: 20px;
            }

            .details-row {
                flex-direction: column;
                gap: 5px;
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
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <section class="card confirmation">
        <div class="success-icon">&#10003;</div>

        <h2>Order Placed Successfully!</h2>

        <p>
            Thank you, <?= escapeHtml($order["customer_name"]) ?>.
            Your order has been recorded.
        </p>

        <div class="order-number">
            Order Number: #<?= (int)$order["id"] ?>
        </div>
    </section>

    <section class="card">
        <h3>Order Details</h3>

        <div class="details-row">
            <span class="muted">Order Number</span>
            <strong>#<?= (int)$order["id"] ?></strong>
        </div>

        <div class="details-row">
            <span class="muted">Order Date</span>
            <span>
                <?= escapeHtml(date(
                    "d M Y, h:i A",
                    strtotime($order["created_at"])
                )) ?>
            </span>
        </div>

        <div class="details-row">
            <span class="muted">Order Status</span>
            <strong><?= escapeHtml($order["order_status"]) ?></strong>
        </div>

        <div class="details-row">
            <span class="muted">Payment</span>
            <span>Not processed online</span>
        </div>
    </section>

    <section class="card">
        <h3>Items Ordered</h3>

        <?php if (empty($items)): ?>
            <p class="muted">No order items were found.</p>
        <?php else: ?>

            <?php foreach ($items as $item): ?>
                <div class="item">
                    <p class="item-name">
                        <?= escapeHtml($item["product_name"]) ?>
                    </p>

                    <div class="item-details">
                        <span>
                            <?= (int)$item["quantity"] ?> ×
                            ₹<?= number_format((float)$item["price"], 2) ?>
                        </span>

                        <strong>
                            ₹<?= number_format(
                                (float)$item["price"] * (int)$item["quantity"],
                                2
                            ) ?>
                        </strong>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <div class="details-row" style="margin-top:15px;">
            <span><strong>Order Total</strong></span>
            <span class="total">
                ₹<?= number_format((float)$order["total_amount"], 2) ?>
            </span>
        </div>

        <div class="buttons">
            <a href="products.php" class="btn">Continue Shopping</a>
            <a href="index.php" class="btn btn-secondary">Back to Home</a>
        </div>
    </section>

</main>

<footer>
    <p>&copy; <?= date("Y") ?> My Shopping Store. All rights reserved.</p>
</footer>

</body>
</html>
