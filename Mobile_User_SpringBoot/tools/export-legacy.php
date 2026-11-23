<?php
// Read-only export. Supply a dedicated SELECT-only source database account.
if(PHP_SAPI!=='cli')exit(1);
$required=['LEGACY_DB_HOST','LEGACY_DB_NAME','LEGACY_DB_USER','LEGACY_TIMEZONE','EXPORT_FILE','IMPORT_SOURCE_KEY'];
foreach($required as $key)if(!getenv($key))throw new RuntimeException("Missing $key");
$path=getenv('EXPORT_FILE');if(file_exists($path))throw new RuntimeException('Refusing to overwrite export.');
$zone=new DateTimeZone(getenv('LEGACY_TIMEZONE'));
$pdo=new PDO('mysql:host='.getenv('LEGACY_DB_HOST').';port='.(getenv('LEGACY_DB_PORT')?:3306).';dbname='.getenv('LEGACY_DB_NAME').';charset=utf8mb4',getenv('LEGACY_DB_USER'),getenv('LEGACY_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("SET time_zone='+00:00'");$pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();
$columns=[
 'users'=>'id,first_name,last_name,email,password,created_at',
 'products'=>'id,name,description,price',
 'addresses'=>'id,user_id,address_line,city,state,zip,country',
 'addresses_whitelist'=>'id,user_id,address_line,city,state,zip,country',
 'cart'=>'id,user_id,product_id,quantity',
 'orders'=>'id,user_id,address_id,total,whitelist_address_id,deleted_at,created_at',
 'order_items'=>'id,order_id,product_id,product_name,price,quantity',
 'order_tracking'=>'id,order_id,tracking_number,status,deleted_at',
];
$out=['source_key'=>getenv('IMPORT_SOURCE_KEY'),'format'=>1,'tables'=>[]];
foreach($columns as $table=>$fields){
 $rows=$pdo->query("SELECT $fields FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
 foreach($rows as &$row){
  foreach(['created_at','deleted_at'] as $column)if(!empty($row[$column])){
   // created_at is MySQL TIMESTAMP and already in UTC; legacy deleted_at DATETIME uses the explicitly selected legacy timezone.
   $date=new DateTimeImmutable($row[$column],$column==='deleted_at'?$zone:new DateTimeZone('UTC'));
   $row[$column]=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
  }
 }unset($row);$out['tables'][$table]=$rows;
}
$pdo->commit();
// Excludes reset tokens, email whitelist, card numbers, notes, and database credentials.
$handle=fopen($path,'x');if(!$handle)throw new RuntimeException('Unable to create export.');
fwrite($handle,json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($handle);
echo "Read-only export completed. Treat the JSON as sensitive account data; do not commit it.\n";
