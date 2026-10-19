
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../includes/db.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Invalid product ID.");
}

$error = "";

// Fetch existing product
$stmt = $conn->prepare(
    "SELECT id, category_id, name, price, description, image
     FROM products WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();

$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    exit("Product not found.");
}

// Fetch categories
$categories = $conn->query(
    "SELECT id, name FROM categories ORDER BY name"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
        $error = "Please enter valid product details.";
    } else {
        // Verify category exists
        $check = $conn->prepare(
            "SELECT id FROM categories WHERE id = ?"
        );
        $check->bind_param("i", $categoryId);
        $check->execute();
        $validCategory = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$validCategory) {
            $error = "Please select a valid category.";
        }
    }

    $newImage = null;

    // Process a replacement image only if one was selected
    if (
        $error === "" &&
        isset($_FILES["image"]) &&
        $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
            $error = "Image upload failed.";
        } elseif ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
            $error = "Image must be 5 MB or smaller.";
        } else {
            $tmp = $_FILES["image"]["tmp_name"];
            $info = getimagesize($tmp);

            $allowed = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            if (!$info || !in_array($info["mime"], $allowed, true)) {
                $error = "Only JPEG, PNG and WebP images are allowed.";
            } elseif (!function_exists("imagecreatetruecolor")) {
                $error = "Enable the PHP GD extension.";
            } else {
                $src = match ($info["mime"]) {
                    "image/jpeg" => imagecreatefromjpeg($tmp),
                    "image/png" => imagecreatefrompng($tmp),
                    "image/webp" => imagecreatefromwebp($tmp)
                };

                if (!$src) {
                    $error = "Unable to process image.";
                } else {
                    $width = imagesx($src);
                    $height = imagesy($src);

                    $scale = min(800 / $width, 800 / $height, 1);

                    $newWidth = max(1, (int) round($width * $scale));
                    $newHeight = max(1, (int) round($height * $scale));

                    $dst = imagecreatetruecolor($newWidth, $newHeight);

                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);

                    $transparent = imagecolorallocatealpha(
                        $dst, 0, 0, 0, 127
                    );
                    imagefill($dst, 0, 0, $transparent);

                    imagecopyresampled(
                        $dst, $src, 0, 0, 0, 0,
                        $newWidth, $newHeight, $width, $height
                    );

                    $uploadDir = __DIR__ . "/../uploads/";

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $newImage = bin2hex(random_bytes(16)) . ".png";

                    if (!imagepng($dst, $uploadDir . $newImage, 7)) {
                        $error = "Could not save replacement image.";
                        $newImage = null;
                    }

                    imagedestroy($src);
                    imagedestroy($dst);
                }
            }
        }
    }

    if ($error === "") {
        if ($newImage !== null) {
            $update = $conn->prepare(
                "UPDATE products
                 SET category_id = ?, name = ?, price = ?,
                     description = ?, image = ?
                 WHERE id = ?"
            );

            $update->bind_param(
                "isdssi",
                $categoryId,
                $name,
                $price,
                $description,
                $newImage,
                $id
            );
        } else {
            $update = $conn->prepare(
                "UPDATE products
                 SET category_id = ?, name = ?, price = ?,
                     description = ?
                 WHERE id = ?"
            );

            $update->bind_param(
                "isdsi",
                $categoryId,
                $name,
                $price,
                $description,
                $id
            );
        }

        if ($update->execute()) {
            // Remove old image only after successful update
            if ($newImage !== null && !empty($product["image"])) {
                $oldPath = __DIR__ . "/../uploads/" .
                    basename($product["image"]);

                if (is_file($oldPath)) {
                    unlink($oldPath);
                }
            }

            $update->close();

            header("Location: products.php?updated=1");
            exit;
        }

        if ($newImage !== null) {
            $newPath = __DIR__ . "/../uploads/" . $newImage;

            if (is_file($newPath)) {
                unlink($newPath);
            }
        }

        $update->close();
        $error = "Unable to update product.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Product</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        label {
            display: block;
            margin: 15px 0 6px;
        }

        input, select, textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
        }

        textarea {
            min-height: 100px;
        }

        button {
            margin-top: 20px;
            padding: 12px 18px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .error {
            color: #b91c1c;
        }

        .preview {
            width: 120px;
            height: 120px;
            object-fit: contain;
            display: block;
            margin-top: 10px;
        }
    </style>
</head>

<body>

<div class="container">
    <h2>Edit Product</h2>

    <?php if ($error !== ""): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Product Name</label>
        <input type="text" name="name" maxlength="150"
               value="<?= htmlspecialchars($product["name"]) ?>"
               required>

        <label>Category</label>
        <select name="category_id" required>
            <?php while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?= (int) $cat["id"] ?>"
                    <?= (int) $cat["id"] === (int) $product["category_id"]
                        ? "selected" : "" ?>>
                    <?= htmlspecialchars($cat["name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Price (₹)</label>
        <input type="number" name="price" min="0"
               max="99999999.99" step="0.01"
               value="<?= htmlspecialchars((string) $product["price"]) ?>"
               required>

        <label>Description</label>
        <textarea name="description"><?= htmlspecialchars($product["description"] ?? "") ?></textarea>

        <label>Current Image</label>

        <?php if (!empty($product["image"])): ?>
            <img class="preview"
                 src="../uploads/<?= rawurlencode(basename($product["image"])) ?>"
                 alt="Current product image">
        <?php else: ?>
            <p>No image uploaded.</p>
        <?php endif; ?>

        <label>Choose New Image (optional)</label>
        <input type="file" name="image"
               accept="image/jpeg,image/png,image/webp">

        <button type="submit">Update Product</button>
    </form>

    <p><a href="products.php">Back to Products</a></p>
</div>

</body>
</html>
