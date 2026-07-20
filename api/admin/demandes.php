<?php
session_start();
require __DIR__ . '/../db.php';

// Pagination
$limit  = 10;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Filtres
$search     = trim($_GET['search'] ?? '');
$filterType = $_GET['type'] ?? '';
$filterPays = $_GET['pays'] ?? '';

// Clause WHERE dynamique
$where  = [];
$params = [];

if ($search) {
    $where[]           = "(nom ILIKE :search OR prenom ILIKE :search OR email ILIKE :search)";
    $params[':search'] = "%$search%";
}
if ($filterType) {
    $where[]        = "type_pret = :type";
    $params[':type'] = $filterType;
}
if ($filterPays) {
    $where[]        = "pays = :pays";
    $params[':pays'] = $filterPays;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Total
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM demandes_financement $whereSql");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$pages = max(1, ceil($total / $limit));

// Demandes
$stmt = $pdo->prepare("SELECT * FROM demandes_financement $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$demandes = $stmt->fetchAll();

// Types distincts pour le filtre
$types = $pdo->query("SELECT DISTINCT type_pret FROM demandes_financement ORDER BY type_pret")->fetchAll(PDO::FETCH_COLUMN);
$paysList = $pdo->query("SELECT DISTINCT pays FROM demandes_financement ORDER BY pays")->fetchAll(PDO::FETCH_COLUMN);

// Helper badge couleur
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
  <title>Demandes | Zorex Fianance Admin</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <style>
    :root { --navy:#042C53; --navy-mid:#0C447C; --blue:#185FA5; --gold:#FAC775; --gold-dark:#EF9F27; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { font-family: 'DM Sans', sans-serif; background: #F1F4F9; color: #1a1a1a; }
    .font-display { font-family: 'Playfair Display', serif; }

    /* Sidebar */
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
    .nav-logout i { color:rgba(248,113,113,0.7) !important; }

    /* Topbar */
    .topbar { background:#fff; border-bottom:1px solid rgba(4,44,83,0.07); padding:14px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:40; }
    .page-title { font-family:'Playfair Display',serif; font-size:20px; font-weight:600; color:var(--navy); }

    /* Cards */
    .card { background:white; border:1px solid rgba(4,44,83,0.07); border-radius:16px; }

    /* Filters bar */
    .filter-input { background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:10px; padding:9px 14px; font-size:13px; font-family:'DM Sans',sans-serif; color:#374151; outline:none; transition:border-color .2s; }
    .filter-input:focus { border-color:var(--navy); }
    .filter-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:10px; font-size:13px; font-weight:500; border:none; cursor:pointer; transition:all .2s; text-decoration:none; }
    .filter-btn-primary { background:var(--navy); color:var(--gold); }
    .filter-btn-primary:hover { background:var(--navy-mid); }
    .filter-btn-ghost { background:#F8FAFC; color:#6B7280; border:1.5px solid #E2E8F0; }
    .filter-btn-ghost:hover { background:#F1F4F9; }

    /* Table */
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#9CA3AF; padding:11px 16px; text-align:left; border-bottom:1px solid rgba(4,44,83,0.07); background:#FAFBFC; }
    .data-table th:first-child { border-radius:0; }
    .data-table td { padding:13px 16px; font-size:13px; color:#374151; border-bottom:1px solid rgba(4,44,83,0.04); vertical-align:middle; }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tbody tr { transition:background .12s; }
    .data-table tbody tr:hover td { background:rgba(4,44,83,0.02); }

    /* Badges */
    .badge-type { display:inline-block; font-size:11px; font-weight:500; padding:3px 10px; border-radius:20px; white-space:nowrap; }
    .badge-personal { background:#E6F1FB; color:#0C447C; }
    .badge-immo     { background:#FAEEDA; color:#854F0B; }
    .badge-auto     { background:#E1F5EE; color:#0F6E56; }
    .badge-pro      { background:#EEEDFE; color:#534AB7; }
    .badge-etudiant { background:#FDE8F3; color:#9C2E6B; }

    /* Avatar */
    .avatar { width:32px; height:32px; border-radius:50%; background:var(--navy); color:var(--gold); font-size:11px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

    /* Pagination */
    .page-btn { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:500; border:1px solid #E2E8F0; background:white; color:#374151; text-decoration:none; transition:all .15s; }
    .page-btn:hover { background:var(--navy); color:var(--gold); border-color:var(--navy); }
    .page-btn.active { background:var(--navy); color:var(--gold); border-color:var(--navy); }
    .page-btn.disabled { opacity:.4; pointer-events:none; }

    /* Mobile */
    .mobile-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:30; }
    .mobile-overlay.show { display:block; }
    @media (max-width:768px) {
      .sidebar { position:fixed; top:0; left:0; z-index:40; height:100vh; transform:translateX(-100%); transition:transform .3s; }
      .sidebar.open { transform:translateX(0); }
      .data-table th:nth-child(3),.data-table td:nth-child(3),
      .data-table th:nth-child(4),.data-table td:nth-child(4) { display:none; }
    }

    /* Eyebrow */
    .eyebrow { font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:#BA7517; background:rgba(250,199,117,0.12); padding:3px 10px; border-radius:20px; display:inline-block; }

    /* Export btn */
    .export-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:10px; font-size:12px; font-weight:500; background:#EAF3DE; color:#3B6D11; border:none; cursor:pointer; text-decoration:none; transition:background .15s; }
    .export-btn:hover { background:#d4ebbe; }

    /* Stats mini */
    .mini-stat { background:white; border:1px solid rgba(4,44,83,0.07); border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:12px; }
    .mini-stat-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
    .mini-stat-num { font-family:'Playfair Display',serif; font-size:20px; font-weight:600; color:var(--navy); line-height:1; }
    .mini-stat-label { font-size:11px; color:#9CA3AF; margin-top:2px; }

    select.filter-input { appearance:none; -webkit-appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%236B7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; padding-right:32px; }
  </style>
</head>

<body class="flex min-h-screen">

<div class="mobile-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo flex items-center gap-3">
    <div style="width:34px;height:34px;border-radius:9px;background:rgba(250,199,117,0.12);border:1px solid rgba(250,199,117,0.2);display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#FAC775" stroke-width="2" stroke-linecap="round">
        <path d="M12 2L4 7v5c0 5 3.5 9.7 8 11 4.5-1.3 8-6 8-11V7L12 2z"/>
      </svg>
    </div>
    <div>
      <div class="sidebar-brand">Zorex Fianance</div>
      <div class="sidebar-tagline">Administration</div>
    </div>
  </div>

  <nav class="flex-1 pt-4 pb-4">
    <div class="nav-section">Principal</div>
    <a href="index.php" class="nav-item"><i class="bi bi-grid-1x2-fill"></i> Tableau de bord</a>
    <a href="demandes.php" class="nav-item active"><i class="bi bi-file-earmark-text-fill"></i> Demandes</a>
    <!-- <div class="nav-section mt-2">Gestion</div>
    <a href="clients.php" class="nav-item"><i class="bi bi-people-fill"></i> Clients</a>
    <a href="stats.php"   class="nav-item"><i class="bi bi-bar-chart-fill"></i> Statistiques</a>
    <a href="settings.php" class="nav-item"><i class="bi bi-gear-fill"></i> Paramètres</a> -->
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


<!-- ===== MAIN ===== -->
<div class="flex-1 flex flex-col overflow-hidden">

  <!-- Topbar -->
  <div class="topbar">
    <div class="flex items-center gap-4">
      <button onclick="openSidebar()" class="md:hidden w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200">
        <i class="bi bi-list text-xl text-gray-600"></i>
      </button>
      <div>
        <div class="page-title">Demandes de financement</div>
        <div style="font-size:11px;color:#9CA3AF"><?= number_format($total) ?> demande<?= $total > 1 ? 's' : '' ?> au total</div>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <button class="relative w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50">
        <i class="bi bi-bell text-gray-500"></i>
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
      </button>
      <a href="/index.html" target="_blank" class="hidden md:flex items-center gap-2 px-3 h-9 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition" style="text-decoration:none">
        <i class="bi bi-box-arrow-up-right text-xs"></i> Voir le site
      </a>
      <div class="avatar">AD</div>
    </div>
  </div>


  <!-- Content -->
  <div class="flex-1 overflow-y-auto p-5 md:p-7 space-y-5">

    <!-- Mini stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="mini-stat">
        <div class="mini-stat-icon" style="background:#E6F1FB"><i class="bi bi-files" style="color:#185FA5"></i></div>
        <div>
          <div class="mini-stat-num"><?= number_format($total) ?></div>
          <div class="mini-stat-label">Total demandes</div>
        </div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-icon" style="background:#EAF3DE"><i class="bi bi-calendar-check" style="color:#3B6D11"></i></div>
        <div>
          <?php
$todayCount = $pdo->query("SELECT COUNT(*) FROM demandes_financement WHERE date_demande::date = CURRENT_DATE")->fetchColumn();          ?>
          <div class="mini-stat-num"><?= $todayCount ?></div>
          <div class="mini-stat-label">Aujourd'hui</div>
        </div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-icon" style="background:#FAEEDA"><i class="bi bi-currency-euro" style="color:#854F0B"></i></div>
        <div>
          <?php
            $totalMontant = $pdo->query("SELECT SUM(montant) FROM demandes_financement")->fetchColumn();
            $formatted = $totalMontant >= 1000000
              ? number_format($totalMontant/1000000, 1).'M'
              : number_format($totalMontant/1000, 0).'K';
          ?>
          <div class="mini-stat-num">€<?= $formatted ?></div>
          <div class="mini-stat-label">Montant total</div>
        </div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-icon" style="background:#EEEDFE"><i class="bi bi-funnel-fill" style="color:#534AB7"></i></div>
        <div>
          <div class="mini-stat-num"><?= $search || $filterType || $filterPays ? number_format($total) : '—' ?></div>
          <div class="mini-stat-label"><?= $search || $filterType || $filterPays ? 'Résultats filtrés' : 'Filtre actif' ?></div>
        </div>
      </div>
    </div>


    <!-- Table card -->
    <div class="card">

      <!-- Header + filters -->
      <div class="p-5 border-b border-gray-100">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <span class="eyebrow">Gestion</span>
            <div class="font-display text-[17px] font-semibold mt-1" style="color:var(--navy)">Liste des demandes</div>
          </div>
          <!-- Export -->
          <a href="export_demandes.php?search=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>&pays=<?= urlencode($filterPays) ?>"
             class="export-btn" title="Exporter en CSV">
            <i class="bi bi-download"></i> Exporter CSV
          </a>
        </div>

        <!-- Filters form -->
        <form method="GET" class="flex flex-wrap gap-3 mt-4" id="filterForm">
          <!-- Search -->
          <div class="relative flex-1" style="min-width:200px">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Nom, prénom, email…"
                   class="filter-input w-full" style="padding-left:34px">
          </div>

          <!-- Type -->
          <select name="type" class="filter-input" onchange="this.form.submit()">
            <option value="">Tous les types</option>
            <?php foreach($types as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>" <?= $filterType===$t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>

          <!-- Pays -->
          <select name="pays" class="filter-input" onchange="this.form.submit()">
            <option value="">Tous les pays</option>
            <?php foreach($paysList as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>" <?= $filterPays===$p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
          </select>

          <button type="submit" class="filter-btn filter-btn-primary">
            <i class="bi bi-search"></i> Rechercher
          </button>

          <?php if($search || $filterType || $filterPays): ?>
            <a href="demandes.php" class="filter-btn filter-btn-ghost">
              <i class="bi bi-x-circle"></i> Réinitialiser
            </a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Table -->
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width:50px">#</th>
              <th>Client</th>
              <th>Email</th>
              <th>Pays</th>
              <th>Type de prêt</th>
              <th>Montant</th>
              <th>WhatsApp</th>
              <th style="width:110px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($demandes): ?>
              <?php foreach ($demandes as $d): ?>
              <tr>
                <td>
                  <span style="font-size:11px;color:#9CA3AF;font-weight:500">#<?= $d['id'] ?></span>
                </td>
                <td>
                  <div style="display:flex;align-items:center;gap:9px">
                    <div class="avatar" style="width:30px;height:30px;font-size:10px">
                      <?= mb_strtoupper(mb_substr($d['prenom'],0,1).mb_substr($d['nom'],0,1)) ?>
                    </div>
                    <div>
                      <div style="font-weight:500;font-size:13px"><?= htmlspecialchars($d['nom'].' '.$d['prenom']) ?></div>
                      <div style="font-size:11px;color:#9CA3AF;margin-top:1px"><?= htmlspecialchars($d['codepostal'] ?? '') ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <a href="mailto:<?= htmlspecialchars($d['email']) ?>"
                     style="color:#6B7280;font-size:12px;text-decoration:none;hover:color:var(--navy)">
                    <?= htmlspecialchars($d['email']) ?>
                  </a>
                </td>
                <td>
                  <span style="font-size:12px;font-weight:500;color:#374151">
                    <?= htmlspecialchars(explode(' ', $d['pays'])[0] ?? $d['pays']) ?>
                  </span>
                </td>
                <td>
                  <span class="badge-type <?= typeBadgeClass($d['type_pret'] ?? '') ?>">
                    <?= htmlspecialchars($d['type_pret'] ?? '—') ?>
                  </span>
                </td>
                <td>
                  <span style="font-family:'Playfair Display',serif;font-size:14px;font-weight:600;color:var(--navy)">
                    <?= number_format($d['montant'], 0, ',', ' ') ?> €
                  </span>
                </td>
                <td>
                  <?php $wa = preg_replace('/\D/', '', $d['adresse'] ?? ''); ?>
                  <a href="https://wa.me/<?= $wa ?>" target="_blank"
                     style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:500;color:#16a34a;background:#dcfce7;padding:4px 10px;border-radius:7px;text-decoration:none;white-space:nowrap">
                    <i class="bi bi-whatsapp"></i>
                    <?= htmlspecialchars($d['adresse'] ?? '—') ?>
                  </a>
                </td>
                <td>
                  <div style="display:flex;gap:5px;align-items:center">
                    <a href="demandes_detail.php?id=<?= $d['id'] ?>"
                       style="width:30px;height:30px;border-radius:7px;border:1px solid #E2E8F0;background:white;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#6B7280;transition:all .15s"
                       title="Voir le détail"
                       onmouseover="this.style.background='#042C53';this.style.color='#FAC775';this.style.borderColor='#042C53'"
                       onmouseout="this.style.background='white';this.style.color='#6B7280';this.style.borderColor='#E2E8F0'">
                      <i class="bi bi-eye" style="font-size:13px"></i>
                    </a>
                    <a href="demandes_delete.php?id=<?= $d['id'] ?>&<?= http_build_query(['search'=>$search,'type'=>$filterType,'pays'=>$filterPays,'page'=>$page]) ?>"
                       onclick="return confirm('Confirmer la suppression de la demande #<?= $d['id'] ?> ?')"
                       style="width:30px;height:30px;border-radius:7px;border:1px solid #FEE2E2;background:white;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#F87171;transition:all .15s"
                       title="Supprimer"
                       onmouseover="this.style.background='#FEE2E2'"
                       onmouseout="this.style.background='white'">
                      <i class="bi bi-trash" style="font-size:13px"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" style="text-align:center;padding:48px;color:#9CA3AF">
                  <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:10px;color:#D1D5DB"></i>
                  <div style="font-size:14px;font-weight:500;margin-bottom:4px">Aucune demande trouvée</div>
                  <div style="font-size:12px">
                    <?php if($search || $filterType || $filterPays): ?>
                      Essayez de modifier vos filtres de recherche.
                    <?php else: ?>
                      Les demandes soumises apparaîtront ici.
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div style="padding:16px 20px;border-top:1px solid rgba(4,44,83,0.06);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div style="font-size:12px;color:#9CA3AF">
          Affichage de <strong style="color:#374151"><?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?></strong>
          sur <strong style="color:#374151"><?= number_format($total) ?></strong> demandes
        </div>
        <div style="display:flex;gap:5px;align-items:center">
          <!-- Prev -->
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>&pays=<?= urlencode($filterPays) ?>" class="page-btn" title="Page précédente">
              <i class="bi bi-chevron-left" style="font-size:11px"></i>
            </a>
          <?php else: ?>
            <span class="page-btn disabled"><i class="bi bi-chevron-left" style="font-size:11px"></i></span>
          <?php endif; ?>

          <!-- Numbered pages (max 7 visible) -->
          <?php
            $range = 3;
            $start = max(1, $page - $range);
            $end   = min($pages, $page + $range);
            if ($start > 1) echo '<span style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#9CA3AF">…</span>';
            for ($i = $start; $i <= $end; $i++):
          ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>&pays=<?= urlencode($filterPays) ?>"
               class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor;
            if ($end < $pages) echo '<span style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#9CA3AF">…</span>';
          ?>

          <!-- Next -->
          <?php if ($page < $pages): ?>
            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>&pays=<?= urlencode($filterPays) ?>" class="page-btn" title="Page suivante">
              <i class="bi bi-chevron-right" style="font-size:11px"></i>
            </a>
          <?php else: ?>
            <span class="page-btn disabled"><i class="bi bi-chevron-right" style="font-size:11px"></i></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div><!-- /content -->
</div><!-- /main -->


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