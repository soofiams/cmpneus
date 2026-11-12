<?php
require __DIR__ . '/../inc/bootstrap.php';
header('X-Frame-Options: DENY');
if (utilizador()) redirecionar('index.php');

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = ip_cliente();
    $st = db()->prepare('SELECT COUNT(*) FROM tentativas_login WHERE ip = ? AND criado_em > (NOW() - INTERVAL 15 MINUTE)');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 5) {
        $erro = 'Demasiadas tentativas. Aguarde 15 minutos e tente de novo.';
    } else {
        $st = db()->prepare('SELECT * FROM utilizadores WHERE email = ?');
        $st->execute([trim($_POST['email'] ?? '')]);
        $u = $st->fetch();
        if ($u && password_verify($_POST['pass'] ?? '', $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['utilizador'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'email' => $u['email']];
            db()->prepare('DELETE FROM tentativas_login WHERE ip = ?')->execute([$ip]);
            redirecionar('index.php');
        }
        db()->prepare('INSERT INTO tentativas_login (ip) VALUES (?)')->execute([$ip]);
        $erro = 'Email ou palavra-passe errados.';
    }
}
?><!DOCTYPE html>
<html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Entrar · Backoffice</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@800&family=Barlow:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="admin.css">
</head><body class="auth">
<main class="auth-card">
  <img src="../assets/logo.png" alt="<?= h(def('nome_oficina', 'CMPNEUS')) ?>" class="auth-logo">
  <h1>Backoffice</h1>
  <?php if ($erro): ?><p class="alert err"><?= h($erro) ?></p><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Email<input name="email" type="email" autocomplete="username" required autofocus value="<?= h($_POST['email'] ?? '') ?>"></label>
    <label>Palavra-passe<input name="pass" type="password" autocomplete="current-password" required></label>
    <button class="btn primary" type="submit" style="justify-content:center">Entrar</button>
  </form>
</main>
</body></html>
