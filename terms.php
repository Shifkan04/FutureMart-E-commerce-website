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
    <title>Terms and Conditions - FutureMart</title>
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
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
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

        .terms-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .terms-card h2 {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .terms-card h3 {
            color: var(--text-light);
            font-size: 1.4rem;
            font-weight: 600;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .terms-card p {
            color: var(--text-muted);
            margin-bottom: 15px;
        }

        .terms-card ul, .terms-card ol {
            color: var(--text-muted);
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .terms-card li {
            margin-bottom: 10px;
        }

        .highlight-box {
            background: rgba(99, 102, 241, 0.2);
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .last-updated {
            background: rgba(236, 72, 153, 0.1);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
        }

        body.light-mode .hero h1 {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        body.light-mode .terms-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            color: #1e293b;
        }

        body.light-mode .terms-card h2 {
            color: #4f46e5;
        }

        body.light-mode .terms-card h3 {
            color: #1e293b;
        }

        body.light-mode .terms-card p,
        body.light-mode .terms-card ul,
        body.light-mode .terms-card ol,
        body.light-mode .terms-card li {
            color: #64748b;
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
            <h1>Terms and Conditions</h1>
            <p class="lead text-muted">Please read these terms carefully before using our services</p>
        </div>
    </div>

    <!-- Content Section -->
    <div class="content-section">
        <div class="container">
            <div class="last-updated">
                <i class="fas fa-calendar-alt text-primary"></i>
                <strong>Last Updated:</strong> November 25, 2025
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-file-contract"></i> 1. Agreement to Terms</h2>
                <p>By accessing and using FutureMart ("we," "us," or "our"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>
                
                <div class="highlight-box">
                    <strong>Important:</strong> These terms apply to all visitors, users, and others who access or use our service.
                </div>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-shopping-cart"></i> 2. Use of Our Service</h2>
                <h3>2.1 Eligibility</h3>
                <p>You must be at least 18 years old to use our service. By using FutureMart, you represent and warrant that you are of legal age to form a binding contract.</p>
                
                <h3>2.2 Account Registration</h3>
                <ul>
                    <li>You must provide accurate and complete information when creating an account</li>
                    <li>You are responsible for maintaining the confidentiality of your account credentials</li>
                    <li>You must notify us immediately of any unauthorized access to your account</li>
                    <li>You are responsible for all activities that occur under your account</li>
                </ul>

                <h3>2.3 Prohibited Activities</h3>
                <p>You agree not to:</p>
                <ol>
                    <li>Use the service for any illegal purpose or in violation of any local, state, national, or international law</li>
                    <li>Violate or infringe the rights of FutureMart or others</li>
                    <li>Transmit any harmful code, viruses, or malicious software</li>
                    <li>Attempt to gain unauthorized access to any portion of the service</li>
                    <li>Engage in any form of harassment, abuse, or harmful behavior</li>
                    <li>Use automated systems or software to extract data from the service</li>
                </ol>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-box"></i> 3. Products and Services</h2>
                <h3>3.1 Product Information</h3>
                <p>We strive to provide accurate product descriptions, pricing, and availability information. However, we do not warrant that product descriptions or other content is accurate, complete, reliable, current, or error-free.</p>

                <h3>3.2 Pricing</h3>
                <ul>
                    <li>All prices are subject to change without notice</li>
                    <li>We reserve the right to modify or discontinue products at any time</li>
                    <li>Prices include applicable taxes unless otherwise stated</li>
                    <li>We are not responsible for pricing errors on our website</li>
                </ul>

                <h3>3.3 Order Acceptance</h3>
                <p>We reserve the right to refuse or cancel any order for any reason, including but not limited to:</p>
                <ul>
                    <li>Product or service availability</li>
                    <li>Errors in product or pricing information</li>
                    <li>Suspected fraudulent activity</li>
                    <li>Orders that violate our terms and conditions</li>
                </ul>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-credit-card"></i> 4. Payment Terms</h2>
                <h3>4.1 Payment Methods</h3>
                <p>We accept various payment methods including credit cards, debit cards, and other payment services. You agree to provide current, complete, and accurate payment information.</p>

                <h3>4.2 Payment Processing</h3>
                <ul>
                    <li>Payment is due immediately upon placing an order</li>
                    <li>We use third-party payment processors to handle transactions securely</li>
                    <li>You authorize us to charge your selected payment method</li>
                    <li>All payments are subject to verification and authorization</li>
                </ul>

                <h3>4.3 Refunds and Returns</h3>
                <p>Our refund and return policy is outlined separately. Please refer to our Returns Policy for detailed information about returning products and receiving refunds.</p>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-truck"></i> 5. Shipping and Delivery</h2>
                <h3>5.1 Shipping Terms</h3>
                <ul>
                    <li>Shipping costs are calculated at checkout based on weight, destination, and shipping method</li>
                    <li>Delivery times are estimates and not guaranteed</li>
                    <li>We are not responsible for delays caused by shipping carriers or customs</li>
                    <li>Risk of loss passes to you upon delivery to the carrier</li>
                </ul>

                <h3>5.2 International Shipping</h3>
                <p>For international orders, you are responsible for all customs duties, taxes, and fees imposed by your country.</p>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-shield-alt"></i> 6. Intellectual Property</h2>
                <h3>6.1 Our Content</h3>
                <p>All content on FutureMart, including text, graphics, logos, images, and software, is the property of FutureMart or its content suppliers and is protected by intellectual property laws.</p>

                <h3>6.2 Limited License</h3>
                <p>We grant you a limited, non-exclusive, non-transferable license to access and use our service for personal, non-commercial purposes only.</p>

                <h3>6.3 Restrictions</h3>
                <p>You may not:</p>
                <ul>
                    <li>Reproduce, distribute, or modify any content without permission</li>
                    <li>Use our trademarks or service marks without authorization</li>
                    <li>Remove or alter any copyright notices</li>
                    <li>Create derivative works based on our content</li>
                </ul>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-user-shield"></i> 7. Privacy and Data Protection</h2>
                <p>Your privacy is important to us. Our Privacy Policy explains how we collect, use, and protect your personal information. By using our service, you consent to our collection and use of your data as described in our Privacy Policy.</p>
                
                <p><a href="privacy.php" class="text-primary"><i class="fas fa-external-link-alt"></i> View our Privacy Policy</a></p>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-exclamation-triangle"></i> 8. Disclaimers and Limitations of Liability</h2>
                <h3>8.1 Disclaimer of Warranties</h3>
                <p>OUR SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO:</p>
                <ul>
                    <li>Warranties of merchantability</li>
                    <li>Fitness for a particular purpose</li>
                    <li>Non-infringement</li>
                    <li>Accuracy, reliability, or completeness of content</li>
                </ul>

                <h3>8.2 Limitation of Liability</h3>
                <p>TO THE MAXIMUM EXTENT PERMITTED BY LAW, FUTUREMART SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, INCLUDING BUT NOT LIMITED TO:</p>
                <ul>
                    <li>Loss of profits or revenue</li>
                    <li>Loss of data or information</li>
                    <li>Business interruption</li>
                    <li>Personal injury or property damage</li>
                </ul>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-balance-scale"></i> 9. Indemnification</h2>
                <p>You agree to indemnify, defend, and hold harmless FutureMart and its officers, directors, employees, and agents from any claims, losses, damages, liabilities, and expenses (including legal fees) arising from:</p>
                <ul>
                    <li>Your use of our service</li>
                    <li>Your violation of these terms</li>
                    <li>Your violation of any rights of another party</li>
                    <li>Your conduct in connection with the service</li>
                </ul>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-gavel"></i> 10. Dispute Resolution</h2>
                <h3>10.1 Governing Law</h3>
                <p>These terms shall be governed by and construed in accordance with the laws of Sri Lanka, without regard to its conflict of law provisions.</p>

                <h3>10.2 Arbitration</h3>
                <p>Any disputes arising from these terms or your use of our service shall be resolved through binding arbitration, except where prohibited by law.</p>

                <h3>10.3 Class Action Waiver</h3>
                <p>You agree to resolve disputes with us on an individual basis and waive any right to participate in a class action lawsuit or class-wide arbitration.</p>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-edit"></i> 11. Changes to Terms</h2>
                <p>We reserve the right to modify these terms at any time. We will notify you of any changes by:</p>
                <ul>
                    <li>Posting the new terms on this page</li>
                    <li>Updating the "Last Updated" date</li>
                    <li>Sending you an email notification (for material changes)</li>
                </ul>
                <p>Your continued use of the service after changes become effective constitutes acceptance of the revised terms.</p>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-ban"></i> 12. Termination</h2>
                <h3>12.1 Termination by Us</h3>
                <p>We may terminate or suspend your account and access to our service immediately, without prior notice, for any reason, including:</p>
                <ul>
                    <li>Breach of these terms</li>
                    <li>Fraudulent or illegal activity</li>
                    <li>Request by law enforcement</li>
                    <li>Extended periods of inactivity</li>
                </ul>

                <h3>12.2 Termination by You</h3>
                <p>You may terminate your account at any time by contacting our customer service or using the account deletion feature in your account settings.</p>

                <h3>12.3 Effect of Termination</h3>
                <p>Upon termination, your right to use our service will immediately cease. All provisions of these terms that by their nature should survive termination shall survive.</p>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-info-circle"></i> 13. General Provisions</h2>
                <h3>13.1 Entire Agreement</h3>
                <p>These terms constitute the entire agreement between you and FutureMart regarding the use of our service.</p>

                <h3>13.2 Severability</h3>
                <p>If any provision of these terms is found to be unenforceable, the remaining provisions will remain in full force and effect.</p>

                <h3>13.3 Waiver</h3>
                <p>Our failure to enforce any right or provision of these terms will not be considered a waiver of those rights.</p>

                <h3>13.4 Assignment</h3>
                <p>You may not assign or transfer these terms without our prior written consent. We may assign our rights without restriction.</p>
            </div>

            <div class="terms-card">
                <h2><i class="fas fa-phone"></i> 14. Contact Information</h2>
                <p>If you have any questions about these Terms and Conditions, please contact us:</p>
                <ul>
                    <li><strong>Email:</strong> futuremart273@gmail.com</li>
                    <li><strong>Phone:</strong> +94 75 563 8086</li>
                    <li><strong>Address:</strong> 44/31/B 2nd Cross St., Thillayadi, Puttalam, Sri Lanka</li>
                    <li><strong>Business Hours:</strong> Monday-Friday, 9:00 AM - 6:00 PM EST</li>
                </ul>
            </div>

            <div class="text-center mt-5">
                <a href="contact.php" class="btn btn-primary">
                    <i class="fas fa-envelope me-2"></i>Contact Support
                </a>
                <a href="privacy.php" class="btn btn-outline-primary ms-3">
                    <i class="fas fa-shield-alt me-2"></i>Privacy Policy
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