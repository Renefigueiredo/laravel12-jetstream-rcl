# Quickstart: Códigos de Operação Excluídos da Conciliação (Módulo 5)

Como conferir a feature de ponta a ponta. Modelo em [data-model.md](./data-model.md); formato do
arquivo em [contracts/import-file.md](./contracts/import-file.md); rotas e comandos em
[contracts/routes.md](./contracts/routes.md).

## Pré-requisitos

Os mesmos do Módulo 1 ([quickstart](../001-session-file-import/quickstart.md)): dependências
instaladas, `.env` configurado e banco local criado. Não há pacote novo nem ajuste de PHP: o
limite de 5 MB cabe nos limites já configurados.

## Preparar

```bash
php artisan migrate
php artisan db:seed             # só em ambiente local: um Administrador e um Operador
php artisan queue:work          # em outro terminal; reinicie após mudar código
npm run dev                     # ou npm run build
```

## Testes automatizados

```bash
php artisan test --compact tests/Unit/Conciliation/OperationCodeTest.php
php artisan test --compact tests/Unit/Conciliation/ExcludedCodeFileParserTest.php
php artisan test --compact --filter='ExcludedCode|UserPermission'
```

Arquivos de teste previstos, em `tests/Feature/Conciliation`:

| Arquivo | Cobre |
|---------|-------|
| `ExcludedCodeAuthorizationTest` | US5: acesso negado sem permissão, menu, gate, Administrador |
| `AddExcludedCodeTest` | US1: cadastro, duplicidade, espaços, validação, auditoria |
| `ImportExcludedCodesTest` | US3: resumo, recusa por linha, repetidos, cabeçalho, limites, auditoria |
| `ExcludedCodeListTest` | US4: paginação, busca, ordenação, remoção, recadastro |
| `ExcludedCodeSnapshotTest` | US2: comparação exata, fotografia imutável, pagamentos continuam importados |
| `ExcludedCodeTemplateTest` | US3: planilha modelo |
| `UserPermissionTest` | permissão pelo papel e por concessão |
| `UserPermissionCommandTest` | conceder e revogar, com auditoria |
| `ExcludedCodeImportMaintenanceTest` | limpeza e recuperação de importações |
| `ExcludedCodePerformanceTest` | SC-004 e SC-006 |

## Roteiro manual

Entrar como `admin@example.com`.

1. **Menu e acesso**: o menu mostra "Códigos excluídos". Entrar como `operador@example.com`: o
   item não aparece e `/codigos-excluidos` responde 403.
2. **Conceder a permissão**:
   `php artisan conciliation:grant-permission operador@example.com manage_excluded_codes --by=admin@example.com`.
   O Operador passa a ver a tela.
3. **Cadastro manual**: adicionar `20150652` com a descrição "Folha de pagamento". O código
   aparece com data e responsável. Adicionar de novo: recusa por duplicidade. Adicionar
   `" 11018953 "` com espaços: é gravado sem os espaços.
   Com pagamentos já importados, digitar um código que nenhum deles usa (por exemplo `1100172`):
   o modal mostra o alerta amarelo e ainda assim deixa salvar.
4. **Validação**: tentar salvar em branco e com `12-34`: nada é gravado e o campo mostra o motivo.
5. **Planilha modelo**: baixar, abrir e conferir os cabeçalhos e a linha de exemplo.
6. **Importação válida**: enviar um arquivo com 10 códigos novos e 5 já cadastrados. A tela
   mostra "em andamento" e depois "10 códigos adicionados e 5 códigos ignorados por já estarem
   cadastrados ou repetidos no arquivo".
7. **Importação inválida**: enviar um arquivo com a linha 45 em branco no código e descrição
   preenchida. Nada é gravado e a tela indica a linha 45, a coluna `COD_OPERACAO` e o motivo.
8. **Arquivo sem códigos** e **arquivo com mais de 10.000 linhas**: recusados com a mensagem
   própria.
9. **Busca e ordenação**: buscar por parte de uma descrição, ordenar por data e copiar o
   endereço para outra aba: a mesma lista aparece.
10. **Remoção**: remover um código, cancelar na confirmação (nada muda), remover de novo e
    confirmar. Cadastrar o mesmo código outra vez: volta como cadastro novo.
11. **Auditoria**: conferir no banco os registros de inclusão manual, inclusão por arquivo e
    exclusão, com usuário e horário.
12. **Acessibilidade**: usando só o teclado, abrir o modal de cadastro, salvar, enviar um arquivo
    e confirmar uma remoção. Conferir que todo campo tem rótulo, que o foco fica visível e que as
    mensagens de erro e de sucesso são lidas por leitor de tela.

## Com os arquivos reais

Montar a lista a partir do relatório de pagamentos de julho/2026: recortar as colunas
`COD_OPERACAO` e nome da operação das linhas que não são compras e importar. O efeito na fila de
investigação só aparece depois do Módulo 2, que consome a lista.

## Resultado esperado

- Suíte verde, incluindo a do Módulo 1.
- `vendor/bin/pint --dirty --format agent` sem alterações pendentes.
