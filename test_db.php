<?php
require 'config/db.php';
print_r($pdo->query('SELECT * FROM certificate_templates ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC));
