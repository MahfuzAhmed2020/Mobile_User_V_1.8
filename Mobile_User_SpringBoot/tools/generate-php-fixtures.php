<?php
if(PHP_SAPI!=='cli')exit(1);
$fixtures=[];
foreach(['Synthetic1!','UTF8-é1!Pass'] as $password)$fixtures[]=['password'=>$password,'hash'=>password_hash($password,PASSWORD_BCRYPT,['cost'=>12])];
file_put_contents(__DIR__.'/../src/test/resources/php-bcrypt.json',json_encode($fixtures,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "Generated synthetic PHP bcrypt compatibility fixtures.\n";
