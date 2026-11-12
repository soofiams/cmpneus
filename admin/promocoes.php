<?php
require __DIR__ . '/_layout.php';

$pastaImg = __DIR__ . '/../uploads/promos/';
$hoje = date('Y-m-d');
$erro = '';
$editar = null;

/** Guarda a imagem enviada e devolve o nome do ficheiro ('' se não houver). Lança Exception com mensagem amigável. */
function guardar_imagem(string $pasta): string {
    $f = $_FILES['imagem'] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return '';
    if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('A imagem não foi enviada. Tente uma imagem mais pequena (máx. 3 MB).');
    if ($f['size'] > 3 * 1024 * 1024) throw new Exception('A imagem tem mais de 3 MB. Reduza-a e tente de novo.');
    $info = @getimagesize($f['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) throw new Exception('A imagem tem de ser JPG, PNG ou WEBP.');
    if (!is_dir($pasta) && !@mkdir($pasta, 0755, true)) throw new Exception('Não foi possível criar a pasta uploads/promos. Verifique as permissões.');
    $nome = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $pasta . $nome)) throw new Exception('Não foi possível guardar a imagem. Verifique as permissões da pasta uploads.');
    return $nome;
}
function apagar_imagem(string $pasta, string $nome): void {
    if ($nome !== '' && preg_match('/^[a-f0-9]{20}\.(jpg|png|webp)$/', $nome) && is_file($pasta . $nome)) @unlink($pasta . $nome);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $atual = null;
    if ($id) {
        $st = db()->prepare('SELECT * FROM promocoes WHERE id = ?');
        $st->execute([$id]);
        $atual = $st->fetch() ?: null;
    }

    if ($acao === 'apagar' && $atual) {
        apagar_imagem($pastaImg, $atual['imagem']);
        db()->prepare('DELETE FROM promocoes WHERE id = ?')->execute([$id]);
        flash('Promoção apagada.');
        redirecionar('promocoes.php');
    }
    if ($acao === 'ativo' && $atual) {
        db()->prepare('UPDATE promocoes SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        redirecionar('promocoes.php');
    }
    if ($acao === 'duplicar' && $atual) {
        // Copia para o mês seguinte ao fim da promoção original
        $ini = (new DateTime($atual['fim']))->modify('first day of next month');
        $fim = (clone $ini)->modify('last day of this month');
        db()->prepare('INSERT INTO promocoes (titulo, texto, destaque, inicio, fim, botao_texto, imagem, ativo) VALUES (?,?,?,?,?,?,?,0)')
            ->execute([$atual['titulo'], $atual['texto'], $atual['destaque'], $ini->format('Y-m-d'), $fim->format('Y-m-d'), $atual['botao_texto'], '']);
        flash('Promoção copiada para ' . $ini->format('m/Y') . '. Está desligada: reveja e ligue quando quiser.');
        redirecionar('promocoes.php?editar=' . db()->lastInsertId());
    }
    if ($acao === 'guardar') {
        $v = [
            'titulo'      => mb_substr(trim($_POST['titulo'] ?? ''), 0, 100),
            'destaque'    => mb_substr(trim($_POST['destaque'] ?? ''), 0, 30),
            'texto'       => mb_substr(trim($_POST['texto'] ?? ''), 0, 600),
            'inicio'      => $_POST['inicio'] ?? '',
            'fim'         => $_POST['fim'] ?? '',
            'botao_texto' => mb_substr(trim($_POST['botao_texto'] ?? ''), 0, 40) ?: 'Quero aproveitar',
            'ativo'       => isset($_POST['ativo']) ? 1 : 0,
        ];
        $dataOk = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
        if ($v['titulo'] === '' || $v['texto'] === '') $erro = 'Preencha o título e o texto da promoção.';
        elseif (!$dataOk($v['inicio']) || !$dataOk($v['fim'])) $erro = 'Indique a data de início e de fim.';
        elseif ($v['fim'] < $v['inicio']) $erro = 'A data de fim tem de ser depois da data de início.';

        $imagem = $atual['imagem'] ?? '';
        if (!$erro) {
            try {
                $nova = guardar_imagem($pastaImg);
                if ($nova !== '') { apagar_imagem($pastaImg, $imagem); $imagem = $nova; }
                elseif (!empty($_POST['remover_imagem'])) { apagar_imagem($pastaImg, $imagem); $imagem = ''; }
            } catch (Exception $e) {
                $erro = $e->getMessage();
            }
        }
        if ($erro) {
            $editar = $v + ['id' => $id, 'imagem' => $imagem];
        } else {
            $v['imagem'] = $imagem;
            $cols = array_keys($v);
            if ($atual) {
                db()->prepare('UPDATE promocoes SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?')->execute([...array_values($v), $id]);
                flash('Promoção guardada.');
            } else {
                db()->prepare('INSERT INTO promocoes (' . implode(', ', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($v));
                flash('Promoção criada.');
            }
            redirecionar('promocoes.php');
        }
    }
}

if (!$editar && isset($_GET['editar'])) {
    $st = db()->prepare('SELECT * FROM promocoes WHERE id = ?');
    $st->execute([(int)$_GET['editar']]);
    $editar = $st->fetch() ?: null;
}
if (!$editar && isset($_GET['novo'])) {
    $editar = ['id' => 0, 'titulo' => '', 'destaque' => '', 'texto' => '', 'botao_texto' => 'Quero aproveitar', 'imagem' => '', 'ativo' => 1,
               'inicio' => date('Y-m-01'), 'fim' => date('Y-m-t')];
}

$noAr = promo_ativa();
$lista = db()->query('SELECT * FROM promocoes ORDER BY inicio DESC, id DESC')->fetchAll();
$estado = function (array $p) use ($noAr, $hoje): array {
    if ($noAr && (int)$noAr['id'] === (int)$p['id']) return ['concluida', 'No site agora'];
    if (!$p['ativo']) return ['cancelada', 'Desligada'];
    if ($p['fim'] < $hoje) return ['cancelada', 'Terminada'];
    if ($p['inicio'] > $hoje) return ['confirmada', 'Agendada'];
    return ['nova', 'Ativa (há outra mais recente)'];
};

topo('Promoções', 'promocoes');
?>
<div class="head">
  <div><h1>Promoções</h1><p class="muted">A promoção ativa aparece numa janela no site e pode ser enviada na newsletter. Se houver várias ativas, aparece a que começou mais recentemente.</p></div>
  <?php if (!$editar): ?><a class="btn primary" href="?novo=1">+ Nova promoção</a><?php endif; ?>
</div>

<?php if (!$editar): ?>
  <?php if ($noAr): ?>
    <p class="alert ok">No site agora: <b><?= h($noAr['titulo']) ?></b>, até <?= date('d/m/Y', strtotime($noAr['fim'])) ?>.
      <a href="../index.php?verpromo" target="_blank">Ver a janela no site ↗</a></p>
  <?php else: ?>
    <p class="alert">Não há nenhuma promoção no site neste momento.</p>
  <?php endif; ?>
<?php endif; ?>

<?php if ($editar): ?>
<div class="card" style="margin-bottom:22px">
  <h2><?= $editar['id'] ? 'Editar promoção' : 'Nova promoção' ?></h2>
  <?php if ($erro): ?><p class="alert err"><?= h($erro) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="grid2">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="guardar"><input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
    <label>Título<input name="titulo" maxlength="100" required value="<?= h($editar['titulo']) ?>" placeholder="Ex.: Revisão completa em promoção"></label>
    <label>Destaque<small>Aparece em grande. Ex.: -20%, 39 €, Grátis</small><input name="destaque" maxlength="30" value="<?= h($editar['destaque']) ?>"></label>
    <label class="full">Texto<small>Condições e detalhes.</small><textarea name="texto" maxlength="600" required><?= h($editar['texto']) ?></textarea></label>
    <label>Começa a<input type="date" name="inicio" required value="<?= h($editar['inicio']) ?>"></label>
    <label>Termina a<input type="date" name="fim" required value="<?= h($editar['fim']) ?>"></label>
    <label>Texto do botão<input name="botao_texto" maxlength="40" value="<?= h($editar['botao_texto']) ?>"></label>
    <label>Imagem (opcional)<small>JPG, PNG ou WEBP até 3 MB. Fica no topo da janela.</small><input type="file" name="imagem" accept="image/jpeg,image/png,image/webp"></label>
    <?php if ($editar['imagem']): ?>
      <div class="full row">
        <img src="../uploads/promos/<?= h($editar['imagem']) ?>" alt="" style="width:160px;aspect-ratio:16/9;object-fit:cover;border-radius:8px;border:1px solid var(--line)">
        <label class="check"><input type="checkbox" name="remover_imagem" value="1"> Remover imagem</label>
      </div>
    <?php endif; ?>
    <label class="check full"><input type="checkbox" name="ativo" value="1" <?= $editar['ativo'] ? 'checked' : '' ?>> Ligada (aparece no site entre as datas indicadas)</label>
    <div class="form-actions full"><button class="btn primary" type="submit">Guardar promoção</button><a class="btn" href="promocoes.php">Cancelar</a></div>
  </form>
</div>
<?php endif; ?>

<div class="table-wrap">
<?php if (!$lista): ?><p class="empty">Ainda não há promoções.</p><?php else: ?>
  <table class="list">
    <thead><tr><th>Promoção</th><th>Datas</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $p): [$cls, $txt] = $estado($p); ?>
      <tr>
        <td><b><?= h($p['titulo']) ?></b><?php if ($p['destaque']): ?> <span class="pill nova" style="padding:3px 8px"><?= h($p['destaque']) ?></span><?php endif; ?>
          <span class="sub"><?= h(mb_strimwidth($p['texto'], 0, 100, '…')) ?></span></td>
        <td class="num"><?= date('d/m/Y', strtotime($p['inicio'])) ?><span class="sub">até <?= date('d/m/Y', strtotime($p['fim'])) ?></span></td>
        <td><span class="pill <?= $cls ?>"><?= h($txt) ?></span></td>
        <td>
          <div class="row" style="flex-wrap:nowrap">
            <a class="btn sm" href="?editar=<?= (int)$p['id'] ?>">Editar</a>
            <?php foreach (['ativo' => $p['ativo'] ? 'Desligar' : 'Ligar', 'duplicar' => 'Copiar p/ mês seguinte'] as $a => $rot): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="<?= $a ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn sm"><?= h($rot) ?></button></form>
            <?php endforeach; ?>
            <form method="post" onsubmit="return confirm('Apagar esta promoção?')"><?= csrf_field() ?><input type="hidden" name="acao" value="apagar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn sm danger">Apagar</button></form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php fundo();
