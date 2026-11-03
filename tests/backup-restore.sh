#!/usr/bin/env bash
# Uses a disposable PostgreSQL cluster listening only on its own Unix socket.
set -euo pipefail
umask 077
work=$(mktemp -d)
export PATH="${PG_BINDIR:-$(pg_config --bindir)}:$PATH"
export PGHOST="$work" PGPORT=5432 PGDATABASE=farmwell_source PGUSER=drill
export PGSSLMODE=disable PGPASSWORD= DB_URL= DB_CONNECTION=pgsql DB_HOST="$work" DB_PORT=5432 DB_USERNAME=drill DB_PASSWORD= DB_DATABASE="$PGDATABASE"
export APP_ENV=testing CACHE_STORE=array FARMWELL_BACKUP_DISK=local
export FARMWELL_PRIVATE_ROOT="$work/private" FARMWELL_BACKUP_ROOT="$work/vault"
export BACKUP_ARCHIVE_PASSWORD="$(openssl rand -hex 32)"
cleanup() {
    pg_ctl -D "$work/cluster" -m immediate stop >/dev/null 2>&1 || true
    rm -rf -- "$work"
}
trap cleanup EXIT
initdb -D "$work/cluster" -U drill -A trust >/dev/null
pg_ctl -D "$work/cluster" -l "$work/postgres.log" -o "-k '$work' -c listen_addresses='' -p 5432" -w start >/dev/null
createdb farmwell_source
createdb farmwell_restored
php artisan migrate --no-interaction >/dev/null
mkdir -p "$FARMWELL_PRIVATE_ROOT/1" "$work/recovered"
printf 'private drill receipt\n' > "$FARMWELL_PRIVATE_ROOT/1/receipt.txt"
psql -Xq -v ON_ERROR_STOP=1 <<'SQL'
INSERT INTO teams (name, slug) VALUES ('Drill', 'drill');
INSERT INTO farms (team_id, name, farm_type) VALUES (1, 'Recovery farm', 'crop');
INSERT INTO media (model_type, model_id, collection_name, name, file_name, disk, size, manipulations, custom_properties, generated_conversions, responsive_images)
VALUES ('App\Models\Farm', 1, 'evidence', 'receipt', 'receipt.txt', 'farmwell_private', 22, '{}', '{}', '{}', '{}');
SQL
if php artisan backup:monitor --no-interaction > /dev/null 2>&1; then
    echo 'An empty backup destination passed monitoring.' >&2; exit 1
fi
bash .github/scripts/farmwell-backup.sh
archives=("$FARMWELL_BACKUP_ROOT"/Farmwell/*.zip)
[[ ${#archives[@]} == 1 && -s "${archives[0]}" ]]
php -r '
    $zip = new ZipArchive();
    if ($zip->open($argv[1]) !== true) { exit(1); }
    foreach (["database.dump", "media/1/receipt.txt"] as $name) {
        if (($zip->statName($name)["encryption_method"] ?? null) !== ZipArchive::EM_AES_256) { exit(2); }
    }
    $zip->setPassword("incorrect");
    if ($zip->getFromName("database.dump") !== false) { exit(3); }
    $zip->setPassword(getenv("BACKUP_ARCHIVE_PASSWORD"));
    if (!$zip->extractTo($argv[2], ["database.dump", "media/1/receipt.txt"])) { exit(4); }
' "${archives[0]}" "$work/recovered"
pg_restore --exit-on-error --single-transaction --no-owner --no-privileges --dbname=farmwell_restored "$work/recovered/database.dump"
query="SELECT f.name, m.id || '/' || m.file_name, m.size FROM farms f JOIN media m ON m.model_id = f.id WHERE f.team_id = 1 AND m.disk = 'farmwell_private'"
[[ "$(psql -XAt -d farmwell_restored -c "$query")" == 'Recovery farm|1/receipt.txt|22' ]]
[[ "$(psql -XAt -c 'SELECT count(*) FROM migrations')" == "$(psql -XAt -d farmwell_restored -c 'SELECT count(*) FROM migrations')" ]]
cmp "$FARMWELL_PRIVATE_ROOT/1/receipt.txt" "$work/recovered/media/1/receipt.txt"
stale="$FARMWELL_BACKUP_ROOT/Farmwell/2000-01-01-00-00-00.zip"
mv "${archives[0]}" "$stale"
if php artisan backup:monitor --no-interaction > /dev/null 2>&1; then
    echo 'A stale backup passed monitoring.' >&2; exit 1
fi
cp "$stale" "${archives[0]}"
php artisan backup:clean --no-interaction >/dev/null
[[ -s "${archives[0]}" && ! -e "$stale" ]]
printf 'truncated' > "$FARMWELL_PRIVATE_ROOT/1/receipt.txt"
if bash .github/scripts/farmwell-backup.sh > "$work/incomplete.log" 2>&1; then
    echo 'An incomplete media copy passed verification.' >&2; exit 1
fi
grep -q 'Missing or incomplete file for media record' "$work/incomplete.log"
echo 'Restore drill passed: encrypted archive, schema, farm, media row and exact private file bytes.'
