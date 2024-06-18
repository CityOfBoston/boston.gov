<?php

namespace Drupal\bos_sql\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SQL.
 *
 * @package Drupal\bos_assessing\Controller
 */
class SQL extends ControllerBase {

  /**
   * Class var.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  public $request;


  /**
   * Check for local env and set connector url staging option.
   */
  public function checkLocalEnv() {
    $local = (isset($_ENV['DBCONNECTOR_SETTINGS'])) ? '' : 'digital-staging.';

    return $local;
  }

  /**
   * Run query against SQL database and return JSON response.
   */
  public function runQuery($bearer_token, $connection_token, $statement) {
    $post_fields = [
      "token"  => $connection_token,
      "statement" => $statement,
    ];
    $post_fields = json_encode($post_fields);
    $url = 'https://dbconnector.' . $this->checkLocalEnv() . 'boston.gov/v1/query/mssql';

    // Make the request and return the response.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Accept: application/json",
      "Content-Type: application/json",
      "Authorization: Bearer " . $bearer_token,
    ]);
    $info = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);


    if (isset($info)) {
      $data = json_decode($info);
    } else {
      $data = "error connecting to service";
    }

    //$response = new CacheableJsonResponse($data);
    return $data;
  }

  public function runSP($bearer_token, $connection_token, string $sp_name, array $input_params) {
    $post_fields = [
      "token"  => $connection_token,
      "procname" => $sp_name,
      "params" => $input_params
    ];
    $post_fields = json_encode($post_fields);
    $url = 'https://dbconnector.' . $this->checkLocalEnv() . 'boston.gov/v1/exec/mssql';

    // Make the request and return the response.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Accept: application/json",
      "Content-Type: application/json",
      "Authorization: Bearer " . $bearer_token,
    ]);
    $info = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);


    if (isset($info)) {
      $data = json_decode($info);
    } else {
      $data = "error connecting to service";
    }

    //$response = new CacheableJsonResponse($data);
    return $data;
  }

  public function runSelect($bearer_token, $connection_token, $table, $fields, $filter = NULL, $sort = NULL, $limit = NULL, $page = NULL) {

    $post_fields = [
      "token"  => $connection_token,
      "table"  => $table,
    ];
    if ($filter !== NULL){
      $post_fields["filter"] = $filter;
    }
    if ($sort !== NULL){
      $post_fields["sort"] = $sort;
    }
    if ($limit !== NULL){
      $post_fields["limit"] = $limit;
    }
    if ($page !== NULL){
      $post_fields["page"] = $page;
    }
    if ($fields !== NULL){
      $post_fields["fields"] = $fields;
    }

    $post_fields = json_encode($post_fields);
    $url = 'https://dbconnector.' . $this->checkLocalEnv() . 'boston.gov/v1/select/mssql';

    // Make the request and return the response.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Accept: application/json",
      "Content-Type: application/json",
      "Authorization: Bearer " . $bearer_token,
    ]);
    $info = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);


    if (isset($info)) {
      $data = json_decode($info);
    } else {
      $data = "error connecting to service";
    }


    $response = new CacheableJsonResponse($data);
    return $response;
  }

  /**
   * Request DB operations
   * * @param string $app_name
   *   The name of the web application / service.
   */
  public function getToken($app_name) {

    if (isset($_ENV['DBCONNECTOR_SETTINGS'])) {
      $dbconnector_env = [];
      $get_vars = explode(",", $_ENV['DBCONNECTOR_SETTINGS']);
      foreach ($get_vars as $item) {
        $json = explode(":", $item);
        $dbconnector_env[$json[0]] = $json[1];
      }
    }
    else {
      $config = \Drupal::config("bos_sql.settings")->get("{$app_name}");
      $dbconnector_env = [
        "username_" . $app_name => $config["username"],
        "password_" . $app_name => $config["password"],
        "conntoken_" . $app_name => $config["token"]
      ];
    }

    $post_fields = [
      "username" => $dbconnector_env["username_" . $app_name],
      "password" => $dbconnector_env["password_" . $app_name],
    ];
    $post_fields = json_encode($post_fields);


    // Make the request and return the response.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, \Drupal::config("bos_sql.settings")->get("host"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Accept: application/json",
      "Content-Type: application/json",
    ]);
    $info = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (isset($info)) {
      $data = json_decode($info,true);
      $data = [
        "bearer_token" => $data["authToken"],
        "connection_token" => $dbconnector_env["conntoken_" . $app_name]
      ];
    }
    else {
      $data = null;
    }

    return $data;
  }

}
