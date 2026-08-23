# Hosting

What you deploy is **two directories** — `wp-content/themes/bhc-theme` and
`wp-content/plugins/bhc-commerce-core` — into a normal WordPress + WooCommerce
install. Everything else in this repository is scaffolding for building and
testing them.

That matters when you pick a host: you are not hosting a bespoke application,
you are hosting WordPress. Anything that runs WordPress well runs this.

---

## Requirements

| | Minimum | Recommended |
|---|---|---|
| PHP | 8.2 | 8.3 |
| Extensions | `gd`, `mbstring`, `intl`, `curl`, `zip`, `dom`, `xml` | plus `redis`, `opcache`, `imagick` |
| MySQL | 8.0 | 8.0 |
| MariaDB | 10.6 | 10.11 |
| WordPress | 6.5 | latest |
| WooCommerce | 8.0 | latest |
| Memory | 256 MB | 512 MB |
| Object cache | none required | Redis 6+ |

The plugin refuses to load below PHP 8.2 or without WooCommerce, with an admin
notice rather than a fatal. Those two floors are the plugin header's
`Requires PHP: 8.2` and `WC requires at least: 8.0`.

**Verified here:** WordPress 7.0.4, WooCommerce 10.9.0, PHP 8.4.19, MariaDB
10.11.14, Redis 7.0.15. The full suite is green on that stack.

### One thing to check in the PHP build: WebP

The theme maps JPEG sub-sizes to WebP with an `image_editor_output_format`
filter (`bhc_webp_subsizes()` in `wp-content/themes/bhc-theme/inc/performance.php`).
Only the *derivatives* change format — the original upload is kept as
uploaded, and PNG is left alone.

That needs an image editor that can actually write WebP: `gd` compiled with
WebP support, or Imagick. If it cannot, WordPress ignores the filter and keeps
producing JPEGs. Nothing breaks; the pages just get heavier. Measured on the
600×600 card size: 16,599 bytes as JPEG against 6,278 as WebP, 62% smaller, and
a 12-card shop page pulls roughly 83KB of imagery in total. Details in
[performance.md](performance.md).

The cost is disk: the original and its WebP sub-sizes both sit in `uploads/`,
so back-up size goes up, not down.

---

## Choosing a host

### Managed WordPress hosting — the default answer

Kinsta, WP Engine, Pressable, Cloudways, SiteGround and similar. You get PHP,
MySQL, Redis, backups, TLS, staging and a CDN without configuring any of it.

**Pick this unless you have a specific reason not to.** A store that takes money
should not also be your first sysadmin project — the failure mode is losing
orders at 2am.

Check before buying:

- **PHP 8.2+ selectable.** Some hosts still default to 8.1.
- **Redis object cache included**, not a paid add-on. This is worth more to this
  build than any other single setting — see [Measured](#measured) below.
- **WP-CLI over SSH.** Without it you cannot run `wp bhc products sync`,
  `wp bhc health-check`, or a demo seed.
- **Real cron**, not WordPress pseudo-cron. Action Scheduler needs it.
- **Staging with a one-click copy**, so you can rehearse a deploy.

### VPS — full control, full responsibility

Hetzner, DigitalOcean, Linode, Vultr. Cheaper at scale and you own the stack.
Budget real hours for OS patching, TLS renewal, backup verification and
monitoring. Full walkthrough below.

### Docker — reproducible, good for teams

`deploy/docker-compose.yml` brings up MariaDB, Redis, PHP-FPM, nginx and a real
cron worker, with the theme and plugin bind-mounted so edits are live.

```bash
cp deploy/.env.example deploy/.env      # then edit it
docker compose -f deploy/docker-compose.yml up -d
docker compose -f deploy/docker-compose.yml exec wordpress \
  bash /opt/bhc/bin/setup-demo.sh /var/www/html
```

The repository root is mounted read-only at `/opt/bhc` inside the `wordpress`
container, which is why `bin/setup-demo.sh` is reachable at that path.

> **Not verified.** The compose file and nginx config were written against the
> documented behaviour of those images but could not be executed in the
> authoring environment, which has no Docker daemon. Everything else in this
> document was run. Treat `deploy/` as a reviewed starting point, not a tested
> artefact.

### Shared cPanel hosting — workable, with caveats

It will run. You will usually not get Redis, often not WP-CLI, and rarely
control over PHP-FPM. Expect query counts closer to the no-object-cache column
below than to the Redis one.

---

## Path A: managed host

1. **Provision** the site and set PHP to 8.2+.
2. **Install WooCommerce** from the plugin directory.
3. **Upload the two directories.** Either drag them into
   `wp-content/themes/` and `wp-content/plugins/` over SFTP, or — better — have
   the host pull from git. If you upload the plugin manually, run
   `composer install --no-dev --optimize-autoloader` inside it first, or bundle
   `vendor/`; the plugin has a fallback autoloader but Composer's is faster.
4. **Build the CSS** before uploading: `npm ci && npm run build`. The compiled
   stylesheets are committed, so this only matters if you changed the SCSS.
5. **Activate** the theme, then the plugin.
6. **Turn on Redis** in the host's control panel.
7. **Verify**: `wp bhc health-check --strict` should exit 0.
8. **Seed** if you want the demo catalogue: `wp bhc demo seed`. That also
   enables two offline payment gateways so checkout is demonstrable — see
   [Going live](#going-live) before this is public. Skip the seed entirely for a
   real store and import your own products.

## Path B: VPS from scratch

Ubuntu 24.04, `bonehorncrafts.com`. Adjust names as needed.

### 1. Packages

```bash
apt update && apt upgrade -y
apt install -y nginx mariadb-server redis-server certbot python3-certbot-nginx \
  php8.3-fpm php8.3-mysql php8.3-gd php8.3-mbstring php8.3-intl \
  php8.3-curl php8.3-zip php8.3-xml php8.3-redis php8.3-opcache \
  git unzip curl

curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp
```

Ubuntu's `php8.3-gd` is built with WebP support, so the WebP sub-sizes described
above will be written. Confirm with
`php -r 'var_dump( gd_info()["WebP Support"] );'` if you are on another distro.

### 2. Database

```bash
mysql_secure_installation

mysql -uroot -p <<'SQL'
CREATE DATABASE bhc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
CREATE USER 'bhc'@'localhost' IDENTIFIED BY 'use-a-long-random-password';
GRANT ALL PRIVILEGES ON bhc.* TO 'bhc'@'localhost';
FLUSH PRIVILEGES;
SQL
```

`utf8mb4` is not optional. A `latin1` column mangles accented product names and
every emoji in a review, silently, on insert.

### 3. PHP

`/etc/php/8.3/fpm/conf.d/99-bhc.ini`:

```ini
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120

opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0     ; reset OPcache on deploy, see below
opcache.jit = tracing
opcache.jit_buffer_size = 64M

expose_php = Off
display_errors = Off
log_errors = On
```

`opcache.validate_timestamps = 0` means PHP never checks whether a file changed
— fast, and it means **your deploy must reset OPcache** or the old code keeps
running. `systemctl reload php8.3-fpm` does it.

### 4. WordPress

```bash
mkdir -p /var/www/bonehorncrafts && cd /var/www/bonehorncrafts
wp core download
wp config create --dbname=bhc --dbuser=bhc --dbpass='...' --dbhost=localhost
wp config shuffle-salts
wp core install --url=https://www.bonehorncrafts.com --title="Bone Horn Crafts" \
  --admin_user=... --admin_password=... --admin_email=...

wp config set WP_ENVIRONMENT_TYPE production
wp config set WP_DEBUG false --raw
wp config set DISALLOW_FILE_EDIT true --raw
wp config set DISABLE_WP_CRON true --raw
wp config set WP_MEMORY_LIMIT 256M
wp config set FORCE_SSL_ADMIN true --raw

chown -R www-data:www-data /var/www/bonehorncrafts
find /var/www/bonehorncrafts -type d -exec chmod 755 {} \;
find /var/www/bonehorncrafts -type f -exec chmod 644 {} \;
chmod 600 /var/www/bonehorncrafts/wp-config.php
```

Note `WP_ENVIRONMENT_TYPE production` here. `bin/setup-demo.sh` sets
`development` instead, which is right for a demo — that is what makes the plugin
rewrite absolute SEO URLs onto the configured canonical host — and wrong for the
real domain.

### 5. This build

```bash
cd /var/www/bonehorncrafts
wp plugin install woocommerce --activate

git clone <your-fork> /opt/bhc
ln -s /opt/bhc/wp-content/themes/bhc-theme  wp-content/themes/bhc-theme
ln -s /opt/bhc/wp-content/plugins/bhc-commerce-core wp-content/plugins/bhc-commerce-core

cd /opt/bhc/wp-content/plugins/bhc-commerce-core
composer install --no-dev --optimize-autoloader

cd /var/www/bonehorncrafts
wp theme activate bhc-theme
wp plugin activate bhc-commerce-core
```

Symlinks make deploys a `git pull` plus an OPcache reload. If your host or
security policy dislikes symlinks under the webroot, copy instead and rsync on
deploy.

### 6. Redis

```bash
wp plugin install redis-cache --activate
wp config set WP_REDIS_HOST 127.0.0.1
wp config set WP_REDIS_PORT 6379 --raw
wp config set WP_CACHE_KEY_SALT bonehorncrafts
wp redis enable
wp bhc health-check          # should say: Active — Redis
```

The health report names Redis specifically when Redis is what is serving, and
says `Active. Not Redis.` for another persistent backend, so this line is a real
check rather than a guess.

In `/etc/redis/redis.conf`:

```
maxmemory 512mb
maxmemory-policy allkeys-lru
```

`allkeys-lru` matters. The default `noeviction` makes Redis *refuse writes* when
full, and WordPress will keep asking — you get a store that gets slower under
exactly the load you bought Redis for.

The key salt matters too: cache keys are laid out as
`bhc:{schema}:{group}:v{version}:{key}`, and two installs sharing one Redis
without distinct salts will read each other's entries.

### 7. nginx and TLS

```bash
cp /opt/bhc/deploy/nginx.conf /etc/nginx/sites-available/bonehorncrafts
```

Change both `fastcgi_pass wordpress:9000` lines to
`fastcgi_pass unix:/run/php/php8.3-fpm.sock`, set `server_name`, set `root`.

Add to the `http {}` block in `/etc/nginx/nginx.conf` (the vhost references it):

```nginx
limit_req_zone $binary_remote_addr zone=bhc_login:10m rate=20r/m;
```

Then:

```bash
ln -s /etc/nginx/sites-available/bonehorncrafts /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
certbot --nginx -d bonehorncrafts.com -d www.bonehorncrafts.com
```

Certbot writes the `listen 443` block and renews automatically. Confirm the
renewal timer actually exists: `systemctl list-timers | grep certbot`.

### 8. Cron

```bash
crontab -u www-data -e
```

```cron
* * * * * cd /var/www/bonehorncrafts && /usr/local/bin/wp cron event run --due-now >/dev/null 2>&1
```

This is what runs Action Scheduler. Without it the merchandising index never
rebuilds and bestsellers quietly freeze.

### 9. Backups

```bash
# /etc/cron.daily/bhc-backup
#!/bin/sh
set -e
DEST=/var/backups/bhc/$(date +%F)
mkdir -p "$DEST"
cd /var/www/bonehorncrafts
/usr/local/bin/wp db export "$DEST/db.sql" --allow-root
tar czf "$DEST/uploads.tgz" wp-content/uploads
find /var/backups/bhc -maxdepth 1 -type d -mtime +30 -exec rm -rf {} +
```

Copy them off the box — a backup on the same disk is not a backup. And restore
one, once, before you need to.

---

## Moving this demo to a host

Only if you want the demo content on a public URL. For a real store, seed
nothing and import your own catalogue.

```bash
# On the machine running the demo
wp --path=~/wp-demo db export bhc.sql
tar czf uploads.tgz -C ~/wp-demo/wp-content uploads

# On the server
wp db import bhc.sql
tar xzf uploads.tgz -C /var/www/bonehorncrafts/wp-content/

# Rewrite the URLs. --precise walks serialized data correctly; a plain
# find-and-replace corrupts every serialized array it touches.
wp search-replace 'http://localhost:8088' 'https://www.bonehorncrafts.com' --precise --all-tables
wp cache flush
wp bhc cache flush
wp rewrite flush --hard
wp bhc products sync
```

Then set `WP_ENVIRONMENT_TYPE` to `production` and check that canonicals and
JSON-LD point at the real domain — on a production environment the plugin uses
`home_url()`, so the site must actually be reachable at the canonical host.

---

## Measured

Queries per page, warm, same catalogue, `SAVEQUERIES` on, in the authoring
environment:

| Page | SQLite, no object cache | MySQL + Redis |
|---|---:|---:|
| Home | 131 | **6** |
| Shop | 83 | **5** |
| Product | 116 | **5** |
| Category | 90 | **5** |
| Cart | 76 | **5** |
| Blog | 66 | **5** |

Read the columns for what they are: two variables move at once. The left is the
SQLite build with no persistent object cache — the default if you do nothing —
and the right is MariaDB 10.11 with Redis 7. This is not an isolated measurement
of Redis against MySQL alone, and it is a count of queries, not wall time.

What it does show is that the uncached path does an order of magnitude more
database work on every page. A persistent object cache is worth more here than
every other hosting decision combined. If a host does not offer Redis, that is a
reason to change host.

What it costs: another service to keep running, patched and monitored, memory
you have to size, an eviction policy you have to set (see above), and a cache
key salt per install. On a VPS that is real work; on a managed host it is a
toggle.

The store works correctly without it — the cache abstraction falls back to
transients and the health check says so plainly — it is simply doing far more
database work per request.

---

## Going live

See [deployment.md](deployment.md) for the full checklist. The five that people
actually get wrong:

1. **`WP_ENVIRONMENT_TYPE=production`** and the site genuinely reachable at the
   canonical host, or your metadata advertises staging.
2. **Delete `wp-content/mu-plugins/bhc-sqlite-dev.php`** if it exists. It
   disables stock holds during checkout. It belongs only to the SQLite demo —
   `bin/setup-demo.sh` copies it there from `tools/dev-mu-plugins/`, and a MySQL
   build never gets it.
3. **Turn off the demo payment gateways.** A fresh WooCommerce install has every
   gateway disabled, so checkout fails with "Invalid payment method"; the seeder
   therefore enables WooCommerce's two offline gateways — Cash on delivery
   (`cod`), retitled `Pay on invoice (demo)`, and Bank transfer (`bacs`),
   retitled `Bank transfer (demo)`. Neither takes a payment. Disable both and
   configure a real gateway before you take an order. The seeder leaves alone
   any gateway that is already enabled, so it will not have overwritten one you
   configured yourself.
4. **Reset the demo data** — `wp bhc demo reset --yes --orphans` — before
   importing a real catalogue, and change the admin password.
5. **Confirm cron is firing.** Load WooCommerce → Status → Scheduled Actions
   a day after launch and check that actions are completing, not queueing.
