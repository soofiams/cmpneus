<?php
require __DIR__ . '/inc/bootstrap.php';

$nome      = def('nome_oficina', 'CMPNEUS');
$tel       = def('telefone');
$servicos  = db()->query('SELECT * FROM servicos WHERE ativo = 1 ORDER BY ordem, id')->fetchAll();
$precos    = db()->query('SELECT * FROM precos WHERE ativo = 1 ORDER BY ordem, id')->fetchAll();
$revisoes  = db()->query('SELECT * FROM revisoes WHERE ativo = 1 ORDER BY ordem, id')->fetchAll();
$horario   = horario();
$grupos    = horario_agrupado();
$promo     = promo_ativa();

// Opções do formulário: revisões + serviços (sem duplicar "Revisões") + Outro
$opcoes = array_column($revisoes, 'nome');
foreach ($servicos as $s) {
    if (mb_strtolower($s['nome']) !== 'revisões' && !in_array($s['nome'], $opcoes, true)) $opcoes[] = $s['nome'];
}
$opcoes[] = 'Outro';

$factos = [];
foreach (preg_split('/\R/', def('factos')) as $linha) {
    if (trim($linha) === '') continue;
    [$t, $d] = array_map('trim', explode('|', $linha, 2) + [1 => '']);
    $factos[] = [$t, $d];
}

// Horário em minutos, para o "aberto agora" no navegador
$horarioJs = [];
foreach ($horario as $dia => $horas) $horarioJs[$dia] = intervalos($horas);

$topbar = implode(' · ', array_map(fn($g) => '<b>' . h($g[0]) . '</b> ' . h($g[1]), array_filter($grupos, fn($g) => $g[1] !== 'Encerrado')));
?><!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($nome) ?> · Oficina e pneus em <?= h(def('localidade')) ?></title>
<meta name="description" content="<?= h($nome) ?> — oficina automóvel e pneus em <?= h(def('localidade')) ?>. Revisões, pneus, travões, diagnóstico e reparações. Peça orçamento.">
<link rel="icon" href="assets/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@600;800;900&family=Barlow:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar"><div class="wrap">
  <span><?= $topbar ?></span>
  <span>Ligue: <b><?= h($tel) ?></b></span>
</div></div>

<header class="nav"><div class="wrap">
  <a class="brand" href="#top" aria-label="<?= h($nome) ?>, início"><img src="assets/logo.png" alt="<?= h($nome) ?>" width="84" height="50"></a>
  <ul>
    <li><a href="#servicos">Serviços</a></li>
    <?php if ($revisoes): ?><li><a href="#revisoes">Revisões</a></li><?php endif; ?>
    <li><a href="#precos">Preços</a></li>
    <li><a href="#contactos">Contactos</a></li>
  </ul>
  <a class="btn btn-accent" href="#orcamento">Pedir orçamento</a>
</div></header>

<main id="top">
<section class="hero">
  <div class="wrap">
    <div>
      <div class="eyebrow">Oficina automóvel · <?= h(def('localidade')) ?></div>
      <h1 style="margin-top:14px"><?= h(def('hero_titulo')) ?> <em><?= h(def('hero_destaque')) ?></em> <?= h(def('hero_fim')) ?></h1>
      <p><?= h(def('hero_texto')) ?></p>
      <div class="hero-ctas">
        <a class="btn btn-accent" href="#orcamento">Pedir orçamento grátis</a>
        <a class="btn btn-ghost" href="#precos">Ver preços</a>
      </div>
    </div>
    <img class="hero-logo" src="assets/logo.png" alt="Logótipo <?= h($nome) ?>" width="360" height="214">
  </div>
  <?php if ($factos): ?>
  <div class="wrap facts-wrap">
    <div class="facts">
      <?php foreach ($factos as [$t, $d]): ?><div><b><?= h($t) ?></b><span><?= h($d) ?></span></div><?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</section>

<section id="servicos">
  <div class="wrap">
    <div class="eyebrow">Serviços</div>
    <h2 style="margin-top:10px">O que fazemos</h2>
    <p class="lead">Mecânica geral para ligeiros de todas as marcas. Se não encontra o que precisa, descreva o problema no pedido de orçamento.</p>
    <div class="svc-grid">
      <?php foreach ($servicos as $s): ?>
      <div class="svc"><?= icone($s['icone']) ?><h3><?= h($s['nome']) ?></h3><p><?= h($s['descricao']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($revisoes): ?>
<section id="revisoes" class="rev">
  <div class="wrap">
    <div class="eyebrow">Revisões</div>
    <h2 style="margin-top:10px">Qual revisão precisa?</h2>
    <p class="lead">Seguimos o plano de manutenção do fabricante. Com dúvidas, diga-nos os quilómetros do carro e a data da última revisão.</p>
    <div class="rev-grid">
      <?php foreach ($revisoes as $r): ?>
      <div class="plan<?= $r['destaque'] ? ' full' : '' ?>">
        <div class="plan-head">
          <h3><?= h($r['nome']) ?></h3>
          <div class="price">desde<b><?= euros($r['preco']) ?></b></div>
        </div>
        <?php if ($r['intervalo']): ?><div class="when"><?= h($r['intervalo']) ?></div><?php endif; ?>
        <ul>
          <?php foreach (preg_split('/\R/', $r['itens']) as $item): if (trim($item) === '') continue; ?>
          <li><?= h(trim($item)) ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="btn<?= $r['destaque'] ? ' btn-accent' : '' ?>" href="#orcamento" data-service="<?= h($r['nome']) ?>">Marcar <?= h(mb_strtolower($r['nome'])) ?></a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section id="precos">
  <div class="wrap">
    <div class="eyebrow">Preços</div>
    <h2 style="margin-top:10px">Preços de referência</h2>
    <p class="lead">Valores com IVA incluído. O preço final depende do modelo e das peças. Confirmamos sempre antes de começar.</p>
    <div class="prices-wrap">
      <table class="prices">
        <thead><tr><th>Serviço</th><th>Preço</th></tr></thead>
        <tbody>
          <?php foreach ($precos as $p): ?>
          <tr><td><?= h($p['nome']) ?><?php if ($p['detalhe']): ?><small><?= h($p['detalhe']) ?></small><?php endif; ?></td>
              <td><?php if ($p['prefixo']): ?><span><?= h($p['prefixo']) ?></span><?php endif; ?><?= euros($p['preco']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <aside class="reader" aria-labelledby="reader-t">
        <h3 id="reader-t">Qual é a medida do seu pneu?</h3>
        <p>Está escrita na parte lateral do pneu. Toque em cada parte para ver o que significa. Com esta medida damos-lhe o preço exato.</p>
        <div class="code" id="code">
          <button type="button" data-k="w" aria-pressed="true">205</button><span class="sep">/</span>
          <button type="button" data-k="p" aria-pressed="false">55</button>
          <button type="button" data-k="r" aria-pressed="false">R</button>
          <button type="button" data-k="d" aria-pressed="false">16</button>
          <button type="button" data-k="l" aria-pressed="false">91</button>
          <button type="button" data-k="s" aria-pressed="false">V</button>
        </div>
        <div class="explain" id="explain" aria-live="polite"></div>
      </aside>
    </div>
    <?php if (def('nota_precos')): ?><p class="note"><?= h(def('nota_precos')) ?></p><?php endif; ?>
  </div>
</section>

<section id="orcamento" class="quote" style="padding-top:0">
  <div class="wrap">
    <div>
      <div class="eyebrow">Orçamento grátis</div>
      <h2 style="margin-top:10px">Peça o seu orçamento</h2>
      <p class="lead">Preencha o pedido e entramos em contacto consigo no próprio dia útil.</p>
      <ol class="steps">
        <li><div><b>Diga-nos o carro e o serviço</b><span>Matrícula, modelo e o que precisa.</span></div></li>
        <li><div><b>Recebe o preço</b><span>Com peças e mão de obra, sem surpresas.</span></div></li>
        <li><div><b>Marcamos o dia</b><span>À hora que lhe der mais jeito.</span></div></li>
      </ol>
    </div>

    <form class="card" id="qform" method="post" action="api/marcacao.php" novalidate>
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="field"><label for="q-nome">Nome</label><input id="q-nome" name="nome" autocomplete="name" maxlength="100" required><span class="err" id="e-nome"></span></div>
      <div class="field"><label for="q-tel">Telemóvel</label><input id="q-tel" name="telemovel" type="tel" autocomplete="tel" inputmode="tel" maxlength="30" required><span class="err" id="e-tel"></span></div>
      <div class="field"><label for="q-mat">Matrícula</label><input id="q-mat" name="matricula" class="plate" placeholder="AA-00-AA" maxlength="12"></div>
      <div class="field"><label for="q-carro">Marca e modelo</label><input id="q-carro" name="carro" placeholder="Ex.: Renault Clio 1.5 dCi" maxlength="100"></div>
      <div class="field"><label for="q-ano">Ano</label><input id="q-ano" name="ano" inputmode="numeric" placeholder="2016" maxlength="4"></div>
      <div class="field"><label for="q-km">Quilómetros</label><input id="q-km" name="km" inputmode="numeric" placeholder="148 000" maxlength="20"></div>
      <div class="field full">
        <div class="legend" id="lg-svc">Serviço pretendido</div>
        <div class="chips" role="group" aria-labelledby="lg-svc" id="q-svcs">
          <?php foreach ($opcoes as $o): ?>
          <label><input type="checkbox" name="servicos[]" value="<?= h($o) ?>"> <?= h($o) ?></label>
          <?php endforeach; ?>
        </div>
        <span class="err" id="e-svc"></span>
      </div>
      <div class="field"><label for="q-pneu">Medida do pneu (se aplicável)</label><input id="q-pneu" name="medida_pneu" placeholder="205/55 R16 91V" maxlength="40"></div>
      <div class="field"><label for="q-data">Data preferida</label><input id="q-data" name="data_preferida" type="date"></div>
      <div class="field full"><label for="q-email">Email (opcional)</label><input id="q-email" name="email" type="email" autocomplete="email" maxlength="150"></div>
      <div class="field full"><label for="q-msg">Descreva o que precisa</label><textarea id="q-msg" name="mensagem" maxlength="2000"></textarea></div>
      <div class="form-foot">
        <button class="btn btn-accent" type="submit" id="q-submit">Enviar pedido</button>
        <small>Os seus dados são usados apenas para responder a este pedido.</small>
      </div>
      <div class="result" id="result" <?= isset($_GET['enviado']) ? '' : 'hidden' ?>>
        <b>Pedido enviado. Obrigado!</b>
        <p style="margin:8px 0 14px">Recebemos o seu pedido e vamos contactá-lo em breve. Se preferir, fale já connosco:</p>
        <div class="row">
          <?php if (def('whatsapp')): ?><a class="btn btn-wa" href="https://wa.me/<?= h(digitos(def('whatsapp'))) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
          <a class="btn btn-line" href="tel:<?= h(digitos($tel)) ?>">Ligar <?= h($tel) ?></a>
        </div>
      </div>
      <p class="err full" id="e-geral" style="grid-column:1/-1"></p>
    </form>
  </div>
</section>

<section id="contactos" class="contact">
  <div class="wrap">
    <div class="eyebrow">Contactos</div>
    <h2 style="margin-top:10px">Onde estamos</h2>
    <div class="contact-grid">
      <div>
        <h3>Morada</h3>
        <p class="big"><?= h(def('morada')) ?><br><?= h(def('codigo_postal')) ?> <?= h(def('localidade')) ?></p>
      </div>
      <div>
        <h3>Contactos</h3>
        <p>Telefone<?= def('whatsapp') ? ' / WhatsApp' : '' ?></p>
        <p class="big"><a href="tel:<?= h(digitos($tel)) ?>" style="text-decoration:none"><?= h($tel) ?></a></p>
        <?php if (def('email')): ?>
        <p style="margin-top:10px">Email</p>
        <p class="big"><a href="mailto:<?= h(def('email')) ?>" style="text-decoration:none"><?= h(def('email')) ?></a></p>
        <?php endif; ?>
      </div>
      <div>
        <h3>Horário</h3>
        <table class="hours">
          <?php foreach ($grupos as [$d, $hs]): ?><tr><td><?= h($d) ?></td><td><?= h($hs) ?></td></tr><?php endforeach; ?>
        </table>
        <span class="open-now" id="open-now"><i></i><span>A verificar…</span></span>
      </div>
    </div>
    <div class="map">
      <div><b><?= h($nome) ?> · <?= h(def('localidade')) ?></b><span>Abra no Google Maps para ver o caminho até à oficina.</span></div>
      <a class="btn btn-accent" href="<?= h(def('maps_url')) ?>" target="_blank" rel="noopener">Abrir no Google Maps</a>
    </div>
  </div>
</section>
<section id="newsletter" class="news">
  <div class="wrap">
    <div>
      <div class="eyebrow">Newsletter</div>
      <h2 style="margin-top:10px"><?= h(def('newsletter_titulo')) ?></h2>
      <p class="lead"><?= h(def('newsletter_texto')) ?></p>
    </div>
    <form class="news-form js-news" method="post" action="api/newsletter.php" novalidate>
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="news-row">
        <label class="sr" for="n-email">Email</label>
        <input id="n-email" name="email" type="email" autocomplete="email" placeholder="o.seu@email.pt" maxlength="150" required>
        <button class="btn btn-accent" type="submit">Subscrever</button>
      </div>
      <label class="consent"><input type="checkbox" name="consentimento" value="1" required> Aceito receber promoções da <?= h($nome) ?> por email. Posso cancelar a qualquer momento.</label>
      <p class="news-msg" role="status"></p>
    </form>
  </div>
</section>
</main>

<footer><div class="wrap">
  <span><b><?= h($nome) ?></b> · Oficina automóvel e pneus · <?= h(def('localidade')) ?></span>
  <span>© <?= date('Y') ?> <?= h($nome) ?> · <a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener">Livro de Reclamações</a></span>
</div></footer>


<?php if ($promo): ?>
<div class="promo" id="promo" data-id="<?= (int)$promo['id'] ?>" data-atraso="<?= (int)def('promo_atraso', '4') ?>" hidden>
  <div class="promo-backdrop" data-fechar></div>
  <div class="promo-card" role="dialog" aria-modal="true" aria-labelledby="promo-t">
    <button class="promo-x" type="button" data-fechar aria-label="Fechar">×</button>
    <?php if ($promo['imagem']): ?><img class="promo-img" src="uploads/promos/<?= h($promo['imagem']) ?>" alt=""><?php endif; ?>
    <div class="promo-body">
      <div class="eyebrow">Promoção do mês</div>
      <?php if ($promo['destaque']): ?><div class="promo-big"><?= h($promo['destaque']) ?></div><?php endif; ?>
      <h3 id="promo-t"><?= h($promo['titulo']) ?></h3>
      <p><?= nl2br(h($promo['texto'])) ?></p>
      <p class="promo-date">Válida até <?= date('d/m/Y', strtotime($promo['fim'])) ?></p>
      <a class="btn btn-accent" href="#orcamento" data-fechar data-promo="<?= h($promo['titulo']) ?>"><?= h($promo['botao_texto']) ?></a>
      <form class="promo-news js-news" method="post" action="api/newsletter.php" novalidate>
        <?= csrf_field() ?>
        <div class="hp" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
        <input type="hidden" name="consentimento" value="1">
        <p class="promo-news-t">Receba a promoção de cada mês no seu email:</p>
        <div class="news-row">
          <input name="email" type="email" autocomplete="email" placeholder="o.seu@email.pt" maxlength="150" aria-label="Email">
          <button class="btn btn-ghost" type="submit">Subscrever</button>
        </div>
        <p class="promo-legal">Ao subscrever, aceita receber as promoções por email. Pode cancelar quando quiser.</p>
        <p class="news-msg" role="status"></p>
      </form>
    </div>
  </div>
</div>
<button class="promo-tab" id="promo-tab" type="button" hidden><span><?= h($promo['destaque'] ?: '%') ?></span> Promoção do mês</button>
<?php endif; ?>

<script>window.HORARIO = <?= json_encode($horarioJs) ?>;</script>
<script src="assets/site.js"></script>
</body>
</html>
