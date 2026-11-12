<?php
// Cabeçalho e rodapé comuns do backoffice.
require_once __DIR__ . '/../inc/bootstrap.php';
exigir_login();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function topo(string $titulo, string $ativo): void {
    $novas = (int)db()->query("SELECT COUNT(*) FROM marcacoes WHERE estado = 'nova'")->fetchColumn();
    $links = [
        'marcacoes'  => ['index.php', 'Marcações'],
        'historico'  => ['historico.php', 'Histórico de serviços'],
        'servicos'   => ['servicos.php', 'Serviços'],
        'precos'     => ['precos.php', 'Preços'],
        'revisoes'   => ['revisoes.php', 'Revisões'],
        'promocoes'  => ['promocoes.php', 'Promoções'],
        'newsletter' => ['newsletter.php', 'Newsletter'],
        'definicoes' => ['definicoes.php', 'Dados da oficina'],
        'horario'    => ['horario.php', 'Horário'],
    ];
    ?><!DOCTYPE html>
<html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= h($titulo) ?> · Backoffice <?= h(def('nome_oficina', 'CMPNEUS')) ?></title>
<link rel="icon" href="../assets/logo.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@800&family=Barlow:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="admin.css">
</head><body>
<div class="app">
  <aside class="side">
    <a class="logo" href="index.php"><img src="../assets/logo.png" alt="<?= h(def('nome_oficina', 'CMPNEUS')) ?>"></a>
    <nav>
      <?php foreach ($links as $k => [$url, $txt]): ?>
        <a href="<?= $url ?>" class="<?= $k === $ativo ? 'on' : '' ?>"><?= h($txt) ?><?php if ($k === 'marcacoes' && $novas): ?><span class="badge"><?= $novas ?></span><?php endif; ?></a>
      <?php endforeach; ?>
      <div class="sep"></div>
      <a href="../index.php" target="_blank">Ver site ↗</a>
      <a href="conta.php" class="<?= $ativo === 'conta' ? 'on' : '' ?>">A minha conta</a>
      <a href="logout.php">Sair</a>
    </nav>
    <div class="user">Sessão: <?= h(utilizador()['nome']) ?></div>
  </aside>
  <main class="main">
  <?php foreach (flashes() as [$tipo, $msg]): ?><p class="alert <?= h($tipo) ?>"><?= h($msg) ?></p><?php endforeach;
}

function fundo(): void {
    ?></main></div></body></html><?php
}
