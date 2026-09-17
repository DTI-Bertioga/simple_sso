# Autenticação Simples SSO / OIDC para Moodle (`local_simple_sso`)

Plugin nativo do Moodle (4.1+) que atua como **Provedor de Autenticação Single Sign-On (SSO)** e **OIDC (OpenID Connect)** simplificado baseado em **Tokens JWT (JSON Web Tokens)** assinados via HMAC-SHA256 (`HS256`).

Permite integrar sistemas externos (como GLPI, portais corporativos, WordPress e sistemas proprietários) para autenticar usuários utilizando as credenciais e a sessão do Moodle.

---

## 🚀 Principais Recursos

- **Multi-Tenant / Múltiplas Aplicações:** Cadastre e gerencie múltiplos sistemas clientes com `client_id` e `client_secret` exclusivos.
- **Proteção contra Open Redirect:** Validação rigorosa de URLs de redirecionamento autorizadas (Lista Branca / *Allowed Redirect URIs*) com decomposição de protocolo, host, porta e caminho via `parse_url()`.
- **Mapeamento de Coortes/Grupos:** Inclui automaticamente as coortes nativas do Moodle do usuário autenticado no claim `groups` do JWT.
- **Endpoint OIDC UserInfo Otimizado:** Validação da assinatura do token com busca em tempo constante $O(1)$ pela claim `aud` (`client_id`).
- **Compatibilidade Universal com Servidores Web:** Suporte a cabeçalhos de autorização Bearer em ambientes Apache, Nginx, IIS e FastCGI/PHP-CLI.
- **Painel Administrativo Nativo:** Gerenciamento de aplicações integrado à navegação do Moodle em *Administração do site ➔ Plugins ➔ Plugins locais*.

---

## 📋 Requisitos

- **Moodle:** 4.1 ou superior (`2022111800+`).
- **PHP:** 7.4, 8.0, 8.1, 8.2 ou superior.
- **Biblioteca PHP JWT:** `firebase/php-jwt` (incluída nativamente nas dependências do Moodle).

---

## 🛠️ Instalação

1. Baixe ou clone este repositório no diretório de plugins locais do seu Moodle:
   ```bash
   cd /caminho/do/seu/moodle/local/
   git clone https://github.com/SEU_USUARIO/simple_sso.git simple_sso
   ```
   *Ou copie a pasta `simple_sso` para dentro de `local/` ficando `/local/simple_sso/`.*

2. Acesse o Moodle como Administrador e vá para **Administração do site ➔ Notificações**.
3. O Moodle detectará a nova tabela (`mdl_local_simple_sso_clients`) e exibirá a tela de atualização do banco de dados. Conclua a instalação.

---

## ⚙️ Configuração e Uso

### 1. Cadastrar uma Aplicação Cliente
1. Acesse **Administração do site ➔ Plugins ➔ Plugins locais ➔ Autenticação Simples SSO / OIDC ➔ Gerenciar Aplicações SSO**.
2. No formulário **Cadastrar Nova Aplicação**:
   - **Nome da Aplicação:** Insira o nome do sistema receptor (ex: `GLPI` ou `Portal do Aluno`).
   - **URLs de Redirecionamento Permitidas (Lista Branca):** Informe as URLs para onde o Moodle poderá redirecionar o usuário após o login (uma URL por linha). Exemplo:
     ```text
     https://serviço.empresa.com/callback
     http://localhost/local/simple_sso/test_sso.php
     ```
3. Clique em **Salvar Aplicação**.
4. Anote o `client_id` e o `client_secret` gerados automaticamente.

---

## 🔗 Endpoints Disponíveis

| Endpoint | URL | Método | Descrição |
| :--- | :--- | :--- | :--- |
| **Authorization** | `/local/simple_sso/authorize.php` | `GET` | Inicia o fluxo SSO. Exige `client_id` e `redirect_uri`. Redireciona de volta anexando `?token=<JWT>`. |
| **Login (Alias)** | `/local/simple_sso/login.php` | `GET` | Endpoint simplificado de login SSO com validação de `client_id` e Lista Branca. |
| **Token** | `/local/simple_sso/token.php` | `POST` / `GET` | Valida credenciais do cliente (`client_id` e `client_secret`). |
| **UserInfo** | `/local/simple_sso/userinfo.php` | `GET` | Retorna o perfil do usuário em JSON. Exige cabeçalho `Authorization: Bearer <JWT>`. |

---

## 🧪 Como Testar a Integração

O plugin inclui um simulador de cliente de teste no arquivo `test_sso.php`:

1. Certifique-se de ter cadastrado ao menos uma aplicação no painel com a URL do simulador autorizada:
   `http://seu-moodle.com/local/simple_sso/test_sso.php` (ou `https://...`).
2. Acesse no navegador a URL do teste:
   ```text
   http://seu-moodle.com/local/simple_sso/test_sso.php
   ```
3. Clique no botão **Entrar via Moodle SSO**.
4. O sistema redirecionará para o endpoint `/authorize.php`, autenticará sua sessão no Moodle e retornará para a tela de teste.
5. Se a autenticação for bem-sucedida, você verá o **Payload do Token JWT Decodificado** exibindo o usuário (`sub`), e-mail, nome e os grupos/coortes vinculados.

---

## 📄 Estrutura de Arquivos

```text
local/simple_sso/
├── db/
│   └── install.xml          # Estrutura da tabela DB 'local_simple_sso_clients'
├── lang/
│   └── en/
│       └── local_simple_sso.php # Dicionário de strings e mensagens de erro
├── authorize.php            # Endpoint de Autorização SSO / OIDC principal
├── lib.php                  # Funções auxiliares (validação rigorosa de URLs parse_url)
├── login.php                # Endpoint seguro de login com verificação de cliente
├── manage.php               # Painel administrativo Moodle de gestão de aplicações
├── settings.php             # Configuração da página de administração Moodle
├── test_sso.php             # Simulador / Script de teste para validação do fluxo
├── token.php                # Endpoint de verificação OIDC de credenciais
├── userinfo.php             # Endpoint OIDC UserInfo (retorna perfil em JSON)
├── version.php              # Controle de versão do plugin Moodle
└── README.md                # Documentação e instruções de uso
```

---

## 📄 Licença

Este plugin é distribuído sob os termos da licença **GNU General Public License (GPLv3)** ou posterior, seguindo o padrão do ecossistema Moodle.
