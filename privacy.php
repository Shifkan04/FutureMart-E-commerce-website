<?php
// Start session and include config
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$userData = null;

if ($isLoggedIn) {
    // Fetch user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
}

// Get cart count for logged-in user
$cartCount = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #ec4899;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-bg);
            color: var(--text-light);
            line-height: 1.8;
        }

        .navbar {
            background: #0f172a;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-link {
            color: var(--text-light) !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .cart-icon {
            position: relative;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--secondary-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .hero {
            padding: 150px 0 80px;
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%);
            position: relative;
            overflow: hidden;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .content-section {
            padding: 60px 0;
        }

        .privacy-card {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .privacy-card h2 {
            color: var(--secondary-color);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .privacy-card h3 {
            color: var(--text-light);
            font-size: 1.4rem;
            font-weight: 600;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .privacy-card p {
            color: var(--text-muted);
            margin-bottom: 15px;
        }

        .privacy-card ul, .privacy-card ol {
            color: var(--text-muted);
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .privacy-card li {
            margin-bottom: 10px;
        }

        .highlight-box {
            background: rgba(236, 72, 153, 0.2);
            border-left: 4px solid var(--secondary-color);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .last-updated {
            background: rgba(99, 102, 241, 0.1);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .data-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }

        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .data-table th {
            background: rgba(236, 72, 153, 0.2);
            color: var(--text-light);
            font-weight: 600;
        }

        .btn-primary {
            background: var(--gradient-1);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .footer {
            background: #0f172a;
            padding: 3rem 0 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 60px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        /* Light Mode Styles */
        body.light-mode {
            background: #f8fafc;
            color: #1e293b;
        }

        body.light-mode .navbar {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .nav-link {
            color: #1e293b !important;
        }

        body.light-mode .hero {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.05) 0%, rgba(99, 102, 241, 0.05) 100%);
        }

        body.light-mode .hero h1 {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        body.light-mode .privacy-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            color: #1e293b;
        }

        body.light-mode .privacy-card h2 {
            color: #ec4899;
        }

        body.light-mode .privacy-card h3 {
            color: #1e293b;
        }

        body.light-mode .privacy-card p,
        body.light-mode .privacy-card ul,
        body.light-mode .privacy-card ol,
        body.light-mode .privacy-card li {
            color: #64748b;
        }

        body.light-mode .data-table th, 
        body.light-mode .data-table td {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .data-table th {
            background: rgba(236, 72, 153, 0.1);
            color: #1e293b;
        }

        body.light-mode .footer {
            background: #f1f5f9;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body<?php echo ($isLoggedIn && $userData && $userData['theme_preference'] === 'light') ? ' class="light-mode"' : ''; ?>>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-rocket me-2"></i>FutureMart
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?php if ($isLoggedIn): ?>
                        <a href="#" class="nav-link cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-badge"><?php echo $cartCount; ?></span>
                        </a>
                        <div class="dropdown">
                            <div class="user-profile" data-bs-toggle="dropdown">
                                <span><?= htmlspecialchars($userData['first_name']); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-user me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero">
        <div class="container text-center">
            <h1>Privacy Policy</h1>
            <p class="lead text-muted">Your privacy matters to us. Learn how we protect your data.</p>
        </div>
    </div>

    <!-- Content Section -->
    <div class="content-section">
        <div class="container">
            <div class="last-updated">
                <i class="fas fa-calendar-alt text-primary"></i>
                <strong>Last Updated:</strong> November 25, 2025
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-shield-alt"></i> 1. Introduction</h2>
                <p>Welcome to FutureMart's Privacy Policy. We are committed to protecting your personal information and your right to privacy. This policy describes how we collect, use, store, and share your information when you use our services.</p>
                
                <div class="highlight-box">
                    <strong>Your Trust Matters:</strong> We understand the importance of your personal data and are dedicated to maintaining its confidentiality and security.
                </div>

                <p>By using FutureMart, you agree to the collection and use of information in accordance with this policy. If you do not agree with our policies and practices, please do not use our services.</p>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-database"></i> 2. Information We Collect</h2>
                <h3>2.1 Personal Information</h3>
                <p>We collect information that you provide directly to us, including:</p>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data Type</th>
                            <th>Examples</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Account Information</td>
                            <td>Name, email, password</td>
                            <td>Account creation and management</td>
                        </tr>
                        <tr>
                            <td>Contact Details</td>
                            <td>Phone number, address</td>
                            <td>Order delivery and communication</td>
                        </tr>
                        <tr>
                            <td>Payment Information</td>
                            <td>Credit card, billing address</td>
                            <td>Processing transactions</td>
                        </tr>
                        <tr>
                            <td>Profile Information</td>
                            <td>Preferences, purchase history</td>
                            <td>Personalized experience</td>
                        </tr>
                    </tbody>
                </table>

                <h3>2.2 Automatically Collected Information</h3>
                <ul>
                    <li><strong>Device Information:</strong> IP address, browser type, device type, operating system</li>
                    <li><strong>Usage Data:</strong> Pages visited, time spent, clicks, search queries</li>
                    <li><strong>Location Data:</strong> Approximate location based on IP address</li>
                    <li><strong>Cookies and Tracking:</strong> Session cookies, preference cookies, analytics cookies</li>
                </ul>

                <h3>2.3 Information from Third Parties</h3>
                <p>We may receive information about you from:</p>
                <ul>
                    <li>Payment processors for transaction verification</li>
                    <li>Social media platforms if you connect your account</li>
                    <li>Marketing partners and analytics providers</li>
                    <li>Public databases and demographic data providers</li>
                </ul>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-cogs"></i> 3. How We Use Your Information</h2>
                <p>We use the information we collect for the following purposes:</p>

                <h3>3.1 Service Provision</h3>
                <ul>
                    <li>Process and fulfill your orders</li>
                    <li>Manage your account and provide customer support</li>
                    <li>Send order confirmations and shipping updates</li>
                    <li>Process payments and prevent fraud</li>
                </ul>

                <h3>3.2 Communication</h3>
                <ul>
                    <li>Respond to your inquiries and requests</li>
                    <li>Send administrative information and service updates</li>
                    <li>Provide marketing communications (with your consent)</li>
                    <li>Send personalized product recommendations</li>
                </ul>

                <h3>3.3 Improvement and Analytics</h3>
                <ul>
                    <li>Analyze usage patterns and trends</li>
                    <li>Improve our website, products, and services</li>
                    <li>Develop new features and functionality</li>
                    <li>Conduct market research and surveys</li>
                </ul>

                <h3>3.4 Legal and Security</h3>
                <ul>
                    <li>Comply with legal obligations and regulations</li>
                    <li>Detect and prevent fraud, security threats, and illegal activities</li>
                    <li>Enforce our terms and conditions</li>
                    <li>Protect our rights, privacy, safety, and property</li>
                </ul>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-share-alt"></i> 4. Information Sharing and Disclosure</h2>
                <p>We may share your information in the following circumstances:</p>

                <h3>4.1 Service Providers</h3>
                <p>We share information with third-party service providers who perform services on our behalf, including:</p>
                <ul>
                    <li>Payment processors and financial institutions</li>
                    <li>Shipping and logistics companies</li>
                    <li>Cloud hosting and data storage providers</li>
                    <li>Email and communication service providers</li>
                    <li>Marketing and analytics platforms</li>
                    <li>Customer service and support tools</li>
                </ul>

                <h3>4.2 Business Transfers</h3>
                <p>In the event of a merger, acquisition, reorganization, or sale of assets, your information may be transferred as part of that transaction.</p>

                <h3>4.3 Legal Requirements</h3>
                <p>We may disclose your information when required by law or in response to:</p>
                <ul>
                    <li>Legal processes (subpoenas, court orders)</li>
                    <li>Government or regulatory requests</li>
                    <li>Protection of our rights and legal claims</li>
                    <li>Emergency situations involving safety threats</li>
                </ul>

                <h3>4.4 With Your Consent</h3>
                <p>We may share your information for other purposes with your explicit consent or at your direction.</p>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-cookie-bite"></i> 5. Cookies and Tracking Technologies</h2>
                <h3>5.1 What Are Cookies?</h3>
                <p>Cookies are small data files stored on your device that help us provide and improve our services.</p>

                <h3>5.2 Types of Cookies We Use</h3>
                <ul>
                    <li><strong>Essential Cookies:</strong> Required for basic website functionality</li>
                    <li><strong>Performance Cookies:</strong> Help us understand how visitors use our site</li>
                    <li><strong>Functional Cookies:</strong> Remember your preferences and settings</li>
                    <li><strong>Marketing Cookies:</strong> Deliver relevant advertisements to you</li>
                </ul>

                <h3>5.3 Managing Cookies</h3>
                <p>You can control cookies through your browser settings. However, disabling cookies may affect your ability to use certain features of our website.</p>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-lock"></i> 6. Data Security</h2>
                <h3>6.1 Security Measures</h3>
                <p>We implement appropriate technical and organizational measures to protect your personal information, including:</p>
                <ul>
                    <li>Encryption of data in transit and at rest</li>
                    <li>Secure socket layer (SSL) technology for transactions</li>
                    <li>Regular security audits and vulnerability assessments</li>
                    <li>Access controls and authentication procedures</li>
                    <li>Employee training on data protection</li>
                    <li>Incident response and breach notification procedures</li>
                </ul>

                <h3>6.2 Data Retention</h3>
                <p>We retain your personal information for as long as necessary to fulfill the purposes outlined in this policy, unless a longer retention period is required by law.</p>

                <h3>6.3 Your Responsibility</h3>
                <p>You are responsible for maintaining the confidentiality of your account credentials and for all activities under your account.</p>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-user-check"></i> 7. Your Privacy Rights</h2>
                <p>Depending on your location, you may have the following rights regarding your personal information:</p>

                <h3>7.1 Access and Portability</h3>
                <ul>
                    <li>Request access to your personal information</li>
                    <li>Receive a copy of your data in a portable format</li>
                </ul>

                <h3>7.2 Correction and Update</h3>
                <ul>
                    <li>Correct inaccurate or incomplete information</li>
                    <li>Update your account information and preferences</li>
                </ul>

                <h3>7.3 Deletion</h3>
                <ul>
                    <li>Request deletion of your personal information</li>
                    <li>Close your account (subject to certain legal obligations)</li>
                </ul>

                <h3>7.4 Opt-Out</h3>
                <ul>
                    <li>Unsubscribe from marketing communications</li>
                    <li>Opt-out of certain data collection and processing</li>
                    <li>Disable cookies through browser settings</li>
                </ul>

                <h3>7.5 How to Exercise Your Rights</h3>
                <p>To exercise any of these rights, please contact us at futuremart273@gmail.com or through your account settings.</p>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-child"></i> 8. Children's Privacy</h2>
                <p>Our services are not directed to individuals under the age of 18. We do not knowingly collect personal information from children. If you are a parent or guardian and believe your child has provided us with personal information, please contact us immediately.</p>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-globe"></i> 9. International Data Transfers</h2>
                <p>Your information may be transferred to and processed in countries other than your country of residence. These countries may have data protection laws that differ from those of your country.</p>
                
                <p>When we transfer your information internationally, we ensure appropriate safeguards are in place, including:</p>
                <ul>
                    <li>Standard contractual clauses approved by relevant authorities</li>
                    <li>Data protection agreements with third parties</li>
                    <li>Compliance with applicable data protection frameworks</li>
                </ul>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-link"></i> 10. Third-Party Links and Services</h2>
                <p>Our website may contain links to third-party websites and services. We are not responsible for the privacy practices of these external sites. We encourage you to review their privacy policies before providing any personal information.</p>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-bell"></i> 11. Changes to This Privacy Policy</h2>
                <p>We may update this Privacy Policy from time to time to reflect changes in our practices or for legal, regulatory, or operational reasons.</p>
                
                <p>We will notify you of any material changes by:</p>
                <ul>
                    <li>Posting the updated policy on this page</li>
                    <li>Updating the "Last Updated" date</li>
                    <li>Sending an email notification to registered users</li>
                    <li>Displaying a prominent notice on our website</li>
                </ul>

                <p>Your continued use of our services after changes become effective constitutes acceptance of the revised policy.</p>
            </div>

            <div class="privacy-card">
                <h2><i class="fas fa-phone-alt"></i> 12. Contact Us</h2>
                <p>If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us:</p>
                
                <div class="highlight-box">
                    <h4>Data Protection Officer</h4>
                    <ul class="mb-0">
                        <li><strong>Email:</strong> futuremart273@gmail.com</li>
                        <li><strong>Phone:</strong> +94 75 563 8086</li>
                        <li><strong>Address:</strong> 44/31/B 2nd Cross St., Thillayadi, Puttalam, Sri Lanka</li>
                        <li><strong>Response Time:</strong> We aim to respond within 30 days</li>
                    </ul>
                </div>
            </div>

            <div class="text-center mt-5">
                <a href="contact.php" class="btn btn-primary">
                    <i class="fas fa-envelope me-2"></i>Contact Us
                </a>
                <a href="terms.php" class="btn btn-outline-primary ms-3">
                    <i class="fas fa-file-contract me-2"></i>Terms & Conditions
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="navbar-brand mb-3">
                        <i class="fas fa-rocket me-2"></i>FutureMart
                    </h5>
                    <p class="text-light">Your trusted partner for cutting-edge products and exceptional shopping experiences.</p>
                </div>
                <div class="col-md-4">
                    <h6>Legal</h6>
                    <ul class="footer-links">
                        <li><a href="terms.php">Terms & Conditions</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="#">Return Policy</a></li>
                        <li><a href="#">Shipping Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6>Contact</h6>
                    <ul class="footer-links">
                        <li><a href="contact.php">Contact Us</a></li>
                        <li><a href="#">Support Center</a></li>
                        <li><a href="about.php">About Us</a></li>
                    </ul>
                </div>
            </div>
            <hr style="border-color: rgba(255, 255, 255, 0.1);">
            <div class="text-center">
                <p class="text-light mb-0">&copy; 2025 FutureMart. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>