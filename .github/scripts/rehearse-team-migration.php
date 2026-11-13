<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Support\Str;

function migrationConnection(string $url): PDO
{
    $parts = parse_url($url);
    if (! in_array($parts['scheme'] ?? '', ['postgres', 'postgresql', 'pgsql'], true)) {
        throw new RuntimeException('Expected a PostgreSQL connection.');
    }
    $host = $parts['host'];
    $tls = str_ends_with($host, '.neon.tech') ? ';sslmode=verify-full;sslrootcert='.(is_file('/etc/ssl/certs/ca-certificates.crt') ? '/etc/ssl/certs/ca-certificates.crt' : '/etc/ssl/cert.pem') : ';sslmode=disable';
    if (! str_ends_with($host, '.neon.tech') && $host !== 'localhost') {
        throw new RuntimeException('Only Neon or local rehearsal databases are supported.');
    }

    return new PDO('pgsql:host='.$host.';port='.($parts['port'] ?? 5432).';dbname='.ltrim($parts['path'], '/').$tls, rawurldecode($parts['user']), rawurldecode($parts['pass'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}

function identifier(string $name): string
{
    return '"'.str_replace('"', '""', $name).'"';
}

/** @return array<int, array<string, mixed>> */
function migrationRows(PDO $connection, string $sql, array $parameters = []): array
{
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll();
}

try {
    umask(0077);
    [$script, $connectionsFile, $slug, $receiptPath] = $argv;
    $connections = json_decode(file_get_contents($connectionsFile), true, flags: JSON_THROW_ON_ERROR);
    if (parse_url($connections['source'], PHP_URL_HOST) === parse_url($connections['target'], PHP_URL_HOST)
        && parse_url($connections['source'], PHP_URL_PATH) === parse_url($connections['target'], PHP_URL_PATH)) {
        throw new RuntimeException('Source and destination must be different databases.');
    }
    $source = migrationConnection($connections['source']);
    $target = migrationConnection($connections['target']);
    $source->exec('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $source->exec("SET LOCAL TIME ZONE 'UTC'");
    $target->beginTransaction();
    $target->exec("SET LOCAL TIME ZONE 'UTC'");
    $targetTables = $target->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($targetTables as $table) {
        if ($table !== 'migrations' && $target->query('SELECT EXISTS(SELECT 1 FROM '.identifier($table).')')->fetchColumn()) {
            throw new RuntimeException('Destination must contain an empty migrated schema.');
        }
    }
    $teams = migrationRows($source, 'SELECT * FROM teams WHERE slug = ? AND deleted_at IS NULL', [$slug]);
    if (count($teams) !== 1) {
        throw new RuntimeException('Expected exactly one active source team.');
    }
    $teamId = $teams[0]['id'];
    $data = ['teams' => $teams];
    $tenantTables = $source->query("SELECT table_name FROM information_schema.columns WHERE table_schema = 'public' AND column_name = 'team_id' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tenantTables as $table) {
        $predicate = 'team_id = ?';
        if ($table === 'roles') {
            $predicate .= ' OR team_id IS NULL';
        } elseif ($table === 'team_invitations') {
            $predicate .= ' AND accepted_at IS NOT NULL';
        }
        $data[$table] = migrationRows($source, 'SELECT * FROM '.identifier($table).' WHERE '.$predicate, [$teamId]);
    }
    $data['permissions'] = migrationRows($source, 'SELECT * FROM permissions');
    $data['role_has_permissions'] = migrationRows($source, 'SELECT rp.* FROM role_has_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.team_id = ? OR r.team_id IS NULL', [$teamId]);
    foreach (['commodity_production_cycle', 'production_cycle_production_unit'] as $table) {
        $data[$table] = migrationRows($source, 'SELECT p.* FROM '.identifier($table).' p JOIN production_cycles c ON c.id = p.production_cycle_id WHERE c.team_id = ?', [$teamId]);
    }
    $data['media'] = array_values(array_filter(migrationRows($source, 'SELECT * FROM media'), function (array $row) use ($data): bool {
        $table = Str::snake(Str::pluralStudly(class_basename($row['model_type'])));

        return in_array($row['model_id'], array_column($data[$table] ?? [], 'id'), true);
    }));
    $foreignKeys = migrationRows($source, "SELECT conrelid::regclass::text AS child, confrelid::regclass::text AS parent, a.attname AS column FROM pg_constraint c JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1] WHERE c.contype = 'f' AND cardinality(c.conkey) = 1 AND c.connamespace = 'public'::regnamespace");
    $userIds = array_column($data['team_members'], 'user_id');
    foreach ($foreignKeys as $key) {
        if ($key['parent'] === 'users') {
            foreach ($data[$key['child']] ?? [] as $row) {
                if ($row[$key['column']] !== null) {
                    $userIds[] = $row[$key['column']];
                }
            }
        }
    }
    $userIds = array_values(array_unique($userIds));
    if ($userIds === []) {
        throw new RuntimeException('Team has no users.');
    }
    $data['users'] = migrationRows($source, 'SELECT * FROM users WHERE id IN ('.implode(',', array_fill(0, count($userIds), '?')).')', $userIds);
    foreach ($data['users'] as &$user) {
        if ($user['two_factor_secret'] !== null || $user['two_factor_recovery_codes'] !== null) {
            throw new RuntimeException('An account requires encryption-key migration before importing.');
        }
        $user['current_team_id'] = in_array($user['id'], array_column($data['team_members'], 'user_id'), true) ? $teamId : null;
        $user['remember_token'] = null;
    }
    unset($user);
    foreach ($foreignKeys as $key) {
        foreach ($data[$key['child']] ?? [] as $row) {
            $value = $row[$key['column']];
            if ($value !== null && ! in_array($value, array_column($data[$key['parent']] ?? [], 'id'), true)) {
                throw new RuntimeException('A selected record references data outside the migration scope.');
            }
        }
    }
    foreach (['ai_summaries', 'report_artifacts'] as $table) {
        if (($data[$table] ?? []) !== []) {
            throw new RuntimeException('AI/report payloads require a separate encryption and source-scope review.');
        }
    }
    $pending = array_filter($data);
    while ($pending !== []) {
        $progress = false;
        foreach ($pending as $table => $rows) {
            $dependencies = array_filter($foreignKeys, fn (array $key): bool => $key['child'] === $table && isset($pending[$key['parent']]) && $key['parent'] !== $table);
            if ($dependencies !== []) {
                continue;
            }
            $columns = array_keys($rows[0]);
            $sql = 'INSERT INTO '.identifier($table).' ('.implode(',', array_map('identifier', $columns)).') VALUES ('.implode(',', array_fill(0, count($columns), '?')).')';
            $statement = $target->prepare($sql);
            foreach ($rows as $row) {
                foreach (array_values($row) as $index => $value) {
                    $statement->bindValue($index + 1, $value, is_bool($value) ? PDO::PARAM_BOOL : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
                }
                $statement->execute();
            }
            unset($pending[$table]);
            $progress = true;
        }
        if (! $progress) {
            throw new RuntimeException('Unresolved dependency cycle; no data was committed.');
        }
    }
    $receipt = ['team_id' => $teamId, 'tables' => [], 'media' => []];
    foreach ($data as $table => $rows) {
        $actual = migrationRows($target, 'SELECT * FROM '.identifier($table));
        $normalize = function (array $records): array {
            $encoded = array_map(function (array $row): string {
                ksort($row);

                return json_encode($row, JSON_THROW_ON_ERROR);
            }, $records);
            sort($encoded);

            return $encoded;
        };
        if ($normalize($rows) !== $normalize($actual)) {
            throw new RuntimeException('Imported row reconciliation failed.');
        }
        $receipt['tables'][$table] = count($rows);
    }
    foreach ($targetTables as $table) {
        if ($table !== 'migrations' && ! array_key_exists($table, $data) && $target->query('SELECT count(*) FROM '.identifier($table))->fetchColumn() !== 0) {
            throw new RuntimeException('Unexpected records in an excluded table.');
        }
    }
    foreach ($data['media'] as $media) {
        if ($media['disk'] !== 'farmwell_private' || basename($media['file_name']) !== $media['file_name']) {
            throw new RuntimeException('Unexpected media storage path.');
        }
        $receipt['media'][] = ['id' => $media['id'], 'key' => $media['id'].'/'.$media['file_name'], 'size' => $media['size']];
    }
    foreach ($targetTables as $table) {
        $sequence = $target->prepare("SELECT pg_get_serial_sequence(?, 'id') WHERE EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = 'id')");
        $sequence->execute([$table, $table]);
        if ($name = $sequence->fetchColumn()) {
            $target->exec('SELECT setval('.$target->quote($name).', GREATEST(COALESCE((SELECT max(id) FROM '.identifier($table).'), 0), 1), EXISTS(SELECT 1 FROM '.identifier($table).'))');
        }
    }
    $target->commit();
    $source->rollBack();
    file_put_contents($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['team_id' => $teamId, 'users' => count($data['users']), 'farms' => count($data['farms']), 'activities' => count($data['farm_activities']), 'photos' => count($data['media']), 'rows_reconciled' => array_sum($receipt['tables'])], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    if (isset($target) && $target->inTransaction()) {
        $target->rollBack();
    }
    if (isset($source) && $source->inTransaction()) {
        $source->rollBack();
    }
    fwrite(STDERR, 'Migration refused or failed ('.get_class($exception).', '.$exception->getCode().'). No source writes were performed.'.PHP_EOL);
    if ($exception instanceof RuntimeException && ! $exception instanceof PDOException) {
        fwrite(STDERR, $exception->getMessage().PHP_EOL);
    }
    exit(1);
}
