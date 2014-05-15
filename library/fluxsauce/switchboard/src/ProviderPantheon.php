<?php
/**
 * @file
 */

namespace Fluxsauce\Switchboard;

class ProviderPantheon extends Provider {
  protected $name = 'pantheon';
  protected $label = 'Pantheon';
  protected $homepage = 'https://www.getpantheon.com/';
  protected $endpoint = 'https://terminus.getpantheon.com';

  public function api_get_sites() {
    $user_uuid = drush_cache_get('user_uuid', 'switchboard-auth-pantheon');
    $result = switchboard_request($this, array(
      'method' => 'GET',
      'realm' => 'sites',
      'resource' => 'user',
      'uuid' => $user_uuid->data,
    ));
    $site_metadata = json_decode($result->body);

    $sites = array();

    foreach ($site_metadata as $uuid => $data) {
      $site = new Site($this->name, $data->information->name);
      $site->uuid = $uuid;
      $site->realm = $data->information->preferred_zone;
      $site->update();
      $this->sites[$site->name] = $site;
    }
  }

  public function requests_options_custom() {
    $options = array();
    $cookies = drush_cache_get('session', 'switchboard-auth-pantheon');
    if (isset($cookies->data)) {
      $options = array(
        'cookies' => array($cookies->data),
      );
    }
    return $options;
  }

  public function auth_login($email, $password) {
    $url = $this->endpoint . '/login';

    // Get the form build ID.
    try {
      $response = \Requests::post($url);
    }
    catch (\Requests_Exception $e) {
      return drush_set_error('SWITCHBOARD_AUTH_LOGIN_PANTHEON_ENDPOINT_UNAVAILABLE', dt('Pantheon endpoint unavailable: @error', array(
        '@error' => $e->getMessage(),
      )));
    }
    $form_build_id = $this->auth_login_get_form_build_id($response->body);
    if (!$form_build_id) {
      return drush_set_error('SWITCHBOARD_AUTH_LOGIN_PANTHEON_LOGIN_UNAVAILABLE', dt('Pantheon login unavailable.'));
    }

    // Attempt to log in.
    try {
      $response = \Requests::post($url, array(), array(
        'email' => $email,
        'password' => $password,
        'form_build_id' => $form_build_id,
        'form_id' => 'atlas_login_form',
        'op' => 'Login',
      ), $this->requests_options(array('follow_redirects' => FALSE)));
    }
    catch (\Requests_Exception $e) {
      return drush_set_error('SWITCHBOARD_AUTH_LOGIN_PANTHEON_LOGIN_FAILURE', dt('Pantheon login failure: @error', array(
        '@error' => $e->getMessage(),
      )));
    }

    $session = $this->auth_login_get_session_from_headers($response->headers->getValues('set-cookie'));

    if (!$session) {
      return drush_set_error('SWITCHBOARD_AUTH_LOGIN_PANTHEON_NO_SESSION', dt('Pantheon Session not found; please check your credentials and try again.'));
    }

    // Get the UUID.
    $user_uuid = array_pop(explode('/', $response->headers->offsetGet('Location')));
    if (!$this->validate_uuid($user_uuid)) {
      return drush_set_error('SWITCHBOARD_AUTH_LOGIN_PANTHEON_NO_UUID', dt('Pantheon User UUID not found; please check your credentials and try again.'));
    }

    drush_cache_clear_all('*', 'switchboard-auth-pantheon', TRUE);
    drush_cache_set('user_uuid', $user_uuid, 'switchboard-auth-pantheon');
    drush_cache_set('session', $session, 'switchboard-auth-pantheon');
    drush_cache_set('email', $email, 'switchboard-auth-pantheon');
    return TRUE;
  }

  public function auth_is_logged_in() {
    $session = drush_cache_get('session', 'switchboard-auth-pantheon');
    return $session->data ? TRUE : FALSE;
  }

  /**
   * Parse session out of a header, based on terminus_pauth_get_session_from_headers().
   * https://github.com/pantheon-systems/terminus
   *
   * @param array $headers
   * @return string
   */
  function auth_login_get_session_from_headers($headers) {
    $session = FALSE;
    foreach ($headers as $header) {
      foreach (explode('; ', $header) as $cookie) {
        if (strpos($cookie, 'SSESS') === 0) {
          $session = $cookie;
        }
      }
    }
    return $session;
  }

  /**
   * Parse form build ID, based on terminus_pauth_login_get_form_build_id().
   * https://github.com/pantheon-systems/terminus
   *
   * @param $html
   * @return string
   */
  public function auth_login_get_form_build_id($html) {
    if (!$html) {
      return FALSE;
    }
    // Parse form build ID.
    $DOM = new \DOMDocument;
    @$DOM->loadHTML($html);
    $login_form = $DOM->getElementById('atlas-login-form');
    if (!$login_form) {
      return FALSE;
    }

    foreach ($login_form->getElementsByTagName('input') as $input) {
      if ($input->getAttribute('name') == 'form_build_id') {
        return $input->getAttribute('value');
      }
    }
    return FALSE;
  }
}
