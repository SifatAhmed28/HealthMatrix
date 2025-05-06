<?php
// Database connection
$host = 'localhost'; // Replace with your database host
$dbname = 'matrix'; // Database name
$username = 'root'; // Your database username
$password = ''; // Your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Fetch diseases from the database
$query = "SELECT * FROM Diseases";
$stmt = $pdo->prepare($query);
$stmt->execute();
$diseases = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Matrix - Diseases</title>
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

        /* Diseases Grid Layout */
        .diseases-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        /* Disease Card Styles */
        .disease-card {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: box-shadow 0.3s ease;
            cursor: pointer;
        }

        .disease-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .disease-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .disease-card .card-content {
            padding: 15px;
        }

        .disease-card h3 {
            margin: 0 0 10px;
            font-size: 1.2em;
        }

        .disease-card p {
            margin: 0;
            font-size: 0.9em;
            color: #555;
        }

        /* Additional Details Styles */
        .disease-details {
            display: none;
            padding: 15px;
            background-color: #f9f9f9;
            border-top: 1px solid #ddd;
        }

        .disease-details p {
            margin: 5px 0;
            font-size: 0.9em;
            color: #333;
        }

        /* Footer Styles */
        footer {
            background-color: #333;
            color: #fff;
            padding: 20px 0;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .footer-columns {
                flex-direction: column;
                gap: 30px;
            }

            .search-box input {
                width: 200px;
            }

            .diseases-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            <h1>Diseases</h1>
            <div id="diseases-container" class="diseases-grid">
                <?php foreach ($diseases as $disease): ?>
                    <div class="disease-card">
                    <img src="<?php echo htmlspecialchars($disease['name']); ?>.jpg" alt="<?php echo htmlspecialchars($disease['name']); ?>">

                        <div class="card-content">
                            <h3><?php echo htmlspecialchars($disease['name']); ?></h3>
                            <p><?php echo htmlspecialchars($disease['description']); ?></p>
                        </div>
                        <div class="disease-details">
                            <p><strong>Symptoms:</strong> <?php echo htmlspecialchars($disease['symptoms']); ?></p>
                            <p><strong>Causes:</strong> <?php echo htmlspecialchars($disease['causes']); ?></p>
                            <p><strong>Prevention:</strong> <?php echo htmlspecialchars($disease['prevention']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

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

    <script>
        // Script to toggle disease details
        document.querySelectorAll('.disease-card').forEach(card => {
            card.addEventListener('click', () => {
                const details = card.querySelector('.disease-details');
                details.style.display = details.style.display === 'block' ? 'none' : 'block';
            });
        });
    </script>
</body>
</html>
