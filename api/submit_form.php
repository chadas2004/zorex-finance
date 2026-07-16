<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php'; // fournit $pdo (connexion Neon/Postgres)

/* ===============================================================
   CONFIGURATION SMTP — via variables d'environnement Vercel
   À définir : SMTP_HOST, SMTP_USER, SMTP_PASS, SMTP_PORT
   =============================================================== */
$smtp_host   = getenv('SMTP_HOST') ?: 'smtp.hostinger.com';
$smtp_user   = getenv('SMTP_USER');
$smtp_pass   = getenv('SMTP_PASS');
$smtp_port   = (int) (getenv('SMTP_PORT') ?: 465);
$smtp_secure = PHPMailer::ENCRYPTION_SMTPS; // SSL

/* ===============================================================
   HELPERS
   =============================================================== */

function json_error(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function sanitize(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

/* ===============================================================
   MÉTHODE
   =============================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Méthode non autorisée.', 405);
}

/* ===============================================================
   CRÉATION TABLE SI ELLE N'EXISTE PAS (syntaxe PostgreSQL)
   =============================================================== */
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS demandes_financement (
            id           SERIAL PRIMARY KEY,
            nom          VARCHAR(100)    NOT NULL,
            prenom       VARCHAR(100)    NOT NULL,
            email        VARCHAR(150)    NOT NULL,
            pays         VARCHAR(100)    NOT NULL,
            type_pret    VARCHAR(100)    NOT NULL,
            montant      DECIMAL(10,2)   NOT NULL,
            adresse      VARCHAR(255)    NOT NULL,
            code_postal  VARCHAR(20)     NOT NULL,
            date_demande TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    json_error('Erreur création table : ' . $e->getMessage(), 500);
}

/* ===============================================================
   RÉCUPÉRATION & NETTOYAGE
   =============================================================== */
$nom        = sanitize($_POST['nom']        ?? '');
$prenom     = sanitize($_POST['prenom']     ?? '');
$email      = trim($_POST['email']          ?? '');
$pays       = sanitize($_POST['pays']       ?? '');
$type       = sanitize($_POST['type']       ?? '');
$montant    = trim($_POST['montant']        ?? '');
$adresse    = sanitize($_POST['adresse']    ?? '');
$codepostal = sanitize($_POST['codepostal'] ?? '');

$langRaw = strtolower(trim($_POST['lang'] ?? ''));
$lang    = in_array($langRaw, ['fr','de','en','pt','el','it','es']) ? $langRaw : 'de';

/* ===============================================================
   VALIDATION
   =============================================================== */
$errors = [];

if (empty($nom))        $errors[] = 'Le nom est requis.';
if (empty($prenom))     $errors[] = 'Le prénom est requis.';
if (empty($email))      $errors[] = "L'email est requis.";
if (empty($pays))       $errors[] = 'Le pays est requis.';
if (empty($type))       $errors[] = 'Le type de prêt est requis.';
if (empty($adresse))    $errors[] = 'Le numéro WhatsApp est requis.';
if (empty($codepostal)) $errors[] = 'Le code postal est requis.';

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Adresse e-mail invalide.';
}

$montantFloat = filter_var($montant, FILTER_VALIDATE_FLOAT);
if ($montantFloat === false || $montantFloat <= 0) {
    $errors[] = 'Montant invalide.';
}

if (!empty($errors)) {
    json_error(implode(' ', $errors), 422);
}

$montantFormatted = number_format($montantFloat, 2, '.', '');

/* ===============================================================
   ANTI-SPAM : limite 3 demandes / même email / 24h
   (syntaxe INTERVAL PostgreSQL : nécessite des guillemets)
   =============================================================== */
try {
    $spamCheck = $pdo->prepare("
        SELECT COUNT(*) FROM demandes_financement
        WHERE email = :email AND date_demande >= NOW() - INTERVAL '24 HOUR'
    ");
    $spamCheck->execute([':email' => $email]);
    if ($spamCheck->fetchColumn() >= 3) {
        json_error('Trop de demandes soumises avec cet email. Réessayez dans 24h.');
    }
} catch (PDOException $e) {
    json_error('Erreur anti-spam : ' . $e->getMessage(), 500);
}

/* ===============================================================
   INSERTION BDD
   (PostgreSQL : on utilise RETURNING id au lieu de lastInsertId()
    qui ne fonctionne pas nativement de la même façon qu'en MySQL)
   =============================================================== */
try {
    $stmt = $pdo->prepare("
        INSERT INTO demandes_financement
            (nom, prenom, email, pays, type_pret, montant, adresse, code_postal)
        VALUES
            (:nom, :prenom, :email, :pays, :type_pret, :montant, :adresse, :code_postal)
        RETURNING id
    ");

    $stmt->execute([
        ':nom'         => $nom,
        ':prenom'      => $prenom,
        ':email'       => $email,
        ':pays'        => $pays,
        ':type_pret'   => $type,
        ':montant'     => $montantFormatted,
        ':adresse'     => $adresse,
        ':code_postal' => $codepostal,
    ]);

    $newId = $stmt->fetchColumn();

} catch (PDOException $e) {
    json_error('Erreur BDD : ' . $e->getMessage(), 500);
}

/* ===============================================================
   FORMAT MONTANT SELON LANGUE
   =============================================================== */
$montantDisplay = match($lang) {
    'en'    => '€ ' . number_format($montantFloat, 0, '.', ','),
    'de'    => number_format($montantFloat, 0, ',', '.') . ' €',
    default => number_format($montantFloat, 0, ',', ' ') . ' €',
};

/* ===============================================================
   TRADUCTIONS EMAIL — TOUTES LES LANGUES
   =============================================================== */
$translations = [
    'de' => [
        'subject'     => "Bestätigung Ihrer Anfrage – Zorex Finance",
        'greeting'    => "Sehr geehrte(r) $prenom $nom,",
        'intro'       => "wir bestätigen hiermit den <strong>ordnungsgemäßen Eingang Ihrer Finanzierungsanfrage</strong>. Vielen Dank für Ihr Vertrauen.",
        'recap_title' => "Zusammenfassung Ihrer Anfrage",
        'lbl_pays'    => "Land",
        'lbl_type'    => "Kreditart",
        'lbl_montant' => "Beantragter Betrag",
        'lbl_ref'     => "Referenz",
        'next'        => "Ein Berater von Zorex Finance wird sich <strong>innerhalb von 48 Stunden</strong> mit Ihnen in Verbindung setzen, um Ihren Antrag zu bearbeiten.",
        'closing'     => "Mit freundlichen Grüßen,",
        'tagline'     => "Finanzlösungen in Europa",
        'footer'      => "© " . date('Y') . " Zorex Finance — Alle Rechte vorbehalten",
        'disclaimer'  => "Diese E-Mail wurde automatisch generiert. Bitte nicht direkt antworten.",
    ],
    'fr' => [
        'subject'     => "Confirmation de votre demande – Zorex Finance",
        'greeting'    => "Bonjour $prenom $nom,",
        'intro'       => "Nous avons bien reçu votre <strong>demande de financement</strong> et nous vous en remercions chaleureusement.",
        'recap_title' => "Récapitulatif de votre demande",
        'lbl_pays'    => "Pays",
        'lbl_type'    => "Type de prêt",
        'lbl_montant' => "Montant sollicité",
        'lbl_ref'     => "Référence",
        'next'        => "Un conseiller Zorex Finance prendra contact avec vous <strong>dans les 48 heures</strong> pour étudier votre dossier et vous proposer les meilleures conditions.",
        'closing'     => "Cordialement,",
        'tagline'     => "Solutions financières en Europe",
        'footer'      => "© " . date('Y') . " Zorex Finance — Tous droits réservés",
        'disclaimer'  => "Cet email est automatique. Ne pas répondre directement.",
    ],
    'en' => [
        'subject'     => "Confirmation of your application – Zorex Finance",
        'greeting'    => "Dear $prenom $nom,",
        'intro'       => "We have successfully received your <strong>financing application</strong> and thank you for your trust in Zorex Finance.",
        'recap_title' => "Summary of your application",
        'lbl_pays'    => "Country",
        'lbl_type'    => "Loan type",
        'lbl_montant' => "Requested amount",
        'lbl_ref'     => "Reference",
        'next'        => "A Zorex Finance advisor will contact you <strong>within 48 hours</strong> to review your application and offer you the best conditions.",
        'closing'     => "Kind regards,",
        'tagline'     => "Financial solutions in Europe",
        'footer'      => "© " . date('Y') . " Zorex Finance — All rights reserved",
        'disclaimer'  => "This email was sent automatically. Please do not reply directly.",
    ],
    'pt' => [
        'subject'     => "Confirmação do seu pedido – Zorex Finance",
        'greeting'    => "Caro(a) $prenom $nom,",
        'intro'       => "Recebemos com sucesso o seu <strong>pedido de financiamento</strong> e agradecemos a sua confiança na Zorex Finance.",
        'recap_title' => "Resumo do seu pedido",
        'lbl_pays'    => "País",
        'lbl_type'    => "Tipo de empréstimo",
        'lbl_montant' => "Montante solicitado",
        'lbl_ref'     => "Referência",
        'next'        => "Um consultor da Zorex Finance entrará em contacto consigo <strong>nas próximas 48 horas</strong> para analisar o seu pedido e propor as meilhores condições.",
        'closing'     => "Com os melhores cumprimentos,",
        'tagline'     => "Soluções financeiras na Europa",
        'footer'      => "© " . date('Y') . " Zorex Finance — Todos os direitos reservados",
        'disclaimer'  => "Este email foi enviado automaticamente. Por favor não responda diretamente.",
    ],
    'el' => [
        'subject'     => "Επιβεβαίωση της αίτησής σας – Zorex Finance",
        'greeting'    => "Αγαπητέ/ή $prenom $nom,",
        'intro'       => "Λάβαμε επιτυχώς την <strong>αίτηση χρηματοδότησής σας</strong> και σας ευχαριστούμε για την εμπιστοσύνη σας στη Zorex Finance.",
        'recap_title' => "Περίληψη της αίτησής σας",
        'lbl_pays'    => "Χώρα",
        'lbl_type'    => "Τύπος δανείου",
        'lbl_montant' => "Αιτούμενο ποσό",
        'lbl_ref'     => "Αναφορά",
        'next'        => "Ένας σύμβουλος της Zorex Finance θα επικοινωνήσει μαζί σας <strong>εντός 48 ωρών</strong> για να εξετάσει την αίτησή σας και να σας προτείνει τις καλύτερες συνθήκες.",
        'closing'     => "Με εκτίμηση,",
        'tagline'     => "Χρηματοοικονομικές λύσεις στην Ευρώπη",
        'footer'      => "© " . date('Y') . " Zorex Finance — Όλα τα δικαιώματα διατηρούνται",
        'disclaimer'  => "Αυτό το email στάλθηκε αυτόματα. Παρακαλώ μην απαντάτε απευθείας.",
    ],
    'it' => [
        'subject'     => "Conferma della sua richiesta – Zorex Finance",
        'greeting'    => "Gentile $prenom $nom,",
        'intro'       => "Abbiamo ricevuto con successo la sua <strong>richiesta di finanziamento</strong> e la ringraziamo per la fiducia accordataci.",
        'recap_title' => "Riepilogo della sua richiesta",
        'lbl_pays'    => "Paese",
        'lbl_type'    => "Tipo di prestito",
        'lbl_montant' => "Importo richiesto",
        'lbl_ref'     => "Riferimento",
        'next'        => "Un consulente di Zorex Finance la contatterà <strong>entro 48 ore</strong> per esaminare la sua richiesta e proporle le migliori condizioni.",
        'closing'     => "Cordiali saluti,",
        'tagline'     => "Soluzioni finanziarie in Europa",
        'footer'      => "© " . date('Y') . " Zorex Finance — Tutti i diritti riservati",
        'disclaimer'  => "Questa email è stata inviata automaticamente. Si prega di non rispondere direttamente.",
    ],
    'es' => [
        'subject'     => "Confirmación de su solicitud – Zorex Finance",
        'greeting'    => "Estimado/a $prenom $nom,",
        'intro'       => "Hemos recibido correctamente su <strong>solicitud de financiación</strong> y le agradecemos su confianza en Zorex Finance.",
        'recap_title' => "Resumen de su solicitud",
        'lbl_pays'    => "País",
        'lbl_type'    => "Tipo de préstamo",
        'lbl_montant' => "Importe solicitado",
        'lbl_ref'     => "Referencia",
        'next'        => "Un asesor de Zorex Finance se pondrá en contacto con usted <strong>en las próximas 48 horas</strong> para estudiar su solicitud y ofrecerle las mejores condiciones.",
        'closing'     => "Atentamente,",
        'tagline'     => "Soluciones financieras en Europa",
        'footer'      => "© " . date('Y') . " Zorex Finance — Todos los derechos reservados",
        'disclaimer'  => "Este correo fue enviado automáticamente. Por favor no responda directamente.",
    ],
];

$t = $translations[array_key_exists($lang, $translations) ? $lang : 'de'];

/* ===============================================================
   TEMPLATE EMAIL HTML
   =============================================================== */
$emailHtml = <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{$t['subject']}</title>
</head>
<body style="margin:0;padding:0;background:#F1F4F9;font-family:'Helvetica Neue',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#F1F4F9;padding:40px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <tr>
    <td style="background:#042C53;border-radius:16px 16px 0 0;padding:30px 36px;text-align:center;">
      <div style="font-family:Georgia,serif;font-size:26px;font-weight:600;color:#FAC775;margin:0;">Zorex Finance</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.45);margin-top:4px;">{$t['tagline']}</div>
    </td>
  </tr>

  <tr>
    <td style="background:#ffffff;padding:36px 36px 28px;">
      <h2 style="margin:0 0 16px;font-size:20px;color:#042C53;font-family:Georgia,serif;">{$t['greeting']}</h2>
      <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.7;">{$t['intro']}</p>

      <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:12px;overflow:hidden;border:1px solid rgba(4,44,83,0.08);margin-bottom:24px;">
        <tr style="background:#F8FAFC;">
          <td colspan="2" style="padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#9CA3AF;border-bottom:1px solid rgba(4,44,83,0.06);">
            {$t['recap_title']}
          </td>
        </tr>
        <tr>
          <td style="padding:12px 16px;font-size:13px;color:#6B7280;width:40%;">{$t['lbl_pays']}</td>
          <td style="padding:12px 16px;font-size:13px;font-weight:500;color:#1a1a1a;">$pays</td>
        </tr>
        <tr style="background:#FAFBFC;">
          <td style="padding:12px 16px;font-size:13px;color:#6B7280;">{$t['lbl_type']}</td>
          <td style="padding:12px 16px;font-size:13px;font-weight:500;color:#1a1a1a;">$type</td>
        </tr>
        <tr>
          <td style="padding:12px 16px;font-size:13px;color:#6B7280;">{$t['lbl_montant']}</td>
          <td style="padding:12px 16px;font-size:16px;font-weight:700;color:#042C53;font-family:Georgia,serif;">$montantDisplay</td>
        </tr>
        <tr style="background:#FAFBFC;">
          <td style="padding:12px 16px;font-size:13px;color:#6B7280;">{$t['lbl_ref']}</td>
          <td style="padding:12px 16px;font-size:13px;font-weight:500;color:#042C53;">MF-$newId</td>
        </tr>
      </table>

      <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F7FF;border-left:3px solid #185FA5;border-radius:0 10px 10px 0;margin-bottom:28px;">
        <tr><td style="padding:16px 18px;font-size:14px;color:#0C447C;line-height:1.6;">⏱ &nbsp;{$t['next']}</td></tr>
      </table>

      <p style="font-size:14px;color:#374151;line-height:1.7;margin:0 0 4px;">{$t['closing']}</p>
      <p style="font-size:14px;font-weight:600;color:#042C53;margin:0 0 4px;font-family:Georgia,serif;">Zorex Finance</p>
      <p style="font-size:12px;color:#9CA3AF;margin:0;">{$t['tagline']}</p>
    </td>
  </tr>

  <tr>
    <td style="background:#042C53;border-radius:0 0 16px 16px;padding:18px 36px;text-align:center;">
      <p style="margin:0 0 6px;font-size:12px;color:rgba(255,255,255,0.35);">{$t['footer']}</p>
      <p style="margin:0;font-size:11px;color:rgba(255,255,255,0.2);">{$t['disclaimer']}</p>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>
HTML;

$emailText = "{$t['greeting']}\n\n"
           . strip_tags($t['intro']) . "\n\n"
           . "{$t['lbl_pays']}: $pays\n"
           . "{$t['lbl_type']}: $type\n"
           . "{$t['lbl_montant']}: $montantDisplay\n"
           . "{$t['lbl_ref']}: MF-$newId\n\n"
           . strip_tags($t['next']) . "\n\n"
           . "{$t['closing']}\nZorex Finance\n\n"
           . $t['footer'];

/* ===============================================================
   ENVOI EMAIL CLIENT
   =============================================================== */
$emailSent = false;
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_user;
    $mail->Password   = $smtp_pass;
    $mail->SMTPSecure = $smtp_secure;
    $mail->Port       = $smtp_port;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtp_user, 'Zorex Finance');
    $mail->addAddress($email, "$prenom $nom");
    $mail->addReplyTo($smtp_user, 'Zorex Finance Support');

    $mail->isHTML(true);
    $mail->Subject = $t['subject'];
    $mail->Body    = $emailHtml;
    $mail->AltBody = $emailText;

    $mail->send();
    $emailSent = true;
} catch (Exception $e) {
    error_log("PHPMailer Client Error: " . $mail->ErrorInfo);
}

/* ===============================================================
   NOTIFICATION ADMIN
   =============================================================== */
$adminNotified = false;
try {
    $adminMail = new PHPMailer(true);
    $adminMail->isSMTP();
    $adminMail->Host       = $smtp_host;
    $adminMail->SMTPAuth   = true;
    $adminMail->Username   = $smtp_user;
    $adminMail->Password   = $smtp_pass;
    $adminMail->SMTPSecure = $smtp_secure;
    $adminMail->Port       = $smtp_port;
    $adminMail->CharSet    = 'UTF-8';

    $adminMail->setFrom($smtp_user, 'Zorex Finance Bot');
    $adminMail->addAddress($smtp_user, 'Admin Zorex Finance');

    $adminMail->isHTML(true);
    $adminMail->Subject = "🔔 Nouvelle demande #MF-$newId — $prenom $nom ($type) [$lang]";
    $adminMail->Body = "
        <div style='font-family:Arial,sans-serif;max-width:500px'>
        <h3 style='color:#042C53'>Nouvelle demande reçue</h3>
        <table cellpadding='8' style='border-collapse:collapse;width:100%'>
            <tr style='background:#f8fafc'><td><b>Référence</b></td><td>MF-$newId</td></tr>
            <tr><td><b>Nom</b></td><td>$prenom $nom</td></tr>
            <tr style='background:#f8fafc'><td><b>Email</b></td><td><a href='mailto:$email'>$email</a></td></tr>
            <tr><td><b>Langue</b></td><td><b>$lang</b></td></tr>
            <tr style='background:#f8fafc'><td><b>Pays</b></td><td>$pays</td></tr>
            <tr><td><b>Type</b></td><td>$type</td></tr>
            <tr style='background:#f8fafc'><td><b>Montant</b></td><td><b>$montantDisplay</b></td></tr>
            <tr><td><b>WhatsApp</b></td><td><a href='https://wa.me/$adresse'>$adresse</a></td></tr>
            <tr style='background:#f8fafc'><td><b>Code postal</b></td><td>$codepostal</td></tr>
        </table>
        </div>
    ";

    $adminMail->send();
    $adminNotified = true;
} catch (Exception $e) {
    error_log("PHPMailer Admin Error: " . $adminMail->ErrorInfo);
}

/* ===============================================================
   RÉPONSE JSON
   =============================================================== */
$response = [
    'success'        => true,
    'reference'      => "MF-$newId",
    'email_sent'     => $emailSent,
    'admin_notified' => $adminNotified,
    'lang_used'      => $lang,
];

if (!$emailSent) {
    $response['warning'] = "Votre demande est enregistrée, mais une erreur technique empêche l'envoi de l'email de confirmation.";
}

echo json_encode($response);
