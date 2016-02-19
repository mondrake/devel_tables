<?php

namespace Drupal\devel_tables;

use Drupal\Core\Cache\Cache;

require_once "/home/mondrak1/private/dbol/Dbol.php";
 
class drupalTableObj {
	protected static $dbol = array();
	protected static $dbObj = array();
	protected static $childObjs = array();
	protected $diag = null;
	protected $tableName;    
  private $_connection;
    
	public function __construct($connection, $table = null, $DTTable = null) {	
    $this->_connection = $connection;
    if (!isset(self::$dbol[$connection]))     {
      $variables = array(
        'DBAL' => 'Drupal8',
      );
      self::$dbol[$connection] = new \dbol($variables);
      db_set_active($connection);
      self::$dbol[$connection]->mount();
      db_set_active();
		}
		//$this->diag = new MMDiag;
        if ($table) {
            $this->tableName = $connection . ':'. $table;
            if (!isset(self::$dbObj[$this->tableName])) {
                // tries to get dbolObj from cache
                $cg = \Drupal::cache()->get("devel_tables:$connection:dbolObj:$table");
                if ($cg) {
                    self::$dbObj[$this->tableName] = $cg->data;
                    return;
                } else {
                // if not cached, builds dbolObj
                    $dbolObj = self::$dbObj[$this->tableName] = new \dbolEntry;
                    $dbolObj->table = $table;
                    $dbolObj->tableProperties['auditLogLevel'] = 0;
                    $dbolObj->tableProperties['listOrder'] = null;
                    self::$dbol[$this->_connection]->fetchAllColumnsProperties($dbolObj);
                    $columnsProperties = self::$dbol[$this->_connection]->getColumnProperties($dbolObj);
                    foreach ($columnsProperties as $columnName => $columnProperties)    {
                        // $columnName has the field name
                        // $columnProperties has the field properties
                        // overrides comment with Drupal description
                        if ($DTTable['isDrupal'] && $DTTable['module'] !== '???') {
                            $schemaUnp = drupal_get_module_schema($DTTable['module']);
                            self::$dbol[$this->_connection]->setColumnProperty(
                                $dbolObj, 
                                array($columnName), 
                                'nativeComment', 
                                $columnProperties['comment']
                            );
                            if (isset($schemaUnp[$DTTable['name']]['fields'][$columnName]['description'])) {
                                $tmp = $schemaUnp[$DTTable['name']]['fields'][$columnName]['description'];
                            } else {
                                $tmp = null;
                            }
                            self::$dbol[$this->_connection]->setColumnProperty(
                                $dbolObj, 
                                array($columnName), 
                                'comment', 
                                $tmp
                            );
                        }
                        // determines type+length description
                        switch ($columnProperties['type'])    {
                        case 'boolean':
                        case 'integer':
                        case 'time':
                        case 'date':
                        case 'timestamp':
                            self::$dbol[$this->_connection]->setColumnProperty($dbolObj, array($columnName), 'typeLength', $columnProperties['type']);
                            break;
                        case 'text':
                        case 'blob':
                            if($columnProperties['length']) 
                                self::$dbol[$this->_connection]->setColumnProperty($dbolObj, array($columnName), 'typeLength', $columnProperties['type'] . '/' . $columnProperties['length']);
                            else
                                self::$dbol[$this->_connection]->setColumnProperty($dbolObj, array($columnName), 'typeLength', $columnProperties['type']);
                            break;
                        default:
                            self::$dbol[$this->_connection]->setColumnProperty($dbolObj, array($columnName), 'typeLength', $columnProperties['type'] . '/' . $columnProperties['length']);
                        }
                    }
                    //kpr($dbolObj); die;
                    \Drupal::cache()->set("devel_tables:$connection:dbolObj:$table", $dbolObj, Cache::PERMANENT); // @todo temporary
                }
            }
        }
	}

	public function getdbol()	{
		return self::$dbol[$this->_connection];
	}

	public function getDbObj()	{
		return self::$dbObj[$this->tableName];
	}

	public function fetchAllTables()	{
		$tables = self::$dbol[$this->_connection]->fetchAllTables();
        return $tables;
	}

  public function getTablePrefix($table) {
        return $table; //self::$dbol->getTablePrefix($table);
    }
	
	public function setDbolVariable($variables) {
	 	self::$dbol[$this->_connection]->setVariables($variables);
	}

	public function getDbolVariable($var = NULL) {
	 	return self::$dbol[$this->_connection]->getVariable($var);
	}

	public function getDBALVersion() {
	 	return self::$dbol[$this->_connection]->getDBALVersion();
	}

	public function getDBALDriver() {
	 	return self::$dbol[$this->_connection]->getDBALDriver();
	}

	public function getDbServer() {
	 	return self::$dbol[$this->_connection]->getDbServer();
	}

	public function getDbServerName() {
	 	return self::$dbol[$this->_connection]->getDbServerName();
	}

	public function getDbServerVersion() {
	 	return self::$dbol[$this->_connection]->getDbServerVersion();
	}

	public function setColumnProperty($cols, $prop, $value)     {
		return self::$dbol[$this->_connection]->setColumnProperty(self::$dbObj[$this->tableName], $cols, $prop, $value);
	}

	public function getColumnProperties()	{
		return self::$dbol[$this->_connection]->getColumnProperties(self::$dbObj[$this->tableName]);
	}

	public function setPKColumns($cols)     {
		return self::$dbol[$this->_connection]->setPKColumns(self::$dbObj[$this->tableName], $cols);
	}

	public function compactPKIntoString() {
		return self::$dbol[$this->_connection]->compactPKIntoString($this, self::$dbObj[$this->tableName]);
	}

	public function read($pk) {
		$whereClause = self::$dbol[$this->_connection]->explodePKStringIntoWhere(self::$dbObj[$this->tableName], $pk);
		return self::readSingle($whereClause);
	}
 
	public function readSingle($whereClause) {
		$ret = self::$dbol[$this->_connection]->readSingle($this, self::$dbObj[$this->tableName], $whereClause);
		return $ret;
	}
	
	public function readMulti($whereClause = NULL, $orderClause = NULL, $limit = NULL, $offset = NULL) {
		$ret = self::$dbol[$this->_connection]->readMulti($this, self::$dbObj[$this->tableName], $whereClause, $orderClause, $limit, $offset);
		return $ret;
	}

	public function count($whereClause = NULL) {
		$ret = self::$dbol[$this->_connection]->count($this, self::$dbObj[$this->tableName], $whereClause);
		return $ret;
	}

	public function listAll($whereClause = NULL, $limit = NULL, $offset = NULL) {
		return $this->readMulti($whereClause, self::$dbObj[$this->tableName]->tableProperties['listOrder'], $limit, $offset);
	}

	public function create($clientPKMap = false) {

		if ($this->validate() > 3)	{
			$table = $this->getDbObj()->table;
			throw new Exception("Validation error on create - Table: $table - PK: $this->primaryKeyString");
		}

		$res = self::$dbol[$this->_connection]->create($this, self::$dbObj[$this->tableName]);
		
		return $res;
	}

	public function createMulti($arr) {
		$res = 0;
		foreach ($arr as $c => $obj)	{
				$res += $obj->create();
		}
		return $res;
	}

	public function update() {
		return self::$dbol[$this->_connection]->update($this, self::$dbObj[$this->tableName]);
	}
		
	public function delete($clientPKMap = false) {
		return self::$dbol[$this->_connection]->delete($this, self::$dbObj[$this->tableName]);
	} 

	public function query($sqlq, $limit = NULL, $offset = NULL, $sqlId = NULL)	{
		return self::$dbol[$this->_connection]->query($sqlq, $limit, $offset, $sqlId);
	}

	public function beginTransaction()	{
		return self::$dbol[$this->_connection]->beginTransaction();
	}

	public function commit()	{
		return self::$dbol[$this->_connection]->commit();
	}

	public function executeSql($sqlq)	{
		return self::$dbol[$this->_connection]->executeSql($sqlq);
	}
	
	protected function validate() {
	}
	
	protected function diagLog($severity, $id, $params, $tableName = null, $throwExceptionOnError = FALSE) {
		if (empty($tableName))	{
			$tableName = get_class($this);
		}
		$this->diag->sLog($severity, $tableName, $id, $params);
		if ($severity == 4)	{
		   // prepares msg for exception
			foreach ($this->diag->get(FALSE) as $a)	{
				$msg .= $a->time . ' - ' . $a->severity . ' - ' . $a->tableName . ' - ' . $a->id . ' - ' . $a->fullText . " \n";
			}
			// logs backtrace
			if ($link->backtrace)	{
				foreach ($link->backtrace as $a => $b)	{
					$this->diag->sLog(4, 'backtrace', 0, array('#text' => $a . ' ' . $b['class'] . '/' . $b['function'] . ' in ' . $b['file'] . ' line ' . $b['line'],));
				}
			}
			if ($throwExceptionOnError)	{
				throw new Exception($msg);
			}
		}
    }

	public function startWatch($id)	{
		return $this->diag->startWatch($id);
	}

	public function getLog()	{
		return $this->diag->get();
	}

	public function __call($name, $args)
    {
        throw new Exception ('Undefined method ' . get_class($this) . '.' . $name . ' called');
    }

}
?>