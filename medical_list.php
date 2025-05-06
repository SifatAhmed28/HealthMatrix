<?php
// Database connection
$host = 'localhost';
$dbname = 'matrix';
$username = 'root'; // replace with your DB username
$password = ''; // replace with your DB password
$dsn = "mysql:host=$host;dbname=$dbname";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
    exit;
}

// Pagination setup
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 12;
$offset = ($currentPage - 1) * $itemsPerPage;

// Search functionality
$searchTerm = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'ASC';

// Query to get medicines data with pagination
$query = "SELECT * FROM Medicines WHERE name LIKE :search ORDER BY name $sortOrder LIMIT :offset, :limit";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->execute();
$medicines = $stmt->fetchAll();

// Query to count total medicines for pagination
$countQuery = "SELECT COUNT(*) FROM Medicines WHERE name LIKE :search";
$stmt = $pdo->prepare($countQuery);
$stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
$stmt->execute();
$totalMedicines = $stmt->fetchColumn();
$totalPages = ceil($totalMedicines / $itemsPerPage);

// Handle stock update
if (isset($_POST['updateStock'])) {
    $medicineId = $_POST['medicineId'];
    $stock = $_POST['stock'];

    // Update the stock in the database
    $updateQuery = "UPDATE Medicines SET stock = :stock WHERE medicine_id = :id";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindParam(':stock', $stock, PDO::PARAM_INT);
    $updateStmt->bindParam(':id', $medicineId, PDO::PARAM_INT);
    $updateStmt->execute();

    // Redirect to refresh the page
    header("Location: {$_SERVER['PHP_SELF']}?page=$currentPage");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Matrix - Medicine List</title>
    <style>
        /* Basic Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header Styles */
        .header-top {
            background-color: #f8f9fa;
            padding: 10px 0;
        }

        .header-top .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo img {
            height: 100px;
        }

        .header-contact span {
            font-size: 14px;
            color: #333;
        }

        .header-actions .btn-login,
        .header-actions .btn-signup {
            padding: 5px 15px;
            margin-left: 10px;
            text-decoration: none;
            color: #fff;
            background-color: #007bff;
            border-radius: 5px;
        }

        .header-actions .btn-signup {
            background-color: #28a745;
        }

        /* Navbar Styles */
        .navbar {
            background-color: #007bff;
            padding: 10px 0;
        }

        .nav-links {
            list-style: none;
            display: flex;
            justify-content: space-between;
        }

        .nav-links li a {
            color: #fff;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }

        .search-box {
            display: flex;
            align-items: center;
        }

        .search-box input {
            padding: 10px;
            border: 3px;
            border-radius: 5px 0 0 5px;
            width: 95%;
        }

        .search-box button {
            padding: 10px 15px;
            border: none;
            background-color: #28a745;
            color: #fff;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
        }

        .search-box button i {
            font-size: 16px;
        }

        /* Main Content Styles */
        main {
            padding: 20px 0;
        }

        main h1 {
            margin-bottom: 20px;
        }

        main p {
            margin-bottom: 20px;
        }

        /* Footer Styles */
        footer {
            background-color: #333;
            color: #fff;
            padding: 20px 0;
        }

        .footer-top .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-columns {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .footer-section {
            flex: 1;
        }

        .footer-section h3 {
            margin-bottom: 15px;
        }

        .footer-section p,
        .footer-section ul {
            font-size: 14px;
            line-height: 1.6;
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li a {
            color: #fff;
            text-decoration: none;
        }

        .footer-section ul li a:hover {
            text-decoration: underline;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 10px;
            font-size: 14px;
            background-color: #222;
            padding: 10px 0;
        }

        /* Sticky Header */
        .sticky {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        /* Medicine List Styles */
        .controls {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        #searchInput {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            flex: 1;
        }

        #sortSelect {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .medicine-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .medicine-card {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            background-color: #f9f9f9;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease, box-shadow 0.3s ease;
        }

        .medicine-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .medicine-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .medicine-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            background-color: #ddd;
        }

        .medicine-card h3 {
            margin: 10px 0;
            font-size: 18px;
        }

        .medicine-card p {
            font-size: 14px;
            color: #555;
        }

        .medicine-card button {
            margin-top: 10px;
            padding: 8px 16px;
            border: none;
            background-color: #007bff;
            color: #fff;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .medicine-card button:hover {
            background-color: #0056b3;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination button {
            padding: 10px 20px;
            margin: 0 5px;
            border: none;
            background-color: #007bff;
            color: #fff;
            border-radius: 5px;
            cursor: pointer;
        }

        .pagination button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        #pageInfo {
            margin: 0 10px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .footer-columns {
                flex-direction: column;
                gap: 30px;
            }

            .search-box input {
                width: 200px;
            }
        }
    </style>
    <!-- Font Awesome for search icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <header>
    <div class="header-top">
            <div class="container">
                <div class="logo">
                    <a href="/"><img src="logo.png" alt="Logo"></a>
                </div>
                <div class="header-contact">
                    <span>Call Us: +880 1234 567890</span>
                </div>
                <div class="header-actions">
                    <a href="/login" class="btn-login">Login</a>
                    <a href="/signup" class="btn-signup">Sign Up</a>
                </div>
            </div>
        </div>
        <nav class="navbar">
            <div class="container">
                <ul class="nav-links">
                    <li><a href="/">Home</a></li>
                    <li><a href="/doctors">Doctors</a></li>
                    <li><a href="/hospitals">Hospitals</a></li>
                    <li><a href="/services">Services</a></li>
                    <li><a href="/blog">Blog</a></li>
                    <li><a href="/contact">Contact</a></li>
                </ul>
                <div class="search-box">
                    <input type="text" placeholder="Search...">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container">
            <h1>Medicine List</h1>
            

            <div class="medicine-list">
                <?php foreach ($medicines as $medicine): ?>
                    <div class="medicine-card visible">
                        <img src="<?= 'Medical_list.png' ?>" alt="<?= htmlspecialchars($medicine['name']) ?>">
                        <h3><?= htmlspecialchars($medicine['name']) ?></h3>
                        <p>Manufacturer: <?= htmlspecialchars($medicine['manufacturer']) ?></p>
                        <p>Price: $<?= number_format($medicine['price'], 2) ?></p>
                        <p>Stock: <?= htmlspecialchars($medicine['stock']) ?></p>

                        <!-- Update Stock Form -->
                        <form method="POST">
                            <input type="hidden" name="medicineId" value="<?= $medicine['medicine_id'] ?>">
                            <input type="number" name="stock" value="<?= $medicine['stock'] ?>" min="0" required>
                            <button type="submit" name="updateStock">Update Stock</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <!-- <div class="pagination">
                <a href="?page=<?= max(1, $currentPage - 1) ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&sort=<?= $_GET['sort'] ?>">Previous</a>
                <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
                <a href="?page=<?= min($totalPages, $currentPage + 1) ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&sort=<?= $_GET['sort'] ?>">Next</a>
            </div> -->
        </div>
    </main>

    <footer>
    <footer>
        <div class="footer-top">
            <div class="container">
                <div class="footer-columns">
                    <div class="footer-section">
                        <h3>About Us</h3>
                        <p>Health Matrix is the number 1 healthcare service provider in Bangladesh, based on one million downloads and ratings on the Play Store.</p>
                    </div>
                    <div class="footer-section">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="/">Home</a></li>
                            <li><a href="/health-plans">Health Plans</a></li>
                            <li><a href="/about-us">About us</a></li>
                            <li><a href="/terms-of-services">Terms of Services</a></li>
                            <li><a href="/privacy-policy">Privacy Policy</a></li>
                            <li><a href="/home-diagnostics">Home Diagnostics</a></li>
                            <li><a href="/for-doctors">For Doctors</a></li>
                            <li><a href="/contact-us">Contact us</a></li>
                        </ul>
                    </div>
                    <div class="footer-section">
                        <h3>Contact Us</h3>
                        <p>📞 09677865599</p>
                        <p>📧 support@healthmatrix.com.bd</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>Copyright © 2025 Health Matrix. All rights reserved.</p>
            </div>
        </div>
    </footer>
    </footer>

</body>
</html>
