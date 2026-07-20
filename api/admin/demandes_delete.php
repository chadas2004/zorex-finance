<?php
session_start();
require __DIR__ . '/../db.php';

// Vérification de l'ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id <= 0) {
    header("Location: demandes.php");
    exit;
}

// Suppression
$stmt = $pdo->prepare("DELETE FROM demandes_financement WHERE id = ?");
$stmt->execute([$id]);

// Redirection
header("Location: demandes.php");
exit;
