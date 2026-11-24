
<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/auth.php";

require_admin_login();

$admin_username = $_SESSION["admin_username"] ?? "Admin";

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

// HANDLE FORM ACTIONS
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $message_type = "error";

    } else {

        $action = $_POST["action"] ?? "";

        // ADD CATEGORY
        if ($action === "add") {

            $category_name = trim($_POST["category_name"] ?? "");

            if ($category_name === "") {

                $message = "Please enter a category name.";
                $message_type = "error";

            } elseif (safeLength($category_name) > 100) {

                $message = "Category name cannot exceed 100 characters.";
                $message_type = "error";

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO categories (name) VALUES (?)"
                );

                if (!$stmt) {

                    $message = "Unable to prepare category information.";
                    $message_type = "error";

                } else {

                    $stmt->bind_param("s", $category_name);

                    if ($stmt->execute()) {
                        $message = "Category added successfully.";
                        $message_type = "success";
                    } else {

                        if ($stmt->errno === 1062) {
                            $message = "This category already exists.";
                        } else {
                            $message = "Unable to add category.";
                        }

                        $message_type = "error";
                    }

                    $stmt->close();
                }
            }
        }

        // UPDATE CATEGORY
        elseif ($action === "update") {

            $category_id = filter_var(
                $_POST["category_id"] ?? 0,
                FILTER_VALIDATE_INT
            );

            $category_id = $category_id === false ? 0 : $category_id;

            $category_name = trim($_POST["category_name"] ?? "");

            if ($category_id <= 0 || $category_name === "") {

                $message = "Please provide valid category information.";
                $message_type = "error";

            } elseif (safeLength($category_name) > 100) {

                $message = "Category name cannot exceed 100 characters.";
                $message_type = "error";

            } else {

                // CHECK WHETHER CATEGORY EXISTS
                $check = $conn->prepare(
                    "SELECT id FROM categories WHERE id = ?"
                );

                if (!$check) {

                    $message = "Unable to verify category.";
                    $message_type = "error";

                } else {

                    $check->bind_param("i", $category_id);
                    $check->execute();

                    $exists = $check->get_result()->num_rows > 0;
                    $check->close();

                    if (!$exists) {

                        $message = "Category not found.";
                        $message_type = "error";

                    } else {

                        // CHECK DUPLICATE CATEGORY NAME
                        $duplicate = $conn->prepare(
                            "SELECT id FROM categories
                             WHERE name = ? AND id != ?"
                        );

                        if (!$duplicate) {

                            $message = "Unable to check category name.";
                            $message_type = "error";

                        } else {

                            $duplicate->bind_param(
                                "si",
                                $category_name,
                                $category_id
                            );

                            $duplicate->execute();

                            $duplicate_exists =
                                $duplicate->get_result()->num_rows > 0;

                            $duplicate->close();

                            if ($duplicate_exists) {

                                $message = "Another category already has this name.";
                                $message_type = "error";

                            } else {

                                // GET OLD CATEGORY NAME
                                $old_query = $conn->prepare(
                                    "SELECT name FROM categories WHERE id = ?"
                                );

                                if (!$old_query) {

                                    $message = "Unable to read existing category.";
                                    $message_type = "error";

                                } else {

                                    $old_query->bind_param("i", $category_id);
                                    $old_query->execute();

                                    $old_category = $old_query
                                        ->get_result()
                                        ->fetch_assoc();

                                    $old_query->close();

                                    if (!$old_category) {

                                        $message = "Category not found.";
                                        $message_type = "error";

                                    } else {

                                        $old_name = $old_category["name"];

                                        try {

                                            $conn->begin_transaction();

                                            // UPDATE CATEGORY NAME
                                            $update = $conn->prepare(
                                                "UPDATE categories
                                                 SET name = ?
                                                 WHERE id = ?"
                                            );

                                            if (!$update) {
                                                throw new Exception(
                                                    "Category update preparation failed."
                                                );
                                            }

                                            $update->bind_param(
                                                "si",
                                                $category_name,
                                                $category_id
                                            );

                                            if (!$update->execute()) {
                                                throw new Exception(
                                                    "Category update failed."
                                                );
                                            }

                                            $update->close();

                                            // UPDATE PRODUCTS USING OLD CATEGORY NAME
                                            $products_update = $conn->prepare(
                                                "UPDATE products
                                                 SET category = ?
                                                 WHERE category = ?"
                                            );

                                            if (!$products_update) {
                                                throw new Exception(
                                                    "Product update preparation failed."
                                                );
                                            }

                                            $products_update->bind_param(
                                                "ss",
                                                $category_name,
                                                $old_name
                                            );

                                            if (!$products_update->execute()) {
                                                throw new Exception(
                                                    "Product category update failed."
                                                );
                                            }

                                            $products_update->close();

                                            $conn->commit();

                                            $message = "Category updated successfully.";
                                            $message_type = "success";

                                        } catch (Throwable $e) {

                                            $conn->rollback();

                                            $message =
                                                "Unable to update category. Please try again.";

                                            $message_type = "error";
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // DELETE CATEGORY
        elseif ($action === "delete") {

            $category_id = filter_var(
                $_POST["category_id"] ?? 0,
                FILTER_VALIDATE_INT
            );

            $category_id = $category_id === false ? 0 : $category_id;

            if ($category_id <= 0) {

                $message = "Invalid category.";
                $message_type = "error";

            } else {

                // GET CATEGORY NAME
                $category_query = $conn->prepare(
                    "SELECT name FROM categories WHERE id = ?"
                );

                if (!$category_query) {

                    $message = "Unable to verify category.";
                    $message_type = "error";

                } else {

                    $category_query->bind_param("i", $category_id);
                    $category_query->execute();

                    $category_data = $category_query
                        ->get_result()
                        ->fetch_assoc();

                    $category_query->close();

                    if (!$category_data) {

                        $message = "Category not found.";
                        $message_type = "error";

                    } else {

                        $category_name = $category_data["name"];

                        // CHECK WHETHER PRODUCTS USE THIS CATEGORY
                        $product_check = $conn->prepare(
                            "SELECT COUNT(*) AS total
                             FROM products
                             WHERE category = ?"
                        );

                        if (!$product_check) {

                            $message = "Unable to check products.";
                            $message_type = "error";

                        } else {

                            $product_check->bind_param(
                                "s",
                                $category_name
                            );

                            $product_check->execute();

                            $product_data = $product_check
                                ->get_result()
                                ->fetch_assoc();

                            $product_check->close();

                            if ((int)$product_data["total"] > 0) {

                                $message =
                                    "This category is being used by products. " .
                                    "Reassign those products before deleting it.";

                                $message_type = "error";

                            } else {

                                $delete = $conn->prepare(
                                    "DELETE FROM categories WHERE id = ?"
                                );

                                if (!$delete) {

                                    $message = "Unable to prepare category deletion.";
                                    $message_type = "error";

                                } else {

                                    $delete->bind_param("i", $category_id);

                                    if (
                                        $delete->execute() &&
                                        $delete->affected_rows > 0
                                    ) {
                                        $message = "Category deleted successfully.";
                                        $message_type = "success";
                                    } else {
                                        $message = "Unable to delete category.";
                                        $message_type = "error";
                                    }

                                    $delete->close();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// SEARCH CATEGORIES
$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT c.id, c.name, c.created_at,
                COUNT(p.id) AS total_products
         FROM categories c
         LEFT JOIN products p ON p.category = c.name
         WHERE c.name LIKE ?
         GROUP BY c.id, c.name, c.created_at
         ORDER BY c.id DESC"
    );

    if ($stmt) {
        $search_value = "%" . $search . "%";
        $stmt->bind_param("s", $search_value);
    }

} else {

    $stmt = $conn->prepare(
        "SELECT c.id, c.name, c.created_at,
                COUNT(p.id) AS total_products
         FROM categories c
         LEFT JOIN products p ON p.category = c.name
         GROUP BY c.id, c.name, c.created_at
         ORDER BY c.id DESC"
    );
}

// EXECUTE CATEGORY QUERY
$categories = false;
$total_categories = 0;

if ($stmt) {

    if ($stmt->execute()) {
        $categories = $stmt->get_result();
        $total_categories = $categories->num_rows;
    } else {
        $query_error = "Unable to load categories. Please try again.";
    }

} else {
    $query_error = "Unable to prepare category list. Please check the database.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Category Management - Needify Admin</title>

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
            color: var(--text);
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
            box-shadow: 0 3px 12px rgba(48, 39, 71, 0.05);
        }

        .card h2 {
            margin-top: 0;
            font-size: 19px;
            color: var(--text);
        }

        .form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        input {
            background: var(--input);
            color: var(--text);
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
            flex: 1;
            min-width: 180px;
        }

        input::placeholder {
            color: var(--muted);
        }

        input:focus {
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

        .delete-btn:hover {
            filter: brightness(0.9);
        }

        .cancel-btn {
            background: #82798F;
            color: white;
        }

        .cancel-btn:hover {
            background: #6D647A;
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
            min-width: 650px;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }

        th {
            color: var(--text);
            font-size: 13px;
            background: #F4EFFB;
        }

        body.dark-mode th {
            background: #30263F;
            color: #F8F2FF;
        }

        td {
            font-size: 14px;
            color: var(--text);
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

    <a href="admin-category.php" class="active">
        <i class="fas fa-list"></i> Categories
    </a>

    <a href="admin-products.php">
        <i class="fas fa-box"></i> Products
    </a>

    <a href="admin-logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>

</aside>

<main class="main">

    <div class="topbar">

        <h1>Category Management</h1>

        <div class="tools">

            <button class="tool-btn" onclick="toggleTheme()">
                <i class="fas fa-moon"></i> Theme
            </button>

            <button class="tool-btn" onclick="changeFont(1)">
                A+
            </button>

            <button class="tool-btn" onclick="changeFont(-1)">
                A-
            </button>

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

        <h2>Total Categories</h2>

        <div class="count">
            <?php echo $total_categories; ?>
        </div>

        <p class="muted">Categories currently available in Needify.</p>

    </div>

    <div class="card" id="categoryForm">

        <h2 id="formTitle">Add New Category</h2>

        <form method="POST" action="admin-category.php">

            <input type="hidden"
                   name="csrf_token"
                   value="<?php echo escapeOutput($_SESSION["csrf_token"]); ?>">

            <input type="hidden"
                   name="action"
                   id="formAction"
                   value="add">

            <input type="hidden"
                   name="category_id"
                   id="categoryId"
                   value="">

            <div class="form-row">

                <input type="text"
                       name="category_name"
                       id="categoryName"
                       maxlength="100"
                       placeholder="Enter category name"
                       required>

                <button type="submit"
                        class="primary-btn"
                        id="submitButton">
                    <i class="fas fa-plus"></i> Add Category
                </button>

                <button type="button"
                        class="cancel-btn"
                        id="cancelButton"
                        onclick="resetCategoryForm()"
                        style="display:none;">
                    Cancel
                </button>

            </div>

        </form>

    </div>

    <div class="card">

        <h2>Manage Categories</h2>

        <form method="GET"
              action="admin-category.php"
              class="form-row"
              style="margin-bottom:20px;">

            <input type="text"
                   name="search"
                   placeholder="Search category..."
                   value="<?php echo escapeOutput($search); ?>">

            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>

            <a href="admin-category.php"
               style="text-decoration:none;">

                <button type="button" class="cancel-btn">
                    Reset
                </button>

            </a>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Total Products</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($categories && $categories->num_rows > 0): ?>

                    <?php while ($category = $categories->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php echo (int)$category["id"]; ?>
                            </td>

                            <td>
                                <?php echo escapeOutput($category["name"]); ?>
                            </td>

                            <td>
                                <?php echo (int)$category["total_products"]; ?>
                            </td>

                            <td>
                                <?php echo escapeOutput($category["created_at"]); ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <button type="button"
                                            class="edit-btn"
                                            onclick='editCategory(
                                                <?php echo (int)$category["id"]; ?>,
                                                <?php echo json_encode(
                                                    $category["name"],
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_QUOT |
                                                    JSON_HEX_TAG |
                                                    JSON_HEX_AMP
                                                ); ?>
                                            )'>

                                        <i class="fas fa-edit"></i>

                                    </button>

                                    <form method="POST"
                                          action="admin-category.php"
                                          onsubmit="return confirm('Are you sure you want to delete this category?');">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?php echo escapeOutput($_SESSION["csrf_token"]); ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="delete">

                                        <input type="hidden"
                                               name="category_id"
                                               value="<?php echo (int)$category["id"]; ?>">

                                        <button type="submit"
                                                class="delete-btn">

                                            <i class="fas fa-trash"></i>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php elseif ($categories): ?>

                    <tr>
                        <td colspan="5" style="text-align:center;">
                            No categories found.
                        </td>
                    </tr>

                <?php else: ?>

                    <tr>
                        <td colspan="5" style="text-align:center;">
                            Categories could not be loaded.
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

    document.querySelectorAll(
        ".table-wrapper th, .table-wrapper td"
    ).forEach(function(element) {
        element.style.textAlign = "center";
    });
}

// EDIT CATEGORY
function editCategory(id, name) {

    document.getElementById("categoryId").value = id;
    document.getElementById("categoryName").value = name;

    document.getElementById("formAction").value = "update";

    document.getElementById("formTitle").innerText =
        "Update Category";

    document.getElementById("submitButton").innerHTML =
        '<i class="fas fa-save"></i> Update Category';

    document.getElementById("cancelButton").style.display =
        "inline-block";

    document.getElementById("categoryForm").scrollIntoView({
        behavior: "smooth"
    });
}

// RESET FORM
function resetCategoryForm() {

    document.getElementById("categoryId").value = "";
    document.getElementById("categoryName").value = "";

    document.getElementById("formAction").value = "add";

    document.getElementById("formTitle").innerText =
        "Add New Category";

    document.getElementById("submitButton").innerHTML =
        '<i class="fas fa-plus"></i> Add Category';

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
