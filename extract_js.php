<?php
$c = file_get_contents('sales.php');
preg_match('/<script>(.*?)<\/script>/is', $c, $m);
$js = preg_replace('/<\?php.*?\?>/is', '{}', $m[1]);
file_put_contents('temp.js', $js);
?>
