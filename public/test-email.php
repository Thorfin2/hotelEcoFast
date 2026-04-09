<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Dotenv\Dotenv;

// Ce fichier est hors du front controller Symfony : il faut charger .env explicitement,
// sinon $_ENV reste vide et le mot de passe SMTP n'est jamais lu.
$envPath = dirname(__DIR__) . '/.env';
if (is_readable($envPath)) {
    (new Dotenv())->loadEnv($envPath);
}

// Sur PHP-FPM / hébergeurs, les secrets sont souvent dans le processus (getenv) mais pas dans $_ENV.
$mailerKeys = ['MAILER_HOST', 'MAILER_PORT', 'MAILER_USERNAME', 'MAILER_PASSWORD', 'MAILER_FROM_EMAIL', 'MAILER_FROM_NAME', 'MAILER_ENCRYPTION'];
foreach ($mailerKeys as $key) {
    if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            $_ENV[$key] = $v;
        }
    }
}

$sent = false;
$error = '';
$debug = '';
$to = $_POST['to'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $to) {
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str) use (&$debug) { $debug .= $str . "\n"; };

        $encryption = $_ENV['MAILER_ENCRYPTION'] ?? 'ssl';

        $mail->isSMTP();
        $mail->Host       = $_ENV['MAILER_HOST'] ?? 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAILER_USERNAME'] ?? 'contact@ecofast-vtc.fr';
        $mail->Password   = $_ENV['MAILER_PASSWORD'] ?? '';
        $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['MAILER_PORT'] ?? 465);
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;

        $mail->setFrom(
            $_ENV['MAILER_FROM_EMAIL'] ?? 'contact@ecofast-vtc.fr',
            $_ENV['MAILER_FROM_NAME'] ?? 'EcoFast VTC'
        );
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Test EcoFast - Email fonctionne !';
        $mail->Body = '
        <div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1)">
            <div style="background:linear-gradient(135deg,#0d9488,#0f766e);padding:32px;text-align:center">
                <h1 style="color:#fff;margin:0;font-size:22px">EcoFast VTC</h1>
            </div>
            <div style="padding:32px;text-align:center">
                <div style="font-size:48px;margin-bottom:16px">&#9989;</div>
                <h2 style="color:#1e293b;margin-top:0">Ca marche !</h2>
                <p style="color:#64748b">Si tu vois ce mail, la configuration SMTP est correcte.</p>
                <div style="background:#f0fdf4;border-left:4px solid #0d9488;padding:12px 16px;border-radius:4px;text-align:left;margin-top:20px">
                    <strong style="color:#0d9488">Infos :</strong><br>
                    <span style="color:#64748b;font-size:13px">
                        Host: ' . htmlspecialchars($_ENV['MAILER_HOST'] ?? 'smtp.hostinger.com') . '<br>
                        Port: ' . htmlspecialchars($_ENV['MAILER_PORT'] ?? '465') . '<br>
                        From: ' . htmlspecialchars($_ENV['MAILER_FROM_EMAIL'] ?? 'contact@ecofast-vtc.fr') . '<br>
                        To: ' . htmlspecialchars($to) . '
                    </span>
                </div>
            </div>
        </div>';

        $mail->send();
        $sent = true;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Test Email - EcoFast</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #1a1a2e; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card { background: #fff; border-radius: 16px; padding: 40px; max-width: 500px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
    h1 { color: #1e293b; font-size: 24px; margin-bottom: 8px; }
    p.sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    input[type=email] { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; outline: none; transition: border-color .2s; }
    input[type=email]:focus { border-color: #0d9488; }
    button { width: 100%; margin-top: 16px; padding: 14px; background: linear-gradient(135deg, #0d9488, #0f766e); color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: opacity .2s; }
    button:hover { opacity: 0.9; }
    .result { margin-top: 20px; padding: 16px; border-radius: 8px; font-size: 14px; }
    .success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .debug { margin-top: 16px; background: #1e293b; color: #94a3b8; padding: 16px; border-radius: 8px; font-size: 11px; font-family: monospace; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
    .env-info { margin-top: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; font-size: 12px; color: #64748b; }
    .env-info strong { color: #374151; }
</style>
</head>
<body>
<div class="card">
    <h1>&#9993; Test Email SMTP</h1>
    <p class="sub">Envoie un email de test pour verifier la config SMTP</p>

    <form method="POST">
        <label for="to">Adresse email du destinataire</label>
        <input type="email" id="to" name="to" placeholder="exemple@gmail.com" value="<?= htmlspecialchars($to) ?>" required>
        <button type="submit">Envoyer le test</button>
    </form>

    <?php if ($sent): ?>
        <div class="result success">&#9989; <strong>Email envoye avec succes !</strong> Verifie ta boite de reception (et les spams).</div>
    <?php elseif ($error): ?>
        <div class="result error">&#10060; <strong>Erreur :</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($debug): ?>
        <div class="debug"><?= htmlspecialchars($debug) ?></div>
    <?php endif; ?>

    <div class="env-info">
        <strong>Config SMTP actuelle :</strong><br>
        Host: <?= htmlspecialchars($_ENV['MAILER_HOST'] ?? 'smtp.hostinger.com') ?><br>
        Port: <?= htmlspecialchars($_ENV['MAILER_PORT'] ?? '465') ?><br>
        Username: <?= htmlspecialchars($_ENV['MAILER_USERNAME'] ?? 'contact@ecofast-vtc.fr') ?><br>
        Password: <?= !empty($_ENV['MAILER_PASSWORD']) && $_ENV['MAILER_PASSWORD'] !== 'change_me_in_railway' ? '****** (configure)' : '<span style="color:#dc2626">NON CONFIGURE</span>' ?><br>
        Encryption: <?= htmlspecialchars($_ENV['MAILER_ENCRYPTION'] ?? 'ssl') ?><br>
        From: <?= htmlspecialchars($_ENV['MAILER_FROM_EMAIL'] ?? 'contact@ecofast-vtc.fr') ?>
    </div>
</div>
</body>
</html>
