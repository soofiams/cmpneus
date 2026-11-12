<?php
// Recebe pedidos de orçamento/marcação do formulário do site.
require __DIR__ . '/../inc/bootstrap.php';

$querJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function responder(bool $ok, string $erro = ''): void {
    global $querJson;
    if ($querJson) {
        http_response_code($ok ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'erro' => $erro]);
    } elseif ($ok) {
        header('Location: ../index.php?enviado=1#orcamento');
    } else {
        http_response_code(422);
        echo '<p>' . h($erro) . '</p><p><a href="../index.php#orcamento">Voltar</a></p>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Pedido inválido.');

$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
    responder(false, 'A página expirou. Atualize a página e volte a enviar.');
}

// Armadilha para robôs: campo escondido que pessoas não preenchem
if (!empty($_POST['website'])) responder(true);

$txt = fn(string $k, int $max) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);

$nome      = $txt('nome', 100);
$telemovel = $txt('telemovel', 30);
$email     = $txt('email', 150);
$matricula = mb_strtoupper($txt('matricula', 12));
$carro     = $txt('carro', 100);
$ano       = preg_replace('/\D/', '', $txt('ano', 4));
$km        = $txt('km', 20);
$pneu      = $txt('medida_pneu', 40);
$mensagem  = $txt('mensagem', 2000);
$dataPref  = $txt('data_preferida', 10);
$servicos  = array_slice(array_filter(array_map(
    fn($s) => mb_substr(trim((string)$s), 0, 60),
    (array)($_POST['servicos'] ?? [])
)), 0, 15);

if ($nome === '') responder(false, 'Indique o seu nome.');
if (strlen(digitos($telemovel)) < 9) responder(false, 'Indique um telemóvel válido.');
if (!$servicos) responder(false, 'Escolha pelo menos um serviço.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) responder(false, 'O email não é válido.');

$data = null;
if ($dataPref !== '') {
    $d = DateTime::createFromFormat('!Y-m-d', $dataPref);
    if ($d && $d->format('Y-m-d') === $dataPref && $d >= new DateTime('today')) $data = $dataPref;
}

// Limite: máximo 5 pedidos por hora a partir do mesmo IP
$ip = ip_cliente();
$st = db()->prepare('SELECT COUNT(*) FROM marcacoes WHERE ip = ? AND criado_em > (NOW() - INTERVAL 1 HOUR)');
$st->execute([$ip]);
if ((int)$st->fetchColumn() >= 5) responder(false, 'Já recebemos vários pedidos seus. Ligue-nos, por favor.');

db()->prepare('INSERT INTO marcacoes
  (nome, telemovel, email, matricula, carro, ano, km, servicos, medida_pneu, data_preferida, mensagem, notas, ip)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
  ->execute([$nome, $telemovel, $email, $matricula, $carro, $ano, $km, implode(', ', $servicos), $pneu, $data, $mensagem, '', $ip]);
$id = (int)db()->lastInsertId();

// Aviso por email (opcional — definido no backoffice)
$destino = def('email_notificacoes');
if ($destino !== '' && filter_var($destino, FILTER_VALIDATE_EMAIL)) {
    $corpo = "Novo pedido no site (#$id)\n\n"
        . "Nome: $nome\nTelemóvel: $telemovel\n"
        . ($email ? "Email: $email\n" : '')
        . ($matricula ? "Matrícula: $matricula\n" : '')
        . ($carro ? "Carro: $carro" . ($ano ? " ($ano)" : '') . "\n" : '')
        . ($km ? "Km: $km\n" : '')
        . 'Serviços: ' . implode(', ', $servicos) . "\n"
        . ($pneu ? "Medida do pneu: $pneu\n" : '')
        . ($data ? 'Data preferida: ' . date('d/m/Y', strtotime($data)) . "\n" : '')
        . ($mensagem ? "\nMensagem:\n$mensagem\n" : '');
    $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5">' . nl2br(h($corpo))
          . '<p><a href="' . h(url_base()) . 'admin/marcacao.php?id=' . $id . '">Abrir no backoffice</a></p></div>';
    enviar_email($destino, "Novo pedido de $nome", $html, '', $email);
}

responder(true);
