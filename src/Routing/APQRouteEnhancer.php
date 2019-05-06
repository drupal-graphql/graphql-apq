<?php

namespace Drupal\graphql_apq\Routing;

use Drupal\graphql\Routing\QueryRouteEnhancer;
use Symfony\Component\HttpFoundation\Request;

class APQRouteEnhancer extends QueryRouteEnhancer {

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    if ($persistedQuery = $this->persistedQuery($defaults)) {
      $defaults['_controller'] = "\Drupal\graphql_apq\Controller\APQRequestController::handleRequest";
      if (empty($defaults['operations']->queryId)) {
        $defaults['operations']->queryId = "{$persistedQuery['version']}:{$persistedQuery['sha256Hash']}";
      }
      $defaults['operations']->query = NULL;
    }
    return $defaults;
  }

  private function persistedQuery(array $defaults) {
    $extensions = $defaults['operations']->getOriginalInput('extensions');
    return empty($extensions['persistedQuery']) ? false : $extensions['persistedQuery'];
  }

}
