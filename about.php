<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>About SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="about-page">
    <?php include_once 'includes/header.php'; ?>

<div class="container">
    <div class="card" style="border-top: 5px solid var(--accent); padding: 2.5rem;">
        <!-- Hero Section -->
        <div class="text-center" style="margin-bottom: 2rem;">
            <div style="width: 120px; height: 120px; background: var(--accent); border-radius: 50%; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; font-size: 4rem; color: white;">
                <i class="fas fa-users"></i>
            </div>
            <h1 style="color: var(--accent); margin-bottom: 0.5rem;">About Us</h1>
            <p style="font-size: 1.2rem; color: var(--text-muted);">Community &amp; Connection – Your Future Starts Here.</p>
        </div>

        <hr style="border: 0; border-top: 2px solid var(--card-alt-bg); margin: 2rem 0;">

        <!-- Our Story -->
        <div style="margin-bottom: 2.5rem;">
            <h2><i class="fas fa-seedling"></i> Our Story</h2>
            <p>SMART Circle was founded by Blessings Emulyn, a graduate of Metallurgy and Materials Engineering from the Malawi University of Science and Technology (MUST). He believes that learning is a shared journey — one that flourishes when students feel a sense of belonging, curiosity, and mutual respect.</p>
            <p>We are not a traditional tutoring service. We are a community of learners who come together to explore ideas, tackle difficult subjects, and celebrate small victories.</p>
            <p>If you are ready to grow, to ask questions without hesitation, and to support others on their path, you belong here. <br><strong>Welcome to the SMART Circle family.</strong></p>
        </div>

        <!-- Mission & Vision -->
        <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; margin-bottom: 2.5rem;">
            <div class="card glass" style="background: var(--card-alt-bg);">
                <h3><i class="fas fa-bullseye"></i> Our Mission</h3>
                <p>To create a free, welcoming space where Form 3 and 4 students in Malawi can confidently tackle Mathematics, English, Physics, Chemistry, and Biology alongside peers who share their ambition.</p>
            </div>
            <div class="card glass" style="background: var(--card-alt-bg);">
                <h3><i class="fas fa-eye"></i> Our Vision</h3>
                <p>To see a generation of young Malawians step into university halls and meaningful careers equipped not just with grades, but with the confidence to lead, question, and innovate.</p>
            </div>
        </div>

        <!-- Our Approach -->
        <div style="margin-bottom: 2.5rem;">
            <h2><i class="fas fa-lightbulb"></i> How We Work</h2>
            <ul style="padding-left: 1.5rem; margin: 1rem 0;">
                <li><strong>Small study circles:</strong> Groups of 5, so every voice is heard and every question is a learning opportunity.</li>
                <li><strong>Real‑world examples:</strong> We connect school topics to the world around us – from a chemistry reaction in the kitchen to a physics principle in a football match.</li>
                <li><strong>No shortcuts:</strong> We believe in honest effort, punctual attendance, and mutual respect between mentors and learners.</li>
                <li><strong>Your future, your pace:</strong> Whether you aim for university, a career, or personal growth, we meet you where you are and help you move forward.</li>
            </ul>
        </div>

        <!-- Our Commitment -->
        <div style="background: var(--card-alt-bg); padding: 1.5rem; border-radius: 1rem; margin-bottom: 2rem;">
            <h3><i class="fas fa-handshake"></i> Our Commitment</h3>
            <p>SMART Circle is a growing community of mentors, learners, and changemakers. We do not accept money, favours, or shortcuts. We accept punctuality, hard work, and an honest desire to improve. If you share these values, you will always find a place here.</p>
        </div>

        <div class="text-center" style="margin-top: 1.5rem;">
            <a href="index.php" class="btn-back">← Back to Home</a>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="signup.php" class="btn" style="margin-left: 0.5rem;">Join SMART Circle</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn" style="margin-left: 0.5rem;">Go to Dashboard</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>