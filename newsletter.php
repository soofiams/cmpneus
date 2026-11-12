<?php
// Página para deixar de receber a newsletter (link no fim de cada email).
require __DIR__ . '/inc/bootstrap.php';

$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['sair'] ?? $_POST['sair'] ?? ''));
$sub = null;
if (strlen($token) === 32) {
    $st = db()->prepare('SELECT * FROM subscritores WHERE token = ?');
    $st->execute([$token]);
    $sub = $st->fetch() ?: null;
}
$feito = false;
if ($sub && $_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('UPDATE subscritores SET ativo = 0, saiu_em = NOW() WHERE id = ?')->execute([$sub['id']]);
    $feito = true;
}
$nome = def('nome_oficina', 'CMPNEUS');
?><!DOCTYPE html>
<html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Newsletter · <?= h($nome) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@800&family=Barlow:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="admin/admin.css">
</head><body class="auth"><main class="auth-card">
  <a href="index.php"><img src="assets/logo.png" alt="<?= h($nome) ?>" class="auth-logo"></a>
  <h1>Newsletter</h1>
  <?php if (!$sub): ?>
    <p class="alert">Este link não é válido ou já expirou.</p>
  <?php elseif ($feito || !$sub['ativo']): ?>
    <p class="alert ok">O email <b><?= h($sub['email']) ?></b> já não vai receber as nossas promoções.</p>
    <a class="btn" href="index.php">Voltar ao site</a>
  <?php else: ?>
    <p>Quer deixar de receber as promoções da <?= h($nome) ?> em <b><?= h($sub['email']) ?></b>?</p>
    <form method="post" class="form-actions">
      <input type="hidden" name="sair" value="<?= h($token) ?>">
      <button class="btn primary" type="submit">Sim, deixar de receber</button>
      <a class="btn" href="index.php">Cancelar</a>
    </form>
  <?php endif; ?>
</main></body></html>
