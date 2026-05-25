<?php
// $config_serverLama = '172.16.0.225\MCOLL_INSTANCE';
$config_serverName = '172.16.1.76';
$config_db = 'MOBILE_COLLECTION';
$config_uid = 'sa';
$config_pwd = 'user.200';
$connectionInfo = array("Database" => $config_db, "UID" => $config_uid, "PWD" => $config_pwd);
$conn = sqlsrv_connect($config_serverName, $connectionInfo);
?>