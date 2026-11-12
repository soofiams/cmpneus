<?php
require __DIR__ . '/_crud.php';
crud([
    'tabela' => 'revisoes', 'pagina' => 'revisoes', 'titulo' => 'Revisões', 'singular' => 'revisão', 'feminino' => true,
    'intro' => 'Os cartões de revisão (simples, completa…). O que estiver "em destaque" aparece com fundo escuro.',
    'campos' => [
        'nome'      => ['Nome', 'text', 80, 'obrigatorio' => true],
        'preco'     => ['Preço desde (€)', 'money'],
        'intervalo' => ['Quando fazer', 'text', 120, 'full' => true, 'ajuda' => 'Ex.: "Recomendada a cada 30 000 km ou 2 anos".'],
        'itens'     => ['O que inclui', 'textarea', 2000, 'ajuda' => 'Um item por linha.'],
        'destaque'  => ['Em destaque (fundo escuro)', 'checkbox', 'padrao' => 0],
        'ativo'     => ['Mostrar no site', 'checkbox'],
    ],
    'colunas' => [
        ['Revisão', fn($r) => '<b>' . h($r['nome']) . '</b>' . ($r['destaque'] ? ' <span class="pill nova">Destaque</span>' : '') . '<span class="sub">' . h($r['intervalo']) . '</span>'],
        ['Inclui', fn($r) => count(array_filter(preg_split('/\R/', $r['itens']), 'trim')) . ' itens'],
        ['Preço', fn($r) => '<span class="num"><span class="muted">desde</span> ' . euros($r['preco']) . '</span>'],
    ],
]);
