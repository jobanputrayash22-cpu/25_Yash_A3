
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../includes/db.php";

$message = "";
$messageType = "success";

// Handle product addition
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_product"])
) {
    $name = trim($_POST["name"] ?? "");
    $categoryId = filter_input(
        INPUT_POST, "category_id", FILTER_VALIDATE_INT
    );
    $price = filter_input(
        INPUT_POST, "price", FILTER_VALIDATE_FLOAT
    );
    $description = trim($_POST["description"] ?? "");

    if (
        $name === "" ||
        strlen($name) > 150 ||
        !$categoryId ||
        $price === false ||
        $price === null ||
        $price < 0 ||
        $price > 99999999.99
    ) {
        $message = "Please enter valid product details.";
        $messageType = "error";
    } elseif (
        !isset($_FILES["image"]) ||
        $_FILES["image"]["error"] !== UPLOAD_ERR_OK
    ) {
        $message = "Please select a product image.";
        $messageType = "error";
    } elseif ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
        $message = "Image must be 5 MB or smaller.";
        $messageType = "error";
    } else {
        // Verify category
        $check = $conn->prepare(
            "SELECT id FROM categories WHERE id = ?"
        );
        $check->bind_param("i", $categoryId);
        $check->execute();
        $validCategory = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$validCategory) {
            $message = "Please select a valid category.";
            $messageType = "error";
        } else {
            $tmp = $_FILES["image"]["tmp_name"];
            $info = getimagesize($tmp);

            $allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            if (
                !$info ||
                !in_array($info["mime"], $allowedTypes, true)
            ) {
                $message = "Only JPG, PNG and WebP images are allowed.";
                $messageType = "error";
            } elseif (!function_exists("imagecreatetruecolor")) {
                $message = "Enable the GD extension in PHP.";
                $messageType = "error";
            } else {
                $src = match ($info["mime"]) {
                    "image/jpeg" => imagecreatefromjpeg($tmp),
                    "image/png"  => imagecreatefrompng($tmp),
                    "image/webp" => imagecreatefromwebp($tmp)
                };

                if (!$src) {
                    $message = "Unable to process the image.";
                    $messageType = "error";
                } else {
                    // Resize image to maximum 800 x 800
                    $width = imagesx($src);
                    $height = imagesy($src);

                    $scale = min(
                        800 / $width,
                        800 / $height,
                        1
                    );

                    $newWidth = max(
                        1, (int) round($width * $scale)
                    );
                    $newHeight = max(
                        1, (int) round($height * $scale)
                    );

                    $dst = imagecreatetruecolor(
                        $newWidth, $newHeight
                    );

                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);

                    $transparent = imagecolorallocatealpha(
                        $dst, 0, 0, 0, 127
                    );
                    imagefill($dst, 0, 0, $transparent);

                    imagecopyresampled(
                        $dst, $src,
                        0, 0, 0, 0,
                        $newWidth, $newHeight,
                        $width, $height
                    );

                    $uploadDir = __DIR__ . "/../uploads/";

                    if (
                        !is_dir($uploadDir) &&
                        !mkdir($uploadDir, 0755, true) &&
                        !is_dir($uploadDir)
                    ) {
                        $message = "Could not create uploads directory.";
                        $messageType = "error";
                    } else {
                        $fileName = bin2hex(random_bytes(16)) . ".png";
                        $filePath = $uploadDir . $fileName;

                        if (!imagepng($dst, $filePath, 7)) {
                            $message = "Could not save product image.";
                            $messageType = "error";
                        } else {
                            $stmt = $conn->prepare(
                                "INSERT INTO products
                                (category_id, name, price, description, image)
                                VALUES (?, ?, ?, ?, ?)"
                            );

                            $stmt->bind_param(
                                "isdss",
                                $categoryId,
                                $name,
                                $price,
                                $description,
                                $fileName
                            );

                            if ($stmt->execute()) {
                                $stmt->close();
                                imagedestroy($src);
                                imagedestroy($dst);

                                header("Location: products.php?added=1");
                                exit;
                            }

                            $stmt->close();
                            unlink($filePath);

                            $message = "Could not save product to database.";
                            $messageType = "error";
                        }
                    }

                    imagedestroy($src);
                    imagedestroy($dst);
                }
            }
        }
    }
}

// Handle status messages
if (isset($_GET["added"])) {
    $message = "Product added successfully!";
    $messageType = "success";
} elseif (isset($_GET["updated"])) {
    $message = "Product updated successfully!";
    $messageType = "success";
} elseif (isset($_GET["deleted"])) {
    $message = "Product deleted successfully!";
    $messageType = "success";
} elseif (isset($_GET["error"])) {
    $errors = [
        "invalid" => "Invalid product ID.",
        "notfound" => "Product not found.",
        "deletefailed" => "Could not delete product."
    ];

    $message = $errors[$_GET["error"]] ?? "An error occurred.";
    $messageType = "error";
}

// Fetch categories
$categories = $conn->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
);

if (!$categories) {
    die("Could not load categories.");
}

// Fetch products
$result = $conn->query(
    "SELECT p.id, p.name, p.price, p.description, p.image,
            p.category_id, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     ORDER BY p.id DESC"
);

if (!$result) {
    die("Could not load products.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Products | Shopping Cart Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .panel {
            background: #fff;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        h2 {
            margin-top: 0;
        }

        .back {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .message {
            padding: 13px;
            margin-bottom: 18px;
            border-radius: 6px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .full {
            grid-column: 1 / -1;
        }

        button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
        }

        .add-btn {
            background: #2563eb;
            padding: 12px 20px;
        }

        .edit-btn {
            background: #d97706;
            text-decoration: none;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            display: inline-block;
            font-size: 13px;
        }

        .delete-btn {
            background: #dc2626;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #1e293b;
            color: white;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .product-img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            background: #f8fafc;
            border-radius: 5px;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .actions form {
            margin: 0;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .panel {
                padding: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h2>Manage Products</h2>

        <a class="back" href="dashboard.php">
            &larr; Back to Dashboard
        </a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Add Product Form -->
    <section class="panel">
        <h2>Add New Product</h2>

        <?php if ($categories->num_rows === 0): ?>

            <p>
                Add a category before adding products.
                <a href="categories.php">Manage Categories</a>
            </p>

        <?php else: ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">

                    <div>
                        <label for="name">Product Name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div>
                        <label for="category_id">Category</label>
                        <select
                            id="category_id"
                            name="category_id"
                            required
                        >
                            <option value="">Select Category</option>

                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= (int) $cat["id"] ?>">
                                    <?= htmlspecialchars($cat["name"]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label for="price">Price (₹)</label>
                        <input
                            type="number"
                            id="price"
                            name="price"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            required
                        >
                    </div>

                    <div>
                        <label for="image">Product Image</label>
                        <input
                            type="file"
                            id="image"
                            name="image"
                            accept="image/jpeg,image/png,image/webp"
                            required
                        >
                        <small>Maximum 5 MB. Resized to fit within 800 × 800.</small>
                    </div>

                    <div class="full">
                        <label for="description">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            placeholder="Enter product description"
                        ></textarea>
                    </div>

                    <div class="full">
                        <button
                            type="submit"
                            name="add_product"
                            class="add-btn"
                        >
                            + Add Product
                        </button>
                    </div>

                </div>
            </form>

        <?php endif; ?>
    </section>

    <!-- Product List -->
    <section class="panel">
        <h2>Product List</h2>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>
                            <td><?= (int) $row["id"] ?></td>

                            <td>
                                <?php if (!empty($row["image"])): ?>
                                    <img
                                        class="product-img"
                                        src="../uploads/<?= rawurlencode(basename($row["image"])) ?>"
                                        alt="<?= htmlspecialchars($row["name"]) ?>"
                                    >
                                <?php else: ?>
                                    No image
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row["name"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["category_name"] ?? "Uncategorized"
                                ) ?>
                            </td>

                            <td>
                                ₹<?= number_format(
                                    (float) $row["price"], 2
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["description"] ?? ""
                                ) ?>
                            </td>

                            <td>
                                <div class="actions">

                                    <a
                                        class="edit-btn"
                                        href="edit_product.php?id=<?= (int) $row["id"] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="delete_product.php"
                                        onsubmit="return confirm('Are you sure you want to delete this product?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $row["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="delete-btn"
                                        >
                                            Delete
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="empty">
                            No products found. Add your first product above.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>

</div>

</body>
</html>
