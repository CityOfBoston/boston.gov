<?php

namespace Drupal\bos_assessing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geolocation\Element;
use Drupal\bos_sql\Controller\SQL;


/**
 * Class Assessing.
 *
 * @package Drupal\bos_assessing\Controller
 */
class Assessing extends ControllerBase {

  /**
   * Logger object for class.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $log;

  /**
   * Get poly coordinates from arcGIS layer
   * @param string $parcel_id
   *   The id of the parcel requested by user
   */
  public function getPolyCoords($parcel_id) {

      $url = 'https://services.arcgis.com/sFnw0xNflSi8J0uh/ArcGIS/rest/services/AbutterParcels_Oct2020/FeatureServer/0/query?where=PID_LONG%3D%27'.$parcel_id.'%27&f=pgeojson';
      // Make the request and return the response.
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPGET, true);
      $info = curl_exec($ch);

      if (isset($info)) {
        //$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $coords = json_decode($info);
      } else {
        return $coords = NULL;
      }

  }

  /**
   * Get parcel info from endpoint
   * @param string $parcel_id
   *   The id of the parcel requested by user
   */
  public function assessingDetails($parcel_id) {
      $statement1 = "SELECT t.*, TP.*, AD.*
                   FROM taxbill AS t
                    LEFT JOIN tax_preliminary AS tp
                      ON t.parcel_id = TP.parcel_id
                    LEFT JOIN additional_data AS ad
                      ON t.parcel_id = AD.parcel_id
                    WHERE t.parcel_id = '$parcel_id'";
      $statement2 = "SELECT * FROM [RESIDENTIAL PROPERTY ATTRIBUTES] WHERE parcel_id = '$parcel_id'";
      $statement3 = "SELECT * FROM [CONDO PROPERTY ATTRIBUTES] WHERE parcel_id = '$parcel_id'";
      $statement4 = "SELECT TOP 10 * FROM value_history WHERE parcel_id = '$parcel_id'";
      $statement5 = "SELECT owner FROM taxbill WHERE parcel_id = '$parcel_id'";
      $statement6 = "SELECT owner_name FROM current_owners WHERE parcel_id = '$parcel_id'";

      $sql = new SQL();
      $bearer_token = $sql->getToken("assessing")["bearer_token"];
      $connection_token = $sql->getToken("assessing")["connection_token"];

      $sqlQuery_main = $sql->runQuery($bearer_token,$connection_token,$statement1);
      $sqlQuery_res = $sql->runQuery($bearer_token,$connection_token,$statement2);
      $sqlQuery_condo = $sql->runQuery($bearer_token,$connection_token,$statement3);
      $sqlQuery_value_history = $sql->runQuery($bearer_token,$connection_token,$statement4);
      $sqlQuery_owner = $sql->runQuery($bearer_token,$connection_token,$statement5);
      $sqlQuery_owners_current = $sql->runQuery($bearer_token,$connection_token,$statement6);

      $coords = $this->getPolyCoords($parcel_id);
      $fiscal_year = ( date('m') > 6) ? intval(date('Y')) + 1 : date('Y');

      return [
        '#theme' => 'bos_assessing',
        '#data_full' => $sqlQuery_main,
        '#data_res' => $sqlQuery_res,
        '#data_condo' => $sqlQuery_condo,
        '#data_owner' => $sqlQuery_owner,
        '#data_owners_current' => $sqlQuery_owners_current,
        '#data_value_history' => $sqlQuery_value_history,
        '#data_coords' => $coords,
        '#data_year_current' => date('Y'),
        '#data_year_fiscal' => $fiscal_year,
      ];
  }

   /**
   * Get post data and apply to select endpoint.
   *
   */
  public function assessingLookup() {
    $data = \Drupal::request()->query;
    $sql = new SQL();

    //required
    $bearer_token = $sql->getToken("assessing")["bearer_token"];
    $connection_token = $sql->getToken("assessing")["connection_token"];

    $table = "taxbill";
    $filter = [];

    if($data->get("parcel_id")){
      array_push($filter, ["parcel_id" => $data->get("parcel_id")]);
    }
    if($data->get("street_number")){
      array_push($filter, ["street_number" => $data->get("street_number")]);
    }
    if($data->get("apt_unit")){
      array_push($filter, ["apt_unit" => $data->get("apt_unit")]);
    }
    if($data->get("street_name_only")){
      $street_name_only = $data->get("street_name_only");
      if(strlen($street_name_only) == 1 && ctype_alpha($street_name_only) == TRUE) {
        array_push($filter, ["street_name_only" => $street_name_only]);
      } else {
        array_push($filter, ["street_name_only" => "%" . $street_name_only . "%" ]);
      }
    }
    if($data->get("street_name_suffix")){
      $sns = explode(",",$data->get("street_name_suffix"));
      array_push($filter, ["street_name_suffix" => $sns]);
    }

    $sort = ($data->get("sort")) ? $data->get("sort") : ["street_name","street_number","apt_unit"];
    $limit = ($data->get("limit")) ? $data->get("limit") : 500;
    $page = ($data->get("page")) ? $data->get("page") : null;
    $fields = ($data->get("fields")) ? $data->get("fields") : null;

    return $sql->runSelect($bearer_token, $connection_token, $table, $fields, $filter, $sort, $limit, $page);

  }

}
