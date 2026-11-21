<?php
/**
 * @var list<array<string, mixed>> $lessons
 * @var array<string, string> $subjects
 * @var array<int, list<string>> $missing
 */
?>
<section class="section">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>My lessons</h1>
        <p class="lead">Lessons uploaded from the board. Fill in the real-life sections, then publish.</p>
        <p><a class="btn" href="/studio/lab">Record a new lesson</a></p>

        <?php if ($lessons === []): ?>
            <div class="card"><p>No lessons uploaded yet. Record one on the board, then press <strong>Upload</strong> on the Replay tab.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Lesson</th><th>Form / subject</th><th>Length</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($lessons as $lesson): $id = (int) $lesson['id']; ?>
                        <tr>
                            <td><a href="/studio/lessons/<?= e($id) ?>"><?= e($lesson['title']) ?></a></td>
                            <td>
                                <?= $lesson['form'] ? 'Form ' . e($lesson['form']) : '<span class="muted">No form</span>' ?>
                                <?php if ($lesson['subject']): ?><span class="badge badge--<?= e($lesson['subject']) ?>"><?= e($subjects[$lesson['subject']] ?? '') ?></span><?php endif; ?>
                            </td>
                            <td><?= e(gmdate('i:s', intdiv((int) $lesson['duration_ms'], 1000))) ?></td>
                            <td>
                                <?php if ($lesson['status'] === 'published'): ?>
                                    <span class="tag tag--ready">Published</span>
                                <?php elseif (($missing[$id] ?? []) === []): ?>
                                    <span class="tag">Ready to publish</span>
                                <?php else: ?>
                                    <span class="tag">Draft · <?= e(count($missing[$id])) ?> to fill in</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
