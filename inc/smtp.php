<?php
// Cliente SMTP simples (sem bibliotecas externas): SSL/TLS, STARTTLS e AUTH LOGIN.
// Funciona com Gmail (palavra-passe de aplicação), Brevo, Outlook/Office 365 e o SMTP do alojamento.
declare(strict_types=1);

class Smtp {
    private $sock = null;
    public string $log = '';

    public function __construct(
        private string $host,
        private int $porta,
        private string $seguranca, // 'tls' (porta 587), 'ssl' (porta 465) ou '' (sem encriptação)
        private string $user,
        private string $pass,
        private int $timeout = 20
    ) {}

    /** Liga e autentica. Lança Exception com uma mensagem legível se falhar. */
    public function ligar(): void {
        if ($this->sock) return;
        $alvo = ($this->seguranca === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->porta;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $this->host]]);
        $sock = @stream_socket_client($alvo, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) throw new Exception("Não foi possível ligar a {$this->host}:{$this->porta} ($errstr). Confirme o servidor e a porta.");
        stream_set_timeout($sock, $this->timeout);
        $this->sock = $sock;

        $this->esperar(220);
        $eu = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
        $this->cmd("EHLO $eu", 250);
        if ($this->seguranca === 'tls') {
            $this->cmd('STARTTLS', 220);
            $metodo = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $metodo |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) $metodo |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            if (!@stream_socket_enable_crypto($this->sock, true, $metodo)) throw new Exception('Falhou a ligação segura (STARTTLS). Experimente a porta 465 com SSL.');
            $this->cmd("EHLO $eu", 250);
        }
        if ($this->user !== '') {
            $this->cmd('AUTH LOGIN', 334);
            $this->cmd(base64_encode($this->user), 334, 'utilizador');
            try {
                $this->cmd(base64_encode($this->pass), 235, 'palavra-passe');
            } catch (Exception $e) {
                throw new Exception('O servidor recusou o utilizador ou a palavra-passe do email. No Gmail tem de usar uma "palavra-passe de aplicação", não a palavra-passe normal. (' . $e->getMessage() . ')');
            }
        }
    }

    /** Envia uma mensagem já montada (cabeçalhos + corpo). */
    public function enviar(string $de, string $para, string $mensagem): void {
        $this->ligar();
        $this->cmd("MAIL FROM:<$de>", 250);
        $this->cmd("RCPT TO:<$para>", [250, 251]);
        $this->cmd('DATA', 354);
        // "Dot-stuffing": linhas que começam por ponto
        $mensagem = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $mensagem));
        $mensagem = str_replace("\n", "\r\n", $mensagem);
        $this->escrever($mensagem . "\r\n.");
        $this->esperar(250);
    }

    /** Depois de um erro num destinatário, limpa o estado para continuar com os seguintes. */
    public function reset(): void {
        if ($this->sock) { try { $this->cmd('RSET', 250); } catch (Exception $e) { $this->fechar(); } }
    }

    public function fechar(): void {
        if ($this->sock) { @fwrite($this->sock, "QUIT\r\n"); @fclose($this->sock); }
        $this->sock = null;
    }

    public function __destruct() { $this->fechar(); }

    private function cmd(string $linha, $esperado, string $oculto = ''): string {
        $this->escrever($linha, $oculto);
        return $this->esperar($esperado);
    }

    private function escrever(string $linha, string $oculto = ''): void {
        $this->log .= 'C: ' . ($oculto ? "[$oculto]" : (strlen($linha) > 200 ? '[mensagem]' : $linha)) . "\n";
        if (@fwrite($this->sock, $linha . "\r\n") === false) throw new Exception('A ligação ao servidor de email foi interrompida.');
    }

    private function esperar($esperado): string {
        $resp = '';
        while (($l = fgets($this->sock, 1024)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') break;
        }
        $this->log .= 'S: ' . trim($resp) . "\n";
        $codigo = (int)substr($resp, 0, 3);
        if (!in_array($codigo, (array)$esperado, true)) {
            if ($resp === '') throw new Exception('O servidor de email não respondeu (tempo esgotado).');
            throw new Exception('Resposta do servidor: ' . trim($resp));
        }
        return $resp;
    }
}
