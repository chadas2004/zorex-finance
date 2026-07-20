<?php
session_start();
require __DIR__ . '/../db.php';
if(!isset($_SESSION['admin'])) exit;

$page = (int)($_GET['page'] ?? 1);
$perPage = (int)($_GET['perPage'] ?? 10);
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$pays = $_GET['pays'] ?? '';

$where = [];
$params = [];

if($search) {
    $where[] = "(nom ILIKE :search OR prenom ILIKE :search OR email ILIKE :search)";
    $params[':search'] = "%$search%";
}
if($type) {
    $where[] = "type_pret = :type";
    $params[':type'] = $type;
}
if($pays) {
    $where[] = "pays = :pays";
    $params[':pays'] = $pays;
}

$whereSQL = $where ? "WHERE " . implode(' AND ', $where) : "";

$total = $pdo->prepare("SELECT COUNT(*) FROM demandes_financement $whereSQL");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$offset = ($page-1)*$perPage;
$stmt = $pdo->prepare("SELECT * FROM demandes_financement $whereSQL ORDER BY id DESC LIMIT :perPage OFFSET :offset");
foreach($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':offset',(int)$offset,PDO::PARAM_INT);
$stmt->bindValue(':perPage',(int)$perPage,PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll();

echo json_encode(['data'=>$data,'totalPages'=>$totalPages]);
