<?php
// Carregado por todas as páginas: configuração, base de dados, sessão e funções comuns.
declare(strict_types=1);

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Falta o ficheiro config.php. Copie config.sample.php para config.php e preencha os dados da base de dados.');
}
$CONFIG = require $configFile;

date_default_timezone_set('Europe/Lisbon');
mb_internal_encoding('UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_name('cmpneus_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $CONFIG['db_host'], (int)($CONFIG['db_port'] ?? 3306), $CONFIG['db_name']);
        $pdo = new PDO($dsn, $CONFIG['db_user'], $CONFIG['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }
    return $pdo;
}

/** Escapa texto para HTML. */
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Todas as definições da oficina (telefone, morada, textos...). */
function definicoes(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT chave, valor FROM definicoes') as $r) {
            $cache[$r['chave']] = $r['valor'];
        }
    }
    return $cache;
}
function def(string $chave, string $padrao = ''): string {
    return definicoes()[$chave] ?? $padrao;
}

/** Formata preço: 59 -> "59 €", 12.5 -> "12,50 €" */
function euros($v): string {
    $v = (float)$v;
    $dec = (floor($v) == $v) ? 0 : 2;
    return number_format($v, $dec, ',', ' ') . ' €';
}

/** Só dígitos — para links tel: e wa.me */
function digitos(string $s): string {
    return preg_replace('/\D+/', '', $s);
}

function ip_cliente(): string {
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

// ---------- Horário ----------
const DIAS = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];

function horario(): array {
    $h = array_fill_keys(array_keys(DIAS), '');
    foreach (db()->query('SELECT dia, horas FROM horario') as $r) {
        $h[(int)$r['dia']] = trim($r['horas']);
    }
    return $h;
}

/** Converte "09:00-13:00, 14:00-19:00" em [[540,780],[840,1140]] (minutos). */
function intervalos(string $horas): array {
    $out = [];
    foreach (preg_split('/[,;]+/', $horas) as $parte) {
        if (preg_match('/(\d{1,2})[:h](\d{2})\s*[-–]\s*(\d{1,2})[:h](\d{2})/', $parte, $m)) {
            $out[] = [(int)$m[1] * 60 + (int)$m[2], (int)$m[3] * 60 + (int)$m[4]];
        }
    }
    return $out;
}

/** Formata "09:00-13:00, 14:00-19:00" como "9h00–13h00 · 14h00–19h00". */
function horas_bonitas(string $horas): string {
    $iv = intervalos($horas);
    if (!$iv) return 'Encerrado';
    $f = fn($m) => intdiv($m, 60) . 'h' . str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT);
    return implode(' · ', array_map(fn($i) => $f($i[0]) . '–' . $f($i[1]), $iv));
}

/** Agrupa dias seguidos com o mesmo horário: [['Segunda a sexta', '9h00–13h00 · ...'], ...] */
function horario_agrupado(): array {
    $h = horario();
    $grupos = [];
    $ini = 1;
    for ($d = 1; $d <= 7; $d++) {
        if ($d === 7 || $h[$d + 1] !== $h[$d]) {
            $nome = $ini === $d ? DIAS[$d] : DIAS[$ini] . ' a ' . mb_strtolower(DIAS[$d]);
            $grupos[] = [$nome, horas_bonitas($h[$d])];
            $ini = $d + 1;
        }
    }
    return $grupos;
}

// ---------- Segurança ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}
function csrf_check(): void {
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('O formulário expirou. Volte atrás e atualize a página.');
    }
}

function utilizador(): ?array {
    return $_SESSION['utilizador'] ?? null;
}
function exigir_login(): void {
    if (!utilizador()) {
        header('Location: login.php');
        exit;
    }
}

function flash(string $msg, string $tipo = 'ok'): void {
    $_SESSION['flash'][] = [$tipo, $msg];
}
function flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
function redirecionar(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ---------- Ícones dos serviços ----------
const ICONES = [
    'pneu'        => ['Pneu', '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M12 3v5M12 16v5M3 12h5M16 12h5"/>'],
    'oleo'        => ['Óleo', '<path d="M12 3c3 4 5 6.5 5 9.5a5 5 0 0 1-10 0C7 9.5 9 7 12 3z"/>'],
    'travao'      => ['Travão', '<circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 1 8 8"/><path d="M4 12h3M17 12h3"/>'],
    'alinhamento' => ['Alinhamento', '<path d="M3 12h18M12 3v18"/><path d="M6 6l12 12M18 6L6 18" opacity=".5"/>'],
    'diagnostico' => ['Diagnóstico', '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10h4M7 14h2M15 10l2 2-2 2"/>'],
    'suspensao'   => ['Suspensão', '<path d="M4 20l6-6M10 14l2-8 6 6-8 2z"/>'],
    'ac'          => ['Ar condicionado', '<path d="M12 2v20M4.9 7l14.2 10M4.9 17L19.1 7"/>'],
    'bateria'     => ['Bateria', '<rect x="4" y="7" width="16" height="12" rx="1"/><path d="M8 7V5M16 7V5M9 13h2M14 12v2M13 13h2"/>'],
    'chave'       => ['Engrenagem', '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>'],
    'carro'       => ['Carro', '<path d="M3 16v-4l2-5h14l2 5v4z"/><circle cx="7.5" cy="16.5" r="1.8"/><circle cx="16.5" cy="16.5" r="1.8"/>'],
];
function icone(string $k): string {
    $p = ICONES[$k][1] ?? ICONES['chave'][1];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' . $p . '</svg>';
}

const ESTADOS = [
    'nova'       => 'Nova',
    'confirmada' => 'Confirmada',
    'concluida'  => 'Concluída',
    'cancelada'  => 'Cancelada',
];

// ---------- Promoções ----------
/** Promoção visível hoje (a mais recente que esteja ativa e dentro das datas), ou null. */
function promo_ativa(): ?array {
    $st = db()->query('SELECT * FROM promocoes WHERE ativo = 1 AND inicio <= CURDATE() AND fim >= CURDATE() ORDER BY inicio DESC, id DESC LIMIT 1');
    return $st->fetch() ?: null;
}

// ---------- Email ----------
/** Endereço base do site, ex.: https://cmpneus.pt/ (funciona também numa subpasta). */
function url_base(): string {
    global $CONFIG;
    if (!empty($CONFIG['url_site'])) return rtrim($CONFIG['url_site'], '/') . '/';
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $dir = preg_replace('#/(admin|api)$#', '', $dir);
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/';
}

/** Último erro de envio de email (para mostrar no backoffice). */
function email_erro(?string $novo = null): string {
    static $erro = '';
    if ($novo !== null) $erro = $novo;
    return $erro;
}

/** true se o SMTP estiver configurado em config.php */
function smtp_configurado(): bool {
    global $CONFIG;
    return !empty($CONFIG['smtp_host']);
}

/** Ligação SMTP partilhada (reaproveitada quando se enviam vários emails seguidos). */
function smtp(): Smtp {
    static $s = null;
    global $CONFIG;
    if ($s === null) {
        require_once __DIR__ . '/smtp.php';
        $s = new Smtp(
            (string)$CONFIG['smtp_host'],
            (int)($CONFIG['smtp_porta'] ?? 587),
            strtolower((string)($CONFIG['smtp_seguranca'] ?? 'tls')),
            (string)($CONFIG['smtp_user'] ?? ''),
            (string)($CONFIG['smtp_pass'] ?? '')
        );
    }
    return $s;
}

/** Envia um email HTML (com versão em texto). Usa SMTP se estiver configurado; senão, mail() do PHP. */
function enviar_email(string $para, string $assunto, string $html, string $extraHeaders = '', string $responderPara = ''): bool {
    global $CONFIG;
    email_erro('');
    $de = $CONFIG['email_remetente'] ?? '';
    if ($de === '' || !filter_var($de, FILTER_VALIDATE_EMAIL)) {
        $de = smtp_configurado() && filter_var($CONFIG['smtp_user'] ?? '', FILTER_VALIDATE_EMAIL) ? $CONFIG['smtp_user'] : 'site@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $nome = def('nome_oficina', 'CMPNEUS');
    $limite = 'b' . bin2hex(random_bytes(8));
    $texto = trim(html_entity_decode(strip_tags(preg_replace(['#<br\s*/?>#i', '#</p>|</h\d>|</tr>#i'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $assuntoCod = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    $headers = "From: =?UTF-8?B?" . base64_encode($nome) . "?= <$de>\r\n"
             . ($responderPara !== '' ? "Reply-To: $responderPara\r\n" : '')
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: multipart/alternative; boundary=\"$limite\"\r\n" . $extraHeaders;
    $corpo = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
           . chunk_split(base64_encode($texto))
           . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
           . chunk_split(base64_encode($html)) . "--$limite--";

    if (smtp_configurado()) {
        $dominio = substr(strrchr($de, '@'), 1) ?: 'localhost';
        $msg = "Date: " . date('r') . "\r\n"
             . "To: <$para>\r\n"
             . "Subject: $assuntoCod\r\n"
             . "Message-ID: <" . bin2hex(random_bytes(12)) . "@$dominio>\r\n"
             . $headers . "\r\n" . $corpo;
        try {
            smtp()->enviar($de, $para, $msg);
            return true;
        } catch (Throwable $e) {
            email_erro($e->getMessage());
            smtp()->reset();
            return false;
        }
    }

    $ok = @mail($para, $assuntoCod, $corpo, $headers);
    if (!$ok) email_erro('A função mail() do PHP não conseguiu enviar. Isto é normal no XAMPP e em alguns alojamentos: configure o SMTP em config.php.');
    return $ok;
}

/** Molde do email da newsletter. $corpo é texto simples (parágrafos separados por linha em branco). */
function email_newsletter(string $assunto, string $corpo, ?array $promo, string $linkSair): string {
    $base = url_base();
    $nome = h(def('nome_oficina', 'CMPNEUS'));
    $paras = '';
    foreach (preg_split('/\R{2,}/', trim($corpo)) as $p) {
        if (trim($p) !== '') $paras .= '<p style="margin:0 0 14px;font-size:16px;line-height:1.55;color:#1B1E22">' . nl2br(h(trim($p))) . '</p>';
    }
    $bloco = '';
    if ($promo) {
        $bloco = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#1B1E22;border-radius:10px;margin:8px 0 20px"><tr><td style="padding:22px 24px;color:#EDEEEA;font-family:Arial,sans-serif">'
            . ($promo['destaque'] ? '<div style="font-size:40px;font-weight:900;color:#3A9DEB;line-height:1">' . h($promo['destaque']) . '</div>' : '')
            . '<div style="font-size:20px;font-weight:700;margin:8px 0 6px">' . h($promo['titulo']) . '</div>'
            . '<div style="font-size:15px;line-height:1.5;color:#C9CCD1">' . nl2br(h($promo['texto'])) . '</div>'
            . '<div style="font-size:13px;color:#A6ABB2;margin-top:10px">Válida até ' . date('d/m/Y', strtotime($promo['fim'])) . '</div>'
            . '<a href="' . h($base) . '#orcamento" style="display:inline-block;margin-top:16px;background:#0B74C1;color:#fff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:8px">' . h($promo['botao_texto']) . '</a>'
            . '</td></tr></table>';
    }
    return '<!DOCTYPE html><html lang="pt-PT"><head><meta charset="utf-8"><title>' . h($assunto) . '</title></head>'
        . '<body style="margin:0;background:#F2F3F0;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px"><tr><td style="padding:28px">'
        . '<img src="' . h($base) . 'assets/logo.png" alt="' . $nome . '" width="140" style="display:block;margin-bottom:20px">'
        . $paras . $bloco
        . '<p style="margin:0;font-size:14px;color:#5E636B">' . h(def('telefone')) . ' · ' . h(def('morada')) . ', ' . h(def('localidade')) . '</p>'
        . '</td></tr></table>'
        . '<p style="font-size:12px;color:#8C929A;margin:16px 0 0">Recebe este email porque subscreveu as promoções da ' . $nome . '. '
        . '<a href="' . h($linkSair) . '" style="color:#5E636B">Deixar de receber</a></p>'
        . '</td></tr></table></body></html>';
}


// ---------- Histórico de serviços e lembretes ----------
/** Cria a tabela do histórico se ainda não existir (para não ser preciso correr o setup outra vez). */
function garantir_historico(): void {
    static $feito = false;
    if ($feito) return;
    db()->exec("CREATE TABLE IF NOT EXISTS historico (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      marcacao_id INT UNSIGNED NULL,
      cliente VARCHAR(100) NOT NULL,
      telemovel VARCHAR(30) NOT NULL DEFAULT '',
      email VARCHAR(150) NOT NULL DEFAULT '',
      matricula VARCHAR(12) NOT NULL DEFAULT '',
      carro VARCHAR(100) NOT NULL DEFAULT '',
      data DATE NOT NULL,
      km INT UNSIGNED NULL,
      servico VARCHAR(500) NOT NULL,
      descricao TEXT NOT NULL,
      valor DECIMAL(8,2) NULL,
      proxima_data DATE NULL,
      proxima_km INT UNSIGNED NULL,
      lembrete_enviado_em DATETIME NULL,
      criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (matricula), INDEX (cliente), INDEX (data), INDEX (proxima_data)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $st = db()->prepare('INSERT IGNORE INTO definicoes (chave, valor) VALUES (?, ?)');
    foreach ([
        'lembrete_dias_antes' => '30',
        'revisao_meses'       => '12',
        'revisao_km'          => '15000',
        'lembrete_assunto'    => 'Está quase na hora da revisão do seu {carro}',
        'lembrete_texto'      => "Olá {nome},\n\nA próxima revisão do seu {carro} ({matricula}) está prevista para {data}{km_texto}.\n\nMarque já a sua revisão pelo site ou ligue-nos e escolhemos o dia que lhe der mais jeito.\n\nAté breve,\nEquipa {oficina}",
    ] as $k => $v) $st->execute([$k, $v]);
    $feito = true;
}

/** "54xp21" / "54 XP 21" -> "54-XP-21" */
function normalizar_matricula(string $m): string {
    $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $m));
    if (strlen($s) === 6) return substr($s, 0, 2) . '-' . substr($s, 2, 2) . '-' . substr($s, 4, 2);
    return mb_substr(strtoupper(trim($m)), 0, 12);
}

/** Substitui {nome}, {carro}... no texto do lembrete. */
function texto_lembrete(string $modelo, array $h): string {
    $primeiro = explode(' ', trim($h['cliente']))[0] ?? '';
    $km = $h['proxima_km'] ? number_format((int)$h['proxima_km'], 0, ',', ' ') . ' km' : '';
    return strtr($modelo, [
        '{nome}'      => $primeiro,
        '{carro}'     => $h['carro'] ?: 'carro',
        '{matricula}' => $h['matricula'],
        '{data}'      => $h['proxima_data'] ? date('d/m/Y', strtotime($h['proxima_data'])) : '',
        '{km}'        => $km,
        '{km_texto}'  => $km ? " ou aos $km, o que acontecer primeiro" : '',
        '{oficina}'   => def('nome_oficina', 'CMPNEUS'),
    ]);
}

/** Email HTML do lembrete de revisão. */
function email_lembrete(array $h): array {
    garantir_historico();
    $assunto = texto_lembrete(def('lembrete_assunto'), $h);
    $corpo = texto_lembrete(def('lembrete_texto'), $h);
    $base = url_base();
    $nome = h(def('nome_oficina', 'CMPNEUS'));
    $paras = '';
    foreach (preg_split('/\R{2,}/', trim($corpo)) as $p) {
        if (trim($p) !== '') $paras .= '<p style="margin:0 0 14px;font-size:16px;line-height:1.55;color:#1B1E22">' . nl2br(h(trim($p))) . '</p>';
    }
    $html = '<!DOCTYPE html><html lang="pt-PT"><head><meta charset="utf-8"><title>' . h($assunto) . '</title></head>'
        . '<body style="margin:0;background:#F2F3F0;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px"><tr><td style="padding:28px">'
        . '<img src="' . h($base) . 'assets/logo.png" alt="' . $nome . '" width="140" style="display:block;margin-bottom:20px">'
        . $paras
        . '<a href="' . h($base) . '#orcamento" style="display:inline-block;margin:6px 0 20px;background:#0B74C1;color:#fff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:8px">Marcar revisão</a>'
        . '<p style="margin:0;font-size:14px;color:#5E636B">' . h(def('telefone')) . ' · ' . h(def('morada')) . ', ' . h(def('localidade')) . '</p>'
        . '</td></tr></table></td></tr></table></body></html>';
    return [$assunto, $html, $corpo];
}

/** Revisões a aproximar-se: o registo mais recente de cada carro com próxima revisão até daqui a N dias, ainda sem lembrete. */
function revisoes_proximas(?int $dias = null): array {
    garantir_historico();
    $dias = $dias ?? max(1, (int)def('lembrete_dias_antes', '30'));
    $st = db()->prepare("SELECT h.* FROM historico h
        WHERE h.proxima_data IS NOT NULL
          AND h.proxima_data <= CURDATE() + INTERVAL ? DAY
          AND h.proxima_data >= CURDATE() - INTERVAL 60 DAY
          AND h.lembrete_enviado_em IS NULL
          AND NOT EXISTS (SELECT 1 FROM historico n WHERE n.matricula <> '' AND n.matricula = h.matricula
                          AND (n.data > h.data OR (n.data = h.data AND n.id > h.id)))
        ORDER BY h.proxima_data ASC");
    $st->execute([$dias]);
    return $st->fetchAll();
}

/** Envia o lembrete de um registo e marca-o como enviado. */
function enviar_lembrete(array $h): bool {
    if (!filter_var($h['email'], FILTER_VALIDATE_EMAIL)) { email_erro('Este cliente não tem email.'); return false; }
    [$assunto, $html] = email_lembrete($h);
    $ok = enviar_email($h['email'], $assunto, $html);
    if ($ok) db()->prepare('UPDATE historico SET lembrete_enviado_em = NOW() WHERE id = ?')->execute([$h['id']]);
    return $ok;
}
