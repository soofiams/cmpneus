<?php
// Instalação: cria as tabelas e a primeira conta do backoffice.
// Depois de instalar, APAGUE este ficheiro do servidor.
require __DIR__ . '/inc/bootstrap.php';

$erro = '';
$feito = false;

try {
    $sql = file_get_contents(__DIR__ . '/install.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
        if (trim($stmt) !== '') db()->exec($stmt);
    }
    $jaTemConta = (int)db()->query('SELECT COUNT(*) FROM utilizadores')->fetchColumn() > 0;
} catch (PDOException $e) {
    exit('Não foi possível ligar à base de dados. Confirme os dados em config.php. (' . h($e->getMessage()) . ')');
}

if (!$jaTemConta && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['pass'] ?? '';
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Indique o nome e um email válido.';
    } elseif (mb_strlen($pass) < 8) {
        $erro = 'A palavra-passe tem de ter pelo menos 8 caracteres.';
    } else {
        db()->prepare('INSERT INTO utilizadores (nome, email, password_hash) VALUES (?,?,?)')
            ->execute([$nome, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        $feito = true;
    }
}
?><!DOCTYPE html>
<html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalação · CMPNEUS</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@800&family=Barlow:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="admin/admin.css">
</head><body class="auth">
<main class="auth-card">
  <img src="assets/logo.png" alt="CMPNEUS" class="auth-logo">
  <h1>Instalação</h1>
  <?php if ($feito): ?>
    <p class="alert ok">Conta criada e base de dados pronta.</p>
    <p><b>Importante:</b> apague agora o ficheiro <code>setup.php</code> do servidor.</p>
    <a class="btn primary" href="admin/login.php">Entrar no backoffice</a>
  <?php elseif ($jaTemConta): ?>
    <p class="alert">A instalação já foi feita. Apague o ficheiro <code>setup.php</code> do servidor.</p>
    <a class="btn primary" href="admin/login.php">Entrar no backoffice</a>
  <?php else: ?>
    <p class="muted">A base de dados foi preparada. Crie a conta de acesso ao backoffice.</p>
    <?php if ($erro): ?><p class="alert err"><?= h($erro) ?></p><?php endif; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <label>Nome<input name="nome" required value="<?= h($_POST['nome'] ?? '') ?>"></label>
      <label>Email<input name="email" type="email" required value="<?= h($_POST['email'] ?? '') ?>"></label>
      <label>Palavra-passe (mínimo 8 caracteres)<input name="pass" type="password" required minlength="8"></label>
      <button class="btn primary" type="submit">Criar conta</button>
    </form>
  <?php endif; ?>
</main>
</body></html>
