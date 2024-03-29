<?php

class InstallRequirements {
  /**
   * Requirement severity -- Requirement successfully met.
   */
  const REQUIREMENT_OK = 0;

  /**
   * Requirement severity -- Warning condition; proceed but flag warning.
   */
  const REQUIREMENT_WARNING = 1;

  /**
   * Requirement severity -- Error condition; abort installation.
   */
  const REQUIREMENT_ERROR = 2;

  protected $system_checks = array(
    'checkMemory',
    'checkServerVariables',
    'checkMysqlConnectExists',
    'checkJsonEncodeExists',
  );

  protected $database_checks = array(
    'checkMysqlConnection',
    'checkMysqlVersion',
    'checkMysqlInnodb',
    'checkMysqlTempTables',
    'checkMySQLAutoIncrementIncrementOne',
    'checkMysqlTrigger',
    'checkMysqlThreadStack',
    'checkMysqlLockTables',
  );

  /**
   * Run all requirements tests.
   *
   * @param array $config
   *   An array with two keys:
   *     - file_paths
   *     - db_config
   *
   * @return
   *   An array of check summaries. Each array contains the keys 'title', 'severity', and 'details'.
   */
  public function checkAll(array $config) {
    return array_merge($this->checkSystem($config['file_paths']), $this->checkDatabase($config['db_config']));
  }

  /**
   * Check system requirements are met, such as sufficient memory,
   * necessary file paths are writable and required php extensions
   * are available.
   *
   * @param array $file_paths
   *   An array of file paths that will be checked to confirm they
   *   are writable.
   */
  public function checkSystem(array $file_paths) {
    $errors = array();

    $errors[] = $this->checkFilepathsWritable($file_paths);
    foreach ($this->system_checks as $check) {
      $errors[] = $this->$check();
    }

    return $errors;
  }

  /**
   * Check database connection, database version and other
   * database requirements are met.
   *
   * @param array $db_config
   *   An array with keys:
   *   - host (with optional port specified eg. localhost:12345)
   *   = database (name of database to select)
   *   - username
   *   - password
   */
  public function checkDatabase(array $db_config) {
    $errors = array();

    foreach ($this->database_checks as $check) {
      $errors[] = $this->$check($db_config);
    }

    return $errors;
  }

  public function checkMemory() {
    $min = 1024 * 1024 * 32;
    $recommended = 1024 * 1024 * 64;

    $mem = $this->getPHPMemory();
    $mem_string = ini_get('memory_limit');

    $results = array(
      'title' => 'CiviCRM memory check',
      'severity' => $this::REQUIREMENT_OK,
      'details' => "You have $mem_string allocated (minimum 32Mb, recommended 64Mb)",
    );

    if ($mem < $min && $mem > 0) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
    }
    else if ($mem < $recommended && $mem != 0) {
      $results['severity'] = $this::REQUIREMENT_WARNING;
    }
    else if ($mem == 0) {
      $results['details'] = "Cannot determine PHP memory allocation. Install only if you're sure you've allocated at least 32 MB.";
      $resulsts['severity'] = $this::REQUIREMENT_WARNING;
    }

    return $results;
  }

  protected function getPHPMemory() {
    $memString = ini_get("memory_limit");

    switch (strtolower(substr($memString, -1))) {
      case "k":
        return round(substr($memString, 0, -1) * 1024);
      case "m":
        return round(substr($memString, 0, -1) * 1024 * 1024);
      case "g":
        return round(substr($memString, 0, -1) * 1024 * 1024 * 1024);
      default:
        return round($memString);
    }
  }

  function checkServerVariables() {
    $results = array(
      'title' => 'CiviCRM PHP server variables',
      'severity' => $this::REQUIREMENT_OK,
      'details' => 'The required $_SERVER variables are set',
    );

    $required_variables = array('SCRIPT_NAME', 'HTTP_HOST', 'SCRIPT_FILENAME');
    $missing = array();

    foreach ($required_variables as $required_variable) {
      if (empty($_SERVER[$required_variable])) {
        $missing[] = '$_SERVER[' . $required_variable . ']';
      }
    }

    if ($missing) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'The following PHP variables are not set: ' . implode(', ', $missing);
    }

    return $results;
  }

  public function checkJsonEncodeExists() {
    $results = array(
      'title' => 'CiviCRM JSON encoding support',
      'severity' => $this::REQUIREMENT_OK,
      'details' => 'Function json_encode() found',
    );
    if (!function_exists('json_encode')) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Function json_encode() does not exist';
    }

    return $results;
  }

  public function checkMysqlConnectExists() {
    $results = array(
      'title' => 'CiviCRM MySQL check',
      'severity' => $this::REQUIREMENT_OK,
      'details' => 'Function mysql_connect() found',
    );
    if (!function_exists('mysql_connect')) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Function mysql_connect() does not exist';
    }

    return $results;
  }

  public function checkMysqlConnection(array $db_config) {
    $results = array(
      'title' => 'CiviCRM MySQL connection',
      'severity' => $this::REQUIREMENT_OK,
      'details' => "Connected",
    );

    $conn = @mysql_connect($db_config['host'], $db_config['username'], $db_config['password']);

    if (!$conn) {
      $results['details'] = mysql_error();
      $results['severity'] = $this::REQUIREMENT_ERROR;
      return $results;
    }

    if (!@mysql_select_db($db_config['database'], $conn)) {
      $results['details'] = mysql_error();
      $results['severity'] = $this::REQUIREMENT_ERROR;
      return $results;
    }

    return $results;
  }

  public function checkMysqlVersion(array $db_config) {
    $min = '5.1';
    $results = array(
      'title' => 'CiviCRM MySQL Version',
      'severity' => $this::REQUIREMENT_OK,
    );

    $conn = @mysql_connect($db_config['host'], $db_config['username'], $db_config['password']);
    if (!$conn || !($info = mysql_get_server_info($conn))) {
      $results['severity'] = $this::REQUIREMENT_WARNING;
      $results['details'] = "Cannot determine the version of MySQL installed. Please ensure at least version {$min} is installed.";
      return $results;
    }

    if (version_compare($info, $min) == -1) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = "MySQL version is {$info}; minimum required is {$min}";
      return $results;
    }

    $results['details'] = "MySQL version is {$info}";
    return $results;
  }

  public function checkMysqlInnodb(array $db_config) {
    $results = array(
      'title' => 'CiviCRM InnoDB support',
      'severity' => $this::REQUIREMENT_ERROR,
      'details' => 'Could not determine if MySQL has InnoDB support. Assuming none.'
    );

    $conn = @mysql_connect($db_config['host'], $db_config['username'], $db_config['password']);
    if (!$conn) {
      return $results;
    }

    $innodb_support = FALSE;
    $result = mysql_query("SHOW ENGINES", $conn);
    while ($values = mysql_fetch_array($result)) {
      if ($values['Engine'] == 'InnoDB') {
        if (strtolower($values['Support']) == 'yes' || strtolower($values['Support']) == 'default') {
          $innodb_support = TRUE;
          break;
        }
      }
    }

    if ($innodb_support) {
      $results['severity'] = $this::REQUIREMENT_OK;
      $results['details'] = 'MySQL supports InnoDB';
    }
    return $results;
  }

  public function checkMysqlTempTables(array $db_config) {
    $results = array(
      'title' => 'CiviCRM MySQL Temp Tables',
      'severity' => $this::REQUIREMENT_OK,
      'details' => 'MySQL server supports temporary tables',
    );

    $conn = @mysql_connect($db_config['host'], $db_config['username'], $db_config['password']);
    if (!$conn) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = "Could not connect to database";
      return $results;
    }

    if (!@mysql_select_db($db_config['database'], $conn)) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = "Could not select the database";
      return $results;
    }

    $r = mysql_query('CREATE TEMPORARY TABLE civicrm_install_temp_table_test (test text)', $conn);
    if (!$r) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = "Database does not support creation of temporary tables";
      return $results;
    }

    mysql_query('DROP TEMPORARY TABLE civicrm_install_temp_table_test');
    return $results;
  }

  public function checkMysqlTrigger($db_config) {
    $results = array(
      'title' => 'CiviCRM MySQL Trigger',
      'severity' => $this::REQUIREMENT_OK,
      'details' => 'Database supports MySQL triggers',
    );

    $conn = @mysql_connect($db_config['host'], $db_config['username'], $db_config['password']);
    if (!$conn) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not connect to database';
      return $results;
    }

    if (!@mysql_select_db($db_config['database'], $conn)) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = "Could not select the database";
      return $results;
    }

    $r = mysql_query('CREATE TABLE civicrm_install_temp_table_test (test text)', $conn);
    if (!$r) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not create a table to run test';
      return $results;
    }

    $r = mysql_query('CREATE TRIGGER civicrm_install_temp_table_test_trigger BEFORE INSERT ON civicrm_install_temp_table_test FOR EACH ROW BEGIN END');
    if (!$r) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Database does not support creation of triggers';
    }
    else {
      mysql_query('DROP TRIGGER civicrm_install_temp_table_test_trigger');
    }

    mysql_query('DROP TABLE civicrm_install_temp_table_test');
    return $results;
  }

  public function checkMySQLAutoIncrementIncrementOne(array $db_config) {
    $results = array(
      'title' => 'CiviCRM MySQL AutoIncrementIncrement',
      'severity' => $this::REQUIREMENT_OK,
      'details' => 'MySQL server auto_increment_increment is 1',
    );

    $conn = @mysql_connect($db_config['host'], $db_config['username'], $db_config['password']);
    if (!$conn) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not connect to database';
      return $results;
    }

    $r = mysql_query("SHOW variables like 'auto_increment_increment'", $conn);
    if (!$r) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not query database server variables';
      return $results;
    }

    $values = mysql_fetch_row($r);
    if ($values[1] != 1) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'MySQL server auto_increment_increment is not 1';
    }
    return $results;
  }

  public function checkMysqlThreadStack($db_config) {
    $min_thread_stack = 192;

    $results = array(
      'title' => 'CiviCRM Mysql thread stack',
      'severity' => $this::REQUIREMENT_OK,
      'details' => 'MySQL thread_stack is OK',
    );

    $conn = @mysql_connect($db_config['server'], $db_config['username'], $db_config['password']);
    if (!$conn) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not connect to database';
      return $results;
    }

    if (!@mysql_select_db($db_config['database'], $conn)) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not select the database';
      return $results;
    }

    $r = mysql_query("SHOW VARIABLES LIKE 'thread_stack'", $conn); // bytes => kb
    if (!$r) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not query thread_stack value';
    }
    else {
      $values = mysql_fetch_row($r);
      if ($values[1] < (1024 * $min_thread_stack)) {
        $results['severity'] = $this::REQUIREMENT_ERROR;
        $results['details'] = 'MySQL thread_stack is ' . ($values[1] / 1024) . "kb (minimum required is {$min_thread_stack} kb";
      }
    }

    return $results;
  }

  function checkMysqlLockTables($db_config) {
    $results = array(
      'title' => 'CiviCRM MySQL Lock Tables',
      'severity' => $this::REQUIREMENT_OK,
      'details' => 'Can successfully lock and unlock tables',
    );

    $conn = @mysql_connect($db_config['server'], $db_config['username'], $db_config['password']);
    if (!$conn) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] =  'Could not connect to database';
      return $results;
    }

    if (!@mysql_select_db($db_config['database'], $conn)) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not select the database';
      mysql_close($conn);
      return $results;
    }

    $r = mysql_query('CREATE TEMPORARY TABLE civicrm_install_temp_table_test (test text)', $conn);
    if (!$r) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not create a table';
      mysql_close($conn);
      return $results;
    }

    $r = mysql_query('LOCK TABLES civicrm_install_temp_table_test WRITE', $conn);
    if (!$r) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not obtain a write lock';
      mysql_close($conn);
      return $results;
    }

    $r = mysql_query('UNLOCK TABLES', $conn);
    if (!$r) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = 'Could not release table lock';
    }

    mysql_close($conn);
    return $results;
  }

  public function checkFilepathsWritable($file_paths) {
    $results = array(
      'title' => 'CiviCRM directories are writable',
      'severity' => $this::REQUIREMENT_OK,
      'details' => 'All required directories are writable: ' . implode(', ', $file_paths),
    );

    $unwritable_dirs = array();
    foreach ($file_paths as $path) {
      if (!is_writable($path)) {
        $unwritable_dirs[] = $path;
      }
    }

    if ($unwritable_dirs) {
      $results['severity'] = $this::REQUIREMENT_ERROR;
      $results['details'] = "The following directories need to be made writable by the webserver: " . implode(', ', $unwritable_dirs);
    }

    return $results;
  }
}