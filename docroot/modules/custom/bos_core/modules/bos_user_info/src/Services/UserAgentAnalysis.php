<?php

namespace Drupal\bos_user_info\Services;

use DeviceDetector\DeviceDetector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class for analyzing user agents using the Matomo/device-detector library.
 */
class UserAgentAnalysis {

  /**
   * Matomo/device-detector class.
   *
   * @var \DeviceDetector\DeviceDetector
   */
  private DeviceDetector $detector;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private Request $request;

  public function __construct(RequestStack $request_stack) {
    $this->request = $request_stack->getCurrentRequest();
    $user_agent = $this->request->server->get('HTTP_USER_AGENT') ?? "Unknown";
    $this->detector = new DeviceDetector($user_agent);
  }

  /**
   * Reads and parses the User Agent, returning details about the client.
   *
   * @return array
   *   Associative array containing the parsed user agent details:
   *               - client: The client details.
   *               - os: The operating system details.
   *               - device: The device details.
   *               - device_name: The name of the device.
   *               - brand_name: The brand name of the device.
   *               - model: The model of the device.
   *               - mobile: Whether the device is a mobile device.
   *               - bot: Whether the user agent is identified as a bot.
   *               - desktop: Whether the device is a desktop.
   */
  public function read(): array {
    $this->detector->parse();
    if (empty($this->detector->getUserAgent())) {
      return [];
    }
    return [
      "client" => $this->detector->getClient(),
      "os" => $this->detector->getOs(),
      "device" => $this->detector->getDevice(),
      "device_name" => $this->detector->getDeviceName(),
      "brand_name" => $this->detector->getBrandName(),
      "model" => $this->detector->getModel(),
      "mobile" => $this->detector->isMobile(),
      "bot" => $this->detector->isBot(),
      "desktop" => $this->detector->isDesktop(),
    ];
  }

}
