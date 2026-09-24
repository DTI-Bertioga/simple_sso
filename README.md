# Simple SSO / OIDC Authentication for Moodle (`local_simple_sso`)

A native Moodle (4.1+) plugin that acts as a **Single Sign-On (SSO)** and simplified **OpenID Connect (OIDC)** provider using **HMAC-SHA256 (`HS256`) signed JWTs (JSON Web Tokens)**.

Allows integrating third-party external applications (such as GLPI, corporate portals, WordPress, or custom web apps) to authenticate users using Moodle credentials and user sessions.

---

## 🚀 Key Features

- **Multi-Tenant / Multi-Client Support:** Register and manage multiple client applications, each with unique `client_id` and `client_secret` credentials.
- **Open Redirect Protection:** Strict URL whitelist verification (*Allowed Redirect URIs*) using `parse_url()` to validate scheme, host, port, and path.
- **Audit Logging & Security Trail:** Built-in audit system (`mdl_local_simple_sso_logs`) tracking authentication attempts, security blocks (invalid URIs or credentials), IP addresses, and administrative changes directly in the Moodle admin panel.
- **Moodle Cohorts / Groups Mapping:** Automatically embeds the user's Moodle cohorts into the JWT `groups` claim.
- **Optimized OIDC UserInfo Endpoint:** Constant time $O(1)$ token signature verification by reading the `aud` (`client_id`) claim.
- **Universal Web Server Compatibility:** Full Bearer Authorization header support across Apache, Nginx, IIS, and FastCGI/PHP-CLI environments.
- **Native Moodle Admin Interface:** Manage client applications and view real-time audit logs directly from *Site administration ➔ Plugins ➔ Local plugins*.
- **Privacy API Compliant:** Implements Moodle's Privacy Subsystem (`provider.php`) in compliance with GDPR and data protection standards.

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
   git clone https://github.com/DTI-Bertioga/simple_sso.git simple_sso
   ```
   *Or copy the `simple_sso` directory into `local/` so the path becomes `/local/simple_sso/`.*

2. Log in to Moodle as an Administrator and navigate to **Site administration ➔ Notifications**.
3. Moodle will detect the database schema tables (`mdl_local_simple_sso_clients` and `mdl_local_simple_sso_logs`) and prompt for the database installation/upgrade. Complete the installation.

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

### 2. Viewing Audit Logs
Navigate to the bottom of the **Manage SSO Applications** page to view the **Audit Logs & SSO History** table showing real-time event logs, IP addresses, timestamp, user IDs, and detailed security error messages for blocked requests.

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
├── classes/
│   └── privacy/
│       └── provider.php     # Moodle Privacy API implementation
├── db/
│   ├── install.xml          # Database schema for clients and audit logs tables
│   └── upgrade.php          # Database upgrade routines
├── lang/
│   ├── en/
│   │   └── local_simple_sso.php # English language pack and privacy metadata
│   └── pt_br/
│       └── local_simple_sso.php # Brazilian Portuguese language pack
├── authorize.php            # Main SSO / OIDC authorization endpoint
├── lib.php                  # Helper functions (strict parse_url URL validation & audit logging)
├── login.php                # Secure login endpoint with client verification
├── manage.php               # Administrative management UI and Audit Logs viewer
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
