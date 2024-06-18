<?php

namespace Drupal\bos_email\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Upaknee class for API.
 */
class UpakneeAPI extends ControllerBase {

  /**
   * Current request object for this class.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  public $request;

  /**
   * Operation mapped to Upaknee.
   *
   * @var string
   */
  public $operation;

  /**
   * Public construct for Request.
   */
  public function __construct(RequestStack $request) {
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('request_stack')
    );
  }

  /**
   * Get ENV var token for Upaknee auth.
   */
  public function getToken() {
    $token = NULL;

    if (isset($_ENV['UPAKNEE_TOKEN'])) {
      $token = $_ENV['UPAKNEE_TOKEN'];
    }
    else {
      $token = Settings::get('upaknee_token');
    }

    return $token;
  }

  /**
   * Add subscriber via Upaknee API.
   *
   * @param array $subscriber_data
   *   The array containing Upaknee API needed fields.
   */
  public function subscriberAdd(array $subscriber_data) {
    if ($subscriber_data["honey"] == "") {
      $username = $this->getToken();
      $password = '';
      $subscriber_data_xml = '<subscriber>
                                <email>' . $subscriber_data["email"] . '</email>
                                <existing-update>true</existing-update>
                                <source>boston.gov webform</source>
                                <source-ip>' . \Drupal::request()->getClientIp() . '</source-ip>
                                <zipcode>' . $subscriber_data["zipcode"] . '</zipcode>
                                <subscriptions>
                                    <subscription>
                                        <newsletter-id>' . $subscriber_data["list"] . '</newsletter-id>
                                    </subscription>
                                </subscriptions>
                              </subscriber>';
      $ch = curl_init();
      curl_setopt_array($ch, [
        CURLOPT_URL => "https://rest.upaknee.com/subscribers/",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_USERPWD => $username . ":" . $password,
        CURLOPT_FOLLOWLOCATION => TRUE,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $subscriber_data_xml,
        CURLOPT_HTTPHEADER => [
          "Accept: application/xml",
          "Content-Type: application/xml"
        ],
      ]);

      $response = curl_exec($ch);
      curl_close($ch);

    }
    else {

      // To do log for suspicious behavior and track bad IP address.

    }

    $response_array = [
      'status' => 'success',
      'subscriber' => 'true',
      'response' => $response,
    ];

    return $response_array;

  }

  /**
   * Begin script and API operations.
   *
   * @param string $operation
   *   The operation being called via the endpoint uri.
   */
  public function begin(string $operation = 'subscribe') {
    // Get POST data and check operation.
    $this->operation = $operation;

    $request_method = $this->request->getCurrentRequest()->getMethod();
    if ($request_method == "POST") :
      $data = $this->request->getCurrentRequest()->get('subscriber');

      if (!empty($data) && $operation == 'subscribe') :
        $response_array = $this->subscriberAdd($data);
      endif;

    else :

      $response_array = [
        'status' => 'error',
        'response' => 'no post data',
      ];

    endif;

    $response = new CacheableJsonResponse($response_array);
    return $response;
  }

}

// End UpakneeAPI class.
