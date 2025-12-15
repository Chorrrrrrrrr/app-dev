<?php
$new_password = '12345'; 
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
echo "Your new hash is: <br>";
echo $hashed_password;
?>