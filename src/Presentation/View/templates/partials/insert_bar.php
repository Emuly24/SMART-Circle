<?php
/**
 * "Insert" toolbar above a notes text box: tables, symbols, equations, fractions, boxes and reference tables.
 * It types the notes markup into the box (works without it too: the markup can be typed by hand).
 * Needs public/assets/js/notes-insert.js.
 */
use SmartCircle\Support\ReferenceTables;
use SmartCircle\Support\Symbols;

$subjectNames = ['mathematics' => 'Mathematics', 'physics' => 'Physics', 'chemistry' => 'Chemistry', 'biology' => 'Biology', 'english' => 'English grammar'];
?>
<div class="insert-bar js-insert-bar" hidden>
    <button type="button" class="btn btn--small btn--ghost" data-wrap="**|**" title="Bold">B</button>
    <button type="button" class="btn btn--small btn--ghost" data-insert="# " data-line title="Heading"># Heading</button>
    <button type="button" class="btn btn--small btn--ghost" data-insert="= " data-line title="A centred equation or calculation step">= Equation</button>
    <button type="button" class="btn btn--small btn--ghost" data-wrap="frac{|}{b}" title="Stacked fraction">a⁄b Fraction</button>
    <button type="button" class="btn btn--small btn--ghost" data-wrap="^{|}" title="Power / superscript">x² Power</button>
    <button type="button" class="btn btn--small btn--ghost" data-wrap="_{|}" title="Subscript">H₂ Sub</button>
    <button type="button" class="btn btn--small btn--ghost" data-pop="symbols">Ω Symbols</button>
    <button type="button" class="btn btn--small btn--ghost" data-pop="table">▦ Table</button>
    <select class="js-insert-box" aria-label="Insert a box">
        <option value="">Box…</option>
        <option value="> Real life: ">Real life (amber)</option>
        <option value="> Why it matters: ">Why it matters (amber)</option>
        <option value="> Remember: ">Remember (blue)</option>
        <option value="> Key idea: ">Key idea (blue)</option>
        <option value="> Example: ">Example (green)</option>
        <option value="> Tip: ">Tip (green)</option>
        <option value="> Careful: ">Careful (warning)</option>
    </select>
    <select class="js-insert-ref" aria-label="Insert a standard reference table">
        <option value="">Standard table…</option>
        <?php foreach (ReferenceTables::menu() as $subject => $tables): ?>
            <optgroup label="<?= e($subjectNames[$subject] ?? $subject) ?>">
                <?php foreach ($tables as $key => $title): ?><option value="<?= e($key) ?>"><?= e($title) ?></option><?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
</div>
<div class="insert-pop js-pop-symbols" hidden>
    <?php foreach (Symbols::picker() as $group => $list): ?>
        <div class="insert-pop__group"><strong><?= e($group) ?></strong>
            <?php foreach ($list as $sym): ?><button type="button" class="insert-pop__sym" data-sym="<?= e($sym) ?>"><?= e($sym) ?></button><?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <p class="muted">You can also type <code>\alpha</code>, <code>\Delta</code>, <code>\deg</code>, <code>\therefore</code>, <code>\times</code>, <code>\pi</code> …</p>
</div>
<div class="insert-pop js-pop-table" hidden>
    <div class="insert-pop__row">
        <label>Columns <input type="number" class="js-table-cols" min="1" max="8" value="3"></label>
        <label>Rows (after the heading) <input type="number" class="js-table-rows" min="1" max="30" value="3"></label>
        <button type="button" class="btn btn--small js-table-insert">Insert table</button>
    </div>
    <p class="muted">Each row is a line like <code>| cell | cell |</code>; the first row is the heading. Replace the words with your own.</p>
</div>
