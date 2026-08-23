#!/usr/bin/env bash
#
# Builds a local Bone Horn Crafts demo store from nothing.
#
# Downloads WordPress and WooCommerce, configures a database, links this
# repository's theme and plugin into the install, builds the assets and seeds
# the demo catalogue. Safe to re-run: every step is skipped when it is already
# done, and the seeder itself is idempotent.
#
# Usage:
#   bin/setup-demo.sh [install-dir]
#
# Environment:
#   WP_VERSION   WordPress version to install      (default 7.0.4)
#   WC_VERSION   WooCommerce version to install    (default 10.9.0)
#   SITE_URL     Site URL                          (default http://localhost:8088)
#   ADMIN_USER   Admin username                    (default admin)
#   ADMIN_PASS   Admin password                    (default admin)
#   ADMIN_EMAIL  Admin e-mail                      (default admin@bonehorncrafts.test)
#   DB_NAME/DB_USER/DB_PASSWORD/DB_HOST
#                MySQL connection. When DB_HOST is unset the script installs the
#                SQLite integration plugin instead, so no database server is
#                needed.
#   REDIS_HOST/REDIS_PORT
#                Set REDIS_HOST to install the Redis object cache drop-in and
#                enable it. Optional; the store runs without one.
#
# Requires: php >= 8.2 (with gd), wp-cli, composer, node >= 18, curl, unzip.

set -euo pipefail

REPO_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
INSTALL_DIR="${1:-${HOME}/wp-demo}"

WP_VERSION="${WP_VERSION:-7.0.4}"
WC_VERSION="${WC_VERSION:-10.9.0}"
SITE_URL="${SITE_URL:-http://localhost:8088}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@bonehorncrafts.test}"

WP="wp --path=${INSTALL_DIR} --allow-root"

say() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }

require() {
	command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1" >&2; exit 1; }
}

for cmd in php curl unzip; do require "$cmd"; done
require wp
require composer
require node

php -r 'exit( version_compare( PHP_VERSION, "8.2", ">=" ) ? 0 : 1 );' \
	|| { echo "PHP 8.2 or newer is required (found $(php -r 'echo PHP_VERSION;'))." >&2; exit 1; }

php -r 'exit( extension_loaded( "gd" ) ? 0 : 1 );' \
	|| echo "WARNING: the gd extension is missing; demo product imagery will be skipped."

# ---------------------------------------------------------------------------
say "WordPress ${WP_VERSION} -> ${INSTALL_DIR}"

mkdir -p "${INSTALL_DIR}"

if [ ! -f "${INSTALL_DIR}/wp-settings.php" ]; then
	if ! $WP core download --version="${WP_VERSION}" --force 2>/dev/null; then
		# wordpress.org is not always reachable from CI runners; the release
		# tags on the WordPress/WordPress mirror carry identical trees.
		echo "wordpress.org unreachable, falling back to the GitHub mirror."
		tmp="$( mktemp -d )"
		git clone --depth 1 --branch "${WP_VERSION}" https://github.com/WordPress/WordPress.git "${tmp}/wp"
		rm -rf "${tmp}/wp/.git"
		cp -R "${tmp}/wp/." "${INSTALL_DIR}/"
		rm -rf "${tmp}"
	fi
else
	echo "Already present, skipping download."
fi

# ---------------------------------------------------------------------------
say "Database configuration"

if [ ! -f "${INSTALL_DIR}/wp-config.php" ]; then
	$WP config create \
		--dbname="${DB_NAME:-bhc_demo}" \
		--dbuser="${DB_USER:-root}" \
		--dbpass="${DB_PASSWORD:-}" \
		--dbhost="${DB_HOST:-127.0.0.1}" \
		--skip-check
else
	echo "wp-config.php already present, keeping its database settings."
fi

# Set outside the branch above, deliberately. These used to be written only when
# the script created wp-config.php itself, so pointing it at a WordPress that
# already existed — a Local site, a MAMP site, a host's one-click install — left
# every one of them unset. The store then ran as "production" on a laptop, and
# WP_ENVIRONMENT_TYPE is what tells BrandProfile to rewrite absolute SEO URLs
# onto the canonical host: on production home_url() is already correct, so the
# schema and Open Graph tags came out full of localhost URLs.
#
# `wp config set` overwrites in place, so re-running is harmless.
$WP config set WP_DEBUG true --raw
$WP config set WP_DEBUG_DISPLAY false --raw
$WP config set WP_DEBUG_LOG true --raw
$WP config set SCRIPT_DEBUG true --raw
$WP config set WP_ENVIRONMENT_TYPE development

if [ -z "${DB_HOST:-}" ] && [ ! -f "${INSTALL_DIR}/wp-content/db.php" ]; then
	say "No DB_HOST set — installing the SQLite integration plugin"

	sqlite_dir="${INSTALL_DIR}/wp-content/plugins/sqlite-database-integration"

	if [ ! -d "${sqlite_dir}" ]; then
		git clone --depth 1 https://github.com/WordPress/sqlite-database-integration.git "${sqlite_dir}"

		if [ -d "${sqlite_dir}/packages/plugin-sqlite-database-integration" ]; then
			# The repository is now a monorepo; the shippable plugin lives under
			# packages/ and is what a wordpress.org download would contain.
			cp -R "${sqlite_dir}/packages/plugin-sqlite-database-integration/." "${sqlite_dir}/"
		fi
	fi

	# In the monorepo, wp-includes/database is a symlink into a sibling package
	# that does not survive being copied to the plugin root. Replace it with the
	# real tree, wherever that tree turns out to live.
	for candidate in \
		"${sqlite_dir}/packages/mysql-on-sqlite/src" \
		"${sqlite_dir}/mysql-on-sqlite/src"
	do
		if [ -d "${candidate}" ] && [ ! -f "${sqlite_dir}/wp-includes/database/version.php" ]; then
			rm -rf "${sqlite_dir}/wp-includes/database"
			mkdir -p "${sqlite_dir}/wp-includes"
			cp -R "${candidate}" "${sqlite_dir}/wp-includes/database"
			break
		fi
	done

	if [ ! -f "${sqlite_dir}/wp-includes/database/version.php" ]; then
		echo "Could not assemble the SQLite implementation folder." >&2
		exit 1
	fi

	cp "${sqlite_dir}/db.copy" "${INSTALL_DIR}/wp-content/db.php" 2>/dev/null || {
		cat > "${INSTALL_DIR}/wp-content/db.php" <<'PHPDROPIN'
<?php
/**
 * SQLite drop-in generated by bin/setup-demo.sh.
 */
define( 'DB_ENGINE', 'sqlite' );
require_once WP_CONTENT_DIR . '/plugins/sqlite-database-integration/load.php';
PHPDROPIN
	}
	sed -i "s|{SQLITE_IMPLEMENTATION_FOLDER_PATH}|${sqlite_dir}|g; s|{SQLITE_PLUGIN}|sqlite-database-integration/load.php|g" "${INSTALL_DIR}/wp-content/db.php"
fi

# ---------------------------------------------------------------------------
say "Installing WordPress"

# On the MySQL path the database has to exist before `core install` can write to
# it, and the error it gives when it does not ("Error establishing a database
# connection") reads like bad credentials rather than a missing schema. Creating
# it here makes the documented one-liner work against a bare server. The user
# still needs CREATE rights; if they do not have them, the message below says so
# in those words rather than leaving it to be inferred two steps later.
#
# Skipped entirely on the SQLite path, where there is no server to ask.
if [ -n "${DB_HOST:-}" ] && ! $WP db check >/dev/null 2>&1; then
	if ! $WP db create 2>/dev/null; then
		echo "NOTE: could not create the database automatically."
		echo "      Create it yourself and re-run, e.g.:"
		echo "        mysql -h ${DB_HOST} -u <admin-user> -p \\"
		echo "          -e \"CREATE DATABASE ${DB_NAME:-bhc_demo};"
		echo "              GRANT ALL ON ${DB_NAME:-bhc_demo}.* TO '${DB_USER:-root}'@'${DB_HOST}';\""
	fi
fi

if ! $WP core is-installed 2>/dev/null; then
	$WP core install \
		--url="${SITE_URL}" \
		--title="Bone Horn Crafts" \
		--admin_user="${ADMIN_USER}" \
		--admin_password="${ADMIN_PASS}" \
		--admin_email="${ADMIN_EMAIL}" \
		--skip-email
else
	echo "Already installed, skipping."
fi

# ---------------------------------------------------------------------------
say "WooCommerce ${WC_VERSION}"

if [ ! -d "${INSTALL_DIR}/wp-content/plugins/woocommerce" ]; then
	if ! $WP plugin install woocommerce --version="${WC_VERSION}" 2>/dev/null; then
		echo "wordpress.org unreachable, fetching the GitHub release asset."
		tmp="$( mktemp -d )"
		curl -fsSL -o "${tmp}/woocommerce.zip" \
			"https://github.com/woocommerce/woocommerce/releases/download/${WC_VERSION}/woocommerce.zip"
		unzip -q "${tmp}/woocommerce.zip" -d "${INSTALL_DIR}/wp-content/plugins/"
		rm -rf "${tmp}"
	fi
fi

$WP plugin activate woocommerce

# ---------------------------------------------------------------------------
say "High-Performance Order Storage"

# HPOS is WooCommerce's order store: orders live in `wc_orders` rather than in
# `posts`/`postmeta`. It is enabled here, before any order is seeded, so the
# demo exercises the same storage a current WooCommerce install uses and the
# order screens the plugin hooks into are the HPOS ones. Left off, the store
# silently runs the legacy path and the HPOS branch of
# `Order\Admin\OrderOperationsMetaBox` is never executed.
#
# `wc hpos enable` refuses while unsynced legacy orders exist, so sync first.
# On a fresh install there is nothing to sync and it is a no-op; on a re-run
# over an existing store it migrates what is there. Both are safe.
$WP wc hpos sync || true
$WP wc hpos enable || echo "WARNING: could not enable HPOS; the store will use legacy order storage."

# ---------------------------------------------------------------------------
say "Linking the theme and plugin from ${REPO_DIR}"

link() {
	local target="$1" name="$2" dest="${INSTALL_DIR}/wp-content/$3/$2"
	[ -e "${dest}" ] || ln -s "${target}" "${dest}"
}

link "${REPO_DIR}/wp-content/plugins/bhc-commerce-core" bhc-commerce-core plugins
link "${REPO_DIR}/wp-content/themes/bhc-theme" bhc-theme themes

# ---------------------------------------------------------------------------
say "Building"

( cd "${REPO_DIR}/wp-content/plugins/bhc-commerce-core" && composer install --no-interaction --quiet )
( cd "${REPO_DIR}" && npm install --silent && npm run build --silent )

# ---------------------------------------------------------------------------
say "Activating"

$WP theme activate bhc-theme
$WP plugin activate bhc-commerce-core

# Router for `php -S`: serve real files, hand everything else to WordPress so
# pretty permalinks resolve.
cp "${REPO_DIR}/tools/router.php" "${INSTALL_DIR}/router.php"

if [ -z "${DB_HOST:-}" ]; then
	# SQLite cannot execute WooCommerce's INSERT ... FROM DUAL ... ON DUPLICATE
	# KEY UPDATE stock reservation query, which blocks checkout. The dev-only
	# mu-plugin turns hold-stock off through a supported filter. Never deploy it.
	mkdir -p "${INSTALL_DIR}/wp-content/mu-plugins"
	cp "${REPO_DIR}/tools/dev-mu-plugins/bhc-sqlite-dev.php" "${INSTALL_DIR}/wp-content/mu-plugins/"
fi

# ---------------------------------------------------------------------------
# Optional: wire up a persistent object cache. The plugin works without one and
# says so on its health screen, but Redis is what the caching abstraction was
# built for, and the difference is large enough to be worth seeing.
if [ -n "${REDIS_HOST:-}" ]; then
	say "Persistent object cache (Redis at ${REDIS_HOST}:${REDIS_PORT:-6379})"

	if ! php -r 'exit( extension_loaded( "redis" ) ? 0 : 1 );'; then
		echo "The PHP redis extension is not loaded; skipping." >&2
	else
		redis_dir="${INSTALL_DIR}/wp-content/plugins/redis-cache"

		[ -d "${redis_dir}" ] || git clone --depth 1 --quiet \
			https://github.com/rhubarbgroup/redis-cache.git "${redis_dir}"

		$WP config set WP_REDIS_HOST "${REDIS_HOST}"
		$WP config set WP_REDIS_PORT "${REDIS_PORT:-6379}" --raw
		# Namespaces the keys so two installs can share one Redis instance.
		$WP config set WP_CACHE_KEY_SALT "$( basename "${INSTALL_DIR}" )"

		$WP plugin activate redis-cache

		# Not fatal. The object cache is an optimisation the health screen
		# reports on honestly, and the store is fully functional without it —
		# but `set -e` used to turn an unreachable Redis into an abort here,
		# before any seeding, so a stopped service cost you the whole catalogue
		# rather than just the cache.
		if ! $WP redis enable; then
			echo "WARNING: could not enable the Redis object cache; continuing without it."
			echo "         Check the server is running, then re-run: $WP redis enable"
		fi
	fi
fi

# ---------------------------------------------------------------------------
say "Seeding the demo catalogue"

$WP bhc demo seed
$WP bhc products sync
$WP rewrite flush --hard

# ---------------------------------------------------------------------------
say "Done"

cat <<EOM

  Store:  ${SITE_URL}
  Admin:  ${SITE_URL}/wp-admin  (${ADMIN_USER} / ${ADMIN_PASS})

  Check it over:

    wp --path=${INSTALL_DIR} --allow-root bhc health-check

  Serve it with the built-in PHP server:

    PHP_CLI_SERVER_WORKERS=6 php -S ${SITE_URL#http://} -t ${INSTALL_DIR} ${INSTALL_DIR}/router.php

  (The worker pool matters: a single-process server queues the browser test
  suites behind themselves and they time out looking like real failures.)

EOM
