
<?php
session_start();
require_once "includes/db.php";

function escapeHtml($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Fetch categories
$categories = $conn->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
);

// Fetch latest products
$products = $conn->query(
    "SELECT p.id, p.category_id, p.name, p.price, p.description,
            p.image, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     ORDER BY p.id DESC
     LIMIT 12"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Shopping Store | Home</title>

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

        /* Header */
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
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        nav a:hover {
            color: #ffc107;
        }

        /* Main container */
        .container {
            width: 90%;
            max-width: 1400px;
            margin: 30px auto;
        }

        /* Hero section */
        .hero {
            background: #212529;
            color: white;
            padding: 55px 35px;
            border-radius: 10px;
            margin-bottom: 35px;
        }

        .hero h2 {
            font-size: 36px;
            margin-bottom: 15px;
        }

        .hero p {
            max-width: 650px;
            color: #e0e0e0;
            font-size: 16px;
            line-height: 1.7;
            margin-bottom: 25px;
        }

        .button {
            display: inline-block;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.2s;
        }

        .button:hover {
            background: #0b5ed7;
        }

        /* Section headings */
        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .section-heading h2 {
            font-size: 27px;
        }

        /* Sidebar and products layout */
        .layout {
            display: grid;
            grid-template-columns: 230px minmax(0, 1fr);
            gap: 25px;
            align-items: start;
        }

        .sidebar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .sidebar h3 {
            margin-bottom: 15px;
            font-size: 19px;
        }

        .category-link {
            display: block;
            padding: 11px 12px;
            margin-bottom: 7px;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.2s, color 0.2s;
        }

        .category-link:hover,
        .category-link.active {
            background: #0d6efd;
            color: white;
        }

        /* Products grid */
        .products {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
            display: flex;
            flex-direction: column;
            min-width: 0;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 5px 16px rgba(0, 0, 0, 0.12);
        }

        .product-card img {
            display: block;
            width: 100%;
            height: 220px;
            object-fit: contain;
            padding: 12px;
            background: white;
        }

        .image-placeholder {
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f3f5;
            color: #777;
            font-size: 14px;
        }

        .product-info {
            padding: 17px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .product-info h3 {
            font-size: 17px;
            margin-bottom: 10px;
            overflow-wrap: anywhere;
        }

        .category {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 14px;
            overflow-wrap: anywhere;
        }

        .price {
            color: #198754;
            font-size: 21px;
            font-weight: bold;
            margin-top: auto;
            margin-bottom: 15px;
        }

        .buy-button {
            display: block;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            text-align: center;
            padding: 11px;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.2s;
        }

        .buy-button:hover {
            background: #0b5ed7;
        }

        .empty-message {
            grid-column: 1 / -1;
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            color: #666;
            line-height: 1.7;
        }

        /* Benefits section */
        .benefits {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-top: 35px;
        }

        .benefit-card {
            background: white;
            padding: 22px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .benefit-card h3 {
            margin-bottom: 9px;
            font-size: 17px;
        }

        .benefit-card p {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }

        /* Footer */
        footer {
            margin-top: 50px;
            padding: 22px;
            text-align: center;
            background: #212529;
            color: white;
        }

        /* Tablet */
        @media (max-width: 1100px) {
            .products {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            header {
                padding: 16px 5%;
            }

            .container {
                width: 92%;
            }

            .hero {
                padding: 35px 24px;
            }

            .hero h2 {
                font-size: 29px;
            }

            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                padding: 15px;
            }

            .category-link {
                display: inline-block;
                margin: 3px;
            }

            .products {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px;
            }

            .product-card img,
            .image-placeholder {
                height: 180px;
            }

            .benefits {
                grid-template-columns: 1fr;
            }
        }

        /* Mobile */
        @media (max-width: 480px) {
            header {
                align-items: flex-start;
                flex-direction: column;
            }

            nav {
                gap: 12px;
            }

            .hero h2 {
                font-size: 25px;
            }

            .hero {
                padding: 30px 20px;
            }

            .section-heading h2 {
                font-size: 23px;
            }

            .products {
                grid-template-columns: 1fr;
            }

            .product-card img,
            .image-placeholder {
                height: 230px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>
        <a href="index.php">My Shopping Store</a>
    </h1>

    
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

    <!-- Hero -->
    <section class="hero">
        <h2>Welcome to Our Store</h2>

        <p>
            Discover our latest products, explore different categories,
            and find what you need all in one place.
        </p>

        <a href="products.php" class="button">Shop Now</a>
    </section>

    <!-- Main content -->
    <div class="layout">

        <!-- Category sidebar -->
        <aside class="sidebar">
            <h3>Categories</h3>

            <a href="products.php"
               class="category-link">
                All Products
            </a>

            <?php if ($categories && $categories->num_rows > 0): ?>

                <?php while ($category = $categories->fetch_assoc()): ?>
                    <a
                        href="products.php?category_id=<?= (int)$category["id"] ?>"
                        class="category-link"
                    >
                        <?= escapeHtml($category["name"]) ?>
                    </a>
                <?php endwhile; ?>

            <?php else: ?>

                <p class="description">No categories available yet.</p>

            <?php endif; ?>
        </aside>

        <!-- Latest products -->
        <section>
            <div class="section-heading">
                <h2>Latest Products</h2>

                <a href="products.php" class="button">View All</a>
            </div>

            <div class="products">

                <?php if ($products && $products->num_rows > 0): ?>

                    <?php while ($product = $products->fetch_assoc()): ?>

                        <article class="product-card">

                            <?php
                            $imageFile = trim((string)($product["image"] ?? ""));
                            $imageFile = str_replace("\\", "/", $imageFile);
                            $imageFile = basename($imageFile);

                            $imagePath = "uploads/" . rawurlencode($imageFile);

                            $imageExists = false;

                            if ($imageFile !== "") {
                                $imageDiskPath = __DIR__ . DIRECTORY_SEPARATOR
                                    . "uploads" . DIRECTORY_SEPARATOR
                                    . $imageFile;

                                $imageExists = is_file($imageDiskPath);
                            }
                            ?>

                            <?php if ($imageExists): ?>

                                <img
                                    src="<?= escapeHtml($imagePath) ?>"
                                    alt="<?= escapeHtml($product["name"]) ?>"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >

                                <div class="image-placeholder"
                                     style="display:none;">
                                    Image not found
                                </div>

                            <?php else: ?>

                                <div class="image-placeholder">
                                    <?= $imageFile === ""
                                        ? "No image uploaded"
                                        : "Image file not found" ?>
                                </div>

                            <?php endif; ?>

                            <div class="product-info">

                                <p class="category">
                                    <?= escapeHtml(
                                        $product["category_name"] ?? "Uncategorized"
                                    ) ?>
                                </p>

                                <h3>
                                    <?= escapeHtml($product["name"]) ?>
                                </h3>

                                <p class="description">
                                    <?php
                                    $description = (string)($product["description"] ?? "");

                                    echo escapeHtml(
                                        strlen($description) > 100
                                            ? substr($description, 0, 100) . "..."
                                            : $description
                                    );
                                    ?>
                                </p>

                                <p class="price">
                                    ₹<?= number_format((float)$product["price"], 2) ?>
                                </p>

                                <a
                                    class="buy-button"
                                    href="products.php?category_id=<?= (int)$product["category_id"] ?>"
                                >
                                    Browse Category
                                </a>

                            </div>
                        </article>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="empty-message">
                        <h3>No products available</h3>
                        <p>Add products through the admin panel to display them here.</p>
                    </div>

                <?php endif; ?>

            </div>
        </section>

    </div>
</main>

<footer>
    <p>&copy; <?= date("Y") ?> My Shopping Store. All rights reserved.</p>
</footer>

</body>
</html>
