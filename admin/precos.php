<?php
require __DIR__ . '/_crud.php';
crud([
    'tabela' => 'precos', 'pagina' => 'precos', 'titulo' => 'Preços', 'singular' => 'preço',
    'intro' => 'A tabela "Preços de referência" do site. Valores com IVA incluído.',
    'campos' => [
        'nome'    => ['Serviço', 'text', 100, 'obrigatorio' => true],
        'detalhe' => ['Detalhe', 'text', 160, 'ajuda' => 'Aparece por baixo, em letra pequena (ex.: "Por pneu, válvula incluída").'],
        'preco'   => ['Preço (€)', 'money'],
        'prefixo' => ['Antes do preço', 'text', 20, 'ajuda' => 'Normalmente "desde". Deixe vazio para preço fixo.', 'padrao' => 'desde'],
        'ativo'   => ['Mostrar no site', 'checkbox'],
    ],
    'colunas' => [
        ['Serviço', fn($r) => '<b>' . h($r['nome']) . '</b><span class="sub">' . h($r['detalhe']) . '</span>'],
        ['Preço', fn($r) => '<span class="num">' . ($r['prefixo'] ? '<span class="muted">' . h($r['prefixo']) . '</span> ' : '') . euros($r['preco']) . '</span>'],
    ],
]);
