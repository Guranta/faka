---
name: acg-faka-deploy
description: Deploy acg-faka (异次元发卡) with Infini crypto payment to a VPS
version: 1.0.0
platforms: [linux]
metadata:
  hermes:
    tags: [deployment, php, nginx, mysql, vps, faka]
    requires_toolsets: [terminal]
---

# Deploy acg-faka + Infini Payment

## Overview

Deploy a PHP card-selling platform (acg-faka) with an integrated Infini crypto payment plugin (USDT/USDC) on a fresh Ubuntu/Debian VPS.

## Prerequisites (confirm before starting)

- VPS with a public IP, running Ubuntu 22.04+ or Debian 11+
- A domain name pointed to the VPS IP (A record)
- SSH root access

## Variables — fill these in before running

| Variable | Description | Example |
|----------|-------------|---------|
| `DOMAIN` | Your domain name | `shop.example.com` |
| `DB_NAME` | MySQL database name | `acgfaka` |
| `DB_USER` | MySQL user | `acgfaka` |
| `DB_PASS` | MySQL password (generate a strong one) | `use openssl rand -hex 16` |
| `DB_ROOT_PASS` | MySQL root password | `use openssl rand -hex 16` |

## Procedure

### Step 1: System packages

```bash
apt update && apt upgrade -y
apt install -y nginx mysql-server php8.1-fpm php8.1-mysql php8.1-mbstring php8.1-xml php8.1-curl php8.1-zip php8.1-bcmath php8.1-gd unzip curl
```

### Step 2: MySQL setup

```bash
mysql_secure_installation
```

Then create the database and user:

```bash
mysql -u root -p <<EOF
CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
```

### Step 3: Deploy application files

Upload the entire project directory to `/var/www/faka/`:

```bash
mkdir -p /var/www/faka
```

If using rsync from local machine:
```bash
rsync -avz --exclude '.git' --exclude '.opencode' ./ root@${VPS_IP}:/var/www/faka/
```

If cloning from a repo:
```bash
cd /var/www
git clone <REPO_URL> faka
```

Set permissions:
```bash
chown -R www-data:www-data /var/www/faka
chmod -R 755 /var/www/faka
chmod -R 775 /var/www/faka/config
chmod -R 775 /var/www/faka/app/Pay/Infini/Config
```

### Step 4: Configure database connection

Edit `/var/www/faka/config/database.php`:

```php
<?php
declare(strict_types=1);

return [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => '${DB_NAME}',
    'username' => '${DB_USER}',
    'password' => '${DB_PASS}',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => 'acg_',
];
```

### Step 5: Nginx configuration

Create `/etc/nginx/sites-available/faka`:

```nginx
server {
    listen 80;
    server_name ${DOMAIN};
    root /var/www/faka;
    index index.php index.html;

    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php?s=$1 last;
            break;
        }
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

Enable the site:
```bash
ln -sf /etc/nginx/sites-available/faka /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl restart nginx
```

### Step 6: Web installer

Open `http://${DOMAIN}` in a browser. The acg-faka installer will launch automatically and create the database tables.

After installation, the admin panel is at: `http://${DOMAIN}/admin`

### Step 7: SSL with Let's Encrypt

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d ${DOMAIN}
```

### Step 8: Enable Infini payment plugin

1. Log into admin panel at `https://${DOMAIN}/admin`
2. Go to 支付管理 (Payment Management)
3. Find the "Infini支付" plugin in the plugin list
4. Click configure and fill in:
   - **Key ID**: Your Infini public key (from https://business-sandbox.infini.money for test, https://business.infini.money for production)
   - **Secret Key**: Your Infini secret key
   - **运行环境**: `沙盒（测试）` for testing, `生产环境` for production
   - **支付方式**: `仅加密货币` (or `加密货币 + 卡支付`)
   - **订单有效期**: `1800` (30 minutes)
5. Save and enable the payment channel
6. In Infini merchant dashboard, configure the Webhook URL to: `https://${DOMAIN}/user/api/order/callback.Infini`

### Step 9: Configure callback domain

In acg-faka admin panel → 系统设置, set the callback domain to:
```
https://${DOMAIN}
```

This ensures payment webhooks are routed correctly.

## Verification checklist

- [ ] `systemctl status nginx` — active (running)
- [ ] `systemctl status php8.1-fpm` — active (running)
- [ ] `systemctl status mysql` — active (running)
- [ ] `curl -I https://${DOMAIN}` — returns 200 or 302
- [ ] Admin panel accessible at `https://${DOMAIN}/admin`
- [ ] Infini plugin appears in 支付管理 → 插件列表
- [ ] Place a test order → redirected to Infini checkout page

## Troubleshooting

| Problem | Solution |
|---------|----------|
| 502 Bad Gateway | Check `php8.1-fpm` is running: `systemctl restart php8.1-fpm` |
| 404 on all pages | Nginx rewrite rules not loaded: `nginx -t && systemctl reload nginx` |
| Database connection error | Verify credentials in `config/database.php`, check `mysql -u ${DB_USER} -p` |
| Infini plugin not showing | Confirm `app/Pay/Infini/Config/Info.php` and `Config/Submit.php` exist |
| Payment callback fails | Check `app/Pay/Infini/runtime.log` for error details |
| SSL certificate error | `certbot renew --dry-run` to test renewal |

## Maintenance

### Update acg-faka (built-in cloud update)

Use the admin panel's built-in update system: 登录后台 → 系统更新 → 检查更新 → 一键升级.

The Infini plugin lives in `app/Pay/Infini/` and will NOT be affected by system updates.

### Update Infini plugin manually

Replace files in `app/Pay/Infini/` only. Config values are preserved in `Config/Config.php`.

### Backup

```bash
# Database
mysqldump -u ${DB_USER} -p ${DB_NAME} > backup_$(date +%Y%m%d).sql

# Files (includes plugin config with keys)
tar czf faka_backup_$(date +%Y%m%d).tar.gz /var/www/faka/
```

### Log locations

| Log | Path |
|-----|------|
| Nginx access | `/var/log/nginx/access.log` |
| Nginx error | `/var/log/nginx/error.log` |
| PHP-FPM | `/var/log/php8.1-fpm.log` |
| Infini plugin | `/var/www/faka/app/Pay/Infini/runtime.log` |
| acg-faka system | `/var/www/faka/runtime/` |

## Hermes agent prompt

Copy the prompt below into Hermes agent directly.

```text
Deploy acg-faka with the Infini payment plugin from GitHub to my VPS.

Repository:
- REPO_URL = https://github.com/Guranta/faka.git

Requirements:
1. Install nginx, mysql-server, php8.1-fpm and required PHP extensions
2. Clone the repository to /var/www/faka
3. Configure nginx for acg-faka rewrite rules
4. Create a MySQL database and user
5. Update /var/www/faka/config/database.php with the database credentials
6. Set correct ownership and permissions for /var/www/faka
7. Start and enable nginx, mysql, and php8.1-fpm
8. If DOMAIN is blank, do not configure final SSL yet; tell me exactly where to fill the domain later
9. After deployment, tell me:
   - where to fill the domain
   - where to fill Infini Key ID / Secret Key
   - the webhook URL for Infini

Variables:
- DOMAIN = <leave blank for now if domain is not ready>
- DB_NAME = acgfaka
- DB_USER = acgfaka
- DB_PASS = generate on server if blank
- DB_ROOT_PASS = generate on server if blank

Verification:
- confirm nginx/php-fpm/mysql are running
- confirm the site is reachable by IP or temporary host
- confirm app/Pay/Infini exists
- confirm the admin panel path
```

### Values to fill later

- `DOMAIN`: your final domain name
- `DB_PASS`: let Hermes generate it on the VPS if you do not want to set it manually
- `DB_ROOT_PASS`: let Hermes generate it on the VPS if you do not want to set it manually
- `Infini Key ID` and `Infini Secret Key`: fill these in the acg-faka admin panel after deployment
