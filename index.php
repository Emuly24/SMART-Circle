<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>SMART Tutor – Empowering Malawi's Youth</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="home-page">
    <?php include_once 'includes/progress_tracker.php'; ?>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-graduation-cap"></i> SMART Tutor</h1>
        <nav>
            <a href="index.php">Home</a>
            <a href="login.php">Login</a>
            <a href="signup.php">Sign Up</a>
            <a href="about.php">About</a>
        </nav>
    </div>

    <div class="hero-section">
        <h2>Empowering Malawi’s Secondary Students</h2>
        <p>SMART Tutor is a free, discipline‑based tutoring platform designed to help hardworking students master challenging subjects through small study groups, practical examples, and real‑world applications.</p>
        <p><strong>Our promise:</strong> No money, no favours – only punctuality, hard work, and respect.</p>
        <a href="signup.php" class="btn">Get Started</a>
    </div>

    <?php include_once 'includes/vision_mission.php'; ?>

    <div class="footer">SMART Tutor – Discipline & Integrity</div>
</div>
</body>
</html>
