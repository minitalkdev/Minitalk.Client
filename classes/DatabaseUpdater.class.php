<?php
/** Administrator-approved, resumable schema updates. Never rebuild customer tables. */
class DatabaseUpdater {
    const VERSION = 1;
    private $db;
    private $connection;
    private $schema;
    private $prefix;
    private $hash;
    private $targetVersion;

    function __construct($db, $schema = null, $version = null) {
        $this->db = $db;
        $this->connection = $db->mysqli();
        $this->prefix = $db->getPrefix();
        if ($schema === null) {
            $package = json_decode(file_get_contents(__MINITALK_PATH__.'/package.json'));
            $schema = $package->databases ?? null;
            $version = $package->database_schema_version ?? self::VERSION;
        }
        if (!is_object($schema) || count((array)$schema) === 0) throw new RuntimeException('INVALID_SCHEMA');
        $this->schema = $schema;
        $this->targetVersion = $version ?? self::VERSION;
        if (!is_int($this->targetVersion) || $this->targetVersion < 1) throw new RuntimeException('INVALID_SCHEMA_VERSION');
        $this->hash = hash('sha256', json_encode(array('version'=>$this->targetVersion,'schema'=>$schema), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function identifier($name) {
        if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name)) throw new RuntimeException('INVALID_IDENTIFIER');
        return '`'.$name.'`';
    }

    private function table($name) { return $this->prefix.$name; }
    private function quote($value) { return "'".$this->connection->real_escape_string((string)$value)."'"; }

    private function rows($sql) {
        $result = $this->connection->query($sql);
        // Preserve SQL error codes even when the legacy driver disables mysqli reporting.
        if ($result === false) throw new mysqli_sql_exception('DATABASE_QUERY_FAILED',$this->connection->errno);
        if ($result === true) return array();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        return $rows;
    }

    private function tables() {
        $tables = array();
        foreach ($this->rows('SHOW FULL TABLES') as $row) {
            $values = array_values($row);
            $tables[$values[0]] = $values[1];
        }
        return $tables;
    }

    private function type($column) {
        $type = strtolower($column->type ?? '');
        if (in_array($type, array('int','bigint','smallint','tinyint','mediumint','text','longtext'), true)) return strtoupper($type);
        if (in_array($type, array('char','varchar'), true)) {
            $length = filter_var($column->length ?? null, FILTER_VALIDATE_INT);
            if ($length === false || $length < 1 || $length > 16383) throw new RuntimeException('INVALID_COLUMN_LENGTH');
            return strtoupper($type).'('.$length.')';
        }
        if ($type === 'enum') {
            $values = str_getcsv((string)($column->length ?? ''), ',', "'", '\\');
            if (!$values || in_array('', $values, true)) throw new RuntimeException('INVALID_ENUM');
            return 'ENUM('.implode(',', array_map(array($this, 'quote'), $values)).')';
        }
        throw new RuntimeException('UNSUPPORTED_COLUMN_TYPE');
    }

    private function definition($name, $column, $autoIncrement = false) {
        $sql = $this->identifier($name).' '.$this->type($column);
        $sql .= ($column->is_null ?? false) === true ? ' NULL' : ' NOT NULL';
        if (property_exists($column, 'default')) $sql .= $column->default === null ? ' DEFAULT NULL' : ' DEFAULT '.$this->quote($column->default);
        if ($autoIncrement) $sql .= ' AUTO_INCREMENT';
        if (isset($column->comment)) $sql .= ' COMMENT '.$this->quote($column->comment);
        return $sql;
    }

    private function indexDefinition($columns, $type) {
        $names = explode(',', $columns);
        $sqlColumns = implode(',', array_map(array($this, 'identifier'), $names));
        if ($type === 'primary_key') return 'PRIMARY KEY ('.$sqlColumns.')';
        $kind = array('index'=>'INDEX', 'unique'=>'UNIQUE INDEX', 'fulltext'=>'FULLTEXT INDEX')[$type] ?? null;
        if ($kind === null) throw new RuntimeException('UNSUPPORTED_INDEX');
        return $kind.' '.$this->identifier('mt_'.substr(hash('sha256', $type.'|'.$columns), 0, 20)).' ('.$sqlColumns.')';
    }

    private function createSql($name, $schema) {
        $parts = array();
        foreach ($schema->columns as $column=>$options) $parts[] = $this->definition($column, $options, ($schema->auto_increment ?? null) === $column);
        foreach ($schema->indexes ?? new stdClass() as $columns=>$type) $parts[] = $this->indexDefinition($columns, $type);
        return 'CREATE TABLE '.$this->identifier($name).' ('.implode(',', $parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    private function metadata($tables) {
        $name = $this->table('schema_version_table');
        if (!isset($tables[$name])) return array('version'=>0, 'hash'=>null);
        if ($tables[$name] !== 'BASE TABLE') throw new RuntimeException('INVALID_VERSION_TABLE');
        $rows = $this->rows('SELECT version,schema_hash FROM '.$this->identifier($name).' WHERE id=1');
        return $rows ? array('version'=>(int)$rows[0]['version'], 'hash'=>$rows[0]['schema_hash']) : array('version'=>0, 'hash'=>null);
    }

    /** Inspect actual fields/indexes without DDL, writes, or cache changes. */
    function status() {
        $tables = $this->tables();
        $metadata = $this->metadata($tables);
        $operations = array(); $blocked = array();
        if ($metadata['version'] > $this->targetVersion) $blocked[] = 'NEWER_DATABASE_VERSION';
        foreach ($this->schema as $logical=>$target) {
            $name = $this->table($logical);
            $quoted = $this->identifier($name);
            if (!isset($tables[$name])) {
                $operations[] = array('kind'=>'create_table', 'sql'=>$this->createSql($name, $target));
                continue;
            }
            if ($tables[$name] !== 'BASE TABLE') { $blocked[] = 'EXPECTED_BASE_TABLE'; continue; }
            $engine = $this->rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='.$this->quote($name));
            if (!$engine || strtoupper($engine[0]['ENGINE']) !== 'INNODB') { $blocked[]='UNSUPPORTED_STORAGE_ENGINE'; continue; }
            $columns = array_column($this->rows('SHOW FULL COLUMNS FROM '.$quoted), null, 'Field');
            foreach ($target->columns as $column=>$options) {
                $desired = $this->type($options);
                if (!isset($columns[$column])) {
                    if (($options->is_null ?? false) !== true && !property_exists($options, 'default') && $this->rows('SELECT 1 FROM '.$quoted.' LIMIT 1')) {
                        $blocked[] = 'COLUMN_BACKFILL_REQUIRED'; continue;
                    }
                    $operations[] = array('kind'=>'add_column','sql'=>'ALTER TABLE '.$quoted.' ADD COLUMN '.$this->definition($column,$options).', ALGORITHM=INPLACE, LOCK=NONE');
                    continue;
                }
                $current = $columns[$column];
                if (($current['Null'] === 'YES') !== (($options->is_null ?? false) === true)) {
                    $blocked[] = 'NULLABILITY_REQUIRES_REVIEW'; continue;
                }
                preg_match('/^([a-z]+)(?:\((\d+)\))?/i', $current['Type'], $actual);
                $base = strtolower($actual[1] ?? '');
                if ($base !== strtolower($options->type)) { $blocked[] = 'COLUMN_CONVERSION_REQUIRED'; continue; }
                if (in_array($base,array('char','varchar'),true) && (int)($actual[2] ?? 0) < (int)$options->length) {
                    // Preserve the existing field's defaults, nullability, collation and comment.
                    if ($current['Extra'] !== '') { $blocked[] = 'GENERATED_COLUMN_REQUIRES_REVIEW'; continue; }
                    $sql = 'ALTER TABLE '.$quoted.' MODIFY COLUMN '.$this->identifier($column).' '.$desired;
                    if ($current['Collation'] !== null) $sql .= ' COLLATE '.$this->identifier($current['Collation']);
                    $sql .= $current['Null'] === 'YES' ? ' NULL' : ' NOT NULL';
                    if ($current['Default'] !== null) $sql .= ' DEFAULT '.$this->quote($current['Default']);
                    elseif ($current['Null'] === 'YES') $sql .= ' DEFAULT NULL';
                    $sql .= ' COMMENT '.$this->quote($current['Comment']);
                    $operations[] = array('kind'=>'widen_column','sql'=>$sql.', ALGORITHM=INPLACE, LOCK=NONE');
                } elseif ($base === 'enum' && substr($current['Type'],strpos($current['Type'],'(')) !== substr($desired,strpos($desired,'('))) {
                    $blocked[] = 'ENUM_CONVERSION_REQUIRED';
                }
            }
            $indexes = array();
            foreach ($this->rows('SHOW INDEX FROM '.$quoted) as $index) {
                $key = $index['Key_name'];
                if (!isset($indexes[$key])) $indexes[$key] = array('columns'=>array(),'partial'=>false,'type'=>$key === 'PRIMARY' ? 'primary_key' : ($index['Index_type'] === 'FULLTEXT' ? 'fulltext' : ((int)$index['Non_unique'] === 0 ? 'unique' : 'index')));
                $indexes[$key]['columns'][(int)$index['Seq_in_index']] = $index['Column_name'];
                if ($index['Sub_part'] !== null) $indexes[$key]['partial'] = true;
            }
            foreach ($target->indexes ?? new stdClass() as $keys=>$type) {
                $found = false; $partialFound = false;
                foreach ($indexes as $index) {
                    ksort($index['columns']);
                    if (implode(',',$index['columns']) === $keys && $index['partial']) { $partialFound=true; continue; }
                    if (implode(',',$index['columns']) === $keys && ($index['type'] === $type || ($type === 'index' && in_array($index['type'],array('unique','primary_key'),true)) || ($type === 'unique' && $index['type'] === 'primary_key'))) $found = true;
                }
                if ($found) continue;
                if ($partialFound) { $blocked[]='PREFIX_INDEX_REQUIRES_REVIEW'; continue; }
                if ($type === 'fulltext') { $blocked[]='FULLTEXT_INDEX_REQUIRES_REVIEW'; continue; }
                if ($type === 'primary_key' && isset($indexes['PRIMARY'])) { $blocked[] = 'PRIMARY_KEY_REQUIRES_REVIEW'; continue; }
                $operations[] = array('kind'=>'add_index','sql'=>'ALTER TABLE '.$quoted.' ADD '.$this->indexDefinition($keys,$type).', ALGORITHM=INPLACE, LOCK=NONE');
            }
        }
        return array('pending'=>count($blocked)>0 || count($operations)>0 || $metadata['version'] !== $this->targetVersion || $metadata['hash'] !== $this->hash,
            'current_version'=>$metadata['version'],'target_version'=>$this->targetVersion,'schema_hash'=>$this->hash,
            'blocked'=>array_values(array_unique($blocked)),'operations'=>$operations);
    }

    /** Called only by an explicitly authorized update or initial installation. */
    function apply($expectedHash) {
        if (!is_string($expectedHash) || !hash_equals($this->hash,$expectedHash)) return array('success'=>false,'code'=>'STALE_SCHEMA');
        if ((int)$this->rows('SELECT @@SESSION.autocommit AS value')[0]['value'] !== 1) return array('success'=>false,'code'=>'ACTIVE_TRANSACTION');
        $database = $this->rows('SELECT DATABASE() AS name')[0]['name'];
        $lockName = 'minitalk-schema:'.substr(hash('sha256',$database.'|'.$this->prefix),0,40);
        try { $lock = $this->rows('SELECT GET_LOCK('.$this->quote($lockName).',0) AS acquired'); }
        catch (Throwable $error) { return array('success'=>false,'code'=>'UPDATE_LOCK_UNAVAILABLE'); }
        if ($lock[0]['acquired'] === null) return array('success'=>false,'code'=>'UPDATE_LOCK_UNAVAILABLE');
        if ((int)$lock[0]['acquired'] !== 1) return array('success'=>false,'code'=>'UPDATE_BUSY');
        $timeout = null;
        try {
            $status = $this->status();
            if ($status['blocked']) return array('success'=>false,'code'=>'MANUAL_SCHEMA_REVIEW_REQUIRED');
            if (!$status['pending']) return array('success'=>true,'code'=>'ALREADY_CURRENT');
            $timeout = (int)$this->rows('SELECT @@SESSION.lock_wait_timeout AS value')[0]['value'];
            $this->rows('SET SESSION lock_wait_timeout=5');
            $metaName = $this->table('schema_version_table');
            $meta = $this->identifier($metaName);
            // Preflight metadata CREATE permission before touching application fields.
            if (!isset($this->tables()[$metaName])) $this->rows('CREATE TABLE '.$meta.' (id TINYINT NOT NULL PRIMARY KEY,version INT NOT NULL,schema_hash CHAR(64) NOT NULL,applied_at BIGINT NOT NULL) ENGINE=InnoDB');
            foreach ($status['operations'] as $operation) $this->rows($operation['sql']);
            $remaining = $this->status();
            if ($remaining['blocked'] || $remaining['operations']) throw new RuntimeException('SCHEMA_VERIFICATION_FAILED');
            $statement = $this->connection->prepare('REPLACE INTO '.$meta.' (id,version,schema_hash,applied_at) VALUES (1,?,?,?)');
            if ($statement === false) throw new mysqli_sql_exception('VERSION_WRITE_FAILED',$this->connection->errno);
            $version=$this->targetVersion; $hash=$this->hash; $now=time();
            $statement->bind_param('isi',$version,$hash,$now);
            if (!$statement->execute()) throw new mysqli_sql_exception('VERSION_WRITE_FAILED',$statement->errno);
            $statement->close();
            return array('success'=>true,'code'=>'UPDATED','version'=>$this->targetVersion);
        } catch (Throwable $error) {
            // DDL is not transactional. All operations are additive; keep data and allow safe retry.
            error_log('Minitalk schema update failed: '.get_class($error).' code '.$error->getCode());
            if ($error instanceof mysqli_sql_exception && in_array($error->getCode(),array(1062,1048,1138,1171),true)) return array('success'=>false,'code'=>'MANUAL_SCHEMA_REVIEW_REQUIRED');
            if ($error instanceof mysqli_sql_exception && in_array($error->getCode(),array(1044,1142,1143,1227),true)) return array('success'=>false,'code'=>'DATABASE_PERMISSION_REQUIRED');
            if ($error instanceof mysqli_sql_exception && in_array($error->getCode(),array(1235,1845,1846),true)) return array('success'=>false,'code'=>'ONLINE_UPDATE_UNSUPPORTED');
            return array('success'=>false,'code'=>'DATABASE_UPDATE_FAILED');
        } finally {
            if ($timeout !== null) { try { $this->rows('SET SESSION lock_wait_timeout='.$timeout); } catch (Throwable $ignored) {} }
            try { $this->rows('SELECT RELEASE_LOCK('.$this->quote($lockName).')'); } catch (Throwable $ignored) {}
        }
    }
}
