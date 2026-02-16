<?php
//if (session_status() === PHP_SESSION_NONE) session_start();
include 'auth-check.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<style>
    .quill-editor {
      height: 150px !important;
      margin-bottom: 1rem !important;
    }

</style>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="products.php">Admin Panel</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link" href="products.php">Products</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="orders.php">Orders</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="coupons.php">Coupons</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="categories.php">Categories</a>
                </li>

            </ul>

            <span class="navbar-text text-white me-3">
                <?php echo htmlspecialchars($_SESSION['admin_email']); ?>
            </span>

            <a href="logout.php" class="btn btn-sm btn-outline-light">
                Logout
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4">
