# 🏄‍♂️ SpecKit Vibe Coder: A Constituição do Projeto
<!--
=============================================================================
🤖 INSTRUÇÕES PARA O AGENTE DE IA
=============================================================================

Este é o arquivo TECH_STACK.md do framework SpecKit Vibe Coder.
Ele contém perguntas técnicas que definem a arquitetura e os padrões
de um projeto de software. Cada pergunta apresenta duas opções de resposta.

## MODO 1 — speckit.constitution
Ativado quando o usuário envia o comando: speckit.constitution

O que você deve fazer:
- Ler este arquivo integralmente e adotá-lo como o guia técnico oficial do projeto.
- Em TODAS as respostas, sugestões de código e decisões de arquitetura,
  respeitar as escolhas documentadas aqui.
- Não sugerir abordagens que contradigam as opções já escolhidas.
- Se uma pergunta ainda tiver DUAS opções (não decidida), alertar o usuário
  que aquela decisão precisa ser tomada antes de avancar naquele tema.

## MODO 2 — speckit.agent
Ativado quando o usuário envia o comando:
  speckit.agent [descrição do projeto]

O que você deve fazer:
1. Ler cada seção e cada pergunta deste arquivo.
2. Com base na descrição do projeto fornecida pelo usuário, escolher a
   opção mais adequada para cada pergunta.
3. No arquivo final, manter apenas a opção escolhida e remover a outra.
4. Logo abaixo da opção escolhida, adicionar uma linha no formato:
   > 💡 Motivo: [justificativa objetiva de 1 a 2 linhas]
5. Ao final do processo, apresentar um resumo das decisões tomadas,
   agrupadas por seção.

## FORMATO DAS PERGUNTAS
Cada pergunta segue a estrutura:
  ### Título da Pergunta
  Contexto explicativo.
  - Opção A: descrição. _Vibe:_ consequência.
  - Opção B: descrição. _Vibe:_ consequência.

## CRITÉRIOS DE DECISÃO (speckit.agent)
Use os seguintes critérios para guiar as escolhas ao responder como agente:
- Projetos em fase inicial/MVP: priorize simplicidade e velocidade.
- Projetos com equipes pequenas (1–3 devs): evite complexidade desnecessria.
- Projetos com dados sensíveis (financeiro, saúde): priorize segurança e auditoria.
- Projetos SaaS: atenção especial a multi-tenancy, planos e faturamento.
- Projetos com SEO importante: SSR sobre SPA.
- Projetos com alta concorrência esperada: cache, filas e indexação.
- Quando houver dúvida, escolha a opção mais simples e documente a razão.
=============================================================================
-->
## 📖 O que é isso e para que serve?

Sabe aquele projeto que começa maravilhoso, mas depois de 3 meses vira um monstro onde ninguém tem coragem de alterar uma linha de código por medo de quebrar tudo? O SpecKit existe para evitar isso.

Ele é o nosso Guia de Sobrevivência. Serve para alinhar as expectativas técnicas de todo mundo _antes_ da gente começar a codar. Ao invés de discutir no meio do projeto como lidar com o banco de dados ou como salvar imagens, a gente decide agora. É a nossa regra do jogo.

### 🛠️ Como usar?

1. Reúna o bonde: Marquem uma call de 1 horinha ou peçam uma pizza no escritório.
2. Leiam juntos: Passem por cada pergunta e leiam a descrição.
3. Debatam a "Vibe": Discutam qual opção faz mais sentido para o momento atual do projeto (Agilidade extrema para validar a ideia VS. Segurança e escalabilidade para o longo prazo).
4. Batam o martelo: Remova a responda que não é adequada, deixe apenas a resposta escolhida (ou escrevam uma nova se precisar).
5. A Lei está feita: Salvem esse documento no repositório do projeto com o nome TECH_STACK.md. A partir de hoje, essa é a regra que guia o Code Review e as decisões do dia a dia.

### ⛑️ Não sabe como usar?

Copie a pergunta e cole com o seguinte prompt na sua IA favorita:

>Sou leigo em programação e você é meu Tech Lead. Me ajude a responder a pergunta abaixo:
>[COLAR PERGUNTA]

---

## 1. A Planta da Casa (Arquitetura e Organização)

_Se a gente não arrumar o quarto hoje, amanhã ninguém acha a meia._

Antes de escrever uma única linha de código, a equipe precisa decidir como o projeto será organizado. A arquitetura é o "mapa da mina": define onde cada coisa fica, como os pedaços se conectam e quem é responsável por quê. Projetos sem arquitetura definida viram "código espaguete" — difícil de testar, impossível de escalar e um pesadelo para quem entra no time depois. As decisões desta seção custam pouco agora e economizam meses de refatoração no futuro.

### Qual linguagem, framework e bibliotecas o time vai usar?

A fundação técnica do projeto. Definir isso antes evita que o código misture padrões, que reviews fiquem inconsistentes e que a IA sugira coisas diferentes em arquivos diferentes.

- Não definido / cada dev usa o que prefere: _Vibe:_ Liberdade no curto prazo, caos no médio. Code review vira guerra de preferências e onboarding de novos membros fica impossível.
- Stack padronizada (linguagem + framework + libs principais definidos): _Vibe:_ Todo mundo fala a mesma língua. A IA entende o projeto. Pull requests são mais previsíveis.

**Stack definida:**
- Linguagem: `________________`
- Framework principal: `________________`
- Banco de dados: `________________`
- Principais bibliotecas: `________________`

### Onde escrevemos as regras pesadas do app?

Todo app tem regras de negócio (ex: calcular frete, validar se tem saldo).

- Tudo no mesmo lugar (Controllers/Rotas): É a rota que recebe o clique e já faz tudo lá mesmo. _Vibe:_ Rápido no começo, mas vira um arquivo infinito.
- Na "gaveta" de Serviços (Service Layer): Criamos arquivos separados só pra regra de negócio (ex: PagamentoService). _Vibe:_ Fica lindo de ler, qualquer novato entende.

### Como agrupamos nossos arquivos e pastas?

A estrutura de pastas do projeto.

- Por Tipo Técnico: Pasta Controllers, pasta Models, pasta Views. _Vibe:_ Padrão da maioria dos frameworks, fácil de começar.
- Por Assunto/Feature: Pasta Usuarios (com rotas, models e regras dele lá dentro), pasta Produtos. _Vibe:_ Bom para apps gigantes, mas dá trabalho configurar no início.

### Textos e Status "Mágicos" (ex: "PAGO", "PENDENTE")

Como o código sabe se um pedido está pago?

- Escrevemos direto no código (Hardcoded): A gente digita "PAGO" em todo lugar que precisa. _Vibe:_ Perigoso. Se alguém digitar "Pago", o sistema quebra.
- Usamos um "Dicionário" (Enums/Constantes): A gente cria um arquivo que diz STATUS*PAGO = "PAGO" e usa isso. _Vibe:* Previne erros bobos de digitação.

### Como nomeamos as coisas no código?

Variáveis, funções e tabelas.

- Mistureba: Português, inglês, nomeUsuario, nome*usuario. _Vibe:* Confusão mental absurda.
- Padronizado: Escolhemos o idioma (ex: Código em Inglês) e o formato (ex: variáveis sempre camelCase). _Vibe:_ Paz de espírito.

### Como lidamos com ferramentas de terceiros (ex: Stripe, Correios)?

Como chamamos serviços externos.

- Cola direto no nosso código: Chama a ferramenta direto onde precisa. _Vibe:_ Se os Correios mudarem o sistema, o nosso quebra inteiro.
- Criamos um "Tradutor" (Interfaces): A gente cria o nosso jeito de pedir frete, e por trás dos panos o tradutor fala com os Correios. _Vibe:_ Trocar de Correios pra Loggi vira tarefa de 10 minutos no futuro.

### O sistema tem planos com limites de uso (ex: Free, Pro, Enterprise)?

Arquitetura de limites de uso.

- Sem limites: Todo usuário acessa tudo igual. _Vibe:_ Simples de começar, mas impossível de monetizar depois sem reescrever.
- Limites por plano (Usage Limits): O código verifica o plano do cliente antes de deixar criar um novo projeto, enviar e-mail etc. _Vibe:_ Precisa ser decidido no dia zero. Consertar depois é cirurgia no coração.

### O sistema precisa emitir Nota Fiscal ou boleto?

Integração fiscal e financeira.

- Não por agora: _Vibe:_ Ok para MVPs, mas lembrar que o modelo de dados de cliente (CPF/CNPJ, endereço fiscal) muda quando chegar a hora.
- Sim, desde o início (NF-e / NFS-e / Boleto): Integração com SEFAZ ou gateway financeiro. _Vibe:_ Muda a estrutura do cadastro todo. Decidir agora evita reescrever o módulo de cliente mais tarde.

### Como organizamos os repositórios do projeto?

Monorepo vs Polyrepo — onde cada base de código mora.

- Repositórios separados por projeto/serviço (Polyrepo): Cada app, serviço ou biblioteca tem o seu próprio repositório Git. _Vibe:_ Isolamento claro de responsabilidades, mas sincronizar mudanças que afetam vários repos é trabalhoso e o CI/CD multiplica.
- Tudo num repositório só (Monorepo): Front, back, libs e microserviços convivem no mesmo repositório com ferramentas como Turborepo, Nx ou pnpm workspaces. _Vibe:_ Mudanças atômicas entre projetos, compartilhamento fácil de código e uma única pipeline de CI. Exige configuração inicial mais cuidadosa.

---

## 💾 2. O Baú do Tesouro (Banco de Dados)

_Seus dados são sagrados. Se o código quebrar, o dado tem que estar a salvo._

O banco de dados é a memória permanente do app. Tudo que o usuário faz, paga, escreve ou compra vive aqui. As decisões desta seção definem não só como os dados são salvos, mas como são protegidos, organizados e recuperados. Um banco mal projetado é como uma gaveta onde se joga tudo junto: funciona no início, mas depois de 6 meses ninguém acha nada e todo mundo tem medo de mexer. Normalização, índices, transações e migrations não são detalhes de DBA — são decisões de produto que afetam diretamente a velocidade e a confiabilidade do app.

### Qual o tipo de banco de dados principal do projeto?

A escolha entre banco relacional e não-relacional molda como os dados são estruturados, consultados e escalados.

- Banco Relacional (SQL): PostgreSQL, MySQL, SQLite. Dados em tabelas com linhas e colunas, relacionamentos com JOINs e garantias ACID. _Vibe:_ A escolha certa para 95% dos projetos. Dados estruturados, queries complexas e integridade garantida pelo banco. Postgres é a recomendação padrão para novos projetos.
- Banco Não-Relacional (NoSQL): MongoDB, DynamoDB, Redis, Cassandra. Dados em documentos, chave-valor, grafos ou colunas largas. _Vibe:_ Indicado para casos específicos: alta escala de escrita, dados sem esquema fixo, cache ou grafos de relacionamento. Usar por padrão sem necessidade real adiciona complexidade sem benefício.

**Banco escolhido:** `________________`

### Quando o usuário clica em "Excluir Conta"?

A gente apaga mesmo ou só finge que apagou?

- Apaga tudo (Hard Delete): Deleta do banco de vez. _Vibe:_ Libera espaço, mas não tem choro nem vela se o usuário se arrepender.
- Esconde embaixo do tapete (Soft Delete): Ganha uma etiqueta "Deletado" mas continua lá. _Vibe:_ Salva sua pele se der processo ou o usuário pedir pra voltar.

### Quem é o segurança da porta dos dados?

Se alguém tentar salvar um "telefone" com letras, quem barra?

- Só o Código: O nosso código checa e avisa. O banco de dados aceita qualquer coisa. _Vibe:_ Mais fácil de programar, mas se tiver um bug no código, o banco vira uma bagunça.
- O Banco também (Constraints): A gente ensina o banco a rejeitar lixo (ex: "Não aceite preço negativo"). _Vibe:_ Blindagem máxima.

### Como guardamos dados muito variados (ex: Preferências de Tema)?

Dados que mudam de formato toda hora.

- Uma coluna pra cada coisa: Criamos a coluna tema*escuro, receber_email, etc. _Vibe:* Estruturado, mas toda hora tem que criar coluna nova no banco.
- Sacola Flexível (Coluna JSON/JSONB): Jogamos tudo numa coluna de texto chamada configuracoes. _Vibe:_ Muito flexível, mas ruim pra fazer relatórios e buscas complexas depois.

### Listas muito grandes (ex: Feed com 10 mil posts)

Como devolver as postagens sem travar o app.

- Páginas (1, 2, 3): O banco conta tudo e divide (Offset). _Vibe:_ Clássico, mas fica lento quando passa de milhões de registros.
- Scroll Infinito (Cursor): O banco só pega "os próximos 20 depois do ID atual". _Vibe:_ Ultra rápido não importa o tamanho do banco.

### E se a gente precisar adicionar tabelas novas?

O processo de alterar a estrutura do banco.

- Fazemos na mão: Entra no painel do banco e cria. _Vibe:_ Funciona na sua máquina, mas quebra quando o amigo for testar.
- Receita de Bolo (Migrations): O código tem um arquivo ensinando a criar a tabela. _Vibe:_ Todo mundo roda o comando e fica com o banco idêntico.

### Onde fazemos as contas (ex: "Soma o total de vendas do mês")?

Matemática e agregação de dados.

- No Código (PHP/Node/Python): Pega todas as vendas do banco e soma no código. _Vibe:_ Se tiver 1 milhão de vendas, o app trava e morre.
- No Banco (SQL): A gente manda a pergunta pro banco, e ele devolve só o resultado. _Vibe:_ O banco foi feito pra isso. Velocidade máxima.

### Precisamos saber quem mudou o quê e quando?

Auditoria de alterações.

- Não precisamos: Salvamos só o estado atual do dado. _Vibe:_ Mais simples, mas se alguém alterar algo errado, não tem como rastrear.
- Guardamos o histórico (Audit Log): Cada alteração salva quem fez, o que era antes e o que virou. _Vibe:_ Essencial para sistemas financeiros, médicos ou qualquer app que precise prestar contas.

### O sistema atende uma empresa ou várias ao mesmo tempo?

Arquitetura multi-tenancy.

- Uma empresa só (Single-tenant): O banco tem os dados de um cliente só. _Vibe:_ Mais simples de construir.
- Várias empresas isoladas (Multi-tenant): Um banco só, mas cada empresa vê apenas os seus dados. _Vibe:_ Essencial para SaaS. Muda a estrutura do banco radicalmente e precisa ser decidido no dia zero.

### As tabelas foram normalizadas ou ficamos com dados repetidos?

Normalização do banco (Formas Normais).

- Dados repetidos à vontade (Desnormalizado): O mesmo endereço do cliente aparece em 10 tabelas diferentes. _Vibe:_ Rápido de montar no começo, mas atualizar um dado vira pesadelo: tem que caçar e corrigir em vários lugares.
- Normalizado (1NF, 2NF, 3NF): Cada dado mora em um lugar só. O endereço fica na tabela de endereços, e as outras tabelas referenciam ele. _Vibe:_ Mais organizado e consistente. A desnormalização pode ser feita depois, intencionalmente, por performance.

### Como as tabelas se "conversam" no banco?

Relacionamentos e integridade referencial.

- Só guardamos o ID no código e acreditamos: A coluna pedido.usuario_id tem um número, mas o banco não sabe que ele aponta pra tabela de usuários. _Vibe:_ Se alguém deletar o usuário, os pedidos ficam apontando pro vazio (registro órfão).
- Foreign Keys de verdade (FK): O banco sabe o relacionamento e rejeita qualquer operação que quebre a consistência. _Vibe:_ O banco vira um guardião: impossível ter pedido sem usuário.

### Escrevemos SQL na mão ou usamos um "Tradutor" (ORM)?

Estratégia de acesso ao banco.

- SQL Puro: Escrevemos as queries diretamente. _Vibe:_ Controle total e máxima performance, mas mais verboso e sujeito a erro humano.
- ORM (ex: Prisma, Eloquent, Hibernate, SQLAlchemy): O código faz `Usuario.find(1)` e o ORM escreve o SQL. _Vibe:_ Produtividade muito maior no dia a dia. Atenção: queries geradas automaticamente podem ser ineficientes e precisam de supervisão.

### Qual ORM, query builder e ferramenta de migration o time vai adotar?

Padronizar a ferramenta evita que metade do código use Prisma e a outra metade use SQL puro — o que transforma code review em adivinhação. A ferramenta de migration define como o schema do banco evolui junto com o código: sem ela, cada máquina do time pode estar rodando uma versão diferente do banco.

- Ainda não decidimos / cada dev escolhe na hora: _Vibe:_ Inconsistência garantida. Migrations feitas na mão quebram no ambiente de outro dev ou em produção.
- Ferramenta padrão definida e migrations obrigatórias no repositório: _Vibe:_ Qualquer pessoa do time roda um comando e fica com o banco idêntico ao de todos. Schema versionado junto com o código.

**Ferramentas definidas:**
- ORM / Query builder: `________________` _(ex: Prisma, Drizzle, TypeORM, Sequelize, Eloquent, SQLAlchemy, ActiveRecord)_
- Migration tool: `________________` _(ex: embutido no ORM, Flyway, Liquibase, golang-migrate)_

### Quais colunas têm índice no banco?

Estratégia de indexação.

- Nenhum índice extra (só a Primary Key): _Vibe:_ O banco varre a tabela inteira a cada busca. Com 100 mil registros, começa a doer. Com 1 milhão, o app trava.
- Índices nas colunas certas: Qualquer coluna usada em WHERE, ORDER BY ou JOIN frequente ganha um índice. _Vibe:_ A diferença entre uma query de 3 segundos e 3 milissegundos. Precisa de análise com EXPLAIN para decidir o que indexar.

### E se duas operações no banco precisam acontecer juntas ou não acontecem?

Transações (Transactions).

- Sem transação: Salva o pagamento, mas antes de atualizar o estoque o servidor cai. Agora o dinheiro saiu e o produto não foi descontado. _Vibe:_ Dado inconsistente. Dor de cabeça jurídica e operacional.
- Dentro de uma Transaction: As duas operações são embrulhadas juntas. Se qualquer uma falhar, as duas voltam atrás (rollback). _Vibe:_ Tudo ou nada. Obrigatório para qualquer fluxo financeiro ou de estoque.

---

## ⚡ 3. A Velocidade da Luz (Performance e Filas)

_O usuário tem paciência zero. Se demorar 3 segundos, ele fecha a aba._

Velocidade não é luxo — é requisito. Estudos mostram que cada segundo extra de carregamento reduz conversões em até 7%. Esta seção cobre as armadilhas clássicas que deixam apps lentos: tarefas pesadas bloqueando o usuário, consultas repetidas ao banco, imagens gigantes e servidores que não aguentam pico de acesso. A boa notícia: a maioria dos problemas de performance tem solução conhecida (cache, filas, compressão, eager loading). O segredo é decidir antes de precisar apagar incêndio.

### Tarefas que demoram (Gerar relatórios, mandar E-mail)

O usuário tem que esperar o envio terminar?

- Espera aí: O usuário fica olhando a tela carregar até terminar. _Vibe:_ Parece que o app travou.
- Fila de Background: A tela dá "Sucesso!" na hora, e o e-mail vai para uma fila pra ser enviado escondido pelo servidor. _Vibe:_ Experiência de app premium.

### O App bombou na Home! Como aliviar?

Muita gente acessando a mesma página ao mesmo tempo.

- Deixa sofrer: Toda vez que alguém entra, o banco de dados trabalha. _Vibe:_ O servidor vai explodir de uso de CPU.
- Tira foto e guarda (Cache/Redis): O servidor monta a tela uma vez, guarda na memória, e entrega a cópia para os próximos mil acessos. _Vibe:_ O segredo dos apps gigantes.

### Problema do N+1 (Consultas repetidas no banco)

Como buscar listas e seus relacionamentos (Posts e seus Autores).

- Buscar um por um: Busca 10 posts, e depois faz 10 consultas separadas pra buscar o autor de cada post. _Vibe:_ Assassino silencioso de performance.
- Trazer tudo na mala (Eager Loading): Faz uma consulta que já pede "Me dê os posts E seus autores de uma vez". _Vibe:_ Muito mais rápido e eficiente.

### Tamanho das imagens dos usuários (Avatar/Fotos)

O usuário sobe uma foto de 10MB tirada no iPhone.

- Pode mandar arquivo gigante: Salva o JPEG direto. _Vibe:_ Seu armazenamento vai pro espaço e a tela dos outros vai demorar a carregar.
- Comprime na porta: O sistema reduz a qualidade e o tamanho da foto (ex: WebP) antes de salvar. _Vibe:_ Economiza dinheiro de servidor e carrega rápido na 3G.

### Se a API externa (Correios/ChatGPT) cair?

Lidando com falhas de terceiros.

- Nosso App quebra junto: O usuário vê tela de erro. _Vibe:_ Frustrante.
- Plano B (Fallback): Mostra uma mensagem amigável "Serviço indisponível" mas deixa o cara navegar no resto do app. _Vibe:_ Resiliência.

### O usuário afobado (Clica 10x no botão de Comprar)

Evitando requisições duplicadas.

- Deixa clicar: O sistema processa 10 compras idênticas. _Vibe:_ Dor de cabeça com estorno.
- Trava o botão (Debounce / Rate Limit): O botão desabilita no primeiro clique ou o servidor ignora pedidos repetidos muito rápidos. _Vibe:_ Seguro contra afobados e hackers.

### Como o usuário encontra conteúdo dentro do app?

Estrategia de busca.

- Filtros simples (SQL LIKE): Consulta direta no banco com filtros de texto. _Vibe:_ Funciona bem para começo, mas degrada com volume e não entende erros de digitação.
- Motor de Busca dedicado (Elasticsearch / Algolia / Typesense): Serviço separado indexa o conteúdo e responde buscas complexas. _Vibe:_ Busca inteligente e rápida mesmo com milhões de registros, mas adiciona uma peça nova na infraestrutura.

---

## 🔒 4. Os Seguranças da Balada (Segurança)

_Proteger os dados é mais barato do que pagar advogado depois do vazamento._

Segurança não é uma feature — é uma fundação. Vazamentos de dados destroem a reputação do produto, geram multas milionárias da LGPD e podem resultar em processos criminais. Esta seção cobre as falhas mais comuns (e mais fáceis de evitar): senhas salvas sem criptografia, tokens sem prazo de validade, acesso sem verificação de propriedade e dados sensíveis expostos por descuido. A maioria dos ataques não é sofisticada — eles exploram preguiça e falta de combinação prévia dentro do time.

### Como o app lembra quem fez Login?

Gerenciamento de sessão.

- Cookies Seguros (Web): O navegador cuida disso sozinho, blindado contra roubo por scripts da tela. _Vibe:_ Melhor para sites tradicionais e painéis.
- Tokens (Mobile/APIs): O celular guarda um token longo e manda a cada clique. _Vibe:_ Essencial se você tiver um App nas lojas (iOS/Android).

### Onde guardamos as Chaves Secretas (Senha do Banco, API Keys)?

Acesso a serviços restritos.

- No código fonte: Escrito lá no arquivo. _Vibe:_ 🚨 ERRO FATAL. Se subir pro GitHub, já era.
- No cofre invisível (.env): Num arquivo isolado que NUNCA é enviado pro GitHub. _Vibe:_ O único jeito certo.

### Como as chaves secretas chegam ao servidor de produção?

O .env resolve no computador do dev, mas em produção as variáveis precisam chegar ao servidor de forma segura — sem aparecer em logs, painéis de CI ou repositórios.

- Variáveis de ambiente configuradas manualmente no painel do servidor: Digitamos as chaves diretamente no painel da Vercel, Railway, Heroku ou no servidor. _Vibe:_ Simples e suficiente para a maioria dos projetos. O risco é não ter histórico de quem alterou o quê e esquecer de atualizar quando a chave mudar.
- Gerenciador de segredos (AWS Secrets Manager, HashiCorp Vault, Doppler, 1Password Secrets): As chaves ficam em um cofre centralizado com controle de acesso, auditoria e rotação automática. _Vibe:_ Obrigatório para projetos com múltiplos ambientes, múltiplos devs ou em setores regulados (financeiro, saúde). A aplicação busca os segredos em runtime sem que nenhum humano precise vê-los.

### Como guardamos a senha do Joãozinho?

Salvando senhas no banco.

- Lemos o que ele digitou (Texto puro): Salva "senha123". _Vibe:_ Ilegal e imoral. O dono do app nunca pode saber a senha do cliente.
- Misturador (Hash Criptografado): Salva um texto maluco tipo $2y$10$abcde.... _Vibe:_ Seguro e obrigatório por lei (LGPD).

### Controle de Acesso (Quem pode apagar um post?)

Gerenciando permissões.

- Perguntando o cargo (Roles): "Se for Admin, apaga." _Vibe:_ Simples, mas engessa rápido.
- Checagem de Políticas (Policies): O código cruza o Usuário atual com o Recurso que ele quer alterar. _Vibe:_ Seguro e granular (ex: "Só apaga se for dono do post ou admin").

### Defesa contra formulários fantasmas (CSRF/CORS)

Sites falsos tentando mandar coisas pro nosso servidor.

- Aberto pro mundo: Qualquer site pode mandar dados pra gente. _Vibe:_ Vulnerável a ataques simples.
- Só entra convidado: Configuramos pra só aceitar cliques e dados que vieram das nossas próprias telas. _Vibe:_ Proteção padrão dos frameworks bons.

### O cara que muda o ID da URL (IDOR)

O usuário acessa /perfil/10/editar e resolve testar /perfil/11/editar.

- Confia na URL: Deixa editar o 11. _Vibe:_ Ele edita o perfil do vizinho.
- Confere a identidade: Antes de abrir, o código checa "Esse ID pertence ao cara que está logado?". _Vibe:_ Privacidade garantida.

### O usuário entra com Google, Apple ou cria senha própria?

Estratégia de autenticação.

- Senha própria (Credenciais): O usuário cria login e senha no nosso sistema. _Vibe:_ Mais controle, mas você é responsável por guardar a senha com segurança.
- Login Social (OAuth): O usuário clica em "Entrar com Google". _Vibe:_ Sem senha pra gerenciar, menor atrito no cadastro, mas depende de terceiros.

### O usuário fica logado para sempre?

Expiração de sessão.

- Sessão eterna: O usuário nunca é deslogado. _Vibe:_ Conveniente, mas se alguém roubar o token, tem acesso para sempre.
- Expiração automática: A sessão expira após X minutos de inatividade ou o token tem prazo de validade. _Vibe:_ Mais seguro, especialmente em apps financeiros e corporativos.

### O app vai exigir uma segunda confirmação de identidade (2FA)?

Autenticação de dois fatores.

- Não, só usuário e senha: _Vibe:_ Se a senha vazar (e vai vazar), o atacante entra direto. Aceitável só para apps com dados de baixo risco.
- Sim, segunda camada (2FA): Login exige senha + código extra (SMS, e-mail ou app autenticador como Google Authenticator). _Vibe:_ Obrigatório para apps financeiros, corporativos e qualquer coisa com dados sensíveis. Reduz em mais de 99% as invasões por senha roubada.

### O que acontece se alguém tentar adivinhar a senha errada mil vezes?

Proteção contra força bruta e credential stuffing.

- Nenhuma proteção: Tentativas são aceitas sem limite. _Vibe:_ Um script automatizado testa milhares de combinações em segundos. Acontece todo dia em apps sem proteção.
- Rate limiting no login + bloqueio temporário: Após N tentativas falhas no mesmo IP ou conta, o sistema bloqueia por X minutos e pode exigir CAPTCHA. _Vibe:_ Proteção básica e obrigatória. Ferramentas como fail2ban, middlewares de rate limiting (express-rate-limit, Rack::Attack) ou WAFs resolvem isso com poucas linhas de configuração.

### Como o usuário recupera a conta se esquecer a senha?

Fluxo de recuperação de senha.

- Não pensamos nisso ainda: _Vibe:_ O suporte vai receber chamados para sempre. E fluxos improvisados são vetores clássicos de ataque.
- Link com expiração por e-mail (padrão seguro): O sistema gera um token único, envia por e-mail e expira em 15–60 minutos. _Vibe:_ Simples, seguro e esperado pelo usuário. O token deve ser de uso único e invalidado após uso.

---

## 🎨 5. A Cara do App (Comunicação Front e Back)

_Como a tela "conversa" com o motor nos bastidores._

A linha que separa o back-end (o motor) do front-end (a tela) é onde a maioria dos mal-entendidos acontece em times de desenvolvimento. Esta seção alinha como os dois lados "falam" entre si: o formato dos dados trocados, como os erros são comunicados, em qual plataforma o app vai rodar e quais ferramentas tornam essa comunicação previsível e documentada. Um contrato claro aqui evita horas de debugging e discussão entre times. Sem ele, cada integração vira uma surpresa.

### O usuário vai acessar pelo celular, computador ou pelos dois?

Escolha da plataforma.

- Só Web (responsiva): Um site que se adapta a qualquer tela. _Vibe:_ Um código só para tudo. Mais rápido e barato de desenvolver e manter.
- App Nativo (React Native / Flutter / Swift / Kotlin): Um aplicativo nas lojas (App Store / Google Play). _Vibe:_ Acesso a câmera, notificações push e GPS nativo. Necessário se a experiência mobile for o coração do produto.

### O App é uma página que se monta no navegador ou no servidor?

A estratégia de renderização das telas.

- App de Página Única (SPA): O servidor manda o código JavaScript, e o navegador do usuário monta a tela. _Vibe:_ Experiência fluida como um aplicativo (sem recarregar a página), mas a primeira abertura pode ser lenta e mecanismos de busca têm mais dificuldade de indexar o conteúdo.
- Renderização no Servidor (SSR): O servidor já manda a tela pronta pra o navegador mostrar. _Vibe:_ Carregamento inicial mais rápido e ranqueamento no Google muito melhor. Ideal para e-commerces, blogs e sites com tráfego orgânico.

### O que o back-end devolve quando tudo dá certo?

O formato do JSON da API.

- O que der na telha: Uma rota devolve texto, a outra devolve lista. _Vibe:_ O time do Front-end vai te odiar.
- Sempre a mesma caixa (Envelope Padrão): Tudo vem dentro de uma estrutura previsível, tipo { data: {}, erros: null }. _Vibe:_ O Front-end cria um código só para ler tudo.

### O que o back-end devolve quando dá ERRO (ex: CPF inválido)?

Como o Front avisa o usuário do que ele errou.

- "Deu erro" (Genérico): O Front que se vire pra adivinhar. _Vibe:_ O usuário fica perdido.
- Mapa de Erros: Devolve uma lista: "cpf": "Formato incorreto". _Vibe:_ O Front consegue pintar de vermelho o campo exato que o usuário errou.

### O Front-end pede uma busca com 3 filtros. Como ele manda isso?

Filtrando listas.

- Dentro do Corpo (Body POST): _Vibe:_ Funciona, mas você não consegue copiar o link e mandar pro amigo ver a mesma busca.
- Na URL (Query GET): ?cor=azul&tamanho=M. _Vibe:_ O link fica compartilhável.

### Se precisarmos mudar muito o App no futuro? (Versionamento)

Como não quebrar os celulares velhos que ainda não atualizaram o app.

- Muda e reza: O app antigo para de funcionar. _Vibe:_ O usuário desinstala o app.
- Versões (V1, V2): O servidor mantém a rota /v1/perfil pros celulares velhos e cria a /v2/perfil pros novos. _Vibe:_ Transição suave.

### Como o Back avisa o Front das rotas que existem?

A documentação da API.

- Chama no WhatsApp / Notion: O dev do back manda "oh, a rota é /pagar". _Vibe:_ A documentação sempre fica defasada.
- Documentação Automática (Swagger/Postman): O código gera uma página web com os botões pro Front testar as rotas. _Vibe:_ Produtividade monstra.

### De onde vêm os botões, modais e tabelas do app?

Biblioteca de componentes visuais.

- Fazemos tudo na mão (Custom): Criamos cada componente do zero com CSS puro. _Vibe:_ Total liberdade visual, mas leva muito mais tempo.
- Usamos uma biblioteca pronta (ex: Shadcn, Material UI, Tailwind UI): Importamos componentes já prontos e estilizados. _Vibe:_ Velocidade absurda no começo, mas o app pode parecer igual ao de todo mundo.

### Qual o estilo de comunicação entre front-end e back-end?

Estilo de API.

- REST: Cada recurso tem uma URL própria (GET /usuarios, POST /pedidos). _Vibe:_ O padrão da indústria. Simples de entender, fácil de documentar com Swagger e compatível com qualquer cliente.
- GraphQL: O front-end pede exatamente os campos que precisa em uma única requisição. _Vibe:_ Elimina over-fetching e under-fetching. Ideal quando múltiplos clientes (web, mobile) consomem dados diferentes de forma diferente. Curva de aprendizado maior.
- tRPC: Chamadas de função type-safe diretas entre front e back, sem definir contrato de API. _Vibe:_ Produtividade máxima em stacks TypeScript full-stack (ex: Next.js). Funciona só se ambos os lados usam TypeScript.

### Como o front-end gerencia o estado global da aplicação?

Gerenciamento de estado no cliente.

- Estado local em cada componente (useState): Cada tela cuida dos seus próprios dados. _Vibe:_ Simples para apps pequenos, mas passa dados "de mão em mão" entre componentes vira pesadelo com o crescimento.
- Estado global centralizado (Zustand / Redux / Signals / Context): Um "armazém" central que qualquer parte do app consulta e atualiza. _Vibe:_ Essencial quando múltiplas telas precisam da mesma informação (ex: usuário logado, carrinho de compras). Zustand e Signals são mais leves que Redux.

---

## 🚨 6. Quando a Casa Cai (Erros, Logs e Alertas)

_Vai dar erro. A questão é como a gente lida com ele._

Todo sistema vai falhar em algum momento. A questão não é "se" — é "quando" e o que acontece depois. Esta seção define a diferença entre saber que algo quebrou antes do cliente reclamar (pro-ativo) ou descobrir pelo Twitter (reativo). Logs bem configurados transformam um bug misterioso em um diagnóstico de 5 minutos. Alertas automáticos permitem agir antes do impacto virar crise. Sem elas, o time fica no escuro e o usuário paga o preço.

### A tela quebrou (Erro 500). O que o usuário enxerga?

A mensagem de erro fatal.

- Página bugada com códigos. _Vibe:_ O app parece amador e vaza informações da infraestrutura.
- Um simpático "Ops" + Trace ID. O usuário vê uma mensagem bonita e um código de rastreio escondidinho. _Vibe:_ Você usa o código pra achar o erro exato no sistema depois.

### Onde a gente anota (Log) os erros do sistema?

Como a gente investiga os bugs.

- Num arquivo .txt gigante no servidor. _Vibe:_ Passar horas lendo texto pra achar o erro.
- Num Painel Visual (ex: Sentry/Datadog): O erro cai num painel na web dizendo "Estourou erro na linha 42". _Vibe:_ Você resolve o problema antes do cliente reclamar.

### Como a gente sabe que o site caiu (Ficou Fora do Ar)?

Monitoramento de saúde.

- Pelo Twitter (O Cliente reclama): _Vibe:_ Vergonha.
- Robô Vigia (Healthcheck): Um serviço bate no site a cada minuto e apita no Slack/Discord do time se falhar. _Vibe:_ Você age na hora.

### Conseguimos rastrear o tempo de resposta de cada rota e o que aconteceu em cada requisição?

Observabilidade e APM (Application Performance Monitoring).

- Só logs e erros: Sabemos que algo quebrou, mas não sabemos qual rota está lenta, onde o tempo foi gasto ou o caminho completo de uma requisição. _Vibe:_ Suficiente para apps simples, mas debugar problemas de performance em produção fica difícil.
- APM / Tracing distribuído (Datadog, New Relic, Sentry Performance, OpenTelemetry): Cada requisição gera um trace com tempo por camada (banco, cache, API externa). Dashboards mostram as rotas mais lentas e onde o tempo é perdido. _Vibe:_ A diferença entre adivinhar e saber. Obrigatório quando o app tem múltiplos serviços ou SLA de tempo de resposta.

### O que a gente NÃO PODE botar nos Logs de erro?

Proteção de dados no meio do caos.

- Salva tudo pra ajudar a debugar. _Vibe:_ Salva o número do Cartão de Crédito e Senha. Ilegal e perigoso.
- Filtro de Segredos (Sanitize): O sistema transforma campos sensíveis em ******* antes de salvar o log. _Vibe:_ Paz com a lei de proteção de dados.

---

## 🤝 7. Trabalho em Equipe (Git e Deploy)

_Como programar junto sem um apagar o trabalho do outro._

O código é o produto — e o produto precisa ser entregue com segurança, rastreabilidade e sem depender de rituais manuais cheios de passos. Esta seção define como o time colabora sem pisar no trabalho um do outro, como as mudanças são revisadas antes de irem ao ar e como o processo de "publicar nova versão" passa de momento de terror para rotina automática e confiável. Times que dominam esse fluxo entregam mais rápido e dormem melhor.

### Como a gente junta o código da galera?

O fluxo do Git.

- Todo mundo joga na main: _Vibe:_ Um dia alguém salva algo quebrado e derruba o site de todo mundo.
- Cria um ramo e pede permissão (Pull Request): Pede pro amigo revisar antes de juntar na principal. _Vibe:_ Filtro de qualidade anti-bug.

### Mensagens de Salvar o código (Commits)

Como descrevemos as mudanças.

- "Arrumei", "Agora vai", "asdfasdf". _Vibe:_ Daqui a 6 meses, ninguém sabe o que o código fez.
- Padrão claro: fix: resolve crash no pagamento ou feat: adiciona botão de compartilhar. _Vibe:_ O histórico vira um livro fácil de ler.

### Como o código sai do PC e vai pro Servidor (Deploy)?

Colocando no ar.

- Arrasta a pasta via FTP / ZIP: _Vibe:_ Altíssimo risco de erro humano e tela branca no servidor.
- Robô de Entrega (CI/CD): Quando junta o código na main, o GitHub avisa o servidor, que puxa e atualiza sozinho em segundos. _Vibe:_ Deploy na sexta-feira sem medo.

### O que a gente faz se o deploy quebrar produção?

Estratégia de rollback.

- Sem plano definido: Tenta reverter na mão, correndo, debaixo de pressão. _Vibe:_ Minutos de downtime viram horas. O pânico faz o time cometer mais erros durante o incidente.
- Rollback documentado e testado: O pipeline tem um comando ou botão de rollback para a versão anterior. O time sabe exatamente o que fazer e ensaia o processo antes de precisar. _Vibe:_ Confiança para dar deploy com mais frequência. Se der errado, a volta é rápida e sem drama.

### Quando uma tarefa (Card) está "Pronta" (Definition of Done)?

A hora de fechar a tarefa.

- "Na minha máquina funcionou." _Vibe:_ Mas na de produção quebra.
- Tá na máquina principal, sem erros, testado e revisado. _Vibe:_ A equipe só comemora quando o usuário final já pode usar.

### Os ambientes de teste e produção são separados?

Isolamento de ambientes.

- Todo mundo testa no servidor real: _Vibe:_ Um teste errado derruba o app dos clientes.
- Ambientes separados (dev / staging / produção): O código passa por etapas antes de chegar no usuário final. _Vibe:_ Erros ficam contidos no ambiente de teste, não no bolso do cliente.

### Se o banco explodir hoje, quando voltamos ao ar?

Estratégia de backup.

- Não temos backup automatizado: _Vibe:_ Reza pra nunca precisar.
- Backup automático com frequência e retenção definidas: Definimos de quanto em quanto tempo (ex: a cada 6h) e por quanto tempo guardamos (ex: 30 dias). _Vibe:_ Tranquilidade. A pergunta não é "se" vai dar problema, é "quando".

---

## 🧪 8. Testes e Garantia da Vibe (Qualidade)

_A gente não é a NASA, mas ninguém quer ficar apagando incêndio 3 da manhã._

Testes automatizados são a rede de segurança que permite o time alterar o código sem medo. Sem ela, cada nova feature é uma roleta-russa: funcionou por acaso ou porque estava correto? Esta seção não prega cobertura de 100% — prega testes estratégicos: cobrir os fluxos críticos do negócio (login, pagamento, cadastro) de forma que qualquer quebra seja detectada antes de chegar ao usuário. A qualidade de código também passa por revisão humana, formatação consistente e uso de ambientes de teste isolados.

### Qual a nossa regra de Testes Automatizados?

Robôs testando o código.

- Deus me livre, só codar: _Vibe:_ Daqui a 6 meses, você tem medo de alterar qualquer coisa e quebrar o app todo.
- Testar o Caminho Feliz (API Tests): Um robô garante que as coisas principais (Login, Cadastro, Compra) funcionam antes de qualquer deploy. _Vibe:_ O sweet spot entre velocidade e segurança.

### Code Review (Revisão pelo Amigo)

O olhar de fora.

- Cada um cuida do seu. _Vibe:_ Você fica viciado nos seus próprios erros.
- Sempre alguém aprova: Mesmo que seja rápido, um dev olha o código do outro antes de ir pro ar. _Vibe:_ Disseminação de conhecimento.

### Formatador de Código (Linting)

O visual do código.

- Cada um usa seus espaços e aspas. _Vibe:_ O código parece uma colcha de retalhos.
- Robô chato, mas justo (Prettier/ESLint/Pint): Ao salvar o arquivo, o editor já formata e arruma pro padrão da equipe. _Vibe:_ O código inteiro parece que foi escrito pela mesma pessoa.

### Testar pagamentos e integrações perigosas

Simulando ações do mundo real.

- Com meu cartão de verdade e depois estorno. _Vibe:_ Sujeito a falência acidental.
- Chaves de Mentirinha (Sandbox): O servidor local usa as credenciais de teste (cartões falsos). _Vibe:_ Seguro e permite errar à vontade.

### O app precisa funcionar para pessoas com deficiência?

Acessibilidade (a11y).

- Não é prioridade agora: _Vibe:_ Exclui uma fatia enorme de usuários. Obrigatório por lei para apps governamentais e corporativos no Brasil.
- Seguimos as diretrizes de acessibilidade (WCAG): Contraste de cores, navegação por teclado, compatibilidade com leitores de tela. _Vibe:_ Produto mais inclusivo e em conformidade com a lei.

### Como ativamos e desativamos features sem precisar de um novo deploy?

Feature Flags (chaves de lançamento).

- Deploy liga tudo direto: Cada nova funcionalidade vai ao ar para 100% dos usuários no mesmo instante do deploy. _Vibe:_ Sem volta se der problema. Um bug crítico afeta todo mundo de uma vez.
- Chaves de feature (Feature Flags): A funcionalidade existe no código, mas fica "apagada". Ligamos para 1% dos usuários primeiro, observamos, depois aumentamos gradualmente. _Vibe:_ Permite lançamentos progressivos, testes A/B e rollback instantâneo sem novo deploy. Ferramentas: LaunchDarkly, Unleash ou simples flags no banco de dados.

---

## ☁️ 9. Nuvem, Arquivos e Infra (Infraestrutura)

_Como o nosso código lida com o mundo físico e o peso dos arquivos._

O código mais bonito do mundo não serve de nada se o servidor não aguenta, o ambiente do dev é diferente do de produção e os arquivos do usuário somem quando o HD lota. Esta seção cobre as decisões de "onde as coisas rodam" — do computador do desenvolvedor até a nuvem que atende milhares de usuários simultâneos — e como garantir que esses ambientes sejam replicáveis, escaláveis e confiáveis. Infra mal planejada é a causa silenciosa de muitos bugs que "só acontecem em produção".

### Onde a gente salva as fotos e PDFs dos usuários?

Armazenamento de uploads.

- Na pasta do código (Local): Salva no próprio servidor. _Vibe:_ O HD do servidor lota rápido e impede de colocar um segundo servidor para ajudar no tráfego.
- Num HD na Nuvem (ex: AWS S3, Cloudflare R2): O servidor joga o arquivo direto pra nuvem. _Vibe:_ Escalabilidade infinita.

### Como o projeto roda no computador de um desenvolvedor novo?

O Onboarding técnico.

- "Instala o banco, a linguagem, ajeita as versões..." _Vibe:_ O dev passa 2 dias configurando o PC e dando erro.
- Caixote Mágico (Docker / Containers): Roda UM comando e o projeto inteiro sobe sozinho, idêntico para todo mundo. _Vibe:_ Produtividade insana.

### A grande divisão: Como organizamos o projeto todo?

Monolito vs Microsserviços.

- Monolito Majestoso: Todo o código num projeto só. _Vibe:_ O melhor jeito de começar 99% dos projetos. Simples de dar deploy e testar.
- Microsserviços: Um projeto pra pagamentos, outro pra usuários. _Vibe:_ Só faz sentido se você tem times grandes. Senão, é engenharia exagerada que mata a velocidade.

### O app precisa funcionar sem internet?

Estrategia Offline First.

- Não, precisa de conexão: A tela quebra ou trava se o usuário ficar sem sinal. _Vibe:_ Aceitável para a maioria dos apps de escritório.
- Sim, funciona offline (Offline First): Os dados ficam salvos no dispositivo e sincronizam quando a internet voltar. _Vibe:_ Essencial para apps de campo, delivery, saúde e regiões com sinal instável. Exige arquitetura bem diferente.

### Quem cuida de atualizar as bibliotecas e corrigir vulnerabilidades?

Gerenciamento de dependências e segurança.

- Atualizamos quando lembrar (ou nunca): _Vibe:_ Bibliotecas desatualizadas são a principal porta de entrada para ataques. A dívida técnica vai crescendo silenciosamente até virar uma CVE crítica no noticiário.
- Robô de atualizações automáticas (Dependabot / Renovate): Configuramos uma ferramenta que monitora as dependências e abre Pull Requests automáticos quando saem versões novas ou com correções de segurança. _Vibe:_ O time só precisa revisar e aprovar, sem precisar caçar o que está desatualizado.

### Como entregamos os arquivos estáticos (JS, CSS, imagens) aos usuários?

Distribuição de assets com CDN.

- Direto do servidor da aplicação: O mesmo servidor que processa as requisições também serve os arquivos estáticos. _Vibe:_ Simples de configurar, mas cada download de JS ou imagem consome CPU e banda do servidor principal. Usuários longe do servidor sentem latência maior.
- CDN (Cloudflare, CloudFront, Fastly, Vercel Edge): Os arquivos estáticos são copiados para servidores espalhados pelo mundo. O usuário baixa do servidor mais próximo dele. _Vibe:_ Páginas carregam mais rápido em qualquer região, o servidor principal fica livre para processar lógica de negócio e há proteção contra DDoS inclusa na maioria das CDNs.

---

## ⏱️ 10. Tempo Real e Rotinas (Cron & Real-time)

_O app precisa ser vivo e inteligente, mesmo sem cliques._

Uma boa parte da inteligência de um app moderno não vem de cliques do usuário — vem de coisas que acontecem sozinhas: cobranças automáticas na data certa, notificações instantâneas de novas mensagens, status de pedido atualizado com o pagamento confirmado. Esta seção cobre como o sistema "pensa por conta própria": agendamento de tarefas, comunicação bidirecional em tempo real e integração com sistemas externos que avisam o nosso app quando algo importante acontece — sem o usuário precisar recarregar a página.

### Atualizações em tempo real (ex: Nova mensagem no chat)

Como a tela sabe que algo mudou.

- F5 Automático (Polling): O celular fica perguntando pro servidor de 5 em 5 segundos. _Vibe:_ Gasta muito recurso do servidor à toa.
- Tubo Aberto (WebSockets / SSE): O servidor avisa o celular do usuário NA HORA que a mensagem chega. _Vibe:_ Experiência moderna e econômica.

### Avisos de Terceiros (ex: MercadoPago avisando do pagamento)

Lidando com integrações externas ativas.

- Esperar o cliente voltar na tela: A gente só checa quando o usuário clica. _Vibe:_ Se ele não voltar, o pedido não anda.
- Campainha Automática (Webhooks): Criamos uma rota secreta. Quando pagam, o MercadoPago "toca a campainha" e nosso sistema libera na hora. _Vibe:_ Automação de respeito.

### Tarefas que rodam sozinhas (ex: Cobrar mensalidade)

Automação baseada em tempo.

- Alguém clica num botão no painel: _Vibe:_ Erro humano garantido. Alguém vai esquecer.
- Robô Agendado (Cron Jobs): O servidor roda uma função automaticamente na hora marcada. _Vibe:_ O sistema trabalha enquanto você dorme.

---

## 📊 11. Dados, Métricas e Conhecimento (Cultura)

_Saber o que tá acontecendo e passar o bastão._

Um produto que não mede não melhora. Esta seção vai além dos logs técnicos: trata de como o time aprende com o comportamento real dos usuários, como o conhecimento do projeto é preservado mesmo quando pessoas saem, como o código envelhece sem virar um fardo intocável e como a privacidade dos dados é respeitada por lei. São escolhas de cultura e processo — não de tecnologia — que separam times amadores de times que constroem produtos duradouros.

### Histórico de ações (Ex: Cliques, visualizações de tela)

Analisando o uso do app.

- Uma tabela gigante no nosso banco: _Vibe:_ Seu banco principal vai ficar lento guardando lixo de métrica.
- Ferramentas Próprias (Analytics / Mixpanel): Manda os dados direto pra uma ferramenta externa focada nisso. _Vibe:_ Nosso banco fica só com os dados que importam.

### Defesa contra "Robôs e Raspadores" (Scraping)

Protegendo as APIs públicas.

- Deixa as portas abertas: Qualquer script copia nossa lista inteira. _Vibe:_ Você gasta servidor pra entregar dados pra concorrência.
- Bloqueador de Robôs: Se o IP pedir mais de 100 coisas por minuto, a gente bloqueia. _Vibe:_ Seu app protegido contra ataques.

### A Regra do Escoteiro (Refatoração)

Lidando com código antigo e feio.

- "Tá feio, vou apagar tudo e reescrever." _Vibe:_ A empresa perde dinheiro porque o time para de entregar coisas novas por meses.
- Melhoria Contínua: Deixa o código antigo quieto se ele funciona. Se precisar mexer nele, melhora um pouquinho. _Vibe:_ Pragmatismo e entrega de valor constante.

### O "Fator Ônibus" (Se alguém for atropelado, o projeto morre?)

Documentação interna da equipe.

- Conhecimento Tribal: Todas as regras estão na cabeça de uma pessoa só. _Vibe:_ Risco altíssimo de negócio.
- README de Respeito: A página inicial do repositório ensina como instalar, onde ficam as senhas e como rodar o projeto. _Vibe:_ O projeto pertence à equipe.

### O app coleta dados de rastreio ou analytics?

Conformidade com LGPD/GDPR.

- Coleta sem avisar: _Vibe:_ Ilegal. A LGPD prevê multas de até 2% do faturamento.
- Banner de consentimento + Política de Privacidade: O usuário sabe o que está sendo coletado e pode recusar. _Vibe:_ Em conformidade com a lei e com a confiança do usuário.

---

## 🌍 12. Internacionalização e Localização

_Construir pra falar a língua do usuário, onde quer que ele esteja._

Ignorar internacionalização no início e tentar adicionar depois é uma das refatorações mais dolorosas que existem. Textos espalhados em centenas de arquivos, datas salvas no fuso errado, valores exibidos no formato equivocado — cada um desses problemas vira uma dívida técnica cara e silenciosa. Mesmo que o app seja só para o Brasil hoje, definir boas práticas de localização desde o início (UTC no banco, textos em arquivos separados, formato de moeda centralizado) tem custo quase zero agora e evita semanas de trabalho urgente no futuro.

### O app vai suportar múltiplos idiomas?

Estrategia de internacionalização (i18n).

- Só português, textos direto no código: _Vibe:_ Rápido agora, mas adicionar inglês depois significa caçar texto hardcoded em centenas de arquivos.
- Preparado para múltiplos idiomas (i18n): Os textos ficam em arquivos de tradução separados. _Vibe:_ Custo baixo no início se feito desde o zero. Custo altíssimo se deixado para depois.

### Como exibimos datas, moedas e fuso horário?

Localização de formatos.

- No formato que der: Cada tela mostra como quiser ("01/03/2026", "Mar 1", "1 de março"). _Vibe:_ Confusão para o usuário e bugs difíceis de rastrear em sistemas com usuários de países diferentes.
- Padrão definido (i18n / UTC no banco): Datas salvas sempre em UTC no banco, convertidas para o fuso do usuário na tela. Moeda e formato de número seguem o locale. _Vibe:_ Consistência e ausência de bugs de "a data chegou errada".

---

## 📣 13. Comunicação com o Usuário (Notificações e E-mail)

_O app precisa falar com o usuário mesmo quando ele não está com a tela aberta._

Nenhum app moderno é uma ilha. Confirmações de compra, alertas de segurança, lembretes e novidades chegam ao usuário por e-mail, push e SMS. Essas comunicações parecem simples, mas envolvem decisões técnicas sérias: qual provedor usar, como garantir a entrega, como evitar que os e-mails caiam no spam e como separar mensagens operacionais ("sua senha foi alterada") de mensagens de marketing ("confira as novidades"). Misturar esses dois mundos no mesmo canal é o caminho certo para perder reputação de entrega e ter e-mails bloqueados.

### Como o app envia e-mails transacionais (confirmações, alertas, senhas)?

Provedor de e-mail transacional.

- Servidor SMTP próprio ou do host: _Vibe:_ Barato, mas com taxa de entrega ruim, sem painel de monitoramento e IP facilmente bloqueado por provedores como Gmail e Outlook.
- Serviço dedicado (Resend, SendGrid, Amazon SES, Mailgun): O e-mail sai por infraestrutura com reputação estabelecida, painel de métricas (taxa de entrega, abertura, rejeição) e suporte a DNS de autenticação (SPF, DKIM, DMARC). _Vibe:_ E-mails que chegam de verdade. Essencial para qualquer app com cadastro ou pagamento.

### E-mails de marketing e e-mails transacionais: mesmo servidor?

Separação de canais de e-mail.

- Tudo pelo mesmo lugar: _Vibe:_ Se uma campanha de marketing for marcada como spam, arrasta junto os e-mails de recuperação de senha e confirmação de compra. O usuário para de receber tudo.
- Domínios e provedores separados: E-mails transacionais saem de um domínio/provedor exclusivo e marketing de outro. _Vibe:_ A reputação de entrega de um não contamina o outro.

### Como avisamos o usuário quando ele não está com o app aberto?

Notificações push e SMS.

- Não avisamos, ele volta quando quiser: _Vibe:_ Usuários esquecem do app e o engajamento despenca.
- Notificações push (Firebase FCM / APNs): O celular recebe alertas mesmo com o app fechado. _Vibe:_ Canal de alto impacto para re-engajamento e avisos urgentes. Requer permissão explícita do usuário.
- SMS / WhatsApp (Twilio, Zenvia, Z-API): Para alertas críticos (código 2FA, fraude detectada, entrega confirmada). _Vibe:_ Taxa de leitura de 98%, mas tem custo por mensagem. Usar com moderação e só para comunicações de alto valor.

---

## 💳 14. Pagamentos e Recorrência

_Dinheiro é a parte mais sensível do sistema. Uma falha aqui é prejuízo real — para o usuário e para o negócio._

A maioria dos tutoriais mostra como integrar um botão de pagamento. Nenhum mostra o que fazer quando o cartão é recusado na terceira tentativa de renovação, quando o usuário pede reembolso depois de 45 dias ou quando há uma disputa de chargeback. Esta seção cobre todo o ciclo de vida financeiro: como o dinheiro entra, como a recorrência é gerenciada, o que acontece nas falhas e como o time testa tudo isso sem gastar dinheiro de verdade.

### Qual gateway de pagamento vamos usar?

Escolha do processador de pagamentos.

- Gateway nacional (MercadoPago, Pagar.me, PagSeguro): Amplamente adotados no Brasil, suportam Pix, boleto e cartão. _Vibe:_ Menor fricção para o público brasileiro. Documentação em português e suporte local. Taxas e aprovação de cartão variam entre provedores.
- Gateway internacional (Stripe): Referência mundial em experiência de desenvolvedores, SDK excelente e suporte robusto a assinaturas. _Vibe:_ Melhor escolha para produtos com clientes internacionais ou que exigem fluxos financeiros complexos. Liquidação em dólar pode ser complicador para empresas brasileiras.

### O app tem cobrança recorrente (assinatura)?

Modelo de faturamento.

- Cobrança avulsa (one-time): O usuário paga uma vez por produto ou serviço. _Vibe:_ Mais simples de implementar. Sem necessidade de gerenciar ciclos de renovação.
- Assinatura recorrente (subscription): O sistema cobra automaticamente todo mês/ano. _Vibe:_ Receita previsível para o negócio, mas exige gerenciar renovações, falhas de cobrança, upgrades, downgrades e cancelamentos. Usar o módulo de assinaturas do gateway economiza meses de desenvolvimento.

### O que acontece quando a cobrança falha (cartão expirado, sem limite)?

Estrategia de dunning (recuperação de inadimplência).

- Cancela o acesso na hora: _Vibe:_ O usuário perde acesso de surpresa. Gera frustração mesmo quando é falha temporária do banco.
- Período de graça + tentativas automáticas: O sistema tenta cobrar novamente em 3, 5 e 7 dias, envia e-mails de aviso e só suspende depois do período de graça. _Vibe:_ Reduz significativamente o churn involuntário. A maioria dos gateways oferece isso nativamente.

### Como testamos o fluxo de pagamento em desenvolvimento?

Ambiente de testes financeiros.

- Testamos com cartão real e estornamos depois: _Vibe:_ Arriscado, caro e impossível de automatizar em CI/CD.
- Sandbox do gateway + cartões de teste: Cada gateway fornece credenciais de teste e números de cartão que simulam aprovação, recusa, erro de rede e 3DS. _Vibe:_ Seguro, gratuito e testável automaticamente. Obrigatório antes de qualquer deploy em produção.

### Como tratamos pedidos de reembolso e chargebacks?

Política de estorno e disputes.

- Gerenciamos no painel do gateway manualmente: _Vibe:_ Funciona para volumes baixos, mas escala mal e depende de ação humana rápida para evitar perder disputes.
- Fluxo definido (prazo, critérios e automação): Definimos política clara de reembolso, automatizamos o que for possível e temos processo documentado para responder chargebacks dentro do prazo da operadora. _Vibe:_ Protege o negócio de perdas desnecessárias e melhora a experiência do cliente.

---

## 🤖 15. Inteligência Artificial e LLMs

_IA não é mais diferencial — é infraestrutura. Mas infraestrutura mal planejada vira conta no cartão e dado vazado._

Em 2026, praticamente todo produto de software usa alguma forma de IA generativa — seja para sugestões, resumos, chatbots, classificação ou geração de conteúdo. O problema é que LLMs são diferentes de qualquer outra dependência de software: custam por token, podem alucinar, têm latência imprevisível, recebem dados do usuário e mudam de comportamento entre versões. Esta seção cobre as decisões que separam uma integração de IA bem-feita de uma que vai gerar incidentes, custos inesperados e problemas de privacidade.

### Qual modelo e provedor de IA vamos usar?

Escolha do LLM e infraestrutura.

- API de provedor externo (OpenAI, Anthropic, Google Gemini): Integração rápida via SDK, sem infraestrutura própria. _Vibe:_ A forma mais rápida de começar. Custo por token pode surpreender em escala. Os dados enviados nos prompts saem do seu servidor.
- Modelo hospedado internamente (Ollama, vLLM, Hugging Face): O modelo roda na sua infraestrutura. _Vibe:_ Controle total sobre dados e custos fixos de GPU. Exige equipe com conhecimento em MLOps e investimento em hardware ou instâncias especializadas. Recomendado quando há dados altamente sensíveis ou volume muito alto.

### Onde ficam os prompts do sistema?

Gerenciamento de prompts.

- Hardcoded no código-fonte: O prompt está escrito direto no arquivo do código. _Vibe:_ Prático para começar, mas qualquer ajuste exige um novo deploy. Impossível de testar variações sem alterar o código.
- Prompts versionados e gerenciáveis (banco de dados, arquivo separado ou LangSmith/Promptfoo): Os prompts ficam fora do código, com histórico de versões e possibilidade de ajuste sem deploy. _Vibe:_ Permite iterar rapidamente, fazer testes A/B de prompts e auditar o que foi enviado ao modelo.

### Como controlamos os custos de tokens?

Governo de custos de IA.

- Sem controle: Qualquer requisição do usuário vai direto para o modelo. _Vibe:_ Um usuário mal-intencionado (ou um loop de bug) pode gerar uma conta de milhares de reais em horas.
- Limites e monitoramento: Rate limiting por usuário/plano, truncamento de contexto, alertas de custo e dashboards de consumo por feature. _Vibe:_ Custo previsível e proteção contra abusos. Ferramentas como LangSmith, Helicone ou os dashboards nativos dos provedores ajudam.

### O que pode e o que não pode ir no contexto enviado à IA?

Privacidade e segurança de dados no contexto.

- Mandamos tudo que for relevante: Inclui nome, CPF, histórico de compras, conversa completa. _Vibe:_ Risco alto de violação da LGPD, especialmente com provedores externos. Dados enviados podem ser usados para treinamento dependendo dos termos do contrato.
- Política de sanitização de contexto: Definimos quais dados podem ser incluídos no prompt. Dados pessoais identificáveis são anonimizados ou substituídos por tokens antes de enviar ao modelo. _Vibe:_ Conformidade com LGPD e redução de risco de vazamento. Exige revisão contratual com o provedor (opt-out de treinamento).

### O que o sistema faz quando a API da IA está lenta ou indisponível?

Resiliência da integração com IA.

- Retorna erro para o usuário: _Vibe:_ Se a feature depende 100% da IA e o provedor cair, o usuário encontra uma tela de erro.
- Fallback gracioso: A feature de IA é tratada como enhancement, não como bloqueio. Se o modelo não responder em X segundos, o sistema exibe uma mensagem amigável ou uma versão degradada da feature. _Vibe:_ O resto do app continua funcionando. Timeout, retry com backoff exponencial e circuit breaker são padrões essenciais aqui.
