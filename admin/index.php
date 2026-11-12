<?php
require __DIR__ . '/_layout.php';

$estado = $_GET['estado'] ?? 'abertas';
$q      = trim($_GET['q'] ?? '');

// Contagens
$cont = ['abertas' => 0, 'todas' => 0] + array_fill_keys(array_keys(ESTADOS), 0);
foreach (db()->query('SELECT estado, COUNT(*) n FROM marcacoes GROUP BY estado') as $r) {
    $cont[$r['estado']] = (int)$r['n'];
    $cont['todas'] += (int)$r['n'];
}
$cont['abertas'] = $cont['nova'] + $cont['confirmada'];

$hoje = (int)db()->query("SELECT COUNT(*) FROM marcacoes WHERE estado = 'confirmada' AND DATE(data_marcada) = CURDATE()")->fetchColumn();
$semana = (int)db()->query("SELECT COUNT(*) FROM marcacoes WHERE estado = 'confirmada' AND data_marcada >= CURDATE() AND data_marcada < CURDATE() + INTERVAL 7 DAY")->fetchColumn();
$mes = (int)db()->query("SELECT COUNT(*) FROM marcacoes WHERE criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();

// Lista
$where = [];
$args = [];
if ($estado === 'abertas') {
    $where[] = "estado IN ('nova','confirmada')";
} elseif (isset(ESTADOS[$estado])) {
    $where[] = 'estado = ?';
    $args[] = $estado;
} else {
    $estado = 'todas';
}
if ($q !== '') {
    $where[] = '(nome LIKE ? OR telemovel LIKE ? OR matricula LIKE ? OR carro LIKE ?)';
    $like = '%' . $q . '%';
    array_push($args, $like, $like, $like, $like);
}
$sql = 'SELECT * FROM marcacoes' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY (estado = 'nova') DESC, COALESCE(data_marcada, data_preferida, criado_em) ASC, id DESC LIMIT 300";
if (in_array($estado, ['concluida', 'cancelada', 'todas'], true)) {
    $sql = str_replace("ORDER BY (estado = 'nova') DESC, COALESCE(data_marcada, data_preferida, criado_em) ASC", 'ORDER BY criado_em DESC', $sql);
}
$st = db()->prepare($sql);
$st->execute($args);
$lista = $st->fetchAll();

$fmt = fn(?string $d, string $f = 'd/m/Y') => $d ? date($f, strtotime($d)) : '';

topo('Marcações', 'marcacoes');
?>
<div class="head">
  <div><h1>Marcações</h1><p class="muted">Pedidos de orçamento e marcações que chegam pelo site.</p></div>
  <a class="btn primary" href="marcacao.php?novo=1">+ Nova marcação</a>
</div>

<div class="stats">
  <div class="stat<?= $cont['nova'] ? ' hi' : '' ?>"><b><?= $cont['nova'] ?></b><span>pedidos novos por responder</span></div>
  <div class="stat"><b><?= $hoje ?></b><span>carros marcados para hoje</span></div>
  <div class="stat"><b><?= $semana ?></b><span>marcados nos próximos 7 dias</span></div>
  <div class="stat"><b><?= $mes ?></b><span>pedidos recebidos este mês</span></div>
</div>

<div class="toolbar">
  <nav class="tabs">
    <?php foreach (['abertas' => 'Em aberto'] + ESTADOS + ['todas' => 'Todas'] as $k => $txt): ?>
      <a href="?estado=<?= $k ?>" class="<?= $estado === $k ? 'on' : '' ?>"><?= h($txt) ?><span><?= $cont[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <form class="search" method="get">
    <input type="hidden" name="estado" value="<?= h($estado) ?>">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Nome, telemóvel, matrícula…" aria-label="Pesquisar">
    <button class="btn">Pesquisar</button>
  </form>
</div>

<div class="table-wrap">
<?php if (!$lista): ?>
  <p class="empty"><?= $q !== '' ? 'Nenhuma marcação encontrada.' : 'Sem marcações nesta lista.' ?></p>
<?php else: ?>
  <table class="list">
    <thead><tr><th>Cliente</th><th>Carro</th><th>Serviços</th><th>Data</th><th>Estado</th><th>Recebido</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $m): ?>
      <tr class="click <?= $m['estado'] === 'nova' ? 'nova' : '' ?>" onclick="location.href='marcacao.php?id=<?= (int)$m['id'] ?>'">
        <td><a href="marcacao.php?id=<?= (int)$m['id'] ?>" style="color:inherit;font-weight:600;text-decoration:none"><?= h($m['nome']) ?></a><span class="sub num"><?= h($m['telemovel']) ?></span></td>
        <td><?= h($m['carro'] ?: '—') ?><?php if ($m['matricula']): ?><span class="sub num"><?= h($m['matricula']) ?></span><?php endif; ?></td>
        <td><?= h($m['servicos']) ?></td>
        <td class="num">
          <?php if ($m['data_marcada']): ?><b><?= $fmt($m['data_marcada'], 'd/m/Y H:i') ?></b><span class="sub">marcada</span>
          <?php elseif ($m['data_preferida']): ?><?= $fmt($m['data_preferida']) ?><span class="sub">preferida</span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><span class="pill <?= h($m['estado']) ?>"><?= h(ESTADOS[$m['estado']]) ?></span></td>
        <td class="num"><?= $fmt($m['criado_em'], 'd/m H:i') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php fundo();
