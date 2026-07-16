<?php
session_start();

// Détruire toutes les variables de session
$_SESSION = [];

// Détruire la session côté serveur
session_destroy();

// Rediriger vers la page de connexion
header('Location: /index.html');
exit;
