printf "Removing old election data "
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties(["type" => "election_candidate_results"]) as $node){$node->delete();}'
printf "."
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties(["type" => "election_contest_results"]) as $node){$node->delete();}'
printf "."
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties(["type" => "election_area_results"]) as $node){$node->delete();}'
printf "."
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("node")->loadByProperties(["type" => "election_report"]) as $node){$node->delete();}'
printf "."
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("paragraph")->loadByProperties(["type" => "election_card"]) as $node){$node->delete();}'
printf "."
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("taxonomy_term")->loadByProperties(["vid" => "election_candidates"]) as $node){$node->delete();}'
printf "."
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("taxonomy_term")->loadByProperties(["vid" => "election_contests"]) as $node){$node->delete();}'
printf "."
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("taxonomy_term")->loadByProperties(["vid" => "election_areas"]) as $node){$node->delete();}'
printf "."
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("taxonomy_term")->loadByProperties(["vid" => "elections"]) as $node){$node->delete();}'
printf "."
drush ev 'foreach(\Drupal::entityTypeManager()->getStorage("taxonomy_term")->loadByProperties(["vid" => "elector_groups"]) as $node){$node->delete();}'
printf "."
drush ev '\Drupal::service("config.factory")->getEditable("node_elections.settings")->set("history", [])->set("last-run", "")->save();'
printf "\nSuccess!\n"
