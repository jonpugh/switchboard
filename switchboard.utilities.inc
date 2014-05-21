<?php
/**
 * @file
 * General purpose utilities.
 */

/**
 * Send a request.
 * @param \Fluxsauce\Switchboard\Provider $provider
 * @param $options
 * @return bool|Requests_Response
 */
function switchboard_request($provider, $options) {
  $url = $provider->endpoint;

  if ($provider->name == 'pantheon') {
    $url .= '/terminus.php?';
  }

  $url .= $options['resource'];

  if ($provider->name == 'pantheon') {
    $url .= '=' . $options['uuid'];
    $url .= '&path=' . urlencode($options['realm']);
  }
  if ($provider->name == 'acquia') {
    $url .= '.json';
  }

  $requests_options = $provider->requests_options();

  if (!in_array($options['method'], array('GET', 'DELETE', 'POST', 'PUT'))) {
    return drush_set_error('SWITCHBOARD_REQUEST_INVALID_METHOD');
  }
  $headers = array();

  // Prepare data for transport.
  $data = NULL;
  if (isset($options['data'])) {
    $data = json_encode(array('data' => $options['data']));
    if (in_array($options['method'], array('POST', 'PUT', 'DELETE'))) {
      $headers = array(
        'Content-Type' => 'application/json',
        'Content-Length' => strlen($data),
      );
    }
    else {
      if (in_array($options['method'], array('GET'))) {
        $url .= '?' . http_build_query($data);
      }
    }
  }

  // Perform request.
  drush_log(dt('Making API @method request to @url', array(
    '@method' => $options['method'],
    '@url' => $url,
  )));

  try {
    switch ($options['method']) {
      case 'GET':
        $response = Requests::get($url, $headers, $requests_options);
        break;

      case 'DELETE':
        $response = Requests::delete($url, $headers, $requests_options);
        break;

      case 'POST':
        $response = Requests::post($url, $headers, $data, $requests_options);
        break;

      case 'PUT':
        $response = Requests::put($url, $headers, $data, $requests_options);
        break;

    }
  } catch (Requests_Exception $e) {
    return drush_set_error('SWITCHBOARD_REQUEST_FAIL', dt('API failure: @error', array(
      '@error' => $e->getMessage(),
    )));
  }
  return $response;
}

/**
 * Get the list of providers supported by Switchboard.
 *
 * @return array
 *   Machine names of providers.
 */
function switchboard_get_provider_names() {
  // Hard coding for now.
  // Later, be clever and scan the directory containing Provider definitions.
  $provider_names = array(
    'acquia',
    'pantheon',
  );
  return $provider_names;
}

/**
 * Helper for debugging PDO exceptions.
 * @param PDOException $e
 */
function switchboard_pdo_exception_debug(PDOException $e) {
  $trace = $e->getTrace();
  drush_set_error(dt('PDO Exception in @file on line @line: "@message"', array(
    '@file' => $e->getFile(),
    '@line' => $e->getLine(),
    '@message' => $e->getMessage(),
  )));
  drush_print(var_export($trace[0], TRUE));
  exit;
}

function switchboard_message_success($message) {
  if (drush_get_option('json')) {
    drush_set_context('DRUSH_USER_ABORT', TRUE);
    drush_print(json_encode(array('success' => $message)));
    exit;
  }
  drush_log($message, 'success');
}

function switchboard_message_fail($code, $message) {
  if (drush_get_option('json')) {
    drush_set_context('DRUSH_USER_ABORT', TRUE);
    drush_print(json_encode(array('error' => $message)));
    exit;
  }
  return drush_set_error($code, $message);
}

/**
 * Generate a UUID.
 *
 * @link http://www.php.net/manual/en/function.uniqid.php#94959
 * @return string
 */
function switchboard_generate_uuid() {
  return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    // 32 bits for "time_low"
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),

    // 16 bits for "time_mid"
    mt_rand(0, 0xffff),

    // 16 bits for "time_hi_and_version",
    // four most significant bits holds version number 4
    mt_rand(0, 0x0fff) | 0x4000,

    // 16 bits, 8 bits for "clk_seq_hi_res",
    // 8 bits for "clk_seq_low",
    // two most significant bits holds zero and one for variant DCE1.1
    mt_rand(0, 0x3fff) | 0x8000,

    // 48 bits for "node"
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
  );
}