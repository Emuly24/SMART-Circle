<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'About SMART Circle') ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="about-page">
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>

    <main class="container">
        <article class="card about-card">
            <header class="about-hero text-center">
                <div class="about-icon" aria-hidden="true">
                    <i class="fas fa-users"></i>
                </div>
                <h1>About Us</h1>
                <p class="about-tagline">Community &amp; Connection &ndash; Your Future Starts Here.</p>
            </header>

            <hr class="about-divider">

            <section aria-labelledby="story-heading">
                <h2 id="story-heading"><i class="fas fa-seedling" aria-hidden="true"></i> Our Story</h2>
                <p>SMART Circle was founded by Blessings Emulyn, a graduate of Metallurgy and Materials Engineering from the Malawi University of Science and Technology (MUST). He believes that learning is a shared journey &mdash; one that flourishes when students feel a sense of belonging, curiosity, and mutual respect.</p>
                <p>We are not a traditional tutoring service. We are a community of learners who come together to explore ideas, tackle difficult subjects, and celebrate small victories.</p>
                <p>If you are ready to grow, to ask questions without hesitation, and to support others on their path, you belong here. <strong>Welcome to the SMART Circle family.</strong></p>
            </section>

            <section class="about-grid" aria-label="Mission and vision">
                <article class="card glass">
                    <h3><i class="fas fa-bullseye" aria-hidden="true"></i> Our Mission</h3>
                    <p>To create a free, welcoming space where Form 3 and 4 students in Malawi can confidently tackle Mathematics, English, Physics, Chemistry, and Biology alongside peers who share their ambition.</p>
                </article>
                <article class="card glass">
                    <h3><i class="fas fa-eye" aria-hidden="true"></i> Our Vision</h3>
                    <p>To see a generation of young Malawians step into university halls and meaningful careers equipped not just with grades, but with the confidence to lead, question, and innovate.</p>
                </article>
            </section>

            <section aria-labelledby="approach-heading">
                <h2 id="approach-heading"><i class="fas fa-lightbulb" aria-hidden="true"></i> How We Work</h2>
                <ul class="about-list">
                    <li><strong>Small study circles:</strong> Groups of 5, so every voice is heard and every question is a learning opportunity.</li>
                    <li><strong>Real-world examples:</strong> We connect school topics to the world around us &ndash; from a chemistry reaction in the kitchen to a physics principle in a football match.</li>
                    <li><strong>No shortcuts:</strong> We believe in honest effort, punctual attendance, and mutual respect between mentors and learners.</li>
                    <li><strong>Your future, your pace:</strong> Whether you aim for university, a career, or personal growth, we meet you where you are and help you move forward.</li>
                </ul>
            </section>

            <aside class="about-commitment">
                <h3><i class="fas fa-handshake" aria-hidden="true"></i> Our Commitment</h3>
                <p>SMART Circle is a growing community of mentors, learners, and changemakers. We do not accept money, favours, or shortcuts. We accept punctuality, hard work, and an honest desire to improve. If you share these values, you will always find a place here.</p>
            </aside>

            <nav class="about-actions text-center" aria-label="Page navigation">
                <a href="index.php" class="btn-back">&larr; Back to Home</a>
                <?php if (!empty($isLoggedIn)): ?>
                    <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <?php else: ?>
                    <a href="signup.php" class="btn">Join SMART Circle</a>
                <?php endif; ?>
            </nav>
        </article>
    </main>

    <?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
    <?php include dirname(__DIR__, 2) . '/includes/toc_navigator.php'; ?>
</body>
</html>
