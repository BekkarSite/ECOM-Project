<!-- public/index.php -->
<?php 
session_start();
include('../includes/header.php'); ?>

<main>
    <h1>Welcome to Our eCommerce Site</h1>
    <section id="featured-products">
        <h2>Featured Products</h2>
        <!-- Display featured products dynamically from the database -->
        <?php
        $sql = "SELECT * FROM products LIMIT 4";
        $result = $conn->query($sql);
        while($row = $result->fetch_assoc()) {
            echo "<div class='product'>
                    <img src='/assets/images/" . $row['image'] . "' alt='" . $row['name'] . "'>
                    <h3>" . $row['name'] . "</h3>
                    <p>" . $row['price'] . " USD</p>
                    <a href='/product/" . $row['id'] . "'>View Details</a>
                  </div>";
        }
        ?>
    </section>
</main>

<?php include('../includes/footer.php'); ?>
