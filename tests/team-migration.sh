#!/usr/bin/env bash
set -euo pipefail
umask 077
work=$(mktemp -d)
export PATH="${PG_BINDIR:-$(pg_config --bindir)}:$PATH"
export PGHOST=localhost PGPORT
PGPORT=$(python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1",0)); print(s.getsockname()[1]); s.close()')
export PGUSER=drill PGPASSWORD= PGSSLMODE=disable PGDATABASE=source
export APP_ENV=testing APP_KEY="base64:$(openssl rand -base64 32)" DB_CONNECTION=pgsql DB_HOST=localhost DB_PORT="$PGPORT" DB_DATABASE=source DB_USERNAME=drill DB_PASSWORD= DB_URL= CACHE_STORE=array
cleanup() { pg_ctl -D "$work/cluster" -m immediate stop >/dev/null 2>&1 || true; rm -rf -- "$work"; }
trap cleanup EXIT
initdb -D "$work/cluster" -U drill -A trust >/dev/null
pg_ctl -D "$work/cluster" -l "$work/postgres.log" -o "-k '$work' -c listen_addresses=127.0.0.1 -p $PGPORT" -w start >/dev/null
createdb source
createdb destination
php artisan migrate --no-interaction >/dev/null
pg_dump --schema-only --no-owner --no-privileges | psql -Xq -v ON_ERROR_STOP=1 -d destination >/dev/null
psql -Xq -v ON_ERROR_STOP=1 <<'SQL'
INSERT INTO teams (id,name,slug) VALUES (4,'Pilot','pilot'), (5,'Unrelated','other');
INSERT INTO users (id,name,email,password,current_team_id,phone,phone_verified_at,two_factor_secret) VALUES
(13,'Pilot user',NULL,'preserved-hash',4,'+2348000000000',now(),'encrypted-secret'),(14,'Other user','other@example.test','other-hash',5,NULL,NULL,NULL);
UPDATE users SET platform_role='super_admin' WHERE id=13;
INSERT INTO team_members (team_id,user_id,role) VALUES (4,13,'admin'),(5,14,'admin');
INSERT INTO team_invitations (id,code,team_id,phone,role,invited_by,accepted_at) VALUES (1,repeat('a',64),4,'+2348000000000','admin',13,now()), (2,repeat('b',64),4,'+2348000000001','operator',13,NULL);
INSERT INTO farms (id,team_id,name,farm_type) VALUES (1,4,'Pilot farm','crop'),(2,5,'Other farm','crop');
INSERT INTO farm_activities (id,team_id,farm_id,recorded_by_id,activity_date,activity_type,description,status) VALUES (3,4,1,13,'2026-11-12','Inspection','Synthetic inspection','completed'),(4,4,1,13,'2026-11-12','Watering','Synthetic watering','completed'),(5,5,2,14,'2026-11-12','Private','Other tenant','completed');
INSERT INTO media (id,model_type,model_id,collection_name,name,file_name,mime_type,disk,size,manipulations,custom_properties,generated_conversions,responsive_images) SELECT n,'App\Models\FarmActivity',4,'evidence','Photo','photo.jpg','image/jpeg','farmwell_private',10,'{}','{}','{}','{}' FROM generate_series(5,7) n;
INSERT INTO sessions (id,user_id,payload,last_activity) VALUES ('excluded',13,'session',1);
INSERT INTO cache (key,value,expiration) VALUES ('otp-challenge','excluded',1);
SQL
printf '{"source":"postgresql://drill@localhost:%s/source","target":"postgresql://drill@localhost:%s/destination"}\n' "$PGPORT" "$PGPORT" > "$work/connections.json"
if php .github/scripts/rehearse-team-migration.php "$work/connections.json" pilot "$work/receipt.json" > "$work/rejected.log" 2>&1; then
    echo 'Encrypted identities were accepted without a key migration.' >&2; exit 1
fi
[[ "$(psql -XAt -d destination -c 'SELECT count(*) FROM teams')" == 0 ]]
psql -Xq -c 'UPDATE users SET two_factor_secret=NULL WHERE id=13'
php .github/scripts/rehearse-team-migration.php "$work/connections.json" pilot "$work/receipt.json"
[[ "$(psql -XAt -d destination -c "SELECT id || '|' || password || '|' || current_team_id FROM users")" == '13|preserved-hash|4' ]]
[[ "$(psql -XAt -d destination -c 'SELECT count(*) FROM team_invitations WHERE accepted_at IS NOT NULL')" == 1 ]]
[[ "$(psql -XAt -d destination -c 'SELECT count(*) FROM users WHERE platform_role IS NOT NULL')" == 0 ]]
[[ "$(psql -XAt -c "SELECT platform_role FROM users WHERE id=13")" == 'super_admin' ]]
[[ "$(psql -XAt -d destination -c 'SELECT count(*) FROM sessions')" == 0 ]]
[[ "$(psql -XAt -d destination -c 'SELECT count(*) FROM cache')" == 0 ]]
[[ "$(psql -XAt -d destination -c 'SELECT count(*) FROM media')" == 3 ]]
[[ "$(psql -XAt -d destination -c 'SELECT count(*) FROM farm_activities')" == 2 ]]
[[ "$(psql -XAt -c 'SELECT count(*) FROM teams')" == 2 ]]
[[ "$(psql -XAt -d destination -c "SELECT nextval(pg_get_serial_sequence('users','id'))")" == 14 ]]
if php .github/scripts/rehearse-team-migration.php "$work/connections.json" pilot "$work/receipt.json" > "$work/rejected.log" 2>&1; then
    echo 'A populated destination was accepted.' >&2; exit 1
fi
[[ "$(psql -XAt -d destination -c 'SELECT count(*) FROM users')" == 1 ]]
echo 'Team migration passed: identity, accepted invitation, farm, diary, media, tenant isolation, sequence advancement and nonempty-target rejection.'
