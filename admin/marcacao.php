<?php
require __DIR__ . '/_layout.php';

$id   = (int)($_GET['id'] ?? 0);
$novo = isset($_GET['novo']);
$m = null;
if ($id) {
    $st = db()->prepare('SELECT * FROM marcacoes WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) { flash('Essa marcação já não existe.', 'err'); redirecionar('index.php'); }
} elseif (!$novo) {
    redirecionar('index.php');
}

$campos = ['nome' => 100, 'telemovel' => 30, 'email' => 150, 'matricula' => 12, 'carro' => 100, 'ano' => 4, 'km' => 20,
           'servicos' => 500, 'medida_pneu' => 40, 'mensagem' => 2000, 'notas' => 5000];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['acao'] ?? '') === 'apagar' && $m) {
        db()->prepare('DELETE FROM marcacoes WHERE id = ?')->execute([$id]);
        flash('Marcação de ' . $m['nome'] . ' apagada.');
        redirecionar('index.php');
    }
    $v = [];
    foreach ($campos as $k => $max) $v[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    $v['matricula'] = mb_strtoupper($v['matricula']);
    $v['estado'] = isset(ESTADOS[$_POST['estado'] ?? '']) ? $_POST['estado'] : 'nova';
    $dp = $_POST['data_preferida'] ?? '';
    $v['data_preferida'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dp) ? $dp : null;
    $dm = $_POST['data_marcada'] ?? '';
    $v['data_marcada'] = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dm) ? str_replace('T', ' ', $dm) . ':00' : null;

    if ($v['nome'] === '' || strlen(digitos($v['telemovel'])) < 9) {
        flash('Indique o nome e um telemóvel com pelo menos 9 dígitos.', 'err');
        $m = array_merge($m ?? [], $v);
    } else {
        $cols = array_keys($v);
        if ($m) {
            $sql = 'UPDATE marcacoes SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?';
            db()->prepare($sql)->execute([...array_values($v), $id]);
            flash('Alterações guardadas.');
        } else {
            $sql = 'INSERT INTO marcacoes (' . implode(', ', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
            db()->prepare($sql)->execute(array_values($v));
            $id = (int)db()->lastInsertId();
            flash('Marcação criada.');
        }
        redirecionar('marcacao.php?id=' . $id);
    }
}

$m = $m ?? array_fill_keys(array_keys($campos), '') + ['estado' => 'confirmada', 'data_preferida' => null, 'data_marcada' => null];
$val = fn($k) => h((string)($m[$k] ?? ''));

// Mensagem pronta para WhatsApp
$oficina = def('nome_oficina', 'CMPNEUS');
$primeiro = explode(' ', trim($m['nome'] ?? ''))[0] ?? '';
if (!empty($m['data_marcada'])) {
    $t = strtotime($m['data_marcada']);
    $waTxt = "Olá $primeiro, confirmamos a marcação" . ($m['carro'] ? " do seu {$m['carro']}" : '') . ' para ' . date('d/m', $t) . ' às ' . date('H:i', $t) . " na $oficina. Até lá!";
} else {
    $waTxt = "Olá $primeiro, fala a $oficina. Recebemos o seu pedido de orçamento" . ($m['servicos'] ? " ({$m['servicos']})" : '') . '.';
}
$telDig = digitos((string)($m['telemovel'] ?? ''));
if (strlen($telDig) === 9) $telDig = '351' . $telDig;

topo($m['nome'] ?: 'Nova marcação', 'marcacoes');
?>
<div class="head">
  <div>
    <a href="index.php" class="muted" style="text-decoration:none">← Marcações</a>
    <h1 style="margin-top:8px"><?= $id ? h($m['nome']) : 'Nova marcação' ?></h1>
    <?php if ($id): ?><p class="muted">Pedido n.º <?= $id ?> · recebido a <?= date('d/m/Y \à\s H:i', strtotime($m['criado_em'])) ?></p><?php endif; ?>
  </div>
  <?php if ($id): ?><span class="pill <?= h($m['estado']) ?>"><?= h(ESTADOS[$m['estado']]) ?></span><?php endif; ?>
</div>

<form method="post" class="detail">
  <?= csrf_field() ?>
  <div class="card">
    <h2>Cliente e carro</h2>
    <div class="grid2">
      <label>Nome<input name="nome" required maxlength="100" value="<?= $val('nome') ?>"></label>
      <label>Telemóvel<input name="telemovel" required maxlength="30" value="<?= $val('telemovel') ?>"></label>
      <label class="full">Email<input name="email" type="email" maxlength="150" value="<?= $val('email') ?>"></label>
      <label>Matrícula<input name="matricula" maxlength="12" value="<?= $val('matricula') ?>" style="font-family:var(--mono);text-transform:uppercase"></label>
      <label>Marca e modelo<input name="carro" maxlength="100" value="<?= $val('carro') ?>"></label>
      <label>Ano<input name="ano" maxlength="4" inputmode="numeric" value="<?= $val('ano') ?>"></label>
      <label>Quilómetros<input name="km" maxlength="20" value="<?= $val('km') ?>"></label>
      <label class="full">Serviços<input name="servicos" maxlength="500" value="<?= $val('servicos') ?>"></label>
      <label>Medida do pneu<input name="medida_pneu" maxlength="40" value="<?= $val('medida_pneu') ?>"></label>
      <label>Data preferida do cliente<input name="data_preferida" type="date" value="<?= $val('data_preferida') ?>"></label>
      <label class="full">Mensagem do cliente<textarea name="mensagem" maxlength="2000"><?= $val('mensagem') ?></textarea></label>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Estado e marcação</h2>
      <div class="stack">
        <label>Estado
          <select name="estado">
            <?php foreach (ESTADOS as $k => $txt): ?><option value="<?= $k ?>" <?= $m['estado'] === $k ? 'selected' : '' ?>><?= h($txt) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Dia e hora marcados<small>Preencha quando combinar com o cliente.</small>
          <input name="data_marcada" type="datetime-local" step="900" value="<?= $m['data_marcada'] ? date('Y-m-d\TH:i', strtotime($m['data_marcada'])) : '' ?>">
        </label>
        <label>Notas internas<small>Só aparecem aqui (ex.: orçamento dado, peças a encomendar).</small>
          <textarea name="notas" maxlength="5000"><?= $val('notas') ?></textarea>
        </label>
        <div class="form-actions"><button class="btn primary" type="submit"><?= $id ? 'Guardar alterações' : 'Criar marcação' ?></button></div>
      </div>
    </div>

    <?php if ($id): ?>
    <div class="card">
      <h2>Serviço feito?</h2>
      <p class="muted" style="margin:0 0 12px;font-size:.9rem">Guarde no histórico do carro o que foi feito, com a data e os quilómetros. Serve para pesquisar mais tarde e para avisar o cliente da próxima revisão.</p>
      <a class="btn primary" href="historico.php?marcacao=<?= $id ?>">Registar serviço no histórico</a>
    </div>
    <div class="card">
      <h2>Contactar cliente</h2>
      <div class="row">
        <a class="btn" href="tel:<?= h(digitos($m['telemovel'])) ?>">Ligar <?= h($m['telemovel']) ?></a>
        <a class="btn wa" href="https://wa.me/<?= h($telDig) ?>?text=<?= rawurlencode($waTxt) ?>" target="_blank" rel="noopener">WhatsApp</a>
        <?php if ($m['email']): ?><a class="btn" href="mailto:<?= h($m['email']) ?>">Email</a><?php endif; ?>
      </div>
      <p class="muted" style="margin:12px 0 0;font-size:.88rem">O WhatsApp abre com esta mensagem, que pode alterar antes de enviar:<br>“<?= h($waTxt) ?>”</p>
    </div>
    <?php endif; ?>
  </div>
</form>

<?php if ($id): ?>
<form method="post" style="margin-top:20px" onsubmit="return confirm('Apagar esta marcação? Não é possível desfazer.')">
  <?= csrf_field() ?><input type="hidden" name="acao" value="apagar">
  <button class="btn danger" type="submit">Apagar marcação</button>
</form>
<?php endif; ?>
<?php fundo();
