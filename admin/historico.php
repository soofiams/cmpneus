<?php
require __DIR__ . '/_layout.php';
garantir_historico();

$hoje = date('Y-m-d');
$fmtKm = fn($km) => $km === null || $km === '' ? '' : number_format((int)$km, 0, ',', ' ') . ' km';
$fmtData = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '';

// ---------- Dados do último serviço de uma matrícula (para preencher o formulário) ----------
if (isset($_GET['dados'])) {
    $mat = normalizar_matricula((string)$_GET['dados']);
    $st = db()->prepare('SELECT cliente, telemovel, email, carro, km FROM historico WHERE matricula = ? ORDER BY data DESC, id DESC LIMIT 1');
    $st->execute([$mat]);
    $r = $st->fetch();
    if (!$r) {
        $st = db()->prepare('SELECT nome AS cliente, telemovel, email, carro FROM marcacoes WHERE matricula = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$mat]);
        $r = $st->fetch();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($r ?: null);
    exit;
}

// ---------- Filtros da pesquisa ----------
$q   = trim((string)($_GET['q'] ?? ''));
$de  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['de'] ?? '') ? $_GET['de'] : '';
$ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['ate'] ?? '') ? $_GET['ate'] : '';
$where = [];
$args = [];
if ($q !== '') {
    $limpa = preg_replace('/[^A-Za-z0-9]/', '', $q);
    $where[] = "(cliente LIKE ? OR telemovel LIKE ? OR carro LIKE ? OR servico LIKE ?" . ($limpa !== '' ? " OR REPLACE(matricula, '-', '') LIKE ?" : '') . ')';
    array_push($args, "%$q%", "%$q%", "%$q%", "%$q%");
    if ($limpa !== '') $args[] = '%' . $limpa . '%';
}
if ($de !== '')  { $where[] = 'data >= ?'; $args[] = $de; }
if ($ate !== '') { $where[] = 'data <= ?'; $args[] = $ate; }
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// ---------- Exportar para Excel ----------
if (isset($_GET['exportar'])) {
    $st = db()->prepare("SELECT * FROM historico$sqlWhere ORDER BY data DESC, id DESC");
    $st->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="historico-servicos-' . $hoje . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Data', 'Cliente', 'Telemóvel', 'Email', 'Matrícula', 'Carro', 'Km', 'Serviço', 'Descrição', 'Valor (€)', 'Próxima revisão', 'Próxima revisão (km)', 'Lembrete enviado'], ';');
    foreach ($st as $r) {
        fputcsv($out, [$fmtData($r['data']), $r['cliente'], $r['telemovel'], $r['email'], $r['matricula'], $r['carro'], $r['km'], $r['servico'], $r['descricao'],
            $r['valor'] !== null ? number_format((float)$r['valor'], 2, ',', '') : '', $fmtData($r['proxima_data']), $r['proxima_km'],
            $r['lembrete_enviado_em'] ? date('d/m/Y H:i', strtotime($r['lembrete_enviado_em'])) : ''], ';');
    }
    exit;
}

// ---------- Ações ----------
$erro = '';
$editar = null;
$qs = $_SERVER['QUERY_STRING'] ?? '';
$voltar = 'historico.php' . ($qs !== '' && !isset($_GET['editar']) && !isset($_GET['novo']) && !isset($_GET['marcacao']) ? '?' . $qs : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $volta = (string)($_POST['voltar'] ?? 'historico.php');
    if (!preg_match('/^historico\.php(\?[^\s<>"]*)?$/', $volta)) $volta = 'historico.php';

    $buscar = function (int $id): ?array {
        $st = db()->prepare('SELECT * FROM historico WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    };

    if ($acao === 'apagar' && $id) {
        db()->prepare('DELETE FROM historico WHERE id = ?')->execute([$id]);
        flash('Registo apagado.');
        redirecionar($volta);
    }
    if ($acao === 'lembrete' && ($h = $buscar($id))) {
        enviar_lembrete($h)
            ? flash("Lembrete enviado para {$h['email']}.")
            : flash('O lembrete não foi enviado. ' . email_erro(), 'err');
        redirecionar($volta);
    }
    if ($acao === 'avisado' && ($h = $buscar($id))) {
        db()->prepare('UPDATE historico SET lembrete_enviado_em = NOW() WHERE id = ?')->execute([$id]);
        flash("{$h['cliente']} marcado como avisado.");
        redirecionar($volta);
    }
    if ($acao === 'lembretes_todos') {
        @set_time_limit(0);
        $ok = $falha = 0;
        $ultimoErro = '';
        foreach (revisoes_proximas() as $h) {
            if (!filter_var($h['email'], FILTER_VALIDATE_EMAIL)) continue;
            if (enviar_lembrete($h)) { $ok++; } else { $falha++; $ultimoErro = email_erro(); }
            if ($falha >= 3 && $ok === 0) break;
        }
        if ($ok === 0 && $falha > 0) flash('Os lembretes não foram enviados. ' . $ultimoErro, 'err');
        elseif ($ok === 0) flash('Não havia lembretes por email para enviar.');
        else flash("Lembretes enviados: $ok" . ($falha ? ", $falha falharam ($ultimoErro)" : '') . '.', $falha ? 'err' : 'ok');
        redirecionar($volta);
    }
    if ($acao === 'guardar') {
        $txt = fn(string $k, int $max) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
        $num = function (string $k): ?int {
            $d = preg_replace('/\D/', '', (string)($_POST[$k] ?? ''));
            return $d === '' ? null : (int)$d;
        };
        $data = fn(string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST[$k] ?? '') ? $_POST[$k] : null;
        $valorTxt = str_replace([' ', '€'], '', (string)($_POST['valor'] ?? ''));
        $valorTxt = str_contains($valorTxt, ',') ? str_replace(['.', ','], ['', '.'], $valorTxt) : $valorTxt;

        $v = [
            'marcacao_id'  => ((int)($_POST['marcacao_id'] ?? 0)) ?: null,
            'cliente'      => $txt('cliente', 100),
            'telemovel'    => $txt('telemovel', 30),
            'email'        => $txt('email', 150),
            'matricula'    => normalizar_matricula($txt('matricula', 20)),
            'carro'        => $txt('carro', 100),
            'data'         => $data('data'),
            'km'           => $num('km'),
            'servico'      => $txt('servico', 500),
            'descricao'    => $txt('descricao', 5000),
            'valor'        => is_numeric($valorTxt) ? round((float)$valorTxt, 2) : null,
            'proxima_data' => $data('proxima_data'),
            'proxima_km'   => $num('proxima_km'),
        ];
        if ($v['cliente'] === '' || $v['servico'] === '') $erro = 'Preencha o nome do cliente e o serviço feito.';
        elseif (!$v['data']) $erro = 'Indique a data do serviço.';
        elseif ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $erro = 'O email não é válido.';
        elseif ($v['proxima_data'] && $v['proxima_data'] <= $v['data']) $erro = 'A próxima revisão tem de ser depois da data do serviço.';

        if ($erro) {
            $editar = $v + ['id' => $id, 'lembrete_enviado_em' => null];
        } else {
            $atual = $id ? $buscar($id) : null;
            // Se mudar a data da próxima revisão, volta a poder enviar lembrete
            $resetLembrete = !$atual || $atual['proxima_data'] !== $v['proxima_data'] || !empty($_POST['voltar_avisar']);
            $cols = array_keys($v);
            if ($atual) {
                $sql = 'UPDATE historico SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ($resetLembrete ? ', lembrete_enviado_em = NULL' : '') . ' WHERE id = ?';
                db()->prepare($sql)->execute([...array_values($v), $id]);
                flash('Registo guardado.');
            } else {
                db()->prepare('INSERT INTO historico (' . implode(', ', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($v));
                // Se veio de uma marcação, fecha-a
                if ($v['marcacao_id']) {
                    db()->prepare("UPDATE marcacoes SET estado = 'concluida' WHERE id = ? AND estado IN ('nova','confirmada')")->execute([$v['marcacao_id']]);
                }
                flash('Serviço registado no histórico' . ($v['marcacao_id'] ? ' e marcação marcada como concluída' : '') . '.');
            }
            redirecionar($v['matricula'] ? 'historico.php?q=' . rawurlencode($v['matricula']) : 'historico.php');
        }
    }
}

// ---------- Formulário: editar / novo / a partir de uma marcação ----------
if (!$editar && isset($_GET['editar'])) {
    $st = db()->prepare('SELECT * FROM historico WHERE id = ?');
    $st->execute([(int)$_GET['editar']]);
    $editar = $st->fetch() ?: null;
}
if (!$editar && (isset($_GET['novo']) || isset($_GET['marcacao']))) {
    $editar = ['id' => 0, 'marcacao_id' => null, 'cliente' => '', 'telemovel' => '', 'email' => '', 'matricula' => '', 'carro' => '',
               'data' => $hoje, 'km' => null, 'servico' => '', 'descricao' => '', 'valor' => null, 'proxima_data' => null, 'proxima_km' => null,
               'lembrete_enviado_em' => null];
    if (!empty($_GET['mat'])) {
        $st = db()->prepare('SELECT cliente, telemovel, email, matricula, carro FROM historico WHERE matricula = ? ORDER BY data DESC, id DESC LIMIT 1');
        $st->execute([normalizar_matricula((string)$_GET['mat'])]);
        if ($ult = $st->fetch()) $editar = array_merge($editar, $ult);
    }
    if (isset($_GET['marcacao'])) {
        $st = db()->prepare('SELECT * FROM marcacoes WHERE id = ?');
        $st->execute([(int)$_GET['marcacao']]);
        if ($m = $st->fetch()) {
            $kmM = preg_replace('/\D/', '', $m['km']);
            $editar = array_merge($editar, [
                'marcacao_id' => (int)$m['id'], 'cliente' => $m['nome'], 'telemovel' => $m['telemovel'], 'email' => $m['email'],
                'matricula' => $m['matricula'] ? normalizar_matricula($m['matricula']) : '', 'carro' => $m['carro'],
                'data' => $m['data_marcada'] && substr($m['data_marcada'], 0, 10) <= $hoje ? substr($m['data_marcada'], 0, 10) : $hoje,
                'km' => $kmM !== '' ? (int)$kmM : null, 'servico' => $m['servicos'],
                'descricao' => trim(($m['medida_pneu'] ? "Pneus: {$m['medida_pneu']}\n" : '') . $m['notas']),
            ]);
        }
    }
}

// ---------- Listas ----------
$proximas = revisoes_proximas();
$comEmail = count(array_filter($proximas, fn($h) => filter_var($h['email'], FILTER_VALIDATE_EMAIL)));

$st = db()->prepare("SELECT * FROM historico$sqlWhere ORDER BY data DESC, id DESC LIMIT 500");
$st->execute($args);
$lista = $st->fetchAll();
$st = db()->prepare("SELECT COUNT(*), COALESCE(SUM(valor), 0) FROM historico$sqlWhere");
$st->execute($args);
[$total, $soma] = $st->fetch(PDO::FETCH_NUM);

// Se a pesquisa corresponde a um único carro, mostra o resumo dele
$carro = null;
$mats = array_values(array_unique(array_filter(array_column($lista, 'matricula'))));
if ($q !== '' && count($mats) === 1 && count($lista) > 0) {
    $carro = ['matricula' => $mats[0], 'carro' => $lista[0]['carro'], 'cliente' => $lista[0]['cliente'], 'km' => $lista[0]['km'],
              'data' => $lista[0]['data'], 'servicos' => count($lista), 'proxima_data' => $lista[0]['proxima_data'], 'proxima_km' => $lista[0]['proxima_km']];
}

$matriculas = db()->query("SELECT DISTINCT matricula FROM historico WHERE matricula <> '' ORDER BY matricula LIMIT 1000")->fetchAll(PDO::FETCH_COLUMN);
$nomesServicos = array_unique(array_merge(
    db()->query('SELECT nome FROM revisoes WHERE ativo = 1 ORDER BY ordem')->fetchAll(PDO::FETCH_COLUMN),
    db()->query('SELECT nome FROM precos WHERE ativo = 1 ORDER BY ordem')->fetchAll(PDO::FETCH_COLUMN),
    db()->query('SELECT nome FROM servicos WHERE ativo = 1 ORDER BY ordem')->fetchAll(PDO::FETCH_COLUMN)
));

$waLink = function (array $h): string {
    $tel = digitos($h['telemovel']);
    if (strlen($tel) === 9) $tel = '351' . $tel;
    $txt = preg_replace('/\R{2,}/', "\n\n", texto_lembrete(def('lembrete_texto'), $h));
    return 'https://wa.me/' . $tel . '?text=' . rawurlencode($txt);
};

topo('Histórico de serviços', 'historico');
?>
<div class="head">
  <div><h1>Histórico de serviços</h1><p class="muted">O que foi feito a cada carro, com data e quilómetros. Pesquise por cliente, matrícula ou datas.</p></div>
  <?php if (!$editar): ?><a class="btn primary" href="?novo=1">+ Registar serviço</a><?php endif; ?>
</div>

<?php if ($editar): ?>
<div class="card" style="margin-bottom:22px">
  <h2><?= $editar['id'] ? 'Editar serviço' : 'Registar serviço feito' ?></h2>
  <?php if ($editar['marcacao_id'] && !$editar['id']): ?><p class="alert ok" style="font-size:.9rem">Dados preenchidos a partir da marcação n.º <?= (int)$editar['marcacao_id'] ?>. Confirme os quilómetros e o que foi feito.</p><?php endif; ?>
  <?php if ($erro): ?><p class="alert err"><?= h($erro) ?></p><?php endif; ?>
  <form method="post" class="grid2" id="hform">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="guardar">
    <input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
    <input type="hidden" name="marcacao_id" value="<?= (int)$editar['marcacao_id'] ?>">

    <label>Matrícula<small>Se o carro já veio cá, os dados do cliente são preenchidos sozinhos.</small>
      <input name="matricula" id="h-mat" list="lista-mat" maxlength="12" value="<?= h($editar['matricula']) ?>" placeholder="AA-00-AA" style="font-family:var(--mono);text-transform:uppercase" autocomplete="off">
    </label>
    <label>Marca e modelo<input name="carro" id="h-carro" maxlength="100" value="<?= h($editar['carro']) ?>"></label>
    <label>Cliente<input name="cliente" id="h-cliente" required maxlength="100" value="<?= h($editar['cliente']) ?>"></label>
    <label>Telemóvel<input name="telemovel" id="h-tel" maxlength="30" value="<?= h($editar['telemovel']) ?>"></label>
    <label class="full">Email<small>Necessário para enviar o lembrete da próxima revisão por email.</small><input name="email" id="h-email" type="email" maxlength="150" value="<?= h($editar['email']) ?>"></label>

    <label>Data do serviço<input name="data" id="h-data" type="date" required value="<?= h($editar['data']) ?>" max="<?= $hoje ?>"></label>
    <label>Quilómetros<input name="km" id="h-km" inputmode="numeric" value="<?= $editar['km'] !== null ? h(number_format((int)$editar['km'], 0, ',', ' ')) : '' ?>" placeholder="148 000"></label>
    <label class="full">Serviço feito<input name="servico" list="lista-serv" required maxlength="500" value="<?= h($editar['servico']) ?>" placeholder="Ex.: Revisão completa, 2 pneus da frente"></label>
    <label class="full">Detalhes<small>Peças trocadas, marca dos pneus, óleo usado, observações para a próxima vez…</small><textarea name="descricao" maxlength="5000"><?= h($editar['descricao']) ?></textarea></label>
    <label>Valor cobrado (€)<small>Opcional.</small><input name="valor" inputmode="decimal" value="<?= $editar['valor'] !== null ? h(rtrim(rtrim(number_format((float)$editar['valor'], 2, ',', ''), '0'), ',')) : '' ?>"></label>
    <div></div>

    <div class="full" style="border-top:1px solid var(--line);padding-top:16px;margin-top:4px">
      <div class="row" style="justify-content:space-between">
        <b>Próxima revisão</b>
        <button type="button" class="btn sm" id="h-calc">Calcular (+<?= (int)def('revisao_meses', '12') ?> meses / +<?= h(number_format((int)def('revisao_km', '15000'), 0, ',', ' ')) ?> km)</button>
      </div>
      <p class="muted" style="margin:6px 0 0;font-size:.88rem">Deixe vazio se este serviço não precisar de lembrete.</p>
    </div>
    <label>Data prevista<input name="proxima_data" id="h-pdata" type="date" value="<?= h($editar['proxima_data']) ?>"></label>
    <label>Aos quilómetros<input name="proxima_km" id="h-pkm" inputmode="numeric" value="<?= $editar['proxima_km'] !== null ? h(number_format((int)$editar['proxima_km'], 0, ',', ' ')) : '' ?>"></label>
    <?php if ($editar['lembrete_enviado_em']): ?>
      <label class="check full"><input type="checkbox" name="voltar_avisar" value="1"> Lembrete já enviado a <?= date('d/m/Y', strtotime($editar['lembrete_enviado_em'])) ?>. Voltar a mostrar em "Revisões a aproximar-se".</label>
    <?php endif; ?>

    <div class="form-actions full">
      <button class="btn primary" type="submit">Guardar</button>
      <a class="btn" href="historico.php">Cancelar</a>
    </div>
  </form>
  <datalist id="lista-mat"><?php foreach ($matriculas as $mt): ?><option value="<?= h($mt) ?>"><?php endforeach; ?></datalist>
  <datalist id="lista-serv"><?php foreach ($nomesServicos as $ns): ?><option value="<?= h($ns) ?>"><?php endforeach; ?></datalist>
</div>
<script>
(function(){
  var meses = <?= (int)def('revisao_meses', '12') ?>, kmMais = <?= (int)def('revisao_km', '15000') ?>;
  var $ = function(id){ return document.getElementById(id); };
  var fmt = function(n){ return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); };
  function calcular(forcar){
    var d = $('h-data').value, km = parseInt(($('h-km').value || '').replace(/\D/g, ''), 10);
    if (d && (forcar || !$('h-pdata').value)) {
      var p = d.split('-'), dt = new Date(+p[0], +p[1] - 1 + meses, +p[2]);
      $('h-pdata').value = dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
    }
    if (km && (forcar || !$('h-pkm').value)) $('h-pkm').value = fmt(km + kmMais);
  }
  $('h-calc').addEventListener('click', function(){ calcular(true); });
  $('h-km').addEventListener('change', function(){ calcular(false); });
  // Preenche os dados do cliente a partir da matrícula
  $('h-mat').addEventListener('change', function(){
    var m = this.value.trim(); if (!m) return;
    fetch('historico.php?dados=' + encodeURIComponent(m)).then(function(r){ return r.json(); }).then(function(d){
      if (!d) return;
      [['h-cliente','cliente'],['h-tel','telemovel'],['h-email','email'],['h-carro','carro']].forEach(function(p){
        if (!$(p[0]).value && d[p[1]]) $(p[0]).value = d[p[1]];
      });
    }).catch(function(){});
  });
})();
</script>
<?php endif; ?>

<?php if (!$editar): ?>
<div class="card" style="margin-bottom:22px">
  <div class="row" style="justify-content:space-between;margin-bottom:12px">
    <h2 style="margin:0">Revisões a aproximar-se</h2>
    <?php if ($comEmail): ?>
      <form method="post" onsubmit="return confirm('Enviar o lembrete por email a <?= $comEmail ?> cliente(s)?')">
        <?= csrf_field() ?><input type="hidden" name="acao" value="lembretes_todos"><input type="hidden" name="voltar" value="<?= h($voltar) ?>">
        <button class="btn primary">Enviar email a todos (<?= $comEmail ?>)</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if (!$proximas): ?>
    <p class="muted" style="margin:0">Nenhum carro com revisão nos próximos <?= (int)def('lembrete_dias_antes', '30') ?> dias por avisar.</p>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table class="list">
      <thead><tr><th>Carro</th><th>Cliente</th><th>Próxima revisão</th><th>Último serviço</th><th>Avisar</th></tr></thead>
      <tbody>
      <?php foreach ($proximas as $h):
          $atraso = $h['proxima_data'] < $hoje; ?>
        <tr>
          <td><a href="?q=<?= rawurlencode($h['matricula']) ?>" class="num" style="font-weight:600"><?= h($h['matricula'] ?: '—') ?></a><span class="sub"><?= h($h['carro']) ?></span></td>
          <td><?= h($h['cliente']) ?><span class="sub num"><?= h($h['telemovel']) ?></span></td>
          <td class="num"><b style="<?= $atraso ? 'color:var(--err)' : '' ?>"><?= $fmtData($h['proxima_data']) ?></b><span class="sub"><?= $atraso ? 'já passou · ' : '' ?><?= $fmtKm($h['proxima_km']) ?></span></td>
          <td><?= h(mb_strimwidth($h['servico'], 0, 50, '…')) ?><span class="sub num"><?= $fmtData($h['data']) ?> · <?= $fmtKm($h['km']) ?></span></td>
          <td>
            <div class="row" style="flex-wrap:nowrap">
              <?php if (filter_var($h['email'], FILTER_VALIDATE_EMAIL)): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="lembrete"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="voltar" value="<?= h($voltar) ?>">
                  <button class="btn sm primary" title="Enviar para <?= h($h['email']) ?>">Email</button></form>
              <?php endif; ?>
              <?php if (strlen(digitos($h['telemovel'])) >= 9): ?>
                <a class="btn sm wa" href="<?= h($waLink($h)) ?>" target="_blank" rel="noopener">WhatsApp</a>
              <?php endif; ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="avisado"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="voltar" value="<?= h($voltar) ?>">
                <button class="btn sm" title="Tirar da lista (por exemplo, depois de avisar por WhatsApp ou telefone)">Já avisei</button></form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<form method="get" class="card" style="margin-bottom:16px">
  <div class="search-grid">
    <label>Pesquisar<input type="search" name="q" value="<?= h($q) ?>" placeholder="Nome do cliente, matrícula, telemóvel ou serviço"></label>
    <label>De<input type="date" name="de" value="<?= h($de) ?>"></label>
    <label>Até<input type="date" name="ate" value="<?= h($ate) ?>"></label>
    <div class="row" style="flex-wrap:nowrap">
      <button class="btn primary">Pesquisar</button>
      <?php if ($q !== '' || $de || $ate): ?><a class="btn" href="historico.php">Limpar</a><?php endif; ?>
    </div>
  </div>
</form>

<?php if ($carro): ?>
<div class="card" style="margin-bottom:16px;display:flex;gap:24px;flex-wrap:wrap;align-items:center">
  <div><span class="num" style="font-size:1.4rem;font-weight:600;letter-spacing:.06em;background:var(--bg);border:1px solid var(--line);padding:4px 10px;border-radius:6px"><?= h($carro['matricula']) ?></span></div>
  <div><b><?= h($carro['carro'] ?: '—') ?></b><span class="sub muted"><?= h($carro['cliente']) ?></span></div>
  <div><b><?= $carro['servicos'] ?></b> <span class="muted">serviço<?= $carro['servicos'] > 1 ? 's' : '' ?></span></div>
  <div><span class="muted">Último:</span> <b><?= $fmtData($carro['data']) ?></b><?= $carro['km'] ? ' · ' . $fmtKm($carro['km']) : '' ?></div>
  <?php if ($carro['proxima_data']): ?><div><span class="muted">Próxima revisão:</span> <b><?= $fmtData($carro['proxima_data']) ?></b><?= $carro['proxima_km'] ? ' · ' . $fmtKm($carro['proxima_km']) : '' ?></div><?php endif; ?>
  <a class="btn sm" style="margin-left:auto" href="?novo=1&amp;mat=<?= rawurlencode($carro['matricula']) ?>">+ Novo serviço a este carro</a>
</div>
<?php endif; ?>

<div class="toolbar">
  <p class="muted" style="margin:0"><?= (int)$total ?> registo<?= $total == 1 ? '' : 's' ?><?= $soma > 0 ? ' · total ' . euros($soma) : '' ?><?= $total > 500 ? ' (a mostrar os 500 mais recentes)' : '' ?></p>
  <?php if ($total): ?><a class="btn sm" href="?<?= h(http_build_query(['q' => $q, 'de' => $de, 'ate' => $ate, 'exportar' => 1])) ?>">Exportar (Excel)</a><?php endif; ?>
</div>
<div class="table-wrap">
<?php if (!$lista): ?>
  <p class="empty"><?= ($q !== '' || $de || $ate) ? 'Nenhum serviço encontrado com essa pesquisa.' : 'Ainda não há serviços registados. Use "Registar serviço" ou, numa marcação, "Registar serviço no histórico".' ?></p>
<?php else: ?>
  <table class="list">
    <thead><tr><th>Data</th><th>Carro</th><th>Cliente</th><th>Km</th><th>Serviço</th><th>Valor</th><th>Próxima revisão</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $r): ?>
      <tr>
        <td class="num"><?= $fmtData($r['data']) ?></td>
        <td><?php if ($r['matricula']): ?><a href="?q=<?= rawurlencode($r['matricula']) ?>" class="num" style="font-weight:600"><?= h($r['matricula']) ?></a><?php else: ?>—<?php endif; ?><span class="sub"><?= h($r['carro']) ?></span></td>
        <td><?= h($r['cliente']) ?><span class="sub num"><?= h($r['telemovel']) ?></span></td>
        <td class="num"><?= $fmtKm($r['km']) ?: '—' ?></td>
        <td><?= h($r['servico']) ?><?php if ($r['descricao']): ?><span class="sub"><?= h(mb_strimwidth($r['descricao'], 0, 90, '…')) ?></span><?php endif; ?></td>
        <td class="num"><?= $r['valor'] !== null ? euros($r['valor']) : '—' ?></td>
        <td class="num"><?= $fmtData($r['proxima_data']) ?: '—' ?><span class="sub"><?= $fmtKm($r['proxima_km']) ?><?= $r['lembrete_enviado_em'] ? ($r['proxima_km'] ? ' · ' : '') . 'avisado' : '' ?></span></td>
        <td>
          <div class="row" style="flex-wrap:nowrap">
            <a class="btn sm" href="?editar=<?= (int)$r['id'] ?>">Editar</a>
            <form method="post" onsubmit="return confirm('Apagar este registo do histórico?')"><?= csrf_field() ?><input type="hidden" name="acao" value="apagar"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="voltar" value="<?= h($voltar) ?>">
              <button class="btn sm danger">Apagar</button></form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php endif; ?>
<?php fundo();
