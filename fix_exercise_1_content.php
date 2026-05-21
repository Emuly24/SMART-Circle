<?php
require_once 'config.php';

// --- Copy the extract function directly so no external file is needed ---
function extractSectionsFromHTML($html, $conn, $note_id) {
    $html = trim($html);
    preg_match_all('/<h[34][^>]*>.*?Exercise.*?<\/h[34]>/i', $html, $matches, PREG_OFFSET_CAPTURE);
    $headings = $matches[0];
    $sections = [];
    $lastPos = 0;
    
    foreach ($headings as $index => $heading) {
        $pos = $heading[1];
        if ($index == 0) {
            $introContent = substr($html, 0, $pos);
            if (trim($introContent)) {
                $sections[] = ['type' => 'introduction', 'content' => $introContent, 'exercise_id' => null];
            }
        }
        $nextPos = isset($headings[$index + 1]) ? $headings[$index + 1][1] : strlen($html);
        $exerciseContent = substr($html, $pos, $nextPos - $pos);
        $exerciseId = null;
        if (preg_match('/Exercise\s+(\d+)/i', $heading[0], $idMatch)) {
            $exerciseId = (int)$idMatch[1];
        }
        $sections[] = ['type' => 'exercise', 'content' => $exerciseContent, 'exercise_id' => $exerciseId];
        $lastPos = $nextPos;
    }
    
    if (empty($sections)) {
        $sections[] = ['type' => 'introduction', 'content' => $html, 'exercise_id' => null];
    }
    return $sections;
}

// --- MAIN ---
$conn = getDB();
$note_id = 3;

$note = $conn->query("SELECT content FROM notes WHERE id = $note_id")->fetch_assoc();
if (!$note) die("Note not found");

$sections = extractSectionsFromHTML($note['content'], $conn, $note_id);

foreach ($sections as $index => $section) {
    $sort_order = $index + 1;
    $section_type = $section['type'];
    $section_content = $conn->real_escape_string($section['content']);
    $exercise_id = $section['exercise_id'] ? $section['exercise_id'] : 'NULL';

    // Ensure note_exercises exists for each exercise
    if ($section['type'] == 'exercise' && $section['exercise_id']) {
        $checkEx = $conn->query("SELECT id FROM note_exercises WHERE note_id = $note_id AND sort_order = $sort_order");
        if ($checkEx->num_rows == 0) {
            $conn->query("INSERT INTO note_exercises (note_id, sort_order, question) VALUES ($note_id, $sort_order, 'Exercise $sort_order')");
        }
        $exRow = $conn->query("SELECT id FROM note_exercises WHERE note_id = $note_id AND sort_order = $sort_order")->fetch_assoc();
        $exercise_id = $exRow['id'];
    }

    // UPDATE existing row OR INSERT if it doesn't exist
    $checkSec = $conn->query("SELECT id FROM note_sections WHERE note_id = $note_id AND sort_order = $sort_order");
    if ($checkSec->num_rows > 0) {
        $conn->query("UPDATE note_sections SET section_type='$section_type', content='$section_content', exercise_id=" . ($exercise_id ? $exercise_id : 'NULL') . " WHERE note_id = $note_id AND sort_order = $sort_order");
    } else {
        $conn->query("INSERT INTO note_sections (note_id, sort_order, section_type, content, exercise_id) 
            VALUES ($note_id, $sort_order, '$section_type', '$section_content', " . ($exercise_id ? $exercise_id : 'NULL') . ")");
    }
}

echo "✅ Exercise 1 content has been fixed with the real content from your note!";
unlink(__FILE__);
?>