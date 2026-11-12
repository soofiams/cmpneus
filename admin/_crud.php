<?php
// Lista + formulário genéricos para Serviços, Preços e Revisões.
require_once __DIR__ . '/_layout.php';

/**
 * $cfg = [
 *   'tabela' => 'servicos', 'pagina' => 'servicos', 'titulo' => 'Serviços', 'singular' => 'serviço',
 *   'intro' => '...', 'campos' => [chave => ['rotulo', tipo, opções...]],
 *   'colunas' => [['Cabeçalho', fn($linha) => html], ...],
 * ]
 * Tipos: text, textarea, money, checkbox, icone
 */
function crud(array $cfg): void {
    $t = $cfg['tabela'];
    $self = $cfg['pagina'] . '.php';
    $campos = $cfg['campos'];
    $f = !empty($cfg['feminino']);
    $Sing = ucfirst($cfg['singular']);

    $todos = fn() => db()->query("SELECT * FROM `$t` ORDER BY ordem, id")->fetchAll();
    $renumerar = function () use ($t, $todos) {
        $i = 1;
        $st = db()->prepare("UPDATE `$t` SET ordem = ? WHERE id = ?");
        foreach ($todos() as $r) $st->execute([$i++, $r['id']]);
    };

    $erro = '';
    $editar = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $acao = $_POST['acao'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($acao === 'apagar' && $id) {
            db()->prepare("DELETE FROM `$t` WHERE id = ?")->execute([$id]);
            $renumerar();
            flash($Sing . ($f ? ' apagada.' : ' apagado.'));
            redirecionar($self);
        }
        if ($acao === 'ativo' && $id) {
            db()->prepare("UPDATE `$t` SET ativo = 1 - ativo WHERE id = ?")->execute([$id]);
            redirecionar($self);
        }
        if ($acao === 'mover' && $id) {
            $renumerar();
            $lista = $todos();
            $pos = array_search($id, array_map('intval', array_column($lista, 'id')), true);
            $alvo = $pos + (($_POST['dir'] ?? '') === 'cima' ? -1 : 1);
            if ($pos !== false && isset($lista[$alvo])) {
                $st = db()->prepare("UPDATE `$t` SET ordem = ? WHERE id = ?");
                $st->execute([$lista[$alvo]['ordem'], $lista[$pos]['id']]);
                $st->execute([$lista[$pos]['ordem'], $lista[$alvo]['id']]);
            }
            redirecionar($self);
        }
        if ($acao === 'guardar') {
            $v = [];
            foreach ($campos as $k => $c) {
                $tipo = $c[1];
                $raw = $_POST[$k] ?? '';
                if ($tipo === 'checkbox') {
                    $v[$k] = isset($_POST[$k]) ? 1 : 0;
                } elseif ($tipo === 'money') {
                    $n = str_replace([' ', '€'], '', (string)$raw);
                    $n = str_contains($n, ',') ? str_replace(['.', ','], ['', '.'], $n) : $n;
                    if (!is_numeric($n) || $n < 0) { $erro = 'O preço tem de ser um número (ex.: 59 ou 12,50).'; }
                    $v[$k] = round((float)$n, 2);
                } elseif ($tipo === 'icone') {
                    $v[$k] = isset(ICONES[$raw]) ? $raw : 'chave';
                } else {
                    $v[$k] = mb_substr(trim((string)$raw), 0, $c[2] ?? 255);
                }
                if (!empty($c['obrigatorio']) && $v[$k] === '') $erro = 'Preencha o campo "' . $c[0] . '".';
            }
            if ($erro) {
                $editar = $v + ['id' => $id];
            } else {
                $cols = array_keys($v);
                if ($id) {
                    db()->prepare("UPDATE `$t` SET " . implode(', ', array_map(fn($c) => "`$c` = ?", $cols)) . ' WHERE id = ?')
                        ->execute([...array_values($v), $id]);
                    flash($Sing . ($f ? ' guardada.' : ' guardado.'));
                } else {
                    $ordem = (int)db()->query("SELECT COALESCE(MAX(ordem),0)+1 FROM `$t`")->fetchColumn();
                    db()->prepare("INSERT INTO `$t` (" . implode(', ', array_map(fn($c) => "`$c`", $cols)) . ', ordem) VALUES (' . implode(',', array_fill(0, count($cols) + 1, '?')) . ')')
                        ->execute([...array_values($v), $ordem]);
                    flash($Sing . ($f ? ' adicionada.' : ' adicionado.'));
                }
                redirecionar($self);
            }
        }
    }

    if (!$editar && isset($_GET['editar'])) {
        $st = db()->prepare("SELECT * FROM `$t` WHERE id = ?");
        $st->execute([(int)$_GET['editar']]);
        $editar = $st->fetch() ?: null;
    }
    if (!$editar && isset($_GET['novo'])) {
        $editar = ['id' => 0];
        foreach ($campos as $k => $c) $editar[$k] = $c['padrao'] ?? ($c[1] === 'checkbox' ? 1 : '');
    }

    $lista = $todos();
    topo($cfg['titulo'], $cfg['pagina']);
    ?>
    <div class="head">
      <div><h1><?= h($cfg['titulo']) ?></h1><p class="muted"><?= h($cfg['intro']) ?></p></div>
      <?php if (!$editar): ?><a class="btn primary" href="?novo=1">+ Adicionar <?= h($cfg['singular']) ?></a><?php endif; ?>
    </div>

    <?php if ($editar): ?>
    <div class="card" style="margin-bottom:22px">
      <h2><?= $editar['id'] ? 'Editar ' . h($cfg['singular']) : ($f ? 'Nova ' : 'Novo ') . h($cfg['singular']) ?></h2>
      <?php if ($erro): ?><p class="alert err"><?= h($erro) ?></p><?php endif; ?>
      <form method="post" class="grid2">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="guardar"><input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
        <?php foreach ($campos as $k => $c):
            [$rot, $tipo] = $c; $valor = $editar[$k] ?? ''; $full = in_array($tipo, ['textarea', 'icone'], true) || !empty($c['full']); ?>
          <?php if ($tipo === 'checkbox'): ?>
            <label class="check full"><input type="checkbox" name="<?= $k ?>" value="1" <?= $valor ? 'checked' : '' ?>> <?= h($rot) ?></label>
          <?php elseif ($tipo === 'icone'): ?>
            <div class="full"><div style="font-weight:600;font-size:.9rem;margin-bottom:6px"><?= h($rot) ?></div>
              <div class="icons"><?php foreach (ICONES as $ik => [$inome]): ?>
                <label><input type="radio" name="<?= $k ?>" value="<?= $ik ?>" <?= ($valor ?: 'chave') === $ik ? 'checked' : '' ?>><?= icone($ik) ?><?= h($inome) ?></label>
              <?php endforeach; ?></div></div>
          <?php else: ?>
            <label class="<?= $full ? 'full' : '' ?>"><?= h($rot) ?><?php if (!empty($c['ajuda'])): ?><small><?= h($c['ajuda']) ?></small><?php endif; ?>
              <?php if ($tipo === 'textarea'): ?>
                <textarea name="<?= $k ?>" maxlength="<?= (int)($c[2] ?? 2000) ?>"><?= h((string)$valor) ?></textarea>
              <?php elseif ($tipo === 'money'): ?>
                <input name="<?= $k ?>" inputmode="decimal" value="<?= $valor !== '' ? h(rtrim(rtrim(number_format((float)$valor, 2, ',', ''), '0'), ',')) : '' ?>" placeholder="0" required>
              <?php else: ?>
                <input name="<?= $k ?>" maxlength="<?= (int)($c[2] ?? 255) ?>" value="<?= h((string)$valor) ?>" <?= !empty($c['obrigatorio']) ? 'required' : '' ?>>
              <?php endif; ?>
            </label>
          <?php endif; ?>
        <?php endforeach; ?>
        <div class="form-actions full">
          <button class="btn primary" type="submit">Guardar</button>
          <a class="btn" href="<?= $self ?>">Cancelar</a>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
    <?php if (!$lista): ?><p class="empty">Ainda não há nada aqui. Use “Adicionar”.</p><?php else: ?>
      <table class="list">
        <thead><tr><th>Ordem</th><?php foreach ($cfg['colunas'] as [$cab]): ?><th><?= h($cab) ?></th><?php endforeach; ?><th>No site</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $i => $r): ?>
          <tr class="<?= $r['ativo'] ? '' : 'off' ?>">
            <td class="num">
              <div class="row" style="gap:4px;flex-wrap:nowrap">
                <?php foreach (['cima' => '↑', 'baixo' => '↓'] as $dir => $seta):
                    $desligado = ($dir === 'cima' && $i === 0) || ($dir === 'baixo' && $i === count($lista) - 1); ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="mover"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="dir" value="<?= $dir ?>">
                    <button class="btn sm" type="submit" <?= $desligado ? 'disabled style="opacity:.35"' : '' ?> aria-label="Mover para <?= $dir ?>"><?= $seta ?></button></form>
                <?php endforeach; ?>
              </div>
            </td>
            <?php foreach ($cfg['colunas'] as [, $fn]): ?><td><?= $fn($r) ?></td><?php endforeach; ?>
            <td>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="ativo"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn sm" type="submit" title="Mostrar ou esconder no site"><?= $r['ativo'] ? '✓ Visível' : 'Escondido' ?></button></form>
            </td>
            <td>
              <div class="row" style="flex-wrap:nowrap">
                <a class="btn sm" href="?editar=<?= (int)$r['id'] ?>">Editar</a>
                <form method="post" onsubmit="return confirm('Apagar? Não é possível desfazer.')"><?= csrf_field() ?><input type="hidden" name="acao" value="apagar"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn sm danger" type="submit">Apagar</button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    </div>
    <?php
    fundo();
}
