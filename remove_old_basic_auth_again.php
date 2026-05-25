<?php
// remove_old_basic_auth_again.php – Removes the old HTTP Basic Auth block but keeps the new session check
$self = __FILE__;

function getPHPFiles($dir, $exclude = []) {
    $files = [];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $files = array_merge($files, getPHPFiles($path, $exclude));
        } else {
            if (pathinfo($path, PATHINFO_EXTENSION) === 'php' && !in_array($path, $exclude)) {
                $files[] = $path;
            }
        }
    }
    return $files;
}

$exclude = [$self, 'config.php', 'login.php'];
$phpFiles = getPHPFiles(__DIR__, $exclude);

$pattern = '/if \(!isset\(\$_SESSION\[\'admin_logged\'\]\)\) \{(?:[^{}]|(?R))*?unset\(\$_SESSION\[\'user_id\'\]\);\s*?\}/s';
$count = 0;

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    
    // Remove the entire old block if it exists
    $newContent = preg_replace($pattern, '', $content);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "✅ Removed old HTTP Basic Auth block from: $file<br>";
        $count++;
    } else {
        // Check if the file has the new session check but still has the old block
        if (strpos($content, 'header("Location: login.php")') !== false && strpos($content, 'unset($_SESSION[\'user_id\']);') !== false) {
            echo "⚠️ Skipped but needs manual review: $file<br>";
        }
    }
}

echo "✅ Total files cleaned: $count<br>";
echo "This script will delete itself now.<br>";
unlink(__FILE__);
echo "✅ Script removed.<br>";
?>