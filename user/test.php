<?php
// submit_testimonial.php - Allow users to submit testimonials
require_once '../config.php';

$isLoggedIn = isLoggedIn();
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_testimonial'])) {
    $customerName = sanitizeInput($_POST['customer_name'] ?? '');
    $customerEmail = sanitizeInput($_POST['customer_email'] ?? '', 'email');
    $rating = (int)($_POST['rating'] ?? 5);
    $testimonialText = sanitizeInput($_POST['testimonial_text'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($customerName) || strlen($customerName) < 2) {
        $errors[] = "Name must be at least 2 characters";
    }
    
    if (!validateInput($customerEmail, 'email')) {
        $errors[] = "Invalid email address";
    }
    
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Rating must be between 1 and 5";
    }
    
    if (empty($testimonialText) || strlen($testimonialText) < 10) {
        $errors[] = "Testimonial must be at least 10 characters";
    }
    
    if (empty($errors)) {
        try {
            $userId = $isLoggedIn ? $_SESSION['user_id'] : null;
            
            // Insert testimonial (requires admin approval by default)
            $stmt = $pdo->prepare("
                INSERT INTO testimonials 
                (user_id, customer_name, customer_email, rating, testimonial_text, is_approved, is_featured) 
                VALUES (?, ?, ?, ?, ?, 0, 0)
            ");
            
            $stmt->execute([
                $userId,
                $customerName,
                $customerEmail,
                $rating,
                $testimonialText
            ]);
            
            $successMessage = "Thank you for your testimonial! It will be reviewed and published soon.";
            
        } catch (Exception $e) {
            $errorMessage = "Error submitting testimonial. Please try again.";
            error_log("Testimonial submission error: " . $e->getMessage());
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
</head>
<style>
    .rating-input {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
        gap: 5px;
    }
    
    .rating-input input[type="radio"] {
        display: none;
    }
    
    .rating-input label {
        cursor: pointer;
        font-size: 2rem;
        color: #d1d5db;
        transition: color 0.2s;
    }
    
    .rating-input label:hover,
    .rating-input label:hover ~ label,
    .rating-input input[type="radio"]:checked ~ label {
        color: #fbbf24;
    }
    
    .form-control {
        background: rgba(99, 102, 241, 0.1);
        color: var(--text-light);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        padding: 0.75rem 1rem;
    }
    
    .form-control:focus {
        background: rgba(99, 102, 241, 0.15);
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        color: var(--text-light);
    }
    
    .form-label {
        color: var(--text-light);
        font-weight: 500;
        margin-bottom: 0.5rem;
    }
    
    /* Light mode styles */
    body.light-mode .form-control {
        background: #ffffff;
        color: #1e293b;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    body.light-mode .form-control:focus {
        background: #ffffff;
        color: #1e293b;
    }
    
    body.light-mode .form-label {
        color: #1e293b;
    }
</style>
<body>
    

<!-- Add this form to your contact page or create a dedicated testimonials page -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%); border-radius: 20px; padding: 2rem; border: 1px solid rgba(255, 255, 255, 0.1);">
                <h3 class="mb-4"><i class="fas fa-star me-2"></i>Share Your Experience</h3>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="submit_testimonial" value="1">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Your Name *</label>
                            <input type="text" class="form-control" name="customer_name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="customer_email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Rating *</label>
                        <div class="rating-input">
                            <input type="radio" name="rating" value="5" id="star5" checked>
                            <label for="star5"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="4" id="star4">
                            <label for="star4"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="3" id="star3">
                            <label for="star3"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="2" id="star2">
                            <label for="star2"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="1" id="star1">
                            <label for="star1"><i class="fas fa-star"></i></label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Your Testimonial *</label>
                        <textarea class="form-control" name="testimonial_text" rows="5" required 
                                  placeholder="Share your experience with FutureMart..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Submit Testimonial
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>