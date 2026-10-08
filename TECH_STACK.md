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

- Stack padronizada (linguagem + framework + libs principais definidos): _Vibe:_ Todo mundo fala a mesma língua. A IA entende o projeto. Pull requests são mais previsíveis.

> 💡 Motivo: composer.json e package.json fixam a stack; CLAUDE.md (Laravel Boost) obriga todos os agentes/devs a seguir essas versões.

**Stack definida:**
- Linguagem: `PHP 8.3`
- Framework principal: `Laravel 12 (Jetstream 5 + Livewire 4)`
- Banco de dados: `PostgreSQL no Supabase Cloud, plano pago (produção); SQLite como padrão em dev, trocável via DB_CONNECTION`
- Principais bibliotecas: `Filament 5, Fortify, Sanctum 4, Pennant, archtechx/laravel-seo, Tailwind CSS 4, Vite 7, PHPUnit 11, Pint, OpenSpout 4 (leitura e escrita de .xlsx e .csv)`

### Onde escrevemos as regras pesadas do app?

Todo app tem regras de negócio (ex: calcular frete, validar se tem saldo).

- Na "gaveta" de Serviços (Service Layer): Criamos arquivos separados só pra regra de negócio (ex: PagamentoService). _Vibe:_ Fica lindo de ler, qualquer novato entende.

> 💡 Motivo: O projeto já isola regras em classes de Action (app/Actions/Fortify e app/Actions/Jetstream); rotas e componentes só delegam.

### Como agrupamos nossos arquivos e pastas?

A estrutura de pastas do projeto.

- Por Tipo Técnico: Pasta Controllers, pasta Models, pasta Views. _Vibe:_ Padrão da maioria dos frameworks, fácil de começar.

> 💡 Motivo: Estrutura padrão do Laravel 12 (app/Models, app/Policies, app/Actions, resources/views); CLAUDE.md proíbe criar novas pastas-base sem aprovação.

### Textos e Status "Mágicos" (ex: "PAGO", "PENDENTE")

Como o código sabe se um pedido está pago?

- Usamos um "Dicionário" (Enums/Constantes): A gente cria um arquivo que diz STATUS*PAGO = "PAGO" e usa isso. _Vibe:* Previne erros bobos de digitação.

> 💡 Motivo: CLAUDE.md define a convenção de Enums PHP com chaves em TitleCase. Ainda não há nenhum Enum em app/, então é regra para o código novo.

### Como nomeamos as coisas no código?

Variáveis, funções e tabelas.

- Padronizado: Escolhemos o idioma (ex: Código em Inglês) e o formato (ex: variáveis sempre camelCase). _Vibe:_ Paz de espírito.

> 💡 Motivo: Código em inglês, PSR-12 via Laravel Pint, .editorconfig no repo e CLAUDE.md exigindo nomes descritivos.

### Como lidamos com ferramentas de terceiros (ex: Stripe, Correios)?

Como chamamos serviços externos.

- Criamos um "Tradutor" (Interfaces): A gente cria o nosso jeito de pedir frete, e por trás dos panos o tradutor fala com os Correios. _Vibe:_ Trocar de Correios pra Loggi vira tarefa de 10 minutos no futuro.

> 💡 Motivo: Cada unidade pode entregar autorizações e extratos em formatos/sistemas diferentes; importadores atrás de uma interface permitem trocar a fonte sem tocar na regra de conciliação.

### O sistema tem planos com limites de uso (ex: Free, Pro, Enterprise)?

Arquitetura de limites de uso.

- Sem limites: Todo usuário acessa tudo igual. _Vibe:_ Simples de começar, mas impossível de monetizar depois sem reescrever.

> 💡 Motivo: Sistema de uso interno do financeiro/controladoria; não há planos nem monetização. O acesso é controlado por papéis e por unidade, não por plano.

### O sistema precisa emitir Nota Fiscal ou boleto?

Integração fiscal e financeira.

- Não por agora: _Vibe:_ Ok para MVPs, mas lembrar que o modelo de dados de cliente (CPF/CNPJ, endereço fiscal) muda quando chegar a hora.

> 💡 Motivo: O sistema concilia autorizações com pagamentos já realizados; não emite NF nem boleto.

### Como organizamos os repositórios do projeto?

Monorepo vs Polyrepo — onde cada base de código mora.

- Tudo num repositório só (Monorepo): Front, back, libs e microserviços convivem no mesmo repositório com ferramentas como Turborepo, Nx ou pnpm workspaces. _Vibe:_ Mudanças atômicas entre projetos, compartilhamento fácil de código e uma única pipeline de CI. Exige configuração inicial mais cuidadosa.

> 💡 Motivo: Uma única aplicação Laravel (back + Blade/Livewire) em um repositório, sem ferramentas de workspace (Turborepo/Nx).

---

## 💾 2. O Baú do Tesouro (Banco de Dados)

_Seus dados são sagrados. Se o código quebrar, o dado tem que estar a salvo._

O banco de dados é a memória permanente do app. Tudo que o usuário faz, paga, escreve ou compra vive aqui. As decisões desta seção definem não só como os dados são salvos, mas como são protegidos, organizados e recuperados. Um banco mal projetado é como uma gaveta onde se joga tudo junto: funciona no início, mas depois de 6 meses ninguém acha nada e todo mundo tem medo de mexer. Normalização, índices, transações e migrations não são detalhes de DBA — são decisões de produto que afetam diretamente a velocidade e a confiabilidade do app.

### Qual o tipo de banco de dados principal do projeto?

A escolha entre banco relacional e não-relacional molda como os dados são estruturados, consultados e escalados.

- Banco Relacional (SQL): PostgreSQL, MySQL, SQLite. Dados em tabelas com linhas e colunas, relacionamentos com JOINs e garantias ACID. _Vibe:_ A escolha certa para 95% dos projetos. Dados estruturados, queries complexas e integridade garantida pelo banco. Postgres é a recomendação padrão para novos projetos.

> 💡 Motivo: Produção roda em PostgreSQL gerenciado pelo Supabase Cloud (plano pago, não auto-hospedado), usado apenas como banco (conexão pgsql do Laravel; autenticação continua no Fortify). O schema é todo relacional via migrations; dev segue com SQLite por padrão (DB_CONNECTION=sqlite).

**Banco escolhido:** `PostgreSQL via Supabase Cloud (plano pago, não auto-hospedado) em produção; SQLite em dev (padrão do .env.example)`

### Quando o usuário clica em "Excluir Conta"?

A gente apaga mesmo ou só finge que apagou?

- Esconde embaixo do tapete (Soft Delete): Ganha uma etiqueta "Deletado" mas continua lá. _Vibe:_ Salva sua pele se der processo ou o usuário pedir pra voltar.

> 💡 Motivo: Usuário nunca é excluído, apenas desativado pelo administrador, para que a trilha de auditoria continue apontando para quem fez cada ação. Ainda não implementado: hoje app/Actions/Jetstream/DeleteUser.php apaga de vez e a exclusão de conta do Jetstream precisa ser desligada.

### Quem é o segurança da porta dos dados?

Se alguém tentar salvar um "telefone" com letras, quem barra?

- O Banco também (Constraints): A gente ensina o banco a rejeitar lixo (ex: "Não aceite preço negativo"). _Vibe:_ Blindagem máxima.

> 💡 Motivo: As migrations já usam unique (users.email, team_user, team_invitations) e NOT NULL, além da validação no código (Fortify/Form Requests).

### Como guardamos dados muito variados (ex: Preferências de Tema)?

Dados que mudam de formato toda hora.

- Uma coluna pra cada coisa: Criamos a coluna tema*escuro, receber_email, etc. _Vibe:* Estruturado, mas toda hora tem que criar coluna nova no banco.

> 💡 Motivo: Valores, datas, parcelas e status precisam de constraints, índices e relatórios. JSON fica restrito a guardar a linha bruta importada, para auditoria.

### Listas muito grandes (ex: Feed com 10 mil posts)

Como devolver as postagens sem travar o app.

- Páginas (1, 2, 3): O banco conta tudo e divide (Offset). _Vibe:_ Clássico, mas fica lento quando passa de milhões de registros.

> 💡 Motivo: Tabelas do Filament paginam por offset e a conferência é sempre filtrada por unidade e período, com totais e salto para página específica.

### E se a gente precisar adicionar tabelas novas?

O processo de alterar a estrutura do banco.

- Receita de Bolo (Migrations): O código tem um arquivo ensinando a criar a tabela. _Vibe:_ Todo mundo roda o comando e fica com o banco idêntico.

> 💡 Motivo: Todo o schema está em database/migrations e CLAUDE.md manda criar via php artisan make:migration.

### Onde fazemos as contas (ex: "Soma o total de vendas do mês")?

Matemática e agregação de dados.

- No Banco (SQL): A gente manda a pergunta pro banco, e ele devolve só o resultado. _Vibe:_ O banco foi feito pra isso. Velocidade máxima.

> 💡 Motivo: Totais por unidade, período e fornecedor e somas de parcelas via agregações do Eloquent/query builder, nunca somando coleções em PHP.

### Precisamos saber quem mudou o quê e quando?

Auditoria de alterações.

- Guardamos o histórico (Audit Log): Cada alteração salva quem fez, o que era antes e o que virou. _Vibe:_ Essencial para sistemas financeiros, médicos ou qualquer app que precise prestar contas.

> 💡 Motivo: Requisito central do produto: rastreabilidade total e conformidade em auditorias. Toda conciliação, ajuste e resolução de divergência registra quem, quando, antes e depois.

### O sistema atende uma empresa ou várias ao mesmo tempo?

Arquitetura multi-tenancy.

- Uma empresa só (Single-tenant): O banco tem os dados de um cliente só. _Vibe:_ Mais simples de construir.

> 💡 Motivo: Uma única empresa. Unidade operacional é um cadastro comum, e o acesso é dado por permissão de usuário por unidade, porque o financeiro precisa cruzar dados de várias unidades. Implementado no Módulo 1: cada usuário tem um papel (`App\Enums\UserRole`: Administrador ou Operador) e todo usuário atua nas duas unidades. O Jetstream Teams continua ativo no código, sem uso para unidades ou papéis.

### As tabelas foram normalizadas ou ficamos com dados repetidos?

Normalização do banco (Formas Normais).

- Normalizado (1NF, 2NF, 3NF): Cada dado mora em um lugar só. O endereço fica na tabela de endereços, e as outras tabelas referenciam ele. _Vibe:_ Mais organizado e consistente. A desnormalização pode ser feita depois, intencionalmente, por performance.

> 💡 Motivo: Schema atual é normalizado: pivô team_user, team_invitations e teams separados, sem dados duplicados.

### Como as tabelas se "conversam" no banco?

Relacionamentos e integridade referencial.

- Foreign Keys de verdade (FK): O banco sabe o relacionamento e rejeita qualquer operação que quebre a consistência. _Vibe:_ O banco vira um guardião: impossível ter pedido sem usuário.

> 💡 Motivo: Pagamento, parcela e autorização não podem ficar órfãos; o banco garante o vínculo que sustenta a trilha de auditoria.

### Escrevemos SQL na mão ou usamos um "Tradutor" (ORM)?

Estratégia de acesso ao banco.

- ORM (ex: Prisma, Eloquent, Hibernate, SQLAlchemy): O código faz `Usuario.find(1)` e o ORM escreve o SQL. _Vibe:_ Produtividade muito maior no dia a dia. Atenção: queries geradas automaticamente podem ser ineficientes e precisam de supervisão.

> 💡 Motivo: Eloquent em todos os models; CLAUDE.md pede Model::query() e relacionamentos, evitando DB:: e SQL cru.

### Qual ORM, query builder e ferramenta de migration o time vai adotar?

Padronizar a ferramenta evita que metade do código use Prisma e a outra metade use SQL puro — o que transforma code review em adivinhação. A ferramenta de migration define como o schema do banco evolui junto com o código: sem ela, cada máquina do time pode estar rodando uma versão diferente do banco.

- Ferramenta padrão definida e migrations obrigatórias no repositório: _Vibe:_ Qualquer pessoa do time roda um comando e fica com o banco idêntico ao de todos. Schema versionado junto com o código.

> 💡 Motivo: Eloquent + migrations do Laravel versionadas em database/migrations.

**Ferramentas definidas:**
- ORM / Query builder: `Eloquent (query builder do Laravel para casos complexos)` _(ex: Prisma, Drizzle, TypeORM, Sequelize, Eloquent, SQLAlchemy, ActiveRecord)_
- Migration tool: `Laravel Migrations (embutido)` _(ex: embutido no ORM, Flyway, Liquibase, golang-migrate)_

### Quais colunas têm índice no banco?

Estratégia de indexação.

- Índices nas colunas certas: Qualquer coluna usada em WHERE, ORDER BY ou JOIN frequente ganha um índice. _Vibe:_ A diferença entre uma query de 3 segundos e 3 milissegundos. Precisa de análise com EXPLAIN para decidir o que indexar.

> 💡 Motivo: As migrations já indexam teams.user_id, sessions.user_id/last_activity, jobs.queue e os índices compostos de agent_conversations.

### E se duas operações no banco precisam acontecer juntas ou não acontecem?

Transações (Transactions).

- Dentro de uma Transaction: As duas operações são embrulhadas juntas. Se qualquer uma falhar, as duas voltam atrás (rollback). _Vibe:_ Tudo ou nada. Obrigatório para qualquer fluxo financeiro ou de estoque.

> 💡 Motivo: CreateNewUser e DeleteUser já usam DB::transaction para criar/remover usuário e time juntos.

---

## ⚡ 3. A Velocidade da Luz (Performance e Filas)

_O usuário tem paciência zero. Se demorar 3 segundos, ele fecha a aba._

Velocidade não é luxo — é requisito. Estudos mostram que cada segundo extra de carregamento reduz conversões em até 7%. Esta seção cobre as armadilhas clássicas que deixam apps lentos: tarefas pesadas bloqueando o usuário, consultas repetidas ao banco, imagens gigantes e servidores que não aguentam pico de acesso. A boa notícia: a maioria dos problemas de performance tem solução conhecida (cache, filas, compressão, eager loading). O segredo é decidir antes de precisar apagar incêndio.

### Tarefas que demoram (Gerar relatórios, mandar E-mail)

O usuário tem que esperar o envio terminar?

- Fila de Background: A tela dá "Sucesso!" na hora, e o e-mail vai para uma fila pra ser enviado escondido pelo servidor. _Vibe:_ Experiência de app premium.

> 💡 Motivo: QUEUE_CONNECTION=database com tabela jobs migrada, queue:listen no composer dev e regra do CLAUDE.md de usar ShouldQueue.

### O App bombou na Home! Como aliviar?

Muita gente acessando a mesma página ao mesmo tempo.

- Tira foto e guarda (Cache/Redis): O servidor monta a tela uma vez, guarda na memória, e entrega a cópia para os próximos mil acessos. _Vibe:_ O segredo dos apps gigantes.

> 💡 Motivo: Cache já configurado (CACHE_STORE=database, tabela cache migrada) e Redis pré-configurado no .env.example para quando precisar.

### Problema do N+1 (Consultas repetidas no banco)

Como buscar listas e seus relacionamentos (Posts e seus Autores).

- Trazer tudo na mala (Eager Loading): Faz uma consulta que já pede "Me dê os posts E seus autores de uma vez". _Vibe:_ Muito mais rápido e eficiente.

> 💡 Motivo: CLAUDE.md exige eager loading para evitar N+1.

### Tamanho das imagens dos usuários (Avatar/Fotos)

O usuário sobe uma foto de 10MB tirada no iPhone.

- Comprime na porta: O sistema reduz a qualidade e o tamanho da foto (ex: WebP) antes de salvar. _Vibe:_ Economiza dinheiro de servidor e carrega rápido na 3G.

> 💡 Motivo: Os uploads principais são planilhas e extratos, não fotos: na prática vira validação de tipo e tamanho máximo na entrada. Fotos de perfil, se ativadas, são redimensionadas antes de salvar.

### Se a API externa (Correios/ChatGPT) cair?

Lidando com falhas de terceiros.

- Plano B (Fallback): Mostra uma mensagem amigável "Serviço indisponível" mas deixa o cara navegar no resto do app. _Vibe:_ Resiliência.

> 💡 Motivo: Conciliação e consulta não podem parar porque um serviço externo (e-mail, fonte de extrato) caiu.

### O usuário afobado (Clica 10x no botão de Comprar)

Evitando requisições duplicadas.

- Trava o botão (Debounce / Rate Limit): O botão desabilita no primeiro clique ou o servidor ignora pedidos repetidos muito rápidos. _Vibe:_ Seguro contra afobados e hackers.

> 💡 Motivo: Importar o mesmo arquivo ou confirmar a mesma conciliação duas vezes duplica lançamentos financeiros. Botão desabilitado (wire:loading) + idempotência no servidor (hash do arquivo, chaves únicas).

### Como o usuário encontra conteúdo dentro do app?

Estrategia de busca.

- Filtros simples (SQL LIKE): Consulta direta no banco com filtros de texto. _Vibe:_ Funciona bem para começo, mas degrada com volume e não entende erros de digitação.

> 💡 Motivo: Filtros do Filament por unidade, período, fornecedor e status bastam. A tolerância a nomes de fornecedor digitados diferente é resolvida por normalização na regra de conciliação, não por motor de busca.

---

## 🔒 4. Os Seguranças da Balada (Segurança)

_Proteger os dados é mais barato do que pagar advogado depois do vazamento._

Segurança não é uma feature — é uma fundação. Vazamentos de dados destroem a reputação do produto, geram multas milionárias da LGPD e podem resultar em processos criminais. Esta seção cobre as falhas mais comuns (e mais fáceis de evitar): senhas salvas sem criptografia, tokens sem prazo de validade, acesso sem verificação de propriedade e dados sensíveis expostos por descuido. A maioria dos ataques não é sofisticada — eles exploram preguiça e falta de combinação prévia dentro do time.

### Como o app lembra quem fez Login?

Gerenciamento de sessão.

- Cookies Seguros (Web): O navegador cuida disso sozinho, blindado contra roubo por scripts da tela. _Vibe:_ Melhor para sites tradicionais e painéis.

> 💡 Motivo: Fortify com guard web e SESSION_DRIVER=database. Sanctum está instalado, mas a feature de API tokens do Jetstream está desligada.

### Onde guardamos as Chaves Secretas (Senha do Banco, API Keys)?

Acesso a serviços restritos.

- No cofre invisível (.env): Num arquivo isolado que NUNCA é enviado pro GitHub. _Vibe:_ O único jeito certo.

> 💡 Motivo: .env está no .gitignore, só .env.example é versionado, e CLAUDE.md proíbe env() fora de config/.

### Como as chaves secretas chegam ao servidor de produção?

O .env resolve no computador do dev, mas em produção as variáveis precisam chegar ao servidor de forma segura — sem aparecer em logs, painéis de CI ou repositórios.

- Variáveis de ambiente configuradas manualmente no painel do servidor: Digitamos as chaves diretamente no painel da Vercel, Railway, Heroku ou no servidor. _Vibe:_ Simples e suficiente para a maioria dos projetos. O risco é não ter histórico de quem alterou o quê e esquecer de atualizar quando a chave mudar.

> 💡 Motivo: Um app e poucos segredos (banco, e-mail), no .env do servidor com permissão restrita. Revisitar um cofre de segredos se entrarem credenciais bancárias ou de ERP.

### Como guardamos a senha do Joãozinho?

Salvando senhas no banco.

- Misturador (Hash Criptografado): Salva um texto maluco tipo $2y$10$abcde.... _Vibe:_ Seguro e obrigatório por lei (LGPD).

> 💡 Motivo: Cast 'password' => 'hashed' no User, bcrypt com BCRYPT_ROUNDS=12.

### Controle de Acesso (Quem pode apagar um post?)

Gerenciando permissões.

- Checagem de Políticas (Policies): O código cruza o Usuário atual com o Recurso que ele quer alterar. _Vibe:_ Seguro e granular (ex: "Só apaga se for dono do post ou admin").

> 💡 Motivo: O acesso é decidido no servidor por policies e gates que conferem o papel do usuário (`App\Enums\UserRole`), como em app/Policies/ReconciliationSessionPolicy.php e no gate `view-session-history`. Papéis de time do Jetstream não são usados.

### Defesa contra formulários fantasmas (CSRF/CORS)

Sites falsos tentando mandar coisas pro nosso servidor.

- Só entra convidado: Configuramos pra só aceitar cliques e dados que vieram das nossas próprias telas. _Vibe:_ Proteção padrão dos frameworks bons.

> 💡 Motivo: Middleware web do Laravel valida CSRF por padrão e não há CORS aberto configurado.

### O cara que muda o ID da URL (IDOR)

O usuário acessa /perfil/10/editar e resolve testar /perfil/11/editar.

- Confere a identidade: Antes de abrir, o código checa "Esse ID pertence ao cara que está logado?". _Vibe:_ Privacidade garantida.

> 💡 Motivo: As classes em app/Actions/Conciliation autorizam via Gate e policy antes de alterar uma sessão; a mesma regra vale para recursos novos.

### O usuário entra com Google, Apple ou cria senha própria?

Estratégia de autenticação.

- Senha própria (Credenciais): O usuário cria login e senha no nosso sistema. _Vibe:_ Mais controle, mas você é responsável por guardar a senha com segurança.

> 💡 Motivo: Autenticação via Fortify (login, reset, 2FA); Socialite não está instalado. Não há auto-cadastro: só o administrador cria usuários. O registro público do Fortify e a exclusão de conta do Jetstream estão desligados; o primeiro Administrador é criado com `php artisan conciliation:create-administrator`.

### O usuário fica logado para sempre?

Expiração de sessão.

- Expiração automática: A sessão expira após X minutos de inatividade ou o token tem prazo de validade. _Vibe:_ Mais seguro, especialmente em apps financeiros e corporativos.

> 💡 Motivo: SESSION_LIFETIME=120 minutos. Atenção: tokens Sanctum estão sem expiração (expiration => null).

### O app vai exigir uma segunda confirmação de identidade (2FA)?

Autenticação de dois fatores.

- Sim, segunda camada (2FA): Login exige senha + código extra (SMS, e-mail ou app autenticador como Google Authenticator). _Vibe:_ Obrigatório para apps financeiros, corporativos e qualquer coisa com dados sensíveis. Reduz em mais de 99% as invasões por senha roubada.

> 💡 Motivo: Features::twoFactorAuthentication ativo no Fortify (TOTP + códigos de recuperação), opcional por usuário.

### O que acontece se alguém tentar adivinhar a senha errada mil vezes?

Proteção contra força bruta e credential stuffing.

- Rate limiting no login + bloqueio temporário: Após N tentativas falhas no mesmo IP ou conta, o sistema bloqueia por X minutos e pode exigir CAPTCHA. _Vibe:_ Proteção básica e obrigatória. Ferramentas como fail2ban, middlewares de rate limiting (express-rate-limit, Rack::Attack) ou WAFs resolvem isso com poucas linhas de configuração.

> 💡 Motivo: RateLimiter 'login' e 'two-factor' definidos em FortifyServiceProvider; reset de senha com throttle de 60s.

### Como o usuário recupera a conta se esquecer a senha?

Fluxo de recuperação de senha.

- Link com expiração por e-mail (padrão seguro): O sistema gera um token único, envia por e-mail e expira em 15–60 minutos. _Vibe:_ Simples, seguro e esperado pelo usuário. O token deve ser de uso único e invalidado após uso.

> 💡 Motivo: Features::resetPasswords ativo; token expira em 60 minutos (config/auth.php).

---

## 🎨 5. A Cara do App (Comunicação Front e Back)

_Como a tela "conversa" com o motor nos bastidores._

A linha que separa o back-end (o motor) do front-end (a tela) é onde a maioria dos mal-entendidos acontece em times de desenvolvimento. Esta seção alinha como os dois lados "falam" entre si: o formato dos dados trocados, como os erros são comunicados, em qual plataforma o app vai rodar e quais ferramentas tornam essa comunicação previsível e documentada. Um contrato claro aqui evita horas de debugging e discussão entre times. Sem ele, cada integração vira uma surpresa.

### O usuário vai acessar pelo celular, computador ou pelos dois?

Escolha da plataforma.

- Só Web (responsiva): Um site que se adapta a qualquer tela. _Vibe:_ Um código só para tudo. Mais rápido e barato de desenvolver e manter.

> 💡 Motivo: Aplicação Blade + Livewire + Tailwind responsiva; não há app nativo no repo.

### O App é uma página que se monta no navegador ou no servidor?

A estratégia de renderização das telas.

- Renderização no Servidor (SSR): O servidor já manda a tela pronta pra o navegador mostrar. _Vibe:_ Carregamento inicial mais rápido e ranqueamento no Google muito melhor. Ideal para e-commerces, blogs e sites com tráfego orgânico.

> 💡 Motivo: Jetstream com stack 'livewire': HTML renderizado no servidor via Blade.

### O que o back-end devolve quando tudo dá certo?

O formato do JSON da API.

- Sempre a mesma caixa (Envelope Padrão): Tudo vem dentro de uma estrutura previsível, tipo { data: {}, erros: null }. _Vibe:_ O Front-end cria um código só para ler tudo.

> 💡 Motivo: CLAUDE.md manda usar Eloquent API Resources (envelope data). Hoje routes/api.php só tem /user sem Resource.

### O que o back-end devolve quando dá ERRO (ex: CPF inválido)?

Como o Front avisa o usuário do que ele errou.

- Mapa de Erros: Devolve uma lista: "cpf": "Formato incorreto". _Vibe:_ O Front consegue pintar de vermelho o campo exato que o usuário errou.

> 💡 Motivo: Validação do Laravel/Form Requests devolve erros por campo (422) e as views usam x-input-error.

### O Front-end pede uma busca com 3 filtros. Como ele manda isso?

Filtrando listas.

- Na URL (Query GET): ?cor=azul&tamanho=M. _Vibe:_ O link fica compartilhável.

> 💡 Motivo: Um analista manda para o outro o link exato da divergência (unidade + período + status). Filtros do Livewire/Filament persistidos na query string.

### Se precisarmos mudar muito o App no futuro? (Versionamento)

Como não quebrar os celulares velhos que ainda não atualizaram o app.

- Versões (V1, V2): O servidor mantém a rota /v1/perfil pros celulares velhos e cria a /v2/perfil pros novos. _Vibe:_ Transição suave.

> 💡 Motivo: CLAUDE.md define versionamento de API como padrão. Ainda não aplicado: /api/user está sem prefixo de versão.

### Como o Back avisa o Front das rotas que existem?

A documentação da API.

- Documentação Automática (Swagger/Postman): O código gera uma página web com os botões pro Front testar as rotas. _Vibe:_ Produtividade monstra.

> 💡 Motivo: Hoje a UI é Livewire e não há API consumida por terceiros. Se surgir integração (ERP, BI), a documentação nasce gerada do código, nunca em Notion.

### De onde vêm os botões, modais e tabelas do app?

Biblioteca de componentes visuais.

- Usamos uma biblioteca pronta (ex: Shadcn, Material UI, Tailwind UI): Importamos componentes já prontos e estilizados. _Vibe:_ Velocidade absurda no começo, mas o app pode parecer igual ao de todo mundo.

> 💡 Motivo: Filament 5 (forms, tables, actions, infolists, notifications) + componentes Blade do Jetstream em resources/views/components.

### Qual o estilo de comunicação entre front-end e back-end?

Estilo de API.

- REST: Cada recurso tem uma URL própria (GET /usuarios, POST /pedidos). _Vibe:_ O padrão da indústria. Simples de entender, fácil de documentar com Swagger e compatível com qualquer cliente.

> 💡 Motivo: routes/api.php com Sanctum é REST; a UI em si conversa com o servidor via Livewire, sem API própria.

### Como o front-end gerencia o estado global da aplicação?

Gerenciamento de estado no cliente.

- Estado local em cada componente (useState): Cada tela cuida dos seus próprios dados. _Vibe:_ Simples para apps pequenos, mas passa dados "de mão em mão" entre componentes vira pesadelo com o crescimento.

> 💡 Motivo: Com Livewire o estado mora no servidor, por componente; não há store global em JavaScript.

---

## 🚨 6. Quando a Casa Cai (Erros, Logs e Alertas)

_Vai dar erro. A questão é como a gente lida com ele._

Todo sistema vai falhar em algum momento. A questão não é "se" — é "quando" e o que acontece depois. Esta seção define a diferença entre saber que algo quebrou antes do cliente reclamar (pro-ativo) ou descobrir pelo Twitter (reativo). Logs bem configurados transformam um bug misterioso em um diagnóstico de 5 minutos. Alertas automáticos permitem agir antes do impacto virar crise. Sem elas, o time fica no escuro e o usuário paga o preço.

### A tela quebrou (Erro 500). O que o usuário enxerga?

A mensagem de erro fatal.

- Um simpático "Ops" + Trace ID. O usuário vê uma mensagem bonita e um código de rastreio escondidinho. _Vibe:_ Você usa o código pra achar o erro exato no sistema depois.

> 💡 Motivo: Usuário interno informa o código de rastreio ao suporte; stack trace nunca aparece na tela (APP_DEBUG=false em produção).

### Onde a gente anota (Log) os erros do sistema?

Como a gente investiga os bugs.

- Num Painel Visual (ex: Sentry/Datadog): O erro cai num painel na web dizendo "Estourou erro na linha 42". _Vibe:_ Você resolve o problema antes do cliente reclamar.

> 💡 Motivo: Falha em importação ou conciliação precisa ser vista antes do fechamento do período. A ferramenta (ex: Sentry) é dependência nova e precisa de aprovação antes de instalar.

### Como a gente sabe que o site caiu (Ficou Fora do Ar)?

Monitoramento de saúde.

- Robô Vigia (Healthcheck): Um serviço bate no site a cada minuto e apita no Slack/Discord do time se falhar. _Vibe:_ Você age na hora.

> 💡 Motivo: O Laravel 12 já expõe a rota /up; basta apontar um monitor externo para ela.

### Conseguimos rastrear o tempo de resposta de cada rota e o que aconteceu em cada requisição?

Observabilidade e APM (Application Performance Monitoring).

- Só logs e erros: Sabemos que algo quebrou, mas não sabemos qual rota está lenta, onde o tempo foi gasto ou o caminho completo de uma requisição. _Vibe:_ Suficiente para apps simples, mas debugar problemas de performance em produção fica difícil.

> 💡 Motivo: Monolito interno com poucos usuários simultâneos e sem SLA de latência; APM seria custo sem retorno agora.

### O que a gente NÃO PODE botar nos Logs de erro?

Proteção de dados no meio do caos.

- Filtro de Segredos (Sanitize): O sistema transforma campos sensíveis em ******* antes de salvar o log. _Vibe:_ Paz com a lei de proteção de dados.

> 💡 Motivo: Logs não podem carregar dados bancários, CPF/CNPJ de fornecedores nem valores de extrato em texto puro.

---

## 🤝 7. Trabalho em Equipe (Git e Deploy)

_Como programar junto sem um apagar o trabalho do outro._

O código é o produto — e o produto precisa ser entregue com segurança, rastreabilidade e sem depender de rituais manuais cheios de passos. Esta seção define como o time colabora sem pisar no trabalho um do outro, como as mudanças são revisadas antes de irem ao ar e como o processo de "publicar nova versão" passa de momento de terror para rotina automática e confiável. Times que dominam esse fluxo entregam mais rápido e dormem melhor.

### Como a gente junta o código da galera?

O fluxo do Git.

- Cria um ramo e pede permissão (Pull Request): Pede pro amigo revisar antes de juntar na principal. _Vibe:_ Filtro de qualidade anti-bug.

> 💡 Motivo: Sistema financeiro: nenhuma regra de conciliação entra sem revisão. Atenção: hoje os commits vão direto na master.

### Mensagens de Salvar o código (Commits)

Como descrevemos as mudanças.

- Padrão claro: fix: resolve crash no pagamento ou feat: adiciona botão de compartilhar. _Vibe:_ O histórico vira um livro fácil de ler.

> 💡 Motivo: Conventional Commits (feat:, fix:) — já usado em parte do histórico; padronizar em todos.

### Como o código sai do PC e vai pro Servidor (Deploy)?

Colocando no ar.

- Robô de Entrega (CI/CD): Quando junta o código na main, o GitHub avisa o servidor, que puxa e atualiza sozinho em segundos. _Vibe:_ Deploy na sexta-feira sem medo.

> 💡 Motivo: Deploy só depois de testes e Pint passarem no pipeline; .github já existe no repositório.

### O que a gente faz se o deploy quebrar produção?

Estratégia de rollback.

- Rollback documentado e testado: O pipeline tem um comando ou botão de rollback para a versão anterior. O time sabe exatamente o que fazer e ensaia o processo antes de precisar. _Vibe:_ Confiança para dar deploy com mais frequência. Se der errado, a volta é rápida e sem drama.

> 💡 Motivo: Parar o sistema no fechamento do mês trava o financeiro; a volta precisa ser um comando, incluindo o plano para migrations.

### Quando uma tarefa (Card) está "Pronta" (Definition of Done)?

A hora de fechar a tarefa.

- Tá na máquina principal, sem erros, testado e revisado. _Vibe:_ A equipe só comemora quando o usuário final já pode usar.

> 💡 Motivo: Pronto = mergeado, testes passando, revisado e utilizável pelo time financeiro.

### Os ambientes de teste e produção são separados?

Isolamento de ambientes.

- Ambientes separados (dev / staging / produção): O código passa por etapas antes de chegar no usuário final. _Vibe:_ Erros ficam contidos no ambiente de teste, não no bolso do cliente.

> 💡 Motivo: Desenvolvimento e testes rodam na VPS Debian atual, com banco próprio, nunca apontando para o Supabase de produção. Onde a produção roda ainda está a definir; se for na mesma VPS, com diretório, .env, banco e filas separados.

### Se o banco explodir hoje, quando voltamos ao ar?

Estratégia de backup.

- Backup automático com frequência e retenção definidas: Definimos de quanto em quanto tempo (ex: a cada 6h) e por quanto tempo guardamos (ex: 30 dias). _Vibe:_ Tranquilidade. A pergunta não é "se" vai dar problema, é "quando".

> 💡 Motivo: O banco é o registro de auditoria; perder histórico de conciliação é inaceitável. No Supabase Cloud pago o backup diário é gerenciado pela plataforma; confirmar a retenção do plano contratado e se o point-in-time recovery (add-on) será ativado.

---

## 🧪 8. Testes e Garantia da Vibe (Qualidade)

_A gente não é a NASA, mas ninguém quer ficar apagando incêndio 3 da manhã._

Testes automatizados são a rede de segurança que permite o time alterar o código sem medo. Sem ela, cada nova feature é uma roleta-russa: funcionou por acaso ou porque estava correto? Esta seção não prega cobertura de 100% — prega testes estratégicos: cobrir os fluxos críticos do negócio (login, pagamento, cadastro) de forma que qualquer quebra seja detectada antes de chegar ao usuário. A qualidade de código também passa por revisão humana, formatação consistente e uso de ambientes de teste isolados.

### Qual a nossa regra de Testes Automatizados?

Robôs testando o código.

- Testar o Caminho Feliz (API Tests): Um robô garante que as coisas principais (Login, Cadastro, Compra) funcionam antes de qualquer deploy. _Vibe:_ O sweet spot entre velocidade e segurança.

> 💡 Motivo: 21 arquivos de teste de feature (PHPUnit) cobrindo auth, 2FA, times e perfil; CLAUDE.md exige teste para toda mudança.

### Code Review (Revisão pelo Amigo)

O olhar de fora.

- Sempre alguém aprova: Mesmo que seja rápido, um dev olha o código do outro antes de ir pro ar. _Vibe:_ Disseminação de conhecimento.

> 💡 Motivo: Regra de conciliação errada vira erro contábil; um segundo par de olhos é obrigatório.

### Formatador de Código (Linting)

O visual do código.

- Robô chato, mas justo (Prettier/ESLint/Pint): Ao salvar o arquivo, o editor já formata e arruma pro padrão da equipe. _Vibe:_ O código inteiro parece que foi escrito pela mesma pessoa.

> 💡 Motivo: Laravel Pint instalado e obrigatório antes de finalizar mudanças (vendor/bin/pint --dirty).

### Testar pagamentos e integrações perigosas

Simulando ações do mundo real.

- Chaves de Mentirinha (Sandbox): O servidor local usa as credenciais de teste (cartões falsos). _Vibe:_ Seguro e permite errar à vontade.

> 💡 Motivo: O sistema não processa pagamentos, mas as integrações (e-mail, fontes de extrato) rodam com fakes nos testes e credenciais de teste fora de produção.

### O app precisa funcionar para pessoas com deficiência?

Acessibilidade (a11y).

- Seguimos as diretrizes de acessibilidade (WCAG): Contraste de cores, navegação por teclado, compatibilidade com leitores de tela. _Vibe:_ Produto mais inclusivo e em conformidade com a lei.

> 💡 Motivo: Sistema corporativo interno; contraste, teclado e leitor de tela desde o início, aproveitando os componentes do Filament.

### Como ativamos e desativamos features sem precisar de um novo deploy?

Feature Flags (chaves de lançamento).

- Chaves de feature (Feature Flags): A funcionalidade existe no código, mas fica "apagada". Ligamos para 1% dos usuários primeiro, observamos, depois aumentamos gradualmente. _Vibe:_ Permite lançamentos progressivos, testes A/B e rollback instantâneo sem novo deploy. Ferramentas: LaunchDarkly, Unleash ou simples flags no banco de dados.

> 💡 Motivo: Laravel Pennant instalado com store database e tabela features já migrada.

---

## ☁️ 9. Nuvem, Arquivos e Infra (Infraestrutura)

_Como o nosso código lida com o mundo físico e o peso dos arquivos._

O código mais bonito do mundo não serve de nada se o servidor não aguenta, o ambiente do dev é diferente do de produção e os arquivos do usuário somem quando o HD lota. Esta seção cobre as decisões de "onde as coisas rodam" — do computador do desenvolvedor até a nuvem que atende milhares de usuários simultâneos — e como garantir que esses ambientes sejam replicáveis, escaláveis e confiáveis. Infra mal planejada é a causa silenciosa de muitos bugs que "só acontecem em produção".

### Onde a gente salva as fotos e PDFs dos usuários?

Armazenamento de uploads.

- Na pasta do código (Local): Salva no próprio servidor. _Vibe:_ O HD do servidor lota rápido e impede de colocar um segundo servidor para ajudar no tráfego.

> 💡 Motivo: Planilhas e extratos ficam no disco privado do Laravel (storage/app/private), fora da pasta pública, servidos só por rota autenticada. Um servidor só e volume baixo. Como são evidência de auditoria e o backup do Supabase não cobre arquivos, essa pasta precisa de backup próprio para fora da VPS.

### Como o projeto roda no computador de um desenvolvedor novo?

O Onboarding técnico.

- Caixote Mágico (Docker / Containers): Roda UM comando e o projeto inteiro sobe sozinho, idêntico para todo mundo. _Vibe:_ Produtividade insana.

> 💡 Motivo: Laravel Sail já está instalado; sobe app e banco com um comando.

### A grande divisão: Como organizamos o projeto todo?

Monolito vs Microsserviços.

- Monolito Majestoso: Todo o código num projeto só. _Vibe:_ O melhor jeito de começar 99% dos projetos. Simples de dar deploy e testar.

> 💡 Motivo: Uma única aplicação Laravel com back-end e front-end (Blade/Livewire) no mesmo projeto.

### O app precisa funcionar sem internet?

Estrategia Offline First.

- Não, precisa de conexão: A tela quebra ou trava se o usuário ficar sem sinal. _Vibe:_ Aceitável para a maioria dos apps de escritório.

> 💡 Motivo: Livewire depende de round-trip ao servidor para cada interação.

### Quem cuida de atualizar as bibliotecas e corrigir vulnerabilidades?

Gerenciamento de dependências e segurança.

- Robô de atualizações automáticas (Dependabot / Renovate): Configuramos uma ferramenta que monitora as dependências e abre Pull Requests automáticos quando saem versões novas ou com correções de segurança. _Vibe:_ O time só precisa revisar e aprovar, sem precisar caçar o que está desatualizado.

> 💡 Motivo: Dependências desatualizadas em sistema financeiro são risco direto; Dependabot abre os PRs e o time só revisa.

### Como entregamos os arquivos estáticos (JS, CSS, imagens) aos usuários?

Distribuição de assets com CDN.

- Direto do servidor da aplicação: O mesmo servidor que processa as requisições também serve os arquivos estáticos. _Vibe:_ Simples de configurar, mas cada download de JS ou imagem consome CPU e banda do servidor principal. Usuários longe do servidor sentem latência maior.

> 💡 Motivo: Poucos usuários internos, mesma região; os assets versionados do Vite servidos pelo próprio servidor bastam.

---

## ⏱️ 10. Tempo Real e Rotinas (Cron & Real-time)

_O app precisa ser vivo e inteligente, mesmo sem cliques._

Uma boa parte da inteligência de um app moderno não vem de cliques do usuário — vem de coisas que acontecem sozinhas: cobranças automáticas na data certa, notificações instantâneas de novas mensagens, status de pedido atualizado com o pagamento confirmado. Esta seção cobre como o sistema "pensa por conta própria": agendamento de tarefas, comunicação bidirecional em tempo real e integração com sistemas externos que avisam o nosso app quando algo importante acontece — sem o usuário precisar recarregar a página.

### Atualizações em tempo real (ex: Nova mensagem no chat)

Como a tela sabe que algo mudou.

- F5 Automático (Polling): O celular fica perguntando pro servidor de 5 em 5 segundos. _Vibe:_ Gasta muito recurso do servidor à toa.

> 💡 Motivo: O único caso é acompanhar o progresso de importações e conciliações em fila; wire:poll resolve sem infraestrutura de WebSocket.

### Avisos de Terceiros (ex: MercadoPago avisando do pagamento)

Lidando com integrações externas ativas.

- Esperar o cliente voltar na tela: A gente só checa quando o usuário clica. _Vibe:_ Se ele não voltar, o pedido não anda.

> 💡 Motivo: Os dados entram por importação de arquivos feita pelo time; nenhum terceiro notifica o sistema. Revisitar se houver integração bancária ou com ERP.

### Tarefas que rodam sozinhas (ex: Cobrar mensalidade)

Automação baseada em tempo.

- Robô Agendado (Cron Jobs): O servidor roda uma função automaticamente na hora marcada. _Vibe:_ O sistema trabalha enquanto você dorme.

> 💡 Motivo: Scheduler do Laravel para reconciliar períodos, sinalizar parcelas vencidas ou em aberto e despesas que caem em outro período.

---

## 📊 11. Dados, Métricas e Conhecimento (Cultura)

_Saber o que tá acontecendo e passar o bastão._

Um produto que não mede não melhora. Esta seção vai além dos logs técnicos: trata de como o time aprende com o comportamento real dos usuários, como o conhecimento do projeto é preservado mesmo quando pessoas saem, como o código envelhece sem virar um fardo intocável e como a privacidade dos dados é respeitada por lei. São escolhas de cultura e processo — não de tecnologia — que separam times amadores de times que constroem produtos duradouros.

### Histórico de ações (Ex: Cliques, visualizações de tela)

Analisando o uso do app.

- Uma tabela gigante no nosso banco: _Vibe:_ Seu banco principal vai ficar lento guardando lixo de métrica.

> 💡 Motivo: Não coletamos cliques nem telemetria de uso. O que importa é a trilha de auditoria de negócio, que fica no nosso banco e não sai para terceiros.

### Defesa contra "Robôs e Raspadores" (Scraping)

Protegendo as APIs públicas.

- Bloqueador de Robôs: Se o IP pedir mais de 100 coisas por minuto, a gente bloqueia. _Vibe:_ Seu app protegido contra ataques.

> 💡 Motivo: Não há API pública, mas as rotas autenticadas e de API ficam atrás de throttle do Laravel.

### A Regra do Escoteiro (Refatoração)

Lidando com código antigo e feio.

- Melhoria Contínua: Deixa o código antigo quieto se ele funciona. Se precisar mexer nele, melhora um pouquinho. _Vibe:_ Pragmatismo e entrega de valor constante.

> 💡 Motivo: Entrega contínua de valor; refatorar só o que for tocado, com teste cobrindo.

### O "Fator Ônibus" (Se alguém for atropelado, o projeto morre?)

Documentação interna da equipe.

- README de Respeito: A página inicial do repositório ensina como instalar, onde ficam as senhas e como rodar o projeto. _Vibe:_ O projeto pertence à equipe.

> 💡 Motivo: As regras de conciliação (tolerâncias, parcelamento, casamento de fornecedor) ficam documentadas no repositório, não na cabeça de uma pessoa.

### O app coleta dados de rastreio ou analytics?

Conformidade com LGPD/GDPR.

- Banner de consentimento + Política de Privacidade: O usuário sabe o que está sendo coletado e pode recusar. _Vibe:_ Em conformidade com a lei e com a confiança do usuário.

> 💡 Motivo: Não usamos analytics nem rastreadores de terceiros, então não há banner; fica o aviso de privacidade interno sobre os dados de fornecedores e o log de auditoria.

---

## 🌍 12. Internacionalização e Localização

_Construir pra falar a língua do usuário, onde quer que ele esteja._

Ignorar internacionalização no início e tentar adicionar depois é uma das refatorações mais dolorosas que existem. Textos espalhados em centenas de arquivos, datas salvas no fuso errado, valores exibidos no formato equivocado — cada um desses problemas vira uma dívida técnica cara e silenciosa. Mesmo que o app seja só para o Brasil hoje, definir boas práticas de localização desde o início (UTC no banco, textos em arquivos separados, formato de moeda centralizado) tem custo quase zero agora e evita semanas de trabalho urgente no futuro.

### O app vai suportar múltiplos idiomas?

Estrategia de internacionalização (i18n).

- Preparado para múltiplos idiomas (i18n): Os textos ficam em arquivos de tradução separados. _Vibe:_ Custo baixo no início se feito desde o zero. Custo altíssimo se deixado para depois.

> 💡 Motivo: As views do Jetstream já usam __(); a interface fica em pt-BR via arquivos de tradução, sem texto fixo no código.

### Como exibimos datas, moedas e fuso horário?

Localização de formatos.

- Padrão definido (i18n / UTC no banco): Datas salvas sempre em UTC no banco, convertidas para o fuso do usuário na tela. Moeda e formato de número seguem o locale. _Vibe:_ Consistência e ausência de bugs de "a data chegou errada".

> 💡 Motivo: Datas em UTC no banco e exibidas no fuso do usuário; valores guardados em centavos (inteiro) e formatados em BRL num único ponto — diferença de centavos é justamente o problema que o sistema resolve.

---

## 📣 13. Comunicação com o Usuário (Notificações e E-mail)

_O app precisa falar com o usuário mesmo quando ele não está com a tela aberta._

Nenhum app moderno é uma ilha. Confirmações de compra, alertas de segurança, lembretes e novidades chegam ao usuário por e-mail, push e SMS. Essas comunicações parecem simples, mas envolvem decisões técnicas sérias: qual provedor usar, como garantir a entrega, como evitar que os e-mails caiam no spam e como separar mensagens operacionais ("sua senha foi alterada") de mensagens de marketing ("confira as novidades"). Misturar esses dois mundos no mesmo canal é o caminho certo para perder reputação de entrega e ter e-mails bloqueados.

### Como o app envia e-mails transacionais (confirmações, alertas, senhas)?

Provedor de e-mail transacional.

- Serviço dedicado (Resend, SendGrid, Amazon SES, Mailgun): O e-mail sai por infraestrutura com reputação estabelecida, painel de métricas (taxa de entrega, abertura, rejeição) e suporte a DNS de autenticação (SPF, DKIM, DMARC). _Vibe:_ E-mails que chegam de verdade. Essencial para qualquer app com cadastro ou pagamento.

> 💡 Motivo: Reset de senha, 2FA e alertas de divergência precisam chegar. Provedor a definir (o Laravel já traz drivers para SES, Resend e Postmark).

### E-mails de marketing e e-mails transacionais: mesmo servidor?

Separação de canais de e-mail.

- Tudo pelo mesmo lugar: _Vibe:_ Se uma campanha de marketing for marcada como spam, arrasta junto os e-mails de recuperação de senha e confirmação de compra. O usuário para de receber tudo.

> 💡 Motivo: Sistema interno não envia marketing; existe um único canal, só transacional.

### Como avisamos o usuário quando ele não está com o app aberto?

Notificações push e SMS.

- Não avisamos, ele volta quando quiser: _Vibe:_ Usuários esquecem do app e o engajamento despenca.

> 💡 Motivo: Sem push nem SMS. Divergências e parcelas pendentes aparecem como notificações dentro do sistema (Filament) e por e-mail.

---

## 💳 14. Pagamentos e Recorrência

_Dinheiro é a parte mais sensível do sistema. Uma falha aqui é prejuízo real — para o usuário e para o negócio._

A maioria dos tutoriais mostra como integrar um botão de pagamento. Nenhum mostra o que fazer quando o cartão é recusado na terceira tentativa de renovação, quando o usuário pede reembolso depois de 45 dias ou quando há uma disputa de chargeback. Esta seção cobre todo o ciclo de vida financeiro: como o dinheiro entra, como a recorrência é gerenciada, o que acontece nas falhas e como o time testa tudo isso sem gastar dinheiro de verdade.

### Qual gateway de pagamento vamos usar?

Escolha do processador de pagamentos.

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: O sistema não cobra nem processa pagamentos; apenas concilia pagamentos já efetuados fora dele.

### O app tem cobrança recorrente (assinatura)?

Modelo de faturamento.

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: Não há cobrança de usuários. Os parcelamentos tratados são das compras autorizadas (domínio do negócio), não assinaturas do produto.

### O que acontece quando a cobrança falha (cartão expirado, sem limite)?

Estrategia de dunning (recuperação de inadimplência).

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: Sem cobrança, sem dunning. Parcela autorizada e não paga é tratada como divergência de conciliação.

### Como testamos o fluxo de pagamento em desenvolvimento?

Ambiente de testes financeiros.

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: Não há gateway. Importações e conciliações são testadas com arquivos e factories de exemplo.

### Como tratamos pedidos de reembolso e chargebacks?

Política de estorno e disputes.

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: Não há gateway. Estornos que aparecem nos extratos são conciliados como lançamentos negativos.

---

## 🤖 15. Inteligência Artificial e LLMs

_IA não é mais diferencial — é infraestrutura. Mas infraestrutura mal planejada vira conta no cartão e dado vazado._

Em 2026, praticamente todo produto de software usa alguma forma de IA generativa — seja para sugestões, resumos, chatbots, classificação ou geração de conteúdo. O problema é que LLMs são diferentes de qualquer outra dependência de software: custam por token, podem alucinar, têm latência imprevisível, recebem dados do usuário e mudam de comportamento entre versões. Esta seção cobre as decisões que separam uma integração de IA bem-feita de uma que vai gerar incidentes, custos inesperados e problemas de privacidade.

### Qual modelo e provedor de IA vamos usar?

Escolha do LLM e infraestrutura.

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: O sistema não usa IA: a conciliação é determinística e isso evita custo recorrente para o cliente. O pacote laravel/ai segue instalado sem uso; removê-lo é mudança de dependência e depende de aprovação.

### Onde ficam os prompts do sistema?

Gerenciamento de prompts.

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: Sem IA no sistema.

### Como controlamos os custos de tokens?

Governo de custos de IA.

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: Sem IA no sistema, sem custo de tokens.

### O que pode e o que não pode ir no contexto enviado à IA?

Privacidade e segurança de dados no contexto.

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: Sem IA no sistema; nenhum dado financeiro sai para provedores de modelo.

### O que o sistema faz quando a API da IA está lenta ou indisponível?

Resiliência da integração com IA.

- Não se aplica: fora do escopo deste sistema.

> 💡 Motivo: Sem IA no sistema.
