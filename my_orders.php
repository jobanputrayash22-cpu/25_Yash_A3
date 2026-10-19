
<?php
session_start();

require_once "includes/db.php";

// Only logged-in customers can view their orders
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

// Fetch orders belonging only to the logged-in customer
$stmt = $conn->prepare("
    SELECT id, total_amount, order_status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function statusClass($status) {
    switch (strtolower((string) $status)) {
        case "completed":
        case "delivered":
            return "status-success";

        case "cancelled":
            return "status-danger";

        case "processing":
        case "shipped":
            return "status-info";

        default:
            return "status-pending";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Orders | Shopping Cart</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #212529;
        }

        header {
            background: #212529;
            color: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        header h2 {
            margin: 0;
            font-size: 24px;
        }

        nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        nav a {
            color: white;
            text-decoration: none;
            padding: 9px 12px;
            border-radius: 5px;
            font-size: 14px;
        }

        nav a:hover {
            background: #343a40;
        }

        .container {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .page-title {
            margin-bottom: 8px;
        }

        .subtitle {
            color: #6c757d;
            margin-bottom: 25px;
        }

        .orders-card {
            background: white;
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.07);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 15px 12px;
            border-bottom: 1px solid #dee2e6;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            font-size: 14px;
        }

        td {
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending {
            color: #856404;
            background: #fff3cd;
        }

        .status-success {
            color: #155724;
            background: #d4edda;
        }

        .status-danger {
            color: #721c24;
            background: #f8d7da;
        }

        .status-info {
            color: #004085;
            background: #cce5ff;
        }

        .btn {
            display: inline-block;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            padding: 9px 13px;
            border-radius: 5px;
            font-size: 13px;
        }

        .btn:hover {
            background: #0b5ed7;
        }

        .empty-state {
            text-align: center;
            padding: 45px 15px;
        }

        .empty-state h3 {
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 22px;
        }

        footer {
            margin-top: 50px;
            padding: 22px;
            text-align: center;
            background: #212529;
            color: white;
        }

        @media (max-width: 600px) {
            header {
                padding: 16px 5%;
            }

            header h2 {
                font-size: 21px;
            }

            nav a {
                padding: 8px;
            }

            .container {
                margin: 25px auto;
                padding: 0 12px;
            }

            .orders-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>Shopping Cart</h2>

    <nav>
        <a href="index.php">Home</a>
        <a href="products.php">Products</a>
        <a href="cart.php">My Cart</a>
        <a href="my_orders.php">My Orders</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">
    <h1 class="page-title">My Orders</h1>

    <p class="subtitle">
        View your previous orders and check their current status.
    </p>

    <section class="orders-card">

        <?php if (count($orders) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                #<?= (int) $order["id"] ?>
                            </td>

                            <td>
                                <?= e(date(
                                    "d M Y, h:i A",
                                    strtotime($order["created_at"])
                                )) ?>
                            </td>

                            <td>
                                ₹<?= number_format(
                                    (float) $order["total_amount"],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <span class="status <?= e(statusClass($order["order_status"])) ?>">
                                    <?= e($order["order_status"]) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="btn"
                                    href="order_success.php?id=<?= (int) $order["id"] ?>"
                                >
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>

            <div class="empty-state">
                <h3>No Orders Yet</h3>

                <p>
                    You have not placed any orders. Browse our products
                    and place your first order.
                </p>

                <a href="products.php" class="btn">
                    Browse Products
                </a>
            </div>

        <?php endif; ?>

    </section>
</main>

<footer>
    <p>&copy; <?= date("Y") ?> My Shopping Store. All rights reserved.</p>
</footer>

</body>
</html>
