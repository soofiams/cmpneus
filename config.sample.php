<?php
// Copie este ficheiro para config.php e preencha os dados.
return [
    // ---------- Base de dados (criada no cPanel / phpMyAdmin) ----------
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'cmpneus',
    'db_user' => 'utilizador_da_bd',
    'db_pass' => 'palavra_passe_da_bd',

    // ---------- Envio de emails (newsletter e avisos de novos pedidos) ----------
    // Remetente dos emails. Com Gmail tem de ser o próprio email Gmail.
    'email_remetente' => 'geral@cmpneus.pt',

    // SMTP — deixe smtp_host vazio para usar o mail() do PHP (não funciona no XAMPP).
    //
    // Gmail:      smtp.gmail.com     porta 587  'tls'  (precisa de "palavra-passe de aplicação")
    // Outlook:    smtp.office365.com porta 587  'tls'
    // Brevo:      smtp-relay.brevo.com porta 587 'tls' (utilizador e chave SMTP no painel da Brevo)
    // Alojamento: mail.cmpneus.pt    porta 465  'ssl'  (dados da conta de email criada no cPanel)
    'smtp_host'      => '',
    'smtp_porta'     => 587,
    'smtp_seguranca' => 'tls',   // 'tls' (587), 'ssl' (465) ou '' (sem encriptação)
    'smtp_user'      => '',
    'smtp_pass'      => '',
];
