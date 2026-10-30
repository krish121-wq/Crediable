<?php
$js = file_get_contents('test_script.js');

// Replace PHP blocks with valid JS to keep line numbers intact.
// e.g. <?php echo ... ; ? > becomes {}
$js = preg_replace('/<\?php.*?\?>/s', '{}', $js);

file_put_contents('test_script_clean.js', $js);
echo "Cleaned JS saved to test_script_clean.js\n";
?>
