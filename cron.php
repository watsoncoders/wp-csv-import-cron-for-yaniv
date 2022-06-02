<?php

$path = dirname(__FILE__);
$cron = $path . "/import.php";
echo exec("***1* php -q ".$cron." &> /dev/null");

?>
