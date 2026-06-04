<?php
// Cierra la sesión del usuario y lo manda de vuelta al login
session_start();
session_destroy();
header('Location: login.php');
exit;
