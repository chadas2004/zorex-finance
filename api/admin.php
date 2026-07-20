<?php
session_start();
require __DIR__ . '/includes/db.php';

// Déterminer si c'est une action API (JSON) ou une vue (HTML)
$action = $_GET['action'] ?? '';
$view   = $_GET['view'] ?? 'dashboard';

// ============================================================================
// 1. ACTIONS API (Retournent du JSON)
// ============================================================================

if ($action === 'ajax_list') {
    header('Content-Type: application/json');
    
    $page    = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['perPage'] ?? 10);
    $search  = $_GET['search'] ?? '';
    $type    = $_GET['type'] ?? '';
    $pays    = $_GET['pays'] ?? '';

    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(nom ILIKE :search OR prenom ILIKE :search OR email ILIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($type) {
        $where[] = "type_pret = :type";
        $params[':type'] = $type;
    }
    if ($pays) {
        $where[] = "pays = :pays";
        $params[':pays'] = $pays;
    }

    $whereSQL = $where ? "WHERE " . implode(' AND ', $where) : "";

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM demandes_financement $whereSQL");
    $totalStmt->execute($params);
    $totalRows = $totalStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRows / $perPage));

    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT * FROM demandes_financement $whereSQL ORDER BY id DESC LIMIT :perPage OFFSET :offset");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode(['data' => $stmt->fetchAll(), 'totalPages' => $totalPages]);
    exit;
}

if ($action === 'delete') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM demandes_financement WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    // Si c'est un appel AJAX (comme dans le dashboard), on retourne du JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Sinon, on redirige comme un formulaire classique
    header("Location: admin.php?view=demandes");
    exit;
}

// ============================================================================
// 2. VUES HTML (Retournent du HTML)
// ============================================================================
header('Content-Type: text/html; charset=utf-8');

// Helper pour les badges
function typeBadgeClass(string $type): string {
    if (str_contains($type, 'Personnel'))      return 'badge-personal';
    if (str_contains($type, 'Immobilier'))     return 'badge-immo';
    if (str_contains($type, 'Auto'))           return 'badge-auto';
    if (str_contains($type, 'Professionnel'))  return 'badge-pro';
    if (str_contains($type, 'Étudiant'))       return 'badge-etudiant';
    return 'badge-personal';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $view === 'dashboard' ? 'Dashboard' : ($view === 'detail' ? 'Détail Demande' : 'Demandes') ?> | Zorex Finance</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <?php if ($view === 'dashboard'): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <?php endif; ?>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root { --navy:#042C53; --navy-mid:#0C447C; --blue:#185FA5; --gold:#FAC775; --gold-dark:#EF9F27; --gold-deeper:#BA7517; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { font-family: 'DM Sans', sans-serif; background: #F1F4F9; color: #1a1a1a; }
    .font-display { font-family: 'Playfair Display', serif; }
    .sidebar { width:240px; min-width:240px; background:var(--navy); min-height:100vh; position:sticky; top:0; display:flex; flex-direction:column; }
    .sidebar-logo { padding:22px 20px 18px; border-bottom:1px solid rgba(255,255,255,0.07); }
    .sidebar-brand { font-family:'Playfair Display',serif; font-size:16px; font-weight:600; color:var(--gold); }
    .sidebar-tagline { font-size:10px; color:rgba(255,255,255,0.35); margin-top:2px; }
    .nav-item { display:flex; align-items:center; gap:10px; padding:10px 18px; margin:2px 10px; border-radius:10px; font-size:13px; color:rgba(255,255,255,0.55); text-decoration:none; transition:all .18s; }
    .nav-item:hover { background:rgba(255,255,255,0.07); color:rgba(255,255,255,0.9); }
    .nav-item.active { background:rgba(250,199,117,0.12); color:var(--gold); }
    .nav-item i { font-size:15px; width:18px; text-align:center; color:rgba(255,255,255,0.35); }
    .nav-item.active i { color:var(--gold); }
    .nav-section { font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:rgba(255,255,255,0.2); padding:16px 18px 6px; }
    .nav-logout { color:rgba(248,113,113,0.7) !important; }
    .nav-logout:hover { background:rgba(248,113,113,0.08) !important; color:#f87171 !important; }
    .topbar { background:#fff; border-bottom:1px solid rgba(4,44,83,0.07); padding:14px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:40; }
    .page-title { font-family:'Playfair Display',serif; font-size:20px; font-weight:600; color:var(--navy); }
    .card { background:white; border:1px solid rgba(4,44,83,0.07); border-radius:16px; }
    .filter-input { background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:10px; padding:9px 14px; font-size:13px; font-family:'DM Sans',sans-serif; color:#374151; outline:none; transition:border-color .2s; }
    .filter-input:focus { border-color:var(--navy); }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#9CA3AF; padding:11px 16px; text-align:left; border-bottom:1px solid rgba(4,44,83,0.07); background:#FAFBFC; }
    .data-table td { padding:13px 16px; font-size:13px; color:#374151; border-bottom:1px solid rgba(4,44,83,0.04); vertical-align:middle; }
    .data-table tbody tr:hover td { background:rgba(4,44,83,0.02); }
    .badge-type { display:inline-block; font-size:11px; font-weight:500; padding:3px 10px; border-radius:20px; white-space:nowrap; }
    .badge-personal { background:#E6F1FB; color:#0C447C; }
    .badge-immo { background:#FAEEDA; color:#854F0B; }
    .badge-auto { background:#E1F5EE; color:#0F6E56; }
    .badge-pro { background:#EEEDFE; color:#534AB7; }
    .badge-etudiant { background:#FDE8F3; color:#9C2E6B; }
    .avatar { width:32px; height:32px; border-radius:50%; background:var(--navy); color:var(--gold); font-size:11px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .page-btn { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:500; border:1px solid #E2E8F0; background:white; color:#374151; text-decoration:none; transition:all .15s; cursor: pointer;}
    .page-btn:hover { background:var(--navy); color:var(--gold); border-color:var(--navy); }
    .page-btn.active { background:var(--navy); color:var(--gold); border-color:var(--navy); }
    .mobile-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:30; }
    .mobile-overlay.show { display:block; }
    @media (max-width:768px) {
      .sidebar { position:fixed; top:0; left:0; z-index:40; height:100vh; transform:translateX(-100%); transition:transform .3s; }
      .sidebar.open { transform:translateX(0); }
    }
    .eyebrow { font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--gold-deeper); background:rgba(250,199,117,0.12); padding:3px 10px; border-radius:20px; display:inline-block; }
    .kpi-card { background: white; border: 1px solid rgba(4,44,83,0.07); border-radius: 16px; padding: 20px 22px; transition: all 0.2s; }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(4,44,83,0.08); }
    .kpi-num { font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 600; color: var(--navy); line-height: 1; margin: 6px 0 4px; }
    .chart-card { background: white; border: 1px solid rgba(4,44,83,0.07); border-radius: 16px; padding: 22px; }
    .chart-title { font-size: 14px; font-weight: 500; color: var(--navy); margin-bottom: 4px; }
    .chart-subtitle { font-size: 11px; color: #9CA3AF; margin-bottom: 16px; }
    .btn-wa { display:flex; align-items:center; justify-content:center; gap:10px; background:linear-gradient(135deg,#22c55e,#16a34a); color:white; font-weight:600; font-size:14px; padding:14px 20px; border-radius:12px; text-decoration:none; transition:all .2s; }
    .btn-delete { display:flex; align-items:center; justify-content:center; gap:7px; background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; font-size:13px; font-weight:500; padding:11px 16px; border-radius:10px; text-decoration:none; transition:all .15s; cursor:pointer; }
  </style>
</head>
<body class="flex min-h-screen">

<div class="mobile-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo flex items-center gap-3">
    <div style="width:34px;height:34px;border-radius:9px;background:rgba(250,199,117,0.12);border:1px solid rgba(250,199,117,0.2);display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#FAC775" stroke-width="2" stroke-linecap="round"><path d="M12 2L4 7v5c0 5 3.5 9.7 8 11 4.5-1.3 8-6 8-11V7L12 2z"/></svg>
    </div>
    <div>
      <div class="sidebar-brand">Zorex Finance</div>
      <div class="sidebar-tagline">Administration</div>
    </div>
  </div>
  <nav class="flex-1 pt-4 pb-4">
    <div class="nav-section">Principal</div>
    <a href="admin.php?view=dashboard" class="nav-item <?= $view === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> Tableau de bord</a>
    <a href="admin.php?view=demandes" class="nav-item <?= $view === 'demandes' || $view === 'detail' ? 'active' : '' ?>"><i class="bi bi-file-earmark-text-fill"></i> Demandes</a>
    <div class="nav-section mt-2">Compte</div>
    <a href="logout.php" class="nav-item nav-logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
  </nav>
  <div style="padding:14px 18px;border-top:1px solid rgba(255,255,255,0.07)">
    <div class="flex items-center gap-3">
      <div class="avatar">AD</div>
      <div>
        <div style="font-size:12px;font-weight:500;color:rgba(255,255,255,0.8)">Administrateur</div>
        <div style="font-size:10px;color:rgba(255,255,255,0.35)">admin@morafinanze.com</div>
      </div>
    </div>
  </div>
</aside>

<!-- MAIN CONTENT -->
<div class="flex-1 flex flex-col overflow-hidden">
  <div class="topbar">
    <div class="flex items-center gap-4">
      <button onclick="openSidebar()" class="md:hidden w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200"><i class="bi bi-list text-xl text-gray-600"></i></button>
      <div>
        <div class="page-title"><?= $view === 'dashboard' ? 'Tableau de bord' : ($view === 'detail' ? 'Détail de la demande' : 'Demandes de financement') ?></div>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <a href="/index.html" target="_blank" class="hidden md:flex items-center gap-2 px-3 h-9 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition" style="text-decoration:none"><i class="bi bi-box-arrow-up-right text-xs"></i> Voir le site</a>
      <div class="avatar">AD</div>
    </div>
  </div>

  <div class="flex-1 overflow-y-auto p-5 md:p-7 space-y-7">

    <!-- ========================================================================= -->
    <!-- VUE : DASHBOARD (anciennement index.php)                                  -->
    <!-- ========================================================================= -->
    <?php if ($view === 'dashboard'): 
      $stats = $pdo->query("SELECT type_pret, COUNT(*) AS total, SUM(montant) AS montant_total FROM demandes_financement GROUP BY type_pret")->fetchAll();
      $kpis = $pdo->query("SELECT COUNT(*) AS total_demandes, SUM(montant) AS montant_total, COUNT(DISTINCT pays) AS nb_pays, AVG(montant) AS montant_moyen FROM demandes_financement")->fetch();
      $byPays = $pdo->query("SELECT pays, COUNT(*) AS total FROM demandes_financement GROUP BY pays ORDER BY total DESC")->fetchAll();
      $monthly = $pdo->query("SELECT TO_CHAR(date_demande, 'YYYY-MM') AS mois, COUNT(*) AS total FROM demandes_financement GROUP BY mois ORDER BY mois ASC LIMIT 12")->fetchAll();
    ?>
      <div>
        <div class="eyebrow">Vue d'ensemble</div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
          <div class="kpi-card">
            <div class="kpi-num"><?= number_format($kpis['total_demandes']) ?></div>
            <div class="kpi-label">Demandes totales</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-num">€<?= $kpis['montant_total'] >= 1000000 ? number_format($kpis['montant_total']/1000000, 1).'M' : number_format($kpis['montant_total']/1000, 0).'K' ?></div>
            <div class="kpi-label">Montant total sollicité</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-num"><?= $kpis['nb_pays'] ?></div>
            <div class="kpi-label">Pays représentés</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-num">€<?= number_format($kpis['montant_moyen']/1000, 0) ?>K</div>
            <div class="kpi-label">Montant moyen</div>
          </div>
        </div>
      </div>

      <div class="chart-card">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
          <div><div class="eyebrow">Dernières demandes</div><div class="chart-title mt-1">Toutes les demandes de financement</div></div>
          <a href="admin.php?view=demandes" class="filter-input flex items-center gap-2 no-underline text-gray-600 hover:bg-gray-100 transition" style="text-decoration:none; width:auto;"><i class="bi bi-arrow-right text-xs"></i> Tout voir</a>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead><tr><th>#</th><th>Nom / Prénom</th><th>Email</th><th>Pays</th><th>Type de prêt</th><th>Montant</th><th>WhatsApp</th><th>Actions</th></tr></thead>
            <tbody x-data="adminDashboard()" x-init="init()">
              <template x-if="demandes.length === 0">
                <tr><td colspan="8" style="text-align:center;padding:32px;color:#9CA3AF"><i class="bi bi-inbox text-3xl block mb-2"></i>Aucune demande trouvée</td></tr>
              </template>
              <template x-for="d in demandes" :key="d.id">
                <tr>
                  <td><span style="font-size:11px;color:#9CA3AF;font-weight:500">#<span x-text="d.id"></span></span></td>
                  <td><div style="display:flex;align-items:center;gap:9px"><div class="avatar" style="width:28px;height:28px;font-size:10px" x-text="(d.prenom?.[0]||'') + (d.nom?.[0]||'')"></div><div style="font-weight:500;font-size:13px" x-text="d.prenom + ' ' + d.nom"></div></div></td>
                  <td style="color:#6B7280;font-size:12px" x-text="d.email"></td>
                  <td><span style="font-size:12px;color:#374151" x-text="d.pays?.split(' ')[0] || d.pays"></span></td>
                  <td><span class="badge-type" :class="{'badge-personal': d.type_pret?.includes('Personnel'), 'badge-immo': d.type_pret?.includes('Immobilier'), 'badge-auto': d.type_pret?.includes('Auto'), 'badge-pro': d.type_pret?.includes('Professionnel'), 'badge-etudiant': d.type_pret?.includes('Étudiant')}" x-text="d.type_pret"></span></td>
                  <td><span style="font-family:'Playfair Display',serif;font-size:14px;font-weight:600;color:#042C53" x-text="Number(d.montant).toLocaleString('fr-FR') + ' €'"></span></td>
                  <td><a :href="'https://wa.me/' + d.adresse?.replace(/\D/g,'')" target="_blank" style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#16a34a;text-decoration:none;background:#dcfce7;padding:3px 9px;border-radius:6px;font-weight:500"><i class="bi bi-whatsapp"></i><span x-text="d.adresse"></span></a></td>
                  <td>
                    <div style="display:flex;gap:6px">
                      <a :href="'admin.php?view=detail&id=' + d.id" style="width:30px;height:30px;border-radius:7px;border:1px solid #E2E8F0;background:white;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#6B7280;text-decoration:none;"><i class="bi bi-eye" style="font-size:13px"></i></a>
                      <button @click="deleteDemande(d.id)" style="width:30px;height:30px;border-radius:7px;border:1px solid #FEE2E2;background:white;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#F87171;"><i class="bi bi-trash" style="font-size:13px"></i></button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <script>
      function adminDashboard() {
        return {
          demandes: [],
          page: 1,
          perPage: 10,
          totalPages: 1,
          loadDemandes() {
            // URL CORRIGÉE pour pointer vers le routeur unique
            fetch(`admin.php?action=ajax_list&page=${this.page}&perPage=${this.perPage}`)
              .then(r => r.json())
              .then(data => { this.demandes = data.data; this.totalPages = data.totalPages; });
          },
          deleteDemande(id) {
            if (!confirm('Confirmer la suppression ?')) return;
            // URL CORRIGÉE pour pointer vers le routeur unique
            fetch(`admin.php?action=delete&id=${id}`, { method: 'POST' })
              .then(r => r.json())
              .then(data => { if (data.success) this.loadDemandes(); });
          },
          init() { this.loadDemandes(); }
        }
      }
      </script>

    <!-- ========================================================================= -->
    <!-- VUE : LISTE DES DEMANDES (anciennement demandes.php)                      -->
    <!-- ========================================================================= -->
    <?php elseif ($view === 'demandes'): 
      $limit  = 10;
      $page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
      $offset = ($page - 1) * $limit;
      $search     = trim($_GET['search'] ?? '');
      $filterType = $_GET['type'] ?? '';
      $filterPays = $_GET['pays'] ?? '';
      $where  = []; $params = [];
      if ($search) { $where[] = "(nom ILIKE :search OR prenom ILIKE :search OR email ILIKE :search)"; $params[':search'] = "%$search%"; }
      if ($filterType) { $where[] = "type_pret = :type"; $params[':type'] = $filterType; }
      if ($filterPays) { $where[] = "pays = :pays"; $params[':pays'] = $filterPays; }
      $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
      $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM demandes_financement $whereSql");
      $totalStmt->execute($params);
      $total = $totalStmt->fetchColumn();
      $pages = max(1, ceil($total / $limit));
      $stmt = $pdo->prepare("SELECT * FROM demandes_financement $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset");
      foreach ($params as $k => $v) $stmt->bindValue($k, $v);
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();
      $demandes = $stmt->fetchAll();
      $types = $pdo->query("SELECT DISTINCT type_pret FROM demandes_financement ORDER BY type_pret")->fetchAll(PDO::FETCH_COLUMN);
      $paysList = $pdo->query("SELECT DISTINCT pays FROM demandes_financement ORDER BY pays")->fetchAll(PDO::FETCH_COLUMN);
    ?>
      <div class="card">
        <div class="p-5 border-b border-gray-100">
          <form method="GET" action="admin.php" class="flex flex-wrap gap-3 mt-4">
            <input type="hidden" name="view" value="demandes">
            <div class="relative flex-1" style="min-width:200px">
              <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
              <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, prénom, email…" class="filter-input w-full" style="padding-left:34px">
            </div>
            <select name="type" class="filter-input" onchange="this.form.submit()">
              <option value="">Tous les types</option>
              <?php foreach($types as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= $filterType===$t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?>
            </select>
            <select name="pays" class="filter-input" onchange="this.form.submit()">
              <option value="">Tous les pays</option>
              <?php foreach($paysList as $p): ?><option value="<?= htmlspecialchars($p) ?>" <?= $filterPays===$p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="filter-input flex items-center gap-2 cursor-pointer" style="background:var(--navy);color:var(--gold);border-color:var(--navy);width:auto;"><i class="bi bi-search"></i> Rechercher</button>
            <?php if($search || $filterType || $filterPays): ?><a href="admin.php?view=demandes" class="filter-input flex items-center gap-2 text-decoration-none" style="width:auto;color:#6B7280;"><i class="bi bi-x-circle"></i> Réinitialiser</a><?php endif; ?>
          </form>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead><tr><th>#</th><th>Client</th><th>Email</th><th>Pays</th><th>Type de prêt</th><th>Montant</th><th>WhatsApp</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if ($demandes): foreach ($demandes as $d): ?>
              <tr>
                <td><span style="font-size:11px;color:#9CA3AF;font-weight:500">#<?= $d['id'] ?></span></td>
                <td><div style="display:flex;align-items:center;gap:9px"><div class="avatar" style="width:30px;height:30px;font-size:10px"><?= mb_strtoupper(mb_substr($d['prenom'],0,1).mb_substr($d['nom'],0,1)) ?></div><div><div style="font-weight:500;font-size:13px"><?= htmlspecialchars($d['nom'].' '.$d['prenom']) ?></div></div></div></td>
                <td><a href="mailto:<?= htmlspecialchars($d['email']) ?>" style="color:#6B7280;font-size:12px;text-decoration:none;"><?= htmlspecialchars($d['email']) ?></a></td>
                <td><span style="font-size:12px;font-weight:500;color:#374151"><?= htmlspecialchars(explode(' ', $d['pays'])[0] ?? $d['pays']) ?></span></td>
                <td><span class="badge-type <?= typeBadgeClass($d['type_pret'] ?? '') ?>"><?= htmlspecialchars($d['type_pret'] ?? '—') ?></span></td>
                <td><span style="font-family:'Playfair Display',serif;font-size:14px;font-weight:600;color:var(--navy)"><?= number_format($d['montant'], 0, ',', ' ') ?> €</span></td>
                <td><a href="https://wa.me/<?= preg_replace('/\D/', '', $d['adresse'] ?? '') ?>" target="_blank" style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:500;color:#16a34a;background:#dcfce7;padding:4px 10px;border-radius:7px;text-decoration:none;"><i class="bi bi-whatsapp"></i> <?= htmlspecialchars($d['adresse'] ?? '—') ?></a></td>
                <td>
                  <div style="display:flex;gap:5px;align-items:center">
                    <!-- LIENS CORRIGÉS -->
                    <a href="admin.php?view=detail&id=<?= $d['id'] ?>" style="width:30px;height:30px;border-radius:7px;border:1px solid #E2E8F0;background:white;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#6B7280;"><i class="bi bi-eye" style="font-size:13px"></i></a>
                    <a href="admin.php?action=delete&id=<?= $d['id'] ?>" onclick="return confirm('Confirmer la suppression ?')" style="width:30px;height:30px;border-radius:7px;border:1px solid #FEE2E2;background:white;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#F87171;"><i class="bi bi-trash" style="font-size:13px"></i></a>
                  </div>
                </td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="8" style="text-align:center;padding:48px;color:#9CA3AF">Aucune demande trouvée</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages > 1): ?>
        <div style="padding:16px 20px;border-top:1px solid rgba(4,44,83,0.06);display:flex;justify-content:flex-end;gap:5px;">
          <?php if ($page > 1): ?><a href="?view=demandes&page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>&pays=<?= urlencode($filterPays) ?>" class="page-btn"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
          <?php for ($i = max(1, $page-2); $i <= min($pages, $page+2); $i++): ?>
            <a href="?view=demandes&page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>&pays=<?= urlencode($filterPays) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($page < $pages): ?><a href="?view=demandes&page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>&pays=<?= urlencode($filterPays) ?>" class="page-btn"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

    <!-- ========================================================================= -->
    <!-- VUE : DÉTAIL D'UNE DEMANDE (anciennement demandes_detail.php)             -->
    <!-- ========================================================================= -->
    <?php elseif ($view === 'detail'): 
      $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
      $stmt = $pdo->prepare("SELECT * FROM demandes_financement WHERE id = ?");
      $stmt->execute([$id]);
      $demande = $stmt->fetch();
      if (!$demande) { echo '<div class="p-10 text-center">Demande introuvable. <a href="admin.php?view=demandes" class="text-blue-600">Retour</a></div>'; exit; }
      $waNumero = preg_replace('/\D+/', '', $demande['adresse'] ?? '');
      $waMessage = rawurlencode("Bonjour {$demande['prenom']} {$demande['nom']},\n\nNous avons bien reçu votre demande de {$demande['type_pret']} pour un montant de " . number_format($demande['montant'], 0, ',', ' ') . " €.\n\nUn conseiller Zorex Finance va vous recontacter très rapidement.");
      $waLink = "https://wa.me/{$waNumero}?text={$waMessage}";
    ?>
      <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;max-width:1100px">
        <div class="space-y-5">
          <div class="card p-6">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid rgba(4,44,83,0.06)">
              <div style="width:56px;height:56px;border-radius:50%;background:var(--navy);color:var(--gold);font-family:'Playfair Display',serif;font-size:20px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <?= mb_strtoupper(mb_substr($demande['prenom'],0,1).mb_substr($demande['nom'],0,1)) ?>
              </div>
              <div>
                <div style="font-family:'Playfair Display',serif;font-size:22px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($demande['prenom'].' '.$demande['nom']) ?></div>
                <div style="display:flex;align-items:center;gap:10px;margin-top:5px;flex-wrap:wrap">
                  <span class="badge-type <?= typeBadgeClass($demande['type_pret'] ?? '') ?>"><?= htmlspecialchars($demande['type_pret'] ?? '—') ?></span>
                  <span style="font-size:12px;color:#9CA3AF"><?= htmlspecialchars(explode(' ', $demande['pays'])[0] ?? $demande['pays']) ?></span>
                </div>
              </div>
            </div>
            <div class="info-row" style="display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid rgba(4,44,83,0.05);">
              <div style="width:36px;height:36px;border-radius:10px;background:#E6F1FB;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-envelope-fill" style="color:#185FA5"></i></div>
              <div><div style="font-size:11px;color:#9CA3AF;margin-bottom:3px">Adresse e-mail</div><a href="mailto:<?= htmlspecialchars($demande['email']) ?>" style="font-size:14px;font-weight:500;color:var(--navy);text-decoration:none"><?= htmlspecialchars($demande['email']) ?></a></div>
            </div>
            <div class="info-row" style="display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid rgba(4,44,83,0.05);">
              <div style="width:36px;height:36px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-whatsapp" style="color:#16a34a"></i></div>
              <div><div style="font-size:11px;color:#9CA3AF;margin-bottom:3px">Numéro WhatsApp</div><a href="<?= $waLink ?>" target="_blank" style="font-size:14px;font-weight:500;color:#16a34a;text-decoration:none;"><?= htmlspecialchars($demande['adresse'] ?? '—') ?></a></div>
            </div>
          </div>
          <div class="card p-6">
            <div style="margin-bottom:18px"><span class="eyebrow">Financement</span><div style="font-family:'Playfair Display',serif;font-size:16px;font-weight:600;color:var(--navy);margin-top:6px">Détails du prêt demandé</div></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
              <div style="background:#F8FAFC;border-radius:12px;padding:16px"><div style="font-size:11px;color:#9CA3AF;margin-bottom:6px">Type de prêt</div><span class="badge-type <?= typeBadgeClass($demande['type_pret'] ?? '') ?>" style="font-size:13px"><?= htmlspecialchars($demande['type_pret'] ?? '—') ?></span></div>
              <div style="background:#F8FAFC;border-radius:12px;padding:16px"><div style="font-size:11px;color:#9CA3AF;margin-bottom:6px">Montant sollicité</div><div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:600;color:var(--navy);line-height:1"><?= number_format($demande['montant'], 0, ',', ' ') ?> €</div></div>
            </div>
          </div>
        </div>
        <div class="space-y-5">
          <div class="card p-5">
            <div style="font-size:13px;font-weight:500;color:var(--navy);margin-bottom:14px;font-family:'Playfair Display',serif">Contacter le client</div>
            <div class="space-y-3">
              <a href="<?= $waLink ?>" target="_blank" class="btn-wa"><i class="bi bi-whatsapp" style="font-size:18px"></i> Envoyer sur WhatsApp</a>
              <a href="mailto:<?= htmlspecialchars($demande['email']) ?>" style="display:flex;align-items:center;justify-content:center;gap:8px;background:#E6F1FB;color:#0C447C;font-weight:500;font-size:13px;padding:11px 16px;border-radius:10px;text-decoration:none;"><i class="bi bi-envelope-fill"></i> Envoyer un e-mail</a>
            </div>
          </div>
          <div class="card p-5" style="border-color:#FEE2E2">
            <div style="font-size:12px;font-weight:600;color:#DC2626;text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px">Zone dangereuse</div>
            <!-- LIEN CORRIGÉ -->
            <a href="admin.php?action=delete&id=<?= $id ?>" onclick="return confirm('Supprimer définitivement la demande #<?= $id ?> ?')" class="btn-delete"><i class="bi bi-trash-fill" style="font-size:13px"></i> Supprimer cette demande</a>
          </div>
          <a href="admin.php?view=demandes" class="filter-input flex items-center justify-center gap-2 text-decoration-none w-full" style="color:#6B7280;cursor:pointer;"><i class="bi bi-arrow-left"></i> Retour à la liste</a>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('overlay').classList.add('show');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('show');
}
</script>
</body>
</html>