#!/usr/bin/env bash
# Restore only to a disposable Unix-socket PostgreSQL cluster; never boot the app or workers.
set -euo pipefail
umask 077
: "${BACKUP_ARCHIVE_PASSWORD:?}"
archive=${1:?}
source=${2:?}
work=$(mktemp -d)
export PATH="${PG_BINDIR:-$(pg_config --bindir)}:$PATH"
export PGHOST="$work" PGPORT=5432 PGDATABASE=farmwell_recovery PGUSER=drill
export PGSSLMODE=disable PGPASSWORD= PGSERVICEFILE=/dev/null
unset PGHOSTADDR PGOPTIONS PGSERVICE
cleanup() {
    pg_ctl -D "$work/cluster" -m immediate stop >/dev/null 2>&1 || true
    rm -rf -- "$work"
}
trap cleanup EXIT
mkdir "$work/recovered"
php .github/scripts/extract-backup.php "$archive" "$work/recovered" "$source"
initdb -D "$work/cluster" -U drill -A trust >/dev/null
pg_ctl -D "$work/cluster" -l "$work/postgres.log" -o "-k '$work' -c listen_addresses='' -p 5432" -w start >/dev/null
createdb "$PGDATABASE"
pg_restore --exit-on-error --single-transaction --no-owner --no-privileges \
    --dbname="$PGDATABASE" "$work/recovered/database.dump" > "$work/restore.log" 2>&1 || {
    echo 'Database restoration failed; no application or worker was started.' >&2
    exit 1
}
psql -XqAt -v ON_ERROR_STOP=1 > "$work/media.jsonl" <<'SQL'
SELECT json_build_array(id, file_name, size) FROM public.media WHERE disk = 'farmwell_private';
SQL
php .github/scripts/verify-backup-source.php "$work/recovered/media" "$work/media.jsonl"
if [[ -n "${FARMWELL_RESTORE_TEAM:-}" ]]; then
    psql -XqAt -v ON_ERROR_STOP=1 -v team="$FARMWELL_RESTORE_TEAM" <<'SQL'
SELECT EXISTS(SELECT 1 FROM teams WHERE slug = :'team' AND deleted_at IS NULL) AS team_exists \gset
\if :team_exists
SELECT json_build_object(
    'team_id', t.id,
    'members', (SELECT count(*) FROM team_members WHERE team_id = t.id),
    'verified_phone_members', (SELECT count(*) FROM team_members tm JOIN users u ON u.id = tm.user_id WHERE tm.team_id = t.id AND to_jsonb(u)->>'phone' IS NOT NULL AND to_jsonb(u)->>'phone_verified_at' IS NOT NULL),
    'farms', (SELECT count(*) FROM farms WHERE team_id = t.id),
    'activities', (SELECT count(*) FROM farm_activities WHERE team_id = t.id),
    'diary_photos', (SELECT count(*) FROM media m JOIN farm_activities a ON a.id = m.model_id WHERE m.model_type = 'App\Models\FarmActivity' AND m.collection_name = 'evidence' AND m.mime_type LIKE 'image/%' AND a.team_id = t.id)
) FROM teams t WHERE t.slug = :'team';
\else
\quit 1
\endif
SQL
fi
echo 'Restore passed: downloaded encrypted archive, identical database dump and private file hashes, complete PostgreSQL restore and media inventory.'
