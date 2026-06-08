<?php
require_once 'config.php';

function formatTestimonial($text) {
    $text = ucfirst(trim($text));
    if (!in_array(substr($text, -1), ['.', '!', '?'])) {
        $text .= '.';
    }
    $text = preg_replace('/\bi\b/', 'I', $text);
    $text = preg_replace('/\bi\'m\b/', "I'm", $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return $text;
}

$conn = getDB();
$result = $conn->query("SELECT fullname, class_level, testimonial, rating, approved_at FROM testimonials WHERE status='approved' ORDER BY approved_at DESC");
$testimonials = [];
while ($row = $result->fetch_assoc()) {
    $row['testimonial'] = formatTestimonial($row['testimonial']);
    $testimonials[] = $row;
}
header('Content-Type: application/json');
echo json_encode($testimonials);
?>