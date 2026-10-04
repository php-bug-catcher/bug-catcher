#!/usr/bin/env bash
#
# Proves that a project created from php-bug-catcher/skeleton is a working Bug Catcher against
# *this* working copy of the bundle.
#
# The bundle's own test suite boots tests/App/, a kernel written to exercise the bundle - it has its
# own bundles.php, its own security.yaml and its own api_platform mapping paths. None of that is
# what an installation has. Everything the bundle needs an application to configure is therefore
# invisible to phpunit and visible only here: a bundle registered, a firewall declared, a route
# imported, an icon committed, a column a migration has to create.
#
# Usage:
#   DATABASE_URL=mysql://root:secret@127.0.0.1:3306/bc_e2e tests/skeleton/e2e.sh /path/to/skeleton
#
# Environment:
#   DATABASE_URL        required; must be MySQL, with ONLY_FULL_GROUP_BY off on the server
#   APP_PORT            port the built-in server listens on (default 8099)
#   E2E_WORKDIR         where the project is built (default a fresh mktemp -d, removed on exit)
#   E2E_BUNDLE_VERSION  version the path repository claims (default <major of latest tag>.9999.0)
#   E2E_SKIP_YARN       set to 1 to skip the Encore build
#
set -euo pipefail

BUNDLE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SKELETON_DIR="${1:-${E2E_SKELETON_DIR:-}}"
PORT="${APP_PORT:-8099}"
BASE="http://127.0.0.1:${PORT}"
EMAIL="admin@example.com"
PASSWORD="e2e-p4ssword"
PROJECT_CODE="e2e"
MCP_TOKEN="e2e-mcp-token"

if [[ -z "${SKELETON_DIR}" || ! -d "${SKELETON_DIR}/.git" ]]; then
	echo "usage: $0 <path to a php-bug-catcher/skeleton checkout>" >&2
	exit 2
fi
if [[ -z "${DATABASE_URL:-}" ]]; then
	echo "DATABASE_URL is required and has to be MySQL" >&2
	exit 2
fi

SKELETON_DIR="$(cd "${SKELETON_DIR}" && pwd)"

KEEP_WORKDIR=1
if [[ -z "${E2E_WORKDIR:-}" ]]; then
	E2E_WORKDIR="$(mktemp -d)"
	KEEP_WORKDIR=0
fi
APP="${E2E_WORKDIR}"
SERVER_LOG="${APP}/server.log"
SERVER_PID=""

# ---------------------------------------------------------------------------- output and assertions

STEP=0
step() { STEP=$((STEP + 1)); printf '\n\033[1;36m==> %s. %s\033[0m\n' "${STEP}" "$*"; }
ok() { printf '    \033[0;32mok\033[0m %s\n' "$*"; }
die() {
	printf '\n\033[1;31mFAILED: %s\033[0m\n' "$*" >&2
	if [[ -s "${SERVER_LOG}" ]]; then
		printf '\n--- last 60 lines of the server log ---\n' >&2
		tail -n 60 "${SERVER_LOG}" >&2
	fi
	exit 1
}

cleanup() {
	if [[ -n "${SERVER_PID}" ]] && kill -0 "${SERVER_PID}" 2>/dev/null; then
		kill "${SERVER_PID}" 2>/dev/null || true
		wait "${SERVER_PID}" 2>/dev/null || true
	fi
	if [[ "${KEEP_WORKDIR}" == "0" ]]; then
		rm -rf "${E2E_WORKDIR}"
	else
		printf '\nworkdir kept at %s\n' "${E2E_WORKDIR}"
	fi
}
trap cleanup EXIT

console() { (cd "${APP}" && php bin/console "$@"); }

COOKIES=""
# http <expected status> <method> <path> [curl args...] -> body in $HTTP_BODY
HTTP_BODY=""
http() {
	local expected="$1" method="$2" path="$3"
	shift 3
	local body_file="${APP}/.http-body" status
	status="$(curl -sS -o "${body_file}" -w '%{http_code}' \
		-X "${method}" \
		${COOKIES:+-b "${COOKIES}" -c "${COOKIES}"} \
		"$@" "${BASE}${path}")" || die "curl ${method} ${path} failed"
	HTTP_BODY="$(cat "${body_file}")"
	if [[ "${status}" != "${expected}" ]]; then
		printf '%s\n' "${HTTP_BODY}" | head -n 40 >&2
		die "${method} ${path} answered ${status}, expected ${expected}"
	fi
	ok "${method} ${path} -> ${status}"
}

body_has() {
	grep -qF -- "$1" <<<"${HTTP_BODY}" || die "response does not contain: $1"
	ok "body contains: $1"
}

# ---------------------------------------------------------------------- 1. materialise the project

step "Materialise the skeleton into ${APP}"
mkdir -p "${APP}"
# Tracked files only, which is exactly what `composer create-project` hands over - a config file
# nobody committed has to fail here rather than pass because it sits in the author's worktree.
git -C "${SKELETON_DIR}" ls-files -z \
	| tar -C "${SKELETON_DIR}" --null -T - -cf - \
	| tar -x -C "${APP}"
[[ -f "${APP}/composer.json" ]] || die "no composer.json came out of ${SKELETON_DIR}"
mkdir -p "${APP}/src/Command"
cp "${BUNDLE_DIR}/tests/skeleton/E2eSeedCommand.php" "${APP}/src/Command/E2eSeedCommand.php"
ok "$(git -C "${SKELETON_DIR}" ls-files | wc -l) tracked files, plus the seed command"

step "Write .env.local"
# prod on purpose: dev hides a missing icon behind an on-demand download, hides a missing asset
# behind strict_mode off, and never builds the container the way a deployment does.
cat >"${APP}/.env.local" <<ENV
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=e2e-not-a-secret
DATABASE_URL="${DATABASE_URL}"
MCP_ACCESS_TOKEN=${MCP_TOKEN}
MCP_ALLOWED_HOSTS=127.0.0.1,localhost
ENV
export APP_ENV=prod APP_DEBUG=0
ok "prod, against ${DATABASE_URL%%\?*}"

step "composer validate"
# Before the path repository is added, or the lock is stale by construction. `create-project` installs
# from the lock, so a lock that disagrees with composer.json is a broken release of the skeleton.
composer --working-dir="${APP}" validate --no-check-all --no-check-publish \
	|| die "the skeleton's composer.json and composer.lock do not agree"

step "Point the project at this working copy of the bundle"
if [[ -z "${E2E_BUNDLE_VERSION:-}" ]]; then
	major="$(git -C "${BUNDLE_DIR}" tag --sort=-v:refname | sed -n 's/^v\?\([0-9]\+\)\..*/\1/p' | head -n 1)"
	E2E_BUNDLE_VERSION="${major:-2}.9999.0"
fi
# A path repository without `versions` reports dev-<branch>, which the skeleton's `^2.0` refuses -
# and refusing it is right, so the repository names a version inside the released range instead.
composer --working-dir="${APP}" config repositories.bug_catcher --json \
	"{\"type\":\"path\",\"url\":\"${BUNDLE_DIR}\",\"options\":{\"symlink\":true,\"versions\":{\"php-bug-catcher/bug-catcher\":\"${E2E_BUNDLE_VERSION}\"}}}"
ok "php-bug-catcher/bug-catcher ${E2E_BUNDLE_VERSION} from ${BUNDLE_DIR}"

step "Install"
# Only the bundle is updated: everything else stays on the committed lock, so this run tests the
# dependency set the skeleton actually ships, not whatever packagist released this morning.
composer --working-dir="${APP}" update php-bug-catcher/bug-catcher \
	--with-all-dependencies --no-interaction --no-progress --prefer-dist \
	|| die "composer update failed"

# ------------------------------------------------------------------------------ 2. static checks

step "Every icon the dashboard renders is committed in the skeleton"
missing=()
while read -r icon; do
	[[ -n "${icon}" ]] || continue
	if [[ ! -f "${APP}/assets/icons/${icon%%:*}/${icon#*:}.svg" ]]; then
		missing+=("${icon}")
	fi
done < <(php "${BUNDLE_DIR}/tests/skeleton/required-icons.php")
if ((${#missing[@]})); then
	printf 'run this in the skeleton and commit assets/icons:\n\n  php bin/console ux:icons:import %s\n\n' "${missing[*]}" >&2
	die "${#missing[@]} icon(s) the bundle renders are not in the skeleton"
fi
ok "all present under assets/icons/"

if [[ "${E2E_SKIP_YARN:-}" != "1" ]]; then
	step "Build the frontend"
	(cd "${APP}" && yarn install --no-progress --non-interactive >/dev/null) || die "yarn install failed"
	(cd "${APP}" && yarn build >/dev/null) || die "yarn build failed"
	[[ -f "${APP}/public/build/entrypoints.json" ]] || die "no public/build/entrypoints.json after yarn build"
	ok "public/build is there"
fi

# ----------------------------------------------------------------------------------- 3. the schema

step "Create the database"
console doctrine:database:create --if-not-exists --no-interaction || die "doctrine:database:create failed"
ok "created"

step "Generate and run the migration"
# The documented first-run flow (see the skeleton README): the bundle ships mapping, not migrations,
# so the installation diffs its own. A diff that comes out empty against an empty database means the
# bundle's entities were not found at all.
console doctrine:migrations:diff --no-interaction --formatted \
	|| die "doctrine:migrations:diff found nothing to create"
console doctrine:migrations:migrate --no-interaction --allow-no-migration \
	|| die "doctrine:migrations:migrate failed"
ok "migrated"

step "The schema matches the mapping"
console doctrine:schema:validate --no-interaction || die "doctrine:schema:validate is not clean after migrating"

step "The ingest endpoints are routed"
routes="$(console debug:router --no-interaction)" || die "debug:router failed"
for route in /api/record_logs /api/record_log_traces /api/perf_buckets /performance /mcp; do
	grep -qF -- "${route}" <<<"${routes}" || die "no route for ${route} - is api_platform.mapping.paths missing the bundle?"
	ok "${route}"
done

step "Create the admin user and the project"
console app:create-user "${EMAIL}" "${PASSWORD}" || die "app:create-user failed"
PROJECT_ID="$(console e2e:seed "${EMAIL}" "${PROJECT_CODE}" "${BASE}/login" | tail -n 1 | tr -d '\r')"
[[ -n "${PROJECT_ID}" ]] || die "e2e:seed printed no project id"
ok "project ${PROJECT_CODE} (${PROJECT_ID})"

# ----------------------------------------------------------------------------------- 4. serve it

step "Start the built-in server on ${PORT}"
cat >"${APP}/router.php" <<'ROUTER'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if ('/' !== $path && is_file(__DIR__.'/public'.$path)) {
	return false; // the docroot is public/, let the built-in server serve the file
}
require __DIR__.'/public/index.php';
ROUTER
(cd "${APP}" && php -S "127.0.0.1:${PORT}" -t public router.php >"${SERVER_LOG}" 2>&1) &
SERVER_PID=$!
for _ in $(seq 1 60); do
	if curl -sS -o /dev/null "${BASE}/login" 2>/dev/null; then break; fi
	kill -0 "${SERVER_PID}" 2>/dev/null || die "the server died on startup"
	sleep 0.5
done
ok "listening"

# --------------------------------------------------------------------------------- 5. the API

step "Ingest a log over the API"
http 201 POST /api/record_logs \
	-H 'Content-Type: application/json' \
	-d "{\"level\":500,\"message\":\"e2e plain log\",\"requestUri\":\"/checkout\",\"projectCode\":\"${PROJECT_CODE}\"}"

step "Ingest a log with a stack trace"
http 201 POST /api/record_log_traces \
	-H 'Content-Type: application/json' \
	-d "{\"level\":500,\"message\":\"e2e trace log\",\"requestUri\":\"/cart\",\"stackTrace\":\"#0 /app/src/Foo.php(13): bar()\",\"projectCode\":\"${PROJECT_CODE}\"}"

step "An unknown project code is refused"
http 404 POST /api/record_logs \
	-H 'Content-Type: application/json' \
	-d '{"level":500,"message":"nope","requestUri":"/","projectCode":"no-such-project"}'

step "Ingest a minute of performance buckets"
PERF_PAYLOAD="$(php -r '
$h = array_fill(0, 16, 0); $h[8] = 12;
echo json_encode(["projectCode" => $argv[1], "rows" => [[
	"bucketAt" => gmdate("Y-m-d\TH:i:00\Z", time() - 120),
	"serverName" => "e2e-web-01", "host" => "127.0.0.1", "path" => "/user/{id}",
	"hits" => 12, "sumDuration" => 7.2, "sumUser" => 3.1, "sumSys" => 0.4,
	"maxDuration" => 1.9, "sumMem" => 12582912, "maxMem" => 2097152,
	"clientErrors" => 1, "serverErrors" => 1, "histogram" => $h,
	"extra" => ["sq" => 48, "st" => 1.2],
]]]);' "${PROJECT_CODE}")"
http 204 POST /api/perf_buckets -H 'Content-Type: application/json' -d "${PERF_PAYLOAD}"

# ------------------------------------------------------------------------- 6. the pages, signed in

step "The login page renders"
COOKIES="${APP}/cookies.txt"
: >"${COOKIES}"
http 200 GET /login
body_has 'name="_username"'
body_has 'name="_csrf_token"'

step "Log in"
CSRF="$(sed -n 's/.*name="_csrf_token" value="\([^"]*\)".*/\1/p' <<<"${HTTP_BODY}" | head -n 1)"
[[ -n "${CSRF}" ]] || die "no CSRF token on the login page"
http 302 POST /login \
	--data-urlencode "_username=${EMAIL}" \
	--data-urlencode "_password=${PASSWORD}" \
	--data-urlencode "_csrf_token=${CSRF}" \
	--data-urlencode "_target_path=/"
ok "redirected"

step "The dashboard shows the project and both records"
http 200 GET /
body_has 'E2E'
body_has 'e2e plain log'
body_has 'e2e trace log'

step "The record detail page renders"
RECORD_ID="$(grep -oE '/detail/[0-9a-f-]{36}' <<<"${HTTP_BODY}" | head -n 1 | cut -d/ -f3)"
[[ -n "${RECORD_ID}" ]] || die "no /detail/<id> link on the dashboard"
http 200 GET "/detail/${RECORD_ID}"

step "The performance page renders, for every project and for one"
http 200 GET /performance
http 200 GET "/performance/${PROJECT_ID}"

step "The admin renders"
http 200 GET /admin
http 200 GET /change-password

step "The API docs render"
http 200 GET /api -H 'Accept: text/html'

# --------------------------------------------------------------------------------- 7. the MCP server

step "The MCP endpoint refuses a request without the token"
MCP_INIT='{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"e2e","version":"1"}}}'
SAVED_COOKIES="${COOKIES}"
COOKIES=""
http 401 POST /mcp -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' -d "${MCP_INIT}"

step "The MCP endpoint answers initialize with the token"
http 200 POST /mcp \
	-H "Authorization: Bearer ${MCP_TOKEN}" \
	-H 'Content-Type: application/json' \
	-H 'Accept: application/json, text/event-stream' \
	-d "${MCP_INIT}"
body_has 'bug-catcher'

step "The MCP tools are registered"
http 200 POST /mcp \
	-H "Authorization: Bearer ${MCP_TOKEN}" \
	-H 'Content-Type: application/json' \
	-H 'Accept: application/json, text/event-stream' \
	-d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
for tool in list_projects search_records get_record_detail set_record_status; do
	body_has "${tool}"
done
COOKIES="${SAVED_COOKIES}"

# ------------------------------------------------------------------------------- 8. the cron commands

step "Every command the README puts in cron runs"
console app:ping-collector || die "app:ping-collector failed"
ok "app:ping-collector"
console app:record-optimizer --past=1 --precision=5 || die "app:record-optimizer failed"
ok "app:record-optimizer"
console app:perf:rollup --granularity=hour || die "app:perf:rollup --granularity=hour failed"
ok "app:perf:rollup hour"
console app:perf:rollup --granularity=day || die "app:perf:rollup --granularity=day failed"
ok "app:perf:rollup day"
console app:perf:detect --window=5 || die "app:perf:detect failed"
ok "app:perf:detect"
console app:perf:purge --dry-run || die "app:perf:purge failed"
ok "app:perf:purge"

step "The dashboard still renders after the roll-up"
http 200 GET /
http 200 GET /performance

step "Log out"
http 302 GET /logout
ok "redirected"

# --------------------------------------------------------------------------------- 9. nothing blew up

step "Nothing in the server log is a crash"
if grep -nE 'PHP Fatal error|Uncaught [A-Za-z\\]*(Error|Exception)' "${SERVER_LOG}"; then
	die "the server logged a fatal error"
fi
ok "clean"

printf '\n\033[1;32mE2E passed: a project built from the skeleton runs this bundle.\033[0m\n'
