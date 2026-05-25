<?php
// replace_basic_auth_with_session.php – Removes HTTP Basic Auth block and inserts session check
// Run once from browser, then it deletes itself.

$self = __FILE__;

// Recursive function to get all .php files
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

// The session check code to insert
$sessionCheckCode = "if (!isset(\$_SESSION['admin_logged']) || \$_SESSION['admin_logged'] !== true) {\n    header(\"Location: login.php\");\n    exit;\n}\n\n";

// Regex pattern to match the old HTTP Basic Auth block
// This pattern matches:
// if (!isset($_SESSION['admin_logged'])) { ... (any code) ... $_SESSION['admin_logged'] = true; ... $_SESSION['role'] = 'admin'; ... unset($_SESSION['user_id']); ... }
$pattern = '/if \(!isset\(\$_SESSION\[\'admin_logged\'\]\)\) \{(?:[^{}]|(?R))*?\$_SESSION\[\'admin_logged\'\] = true;\s*?\$_SESSION\[\'role\'\] = \'admin\';\s*?unset\(\$_SESSION\[\'user_id\'\]\);\s*?\}/s';

// Alternative pattern for files that define $admin_hash inside or before the block
$pattern2 = '/if \(!isset\(\$_SERVER\[\'PHP_AUTH_USER\'\]\) \|\| !password_verify\(\$_SERVER\[\'PHP_AUTH_PW\'\]\,.*?\)\)\s*\{[^}]*?unset\(\$_SESSION\[\'user_id\'\]\);\s*?\}/s';

$exclude = [$self, 'config.php', 'login.php'];
$phpFiles = getPHPFiles(__DIR__, $exclude);

$count = 0;
foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    
    // Try pattern1 (the most common block structure)
    $newContent = preg_replace($pattern, $sessionCheckCode, $content);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "✅ Replaced block (pattern1) in: $file<br>";
        $count++;
        continue;
    }
    
    // Try pattern2 (for different variable name or structure)
    $newContent = preg_replace($pattern2, $sessionCheckCode, $content);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "✅ Replaced block (pattern2) in: $file<br>";
        $count++;
        continue;
    }
    
    // If no match found, check if the file has admin_logged but no session check
    if (strpos($content, '$_SESSION[\'admin_logged\']') !== false && 
        strpos($content, 'header("Location: login.php")') === false) {
        // Insert the session check after the opening PHP tag
        $newContent = preg_replace('/<\?php/', "<?php\n" . $sessionCheckCode, $content, 1);
        if ($newContent !== $content) {
            file_put_contents($file, $newContent);
            echo "✅ Added missing session check to: $file<br>";
            $count++;
        }
    }
}

echo "✅ Total files processed: $count<br>";
echo "This script will now delete itself.<br>";

// Delete this script
unlink(__FILE__);
echo "✅ Script removed.<br>";
?>