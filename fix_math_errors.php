<?php
require_once 'config.php';

$conn = getDB();
$note_id = 3;

// Fetch all sections for this note
$result = $conn->query("SELECT id, content FROM note_sections WHERE note_id = $note_id");

while ($row = $result->fetch_assoc()) {
    $content = $row['content'];
    
    // 1. Fix raw (aeqO) to proper LaTeX
    $content = str_replace('(aeqO)', '\\quad (a \\neq 0)', $content);
    
    // 2. Fix missing \right) for \left( patterns
    // This looks for \left( followed by content, then a closing curly brace } without a \right)
    $content = preg_replace('/\\left\\(([^}]*?)(?=\s*})/', '\\left($1\\right)', $content);
    
    // 3. Update the database
    $content = $conn->real_escape_string($content);
    $conn->query("UPDATE note_sections SET content = '$content' WHERE id = {$row['id']}");
}

echo "✅ Fixed LaTeX issues in note_sections. This file will now delete itself.";

// Self-delete
unlink(__FILE__);
?>