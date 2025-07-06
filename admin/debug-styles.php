<?php
/**
 * Diagnostic script to debug CSS loading issues
 * Access this at: https://pay.capefearlawn.com/admin/debug-styles.php
 */

// Check if headers were already sent
$headers_sent = headers_sent($file, $line);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS Debug Information</title>
    
    <!-- Test CDN loading directly -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body { font-family: monospace; padding: 20px; }
        .section { margin: 20px 0; padding: 20px; border: 1px solid #ccc; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>CSS Loading Debug Information</h1>
    
    <div class="section">
        <h2>1. Headers Status</h2>
        <?php if ($headers_sent): ?>
            <p class="error">⚠️ Headers were already sent at: <?php echo htmlspecialchars($file); ?> line <?php echo $line; ?></p>
        <?php else: ?>
            <p class="success">✓ Headers not sent yet (good)</p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>2. Current Headers</h2>
        <pre><?php print_r(headers_list()); ?></pre>
    </div>
    
    <div class="section">
        <h2>3. Protocol Check</h2>
        <p>Is HTTPS: <span class="<?php echo (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'success' : 'error'; ?>">
            <?php echo (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'Yes' : 'No'; ?>
        </span></p>
        <p>Server Protocol: <?php echo $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown'; ?></p>
        <p>Request Scheme: <?php echo $_SERVER['REQUEST_SCHEME'] ?? 'Unknown'; ?></p>
    </div>
    
    <div class="section">
        <h2>4. CDN Connectivity Test</h2>
        <p>Testing connection to CDNs from server...</p>
        <?php
        $cdns = [
            'Tailwind CSS' => 'https://cdn.tailwindcss.com',
            'Font Awesome' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
        ];
        
        foreach ($cdns as $name => $url) {
            $context = stream_context_create([
                "http" => [
                    "timeout" => 5,
                    "ignore_errors" => true
                ],
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false
                ]
            ]);
            
            $headers = @get_headers($url, 1, $context);
            if ($headers && strpos($headers[0], '200') !== false) {
                echo "<p class='success'>✓ $name: Accessible</p>";
            } else {
                echo "<p class='error'>✗ $name: Not accessible - " . ($headers ? $headers[0] : 'Connection failed') . "</p>";
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h2>5. Tailwind Test</h2>
        <p class="text-2xl font-bold text-blue-600">If this text is large, bold, and blue, Tailwind is working!</p>
        <button class="bg-blue-500 text-white px-4 py-2 rounded">Tailwind Button Test</button>
    </div>
    
    <div class="section">
        <h2>6. Font Awesome Test</h2>
        <p>Icons: 
            <i class="fas fa-check text-green-500"></i> Check
            <i class="fas fa-times text-red-500"></i> Times
            <i class="fas fa-user text-blue-500"></i> User
        </p>
    </div>
    
    <div class="section">
        <h2>7. JavaScript Console Check</h2>
        <p>Open browser console (F12) and check for any errors above.</p>
        <script>
            console.log('Debug script loaded successfully');
            
            // Check if Tailwind loaded
            if (typeof tailwind !== 'undefined') {
                console.log('✓ Tailwind CSS loaded successfully');
            } else {
                console.error('✗ Tailwind CSS failed to load');
            }
            
            // Check Font Awesome
            const faTest = document.querySelector('.fa-check');
            if (faTest && window.getComputedStyle(faTest, ':before').content !== 'none') {
                console.log('✓ Font Awesome loaded successfully');
            } else {
                console.error('✗ Font Awesome failed to load');
            }
        </script>
    </div>
    
    <div class="section">
        <h2>8. Browser Information</h2>
        <p>User Agent: <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'); ?></p>
    </div>
    
    <div class="section">
        <h2>9. Recommendations</h2>
        <ul>
            <li>Check browser console for CSP violations</li>
            <li>Verify HTTPS certificates are valid</li>
            <li>Check if server firewall blocks outgoing HTTPS requests</li>
            <li>Consider downloading and hosting CSS files locally</li>
        </ul>
    </div>
</body>
</html>