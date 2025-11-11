<?php
$result = mail('rambonanzakalis@gmail.com', 'Test Email', 'This is a test', "From: rambonanzakalis@gmail.com\r\nContent-Type: text/html");
echo $result ? "Sent!" : "Failed";
?>
