# Simple SSO / OIDC Authentication for Moodle (`local_simple_sso`)

[![Moodle Version](https://img.shields.io/badge/Moodle-4.1%2B-orange.svg)](https://moodle.org)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![Release](https://img.shields.io/badge/Release-v1.2.1-green.svg)](version.php)

A native Moodle (4.1+) plugin that acts as a **Single Sign-On (SSO)** and **OpenID Connect (OIDC)** provider supporting **Implicit Flow**, **Authorization Code Flow with PKCE (RFC 7636)**, and **Signed JWTs (HMAC-SHA256 / `HS256`)**.

Allows integrating third-party external applications (such as GLPI, mobile apps in Flutter / React Native, SPAs, corporate portals, or custom web apps) to authenticate users with Moodle credentials, canonical physical person identity (CPF), and HR employee claims.

---

## 🚀 Key Features

- **PKCE Support (RFC 7636):** Full Proof Key for Code Exchange implementation (Authorization Code Flow) designed for secure authentication in Mobile Apps and Single Page Applications (SPAs) without exposing `client_secret`.
- **OIDC Strict Mode (`#token=`) & Legacy Query Mode (`?token=`):** Supports URI fragment redirection (`#token=...`) to prevent token leakage in HTTP server logs and `Referer` headers, alongside legacy query parameter mode for backend PHP applications.
- **Canonical Physical Person Identity (`sub`):** Resolves and emits the user's canonical ID as `sub` to guarantee single-person identity mapping across multiple login methods (Google OAuth2, LDAP, Manual).
- **HR Employee Records & Claims (`vinculos`):** Embeds employee contract details (matricula, contrato, cargo, lotacao, secretaria) into the JWT token claims.
- **Target Audience Restriction (`allowed_audience`):** Per-client access restriction (`todos`, `servidor`, `contribuinte`) with automated authorization blocking.
- **Multi-Tenant / Multi-Client Management:** Register and manage multiple client applications with unique `client_id`, `client_secret`, and allowed URI whitelists.
- **Strict Redirect URI Whitelisting:** URL whitelist verification using `parse_url()` validating scheme, host, port, and path prefix.
- **Audit Logging & Security Trail:** System tracking login events, security blocks, IP addresses, HTTP user agents, and administrative actions directly in the Moodle admin panel.
- **Mustache & Output API Template:** Management UI built with Moodle's Mustache template system and `$OUTPUT->render_from_template()`.
- **Optimized UserInfo Endpoint:** $O(1)$ token signature verification reading `aud` (`client_id`).
- **Privacy API Compliant:** Implements Moodle's Privacy Subsystem (`provider.php`) in full compliance with GDPR / LGPD data protection standards.

---

## 📋 Requirements

- **Moodle:** 4.1 or higher (`2022111800+`).
- **PHP:** 7.4, 8.0, 8.1, 8.2 or 8.3+.
- **PHP JWT Library:** `firebase/php-jwt` (included natively in Moodle core dependencies).

---

## 🛠️ Installation

1. Download or clone this repository into your Moodle's local plugins directory:
   ```bash
   cd /path/to/your/moodle/local/
   git clone https://github.com/DTI-Bertioga/simple_sso.git simple_sso
   ```
   *Or copy the `simple_sso` directory into `local/` so the path becomes `/local/simple_sso/`.*
   *Or navigate to `Site administration ➔ Plugins ➔ Install plugins` and upload `simple_sso.zip`.*

2. Log in to Moodle as an Administrator and navigate to **Site administration ➔ Notifications**.
3. Moodle will detect the plugin and prompt for installation/database upgrade. Complete the upgrade.

---

## ⚙️ Configuration & Management

### 1. Registering a Client Application
1. Go to **Site administration ➔ Plugins ➔ Local plugins ➔ Simple SSO / OIDC Authentication ➔ Manage SSO Applications**.
2. In the **Register New Application** form:
   - **Application Name:** Enter the client system name (e.g., `GLPI` or `Mobile App`).
   - **Allowed Redirect URIs (Whitelist):** Enter the authorized callback URLs (one per line):
     ```text
     https://requests.company.com/callback
     https://app.company.com/sso/callback
     ```
   - **Target Audience:** Select `All Users`, `Servidores Públicos`, or `Contribuintes`.
3. Click **Save Application**. Copy the generated `client_id` and `client_secret`.

---

## 🔐 Authentication Modes & Integration Guide

### 1. Mode 1: OIDC Strict Mode (Implicit Flow via `#token=`)
- **Authorize Request:**
  ```text
  GET /local/simple_sso/authorize.php?client_id=CLIENT_ID&redirect_uri=HTTPS_CALLBACK_URL&response_mode=fragment
  ```
- **Redirect Response:**
  ```text
  HTTP 302 ➡️ https://your-app.com/callback#token=eyJhbGciOiJIUzI1Ni...
  ```
- **Client JS Handling:**
  ```javascript
  if (window.location.hash.includes('token=')) {
      const params = new URLSearchParams(window.location.hash.substring(1));
      const jwtToken = params.get('token');
      // Decode or send to backend
  }
  ```

---

### 2. Mode 2: Legacy Backend Mode (`?token=`)
- **Authorize Request:**
  ```text
  GET /local/simple_sso/authorize.php?client_id=CLIENT_ID&redirect_uri=HTTPS_CALLBACK_URL&response_mode=query
  ```
- **Redirect Response:**
  ```text
  HTTP 302 ➡️ https://your-app.com/callback?token=eyJhbGciOiJIUzI1Ni...
  ```
- **PHP Backend Handling:**
  ```php
  if (isset($_GET['token'])) {
      $jwt = $_GET['token'];
      $decoded = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key($client_secret, 'HS256'));
  }
  ```

---

### 3. Mode 3: PKCE Flow (Authorization Code Flow - RFC 7636)

#### Step 1: Redirect to Authorize Endpoint
Generate a random `code_verifier` (43-128 chars) and calculate `code_challenge = base64url(sha256(code_verifier))`.

```text
GET /local/simple_sso/authorize.php?
    client_id=CLIENT_ID&
    redirect_uri=HTTPS_CALLBACK_URL&
    response_type=code&
    code_challenge=COMPUTED_SHA256_HASH&
    code_challenge_method=S256
```

#### Step 2: Exchange Authorization Code at Token Endpoint
Upon receiving `?code=AUTH_CODE` at your callback URL, send a `POST` request:

```text
POST /local/simple_sso/token.php
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&
client_id=CLIENT_ID&
code=AUTH_CODE&
code_verifier=CODE_VERIFIER_ORIGINAL
```

**JSON Token Response:**
```json
{
  "access_token": "eyJhbGciOiJIUzI1Ni...",
  "token_type": "Bearer",
  "expires_in": 300,
  "id_token": "eyJhbGciOiJIUzI1Ni..."
}
```

---

## 📦 JWT Payload Claims Structure

```json
{
  "iss": "https://moodle.company.com",
  "aud": "client_eb1c70394387702a",
  "sub": "12345678900",
  "moodle_userid": 42,
  "email": "user@company.com",
  "firstname": "João",
  "lastname": "Silva",
  "name": "João Silva",
  "user_type": "servidor",
  "auth_method": "manual",
  "vinculos": [
    {
      "matricula": 1024,
      "contrato": 1,
      "cod_lotacao": "SEC-01",
      "cargo": "Analista de Tecnologia",
      "lotacao": "Secretaria de TI",
      "descr_secretaria": "Secretaria de Tecnologia da Informação"
    }
  ],
  "groups": ["TI_SUPORTE", "COORTE_2026"],
  "iat": 1772832000,
  "exp": 1772832300
}
```

---

## 🔗 Available Endpoints

| Endpoint | Method | Description | Parameters |
| :--- | :--- | :--- | :--- |
| `/local/simple_sso/authorize.php` | `GET` | Main authorization endpoint | `client_id`, `redirect_uri`, `response_type` (`token`/`code`), `response_mode` (`fragment`/`query`), `code_challenge`, `code_challenge_method` (`S256`) |
| `/local/simple_sso/token.php` | `POST` | Token issuance endpoint | `grant_type` (`authorization_code`/`client_credentials`), `client_id`, `client_secret` (optional in PKCE), `code`, `code_verifier` |
| `/local/simple_sso/userinfo.php` | `GET` | User profile endpoint | Header: `Authorization: Bearer <JWT_TOKEN>` |
| `/local/simple_sso/manage.php` | `GET/POST` | Administrative UI | Management panel and real-time audit log viewer |

---

## 📄 File Structure

```text
local/simple_sso/
├── classes/
│   └── privacy/
│       └── provider.php        # Moodle Privacy API implementation (GDPR / LGPD)
├── db/
│   ├── install.xml             # Database schema for clients, codes, and audit logs
│   └── upgrade.php             # Database upgrade routines
├── lang/
│   ├── en/
│   │   └── local_simple_sso.php # English language pack and privacy metadata
│   └── pt_br/
│       └── local_simple_sso.php # Brazilian Portuguese language pack
├── templates/
│   └── manage.mustache         # Mustache UI template for administration panel
├── authorize.php               # Main SSO / OIDC authorization endpoint
├── lib.php                     # Core helper functions (whitelist validation, PKCE, audit logging)
├── link_account.php            # First-access Google/OAuth2 CPF link page
├── login.php                   # Simplified login redirect endpoint
├── manage.php                  # Administration UI controller & audit log viewer
├── settings.php                # Moodle admin navigation menu integration
├── token.php                   # OIDC Token endpoint (PKCE & Client Credentials)
├── userinfo.php                # OIDC UserInfo endpoint (returns JSON profile)
├── version.php                 # Moodle plugin version control
├── LICENSE                     # GNU General Public License v3
└── README.md                   # Plugin documentation and integration guide
```

---

## 📄 License

This plugin is licensed under the **GNU General Public License (GPLv3)** or later, following Moodle's standard open-source licensing.
