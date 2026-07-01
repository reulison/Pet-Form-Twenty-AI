# Pet Form API (PHP + Twenty CRM)

Aplicacao em PHP para cadastro de tutor e pets, com envio dos dados para o Twenty CRM.

## Fluxo atual

1. O formulario em `index.php` valida dados do tutor e dos pets.
2. O backend chama `sendToTwenty` em `twenty.php`.
3. O tutor (person) e criado ou atualizado no Twenty.
4. Cada pet e criado relacionado ao tutor.

## Requisitos

- PHP 8.0+
- Extensao cURL habilitada
- Conta no Twenty com API Key valida

## Estrutura principal

- `index.php`: pagina e processamento do formulario
- `twenty.php`: integracao com API REST/GraphQL do Twenty
- `public/style.css`: estilos da pagina
- `public/script.js`: comportamento dinamico do formulario
- `.env.example`: exemplo de variaveis de ambiente

## Configuracao

1. Copie `.env.example` para `.env`.
2. Preencha pelo menos as variaveis abaixo:

- `TWENTY_API_KEY`
- `TWENTY_BASE_URL` (padrao: `https://api.twenty.com`)
- `TWENTY_PERSON_OBJECT_NAME` (padrao: `people`)
- `TWENTY_PET_OBJECT_NAME` (padrao: `pets`)

Observacao: as variaveis `TWENTY_PET_*` tambem estao no `.env.example` para refletir mapeamentos de campos.

## Executar localmente

No PowerShell, na raiz do projeto:

php -S localhost:8000

Depois acesse:

http://localhost:8000

## Logs e depuracao

- Erros de integracao com Twenty sao registrados em `twenty_error.log`.
- Esse arquivo esta ignorado pelo Git via `*.log` no `.gitignore`.
- Para depuracao detalhada em desenvolvimento, ative `TWENTY_DEBUG` em `twenty.php`.

## Comportamentos importantes

- O fluxo trata tentativa de duplicidade de pessoa no Twenty.
- Se existir registro soft-deleted para o mesmo email, o sistema tenta restaurar e reaproveitar o cadastro.
- Se falhar, a mensagem de erro retornada para a interface inclui detalhes tecnicos para diagnostico.

## Publicacao no GitHub

Antes de subir:

- Garanta que `.env` nao esta versionado.
- Remova arquivos locais temporarios de teste e logs antigos.
- Revise se as chaves e URLs sensiveis nao aparecem em commits.