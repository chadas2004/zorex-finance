<?php
require __DIR__ . '/../db.php';

// Stats globales par type
$stats = $pdo->query("
    SELECT type_pret, COUNT(*) AS total, SUM(montant) AS montant_total
    FROM demandes_financement
    GROUP BY type_pret
")->fetchAll();

// KPIs globaux
$kpis = $pdo->query("
    SELECT
        COUNT(*) AS total_demandes,
        SUM(montant) AS montant_total,
        COUNT(DISTINCT pays) AS nb_pays,
        AVG(montant) AS montant_moyen
    FROM demandes_financement
")->fetch();

// Dernières demandes (5)
$recent = $pdo->query("
    SELECT * FROM demandes_financement ORDER BY id DESC LIMIT 5
")->fetchAll();

// Demandes par pays
$byPays = $pdo->query("
    SELECT pays, COUNT(*) AS total FROM demandes_financement GROUP BY pays ORDER BY total DESC
")->fetchAll();

// Évolution mensuelle
$monthly = $pdo->query("
    SELECT TO_CHAR(date_demande, 'YYYY-MM') AS mois, COUNT(*) AS total
    FROM demandes_financement
    GROUP BY mois ORDER BY mois ASC LIMIT 12
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" x-data="adminDashboard()" x-init="init()">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin | Zorex Fianance</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <style>
    :root {
      --navy: #042C53;
      --navy-mid: #0C447C;
      --blue: #185FA5;
      --gold: #FAC775;
      --gold-dark: #EF9F27;
      --gold-deeper: #BA7517;
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { font-family: 'DM Sans', sans-serif; background: #F1F4F9; color: #1a1a1a; }
    .font-display { font-family: 'Playfair Display', serif; }

    /* Sidebar */
    .sidebar {
      width: 240px; min-width: 240px;
      background: var(--navy);
      min-height: 100vh;
      position: sticky; top: 0;
      display: flex; flex-direction: column;
    }
    .sidebar-logo {
      padding: 22px 20px 18px;
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .sidebar-brand { font-family: 'Playfair Display', serif; font-size: 16px; font-weight: 600; color: var(--gold); }
    .sidebar-tagline { font-size: 10px; color: rgba(255,255,255,0.35); margin-top: 2px; }

    .nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 18px; margin: 2px 10px;
      border-radius: 10px;
      font-size: 13px; font-weight: 400;
      color: rgba(255,255,255,0.55);
      text-decoration: none;
      transition: all 0.18s;
    }
    .nav-item:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.9); }
    .nav-item.active { background: rgba(250,199,117,0.12); color: var(--gold); }
    .nav-item.active i { color: var(--gold); }
    .nav-item i { font-size: 15px; width: 18px; text-align: center; color: rgba(255,255,255,0.35); }
    .nav-section { font-size: 10px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.2); padding: 16px 18px 6px; }
    .nav-logout { color: rgba(248,113,113,0.7) !important; }
    .nav-logout:hover { background: rgba(248,113,113,0.08) !important; color: #f87171 !important; }
    .nav-logout i { color: rgba(248,113,113,0.7) !important; }

    /* Top bar */
    .topbar {
      background: #ffffff;
      border-bottom: 1px solid rgba(4,44,83,0.07);
      padding: 14px 24px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 40;
    }
    .page-title { font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 600; color: var(--navy); }

    /* KPI Cards */
    .kpi-card {
      background: white;
      border: 1px solid rgba(4,44,83,0.07);
      border-radius: 16px;
      padding: 20px 22px;
      transition: all 0.2s;
    }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(4,44,83,0.08); }
    .kpi-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 19px; }
    .kpi-num { font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 600; color: var(--navy); line-height: 1; margin: 6px 0 4px; }
    .kpi-label { font-size: 12px; color: #6B7280; }
    .kpi-badge { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 500; padding: 2px 7px; border-radius: 20px; margin-top: 6px; }
    .badge-up { background: #EAF3DE; color: #3B6D11; }
    .badge-info { background: #E6F1FB; color: #0C447C; }

    /* Chart cards */
    .chart-card {
      background: white;
      border: 1px solid rgba(4,44,83,0.07);
      border-radius: 16px;
      padding: 22px;
    }
    .chart-title { font-size: 14px; font-weight: 500; color: var(--navy); margin-bottom: 4px; }
    .chart-subtitle { font-size: 11px; color: #9CA3AF; margin-bottom: 16px; }

    /* Table */
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th {
      font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
      color: #9CA3AF; padding: 10px 14px; text-align: left;
      border-bottom: 1px solid rgba(4,44,83,0.07);
    }
    .data-table td {
      padding: 12px 14px; font-size: 13px; color: #374151;
      border-bottom: 1px solid rgba(4,44,83,0.04);
    }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: rgba(4,44,83,0.02); }

    /* Status badges */
    .badge-type {
      display: inline-block; font-size: 11px; font-weight: 500;
      padding: 3px 10px; border-radius: 20px;
    }
    .badge-personal  { background: #E6F1FB; color: #0C447C; }
    .badge-immo      { background: #FAEEDA; color: #854F0B; }
    .badge-auto      { background: #E1F5EE; color: #0F6E56; }
    .badge-pro       { background: #EEEDFE; color: #534AB7; }
    .badge-etudiant  { background: #FDE8F3; color: #9C2E6B; }

    /* Search / filter input */
    .filter-input {
      background: #F8FAFC;
      border: 1.5px solid #E2E8F0;
      border-radius: 10px;
      padding: 9px 14px;
      font-size: 13px;
      font-family: 'DM Sans', sans-serif;
      color: #374151;
      outline: none;
      transition: border-color 0.2s;
    }
    .filter-input:focus { border-color: var(--navy); }

    /* Pagination btn */
    .page-btn {
      width: 32px; height: 32px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 500; cursor: pointer;
      transition: all 0.15s;
      border: 1px solid #E2E8F0;
      background: white; color: #374151;
    }
    .page-btn:hover:not(:disabled) { background: var(--navy); color: var(--gold); border-color: var(--navy); }
    .page-btn:disabled { opacity: 0.4; cursor: default; }
    .page-btn.active { background: var(--navy); color: var(--gold); border-color: var(--navy); }

    /* Progress bar */
    .prog { height: 6px; border-radius: 6px; background: #E2E8F0; overflow: hidden; }
    .prog-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, var(--navy), var(--blue)); transition: width 0.6s ease; }

    /* Mobile sidebar overlay */
    .mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 30; }
    .mobile-overlay.show { display: block; }
    @media (max-width: 768px) {
      .sidebar { position: fixed; top: 0; left: 0; z-index: 40; transform: translateX(-100%); transition: transform 0.3s; }
      .sidebar.open { transform: translateX(0); }
    }

    /* Avatar initials */
    .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--navy); color: var(--gold); font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; }

    /* Section eyebrow */
    .eyebrow { font-size: 10px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--gold-deeper); background: rgba(250,199,117,0.12); padding: 3px 10px; border-radius: 20px; display: inline-block; margin-bottom: 8px; }
  </style>
</head>

<body class="flex min-h-screen">

<!-- Mobile overlay -->
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
    <a href="index.php" class="nav-item active">
      <i class="bi bi-grid-1x2-fill"></i> Tableau de bord
    </a>
    <a href="demandes.php" class="nav-item">
      <i class="bi bi-file-earmark-text-fill"></i> Demandes
    </a>

    <!-- <div class="nav-section mt-2">Gestion</div>
    <a href="clients.php" class="nav-item"> -->
      <!-- <i class="bi bi-people-fill"></i> Clients
    </a>
    <a href="stats.php" class="nav-item">
      <i class="bi bi-bar-chart-fill"></i> Statistiques
    </a>
    <a href="settings.php" class="nav-item">
      <i class="bi bi-gear-fill"></i> Paramètres -->
    </a>

    <div class="nav-section mt-2">Compte</div>
    <a href="logout.php" class="nav-item nav-logout">
      <i class="bi bi-box-arrow-right"></i> Déconnexion
    </a>
  </nav>

  <!-- Sidebar footer -->
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


<!-- ===== MAIN CONTENT ===== -->
<div class="flex-1 flex flex-col overflow-hidden">

  <!-- Top bar -->
  <div class="topbar">
    <div class="flex items-center gap-4">
      <!-- Mobile burger -->
      <button onclick="openSidebar()" class="md:hidden w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200">
        <i class="bi bi-list text-xl text-gray-600"></i>
      </button>
      <div>
        <div class="page-title">Tableau de bord</div>
        <div style="font-size:11px;color:#9CA3AF"><?= date('l d F Y') ?></div>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <!-- Notifications -->
      <button class="relative w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50">
        <i class="bi bi-bell text-gray-500"></i>
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
      </button>
      <!-- Quick link -->
      <a href="/index.html" target="_blank" class="hidden md:flex items-center gap-2 px-3 h-9 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition" style="text-decoration:none">
        <i class="bi bi-box-arrow-up-right text-xs"></i> Voir le site
      </a>
      <div class="avatar">AD</div>
    </div>
  </div>


  <!-- Page content -->
  <div class="flex-1 overflow-y-auto p-5 md:p-7 space-y-7">

    <!-- KPI Grid -->
    <div>
      <div class="eyebrow">Vue d'ensemble</div>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-2">

        <div class="kpi-card">
          <div class="kpi-icon" style="background:#E6F1FB">
            <i class="bi bi-file-earmark-check-fill" style="color:#185FA5"></i>
          </div>
          <div class="kpi-num"><?= number_format($kpis['total_demandes']) ?></div>
          <div class="kpi-label">Demandes totales</div>
          <span class="kpi-badge badge-up"><i class="bi bi-arrow-up-short"></i> Ce mois</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon" style="background:#FAEEDA">
            <i class="bi bi-currency-euro" style="color:#854F0B"></i>
          </div>
          <div class="kpi-num">€<?= $kpis['montant_total'] >= 1000000 ? number_format($kpis['montant_total']/1000000, 1).'M' : number_format($kpis['montant_total']/1000, 0).'K' ?></div>
          <div class="kpi-label">Montant total sollicité</div>
          <span class="kpi-badge badge-info"><i class="bi bi-graph-up"></i> Cumulé</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon" style="background:#E1F5EE">
            <i class="bi bi-globe2" style="color:#0F6E56"></i>
          </div>
          <div class="kpi-num"><?= $kpis['nb_pays'] ?></div>
          <div class="kpi-label">Pays représentés</div>
          <span class="kpi-badge badge-info">DE · AT · CH</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon" style="background:#EEEDFE">
            <i class="bi bi-calculator-fill" style="color:#534AB7"></i>
          </div>
          <div class="kpi-num">€<?= number_format($kpis['montant_moyen']/1000, 0) ?>K</div>
          <div class="kpi-label">Montant moyen</div>
          <span class="kpi-badge badge-up">Par demande</span>
        </div>

      </div>
    </div>


    <!-- Charts row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Bar chart - demandes par type -->
      <div class="chart-card lg:col-span-2">
        <div class="chart-title">Demandes par type de prêt</div>
        <div class="chart-subtitle">Nombre de demandes enregistrées par catégorie</div>
        <div style="position:relative;height:220px">
          <canvas id="chartType"></canvas>
        </div>
      </div>

      <!-- Donut - répartition pays -->
      <div class="chart-card">
        <div class="chart-title">Répartition par pays</div>
        <div class="chart-subtitle">Distribution géographique</div>
        <div style="position:relative;height:150px;margin-bottom:12px">
          <canvas id="chartPays"></canvas>
        </div>
        <!-- Legend -->
        <?php foreach($byPays as $p): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <div style="display:flex;align-items:center;gap:7px;font-size:12px;color:#374151">
            <span style="width:8px;height:8px;border-radius:50%;background:#042C53;display:inline-block"></span>
            <?= htmlspecialchars($p['pays']) ?>
          </div>
          <div style="font-size:12px;font-weight:500;color:#042C53"><?= $p['total'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>

    </div>


    <!-- Trend chart -->
    <div class="chart-card">
      <div class="flex items-center justify-between mb-1">
        <div>
          <div class="chart-title">Évolution mensuelle des demandes</div>
          <div class="chart-subtitle">12 derniers mois</div>
        </div>
      </div>
      <div style="position:relative;height:180px">
        <canvas id="chartMonthly"></canvas>
      </div>
    </div>


    <!-- Demandes récentes + table -->
    <div class="chart-card">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
        <div>
          <div class="eyebrow">Dernières demandes</div>
          <div class="chart-title mt-1">Toutes les demandes de financement</div>
        </div>
        <div class="flex flex-wrap gap-2">
          <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" x-model="search" @input.debounce.300ms="page=1;loadDemandes()" placeholder="Rechercher..." class="filter-input pl-8" style="width:200px">
          </div>
          <select x-model="filterType" @change="page=1;loadDemandes()" class="filter-input">
            <option value="">Tous les types</option>
            <option>Prêt Personnel</option>
            <option>Prêt Immobilier</option>
            <option>Crédit Auto</option>
            <option>Prêt Professionnel</option>
            <option>Prêt Étudiant</option>
          </select>
          <select x-model="filterPays" @change="page=1;loadDemandes()" class="filter-input">
            <option value="">Tous les pays</option>
            <option>Allemagne +49</option>
            <option>Autriche +43</option>
            <option>Suisse +41</option>
          </select>
          <a href="demandes.php" class="filter-input flex items-center gap-2 no-underline text-gray-600 hover:bg-gray-100 transition">
            <i class="bi bi-arrow-right text-xs"></i> Tout voir
          </a>
        </div>
      </div>

      <!-- Table -->
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nom / Prénom</th>
              <th>Email</th>
              <th>Pays</th>
              <th>Type de prêt</th>
              <th>Montant</th>
              <th>WhatsApp</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- Loading state -->
            <template x-if="demandes.length === 0">
              <tr>
                <td colspan="8" style="text-align:center;padding:32px;color:#9CA3AF">
                  <i class="bi bi-inbox text-3xl block mb-2"></i>
                  Aucune demande trouvée
                </td>
              </tr>
            </template>

            <template x-for="d in demandes" :key="d.id">
              <tr>
                <td>
                  <span style="font-size:11px;color:#9CA3AF;font-weight:500">#<span x-text="d.id"></span></span>
                </td>
                <td>
                  <div style="display:flex;align-items:center;gap:9px">
                    <div class="avatar" style="width:28px;height:28px;font-size:10px" x-text="(d.prenom?.[0]||'') + (d.nom?.[0]||'')"></div>
                    <div>
                      <div style="font-weight:500;font-size:13px" x-text="d.prenom + ' ' + d.nom"></div>
                    </div>
                  </div>
                </td>
                <td style="color:#6B7280;font-size:12px" x-text="d.email"></td>
                <td>
                  <span style="font-size:12px;color:#374151" x-text="d.pays?.split(' ')[0] || d.pays"></span>
                </td>
                <td>
                  <span class="badge-type"
                        :class="{
                          'badge-personal': d.type_pret?.includes('Personnel'),
                          'badge-immo':     d.type_pret?.includes('Immobilier'),
                          'badge-auto':     d.type_pret?.includes('Auto'),
                          'badge-pro':      d.type_pret?.includes('Professionnel'),
                          'badge-etudiant': d.type_pret?.includes('Étudiant')
                        }"
                        x-text="d.type_pret">
                  </span>
                </td>
                <td>
                  <span style="font-family:'Playfair Display',serif;font-size:14px;font-weight:600;color:#042C53" x-text="Number(d.montant).toLocaleString('fr-FR') + ' €'"></span>
                </td>
                <td>
                  <a :href="'https://wa.me/' + d.adresse?.replace(/\D/g,'')" target="_blank"
                     style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#16a34a;text-decoration:none;background:#dcfce7;padding:3px 9px;border-radius:6px;font-weight:500">
                    <i class="bi bi-whatsapp"></i>
                    <span x-text="d.adresse"></span>
                  </a>
                </td>
                <td>
                  <div style="display:flex;gap:6px">
                    <button @click="viewDemande(d)"
                            style="width:30px;height:30px;border-radius:7px;border:1px solid #E2E8F0;background:white;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#6B7280;transition:all .15s"
                            onmouseover="this.style.background='#042C53';this.style.color='#FAC775'"
                            onmouseout="this.style.background='white';this.style.color='#6B7280'">
                      <i class="bi bi-eye" style="font-size:13px"></i>
                    </button>
                    <button @click="deleteDemande(d.id)"
                            style="width:30px;height:30px;border-radius:7px;border:1px solid #FEE2E2;background:white;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#F87171;transition:all .15s"
                            onmouseover="this.style.background='#FEE2E2'"
                            onmouseout="this.style.background='white'">
                      <i class="bi bi-trash" style="font-size:13px"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;padding-top:14px;border-top:1px solid rgba(4,44,83,0.06)">
        <div style="font-size:12px;color:#9CA3AF">
          Page <span x-text="page"></span> sur <span x-text="totalPages"></span>
        </div>
        <div style="display:flex;gap:6px">
          <button @click="prevPage" :disabled="page <= 1" class="page-btn">
            <i class="bi bi-chevron-left"></i>
          </button>
          <template x-for="p in visiblePages" :key="p">
            <button @click="goToPage(p)" :class="p === page ? 'active' : ''" class="page-btn" x-text="p"></button>
          </template>
          <button @click="nextPage" :disabled="page >= totalPages" class="page-btn">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

  </div><!-- /page content -->
</div><!-- /main -->


<!-- ===== MODAL detail demande ===== -->
<div x-show="modal" x-cloak style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:100;display:flex;align-items:center;justify-content:center;padding:16px"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
  <div @click.outside="modal=false" style="background:white;border-radius:18px;max-width:520px;width:100%;padding:28px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:600;color:#042C53">Détail de la demande</div>
      <button @click="modal=false" style="width:32px;height:32px;border-radius:8px;border:1px solid #E2E8F0;background:none;cursor:pointer;font-size:16px;color:#6B7280">×</button>
    </div>
    <template x-if="selectedDemande">
      <div style="space-y:12px">
        <div style="background:#F8FAFC;border-radius:12px;padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div><div style="font-size:11px;color:#9CA3AF;margin-bottom:3px">Prénom / Nom</div><div style="font-weight:500;font-size:13px" x-text="selectedDemande?.prenom + ' ' + selectedDemande?.nom"></div></div>
          <div><div style="font-size:11px;color:#9CA3AF;margin-bottom:3px">Email</div><div style="font-size:12px;color:#374151" x-text="selectedDemande?.email"></div></div>
          <div><div style="font-size:11px;color:#9CA3AF;margin-bottom:3px">Pays</div><div style="font-size:13px" x-text="selectedDemande?.pays"></div></div>
          <div><div style="font-size:11px;color:#9CA3AF;margin-bottom:3px">Type de prêt</div><div style="font-size:13px" x-text="selectedDemande?.type_pret"></div></div>
          <div><div style="font-size:11px;color:#9CA3AF;margin-bottom:3px">Montant</div><div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:600;color:#042C53" x-text="Number(selectedDemande?.montant).toLocaleString('fr-FR') + ' €'"></div></div>
          <div><div style="font-size:11px;color:#9CA3AF;margin-bottom:3px">WhatsApp</div><div style="font-size:13px" x-text="selectedDemande?.adresse"></div></div>
          <div><div style="font-size:11px;color:#9CA3AF;margin-bottom:3px">Code postal</div><div style="font-size:13px" x-text="selectedDemande?.codepostal"></div></div>
        </div>
        <a :href="'https://wa.me/' + selectedDemande?.adresse?.replace(/\D/g,'')" target="_blank"
           style="display:flex;align-items:center;justify-content:center;gap:8px;background:#16a34a;color:white;padding:12px;border-radius:12px;text-decoration:none;font-weight:600;font-size:14px;margin-top:14px">
          <i class="bi bi-whatsapp"></i> Contacter sur WhatsApp
        </a>
      </div>
    </template>
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

function adminDashboard() {
  return {
    demandes: [],
    search: '',
    filterType: '',
    filterPays: '',
    page: 1,
    perPage: 10,
    totalPages: 1,
    modal: false,
    selectedDemande: null,

    get visiblePages() {
      const pages = [];
      const start = Math.max(1, this.page - 2);
      const end   = Math.min(this.totalPages, start + 4);
      for (let i = start; i <= end; i++) pages.push(i);
      return pages;
    },

    loadDemandes() {
      fetch(`ajax_demandes.php?page=${this.page}&perPage=${this.perPage}&search=${encodeURIComponent(this.search)}&type=${encodeURIComponent(this.filterType)}&pays=${encodeURIComponent(this.filterPays)}`)
        .then(r => r.json())
        .then(data => {
          this.demandes = data.data;
          this.totalPages = data.totalPages;
        });
    },

    nextPage() { if (this.page < this.totalPages) { this.page++; this.loadDemandes(); } },
    prevPage() { if (this.page > 1) { this.page--; this.loadDemandes(); } },
    goToPage(p) { this.page = p; this.loadDemandes(); },

    viewDemande(d) { this.selectedDemande = d; this.modal = true; },

    deleteDemande(id) {
      if (!confirm('Confirmer la suppression de cette demande ?')) return;
      fetch(`delete_demande.php?id=${id}`, { method: 'POST' })
        .then(r => r.json())
        .then(data => { if (data.success) this.loadDemandes(); });
    },

    initCharts() {
      const palette = ['#042C53','#0C447C','#185FA5','#378ADD','#EF9F27'];

      // Bar - types
      const labels   = [<?php foreach($stats as $s) echo "'".addslashes($s['type_pret'])."',"; ?>];
      const totals   = [<?php foreach($stats as $s) echo (int)$s['total'].','; ?>];
      const montants = [<?php foreach($stats as $s) echo (float)$s['montant_total'].','; ?>];

      new Chart(document.getElementById('chartType'), {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Demandes',
            data: totals,
            backgroundColor: palette,
            borderRadius: 8,
            borderSkipped: false
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false }, ticks: { font: { family: 'DM Sans', size: 11 }, color: '#9CA3AF' } },
            y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { family: 'DM Sans', size: 11 }, color: '#9CA3AF' } }
          }
        }
      });

      // Donut - pays
      const paysLabels = [<?php foreach($byPays as $p) echo "'".addslashes($p['pays'])."',"; ?>];
      const paysTotals = [<?php foreach($byPays as $p) echo (int)$p['total'].','; ?>];

      new Chart(document.getElementById('chartPays'), {
        type: 'doughnut',
        data: {
          labels: paysLabels,
          datasets: [{ data: paysTotals, backgroundColor: ['#042C53','#185FA5','#FAC775'], borderWidth: 0, hoverOffset: 6 }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          cutout: '65%'
        }
      });

      // Line - monthly
      const monthLabels = [<?php foreach($monthly as $m) echo "'".addslashes($m['mois'])."',"; ?>];
      const monthData   = [<?php foreach($monthly as $m) echo (int)$m['total'].','; ?>];

      new Chart(document.getElementById('chartMonthly'), {
        type: 'line',
        data: {
          labels: monthLabels,
          datasets: [{
            label: 'Demandes',
            data: monthData,
            borderColor: '#042C53',
            backgroundColor: 'rgba(4,44,83,0.06)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#FAC775',
            pointBorderColor: '#042C53',
            pointRadius: 4
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false }, ticks: { font: { family: 'DM Sans', size: 11 }, color: '#9CA3AF' } },
            y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { family: 'DM Sans', size: 11 }, color: '#9CA3AF' } }
          }
        }
      });
    },

    init() {
      this.loadDemandes();
      this.$nextTick(() => this.initCharts());
    }
  }
}
</script>

</body>
</html>