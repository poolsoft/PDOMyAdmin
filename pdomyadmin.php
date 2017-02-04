<?php
	session_start();
	error_reporting(E_ALL);
	ini_set('display_errors',1);
	date_default_timezone_set('Asia/Jakarta');


	if(isset($_GET['sign']) && $_GET['sign']=='out') {
		unset($_SESSION['pdomyadmin']);
	}
	
	if(!empty($_POST['user']) && !empty($_POST['pass'])) {
		$_SESSION['pdomyadmin'] = array(
			'rdbms'	=> $_POST['rdbms'],
			'host'	=> $_POST['host'],
			'port'	=> $_POST['port'],
			'name'	=> $_POST['name'],
			'user'	=> $_POST['user'],
			'pass'	=> $_POST['pass']			
		);	
	}
	
	if(!isset($_SESSION['pdomyadmin'])) {
		$tmp = "<form action=\"?\" method=\"post\" style=\"font-family:arial;\">";
		$tmp .= "<table cellpadding=\"4\">";
		$tmp .= "<tr><td>RDBMS</td><td><input name=\"rdbms\" value=\"mysql\" /><br /></td></tr>";
		$tmp .= "<tr><td>Host</td><td><input name=\"host\" value=\"localhost\" /><br /></td></tr>";
		$tmp .= "<tr><td>Port</td><td><input name=\"port\" value=\"3306\" /><br /></td></tr>";
		$tmp .= "<tr><td>DB Name</td><td><input autocomplete=\"off\" name=\"name\" value=\"\" /><br /></td></tr>";
		$tmp .= "<tr><td>Username</td><td><input autocomplete=\"off\" name=\"user\" value=\"\" /><br /></td></tr>";
		$tmp .= "<tr><td>Password</td><td><input autocomplete=\"off\" name=\"pass\" type=\"password\" value=\"\" /><br /></td></tr>";
		$tmp .= "<tr><td></td><td><input type=\"submit\" value=\"Login\" /></td></tr>";
		$tmp .= "</table>";
		$tmp .= "</form>";
		echo($tmp);
	}
	else {
		new PDOMyAdmin (array(
			'rdbms'	=> $_SESSION['pdomyadmin']['rdbms'],
			'host'	=> $_SESSION['pdomyadmin']['host'],
			'port'	=> $_SESSION['pdomyadmin']['port'],
			'name'	=> $_SESSION['pdomyadmin']['name'],
			'user'	=> $_SESSION['pdomyadmin']['user'],
			'pass'	=> $_SESSION['pdomyadmin']['pass']
		));
	}


	class PDOMyAdmin {
		private $link, $sql, $result, $columns, $benchmark, $error, $affected, $DBStructure;
		
		function __construct($params) {
			$this->connect($params);
			$this->getDBStructure();
			
			$this->sql = !empty($_GET['db']) && !empty($_GET['tb'])?"select\n*\nfrom ".$_GET['tb']."\nlimit 10":"";
			$this->sql = !empty($_POST['sql'])?$_POST['sql']:$this->sql;
			
			$this->result = !empty($this->sql)?$this->query($this->sql):"";
			
			if(isset($_POST['output']) && $_POST['output']=='Excel') $this->download();
			else $this->viewHTML($params['rdbms']);
			
		}
		
		private function getDBStructure() {
			$dbNames = $this->query('show databases');
			if(!empty($_GET['db'])) {
				$dbName[$_GET['db']] = $this->query('show tables from '.$_GET['db']);
				$dbKey = array_search($_GET['db'],$dbNames);
				$dbNames[$dbKey]  = $dbName;
				if(!empty($_GET['tb'])) {
					$this->link->exec('use '.$_GET['db']);
					$tableName[$_GET['tb']] = $this->query('show columns from '.$_GET['tb']);
					$dbNames[$dbKey][$_GET['db']][array_search($_GET['tb'],$dbNames[$dbKey][$_GET['db']])] = $tableName;
				}
			}			
			$this->DBStructure = $dbNames;
			$this->columns = "";
		}
		
		private function connect($database) {
			try {
				$dbname = !empty($_SESSION['pdomyadmin']['name'])?"dbname=".$_SESSION['pdomyadmin']['name'].";":"";
				$this->link = new PDO($database['rdbms'].":hostname=".$database['host'].";".$dbname."port=".$database['port'],$database['user'],$database['pass']);
				$this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
			catch(PDOException $e) {
				unset($_SESSION['pdomyadmin']);
				echo($e->getMessage()."<br /><a href=\"?\">Try again</a>"); exit;
			}
		}
		
		private function query($sql) {
			$result = Array();
			try {
				$time_start = microtime(true);
				$query = $this->link->query($sql);
				$this->affected = $query->rowCount();

				$manipulation = array('insert','update','delete');				
				$command = trim(strtok(strtolower(preg_replace('/[^a-zA-Z0-9\s]/',' ', $sql)),' '));
				if($command=='show') $result = $query->fetchAll(PDO::FETCH_COLUMN);
				elseif(!in_array($command,$manipulation)) $result = $query->fetchAll(PDO::FETCH_ASSOC);

				$time_end = microtime(true);
				$this->benchmark = ", ".substr(($time_end-$time_start),0,6)." seconds";
				if($query->columnCount() > 0) {
					foreach(range(0, $query->columnCount() - 1) as $columns) {
						$meta = $query->getColumnMeta($columns);
						$this->columns[] = $meta['name'];
					}
				}
			}
			catch(PDOException $e) {
				$this->affected = 0;
				$this->error = "<div style=\"color:red\">".$e->getMessage()."</div>";
			}
			return($result);
		}
		
		private function download(){
			header("Content-type: text/csv");
			header("Content-Disposition: attachment; filename=file.csv");
			header("Pragma: no-cache");
			$tmp = implode(',',$this->columns);
			foreach($this->result as $rows => $row) {
				$tmp .= "\r";
				$col = array();
				foreach($row as $data) {
					$col[] = strpos($data,',')?"\"".$data."\"":$data;
				}
				$tmp .= implode(',',$col);
			}
			echo($tmp);
		}
		
		private function viewHTML($rdbms) {
			$tmp = "<html>";
			$tmp .= "<head>";
			$tmp .= "<title>PDOMyAdmin</title>";
			$tmp .= "<style>";
			$tmp .= "body{font-family:arial}";
			$tmp .= "ul,li{margin:0;}";
			$tmp .= "a,li a{color:black;text-decoration:none;}";
			$tmp .= "textarea{width:700px;background-color:rgb(250,250,250);font-size:16px;}";
			$tmp .= "input[type=\"submit\"]{cursor:pointer;font-size:16px;color:white;background-color:green}";
			$tmp .= "textarea,input{outline:0px!important;-webkit-appearance:none;}";	
			$tmp .= "#content{font-size:15px;font-family:arial;border-collapse:collapse;}";
			$tmp .= "#content,#content td{border:1px solid gray;}";
			$tmp .= "</style>";
			$tmp .= "</head>";
			$tmp .= "<body>";
			$tmp .= "<table>";
			$tmp .= "<tr>";
			$tmp .= "<td valign=\"top\" width=\"270\">";
			$tmp .= "<a href=\"?\">".strtoupper($rdbms)."</a>";
			$tmp .= "<ul>";
			foreach($this->DBStructure as $dbNames => $dbName) {
				if(!is_array($dbName)) {
					$tmp .= "<li><a href=\"?db=".$dbName."\">".$dbName."</a></li>";
				}
				else {
					foreach($dbName as $selectedDB => $tableNames) {
						$tmp .= "<li><a href=\"?db=".$selectedDB."\">".$selectedDB."</a></li>";
						$tmp .= "<ul>";
						foreach($tableNames as $tableName) {
							if(!is_array($tableName)) {
								$tmp .= "<li><a href=\"?db=".$_GET['db']."&tb=".$tableName."\">".$tableName."</a></li>";
							}
							else {
								foreach($tableName as $selectedTable => $columnNames) {
									$tmp .= "<li><a href=\"?db=".$_GET['db']."&tb=".$selectedTable."\">".$selectedTable."</a></li>";
									$tmp .= "<ul>";
									foreach($columnNames as $columnName) {
										$tmp .= "<li>".$columnName."</li>";
									}
									$tmp .= "</ul>";
								}
							}
						}
						$tmp .= "</ul>";
					}
				}
			}
			$tmp .= "</ul>";
			$tmp .= "</td>";
			$tmp .= "<td valign=\"top\">";
		
			$dbName = !empty($_GET['db'])?$_GET['db']:'';
			$tbName = !empty($_GET['tb'])?$_GET['tb']:'';
			
			$dbLabel = !empty($dbName)?'Database '.$dbName:'PDOMyAdmin';
			$tbLabel = !empty($tbName)?' &gt; Table '.$tbName:'';
		
			$tmp .= "<table cellpadding=\"0\" cellspacing=\"0\" width=\"700\">";
			$tmp .= "<tr>";
			$tmp .= "<td><h2>".$dbLabel.$tbLabel."</h2></td>";
			$tmp .= "<td align=\"right\"><a href=\"?sign=out\">Sign Out</a></td>";
			$tmp .= "</tr>";
			$tmp .= "</table>";
			$tmp .= "<form action=\"?db=".$dbName."&tb=".$tbName."\" method=\"post\">";
			$tmp .= "<textarea rows=\"10\" name=\"sql\" autocomplete=\"off\" autocorrect=\"off\" spellcheck=\"false\" autocapitalize=\"off\">".$this->sql."</textarea><br /><br />";
			$tmp .= "<table cellpadding=\"0\" cellspacing=\"0\" width=\"700\">";
			$tmp .= "<tr>";
			$tmp .= "<td>";
			$tmp .= "<input type=\"submit\" name=\"output\" value=\"Execute\"> ";
			$tmp .= "<input type=\"submit\" name=\"output\" value=\"Excel\"> ";
			$tmp .= "</td>";
			$tmp .= "<td align=\"right\">";
			$tmp .= is_array($this->result) && $this->affected>0?$this->affected." rows affected".$this->benchmark:"";
			$tmp .= "</td>";
			$tmp .= "</tr>";
			$tmp .= "<table>";
			$tmp .= "<form><br /><br />";
			
			if(!empty($this->error)) {
				$tmp .= "".$this->error;
			}
			elseif(is_array($this->result) && count($this->result) > 0) {
				$tmp .= "<table id=\"content\" cellpadding=\"3\">";
				$tmp .= "<tr bgcolor=\"orange\">";
				foreach($this->columns as $columnName) {
					$tmp .= "<td>".$columnName."</td>";
				}
				$tmp .= "</tr>";
				foreach($this->result as $rows => $row) {
					$tmp .= "<tr>";
					foreach($row as $data) {
						$tmp .= "<td valign=\"top\">".$data."</td>";
					}
					$tmp .= "</tr>";
				}
				$tmp .= "</table>";
			}
			$tmp .= "</td>";
			$tmp .= "</tr>";			
			$tmp .= "</table>";
			$tmp .= "</body>";
			$tmp .= "</html>";
			
			echo($tmp);
		}
	}
?>