
<?php
session_start();
require_once "includes/db.php";

function escapeHtml($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Get selected category
$categoryId = filter_input(INPUT_GET, "category_id", FILTER_VALIDATE_INT);

if (!$categoryId || $categoryId < 1) {
    $categoryId = 0;
}

// Fetch categories
$categories = [];

$categoryResult = $conn->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
);

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get selected category name
$selectedCategoryName = "All Products";

if ($categoryId > 0) {
    $stmt = $conn->prepare(
        "SELECT name FROM categories WHERE id = ?"
    );

    $stmt->bind_param("i", $categoryId);
    $stmt->execute();

    $result = $stmt->get_result();
    $category = $result->fetch_assoc();

    $stmt->close();

    if ($category) {
        $selectedCategoryName = $category["name"];
    } else {
        $categoryId = 0;
    }
}

// Ensure the customer has a CSRF token
if (isset($_SESSION["user_id"]) && empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= escapeHtml($selectedCategoryName) ?> - Shopping Store</title>

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
            align-items: center;
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
            max-width: 1400px;
            margin: 30px auto;
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
            line-height: 1.6;
        }

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

        .content {
            min-width: 0;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 22px;
        }

        .section-heading h2 {
            font-size: 25px;
        }

        .button {
            display: inline-block;
            background: #0d6efd;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
        }

        .button:hover {
            background: #0b5ed7;
        }

        #status {
            margin-bottom: 18px;
            color: #666;
            line-height: 1.6;
        }

        .products {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        .product-card {
            min-width: 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
            display: flex;
            flex-direction: column;
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
            background: #eee;
            color: #666;
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

        .cart-form {
            margin-top: auto;
        }

        .quantity-label {
            display: block;
            font-size: 13px;
            color: #555;
            margin-bottom: 7px;
        }

        .quantity-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .buy-button {
            display: block;
            width: 100%;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            text-align: center;
            padding: 11px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }

        .buy-button:hover {
            background: #0b5ed7;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .page-button {
            border: 1px solid #ddd;
            background: white;
            color: #333;
            padding: 9px 14px;
            border-radius: 5px;
            cursor: pointer;
        }

        .page-button:hover {
            background: #e9ecef;
        }

        .page-button.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }

        .page-button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
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

        footer {
            margin-top: 50px;
            padding: 22px;
            text-align: center;
            background: #212529;
            color: white;
        }

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
        }

        @media (max-width: 480px) {
            header {
                align-items: flex-start;
                flex-direction: column;
            }

            nav {
                gap: 12px;
            }

            .page-title h2 {
                font-size: 24px;
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

    <div class="page-title">
        <h2><?= escapeHtml($selectedCategoryName) ?></h2>
        <p>Browse our products and find what you need.</p>
    </div>

    <div class="layout">

        <!-- Categories -->
        <aside class="sidebar">
            <h3>Categories</h3>

            <a
                href="products.php"
                class="category-link <?= $categoryId === 0 ? "active" : "" ?>"
            >
                All Products
            </a>

            <?php foreach ($categories as $category): ?>
                <a
                    href="products.php?category_id=<?= (int)$category["id"] ?>"
                    class="category-link <?= $categoryId === (int)$category["id"] ? "active" : "" ?>"
                >
                    <?= escapeHtml($category["name"]) ?>
                </a>
            <?php endforeach; ?>
        </aside>

        <!-- Products -->
        <section class="content">

            <div class="section-heading">
                <h2>Our Products</h2>
                <a href="cart.php" class="button">View Cart</a>
            </div>

            <p id="status" aria-live="polite">Loading products...</p>

            <div id="products" class="products"></div>

            <div
                id="pagination"
                class="pagination"
                aria-label="Product pages"
            ></div>

        </section>

    </div>
</main>

<footer>
    <p>&copy; <?= date("Y") ?> My Shopping Store. All rights reserved.</p>
</footer>

<script>
    const selectedCategoryId = <?= (int)$categoryId ?>;
    const isLoggedIn = <?= isset($_SESSION["user_id"]) ? "true" : "false" ?>;
    const csrfToken = <?= json_encode($_SESSION["csrf_token"] ?? "") ?>;
</script>

<script>
(function () {
    const productsContainer = document.getElementById("products");
    const paginationContainer = document.getElementById("pagination");
    const statusElement = document.getElementById("status");

    let currentPage = 1;

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, function (char) {
            return {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;"
            }[char];
        });
    }

    function getImageUrl(image) {
        if (!image) {
            return "";
        }

        // Support filenames such as "shirt.png" or "uploads/shirt.png".
        const filename = String(image)
            .replace(/\\/g, "/")
            .split("/")
            .pop();

        if (!filename || filename === "." || filename === "..") {
            return "";
        }

        return "uploads/" + encodeURIComponent(filename);
    }

    function createProductCard(product) {
        const card = document.createElement("article");
        card.className = "product-card";

        const imageUrl = getImageUrl(product.image);
        const imageArea = document.createElement("div");

        if (imageUrl) {
            const img = document.createElement("img");
            img.src = imageUrl;
            img.alt = product.name || "Product image";
            img.loading = "lazy";

            img.onerror = function () {
                const placeholder = document.createElement("div");
                placeholder.className = "image-placeholder";
                placeholder.textContent = "Image not found";
                img.replaceWith(placeholder);
            };

            imageArea.appendChild(img);
        } else {
            const placeholder = document.createElement("div");
            placeholder.className = "image-placeholder";
            placeholder.textContent = "No image uploaded";
            imageArea.appendChild(placeholder);
        }

        card.appendChild(imageArea);

        const info = document.createElement("div");
        info.className = "product-info";

        const title = document.createElement("h3");
        title.textContent = product.name || "Untitled product";
        info.appendChild(title);

        const description = document.createElement("p");
        description.className = "description";
        const descriptionText = product.description || "";
        description.textContent = descriptionText.length > 100
            ? descriptionText.substring(0, 100) + "..."
            : descriptionText;
        info.appendChild(description);

        const price = document.createElement("p");
        price.className = "price";
        price.textContent = "₹" + Number(product.price || 0).toLocaleString(
            "en-IN",
            { minimumFractionDigits: 2, maximumFractionDigits: 2 }
        );
        info.appendChild(price);

        if (isLoggedIn) {
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "add_to_cart.php";
            form.className = "cart-form";

            const token = document.createElement("input");
            token.type = "hidden";
            token.name = "csrf_token";
            token.value = csrfToken;
            form.appendChild(token);

            const productId = document.createElement("input");
            productId.type = "hidden";
            productId.name = "product_id";
            productId.value = product.id;
            form.appendChild(productId);

            const label = document.createElement("label");
            label.className = "quantity-label";
            label.textContent = "Quantity";

            const quantity = document.createElement("input");
            quantity.type = "number";
            quantity.name = "quantity";
            quantity.className = "quantity-input";
            quantity.value = "1";
            quantity.min = "1";
            quantity.max = "99";
            quantity.required = true;

            label.appendChild(quantity);
            form.appendChild(label);

            const button = document.createElement("button");
            button.type = "submit";
            button.className = "buy-button";
            button.textContent = "Add to Cart";
            form.appendChild(button);

            info.appendChild(form);
        } else {
            const loginLink = document.createElement("a");
            loginLink.href = "login.php";
            loginLink.className = "buy-button";
            loginLink.textContent = "Login to Buy";
            info.appendChild(loginLink);
        }

        card.appendChild(info);
        return card;
    }

    function renderPagination(totalPages) {
        paginationContainer.replaceChildren();

        if (totalPages <= 1) {
            return;
        }

        function addButton(label, page, disabled, active) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "page-button" + (active ? " active" : "");
            button.textContent = label;
            button.disabled = disabled;

            if (active) {
                button.setAttribute("aria-current", "page");
            }

            button.addEventListener("click", function () {
                loadProducts(page);
            });

            paginationContainer.appendChild(button);
        }

        addButton("Previous", currentPage - 1, currentPage <= 1, false);

        for (let page = 1; page <= totalPages; page++) {
            addButton(String(page), page, false, page === currentPage);
        }

        addButton("Next", currentPage + 1, currentPage >= totalPages, false);
    }

    async function loadProducts(page) {
        currentPage = page;
        statusElement.textContent = "Loading products...";
        productsContainer.replaceChildren();
        paginationContainer.replaceChildren();

        const params = new URLSearchParams();
        params.set("page", String(page));

        if (selectedCategoryId > 0) {
            params.set("category_id", String(selectedCategoryId));
        }

        try {
            const response = await fetch(
                "api/products.php?" + params.toString(),
                { headers: { "Accept": "application/json" } }
            );

            if (!response.ok) {
                throw new Error("Could not load products.");
            }

            const data = await response.json();

            if (!Array.isArray(data.products)) {
                throw new Error(data.error || "Invalid product response.");
            }

            if (data.products.length === 0) {
                statusElement.textContent = "No products found.";
                const empty = document.createElement("div");
                empty.className = "empty-message";
                empty.textContent = "No products available in this category.";
                productsContainer.appendChild(empty);
                return;
            }

            data.products.forEach(function (product) {
                productsContainer.appendChild(createProductCard(product));
            });

            statusElement.textContent =
                "Showing " + data.products.length +
                " of " + (data.totalProducts ?? data.products.length) +
                " products.";

            renderPagination(Number(data.totalPages || 1));

        } catch (error) {
            statusElement.textContent =
                "Unable to load products. Check api/products.php and try again.";

            const message = document.createElement("div");
            message.className = "empty-message";
            message.textContent = error.message;
            productsContainer.appendChild(message);
        }
    }

    loadProducts(1);
})();
</script>

</body>
</html>
