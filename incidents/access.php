<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
requireRole($user['role']);
$types = ['Flooding', 'Fire', 'Landslide', 'Road obstruction', 'Other emergency'];
$areas = ['Abiang', 'Caliking', 'Cattubo', 'Naguey', 'Paoay', 'Pasdong', 'Poblacion', 'Topdac'];
