/**
 * @file
 * Script to set Graphql settings and map with remote REACT app.
 */

const gqlApiKey = window.drupalSettings.bos_commissions_search.graphql_api_key;
const gqlEndpoint = window.drupalSettings.bos_commissions_search.graphql_endpoint;
window.Drupal = {
    settings: {
      bos_commissions_search: {
        bos_commissions_search_graphql_api_key: gqlApiKey,
        bos_commissions_search_graphql_endpoint: gqlEndpoint,
      },
    },
  };
