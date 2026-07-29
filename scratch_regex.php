<?php
$search = 'hadi';
$search = preg_replace('/[^a-zA-Z0-9._\-@]/', '', $search);
var_dump($search);
