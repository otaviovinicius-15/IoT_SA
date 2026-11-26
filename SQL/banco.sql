CREATE DATABASE IF NOT EXISTS controle_ferramentas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE controle_ferramentas;


CREATE TABLE usuarios (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  nome   VARCHAR(100) NOT NULL,
  email  VARCHAR(100)  NOT NULL UNIQUE,
  senha  VARCHAR(255) NOT NULL,
  perfil ENUM('administrador','supervisor') NOT NULL DEFAULT 'supervisor',
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


CREATE TABLE armarios (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nome      VARCHAR(100) NOT NULL,
  ativo     BOOLEAN NOT NULL DEFAULT TRUE,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE armario_funcionarios (
  armario_id     INT NOT NULL,
  funcionario_id INT NOT NULL,
  PRIMARY KEY (armario_id, funcionario_id),
  FOREIGN KEY (armario_id)     REFERENCES armarios(id),
  FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)
);

CREATE TABLE armario_ferramentas (
  armario_id    INT NOT NULL,
  ferramenta_id INT NOT NULL,
  quantidade    INT NOT NULL DEFAULT 0,
  prazo_dias    INT NOT NULL DEFAULT 1,
  PRIMARY KEY (armario_id, ferramenta_id),
  FOREIGN KEY (armario_id)    REFERENCES armarios(id),
  FOREIGN KEY (ferramenta_id) REFERENCES ferramentas(id)
);

CREATE TABLE movimentacoes (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  armario_id      INT NULL,
  funcionario_id  INT NOT NULL,
  ferramenta_id   INT NOT NULL,
  quantidade      INT NOT NULL DEFAULT 1,
  data_retirada   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  prazo_devolucao DATETIME NOT NULL,
  data_devolucao  DATETIME NULL,
  FOREIGN KEY (armario_id)     REFERENCES armarios(id),
  FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id),
  FOREIGN KEY (ferramenta_id)  REFERENCES ferramentas(id)
);

CREATE TABLE acessos_armario (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  armario_id     INT NULL,
  funcionario_id INT NULL,
  cartao_rfid    VARCHAR(50) NOT NULL,
  autorizado     BOOLEAN NOT NULL,
  mensagem       VARCHAR(100) NOT NULL,
  data_hora      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (armario_id)     REFERENCES armarios(id),
  FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)
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
-- Armário 1 de exemplo (é o ARMARIO_ID 1 do sketch do ESP32)
INSERT INTO armarios (nome) VALUES ('Armário 1');

INSERT INTO armario_funcionarios (armario_id, funcionario_id) VALUES
(1, 1), (1, 2), (1, 3);

INSERT INTO armario_ferramentas (armario_id, ferramenta_id, quantidade, prazo_dias) VALUES
(1, 1, 2, 1),
(1, 2, 2, 1),
(1, 3, 2, 1),
(1, 4, 1, 1);