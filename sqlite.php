<?php
/**
 * Sqlite module simple function set
 * @author DarkPark, Urkaine Odessa 2011
 */

/**
 * Dumps the message
 * @param string $message 
 */
function mlog ( $message ) {
	echo "$message\n";
}

/**
 * Escapes sql string
 * @param string $data
 * @return string
 */
function db_escape ( $data ) {
	if ( $data ) {
		//$data = str_replace('"', '""', $data);
		$data = str_replace("'", "''", $data);
		return $data;
	}
}

/**
 * Executes sql query and log errors
 * @global PDO $dbh
 * @param string $sql
 * @return PDOStatement
 */
function db_query ( $sql ) {
	global $dbh;
	// check input
	if ( $dbh && $sql ) {
		$result = $dbh->query($sql);
		if ( $result ) {
			return $result;
		} else {
			$error = $dbh->errorInfo();
			mlog("ERRORDB:{$error[0]}:{$error[1]} :: {$error[2]} :: $sql");
		}
	}
}

/**
 * Gets all records from query
 * @param PDOStatement $pdos
 * @param string $field
 * @return array
 */
function db_array ( $pdos, $field = null, $field_grp = null ) {
	$result = array();
	if ( $pdos ) {
		$items = $pdos->fetchAll(PDO::FETCH_ASSOC);
		if ( $items ) {
			foreach ( $items as $item ) {
				if ( $field ) {
					if ( isset($item[$field]) ) {
						$id = $item[$field];
						unset($item[$field]);
						if ( isset($item[$field_grp]) ) {
							$id_grp = $item[$field_grp];
							unset($item[$field_grp]);
							$result[$id_grp][$id] = $item;
						} else {
							$result[$id] = $item;
						}
					}
				} else {
					if ( isset($item[$field_grp]) ) {
						$id_grp = $item[$field_grp];
						unset($item[$field_grp]);
						$result[$id_grp][] = $item;
					} else {
						$result[] = $item;
					}
				}
			}
		}
	}
	return $result;
}

/**
 * Returns the inserted id of the executed insert query
 * @global PDO $dbh
 * @param string $sql
 * @return int
 */
function db_insert ( $sql ) {
	global $dbh;
	if ( $dbh && $sql && db_query($sql) ) {
		return $dbh->lastInsertId();
	}
}

/**
 * Updates the sql query
 * @global PDO $dbh
 * @param string $sql 
 */
function db_update ( $sql ) {
	global $dbh;
	if ( $dbh && $sql ) {
		$dbh->query($sql);
	}
}

/**
 * Execs sql query
 * @global PDO $dbh
 * @param string $sql 
 */
function db_exec ( $sql ) {
	global $dbh;
	if ( $dbh && $sql ) {
		$dbh->exec($sql);
	}
}

?>