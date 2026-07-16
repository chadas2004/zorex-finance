<?php
/**
 * Connexion à la base de données Neon (PostgreSQL)
 * Les identifiants viennent des variables d'environnement Vercel,
 * jamais écrits en dur ici.
 *
 * À configurer sur Vercel (Project Settings > Environment Variables) :
 *   DB_HOST  = ep-xxxxxxx.eu-central-1.aws.neon.tech
 *   DB_NAME  = nom_de_ta_base
 *   DB_USER  = ton_user_neon
 *   DB_PASS  = ton_mot_de_passe_neon
 */

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

// DSN PostgreSQL — sslmode=require est obligatoire pour Neon
$dsn = "pgsql:host=$host;port=5432;dbname=$db;sslmode=require";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()
    ]));
}
