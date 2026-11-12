<?php
require __DIR__ . '/_crud.php';
crud([
    'tabela' => 'servicos', 'pagina' => 'servicos', 'titulo' => 'Serviços', 'singular' => 'serviço',
    'intro' => 'Os cartões da secção "O que fazemos". Também aparecem como opções no pedido de orçamento.',
    'campos' => [
        'nome'      => ['Nome', 'text', 80, 'obrigatorio' => true],
        'descricao' => ['Descrição', 'textarea', 400, 'ajuda' => 'Uma ou duas frases curtas.'],
        'icone'     => ['Ícone', 'icone'],
        'ativo'     => ['Mostrar no site', 'checkbox'],
    ],
    'colunas' => [
        ['Ícone', fn($r) => '<span class="ico">' . icone($r['icone']) . '</span>'],
        ['Serviço', fn($r) => '<b>' . h($r['nome']) . '</b><span class="sub">' . h(mb_strimwidth($r['descricao'], 0, 110, '…')) . '</span>'],
    ],
]);
