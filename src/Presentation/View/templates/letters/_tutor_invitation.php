<?php
/**
 * Inviting a volunteer tutor to the SMART Circle Team: why, what they would do, what they gain.
 * @var string $subjects
 * @var array<string, mixed> $data
 */
$in = $subjects !== '' ? $subjects : 'the subjects you know best';
?>
<p>I would like to invite you to join the <strong>SMART Circle Team</strong> as a volunteer tutor in <strong><?= e($in) ?></strong>.
    SMART Circle is a free programme that helps committed Form 1–4 students in Malawi to <em>understand</em> Mathematics, Physics, Chemistry,
    Biology and English grammar, and to <em>apply</em> what they learn in real life.</p>
<?php if (!empty($data['note'])): ?><p class="letter__note"><?= nl2br(e($data['note'])) ?></p><?php endif; ?>
<h2>Why we need you</h2>
<ul>
    <li><strong>More students than one tutor can serve.</strong> More committed students apply than I can support well on my own; every seat we add needs more teaching hands.</li>
    <li><strong>Five subjects, four forms.</strong> No one person teaches them all at their best. Your strength in <?= e($in) ?> would reach students who need exactly that.</li>
    <li><strong>Quality.</strong> Students deserve quick, careful, encouraging feedback, and lessons that always show where a topic is used in real life in Malawi.</li>
</ul>
<h2>What you would do</h2>
<ul>
    <li>Record short lessons on our online board, from a tablet or phone. Each one answers <em>why it matters</em> and <em>where it is used</em>.</li>
    <li>Write notes with checkpoint exercises, and mark students' work with “Got it”, “Partly there” or “Not yet”, always with a helpful next step.</li>
    <li>Answer students' questions under the lessons.</li>
    <li>Only in your own subjects, a few hours a week, at times that suit you. Everything happens online.</li>
</ul>
<h2>What you gain</h2>
<ul>
    <li><strong>Real teaching experience</strong> with motivated students, using a proven method: real-life teaching and encouraging marking.</li>
    <li><strong>Recognition.</strong> Your name appears on the lessons you record and the notes you write, seen by students, parents and partner schools.</li>
    <li><strong>A reference from the founder</strong> for jobs, scholarships or further study, based on the work you have done.</li>
    <li><strong>New skills</strong> in online teaching, lesson design and digital tools.</li>
    <li><strong>Purpose.</strong> You help young Malawians understand, apply what they learn, and live lives of their own choosing.</li>
</ul>
<p class="letter__box letter__box--blue">Before joining, every tutor reads and agrees to the SMART Circle philosophy and principles: honesty with schools, kindness in marking,
    safeguarding of students, and no favours of any kind. It is voluntary: there is no payment either way.</p>
