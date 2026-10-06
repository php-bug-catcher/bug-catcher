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
#   E2E_BUNDLE          "working-copy" (default) or "released" - the latter installs the skeleton's
#                       committed lock untouched, which is what `create-project` hands out today.
#                       This script grows an assertion with every feature, so a released run has to
#                       be judged by the contract of the version the lock pins: skeleton.yml checks
#                       `tests/skeleton` and `templates` out at that tag before running this leg,
#                       and a local released run is only honest if you do the same.
#   E2E_BUNDLE_VERSION  version the path repository claims (default from the skeleton's constraint)
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
	HTTP_HEADERS_FILE="${APP}/.http-headers"
	status="$(curl -sS -o "${body_file}" -D "${HTTP_HEADERS_FILE}" -w '%{http_code}' \
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

if [[ "${E2E_BUNDLE:-working-copy}" == "released" ]]; then
	step "Install the skeleton as published, from its own lock"
	# No path repository: this answers the other half of the question - does `create-project` work
	# *today*, with the version the committed composer.lock pins? A bundle fix that the skeleton
	# has not re-locked against is a broken installation however green the working-copy run is.
	composer --working-dir="${APP}" install --no-interaction --no-progress --prefer-dist \
		|| die "composer install from the committed lock failed"
	INSTALLED="$(php -r '
		foreach (json_decode(file_get_contents($argv[1]), true)["packages"] as $package) {
			if ("php-bug-catcher/bug-catcher" === $package["name"]) { echo $package["version"]; }
		}' "${APP}/composer.lock")"
	ok "php-bug-catcher/bug-catcher ${INSTALLED} from packagist"
else

step "Point the project at this working copy of the bundle"
if [[ -z "${E2E_BUNDLE_VERSION:-}" ]]; then
	# Read off the skeleton's own constraint rather than the bundle's git tags: a CI checkout is a
	# shallow one with no tags, and the number that has to be satisfied is the skeleton's anyway.
	E2E_BUNDLE_VERSION="$(php -r '
		$require = json_decode(file_get_contents($argv[1]), true)["require"] ?? [];
		$constraint = $require["php-bug-catcher/bug-catcher"] ?? "";
		preg_match("/(\d+)/", $constraint, $m) || exit(1);
		echo $m[1], ".9999.0";
	' "${APP}/composer.json")" || die "the skeleton does not require php-bug-catcher/bug-catcher"
fi
# A path repository without `versions` reports dev-<branch>, which the skeleton's `^2.0` refuses -
# and refusing it is right, so the repository names a version inside the released range instead.
composer --working-dir="${APP}" config repositories.bug_catcher --json \
	"{\"type\":\"path\",\"url\":\"${BUNDLE_DIR}\",\"options\":{\"symlink\":true,\"versions\":{\"php-bug-catcher/bug-catcher\":\"${E2E_BUNDLE_VERSION}\"}}}"
ok "php-bug-catcher/bug-catcher ${E2E_BUNDLE_VERSION} from ${BUNDLE_DIR}"

step "Install"
# Only the bundle is updated, and deliberately without --with-all-dependencies: everything else
# stays on the committed lock, so this run tests the dependency set `create-project` hands out
# rather than whatever packagist released this morning. A working copy whose requirements no longer
# fit that lock fails here, which is the skeleton saying it needs to be re-locked.
composer --working-dir="${APP}" update php-bug-catcher/bug-catcher \
	--no-interaction --no-progress --prefer-dist \
	|| die "composer update failed; the skeleton's lock may need regenerating against this bundle"

fi

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
if curl -sS -o /dev/null --max-time 2 "${BASE}/" 2>/dev/null; then
	die "something is already listening on ${PORT}; set APP_PORT"
fi
# Started without a wrapping subshell on purpose, so $! is php itself - killing a subshell leaves
# the server orphaned and the next run answers from a workdir that no longer exists.
php -S "127.0.0.1:${PORT}" -t "${APP}/public" "${APP}/router.php" >"${SERVER_LOG}" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 60); do
	if grep -q 'Failed to listen' "${SERVER_LOG}" 2>/dev/null; then
		die "the server could not bind ${PORT}"
	fi
	if curl -sS -o /dev/null "${BASE}/login" 2>/dev/null; then break; fi
	kill -0 "${SERVER_PID}" 2>/dev/null || die "the server died on startup"
	sleep 0.5
done
ok "listening (pid ${SERVER_PID})"

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

step "Ingest performance buckets"
# Two minutes: one a couple of minutes ago, which is what the dashboard row and the minute-grained
# charts read, and one inside the previous whole hour, which is the only thing app:perf:rollup
# --granularity=hour is going to find anything in.
#
# One instant for the whole run, rather than time() per call: the anchored-window step below asks
# for [at, at+1h) and has to name the minute these rows actually landed in. Recomputing "two
# minutes ago" down there put the window one minute late whenever the run crossed a minute
# boundary in between, and the row it was looking for fell outside it - a flake that hit roughly
# one leg in four and read like the path hash had changed.
PERF_NOW="$(php -r 'echo time();')"
PERF_AT="$(php -r 'echo gmdate("Y-m-d\TH:i", (int) $argv[1] - 120);' "${PERF_NOW}")"
PERF_PAYLOAD="$(php -r '
$now = (int) $argv[2];
$row = static function (int $at, int $hits, float $sumDuration, string $path = "/user/{id}", string $host = "127.0.0.1", int $errors = 1): array {
	$h = array_fill(0, 16, 0);
	$h[8] = $hits;
	return [
		"bucketAt" => gmdate("Y-m-d\TH:i:00\Z", $at),
		"serverName" => "e2e-web-01", "host" => $host, "path" => $path,
		"hits" => $hits, "sumDuration" => $sumDuration, "sumUser" => $sumDuration / 2,
		"sumSys" => 0.4, "maxDuration" => 1.9, "sumMem" => 1048576 * $hits,
		"maxMem" => 2097152, "clientErrors" => $errors, "serverErrors" => $errors,
		"histogram" => $h, "extra" => ["sq" => 4 * $hits, "st" => 0.1 * $hits],
	];
};
echo json_encode(["projectCode" => $argv[1], "rows" => [
	$row($now - 120, 12, 7.2),
	$row(strtotime(gmdate("Y-m-d H:00:00", $now - 3600)) + 600, 30, 18.5),
	// A console run, which is the one row shape phpunit cannot prove: `host` is empty, which has
	// to survive a unique key it is part of, and the path has a colon in it, which has to survive
	// the anchor round-trip. No error counters either - http_response_code() is false in CLI.
	$row($now - 120, 1, 94.0, "/console/app:import/orders", "", 0),
]]);' "${PROJECT_CODE}" "${PERF_NOW}")"
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
body_has 'name="_token"'

step "Resolving a record takes it off the dashboard"
RECORD_TOKEN="$(sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' <<<"${HTTP_BODY}" | head -n 1)"
[[ -n "${RECORD_TOKEN}" ]] || die "no CSRF token on the detail page"
http 302 POST "/detail/${RECORD_ID}/status/resolved" --data-urlencode "_token=${RECORD_TOKEN}"
http 200 GET /
grep -qF -- "/detail/${RECORD_ID}" <<<"${HTTP_BODY}" \
	&& die "the resolved record is still in the new-logs list"
ok "gone from the list"

step "The performance page renders, for every project and for one"
http 200 GET /performance
http 200 GET "/performance/${PROJECT_ID}"
# the path that was shipped two minutes ago, read back out of perf_bucket by TopPaths
body_has '/user/{id}'

step "The performance page honours the window a regression's link writes"
# The link on a RecordPerformance carries an anchored window, one route and a row to scroll to.
# Only prod builds the container the way a deployment does, and only here is the asset build real:
# the two date pickers are a front-end dependency, so a forgotten `yarn build` shows up as a page
# without `data-controller="perf-range"` and nowhere else.
# The minute the rows above were ingested into, not "two minutes before whenever this line runs" -
# the window is [at, at+1h) and the row has to be inside it however long the steps in between took.
AT="${PERF_AT}"
http 200 GET "/performance/${PROJECT_ID}?at=${AT}&hours=1&path=/user/%7Bid%7D"
body_has 'data-controller="perf-range"'
# the pickers are filled in from the resolved window rather than left empty
body_has "value=\"${AT%T*}\""
# and the row the fragment points at is there, keyed by the path's hash
body_has "id=\"perf-path-$(printf '%s' '/user/{id}' | md5sum | cut -d' ' -f1)\""
ok "anchored, filtered and anchored to a row"

step "A console run is a row of its own, colon and empty vhost included"
# Ingested with host="" and a path with a colon in it. `host` is part of the unique key and the
# path is in it as its hash, so if either had been mangled this row would be missing or merged
# into the HTTP one - and neither shape is reachable from the phpunit suite's own fixtures.
# Which sort puts it on screen is the report builder's business and is tested there; here there
# are three rows and a limit of twenty, so it is on screen either way.
http 200 GET "/performance/${PROJECT_ID}?hours=1"
body_has 'app:import/orders'
body_has "id=\"perf-path-$(printf '%s' '/console/app:import/orders' | md5sum | cut -d' ' -f1)\""
ok "named after the command, with its own bucket"

step "A hand-edited window is a page, not a 500"
# this page is often behind no firewall at all, and `hours` reaches a chart that is inline SVG in
# the document - PerfRangeResolver::MAX_HOURS is what keeps `?hours=100000` from being a DoS
http 200 GET "/performance/${PROJECT_ID}?at=not-a-date&hours=-5"
http 200 GET "/performance/${PROJECT_ID}?at=&hours=100000"
ok "clamped and fallen back"

step "The web app manifest renders and its icons are really there"
# The only reason the dashboard is installable is that Chrome then stops refusing the alert sound,
# and the only reason it installs is a manifest whose icons resolve. Those URLs come out of
# Encore's manifest.json and are published by assets:install, so a forgotten `yarn build` or a
# missing icon in the configured logo variant can only fail here - never in phpunit.
http 200 GET /
body_has 'rel="manifest"'
http 200 GET /manifest.webmanifest
body_has '"display":"standalone"'
body_has '"sizes":"192x192"'
body_has '"sizes":"512x512"'
for ICON_URL in $(grep -oE '/bundles/bugcatcher/assets/logo/[^"]+\.(png|svg)' <<<"${HTTP_BODY}" | sort -u); do
	http 200 GET "${ICON_URL}"
done

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
# Every call after initialize carries the session the server handed back in Mcp-Session-Id.
MCP_SESSION="$(sed -n 's/^[Mm]cp-[Ss]ession-[Ii]d: *\(.*\)\r*$/\1/p' "${HTTP_HEADERS_FILE}" | tr -d '\r' | head -n 1)"
[[ -n "${MCP_SESSION}" ]] || die "initialize returned no Mcp-Session-Id header"
MCP_HEADERS=(
	-H "Authorization: Bearer ${MCP_TOKEN}"
	-H "Mcp-Session-Id: ${MCP_SESSION}"
	-H 'Content-Type: application/json'
	-H 'Accept: application/json, text/event-stream'
)
http 202 POST /mcp "${MCP_HEADERS[@]}" -d '{"jsonrpc":"2.0","method":"notifications/initialized"}'
http 200 POST /mcp "${MCP_HEADERS[@]}" -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
for tool in list_projects search_records get_record_detail set_record_status; do
	body_has "${tool}"
done

step "An MCP tool answers with the data the API ingested"
http 200 POST /mcp "${MCP_HEADERS[@]}" \
	-d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"list_projects","arguments":{}}}'
body_has "${PROJECT_CODE}"
COOKIES="${SAVED_COOKIES}"

# ------------------------------------------------------------------------------- 8. the cron commands

step "Every command the README puts in cron runs"
console app:ping-collector || die "app:ping-collector failed"
ok "app:ping-collector"
console app:record-optimizer --past=1 --precision=5 || die "app:record-optimizer failed"
ok "app:record-optimizer"
ROLLUP="$(console app:perf:rollup --granularity=hour)" || die "app:perf:rollup --granularity=hour failed"
printf '    %s\n' "${ROLLUP}"
# The previous whole hour holds the second bucket of the batch, so a run that folds nothing means
# the roll-up window and the ingested bucketAt disagree - and every chart wider than two hours,
# which reads hours, would stay empty for ever without saying so.
grep -qE '[1-9][0-9]* buckets' <<<"${ROLLUP}" || die "the hour roll-up folded no buckets"
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
