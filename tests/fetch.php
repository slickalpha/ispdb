<?php 


$file = '../ispdb.json.gz';

$domain = 'mail.com'; // test domain

$ispdb_arr = json_decode(gzdecode(file_get_contents($file)), true);

var_dump($ispdb_arr[$domain]);

die();

?>
