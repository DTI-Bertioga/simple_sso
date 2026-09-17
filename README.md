# Simple SSO / OIDC Authentication for Moodle (`local_simple_sso`)

A native Moodle (4.1+) plugin that acts as a **Single Sign-On (SSO)** and simplified **OpenID Connect (OIDC)** provider using **HMAC-SHA256 (`HS256`) signed JWTs (JSON Web Tokens)**.

Allows integrating third-party external applications (such as GLPI, corporate portals, WordPress, or custom web apps) to authenticate users using Moodle credentials and user sessions.

---

## 🚀 Key Features

- **Multi-Tenant / Multi-Client Support:** Register and manage multiple client applications, each with unique `client_id` and `client_secret` credentials.
- **Open Redirect Protection:** Strict URL whitelist verification (*Allowed Redirect URIs*) using `parse_url()` to validate scheme, host, port, and path.
- **Moodle Cohorts / Groups Mapping:** Automatically embeds the user's Moodle cohorts into the JWT `groups` claim.
- **Optimized OIDC UserInfo Endpoint:** Constant time $O(1)$ token signature verification by reading the `aud` (`client_id`) claim.
- **Universal Web Server Compatibility:** Full Bearer Authorization header support across Apache, Nginx, IIS, and FastCGI/PHP-CLI environments.
- **Native Moodle Admin Interface:** Manage client applications directly from *Site administration ➔ Plugins ➔ Local plugins*.

---

## 📋 Requirements

- **Moodle:** 4.1 or higher (`2022111800+`).
- **PHP:** 7.4, 8.0, 8.1, 8.2 or higher.
- **PHP JWT Library:** `firebase/php-jwt` (included natively in Moodle dependencies).

---

## 🛠️ Installation

1. Download or clone this repository into your Moodle's local plugins directory:
   ```bash
   cd /path/to/your/moodle/local/
   git clone https://github.com/YOUR_USERNAME/simple_sso.git simple_sso
   ```
   *Or copy the `simple_sso` directory into `local/` so the path becomes `/local/simple_sso/`.*

2. Log in to Moodle as an Administrator and navigate to **Site administration ➔ Notifications**.
3. Moodle will detect the new table (`mdl_local_simple_sso_clients`) and prompt for the database upgrade. Complete the installation.

---

## ⚙️ Configuration & Usage

### 1. Registering a Client Application
1. Go to **Site administration ➔ Plugins ➔ Local plugins ➔ Simple SSO / OIDC Authentication ➔ Manage SSO Applications**.
2. In the **Register New Application** form:
   - **Application Name:** Enter the client system name (e.g., `GLPI` or `Student Portal`).
   - **Allowed Redirect URIs (Whitelist):** Enter the authorized callback URLs where Moodle is permitted to redirect users after login (one URL per line). Example:
     ```text
     https://requests.company.com/callback
     http://localhost/local/simple_sso/test_sso.php
     ```
3. Click **Save Application**.
4. Copy the generated `client_id` and `client_secret`.

---

## 🔗 Available Endpoints

| Endpoint | URL | Method | Description |
| :--- | :--- | :--- | :--- |
| **Authorization** | `/local/simple_sso/authorize.php` | `GET` | Initiates the SSO flow. Requires `client_id` and `redirect_uri`. Redirects back appending `?token=<JWT>`. |
| **Login (Alias)** | `/local/simple_sso/login.php` | `GET` | Simplified SSO login endpoint with `client_id` and whitelist validation. |
| **Token** | `/local/simple_sso/token.php` | `POST` / `GET` | Validates client credentials (`client_id` and `client_secret`). |
| **UserInfo** | `/local/simple_sso/userinfo.php` | `GET` | Returns user profile data in JSON. Requires `Authorization: Bearer <JWT>` header. |

---

## 🧪 Testing the Integration

The plugin includes a test simulator script in `test_sso.php`:

1. Ensure you have registered at least one application with the simulator URL added to the whitelist:
   `http://your-moodle.com/local/simple_sso/test_sso.php` (or `https://...`).
2. Open the test URL in your browser:
   ```text
   http://your-moodle.com/local/simple_sso/test_sso.php
   ```
3. Click the **Login via Moodle SSO** button.
4. The system will redirect to `/authorize.php`, authenticate your Moodle session, and redirect back to the test page.
5. Upon successful authentication, the decoded **JWT Payload** will be displayed, showing the user ID (`sub`), email, name, and assigned cohorts/groups.

---

## 📄 File Structure

```text
local/simple_sso/
├── db/
│   └── install.xml          # Database schema for 'local_simple_sso_clients' table
├── lang/
│   └── en/
│       └── local_simple_sso.php # String translations and error messages
├── authorize.php            # Main SSO / OIDC authorization endpoint
├── lib.php                  # Helper functions (strict parse_url URL whitelist validation)
├── login.php                # Secure login endpoint with client verification
├── manage.php               # Moodle administrative management UI
├── settings.php             # Moodle administration menu registration
├── test_sso.php             # Test simulator script for integration testing
├── token.php                # OIDC client credentials validation endpoint
├── userinfo.php             # OIDC UserInfo endpoint (returns JSON profile)
├── version.php              # Moodle plugin version control
└── README.md                # Documentation and usage guide
```

---

## 📄 License

This plugin is licensed under the **GNU General Public License (GPLv3)** or later, following Moodle's standard open-source licensing.
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
