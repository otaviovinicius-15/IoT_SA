CREATE DATABASE IF NOT EXISTS controle_ferramentas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE controle_ferramentas;


CREATE TABLE usuarios (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  nome   VARCHAR(100) NOT NULL,
  email  VARCHAR(100)  NOT NULL UNIQUE,
  senha  VARCHAR(255) NOT NULL,                      -- guardar com password_hash()
  perfil ENUM('administrador','operador') NOT NULL DEFAULT 'operador',
  ativo  BOOLEAN NOT NULL DEFAULT TRUE,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE funcionarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nome        VARCHAR(100) NOT NULL,
  setor       VARCHAR(60)  NULL,
  cartao_rfid VARCHAR(50)  NOT NULL UNIQUE,
  ativo       BOOLEAN NOT NULL DEFAULT TRUE,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ferramentas (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  nome                  VARCHAR(100) NOT NULL,
  quantidade_disponivel INT NOT NULL DEFAULT 0,
  estoque_minimo        INT NOT NULL DEFAULT 1,
  ativo                 BOOLEAN NOT NULL DEFAULT TRUE,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE movimentacoes (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  funcionario_id  INT NOT NULL,
  ferramenta_id   INT NOT NULL,
  data_retirada   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  prazo_devolucao DATETIME NOT NULL,
  data_devolucao  DATETIME NULL,                     -- NULL = ainda não devolvida
  FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id),
  FOREIGN KEY (ferramenta_id)  REFERENCES ferramentas(id)
);

INSERT INTO usuarios (nome, email, senha, perfil)
VALUES ('Administrador', 'admin@email.com', '$2y$10$IvZuI5SCMfDlUhOaTvQbL.BWo9TFnVIt4wp.kXYrQ0Aw6Aib3LBgO', 'administrador');

INSERT INTO funcionarios (nome, setor, cartao_rfid) VALUES
('João Silva',     'Manutenção', '1001'),
('Maria Souza',    'Produção',   '1002'),
('Carlos Pereira', 'Elétrica',   '1003');

INSERT INTO ferramentas (nome, quantidade_disponivel, estoque_minimo) VALUES
('Furadeira',         4, 2),
('Torquímetro',       2, 1),
('Multímetro',        3, 1),
('Chave de impacto',  1, 1);