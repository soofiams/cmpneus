<?php
// Envio automático dos lembretes de revisão.
// Para usar no alojamento: cPanel → Tarefas Cron → todos os dias às 10h00:
//   php /home/UTILIZADOR/public_html/cron/lembretes.php
// (No computador/XAMPP não é preciso: use o botão "Enviar email a todos" no backoffice.)
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Só pode ser corrido pela linha de comandos.'); }

require __DIR__ . '/../inc/bootstrap.php';

$ok = $falha = $semEmail = 0;
foreach (revisoes_proximas() as $h) {
    if (!filter_var($h['email'], FILTER_VALIDATE_EMAIL)) { $semEmail++; continue; }
    if (enviar_lembrete($h)) {
        $ok++;
        echo date('Y-m-d H:i') . " enviado: {$h['email']} ({$h['matricula']})\n";
    } else {
        $falha++;
        echo date('Y-m-d H:i') . " FALHOU: {$h['email']} — " . email_erro() . "\n";
        if ($falha >= 3 && $ok === 0) break;
    }
}
echo "Enviados: $ok · Falharam: $falha · Sem email (avisar por WhatsApp/telefone): $semEmail\n";
