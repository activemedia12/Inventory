<?php
// test_limits.php - Access directly via browser
echo "<h3>Server Limits</h3>";
echo "POST max size: " . ini_get('post_max_size') . "<br>";
echo "Max input vars: " . ini_get('max_input_vars') . "<br>";
echo "Memory limit: " . ini_get('memory_limit') . "<br>";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "PHP Version: " . PHP_VERSION . "<br>";