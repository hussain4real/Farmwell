#!/usr/bin/env bash
set -euo pipefail
umask 077

: "${PGHOST:?}" "${PGDATABASE:?}" "${PGUSER:?}" "${BACKUP_ARCHIVE_PASSWORD:?}"
php -r 'exit(ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256) ? 0 : 1);'
work=$(mktemp -d)
trap 'rm -rf -- "$work"' EXIT
export FARMWELL_BACKUP_SOURCE="$work/source" FARMWELL_BACKUP_TEMP="$work/spatie"
mkdir -p "$FARMWELL_BACKUP_SOURCE/media"

# Export the media inventory and database dump from the same PostgreSQL snapshot.
psql -XqAt -v ON_ERROR_STOP=1 > "$work/media.jsonl" <<'SQL'
BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY;
SELECT pg_export_snapshot() AS snapshot \gset
\setenv FARMWELL_PG_SNAPSHOT :snapshot
\! pg_dump --snapshot="$FARMWELL_PG_SNAPSHOT" --format=custom --file="$FARMWELL_BACKUP_SOURCE/database.dump" && touch "$FARMWELL_BACKUP_TEMP.dump-complete"
SELECT json_build_array(id, file_name, size) FROM public.media WHERE disk = 'farmwell_private';
COMMIT;
SQL
[[ -f "$FARMWELL_BACKUP_TEMP.dump-complete" ]]
case "${FARMWELL_BACKUP_DISK:?}" in
    r2)
        : "${AWS_ENDPOINT:?}" "${AWS_BUCKET:?}" "${AWS_ACCESS_KEY_ID:?}" "${AWS_SECRET_ACCESS_KEY:?}"
        : "${BACKUP_R2_ENDPOINT:?}" "${BACKUP_R2_BUCKET:?}" "${BACKUP_R2_ACCESS_KEY_ID:?}" "${BACKUP_R2_SECRET_ACCESS_KEY:?}"
        if [[ "$AWS_BUCKET" == "$BACKUP_R2_BUCKET" ]]; then
            echo 'Use a dedicated backup bucket.' >&2
            exit 1
        fi
        aws --endpoint-url "$AWS_ENDPOINT" s3 sync "s3://$AWS_BUCKET/" "$FARMWELL_BACKUP_SOURCE/media/" --only-show-errors
        ;;
    local)
        : "${FARMWELL_PRIVATE_ROOT:?}" "${FARMWELL_BACKUP_ROOT:?}"
        rsync -r -- "$FARMWELL_PRIVATE_ROOT/" "$FARMWELL_BACKUP_SOURCE/media/"
        ;;
    *) echo 'Expected local or r2 backup disk.' >&2; exit 1 ;;
esac

php .github/scripts/verify-backup-source.php "$FARMWELL_BACKUP_SOURCE/media" "$work/media.jsonl"
php artisan backup:run --no-interaction
php artisan backup:monitor --no-interaction
php artisan backup:clean --no-interaction
