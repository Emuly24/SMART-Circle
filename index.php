<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>SMART Tutor – Empowering Malawi's Youth</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>

    <!-- Hero Section -->
    <div class="hero-section">
        <h1>Empowering Malawi’s Secondary Students</h1>
        <p>SMART Tutor is a free, discipline‑based tutoring platform designed to help hardworking students master challenging subjects through small study groups, practical examples, and real‑world applications.</p>
        <p class="promise"><strong>Our promise:</strong> No money, no favours – only punctuality, hard work, and respect.</p>
        <div class="hero-buttons">
            <button id="checkEligibilityBtn" class="btn-hero">Get Started</button>
        </div>
    </div>

    <?php include_once 'includes/vision_mission.php'; ?>

    <!-- Testimonials Section -->
    <div class="testimonials-section">
        <h2><i class="fas fa-star"></i> What Our Students Say</h2>
        <div id="testimonialContainer" class="testimonial-slide">
            <div class="testimonial-card-placeholder">Loading...</div>
        </div>
    </div>

    <!-- Join Section (CTA) -->
    <div class="join-us">
        <h2>Join the SMART Tutor Community</h2>
        <p>Are you a secondary school student looking for support? SMART Tutor welcomes you. Together, we can create your brighter future.</p>
        <button id="joinNowBtn" class="btn-hero">Get Started</button>
    </div>

    <div class="footer">
        <a href="login.php">Login</a> | <a href="signup.php">Sign Up</a>
    </div>
</div>

<!-- Eligibility Modal -->
<div id="eligibilityModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><i class="fas fa-clipboard-list"></i> Am I Eligible?</h2>
        <p>To join SMART Tutor, you must meet the following criteria:</p>
        <ul class="eligibility-list">
            <li><i class="fas fa-check-circle"></i> Be in <strong>Form 3 or Form 4</strong> (secondary school)</li>
            <li><i class="fas fa-check-circle"></i> Live within <strong>Sharpevalley area</strong> or be willing to commute to the designated tutoring place</li>
            <li><i class="fas fa-check-circle"></i> Be <strong>hardworking, disciplined, and respectful</strong></li>
            <li><i class="fas fa-check-circle"></i> Have a genuine desire to improve your grades</li>
            <li><i class="fas fa-check-circle"></i> Commit to punctuality and active participation</li>
        </ul>
        <p>If you meet all the above, we welcome you! Click below to create your account.</p>
        <div class="modal-buttons">
            <a href="signup.php" class="btn">Yes, I'm Eligible – Sign Up</a>
            <button id="closeModalBtn" class="btn-secondary">Not Now</button>
        </div>
    </div>
</div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
<script>
    // Testimonials fetch and rotate (same as before)
    let testimonials = [];
    let currentIndex = 0;
    let interval;
    function fetchTestimonials() {
        fetch('get_testimonials.php')
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    document.getElementById('testimonialContainer').innerHTML = '<div class="testimonial-card"><p>No testimonials yet. Be the first to share your experience!</p></div>';
                    return;
                }
                testimonials = data;
                showTestimonial(0);
                startRotation();
            });
    }
    function showTestimonial(index) {
        const t = testimonials[index];
        const html = `<div class="testimonial-card"><div class="testimonial-rating">${'⭐'.repeat(t.rating)}</div><p class="testimonial-text">"${escapeHtml(t.testimonial)}"</p><p class="testimonial-author">– ${escapeHtml(t.fullname)}, ${escapeHtml(t.class_level)}</p></div>`;
        const container = document.getElementById('testimonialContainer');
        container.style.opacity = '0';
        setTimeout(() => {
            container.innerHTML = html;
            container.style.opacity = '1';
        }, 300);
    }
    function startRotation() {
        if (interval) clearInterval(interval);
        interval = setInterval(() => {
            currentIndex = (currentIndex + 1) % testimonials.length;
            showTestimonial(currentIndex);
        }, 8000);
    }
    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    const modal = document.getElementById('eligibilityModal');
    const checkBtn = document.getElementById('checkEligibilityBtn');
    const joinBtn = document.getElementById('joinNowBtn');
    const closeSpan = document.querySelector('#eligibilityModal .close');
    const closeBtn = document.getElementById('closeModalBtn');
    function openModal() { modal.style.display = 'flex'; }
    checkBtn.addEventListener('click', openModal);
    joinBtn.addEventListener('click', openModal);
    closeSpan.addEventListener('click', () => modal.style.display = 'none');
    closeBtn.addEventListener('click', () => modal.style.display = 'none');
    window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
    fetchTestimonials();
</script>
</body>
</html>