<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
echo "Testing SNMP to 10.99.99.2:1161\n";
$res = snmp2_real_walk("10.99.99.2:1161", "public", "1.3.6.1.2.1.1", 3000000, 2);
var_dump($res);
