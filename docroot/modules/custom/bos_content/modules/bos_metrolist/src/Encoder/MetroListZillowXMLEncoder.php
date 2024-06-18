<?php

namespace Drupal\bos_metrolist\Encoder;

use Drupal\serialization\Encoder\XmlEncoder as SerializationXMLEncoder;

/**
 * Encodes xml API data.
 *
 * David: July 2023 during D10 upgrade.
 * The Drupal\serialization\Encoder\XmlEncoder class is now marked as
 * internal in Drupal10.
 * @see https://www.drupal.org/about/core/policies/core-change-policies/bc-policy#internal
 *
 * Apparently, this internal flag is used to advise that the class is not
 * intended by the modules designers (Drupal Core) to be used directly by other
 * functions/services, is not supported as an "API" and can be changed without
 * notice at any time.
 * As far as PHP is concerned, extending an internal class is possible and
 * there is no issue with doing so, but does cause warnings for certain code
 * validators/sniffers.
 * The alternative is to find a way to rewrite this, or (better) to clone
 * Drupal\serialization\Encoder\XmlEncoder in this module and namespace and then
 * use that cloned class, however the class has dependencies on symfony
 * resources which are regularly updated, and I can see that cloning the class
 * will require us to manually maintain symfony dependencies will be a headache
 * in the future.
 * We are better to put up with the warning messages ....
 *
 * @internal
 */
class MetroListZillowXMLEncoder extends SerializationXMLEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected static $format = ['metrolist_zillow_xml'];

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []): string {

    $xmlDocument = new \DOMDocument('1.0');
    $xmlDocument->formatOutput = TRUE;
    $xmlDocument->encoding = 'UTF-8';

    $hotPadItems = $xmlDocument->createElement('hotPadsItems');
    $xmlDocument->appendChild($hotPadItems);
    $hotPadItems->setAttribute('version', '2.1');

    $company = $xmlDocument->createElement('Company');
    $companyID = 'city_of_boston_dnd_metrolist';
    $company->setAttribute('id', $companyID);
    $company->appendChild($xmlDocument->createElement('name', 'Boston Metrolist'));
    $company->appendChild($xmlDocument->createElement('city', 'Boston'));
    $company->appendChild($xmlDocument->createElement('state', 'MA'));
    $company->appendChild($xmlDocument->createElement('website', 'https://boston.gov/metrolist'));
    $hotPadItems->appendChild($company);

    foreach ($data as $listingData) {

      $xmlListing = $xmlDocument->createElement('Listing');
      $hotPadItems->appendChild($xmlListing);

      foreach ($listingData as $fieldName => $fieldValue) {

        if ($fieldName == 'id') {
          $xmlListing->setAttribute('id', $fieldValue);
        }

        if ($fieldName == 'street') {
          $xmlListing->setAttribute('hide', 'false');
        }

        // @TODO: Hard coded values, fix this:
        $xmlListing->setAttribute('type', 'RENTAL');
        $xmlListing->setAttribute('companyId', $companyID);
        $xmlListing->setAttribute('propertyType', 'CONDO');

        $xmlField = $xmlDocument->createElement($fieldName, $fieldValue);
        $xmlListing->appendChild($xmlField);
      }
    }

    return $xmlDocument->saveXML();

    // $parent = parent::encode($data, $format, $context);
    // do stuff

    // Return $parent;.
  }

}
