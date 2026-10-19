
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../includes/db.php";

// Get total categories
$categoryResult = $conn->query(
    "SELECT COUNT(*) AS total FROM categories"
);
$totalCategories = $categoryResult->fetch_assoc()["total"];

// Get total products
$productResult = $conn->query(
    "SELECT COUNT(*) AS total FROM products"
);
$totalProducts = $productResult->fetch_assoc()["total"];

// Get total registered users
$userResult = $conn->query(
    "SELECT COUNT(*) AS total FROM users"
);
$totalUsers = $userResult->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Shopping Cart</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        header {
            background: #1e293b;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        header h2 {
            margin: 0;
        }

        .logout {
            background: #dc2626;
            color: white;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 5px;
        }

        .container {
            padding: 30px;
            max-width: 1100px;
            margin: auto;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin: 25px 0;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0001;
        }

        .card h3 {
            margin-top: 0;
            color: #475569;
        }

        .number {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .menu a {
            display: block;
            background: #2563eb;
            color: white;
            text-align: center;
            padding: 18px;
            border-radius: 7px;
            text-decoration: none;
        }

        .menu a:hover {
            background: #1d4ed8;
        }

        @media (max-width: 650px) {
            .container {
                padding: 18px;
            }

            .cards,
            .menu {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>Shopping Cart Admin</h2>

    <div>
        Welcome,
        <?= htmlspecialchars($_SESSION["admin_username"] ?? "Admin") ?>
        &nbsp;
        <a class="logout" href="logout.php">Logout</a>
    </div>
</header>

<div class="container">

    <h2>Dashboard Overview</h2>

    <div class="cards">

        <div class="card">
            <h3>Total Categories</h3>
            <div class="number">
                <?= (int) $totalCategories ?>
            </div>
        </div>

        <div class="card">
            <h3>Total Products</h3>
            <div class="number">
                <?= (int) $totalProducts ?>
            </div>
        </div>

        <div class="card">
            <h3>Registered Users</h3>
            <div class="number">
                <?= (int) $totalUsers ?>
            </div>
        </div>

    </div>

    <h2>Manage Your Store</h2>

    <div class="menu">
        <a href="categories.php">Manage Categories</a>
        <a href="products.php">Manage Products</a>
        <a href="../index.php">View User Site</a>
    </div>

</div>

</body>
</html>
