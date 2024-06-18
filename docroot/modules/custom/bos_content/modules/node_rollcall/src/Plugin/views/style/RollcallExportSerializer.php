<?php

namespace Drupal\node_rollcall\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

/**
 * This plugin takes the node and its linked paragraphs and groups the output
 * for easier consumption.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "node_rollcall",
 *   title = @Translation("Rollcall Export Serializer"),
 *   help = @Translation("Serializes views row data using the Serializer
 *   component."), display_types = {"data"}
 * )
 */
class RollcallExportSerializer extends Serializer {

  public function render() {

    $output = [];

    // Get the feed output.
    if ($feed = json_decode(parent::render())) {

      $output[$feed[0]->id] = [
        "id" => $feed[0]->id,
        "docket" => $feed[0]->docket,
        "date" => $feed[0]->date,
        "subject" => $feed[0]->subject,
        "votes" => []
      ];

      // Reformat the feed output.
      foreach ($feed as $vote) {

        if (!array_key_exists($vote->id, $output)) {
          $output[$vote->id] = [
            "id" => $vote->id,
            "docket" => $vote->docket,
            "date" => $vote->date,
            "subject" => $vote->subject,
            "votes" => [],
          ];
        }

        $output[$vote->id]["votes"][$vote->councillor] = $vote->vote;

      }
    }

    $output = array_values($output);
    return json_encode($output);
  }

}
