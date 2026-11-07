<?php

namespace App\Services\Installation;

use PDO;
use PDOException;
use Throwable;

class DatabaseTester
{
    /**
     * Test a database connection without creating or modifying application data
     * (except for an isolated permission probe table which is removed immediately).
     */
    public function test(array $config): array
    {
        $driver = $config['driver'] ?? 'mysql';
        $result = [
            'ok' => false,
            'driver' => $driver,
            'version' => null,
            'errors' => [],
            'warnings' => [],
            'existing_tables' => [],
        ];

        try {
            if ($driver === 'sqlite') {
                return $this->testSqlite($config, $result);
            }

            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                $result['errors'][] = __('Unsupported database driver.');

                return $result;
            }

            $conn = $this->connect($config);
            $version = (string) $conn->query('SELECT VERSION()')->fetchColumn();
            $result['version'] = $version;

            $isMaria = stripos($version, 'mariadb') !== false;
            if ($driver === 'mysql' && $isMaria) {
                $result['warnings'][] = __('A MariaDB server was detected. MariaDB support is provided in compatibility mode.');
            }

            if ($driver === 'mysql' && ! $isMaria && version_compare($version, '8.0.0', '<')) {
                $result['errors'][] = __('MySQL 8.0 or newer is required. Detected: :version', ['version' => $version]);
            }

            if ($driver === 'mariadb' && version_compare($version, '10.6.0', '<')) {
                $result['errors'][] = __('MariaDB 10.6 or newer is required. Detected: :version', ['version' => $version]);
            }

            try {
                $charset = (string) $conn->query('SELECT @@character_set_database')->fetchColumn();
                if ($charset !== 'utf8mb4') {
                    $result['warnings'][] = __('The database default charset is ":charset"; utf8mb4 is recommended.', ['charset' => $charset]);
                }
            } catch (Throwable) {
                // Not all hosts expose this variable.
            }

            $tables = $this->tables($conn);
            $result['existing_tables'] = $tables;

            $conflicts = array_values(array_intersect($tables, [
                'users', 'resource_packs', 'resource_pack_versions', 'installations',
            ]));
            if ($conflicts) {
                $result['warnings'][] = __('This database already contains Next Respack tables. Installation will continue from the existing schema without deleting unrelated tables.');
            }

            $probe = 'nr_perm_probe_'.bin2hex(random_bytes(6));
            try {
                $conn->exec("CREATE TABLE `{$probe}` (`id` INT NOT NULL) ENGINE=InnoDB");
                $conn->exec("INSERT INTO `{$probe}` (`id`) VALUES (1)");
                $conn->exec("DROP TABLE `{$probe}`");
            } catch (Throwable $e) {
                $result['errors'][] = __('The database account lacks the privileges required to create and modify tables: :message', ['message' => $e->getMessage()]);
                if ($conflicts) {
                    $result['errors'][] = __('Existing Next Respack tables were found, so installation cannot continue safely without CREATE/ALTER privileges.');
                }
            }

            $result['ok'] = $result['errors'] === [];
        } catch (PDOException $e) {
            $result['errors'][] = $this->friendlyError($e);
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    public function connect(array $config): PDO
    {
        $driver = $config['driver'] === 'mariadb' ? 'mysql' : $config['driver'];
        $host = $config['host'] ?? '127.0.0.1';
        $port = (int) ($config['port'] ?? 3306);
        $database = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $driver, $host, $port, $database, $charset);

        return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ]);
    }

    public function sqlitePath(array $config): string
    {
        $path = $config['sqlite_path'] ?? database_path('database.sqlite');

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        return $path;
    }

    private function testSqlite(array $config, array $result): array
    {
        if (! extension_loaded('pdo_sqlite')) {
            $result['errors'][] = __('The PDO SQLite extension is not installed.');

            return $result;
        }

        $path = $this->sqlitePath($config);
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $result['errors'][] = __('The SQLite database directory is not writable.');

            return $result;
        }

        if (! is_file($path)) {
            if (! @touch($path)) {
                $result['errors'][] = __('Unable to create the SQLite database file.');

                return $result;
            }
            @chmod($path, 0660);
        }

        if (! is_writable($path)) {
            $result['errors'][] = __('The SQLite database file is not writable.');

            return $result;
        }

        try {
            $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $result['version'] = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
            $result['ok'] = true;
            $result['warnings'][] = __('SQLite is suitable for light deployments and testing. It has limited write concurrency and is not recommended for busy servers.');
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
            $result['existing_tables'] = $tables;
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    private function tables(PDO $conn): array
    {
        $database = (string) $conn->query('SELECT DATABASE()')->fetchColumn();

        $stmt = $conn->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = ?');
        $stmt->execute([$database]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function friendlyError(PDOException $e): string
    {
        $code = (int) $e->getCode();

        return match ($code) {
            1045 => __('Access denied. Check the database username and password.'),
            1049 => __('The database does not exist. Create it in your hosting panel first.'),
            2002, 2003 => __('Unable to reach the database server. Check the host, port and firewall.'),
            default => __('Database connection failed: :message', ['message' => $e->getMessage()]),
        };
    }
}
