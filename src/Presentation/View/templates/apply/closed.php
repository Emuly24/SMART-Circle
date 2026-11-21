<?php /** @var \SmartCircle\Application\Admissions\SeatChart|null $chart */ ?>
<section class="section">
    <div class="container narrow-wide">
        <h1>Applications are closed</h1>
        <p class="lead">SMART Circle is not taking applications right now. Please check again soon, or ask your school.</p>
        <?= $view->partial('seat_chart', ['chart' => $chart]) ?>
        <p><a class="btn btn--ghost" href="/apply/status">Check an application I already made</a></p>
    </div>
</section>
