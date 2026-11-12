# CMPNEUS — site + backoffice (PHP + MySQL)

## O que tem
- **Site público** (`index.php`): tudo vem da base de dados (contactos, morada, horário, serviços, preços, revisões, textos do topo).
- **Pedido de orçamento**: grava na base de dados (`api/marcacao.php`), com proteção anti-spam (campo escondido + máx. 5 pedidos/hora por IP) e email de aviso opcional.
- **Backoffice** (`/admin`):
  - Marcações: lista com filtros por estado, pesquisa, estatísticas, detalhe editável, dia/hora marcados, notas internas, botões Ligar/WhatsApp (mensagem pronta), criar marcação manual (pedidos por telefone).
  - Serviços (com escolha de ícone), Preços e Revisões: adicionar, editar, apagar, esconder e reordenar.
  - Dados da oficina: telefone, WhatsApp, email, morada, link do Maps, textos do site, email de avisos.
  - Horário: por dia da semana; alimenta a tabela, a barra do topo e o "Aberto agora".
  - Promoções: promoção do mês com destaque (ex.: -20%), texto, datas de início/fim, imagem opcional e botão. Aparece numa janela no site (uma vez por visitante, depois fica um botão no canto). "Copiar p/ mês seguinte" para reaproveitar.
  - Newsletter: lista de subscritores (site + adicionados à mão), exportar para Excel, escrever e enviar o email do mês com a promoção incluída, pré-visualizar, enviar teste e histórico de envios. Cada email tem link para deixar de receber.
  - Histórico de serviços: o que foi feito a cada carro (data, km, serviço, detalhes, valor), pesquisa por cliente, matrícula, telemóvel ou datas, ficha do carro, exportar para Excel. Numa marcação, "Registar serviço no histórico" preenche tudo e fecha a marcação.
  - Lembretes de revisão: a próxima revisão é calculada (+12 meses / +15 000 km, configurável) e os carros aparecem em "Revisões a aproximar-se" X dias antes. Envio por email (um a um ou todos), ou WhatsApp com a mensagem pronta. Texto do email editável em Dados da oficina. No alojamento, o envio pode ser automático com uma tarefa cron: `php /caminho/para/cron/lembretes.php` todos os dias.
  - A minha conta: mudar dados/palavra-passe e dar acesso a outra pessoa.

## Instalar no alojamento (cPanel/Plesk)
1. Criar uma base de dados MySQL e um utilizador com todas as permissões nela.
2. Enviar todos os ficheiros para `public_html` (ou subpasta).
3. Copiar `config.sample.php` para `config.php` e preencher os dados da base de dados.
4. Abrir `https://oseudominio.pt/setup.php` e criar a conta do backoffice.
5. **Apagar `setup.php` do servidor.**
6. Entrar em `https://oseudominio.pt/admin/` e substituir os dados de exemplo (telefone, morada, preços…).

Requisitos: PHP 8.0+ com PDO MySQL; MySQL 5.7+ ou MariaDB 10.3+. Usar HTTPS (o cPanel tem Let's Encrypt grátis).

## Atualizar uma instalação existente (promoções + newsletter)
1. Substituir os ficheiros no servidor (não apagar o `config.php`).
2. Enviar o `setup.php` e abri-lo uma vez no navegador: cria as tabelas novas sem tocar nos dados existentes.
3. Apagar de novo o `setup.php`.
4. Garantir que a pasta `uploads/promos` tem permissão de escrita (755).

## Notas
- Emails: configure o SMTP em `config.php` (Gmail, Outlook, Brevo ou o email do alojamento). Sem SMTP, usa o `mail()` do PHP, que não funciona no XAMPP e falha em muitos alojamentos. O botão "Enviar teste para mim" na página Newsletter mostra o erro exato se algo estiver mal.
- RGPD: a subscrição pede consentimento explícito, guarda a data e o IP, e cada email tem link para sair. Convém ter uma página de política de privacidade.
- `.htaccess` bloqueia acesso a `config.php`, `install.sql` e à pasta `inc/` (Apache). Em Nginx, bloqueie estas rotas na configuração.
- Segurança: consultas preparadas (PDO), palavras-passe com `password_hash`, tokens CSRF em todos os formulários, limite de tentativas de login (5 em 15 min), sessões `httponly`.
