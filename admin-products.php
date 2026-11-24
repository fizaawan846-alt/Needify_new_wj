
<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/auth.php";

require_admin_login();

$message = "";
$message_type = "";
$query_error = "";

// CSRF TOKEN
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// SAFE OUTPUT
function escapeOutput($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// SAFE STRING LENGTH
function safeLength($value) {
    return function_exists("mb_strlen")
        ? mb_strlen($value, "UTF-8")
        : strlen($value);
}

// HANDLE POST ACTIONS
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $message_type = "error";

    } else {

        $action = $_POST["action"] ?? "";

        // ADD OR UPDATE PRODUCT
        if ($action === "add" || $action === "update") {

            $product_id = filter_var(
                $_POST["product_id"] ?? 0,
                FILTER_VALIDATE_INT
            );

            $product_id = $product_id === false ? 0 : $product_id;

            $name = trim($_POST["name"] ?? "");

            $category_id = filter_var(
                $_POST["category_id"] ?? 0,
                FILTER_VALIDATE_INT
            );

            $category_id = $category_id === false ? 0 : $category_id;

            $description = trim($_POST["description"] ?? "");
            $price = trim($_POST["price"] ?? "");
            $image = trim($_POST["image"] ?? "");

            $stock = filter_var(
                $_POST["stock"] ?? null,
                FILTER_VALIDATE_INT
            );

            $is_active = isset($_POST["is_active"]) ? 1 : 0;

            if (
                $name === "" ||
                $category_id <= 0 ||
                $price === "" ||
                !is_numeric($price) ||
                !is_finite((float)$price) ||
                (float)$price < 0 ||
                (float)$price > 99999999.99 ||
                $stock === false ||
                $stock === null ||
                $stock < 0 ||
                $stock > 2147483647
            ) {
                $message = "Please enter valid product information.";
                $message_type = "error";

            } elseif (
                safeLength($name) > 150 ||
                safeLength($image) > 255
            ) {
                $message = "Product name or image filename is too long.";
                $message_type = "error";

            } else {

                // GET CATEGORY NAME
                $category_stmt = $conn->prepare(
                    "SELECT name FROM categories WHERE id = ?"
                );

                if (!$category_stmt) {

                    $message = "Unable to verify product category.";
                    $message_type = "error";

                } else {

                    $category_stmt->bind_param("i", $category_id);
                    $category_stmt->execute();

                    $category_data = $category_stmt
                        ->get_result()
                        ->fetch_assoc();

                    $category_stmt->close();

                    if (!$category_data) {

                        $message = "Please select a valid category.";
                        $message_type = "error";

                    } else {

                        $category = $category_data["name"];
                        $price_value = (float)$price;

                        // ADD PRODUCT
                        if ($action === "add") {

                            $stmt = $conn->prepare(
                                "INSERT INTO products
                                (name, category, description, price, image, stock, is_active)
                                VALUES (?, ?, ?, ?, ?, ?, ?)"
                            );

                            if ($stmt) {

                                $stmt->bind_param(
                                    "sssdsii",
                                    $name,
                                    $category,
                                    $description,
                                    $price_value,
                                    $image,
                                    $stock,
                                    $is_active
                                );

                                if ($stmt->execute()) {
                                    $message = "Product added successfully.";
                                    $message_type = "success";
                                } else {
                                    $message = "Unable to add product.";
                                    $message_type = "error";
                                }

                                $stmt->close();

                            } else {

                                $message = "Unable to prepare product information.";
                                $message_type = "error";
                            }

                        } else {

                            // UPDATE PRODUCT
                            if ($product_id <= 0) {

                                $message = "Invalid product.";
                                $message_type = "error";

                            } else {

                                $stmt = $conn->prepare(
                                    "UPDATE products
                                     SET name = ?, category = ?, description = ?,
                                         price = ?, image = ?, stock = ?,
                                         is_active = ?
                                     WHERE id = ?"
                                );

                                if ($stmt) {

                                    $stmt->bind_param(
                                        "sssdsiii",
                                        $name,
                                        $category,
                                        $description,
                                        $price_value,
                                        $image,
                                        $stock,
                                        $is_active,
                                        $product_id
                                    );

                                    if ($stmt->execute()) {
                                        $message = "Product updated successfully.";
                                        $message_type = "success";
                                    } else {
                                        $message = "Unable to update product.";
                                        $message_type = "error";
                                    }

                                    $stmt->close();

                                } else {

                                    $message = "Unable to prepare product update.";
                                    $message_type = "error";
                                }
                            }
                        }
                    }
                }
            }
        }

        // DELETE PRODUCT
        elseif ($action === "delete") {

            $product_id = filter_var(
                $_POST["product_id"] ?? 0,
                FILTER_VALIDATE_INT
            );

            if ($product_id === false || $product_id <= 0) {

                $message = "Invalid product.";
                $message_type = "error";

            } else {

                // CHECK ORDER ITEMS
                $order_check = $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM order_items
                     WHERE product_id = ?"
                );

                if (!$order_check) {

                    $message = "Unable to verify product orders.";
                    $message_type = "error";

                } else {

                    $order_check->bind_param("i", $product_id);
                    $order_check->execute();

                    $order_data = $order_check
                        ->get_result()
                        ->fetch_assoc();

                    $order_check->close();

                    if ((int)$order_data["total"] > 0) {

                        $message =
                            "This product is linked to an order. " .
                            "Deactivate it instead of deleting it.";

                        $message_type = "error";

                    } else {

                        $delete = $conn->prepare(
                            "DELETE FROM products WHERE id = ?"
                        );

                        if ($delete) {

                            $delete->bind_param("i", $product_id);

                            if (
                                $delete->execute() &&
                                $delete->affected_rows > 0
                            ) {
                                $message = "Product deleted successfully.";
                                $message_type = "success";
                            } else {
                                $message = "Product not found or could not be deleted.";
                                $message_type = "error";
                            }

                            $delete->close();

                        } else {

                            $message = "Unable to prepare product deletion.";
                            $message_type = "error";
                        }
                    }
                }
            }
        }
    }
}

// FETCH CATEGORIES
$categories = $conn->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
);

if (!$categories) {
    $query_error = "Unable to load categories.";
}

// SEARCH PRODUCTS
$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT p.id, p.name, p.category, p.description,
                p.price, p.image, p.stock, p.is_active,
                p.created_at, c.id AS category_id
         FROM products p
         LEFT JOIN categories c ON c.name = p.category
         WHERE p.name LIKE ? OR p.category LIKE ?
         ORDER BY p.id DESC"
    );

    if ($stmt) {
        $search_value = "%" . $search . "%";
        $stmt->bind_param("ss", $search_value, $search_value);
    }

} else {

    $stmt = $conn->prepare(
        "SELECT p.id, p.name, p.category, p.description,
                p.price, p.image, p.stock, p.is_active,
                p.created_at, c.id AS category_id
         FROM products p
         LEFT JOIN categories c ON c.name = p.category
         ORDER BY p.id DESC"
    );
}

$products = false;

if ($stmt) {

    if ($stmt->execute()) {
        $products = $stmt->get_result();
    } else {
        $query_error = "Unable to load products.";
    }

} else {
    $query_error = "Unable to prepare product list.";
}

// TOTAL PRODUCT COUNT
$count_result = $conn->query(
    "SELECT COUNT(*) AS total FROM products"
);

$total_all_products = 0;

if ($count_result) {
    $total_all_products = (int)$count_result->fetch_assoc()["total"];
} else {
    $query_error = "Unable to load product count.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Product Management - Needify Admin</title>

    <link rel="icon" type="image/png" href="assets/images/logo.png">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --bg: #FFFAF5;
            --card: #FFFFFF;
            --text: #302747;
            --muted: #82798F;
            --border: #EEE7F3;
            --primary: #7956C8;
            --primary-dark: #6844B5;
            --sidebar: linear-gradient(180deg, #7956C8, #6844B5);
            --input: #FFFFFF;
            --pink: #FFE5EF;
            --danger: #D94B69;
        }

        body.dark-mode {
            --bg: #171323;
            --card: #241D32;
            --text: #F8F2FF;
            --muted: #C3B6D3;
            --border: #40334F;
            --primary: #A98AE8;
            --primary-dark: #8E6BD4;
            --sidebar: linear-gradient(180deg, #302044, #21182F);
            --input: #30263F;
            --pink: #493044;
            --danger: #F07891;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: 0.3s;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--sidebar);
            color: white;
            padding: 25px 15px;
            overflow-y: auto;
        }

        .logo {
            text-align: center;
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 35px;
        }

        .logo a {
            color: white;
            text-decoration: none;
        }

        .sidebar a {
            display: block;
            color: #F8F2FF;
            padding: 13px 15px;
            margin: 7px 0;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--pink);
            color: #302747;
        }

        .sidebar i {
            width: 25px;
        }

        .main {
            margin-left: 240px;
            padding: 25px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 25px;
        }

        .tools {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        button {
            cursor: pointer;
            border: none;
            border-radius: 7px;
            padding: 10px 14px;
            font-size: 14px;
            transition: 0.2s;
        }

        .tool-btn {
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .tool-btn:hover {
            background: var(--pink);
            color: var(--text);
        }

        .card {
            background: var(--card);
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 22px;
            border: 1px solid var(--border);
            box-shadow: 0 3px 12px rgba(48,39,71,0.05);
        }

        .card h2 {
            margin-top: 0;
            font-size: 19px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            background: var(--input);
            color: var(--text);
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
        }

        input::placeholder,
        textarea::placeholder {
            color: var(--muted);
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        input:focus, select:focus, textarea:focus {
            outline: 2px solid var(--primary);
            border-color: var(--primary);
        }

        .primary-btn,
        .edit-btn,
        .search-btn {
            background: var(--primary);
            color: white;
        }

        .primary-btn:hover,
        .edit-btn:hover,
        .search-btn:hover {
            background: var(--primary-dark);
        }

        .delete-btn {
            background: var(--danger);
            color: white;
        }

        .cancel-btn {
            background: #82798F;
            color: white;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .success {
            background: #E8F8EC;
            color: #246B38;
        }

        .error {
            background: #FFF0F3;
            color: #A52F4B;
        }

        body.dark-mode .success {
            background: #20432E;
            color: #C9F5D4;
        }

        body.dark-mode .error {
            background: #512535;
            color: #FFD5DF;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th, td {
            padding: 13px 11px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: middle;
        }

        th {
            color: var(--text);
            font-size: 13px;
            background: #F4EFFB;
        }

        body.dark-mode th {
            background: #30263F;
        }

        td {
            font-size: 14px;
        }

        tbody tr:hover {
            background: #FFFAFD;
        }

        body.dark-mode tbody tr:hover {
            background: #30263F;
        }

        .actions {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .actions button {
            padding: 8px 11px;
        }

        .count {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary);
        }

        .muted {
            color: var(--muted);
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .active-status {
            background: #E8F8EC;
            color: #246B38;
        }

        .inactive-status {
            background: #FFF0F3;
            color: #A52F4B;
        }

        body.dark-mode .active-status {
            background: #20432E;
            color: #C9F5D4;
        }

        body.dark-mode .inactive-status {
            background: #512535;
            color: #FFD5DF;
        }

        .product-image {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 7px;
            border: 1px solid var(--border);
        }

        .form-buttons {
            display: flex;
            gap: 9px;
            margin-top: 15px;
        }

        footer {
            text-align: center;
            color: var(--muted);
            padding: 22px 10px;
            margin-top: 35px;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 800px) {
            .sidebar {
                width: 190px;
            }

            .main {
                margin-left: 190px;
                padding: 18px;
            }
        }

        @media (max-width: 600px) {
            .sidebar {
                width: 65px;
                padding: 15px 7px;
            }

            .sidebar .logo {
                font-size: 12px;
            }

            .sidebar a {
                text-align: center;
                padding: 13px 5px;
                font-size: 0;
            }

            .sidebar a i {
                font-size: 17px;
            }

            .main {
                margin-left: 65px;
                padding: 12px;
            }

            .topbar h1 {
                font-size: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">
        <a href="index.php">Needify</a>
    </div>

    <a href="admin-dashboard.php">
        <i class="fas fa-chart-line"></i> Dashboard
    </a>

    <a href="admin-user.php">
        <i class="fas fa-users"></i> Users
    </a>

    <a href="admin-needs.php">
        <i class="fas fa-hand-holding-heart"></i> Needs
    </a>

    <a href="admin-messages.php">
        <i class="fas fa-envelope"></i> Messages
    </a>

    <a href="admin-orders.php">
        <i class="fas fa-shopping-cart"></i> Orders
    </a>

    <a href="admin-category.php">
        <i class="fas fa-list"></i> Categories
    </a>

    <a href="admin-products.php" class="active">
        <i class="fas fa-box"></i> Products
    </a>

    <a href="admin-logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>

</aside>

<main class="main">

    <div class="topbar">

        <h1>Product Management</h1>

        <div class="tools">

            <button class="tool-btn" onclick="toggleTheme()">
                <i class="fas fa-moon"></i> Theme
            </button>

            <button class="tool-btn" onclick="changeFont(1)">A+</button>
            <button class="tool-btn" onclick="changeFont(-1)">A-</button>

            <button class="tool-btn" onclick="textAlignCenter()">
                <i class="fas fa-align-center"></i>
            </button>

            <button class="tool-btn" onclick="window.scrollTo(0,0)">
                <i class="fas fa-arrow-up"></i>
            </button>

            <button class="tool-btn"
                    onclick="window.scrollTo(0,document.body.scrollHeight)">
                <i class="fas fa-arrow-down"></i>
            </button>

        </div>

    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo escapeOutput($message_type); ?>">
            <?php echo escapeOutput($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($query_error !== ""): ?>
        <div class="message error">
            <?php echo escapeOutput($query_error); ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2>Total Products</h2>

        <div class="count">
            <?php echo $total_all_products; ?>
        </div>

        <p class="muted">Products available in the Needify database.</p>

    </div>

    <div class="card" id="productForm">

        <h2 id="formTitle">Add New Product</h2>

        <?php if (!$categories || $categories->num_rows === 0): ?>

            <p class="muted">
                No categories available. Please add a category first.
            </p>

        <?php else: ?>

            <form method="POST" action="admin-products.php">

                <input type="hidden"
                       name="csrf_token"
                       value="<?php echo escapeOutput($_SESSION["csrf_token"]); ?>">

                <input type="hidden"
                       name="action"
                       id="formAction"
                       value="add">

                <input type="hidden"
                       name="product_id"
                       id="productId"
                       value="">

                <div class="form-grid">

                    <div class="form-group">
                        <label for="productName">Product Name</label>

                        <input type="text"
                               id="productName"
                               name="name"
                               maxlength="150"
                               required
                               placeholder="Enter product name">
                    </div>

                    <div class="form-group">
                        <label for="categoryId">Category</label>

                        <select id="categoryId" name="category_id" required>

                            <option value="">Select Category</option>

                            <?php
                            $categories->data_seek(0);

                            while ($cat = $categories->fetch_assoc()):
                            ?>

                                <option value="<?php echo (int)$cat["id"]; ?>">
                                    <?php echo escapeOutput($cat["name"]); ?>
                                </option>

                            <?php endwhile; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label for="productPrice">Price</label>

                        <input type="number"
                               id="productPrice"
                               name="price"
                               min="0"
                               max="99999999.99"
                               step="0.01"
                               required
                               placeholder="Enter price">
                    </div>

                    <div class="form-group">
                        <label for="productStock">Stock</label>

                        <input type="number"
                               id="productStock"
                               name="stock"
                               min="0"
                               step="1"
                               required
                               placeholder="Enter stock quantity">
                    </div>

                    <div class="form-group full">
                        <label for="productDescription">Description</label>

                        <textarea id="productDescription"
                                  name="description"
                                  placeholder="Enter product description"></textarea>
                    </div>

                    <div class="form-group full">
                        <label for="productImage">Image Filename / Path</label>

                        <input type="text"
                               id="productImage"
                               name="image"
                               maxlength="255"
                               placeholder="Example: uploads/product.jpg">
                    </div>

                    <div class="form-group full">
                        <label>
                            <input type="checkbox"
                                   id="productActive"
                                   name="is_active"
                                   value="1"
                                   checked
                                   style="width:auto;">
                            Product is Active
                        </label>
                    </div>

                </div>

                <div class="form-buttons">

                    <button type="submit"
                            class="primary-btn"
                            id="submitButton">
                        <i class="fas fa-plus"></i> Add Product
                    </button>

                    <button type="button"
                            class="cancel-btn"
                            id="cancelButton"
                            onclick="resetProductForm()"
                            style="display:none;">
                        Cancel
                    </button>

                </div>

            </form>

        <?php endif; ?>

    </div>

    <div class="card">

        <h2>Manage Products</h2>

        <form method="GET"
              action="admin-products.php"
              style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">

            <input type="text"
                   name="search"
                   placeholder="Search product or category..."
                   value="<?php echo escapeOutput($search); ?>"
                   style="flex:1;min-width:180px;">

            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>

            <a href="admin-products.php" style="text-decoration:none;">
                <button type="button" class="cancel-btn">Reset</button>
            </a>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($products && $products->num_rows > 0): ?>

                    <?php while ($product = $products->fetch_assoc()): ?>

                        <tr>

                            <td><?php echo (int)$product["id"]; ?></td>

                            <td>
                                <?php if (!empty($product["image"])): ?>
                                    <img class="product-image"
                                         src="<?php echo escapeOutput($product["image"]); ?>"
                                         alt="Product"
                                         onerror="this.style.display='none';">
                                <?php else: ?>
                                    <span class="muted">No image</span>
                                <?php endif; ?>
                            </td>

                            <td><?php echo escapeOutput($product["name"]); ?></td>

                            <td><?php echo escapeOutput($product["category"]); ?></td>

                            <td>
                                Rs. <?php echo number_format((float)$product["price"], 2); ?>
                            </td>

                            <td><?php echo (int)$product["stock"]; ?></td>

                            <td>
                                <?php if ((int)$product["is_active"] === 1): ?>
                                    <span class="status active-status">Active</span>
                                <?php else: ?>
                                    <span class="status inactive-status">Inactive</span>
                                <?php endif; ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <button type="button"
                                            class="edit-btn"
                                            onclick='editProduct(<?php
                                                echo json_encode([
                                                    "id" => (int)$product["id"],
                                                    "name" => $product["name"],
                                                    "category_id" => (int)($product["category_id"] ?? 0),
                                                    "description" => $product["description"],
                                                    "price" => $product["price"],
                                                    "image" => $product["image"],
                                                    "stock" => (int)$product["stock"],
                                                    "is_active" => (int)$product["is_active"]
                                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
                                            ?>)'>

                                        <i class="fas fa-edit"></i>

                                    </button>

                                    <form method="POST"
                                          action="admin-products.php"
                                          onsubmit="return confirm('Are you sure you want to delete this product?');">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?php echo escapeOutput($_SESSION["csrf_token"]); ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="delete">

                                        <input type="hidden"
                                               name="product_id"
                                               value="<?php echo (int)$product["id"]; ?>">

                                        <button type="submit" class="delete-btn">
                                            <i class="fas fa-trash"></i>
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php elseif ($products): ?>

                    <tr>
                        <td colspan="8" style="text-align:center;">
                            No products found.
                        </td>
                    </tr>

                <?php else: ?>

                    <tr>
                        <td colspan="8" style="text-align:center;">
                            Products could not be loaded.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> Needify. All Rights Reserved.
    </footer>

</main>

<script>

// THEME
function applySavedTheme() {
    if (localStorage.getItem("needifyTheme") === "dark") {
        document.body.classList.add("dark-mode");
    }
}

function toggleTheme() {
    document.body.classList.toggle("dark-mode");

    localStorage.setItem(
        "needifyTheme",
        document.body.classList.contains("dark-mode")
            ? "dark"
            : "light"
    );
}

// FONT SIZE
let fontLevel = parseInt(
    localStorage.getItem("needifyFontLevel") || "0"
);

if (!Number.isFinite(fontLevel)) {
    fontLevel = 0;
}

fontLevel = Math.max(-3, Math.min(5, fontLevel));

function applyFontSize() {
    document.documentElement.style.fontSize =
        (16 + fontLevel) + "px";
}

function changeFont(amount) {
    fontLevel += amount;

    if (fontLevel > 5) fontLevel = 5;
    if (fontLevel < -3) fontLevel = -3;

    localStorage.setItem("needifyFontLevel", fontLevel);
    applyFontSize();
}

// CENTER TEXT
function textAlignCenter() {
    document.querySelector(".main").style.textAlign = "center";

    document.querySelectorAll("table th, table td").forEach(function(el) {
        el.style.textAlign = "center";
    });
}

// EDIT PRODUCT
function editProduct(product) {

    document.getElementById("productId").value = product.id;
    document.getElementById("productName").value = product.name;
    document.getElementById("categoryId").value = product.category_id;
    document.getElementById("productDescription").value =
        product.description || "";
    document.getElementById("productPrice").value = product.price;
    document.getElementById("productImage").value = product.image || "";
    document.getElementById("productStock").value = product.stock;
    document.getElementById("productActive").checked =
        product.is_active === 1;

    document.getElementById("formAction").value = "update";

    document.getElementById("formTitle").innerText =
        "Update Product";

    document.getElementById("submitButton").innerHTML =
        '<i class="fas fa-save"></i> Update Product';

    document.getElementById("cancelButton").style.display =
        "inline-block";

    document.getElementById("productForm").scrollIntoView({
        behavior: "smooth"
    });
}

// RESET FORM
function resetProductForm() {

    document.getElementById("productId").value = "";
    document.getElementById("productName").value = "";
    document.getElementById("categoryId").value = "";
    document.getElementById("productDescription").value = "";
    document.getElementById("productPrice").value = "";
    document.getElementById("productImage").value = "";
    document.getElementById("productStock").value = "";
    document.getElementById("productActive").checked = true;

    document.getElementById("formAction").value = "add";

    document.getElementById("formTitle").innerText =
        "Add New Product";

    document.getElementById("submitButton").innerHTML =
        '<i class="fas fa-plus"></i> Add Product';

    document.getElementById("cancelButton").style.display =
        "none";
}

// PAGE UP / PAGE DOWN
document.addEventListener("keydown", function(event) {

    if (event.key === "PageUp") {
        window.scrollBy({
            top: -window.innerHeight,
            behavior: "smooth"
        });
    }

    if (event.key === "PageDown") {
        window.scrollBy({
            top: window.innerHeight,
            behavior: "smooth"
        });
    }

});

applySavedTheme();
applyFontSize();

</script>

</body>
</html>

<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
?>
