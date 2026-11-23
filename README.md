# SA_IoT

Internet das Coisas

---

## 📊 Estrutura Organizacional
![Estrutura Organizacional](pictures/Estrutura-Organizacional.png)

---

## 🧩 Business Model Canvas
🔗 https://canva.link/jg32m8yajdrwedx

---

## 🌍 Área de Atuação

Gestão e Controle de Estoque 

---

## ⚠️ Problema

- A falta de um sistema automatizado e seguro para o controle de retirada e devolução de ferramentas especiais pode ocasionar perdas, extravios, acessos não autorizados e dificuldades na identificação dos responsáveis por cada movimentação.

---

## 💡 Solução

- Sistema inteligente de controle de estoque de ferramentas especiais, utilizando tecnologia RFID para gerenciar e monitorar os processos de retirada e devolução dos equipamentos, garantindo que essas operações sejam realizadas exclusivamente por funcionários autorizados.

---

## ⚖️ Regras de Negócio

- Somente funcionários cadastrados e autorizados poderão realizar retiradas e devoluções de ferramentas.
- Cada funcionário deverá possuir um cartão RFID vinculado ao seu cadastro no sistema.
- O sistema deverá identificar o funcionário por meio da leitura do cartão RFID.
- Toda retirada deverá ser registrada no banco de dados, contendo funcionário, ferramenta, data e horário.
- O sistema deve impedir a retirada de ferramentas quando não houver unidades disponíveis no estoque.
- Toda devolução deverá ser vinculada à movimentação de retirada correspondente.
- O sistema deverá atualizar automaticamente a quantidade de ferramentas disponíveis após cada retirada ou devolução.
- O sistema deverá emitir alertas quando a quantidade de ferramentas atingir um nível inferior ao mínimo estabelecido pela empresa.
- O sistema deverá identificar ferramentas que não forem devolvidas dentro do prazo previsto e enviar notificações ao setor responsável.
- O sistema deverá manter um histórico das movimentações para permitir consultas e auditorias.
- O sistema deverá permitir que usuários com perfil autorizado cadastrem, editem e inativem ferramentas e funcionários.

---

## ⚙️ Requisitos Funcionais

- Autenticação.
- Dashboard.
- Histórico de Movimentações.

---

## 🛡️ Requisitos Não Funcionais

- O sistema deverá proteger os dados e restringir o acesso conforme o perfil de cada usuário.
- O sistema deverá processar as leituras RFID e registrar as movimentações rapidamente.
- O sistema deverá estar disponível durante o horário de funcionamento da empresa.
- A interface deverá ser simples, intuitiva e de fácil utilização.
- O sistema deverá registrar corretamente as retiradas, devoluções e atualizações do estoque.
- As informações armazenadas no banco de dados deverão ser consistentes e protegidas contra alterações indevidas.
- O sistema web deverá funcionar nos principais navegadores.
- O sistema deverá permitir futuras atualizações e melhorias.
- O banco de dados deverá possuir cópias de segurança para evitar a perda de informações.
- A comunicação entre ESP32, broker MQTT e sistema deverá ocorrer de forma estável e confiável.

---

## 📊 Modelagem Lógica Banco
![Modelagem Lógica Banco]()
