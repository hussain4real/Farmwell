#!/usr/bin/env bash
set -euo pipefail

sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
# The runner's unused development headers conflict with PGDG's pg_config ownership.
sudo apt-get remove -y libpq-dev
sudo apt-get install -y --no-install-recommends postgresql-18 postgresql-client-18
echo '/usr/lib/postgresql/18/bin' >> "${GITHUB_PATH:?}"
echo 'PG_BINDIR=/usr/lib/postgresql/18/bin' >> "${GITHUB_ENV:?}"
/usr/lib/postgresql/18/bin/pg_dump --version
/usr/lib/postgresql/18/bin/pg_restore --version
