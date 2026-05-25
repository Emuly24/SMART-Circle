<?php
// find_redirect.php – Step-by-step inclusion test to find the redirect loop

echo "<h1>🔍 Finding the redirect loop source</h1>";

$files_to_test = [
    'config.php',
    'cookie_login.php',
    'check_access.php',
    'auth_check.php',
    'check_remember_me.php'
];

foreach ($files_to_test as $file) {
    echo "<h2>Testing inclusion of <code>$file</code>:</h2>";
    if (!file_exists($file)) {
        echo "❌ $file not found.<br>";
        continue;
    }
    
    // Temporarily override header() to prevent actual redirects
    function header($string, $replace = true, $http_response_code = null) {
        // Intercept redirects and log them
        if (strpos($string, 'Location:') === 0) {
            echo "⚠️ Intercepted redirect to: " . substr($string, 9) . "<br>";
            return;
        }
        // Allow other headers to pass
        return;
    }
    
    ob_start();
    require_once $file;
    $output = ob_get_clean();
    
    echo "✅ $file loaded without redirecting.<br>";
    if (!empty($output)) {
        echo "Output from $file:<br><pre>" . htmlspecialchars($output) . "</pre>";
    }
}
?>