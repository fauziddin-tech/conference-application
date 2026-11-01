<?php require 'inc.php';
if (hash_equals($_SESSION['t'] ?? '', (string)($_GET['t'] ?? ''))) { $_SESSION = []; session_destroy(); }
go('./');
