<?php
require __DIR__ . '/_layout.php';
global $CONFIG;

$promo = promo_ativa();
$oficina = def('nome_oficina', 'CMPNEUS');

// Exportar CSV (antes de qualquer HTML)
if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscritores-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // para o Excel ler os acentos
    fputcsv($out, ['Email', 'Nome', 'Subscreveu em', 'Ativo'], ';');
    foreach (db()->query('SELECT email, nome, criado_em, ativo FROM subscritores ORDER BY criado_em DESC') as $r) {
        fputcsv($out, [$r['email'], $r['nome'], date('d/m/Y H:i', strtotime($r['criado_em'])), $r['ativo'] ? 'Sim' : 'Não'], ';');
    }
    exit;
}

$assunto = 'Promoção do mês · ' . $oficina;
$corpo = "Olá,\n\nEste mês temos uma promoção para o seu carro. Veja os detalhes abaixo e marque já pelo site ou por telefone.\n\nAté breve,\nEquipa $oficina";
$incluirPromo = (bool)$promo;
$preview = null;
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if (in_array($acao, ['pre', 'teste', 'enviar'], true)) {
        $assunto = mb_substr(trim($_POST['assunto'] ?? ''), 0, 150);
        $corpo = mb_substr(trim($_POST['corpo'] ?? ''), 0, 5000);
        $incluirPromo = !empty($_POST['incluir_promo']) && $promo;
        if ($assunto === '' || $corpo === '') {
            $erro = 'Escreva o assunto e a mensagem.';
        } elseif ($acao === 'pre') {
            $preview = email_newsletter($assunto, $corpo, $incluirPromo ? $promo : null, url_base() . 'newsletter.php?sair=exemplo');
        } elseif ($acao === 'teste') {
            $html = email_newsletter($assunto, $corpo, $incluirPromo ? $promo : null, url_base() . 'newsletter.php?sair=teste');
            $ok = enviar_email(utilizador()['email'], '[TESTE] ' . $assunto, $html);
            flash($ok ? 'Email de teste enviado para ' . utilizador()['email'] . '. Veja a caixa de entrada (e o spam).' : 'O email de teste não foi enviado. ' . email_erro(), $ok ? 'ok' : 'err');
            $preview = $html;
        } else {
            @set_time_limit(0);
            $subs = db()->query('SELECT email, token FROM subscritores WHERE ativo = 1')->fetchAll();
            $ok = $falha = 0;
            $ultimoErro = '';
            foreach ($subs as $s) {
                $sair = url_base() . 'newsletter.php?sair=' . $s['token'];
                $html = email_newsletter($assunto, $corpo, $incluirPromo ? $promo : null, $sair);
                $extra = "List-Unsubscribe: <$sair>\r\n";
                if (enviar_email($s['email'], $assunto, $html, $extra)) { $ok++; } else { $falha++; $ultimoErro = email_erro(); }
                if ($falha >= 3 && $ok === 0) break; // configuração errada: não vale a pena continuar
                usleep(150000); // pequena pausa para não ser travado pelo servidor
            }
            db()->prepare('INSERT INTO newsletter_envios (assunto, corpo, enviados, falhados, enviado_por) VALUES (?,?,?,?,?)')
                ->execute([$assunto, $corpo, $ok, $falha, utilizador()['nome']]);
            if ($ok === 0 && $falha > 0) {
                flash('A newsletter não foi enviada. ' . $ultimoErro, 'err');
            } else {
                flash("Newsletter enviada: $ok emails" . ($falha ? ", $falha falharam ($ultimoErro)" : '') . '.', $falha ? 'err' : 'ok');
            }
            redirecionar('newsletter.php');
        }
    }
    if ($acao === 'adicionar') {
        $email = mb_strtolower(trim($_POST['email'] ?? ''));
        $nome = mb_substr(trim($_POST['nome'] ?? ''), 0, 80);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Esse email não é válido.', 'err');
        } else {
            db()->prepare('INSERT INTO subscritores (email, nome, token, origem) VALUES (?,?,?,\'backoffice\')
                           ON DUPLICATE KEY UPDATE ativo = 1, saiu_em = NULL')
                ->execute([$email, $nome, bin2hex(random_bytes(16))]);
            flash("$email adicionado à lista.");
        }
        redirecionar('newsletter.php');
    }
    if ($acao === 'remover') {
        db()->prepare('DELETE FROM subscritores WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Subscritor apagado da lista.');
        redirecionar('newsletter.php');
    }
}

$q = trim($_GET['q'] ?? '');
$ativos = (int)db()->query('SELECT COUNT(*) FROM subscritores WHERE ativo = 1')->fetchColumn();
$novosMes = (int)db()->query("SELECT COUNT(*) FROM subscritores WHERE ativo = 1 AND criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$sairam = (int)db()->query('SELECT COUNT(*) FROM subscritores WHERE ativo = 0')->fetchColumn();
$st = db()->prepare('SELECT * FROM subscritores' . ($q !== '' ? ' WHERE email LIKE ? OR nome LIKE ?' : '') . ' ORDER BY ativo DESC, criado_em DESC LIMIT 500');
$st->execute($q !== '' ? ["%$q%", "%$q%"] : []);
$subs = $st->fetchAll();
$envios = db()->query('SELECT * FROM newsletter_envios ORDER BY criado_em DESC LIMIT 12')->fetchAll();

topo('Newsletter', 'newsletter');
?>
<div class="head">
  <div><h1>Newsletter</h1><p class="muted">Pessoas que pediram para receber as promoções do mês, e envio da newsletter.</p></div>
  <a class="btn" href="?exportar=1">Exportar lista (Excel)</a>
</div>

<div class="stats" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat hi"><b><?= $ativos ?></b><span>subscritores ativos</span></div>
  <div class="stat"><b><?= $novosMes ?></b><span>novos este mês</span></div>
  <div class="stat"><b><?= $sairam ?></b><span>cancelaram a subscrição</span></div>
</div>

<div class="detail">
  <div>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2>Enviar newsletter</h2>
      <?php if ($erro): ?><p class="alert err"><?= h($erro) ?></p><?php endif; ?>
      <div class="stack">
        <label>Assunto<input name="assunto" maxlength="150" required value="<?= h($assunto) ?>"></label>
        <label>Mensagem<small>Deixe uma linha em branco entre parágrafos.</small><textarea name="corpo" maxlength="5000" style="min-height:180px" required><?= h($corpo) ?></textarea></label>
        <?php if ($promo): ?>
          <label class="check"><input type="checkbox" name="incluir_promo" value="1" <?= $incluirPromo ? 'checked' : '' ?>> Incluir a promoção do mês: <b><?= h($promo['titulo']) ?></b> <?= $promo['destaque'] ? '(' . h($promo['destaque']) . ')' : '' ?></label>
        <?php else: ?>
          <p class="muted" style="margin:0">Não há promoção ativa para juntar ao email. <a href="promocoes.php">Criar promoção</a></p>
        <?php endif; ?>
        <div class="form-actions">
          <button class="btn" name="acao" value="pre">Pré-visualizar</button>
          <button class="btn" name="acao" value="teste">Enviar teste para mim</button>
          <button class="btn primary" name="acao" value="enviar" <?= $ativos ? '' : 'disabled' ?>
            onclick="return confirm('Enviar esta newsletter a <?= $ativos ?> pessoas? Não é possível desfazer.')">Enviar a <?= $ativos ?> subscritores</button>
        </div>
      </div>
    </form>

    <?php if ($preview): ?>
    <div class="card">
      <h2>Pré-visualização</h2>
      <iframe title="Pré-visualização do email" srcdoc="<?= h($preview) ?>" style="width:100%;height:620px;border:1px solid var(--line);border-radius:8px;background:#fff" sandbox></iframe>
    </div>
    <?php endif; ?>

    <div class="card">
      <h2>Subscritores</h2>
      <form method="get" class="search" style="margin-bottom:12px">
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="Procurar email ou nome" aria-label="Procurar subscritor">
        <button class="btn">Procurar</button>
      </form>
      <?php if (!$subs): ?><p class="empty" style="padding:20px">Ainda não há subscritores.</p><?php else: ?>
      <div style="overflow-x:auto">
        <table class="list">
          <thead><tr><th>Email</th><th>Desde</th><th>Estado</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($subs as $s): ?>
            <tr class="<?= $s['ativo'] ? '' : 'off' ?>">
              <td><b><?= h($s['email']) ?></b><?php if ($s['nome']): ?><span class="sub"><?= h($s['nome']) ?></span><?php endif; ?></td>
              <td class="num"><?= date('d/m/Y', strtotime($s['criado_em'])) ?><span class="sub"><?= $s['origem'] === 'backoffice' ? 'adicionado aqui' : 'pelo site' ?></span></td>
              <td><span class="pill <?= $s['ativo'] ? 'concluida' : 'cancelada' ?>"><?= $s['ativo'] ? 'Ativo' : 'Cancelou' ?></span></td>
              <td><form method="post" onsubmit="return confirm('Apagar este subscritor da lista?')"><?= csrf_field() ?><input type="hidden" name="acao" value="remover"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn sm danger">Apagar</button></form></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="acao" value="adicionar">
      <h2>Adicionar à mão</h2>
      <p class="muted" style="margin-top:0;font-size:.9rem">Só com autorização do cliente (por exemplo, pediu na oficina para receber as promoções).</p>
      <div class="stack">
        <label>Email<input name="email" type="email" required></label>
        <label>Nome (opcional)<input name="nome" maxlength="80"></label>
        <div class="form-actions"><button class="btn">Adicionar</button></div>
      </div>
    </form>

    <div class="card">
      <h2>Envios anteriores</h2>
      <?php if (!$envios): ?><p class="muted" style="margin:0">Ainda não foi enviada nenhuma newsletter.</p><?php else: ?>
      <table class="list">
        <?php foreach ($envios as $e): ?>
          <tr><td><b><?= h($e['assunto']) ?></b><span class="sub"><?= date('d/m/Y H:i', strtotime($e['criado_em'])) ?> · <?= h($e['enviado_por']) ?></span></td>
              <td class="num" style="text-align:right"><?= (int)$e['enviados'] ?><span class="sub"><?= $e['falhados'] ? (int)$e['falhados'] . ' falharam' : 'enviados' ?></span></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Sobre o envio de emails</h2>
      <?php if (smtp_configurado()): ?>
        <p class="alert ok" style="font-size:.9rem">A enviar por SMTP: <b><?= h($CONFIG['smtp_host']) ?></b>. Use "Enviar teste para mim" para confirmar.</p>
      <?php else: ?>
        <p class="alert err" style="font-size:.9rem">O SMTP não está configurado. Sem ele, os emails dependem do <code>mail()</code> do servidor, que não funciona no XAMPP e falha em muitos alojamentos. Preencha a parte SMTP do <code>config.php</code>.</p>
      <?php endif; ?>
      <p class="muted" style="margin:0;font-size:.9rem">Os emails saem pelo servidor do alojamento. Para listas pequenas (até umas centenas) funciona bem. Se os emails forem parar ao spam ou a lista crescer muito, convém ligar um serviço de envio (por exemplo Brevo ou Mailjet, ambos com plano grátis).</p>
    </div>
  </div>
</div>
<?php fundo();
