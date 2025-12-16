document.addEventListener('DOMContentLoaded', function() {
    initRatingInput();
    loadMyTestimonials();
    
    document.getElementById('testimonialForm').addEventListener('submit', submitTestimonial);
});

function initAnimations() {
    // testimonial-header 
    gsap.from('.testimonial-header', {
        duration: 0.8,
        y: -50,
        opacity: 0,
        ease: 'power3.out'
    });
}

function initRatingInput() {
    document.querySelectorAll('.star-label').forEach(label => {
        label.addEventListener('click', function() {
            const input = document.getElementById(this.getAttribute('for'));
            input.checked = true;
            
            gsap.from(this, {
                duration: 0.3,
                scale: 1.5,
                ease: 'back.out(2)'
            });
        });
    });
}

async function submitTestimonial(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    formData.append('submit_testimonial', '1');
    
    const messageDiv = document.getElementById('testimonialMessage');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    
    try {
        const response = await fetch('testimonials.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        
        messageDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Thank you! Your testimonial will be reviewed and published soon.</div>';
        
        form.reset();
        document.getElementById('star5').checked = true;
        
        setTimeout(() => {
            loadMyTestimonials();
        }, 1000);
        
    } catch (error) {
        console.error('Error:', error);
        messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error submitting testimonial. Please try again.</div>';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Testimonial';
    }
}

async function loadMyTestimonials() {
    // This would load user's testimonials from database
    const container = document.getElementById('myTestimonialsList');
    
    // For now, show a placeholder
    container.innerHTML = `
        <div class="text-center text-muted py-4">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <p>You haven't submitted any testimonials yet.</p>
        </div>
    `;
}