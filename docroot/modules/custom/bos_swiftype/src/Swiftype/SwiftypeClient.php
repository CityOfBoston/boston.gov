<?php
/**
 * @file
 * Library for connecting to Swiftype
 */

// @codingStandardsIgnoreFile

namespace Drupal\bos_swiftype\Swiftype;

class SwiftypeClient
{
  private $username;
  private $password;
  private $api_key;
  private $host;
  private $api_base_path;

  public function __construct($username = null, $password = null, $api_key = null, $host = 'https://api.swiftype.com', $api_base_path = '/api/v1/')
  {
    $this->username      = $username;
    $this->password      = $password;
    $this->api_key       = ($api_key ?? getenv("bos_swiftype_auth_token"));
    $this->host          = $host;
    $this->api_base_path = $api_base_path;

    if (!function_exists('curl_init')) {
      throw new \Exception('Swiftype requires the CURL PHP extension.');
    }

    if (!function_exists('json_decode')) {
      throw new \Exception('Swiftype requires the JSON PHP extension.');
    }
  }

  public function engines()
  {
    return $this->get($this->engines_path());
  }

  public function engine($engine_id)
  {
    return $this->get($this->engine_path($engine_id));
  }

  public function create_engine($engine_id)
  {
    $engine = array(
      'engine' => array(
        'name' => $engine_id
      )
    );

    return $this->post($this->engines_path(), array(), $engine);
  }

  public function destroy_engine($engine_id)
  {
    return $this->delete($this->engine_path($engine_id));
  }

  public function document_types($engine_id)
  {
    return $this->get($this->document_types_path($engine_id));
  }

  public function document_type($engine_id, $document_type_id)
  {
    return $this->get($this->document_type_path($engine_id, $document_type_id));
  }

  public function create_document_type($engine_id, $document_type_id)
  {
    $document_type = array(
      'document_type' => array(
        'name' => $document_type_id
      )
    );
    return $this->post($this->document_types_path($engine_id), array(), $document_type);
  }

  public function destroy_document_type($engine_id, $document_type_id)
  {
    return $this->delete($this->document_type_path($engine_id, $document_type_id));
  }

  public function documents($engine_id, $document_type_id)
  {
    return $this->get($this->documents_path($engine_id, $document_type_id));
  }

  public function document($engine_id, $document_type_id, $document_id)
  {
    return $this->get($this->document_path($engine_id, $document_type_id, $document_id));
  }

  public function create_document($engine_id, $document_type_id, $document = array())
  {
    return $this->post($this->documents_path($engine_id, $document_type_id), array(), array(
      'document' => $document
    ));
  }

  public function create_or_update_document($engine_id, $document_type_id, $document = array())
  {
    return $this->post($this->documents_path($engine_id, $document_type_id) . '/create_or_update', array(), array(
      'document' => $document
    ));
  }

  public function update_document($engine_id, $document_type_id, $document_id, $fields = array())
  {
    return $this->put($this->document_path($engine_id, $document_type_id, $document_id) . '/update_fields', array(), array(
      'fields' => $fields
    ));
  }

  public function update_documents($engine_id, $document_type_id, $documents = array())
  {
    return $this->put($this->documents_path($engine_id, $document_type_id) . '/bulk_update', array(), array(
      'documents' => $documents
    ));
  }

  public function create_or_update_documents($engine_id, $document_type_id, $documents = array())
  {
    return $this->post($this->documents_path($engine_id, $document_type_id) . '/bulk_create_or_update', array(), array(
      'documents' => $documents
    ));
  }

  public function create_or_update_documents_verbose($engine_id, $document_type_id, $documents = array())
  {
    return $this->post($this->documents_path($engine_id, $document_type_id) . '/bulk_create_or_update_verbose', array(), array(
      'documents' => $documents
    ));
  }

  public function destroy_document($engine_id, $document_type_id, $document_id)
  {
    return $this->delete($this->document_path($engine_id, $document_type_id, $document_id));
  }

  public function log_click($engine_id, $document_type_id, $document_id, $query)
  {
    return $this->post($this->click_path($engine_id, $document_type_id, $document_id) . '/analytics/log_clickthrough', array(), array(
      'q' => $query,
      'id' => $document_id,
			'auth_token' => $this->api_key,
    ));
  }

  public function destroy_documents($engine_id, $document_type_id, $document_ids = array())
  {
    return $this->post($this->documents_path($engine_id, $document_type_id) . '/bulk_destroy', array(), array(
      'documents' => $document_ids
    ));
  }

  public function search($engine_id, $document_type_id, $query, $options = array())
  {
    $query_string = array(
      'q' => $query
    );
    $full_query   = array_merge($query_string, $options);
    $document_type_id = $document_type_id ?: NULL;
    return $this->post($this->search_path($engine_id, $document_type_id), array(), $full_query);
  }

  public function suggest($engine_id, $document_type_id, $query, $options = array())
  {
    $query_string = array(
      'q' => $query
    );
    $full_query   = array_merge($query_string, $options);
    $document_type_id = $document_type_id ?: NULL;
    return $this->post($this->suggest_path($engine_id, $document_type_id), array(), $full_query);
  }

  private function search_path($engine_id, $document_type_id = null)
  {
    if ($document_type_id === null) {
      return 'engines/' . $engine_id . '/search';
    } else {
      return 'engines/' . $engine_id . '/document_types/' . $document_type_id . '/search';
    }
  }

  private function suggest_path($engine_id, $document_type_id = null)
  {
    if ($document_type_id === null) {
      return 'engines/' . $engine_id . '/suggest';
    } else {
      return 'engines/' . $engine_id . '/document_types/' . $document_type_id . '/suggest';
    }
  }

  private function engines_path()
  {
    return 'engines';
  }

  private function engine_path($engine_id)
  {
    return 'engines/' . $engine_id;
  }

  private function document_type_path($engine_id, $document_type_id)
  {
    return $this->engine_path($engine_id) . '/document_types/' . $document_type_id;
  }

  private function document_types_path($engine_id)
  {
    return $this->engine_path($engine_id) . '/document_types';
  }

  private function click_path($engine_id, $document_type_id, $document_id)
  {
    return $this->document_type_path($engine_id, $document_type_id);
  }

  private function document_path($engine_id, $document_type_id, $document_id)
  {
    return $this->document_type_path($engine_id, $document_type_id) . '/documents/' . $document_id;
  }

  private function documents_path($engine_id, $document_type_id)
  {
    return $this->document_type_path($engine_id, $document_type_id) . '/documents';
  }

  private function get($path, $params = array(), $data = array())
  {
    return $this->request('GET', $path, $params, $data);
  }

  private function post($path, $params = array(), $data = array())
  {
    return $this->request('POST', $path, $params, $data);
  }

  private function delete($path, $params = array(), $data = array())
  {
    return $this->request('DELETE', $path, $params, $data);
  }

  private function put($path, $params = array(), $data = array())
  {
    return $this->request('PUT', $path, $params, $data);
  }

  private function request($method, $path, $params = array(), $data = array())
  {
    //Final URL
    $full_path = $this->host . $this->api_base_path . $path . '.json';

    //Use the api key if we have it.
    //Throw an exception if we have nothing with which to authenticate ourselves
    if ($this->api_key !== null) {
      $params['auth_token'] = $this->api_key;
    }
    else if ($this->username === null && $this->password === null) {
      throw new \Exception('Authorization requires an API key, or username/password combination.');
    }
    else if ($this->username === null || $this->password === null) {
      throw new \Exception('Authorization requires both username AND password. Alternatively, you can provide an API key.');
    }

    //Build the query string
    $query = http_build_query($params);

    if ($query) {
      $full_path .= '?' . $query;
    }

    $request = curl_init($full_path);

    //Use basic http auth if we have no api key
    //Exceptions have already been thrown if we have nothing with which to authenticate ourselves
    if ($this->api_key === null) {
      curl_setopt($request, CURLOPT_USERPWD, $this->username . ':' . $this->password);
    }

    //Return the output instead of printing it
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($request, CURLOPT_FAILONERROR, true);

    $body = ($data) ? json_encode($data) : '';

    curl_setopt($request, CURLOPT_HTTPHEADER, array(
      'Content-type: application/json'
    ));

    if ($method === 'POST') {
      curl_setopt($request, CURLOPT_POST, true);
      curl_setopt($request, CURLOPT_POSTFIELDS, $body);
    } elseif ($method === 'DELETE') {
      curl_setopt($request, CURLOPT_CUSTOMREQUEST, 'DELETE');
    } elseif ($method === 'PUT') {
      curl_setopt($request, CURLOPT_CUSTOMREQUEST, 'PUT');
      curl_setopt($request, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($request);
    $error    = curl_error($request);

    if (!empty($error)) {
      throw new \Exception("Sending message failed. Error: " . $error);
    }

    $http_status = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);

    //Any 2XX HTTP codes mean that the request worked
    if (intval(floor($http_status / 100)) === 2) {
      $final = json_decode($response);
      switch (json_last_error()) {
        case JSON_ERROR_DEPTH:
          $error = 'Maximum stack depth exceeded';
          break;
        case JSON_ERROR_CTRL_CHAR:
          $error = 'Unexpected control character found';
          break;
        case JSON_ERROR_SYNTAX:
        	$error = FALSE;
        	if (strpos($full_path, "log_clickthrough") === FALSE) {
						$error = 'Syntax error, malformed JSON';
					}
          break;
        case JSON_ERROR_STATE_MISMATCH:
          $error = 'Underflow or the modes mismatch';
          break;
        case JSON_ERROR_UTF8:
          $error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
          break;
        case JSON_ERROR_NONE:
        default:
          $error = false;
          break;
      }

      if ($error === false) {
        //Request and response are OK
        if ($final) {
          return array(
            'status' => $http_status,
            'body' => $final
          );
        } else {
          return array(
            'status' => $http_status
          );
        }
      } else {
        throw new \Exception('The JSON response could not be parsed: ' . $error . '\n' . $response);
      }
    } elseif ($http_status === 401) {
      throw new \Exception('Authorization required.');
    } else {
      throw new \Exception("Couldn't send message, got response code: " . $http_status . " response: " . $response);
    }
  }
}

?>
