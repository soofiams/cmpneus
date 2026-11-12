<?php
require __DIR__ . '/_layout.php';

$erro = '';
$h = horario();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $novo = [];
    foreach (DIAS as $d => $nome) {
        $v = trim((string)($_POST['dia'][$d] ?? ''));
        if (!empty($_POST['fechado'][$d])) $v = '';
        if ($v !== '' && !intervalos($v)) $erro = "O horário de $nome não está no formato certo. Use, por exemplo: 09:00-13:00, 14:00-19:00";
        // Guarda num formato limpo
        $novo[$d] = implode(', ', array_map(fn($i) => sprintf('%02d:%02d-%02d:%02d', intdiv($i[0], 60), $i[0] % 60, intdiv($i[1], 60), $i[1] % 60), intervalos($v)));
    }
    if (!$erro) {
        $st = db()->prepare('INSERT INTO horario (dia, horas) VALUES (?, ?) ON DUPLICATE KEY UPDATE horas = VALUES(horas)');
        foreach ($novo as $d => $v) $st->execute([$d, $v]);
        flash('Horário guardado.');
        redirecionar('horario.php');
    }
    $h = array_map('strval', $_POST['dia'] ?? []) + $h;
}

topo('Horário', 'horario');
?>
<div class="head"><div><h1>Horário</h1><p class="muted">Usado na tabela de horário, na barra do topo e no aviso "Aberto agora".</p></div></div>
<?php if ($erro): ?><p class="alert err"><?= h($erro) ?></p><?php endif; ?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <p class="muted" style="margin-top:0">Escreva os períodos separados por vírgula. Ex.: <code>09:00-13:00, 14:00-19:00</code></p>
  <div class="hours-edit">
    <?php foreach (DIAS as $d => $nome): $fechado = trim($h[$d]) === ''; ?>
      <b><?= h($nome) ?></b>
      <input name="dia[<?= $d ?>]" value="<?= h($h[$d]) ?>" placeholder="Encerrado" aria-label="Horário de <?= h($nome) ?>">
      <label class="check"><input type="checkbox" name="fechado[<?= $d ?>]" value="1" <?= $fechado ? 'checked' : '' ?> onchange="this.closest('label').previousElementSibling.disabled=this.checked"> Encerrado</label>
    <?php endforeach; ?>
  </div>
  <div class="form-actions" style="margin-top:18px"><button class="btn primary" type="submit">Guardar horário</button></div>
</form>
<div class="card">
  <h2>Como aparece no site</h2>
  <table class="list" style="max-width:520px">
    <?php foreach (horario_agrupado() as [$d, $hs]): ?><tr><td><?= h($d) ?></td><td class="num"><?= h($hs) ?></td></tr><?php endforeach; ?>
  </table>
</div>
<script>document.querySelectorAll('.hours-edit input[type=checkbox]').forEach(function(c){c.closest('label').previousElementSibling.disabled=c.checked});</script>
<?php fundo();
