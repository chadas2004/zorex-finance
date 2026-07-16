<?php
session_start();
require '../db.php';

// Vérification de l'ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: demandes.php");
    exit;
}

// Récupération de la demande
$stmt = $pdo->prepare("SELECT * FROM demandes_financement WHERE id = ?");
$stmt->execute([$id]);
$demande = $stmt->fetch();

if (!$demande) {
    header("Location: demandes.php");
    exit;
}

// Nettoyage numéro WA
$waNumero = preg_replace('/\D+/', '', $demande['adresse'] ?? '');

// Demande précédente / suivante (navigation)
$prev = $pdo->prepare("SELECT id FROM demandes_financement WHERE id < ? ORDER BY id DESC LIMIT 1");
$prev->execute([$id]);
$prevId = $prev->fetchColumn();

$next = $pdo->prepare("SELECT id FROM demandes_financement WHERE id > ? ORDER BY id ASC LIMIT 1");
$next->execute([$id]);
$nextId = $next->fetchColumn();

// Badge couleur type
function typeBadgeClass(string $type): string {
    if (str_contains($type, 'Personnel'))     return 'badge-personal';
    if (str_contains($type, 'Immobilier'))    return 'badge-immo';
    if (str_contains($type, 'Auto'))          return 'badge-auto';
    if (str_contains($type, 'Professionnel')) return 'badge-pro';
    if (str_contains($type, 'Étudiant'))      return 'badge-etudiant';
    return 'badge-personal';
}

// Message WhatsApp pré-rempli
$waMessage = rawurlencode(
    "Bonjour {$demande['prenom']} {$demande['nom']},\n\n" .
    "Nous avons bien reçu votre demande de {$demande['type_pret']} pour un montant de " .
    number_format($demande['montant'], 0, ',', ' ') . " €.\n\n" .
    "Un conseiller Zorex Fianance va vous recontacter très rapidement.\n\n" .
    "Merci de votre confiance !"
);
$waLink = "https://wa.me/{$waNumero}?text={$waMessage}";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Demande #<?= $id ?> | Zorex Fianance Admin</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <style>
    :root { --navy:#042C53; --navy-mid:#0C447C; --blue:#185FA5; --gold:#FAC775; --gold-dark:#EF9F27; }
    * { box-sizing: border-box; }
    body { font-family:'DM Sans',sans-serif; background:#F1F4F9; color:#1a1a1a; }
    .font-display { font-family:'Playfair Display',serif; }

    /* Sidebar */
    .sidebar { width:240px; min-width:240px; background:var(--navy); min-height:100vh; position:sticky; top:0; display:flex; flex-direction:column; }
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

    /* Cards */
    .card { background:white; border:1px solid rgba(4,44,83,0.07); border-radius:16px; }

    /* Info rows */
    .info-row { display:flex; align-items:flex-start; gap:12px; padding:14px 0; border-bottom:1px solid rgba(4,44,83,0.05); }
    .info-row:last-child { border-bottom:none; }
    .info-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
    .info-label { font-size:11px; color:#9CA3AF; margin-bottom:3px; }
    .info-value { font-size:14px; font-weight:500; color:#1a1a1a; }

    /* Badges */
    .badge-type { display:inline-block; font-size:12px; font-weight:500; padding:4px 12px; border-radius:20px; }
    .badge-personal { background:#E6F1FB; color:#0C447C; }
    .badge-immo     { background:#FAEEDA; color:#854F0B; }
    .badge-auto     { background:#E1F5EE; color:#0F6E56; }
    .badge-pro      { background:#EEEDFE; color:#534AB7; }
    .badge-etudiant { background:#FDE8F3; color:#9C2E6B; }

    /* Avatar */
    .avatar-lg { width:56px; height:56px; border-radius:50%; background:var(--navy); color:var(--gold); font-family:'Playfair Display',serif; font-size:20px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

    /* Buttons */
    .btn-wa { display:flex; align-items:center; justify-content:center; gap:10px; background:linear-gradient(135deg,#22c55e,#16a34a); color:white; font-weight:600; font-size:14px; padding:14px 20px; border-radius:12px; text-decoration:none; transition:all .2s; box-shadow:0 4px 20px rgba(34,197,94,0.2); }
    .btn-wa:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(34,197,94,0.3); }
    .btn-mail { display:flex; align-items:center; justify-content:center; gap:8px; background:#E6F1FB; color:#0C447C; font-weight:500; font-size:13px; padding:11px 16px; border-radius:10px; text-decoration:none; transition:all .2s; }
    .btn-mail:hover { background:#d3e9f8; }
    .btn-back { display:inline-flex; align-items:center; gap:7px; background:white; border:1px solid rgba(4,44,83,0.1); color:#374151; font-size:13px; font-weight:500; padding:9px 16px; border-radius:10px; text-decoration:none; transition:all .15s; }
    .btn-back:hover { background:#F8FAFC; border-color:rgba(4,44,83,0.2); }
    .btn-delete { display:flex; align-items:center; justify-content:center; gap:7px; background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; font-size:13px; font-weight:500; padding:11px 16px; border-radius:10px; text-decoration:none; transition:all .15s; cursor:pointer; }
    .btn-delete:hover { background:#FEE2E2; }

    /* Nav badge */
    .nav-badge { display:inline-flex; align-items:center; gap:7px; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:500; text-decoration:none; transition:all .15s; border:1px solid; }
    .nav-badge-prev { background:#F8FAFC; border-color:#E2E8F0; color:#6B7280; }
    .nav-badge-prev:hover { background:white; border-color:var(--navy); color:var(--navy); }
    .nav-badge-next { background:var(--navy); border-color:var(--navy); color:var(--gold); }
    .nav-badge-next:hover { background:var(--navy-mid); }
    .nav-badge.disabled { opacity:.4; pointer-events:none; }

    /* Timeline */
    .timeline-dot { width:10px; height:10px; border-radius:50%; background:var(--gold); border:2px solid var(--navy); flex-shrink:0; margin-top:5px; }

    /* Mobile */
    .mobile-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:30; }
    .mobile-overlay.show { display:block; }
    @media (max-width:768px) {
      .sidebar { position:fixed; top:0; left:0; z-index:40; height:100vh; transform:translateX(-100%); transition:transform .3s; }
      .sidebar.open { transform:translateX(0); }
      .detail-grid { grid-template-columns:1fr !important; }
    }

    .eyebrow { font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:#BA7517; background:rgba(250,199,117,0.12); padding:3px 10px; border-radius:20px; display:inline-block; }
  </style>
</head>

<body class="flex min-h-screen">

<div class="mobile-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
  <div style="padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.07)" class="flex items-center gap-3">
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
    <a href="index.php"    class="nav-item"><i class="bi bi-grid-1x2-fill"></i> Tableau de bord</a>
    <a href="demandes.php" class="nav-item active"><i class="bi bi-file-earmark-text-fill"></i> Demandes</a>
    <div class="nav-section mt-2">Gestion</div>
    <a href="clients.php"  class="nav-item"><i class="bi bi-people-fill"></i> Clients</a>
    <a href="stats.php"    class="nav-item"><i class="bi bi-bar-chart-fill"></i> Statistiques</a>
    <a href="settings.php" class="nav-item"><i class="bi bi-gear-fill"></i> Paramètres</a>
    <div class="nav-section mt-2">Compte</div>
    <a href="logout.php"   class="nav-item nav-logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
  </nav>

  <div style="padding:14px 18px;border-top:1px solid rgba(255,255,255,0.07)">
    <div class="flex items-center gap-3">
      <div style="width:32px;height:32px;border-radius:50%;background:var(--navy);border:2px solid rgba(250,199,117,0.3);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--gold)">AD</div>
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
    <div class="flex items-center gap-3">
      <button onclick="openSidebar()" class="md:hidden w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200">
        <i class="bi bi-list text-xl text-gray-600"></i>
      </button>
      <a href="demandes.php" class="btn-back">
        <i class="bi bi-arrow-left text-xs"></i> Demandes
      </a>
      <div style="width:1px;height:20px;background:rgba(4,44,83,0.1)"></div>
      <div>
        <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:600;color:var(--navy)">
          Demande <span style="color:#9CA3AF">#<?= $id ?></span>
        </div>
      </div>
    </div>

    <!-- Prev / Next navigation -->
    <div class="flex items-center gap-2">
      <?php if ($prevId): ?>
        <a href="demandes_detail.php?id=<?= $prevId ?>" class="nav-badge nav-badge-prev">
          <i class="bi bi-chevron-left text-xs"></i> #<?= $prevId ?>
        </a>
      <?php else: ?>
        <span class="nav-badge nav-badge-prev disabled">
          <i class="bi bi-chevron-left text-xs"></i> Précédent
        </span>
      <?php endif; ?>

      <?php if ($nextId): ?>
        <a href="demandes_detail.php?id=<?= $nextId ?>" class="nav-badge nav-badge-next">
          #<?= $nextId ?> <i class="bi bi-chevron-right text-xs"></i>
        </a>
      <?php else: ?>
        <span class="nav-badge nav-badge-next disabled">
          Suivant <i class="bi bi-chevron-right text-xs"></i>
        </span>
      <?php endif; ?>
    </div>
  </div>


  <!-- Content -->
  <div class="flex-1 overflow-y-auto p-5 md:p-7">
    <div class="detail-grid" style="display:grid;grid-template-columns:1fr 340px;gap:20px;max-width:1100px">

      <!-- LEFT: Main info -->
      <div class="space-y-5">

        <!-- Client identity card -->
        <div class="card p-6">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid rgba(4,44,83,0.06)">
            <div class="avatar-lg">
              <?= mb_strtoupper(mb_substr($demande['prenom'],0,1).mb_substr($demande['nom'],0,1)) ?>
            </div>
            <div>
              <div style="font-family:'Playfair Display',serif;font-size:22px;font-weight:600;color:var(--navy)">
                <?= htmlspecialchars($demande['prenom'].' '.$demande['nom']) ?>
              </div>
              <div style="display:flex;align-items:center;gap:10px;margin-top:5px;flex-wrap:wrap">
                <span class="badge-type <?= typeBadgeClass($demande['type_pret'] ?? '') ?>">
                  <?= htmlspecialchars($demande['type_pret'] ?? '—') ?>
                </span>
                <span style="font-size:12px;color:#9CA3AF">
                  <?= htmlspecialchars(explode(' ', $demande['pays'])[0] ?? $demande['pays']) ?>
                </span>
                <?php if (!empty($demande['date_creation'])): ?>
                  <span style="font-size:11px;color:#CBD5E1">·</span>
                  <span style="font-size:12px;color:#9CA3AF">
                    <?= date('d/m/Y à H:i', strtotime($demande['date_creation'])) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Info rows -->
          <div>
            <div class="info-row">
              <div class="info-icon" style="background:#E6F1FB">
                <i class="bi bi-envelope-fill" style="color:#185FA5"></i>
              </div>
              <div>
                <div class="info-label">Adresse e-mail</div>
                <a href="mailto:<?= htmlspecialchars($demande['email']) ?>"
                   style="font-size:14px;font-weight:500;color:var(--navy);text-decoration:none">
                  <?= htmlspecialchars($demande['email']) ?>
                </a>
              </div>
            </div>

            <div class="info-row">
              <div class="info-icon" style="background:#EAF3DE">
                <i class="bi bi-geo-alt-fill" style="color:#3B6D11"></i>
              </div>
              <div>
                <div class="info-label">Pays de résidence</div>
                <div class="info-value"><?= htmlspecialchars($demande['pays']) ?></div>
              </div>
            </div>

            <div class="info-row">
              <div class="info-icon" style="background:#dcfce7">
                <i class="bi bi-whatsapp" style="color:#16a34a"></i>
              </div>
              <div>
                <div class="info-label">Numéro WhatsApp</div>
                <a href="<?= $waLink ?>" target="_blank"
                   style="font-size:14px;font-weight:500;color:#16a34a;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                  <?= htmlspecialchars($demande['adresse'] ?? '—') ?>
                  <i class="bi bi-box-arrow-up-right" style="font-size:10px;opacity:.6"></i>
                </a>
              </div>
            </div>

            <div class="info-row">
              <div class="info-icon" style="background:#fce7f3">
                <i class="bi bi-mailbox2" style="color:#9C2E6B"></i>
              </div>
              <div>
                <div class="info-label">Code postal</div>
                <div class="info-value"><?= htmlspecialchars($demande['codepostal'] ?? $demande['code_postal'] ?? '—') ?></div>
              </div>
            </div>

            <?php if (!empty($demande['date_creation'])): ?>
            <div class="info-row">
              <div class="info-icon" style="background:#F1F4F9">
                <i class="bi bi-calendar3" style="color:#6B7280"></i>
              </div>
              <div>
                <div class="info-label">Date de soumission</div>
                <div class="info-value"><?= date('d F Y à H:i', strtotime($demande['date_creation'])) ?></div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Loan details card -->
        <div class="card p-6">
          <div style="margin-bottom:18px">
            <span class="eyebrow">Financement</span>
            <div style="font-family:'Playfair Display',serif;font-size:16px;font-weight:600;color:var(--navy);margin-top:6px">Détails du prêt demandé</div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div style="background:#F8FAFC;border-radius:12px;padding:16px">
              <div style="font-size:11px;color:#9CA3AF;margin-bottom:6px">Type de prêt</div>
              <span class="badge-type <?= typeBadgeClass($demande['type_pret'] ?? '') ?>" style="font-size:13px">
                <?= htmlspecialchars($demande['type_pret'] ?? '—') ?>
              </span>
            </div>
            <div style="background:#F8FAFC;border-radius:12px;padding:16px">
              <div style="font-size:11px;color:#9CA3AF;margin-bottom:6px">Montant sollicité</div>
              <div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:600;color:var(--navy);line-height:1">
                <?= number_format($demande['montant'], 0, ',', ' ') ?> €
              </div>
            </div>
          </div>
        </div>

      </div><!-- /left -->


      <!-- RIGHT: Actions sidebar -->
      <div class="space-y-5">

        <!-- Contact actions -->
        <div class="card p-5">
          <div style="font-size:13px;font-weight:500;color:var(--navy);margin-bottom:14px;font-family:'Playfair Display',serif">Contacter le client</div>

          <div class="space-y-3">
            <a href="<?= $waLink ?>" target="_blank" class="btn-wa">
              <i class="bi bi-whatsapp" style="font-size:18px"></i>
              Envoyer sur WhatsApp
            </a>
            <a href="mailto:<?= htmlspecialchars($demande['email']) ?>" class="btn-mail">
              <i class="bi bi-envelope-fill"></i>
              Envoyer un e-mail
            </a>
          </div>

          <div style="margin-top:14px;padding:10px 12px;background:#FAFBFC;border-radius:10px;border:1px solid rgba(4,44,83,0.06)">
            <div style="font-size:11px;color:#9CA3AF;margin-bottom:4px">Message WhatsApp pré-rempli</div>
            <div style="font-size:11px;color:#6B7280;line-height:1.5">
              Bonjour <strong><?= htmlspecialchars($demande['prenom']) ?></strong>,
              votre demande de <em><?= htmlspecialchars($demande['type_pret'] ?? '') ?></em>
              (<?= number_format($demande['montant'], 0, ',', ' ') ?> €) a bien été reçue…
            </div>
          </div>
        </div>

        <!-- Quick recap -->
        <div class="card p-5">
          <div style="font-size:13px;font-weight:500;color:var(--navy);margin-bottom:14px;font-family:'Playfair Display',serif">Récapitulatif</div>
          <div style="space-y:10px">
            <?php
            $fields = [
              ['label'=>'Identité',    'val'=>htmlspecialchars($demande['prenom'].' '.$demande['nom'])],
              ['label'=>'Email',       'val'=>htmlspecialchars($demande['email'])],
              ['label'=>'Pays',        'val'=>htmlspecialchars($demande['pays'])],
              ['label'=>'Type',        'val'=>htmlspecialchars($demande['type_pret'] ?? '—')],
              ['label'=>'Montant',     'val'=>number_format($demande['montant'],0,',',' ').' €'],
              ['label'=>'WhatsApp',    'val'=>htmlspecialchars($demande['adresse'] ?? '—')],
              ['label'=>'Code postal', 'val'=>htmlspecialchars($demande['codepostal'] ?? $demande['code_postal'] ?? '—')],
            ];
            foreach($fields as $f):
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(4,44,83,0.04)">
              <span style="font-size:11px;color:#9CA3AF"><?= $f['label'] ?></span>
              <span style="font-size:12px;font-weight:500;color:#374151;text-align:right;max-width:180px;word-break:break-word"><?= $f['val'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Danger zone -->
        <div class="card p-5" style="border-color:#FEE2E2">
          <div style="font-size:12px;font-weight:600;color:#DC2626;text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px">Zone dangereuse</div>
          <a href="demandes_delete.php?id=<?= $id ?>"
             onclick="return confirm('Supprimer définitivement la demande #<?= $id ?> de <?= addslashes(htmlspecialchars($demande['prenom'].' '.$demande['nom'])) ?> ? Cette action est irréversible.')"
             class="btn-delete">
            <i class="bi bi-trash-fill" style="font-size:13px"></i>
            Supprimer cette demande
          </a>
          <p style="font-size:11px;color:#9CA3AF;margin-top:8px;line-height:1.4">Cette action est irréversible et supprimera définitivement la demande et toutes ses données.</p>
        </div>

      </div><!-- /right -->

    </div><!-- /grid -->
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