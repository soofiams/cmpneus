<?php
require __DIR__ . '/_layout.php';
garantir_historico();

$grupos = [
    'Contactos' => [
        'nome_oficina'  => ['Nome da oficina', 'text', 60],
        'telefone'      => ['Telefone', 'text', 30, 'Como aparece no site, ex.: +351 912 345 678'],
        'whatsapp'      => ['Número de WhatsApp', 'text', 20, 'Com indicativo e sem espaços, ex.: 351912345678. Vazio esconde o botão.'],
        'email'         => ['Email público', 'email', 150, 'Aparece nos contactos do site.'],
    ],
    'Morada' => [
        'morada'        => ['Rua e número', 'text', 150],
        'codigo_postal' => ['Código postal', 'text', 10],
        'localidade'    => ['Localidade', 'text', 60],
        'maps_url'      => ['Link do Google Maps', 'url', 500, 'No Google Maps, procure a oficina, carregue em "Partilhar" e cole aqui o link.'],
    ],
    'Textos do topo do site' => [
        'hero_titulo'   => ['Título (início)', 'text', 80],
        'hero_destaque' => ['Palavra em azul', 'text', 40],
        'hero_fim'      => ['Título (fim)', 'text', 80],
        'hero_texto'    => ['Texto de apresentação', 'textarea', 400],
        'factos'        => ['Destaques por baixo do topo', 'textarea', 600, 'Um por linha, no formato: Título | texto. Ex.: 24 h | para responder ao seu orçamento'],
        'nota_precos'   => ['Nota por baixo dos preços', 'textarea', 400],
    ],
    'Promoções e newsletter' => [
        'promo_atraso'      => ['Segundos até abrir a janela da promoção', 'number', 3, '0 abre logo. A janela só aparece uma vez a cada visitante; depois fica um botão no canto.'],
        'newsletter_titulo' => ['Título da secção da newsletter', 'text', 80],
        'newsletter_texto'  => ['Texto da secção da newsletter', 'textarea', 300],
    ],
    'Lembretes de revisão' => [
        'lembrete_dias_antes' => ['Avisar quantos dias antes da revisão', 'number', 3, 'Os carros aparecem em "Revisões a aproximar-se" a partir desta altura.', 'max' => 120],
        'revisao_meses'       => ['Próxima revisão: meses depois do serviço', 'number', 2, 'Usado para preencher a data da próxima revisão automaticamente.', 'max' => 48],
        'revisao_km'          => ['Próxima revisão: km depois do serviço', 'number', 6, 'Ex.: 15000', 'max' => 100000],
        'lembrete_assunto'    => ['Assunto do email de lembrete', 'text', 150, 'Pode usar {nome}, {carro}, {matricula}, {data}, {km}'],
        'lembrete_texto'      => ['Texto do email de lembrete', 'textarea', 2000, 'Pode usar {nome}, {carro}, {matricula}, {data}, {km}, {km_texto}, {oficina}. Deixe uma linha em branco entre parágrafos.'],
    ],
    'Avisos' => [
        'email_notificacoes' => ['Enviar aviso de novos pedidos para', 'email', 150, 'Recebe um email sempre que alguém pede orçamento no site. Vazio = sem email.'],
    ],
];

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('INSERT INTO definicoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    $novos = [];
    foreach ($grupos as $campos) {
        foreach ($campos as $k => $c) {
            $v = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $c[2]);
            if ($c[1] === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $erro = '"' . $c[0] . '" não é um email válido.';
            if ($c[1] === 'number') $v = (string)max(0, min((int)($c['max'] ?? 60), (int)$v));
            if ($c[1] === 'url' && $v !== '' && !preg_match('#^https?://#i', $v)) $erro = '"' . $c[0] . '" tem de começar por https://';
            $novos[$k] = $v;
        }
    }
    if (!$erro) {
        foreach ($novos as $k => $v) $st->execute([$k, $v]);
        flash('Dados guardados. Já estão no site.');
        redirecionar('definicoes.php');
    }
}
$val = fn($k) => h($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST[$k] ?? '') : def($k));

topo('Dados da oficina', 'definicoes');
?>
<div class="head"><div><h1>Dados da oficina</h1><p class="muted">Contactos, morada e textos que aparecem no site.</p></div></div>
<?php if ($erro): ?><p class="alert err"><?= h($erro) ?></p><?php endif; ?>
<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($grupos as $titulo => $campos): ?>
  <div class="card">
    <h2><?= h($titulo) ?></h2>
    <div class="grid2">
      <?php foreach ($campos as $k => $c): $full = $c[1] === 'textarea' || $c[1] === 'url'; ?>
        <label class="<?= $full ? 'full' : '' ?>"><?= h($c[0]) ?><?php if (!empty($c[3])): ?><small><?= h($c[3]) ?></small><?php endif; ?>
          <?php if ($c[1] === 'textarea'): ?>
            <textarea name="<?= $k ?>" maxlength="<?= $c[2] ?>"><?= $val($k) ?></textarea>
          <?php else: ?>
            <input name="<?= $k ?>" type="<?= $c[1] ?>" maxlength="<?= $c[2] ?>" value="<?= $val($k) ?>">
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="form-actions" style="margin-top:20px"><button class="btn primary" type="submit">Guardar dados</button></div>
</form>
<?php fundo();
