<?php

namespace Drupal\bos_swiftype\Controller;

// TODO: Make this Ajax.
use Drupal\bos_core\Services\BosCoreGAPost;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Mail\MailManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\bos_swiftype\Swiftype\SwiftypeClient;

/**
 * Class SwiftypeController.
 *
 * @package Drupal\bos_swiftype\Controller
 */
class SwiftypeController extends ControllerBase {

  /**
   * Request object for swifttype class.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * Logger for swifttype class.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $log;

  /**
   * Mail object for swifttype class.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mail;

  /**
   * Google Analytics object for swifttype class.
   *
   * @var \Drupal\bos_core\Services\BosCoreGAPost
   */
  protected $gapost;

  /**
   * EntityTypeManager for class.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * CityscoreRest create.
   *
   * @inheritdoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('plugin.manager.mail'),
      $container->get('bos_core.gapost'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * CityscoreRest constructor.
   *
   * @inheritdoc
   */
  public function __construct(RequestStack $requestStack, LoggerChannelFactory $logger, MailManager $mail, BosCoreGAPost $gapost, EntityTypeManager $entityTypeManager) {
    $this->request = $requestStack->getCurrentRequest();
    $this->log = $logger->get('Swifttype-search');
    $this->mail = $mail;
    $this->gapost = $gapost;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Magic function.  Catches calls to endpoints that dont exist.
   *
   * @param string $name
   *   Name.
   * @param mixed $arguments
   *   Arguments.
   */
  public function __call($name, $arguments) {
    throw new NotFoundHttpException();
  }

  /**
   * Extends the config function.
   *
   * @param string $name
   *   The config(setting) to be managed.
   *
   * @return array|\Drupal\Core\Config\Config|mixed|null
   *   The value of the setting being managed.
   */
  public function config($name) {
    $config = parent::config("bos_swiftype.settings");
    if (isset($name)) {
      return $config->get($name);
    }
    return $config->getRawData();
  }

  /**
   * When a user requests a page from the search results.
   */
  public function searchClick() {
    $params = \Drupal::request()->query->all();
    if (empty($params["id"])) {
      $params["id"] = \Drupal::request()->server->get("HTTP_REFERER");
      $params["id"] = explode("/", $params["id"]);
      $params["id"] = array_pop($params["id"]);
    }

    $api_key = getenv("bos_swiftype_auth_token") ?: "vgZPe9Y3JZpZzCEzEpM6";
    $password = $this->config('swiftype_password') ?: "";

    try {
      $client = new SwiftypeClient($this->config('swiftype_email'), $password, $api_key, $this->config('swiftype_endpoint_host'), $this->config('swiftype_endpoint_path'));
      $results = $client->log_click($this->config('swiftype_engine'), 'page', $params['id'], $params['query']);
    }
    catch (Exception $e) {
      echo 'Unable to log click: ', $e->getMessage(), "\n";
    }

    $url = html_entity_decode($params['url']);
    header('Location: ' . $url);

    exit();
  }

  /**
   * When a user requests a search from a search bar.
   *
   * @return mixed
   *   A page of results.
   *
   * @throws \Exception
   */
  public function searchPage() {
    $params = \Drupal::request()->query->all();

    $api_key = getenv("bos_swiftype_auth_token");
    $password = $this->config('swiftype_password') ?: "";

    if (!empty($params) && (!empty($api_key) || !empty($password))) {
      $client = new SwiftypeClient($this->config('swiftype_email'), $password, $api_key, $this->config('swiftype_endpoint_host'), $this->config('swiftype_endpoint_path'));

      if (!empty($params['query'])) {
        if (!empty($params['facet'])) {
          $filters = [
            'page' => [
              'type' => $params['facet'],
            ],
          ];
        }
        else {
          $filters = NULL;
        }

        $results = $client->search($this->config('swiftype_engine'), 'page', $params['query'], [
          'per_page' => 10,
          'page' => $params['page'] ?? 1,
          'filters' => $filters,
          'facets' => [
            'page' => [
              'type',
            ],
          ],
        ]);
        if ($results['body']->info->page == NULL) {
          $range = new \stdClass();
        }
        else {
          $range = $results['body']->info->page;
        }
      }
      else {
        $results = NULL;
        $range = new \stdClass();
      }



      return [
        '#theme' => 'bos_swiftype_search_results',
        '#results' => $results,
        '#range' => $this->pageRange($range),
        '#selected_facets' => $params['facet'] ?? [],
        '#bos_search_url' => $this->config('bos_search_url') ?: "",
        "#facets" => [],
        "#facets_extra" => [],
        "#has_results" => FALSE,
        "#info" => new \stdClass(),
        "#records" => [],
      ];
    }
    else {
      return [
        '#theme' => 'bos_swiftype_search_results',
        '#results' => NULL,
        '#range' => new \stdClass(),
        '#selected_facets' => [],
        '#bos_search_url' => $this->config('bos_search_url') ?: "",
        "#facets" => [],
        "#facets_extra" => [],
        "#has_results" => FALSE,
        '#no_search' => empty($params),
        "#info" => new \stdClass(),
        "#records" => [],
      ];
    }
  }

  /**
   * Manages multi-page retrieval of search results.
   *
   * @param object $info
   *   Search info.
   *
   * @return array
   *   The overall number of pages.
   */
  private function pageRange(\stdClass $info) {
    $start = 1;
    $end = 5;

    if ((array) $info !== []) {

      if ($info->current_page > 5) {
        $start = $info->current_page - 2;
        $end = $info->current_page + 2;
      }

      if ($end > $info->num_pages) {
        $end = $info->num_pages;
      }

      if ($info->num_pages < 5) {
        $end = $info->num_pages;
      }
    }
    return range($start, $end);
  }

}
