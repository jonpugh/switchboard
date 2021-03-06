<?php
/**
 * @file
 * Remote Site structure.
 */

namespace Fluxsauce\Switchboard;

class Site extends Persistent {
  protected $provider;
  protected $uuid;
  protected $realm;
  protected $title;
  protected $unixUsername;
  protected $vcsUrl;
  protected $vcsType;
  protected $vcsProtocol;
  protected $sshPort;

  protected $environments;

  protected $externalKeyName = 'provider';

  /**
   * Magic __get, overriding Persistent.
   *
   * @param string $name
   *   Name of the property.
   *
   * @return mixed
   *   Value of set property.
   * @throws \Exception
   */
  public function __get($name) {
    $name = switchboard_to_camel_case($name);
    $value = parent::__get($name);
    if (is_null($value) || drush_get_option('refresh')) {
      $callers = debug_backtrace();
      drush_log(dt('Site @site_name is missing value for @name from @calling_function.', array(
        '@site_name' => $this->name,
        '@name' => $name,
        '@calling_function' => $callers[1]['function'],
      )));
      if ($this->provider) {
        $provider =& Provider::getInstance($this->provider);
        $this->$name = $value = $provider->siteGetField($this->name, $name);
      }
    }
    return $value;
  }

  /**
   * Helper to add an environment to a Site.
   *
   * @param Environment $environment
   *   Environment to add.
   */
  public function environmentAdd(Environment $environment) {
    if (!is_array($this->environments)) {
      $this->environments = array();
    }
    $this->environments[$environment->name] = $environment;
  }

  /**
   * Helper to remove an environment from a Site.
   *
   * @param Environment $environment
   *   Environment to remove.
   */
  public function environmentRemove(Environment $environment) {
    unset($this->environments[$environment->name]);
  }

  /**
   * Read a site.
   */
  public function read() {
    parent::read();
    $pdo = Sqlite::get();
    // Environments.
    try {
      $sql_query = 'SELECT name ';
      $sql_query .= 'FROM environments ';
      $sql_query .= 'WHERE siteId = :id ';
      $stmt = $pdo->prepare($sql_query);
      $stmt->bindParam(':id', $this->id);
      $result = $stmt->execute();
      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $this->environmentAdd(new Environment($this->id, $row['name']));
      }
    }
    catch (\PDOException $e) {
      switchboard_pdo_exception_debug($e);
    }
  }

  /**
   * Render a Site's environments as a Drush table.
   */
  public function renderEnvironmentsDrushTable() {
    $rows = array();
    $environment = new Environment();
    $fields = $environment->toArray();
    $rows = array();
    $rows[] = array_keys($fields);
    foreach ($this->__get('environments') as $environment) {
      $fields = $environment->toArray();
      $rows[] = array_values($fields);
    }
    drush_print_table($rows, TRUE);
  }

  /**
   * Render a Site's environments as JSON.
   */
  public function renderEnvironmentsJson() {
    $rows = array();
    foreach ($this->__get('environments') as $environment) {
      $rows[] = $environment->toArray();
    }
    drush_print(json_encode($rows));
  }

  /**
   * Dump to an array.
   *
   * @return array
   *   Property names and values.
   */
  public function toArray() {
    $fields = parent::toArray();
    unset($fields['environments']);
    return $fields;
  }

  /**
   * Build VCS URL.
   *
   * @return string
   *   A full VCS connection URL.
   */
  public function getVcsUrl() {
    $url = '';
    if ($this->__get('vcsProtocol') == 'ssh') {
      $url .= 'ssh://';
    }
    $url .= $this->__get('vcsUrl');
    return $url;
  }
}
