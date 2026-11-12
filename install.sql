-- CMPNEUS — estrutura da base de dados (MySQL 5.7+ / MariaDB 10.3+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS definicoes (
  chave VARCHAR(60) NOT NULL PRIMARY KEY,
  valor TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS horario (
  dia TINYINT UNSIGNED NOT NULL PRIMARY KEY COMMENT '1=segunda ... 7=domingo',
  horas VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'ex.: 09:00-13:00, 14:00-19:00 (vazio = encerrado)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  descricao TEXT NOT NULL,
  icone VARCHAR(30) NOT NULL DEFAULT 'chave',
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS precos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  detalhe VARCHAR(160) NOT NULL DEFAULT '',
  preco DECIMAL(8,2) NOT NULL DEFAULT 0,
  prefixo VARCHAR(20) NOT NULL DEFAULT 'desde',
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS revisoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  preco DECIMAL(8,2) NOT NULL DEFAULT 0,
  intervalo VARCHAR(120) NOT NULL DEFAULT '',
  itens TEXT NOT NULL,
  destaque TINYINT(1) NOT NULL DEFAULT 0,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marcacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  telemovel VARCHAR(30) NOT NULL,
  email VARCHAR(150) NOT NULL DEFAULT '',
  matricula VARCHAR(12) NOT NULL DEFAULT '',
  carro VARCHAR(100) NOT NULL DEFAULT '',
  ano VARCHAR(4) NOT NULL DEFAULT '',
  km VARCHAR(20) NOT NULL DEFAULT '',
  servicos VARCHAR(500) NOT NULL DEFAULT '',
  medida_pneu VARCHAR(40) NOT NULL DEFAULT '',
  data_preferida DATE NULL,
  mensagem TEXT NOT NULL,
  estado ENUM('nova','confirmada','concluida','cancelada') NOT NULL DEFAULT 'nova',
  data_marcada DATETIME NULL,
  notas TEXT NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (estado), INDEX (criado_em), INDEX (data_marcada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS utilizadores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tentativas_login (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Dados iniciais (editáveis no backoffice) ----------
INSERT IGNORE INTO definicoes (chave, valor) VALUES
('nome_oficina', 'CMPNEUS'),
('telefone', '+351 900 000 000'),
('whatsapp', '351900000000'),
('email', 'geral@cmpneus.pt'),
('morada', 'Rua [nome da rua], n.º 00'),
('codigo_postal', '4400-000'),
('localidade', 'Vila Nova de Gaia'),
('maps_url', 'https://www.google.com/maps/search/?api=1&query=CMPNEUS+Vila+Nova+de+Gaia'),
('hero_titulo', 'Pneus, revisões e'),
('hero_destaque', 'arranjos'),
('hero_fim', 'numa só oficina.'),
('hero_texto', 'Da mudança de óleo à revisão completa, da troca de pneus à reparação de travões. Dizemos o preço antes de começar e só avançamos com a sua aprovação.'),
('factos', 'Todas | as marcas e modelos\n24 h | para responder ao seu orçamento\nPneus | novos, montagem e equilíbrio\nGaia | fácil acesso e estacionamento'),
('nota_precos', 'Pneus: o preço depende da marca e da medida. Peça orçamento com a medida e mostramos opções económicas, médias e premium.'),
('email_notificacoes', '');

INSERT IGNORE INTO horario (dia, horas) VALUES
(1,'09:00-13:00, 14:00-19:00'),(2,'09:00-13:00, 14:00-19:00'),(3,'09:00-13:00, 14:00-19:00'),
(4,'09:00-13:00, 14:00-19:00'),(5,'09:00-13:00, 14:00-19:00'),(6,'09:00-13:00'),(7,'');

INSERT INTO servicos (nome, descricao, icone, ordem) SELECT * FROM (
  SELECT 'Pneus' a, 'Venda e montagem de pneus novos, equilibragem, válvulas, reparação de furos e verificação de pressões.' b, 'pneu' c, 1 d UNION ALL
  SELECT 'Revisões', 'Revisão simples com mudança de óleo e filtro, ou revisão completa com todos os filtros e verificação geral.', 'oleo', 2 UNION ALL
  SELECT 'Travões', 'Pastilhas, discos, maxilas e óleo dos travões. Verificamos o desgaste e dizemos quando é mesmo preciso trocar.', 'travao', 3 UNION ALL
  SELECT 'Alinhamento', 'Alinhamento de direção para evitar desgaste irregular dos pneus e o carro a puxar para um lado.', 'alinhamento', 4 UNION ALL
  SELECT 'Diagnóstico', 'Leitura de avarias por computador. Luz do motor acesa? Descobrimos a causa antes de trocar peças.', 'diagnostico', 5 UNION ALL
  SELECT 'Suspensão', 'Amortecedores, molas, rótulas, braços e casquilhos. Ruídos e folgas resolvidos.', 'suspensao', 6 UNION ALL
  SELECT 'Ar condicionado', 'Carga de gás, deteção de fugas, higienização e troca do filtro do habitáculo.', 'ac', 7 UNION ALL
  SELECT 'Baterias e elétrica', 'Teste e substituição de baterias, alternadores, motores de arranque, lâmpadas e fusíveis.', 'bateria', 8 UNION ALL
  SELECT 'Mecânica geral', 'Correias de distribuição, embraiagens, escapes, bombas de água e preparação para a inspeção.', 'chave', 9
) t WHERE NOT EXISTS (SELECT 1 FROM servicos);

INSERT INTO precos (nome, detalhe, preco, ordem) SELECT * FROM (
  SELECT 'Revisão simples' a, 'Óleo + filtro de óleo + verificações' b, 59.00 c, 1 d UNION ALL
  SELECT 'Revisão completa', 'Óleo + todos os filtros + verificação geral', 129.00, 2 UNION ALL
  SELECT 'Montagem e equilibragem', 'Por pneu, válvula incluída', 12.00, 3 UNION ALL
  SELECT 'Reparação de furo', 'Pneu de ligeiro', 15.00, 4 UNION ALL
  SELECT 'Alinhamento de direção', 'Eixo dianteiro', 29.00, 5 UNION ALL
  SELECT 'Pastilhas de travão', 'Eixo, peças e mão de obra', 65.00, 6 UNION ALL
  SELECT 'Diagnóstico por computador', 'Descontado se fizer a reparação', 25.00, 7 UNION ALL
  SELECT 'Carga de ar condicionado', 'Com verificação de fugas', 55.00, 8 UNION ALL
  SELECT 'Substituição de bateria', 'Mão de obra, com teste do sistema de carga', 15.00, 9
) t WHERE NOT EXISTS (SELECT 1 FROM precos);

INSERT INTO revisoes (nome, preco, intervalo, itens, destaque, ordem) SELECT * FROM (
  SELECT 'Revisão simples' a, 59.00 b, 'Recomendada a cada 10 000–15 000 km ou 1 ano' c,
    'Mudança de óleo do motor\nSubstituição do filtro de óleo\nVerificação de níveis (refrigerante, travões, limpa-vidros)\nVerificação de pneus e pressões\nVerificação de luzes e escovas' d, 0 e, 1 f UNION ALL
  SELECT 'Revisão completa', 129.00, 'Recomendada a cada 30 000 km ou 2 anos',
    'Tudo o que inclui a revisão simples\nFiltro de ar, filtro de combustível e filtro do habitáculo\nVerificação de travões, discos e pastilhas\nVerificação de suspensão, direção e escape\nTeste de bateria e diagnóstico por computador\nRelatório com o que precisa de atenção', 1, 2
) t WHERE NOT EXISTS (SELECT 1 FROM revisoes);

-- ---------- Promoções e newsletter (v2) ----------
CREATE TABLE IF NOT EXISTS promocoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(100) NOT NULL,
  texto TEXT NOT NULL,
  destaque VARCHAR(30) NOT NULL DEFAULT '' COMMENT 'ex.: -20% ou 39 €',
  inicio DATE NOT NULL,
  fim DATE NOT NULL,
  botao_texto VARCHAR(40) NOT NULL DEFAULT 'Quero aproveitar',
  imagem VARCHAR(120) NOT NULL DEFAULT '',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (ativo, inicio, fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscritores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL UNIQUE,
  nome VARCHAR(80) NOT NULL DEFAULT '',
  token CHAR(32) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  origem VARCHAR(20) NOT NULL DEFAULT 'site',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  saiu_em DATETIME NULL,
  INDEX (ativo), INDEX (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_envios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assunto VARCHAR(150) NOT NULL,
  corpo TEXT NOT NULL,
  enviados INT NOT NULL DEFAULT 0,
  falhados INT NOT NULL DEFAULT 0,
  enviado_por VARCHAR(80) NOT NULL DEFAULT '',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO definicoes (chave, valor) VALUES
('promo_atraso', '4'),
('newsletter_titulo', 'Receba as promoções do mês'),
('newsletter_texto', 'Uma mensagem por mês, no máximo, com descontos em pneus, revisões e manutenção. Pode sair quando quiser.');

INSERT INTO promocoes (titulo, texto, destaque, inicio, fim, botao_texto)
SELECT 'Revisão completa em promoção', 'Revisão completa com todos os filtros e verificação de 30 pontos. Válido para ligeiros de passageiros, mediante marcação.', '-15%',
       DATE_FORMAT(CURDATE(), '%Y-%m-01'), LAST_DAY(CURDATE()), 'Marcar revisão'
WHERE NOT EXISTS (SELECT 1 FROM promocoes);

-- ---------- Histórico de serviços (v4) — também criado automaticamente pelo backoffice ----------
CREATE TABLE IF NOT EXISTS historico (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
