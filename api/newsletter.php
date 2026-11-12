<?php
// Subscrição da newsletter (formulário do site e da janela da promoção).
require __DIR__ . '/../inc/bootstrap.php';

$querJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
function resp(bool $ok, string $msg): void {
    global $querJson;
    if ($querJson) {
        http_response_code($ok ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'msg' => $msg]);
    } else {
        echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><p style="font-family:sans-serif;padding:20px">' . h($msg) . '</p><p style="font-family:sans-serif;padding:0 20px"><a href="../index.php">Voltar ao site</a></p>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Pedido inválido.');
$t = $_POST['csrf'] ?? '';
if (!is_string($t) || !hash_equals(csrf_token(), $t)) resp(false, 'A página expirou. Atualize e tente de novo.');
if (!empty($_POST['website'])) resp(true, 'Subscrição feita. Obrigado!');

$email = mb_strtolower(mb_substr(trim((string)($_POST['email'] ?? '')), 0, 150));
$nome  = mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 80);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) resp(false, 'Escreva um email válido.');
if (empty($_POST['consentimento'])) resp(false, 'Confirme que aceita receber as promoções por email.');

// Limite: 10 subscrições por hora por IP
$st = db()->prepare('SELECT COUNT(*) FROM subscritores WHERE ip = ? AND criado_em > (NOW() - INTERVAL 1 HOUR)');
$st->execute([ip_cliente()]);
if ((int)$st->fetchColumn() >= 10) resp(false, 'Demasiados pedidos. Tente mais tarde.');

$st = db()->prepare('SELECT id, ativo FROM subscritores WHERE email = ?');
$st->execute([$email]);
$existe = $st->fetch();
if ($existe) {
    if (!$existe['ativo']) {
        db()->prepare('UPDATE subscritores SET ativo = 1, saiu_em = NULL, nome = IF(CHAR_LENGTH(?) > 0, ?, nome) WHERE id = ?')->execute([$nome, $nome, $existe['id']]);
    }
    resp(true, 'Este email já está na lista. Vai receber as próximas promoções.');
}
db()->prepare('INSERT INTO subscritores (email, nome, token, ip) VALUES (?,?,?,?)')
    ->execute([$email, $nome, bin2hex(random_bytes(16)), ip_cliente()]);
resp(true, 'Subscrição feita. Vai receber as promoções do mês neste email.');
