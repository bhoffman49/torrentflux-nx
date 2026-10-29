<?php
if (!defined("ADODB_DIR")) die();

class ADODB_sqlite extends ADOConnection {
	var $databaseType = "sqlite";
	var $replaceQuote = "''";
	var $concat_operator = "||";
	var $_errorNo = 0;
	var $hasLimit = true;
	var $hasInsertID = true;
	var $hasAffectedRows = true;
	var $metaTablesSQL = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name";
	var $sysDate = "date('now')";
	var $sysTimeStamp = "datetime('now')";
	var $fmtTimeStamp = "'Y-m-d H:i:s'";
	var $_pdo = null;
	var $_stmt = null;
	var $_insertid = 0;
	var $_affected = 0;

	function ADODB_sqlite() {}

	function ServerInfo() {
		$arr["version"] = $this->GetOne("SELECT sqlite_version()");
		$arr["description"] = "SQLite via PDO";
		$arr["encoding"] = "UTF-8";
		return $arr;
	}

	function BeginTrans() { if ($this->transOff) return true; $this->Execute("BEGIN"); $this->transCnt += 1; return true; }
	function CommitTrans($ok = true) { if ($this->transOff) return true; if (!$ok) return $this->RollbackTrans(); $this->Execute("COMMIT"); if ($this->transCnt > 0) $this->transCnt -= 1; return true; }
	function RollbackTrans() { if ($this->transOff) return true; $this->Execute("ROLLBACK"); if ($this->transCnt > 0) $this->transCnt -= 1; return true; }

	function ErrorMsg() { return $this->_errorMsg; }
	function ErrorNo() { return $this->_errorNo; }

	function _connect($argHostname, $argUsername, $argPassword, $argDatabasename) {
		if (!class_exists("PDO")) return null;
		try {
			$dsn = "sqlite:" . $argDatabasename;
			$this->_pdo = new PDO($dsn);
			$this->_pdo->exec("PRAGMA journal_mode=WAL");
			$this->_pdo->exec("PRAGMA foreign_keys=ON");
			return true;
		} catch (PDOException $e) {
			$this->_errorMsg = $e->getMessage();
			return false;
		}
	}

	function _pconnect($argHostname, $argUsername, $argPassword, $argDatabasename) {
		return $this->_connect($argHostname, $argUsername, $argPassword, $argDatabasename);
	}

	function _query($sql, $inputarr = false) {
		$this->_errorNo = 0;
		$this->_errorMsg = "";
		// Handle empty/false SQL gracefully
		if (empty($sql) || !is_string($sql)) {
			$this->_errorMsg = "Empty SQL statement";
			$this->_errorNo = -1;
			return false;
		}
		try {
			if (preg_match("/^\\s*SELECT/i", $sql)) {
				$this->_stmt = $this->_pdo->query($sql);
				if ($this->_stmt) return $this->_stmt;
			} else {
				$this->_affected = $this->_pdo->exec($sql);
				if (preg_match("/INSERT/i", $sql)) {
					$r = $this->_pdo->query("SELECT last_insert_rowid()");
					if ($r) { $row = $r->fetch(0); if ($row) $this->_insertid = $row[0]; }
				}
				return ($this->_affected !== false);
			}
		} catch (PDOException $e) {
			$this->_errorMsg = $e->getMessage();
			$this->_errorNo = -1;
			return false;
		}
		return false;
	}

	function _insertid() { return $this->_insertid; }
	function _affectedrows() { return $this->_affected; }

	function _close() {
		try { $this->_pdo = null; return true; }
		catch (PDOException $e) { return false; }
	}

	function &SelectLimit($sql, $nrows = -1, $offset = -1, $inputarr = false, $secs2cache = 0) {
		$offsetStr = ($offset >= 0) ? " OFFSET $offset" : "";
		$limitStr = ($nrows >= 0) ? " LIMIT $nrows" : ($offset >= 0 ? " LIMIT 999999999" : "");
		if ($secs2cache) $rs =& $this->CacheExecute($secs2cache, $sql . "$limitStr$offsetStr", $inputarr);
		else $rs =& $this->Execute($sql . "$limitStr$offsetStr", $inputarr);
		return $rs;
	}

	var $_genSeqSQL = "create table %s (id integer)";

	function GenID($seq = 'adodbseq', $start = 1) {
		$MAXLOOPS = 100;
		while (--$MAXLOOPS >= 0) {
			$num = $this->GetOne("select id from $seq");
			if ($num === false) {
				$this->Execute(sprintf($this->_genSeqSQL, $seq));
				$start -= 1;
				$num = '0';
				$ok = $this->Execute("insert into $seq values($start)");
				if (!$ok) return false;
			}
			$this->Execute("update $seq set id=id+1 where id=$num");
			if ($this->affected_rows() > 0) {
				$num += 1;
				$this->genID = $num;
				return $num;
			}
		}
		if ($fn = $this->raiseErrorFn) $fn($this->databaseType, 'GENID', -32000, "Unable to generate unique id", $seq, $num);
		return false;
	}

	function CreateSequence($seqname = 'adodbseq', $start = 1) {
		if (empty($this->_genSeqSQL)) return false;
		if (!$this->Execute(sprintf($this->_genSeqSQL, $seqname))) return false;
		$start -= 1;
		return $this->Execute("insert into $seqname values($start)");
	}

	var $_dropSeqSQL = 'drop table %s';
	function DropSequence($seqname = 'adodbseq') {
		if (empty($this->_dropSeqSQL)) return false;
		return $this->Execute(sprintf($this->_dropSeqSQL, $seqname));
	}

	function &MetaColumns($table, $normalize = true) {
		$ret = array();
		try {
			$st = $this->_pdo->query("PRAGMA table_info(" . preg_replace('/[^A-Za-z0-9_]/', '', $table) . ")");
			if ($st) foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
				$o = new ADOFieldObject();
				$o->name = $r['name'];
				$o->type = strtoupper(preg_replace('/\(.*$/', '', $r['type']));
				$o->max_length = -1;
				$ret[strtoupper($r['name'])] = $o;
			}
		} catch (PDOException $e) {}
		if (!$ret) { $f = false; return $f; }
		return $ret;
	}

	// Bypass ADOdb Execute chain — use PDO directly for scalar queries
	function GetOne($sql, $inputarr = false) {
		if (!empty($sql)) {
			try {
				$stmt = $this->_pdo->query($sql);
				if ($stmt) {
					$rows = $stmt->fetchAll(PDO::FETCH_NUM);
					if ($rows && sizeof($rows) > 0) {
						return $rows[0][0];
					}
				}
			} catch (PDOException $e) {
				// Query failed, return false
			}
		}
		return false;
	}
}

class ADORecordset_sqlite extends ADORecordSet {
	var $databaseType = "sqlite";
	var $_stmt = null;
	var $_numFields = 0;
	var $_fieldNames = array();
	var $_allRows = array();
	var $_rowIndex = 0;

	function __construct($queryID, $mode = false) {
		$this->_queryID = $queryID;
		$this->fields = array();
		if (is_object($queryID)) {
			$this->_currentRow = -1;
			$this->_stmt = $queryID;
			// Get column info
			$this->_numOfFields = $queryID->columnCount();
			$this->_fieldNames = array();
			for ($i = 0; $i < $this->_numOfFields; $i++) {
				$meta = $queryID->getColumnMeta($i);
				$this->_fieldNames[] = $meta["name"];
			}
			// Buffer ALL rows using fetchAll (fetch() doesn't work in this context)
			$rows = $queryID->fetchAll(PDO::FETCH_NUM);
			$this->_allRows = $rows ? $rows : array();
			$this->_rowIndex = -1; // Init() calls _fetch() for the first row
			$this->_numOfRows = sizeof($this->_allRows);
			$this->EOF = ($this->_numOfRows == 0);
		} else {
			$this->_numOfRows = 0;
			$this->_numOfFields = 0;
			$this->EOF = true;
		}
	}

	function _populateFields() {
		$this->fields = array();
		$row = $this->_allRows[$this->_rowIndex];
		for ($i = 0; $i < $this->_numOfFields; $i++) {
			$name = $this->_fieldNames[$i];
			$val = $row[$i];
			if (isset($val)) {
				$this->fields[$name] = $val;
				$this->fields[strtoupper($name)] = $val;
				$this->fields[$i] = $val;
			} else {
				$this->fields[$name] = null;
				$this->fields[strtoupper($name)] = null;
				$this->fields[$i] = null;
			}
		}
	}

	function _initrs() {
		// Fields already initialized in constructor
	}

	function Fields($colname) { return $this->fields[strtoupper($colname)]; }

	function _seek($row) { return false; }

	function _fetch($ignore_fields = false) {
		$this->_rowIndex++;
		if ($this->_rowIndex >= $this->_numOfRows) {
			$this->fields = array();
			return false;
		}
		$this->_populateFields();
		return true;
	}

	function &FetchField($fieldOffset = -1) {
		$o = new ADOFieldObject();
		$o->name = isset($this->_fieldNames[$fieldOffset]) ? $this->_fieldNames[$fieldOffset] : '';
		$o->type = 'VARCHAR';
		$o->max_length = -1;
		return $o;
	}

	function FieldCount() { return $this->_numOfFields; }

	function _close() {}
}