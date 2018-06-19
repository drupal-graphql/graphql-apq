<?php

namespace Drupal\graphql_apq\Routing;

use Drupal\Component\Utility\NestedArray;
use Symfony\Component\HttpFoundation\Request;
use Drupal\graphql\Routing\QueryRouteEnhancer;

class APQRouteEnhancer extends QueryRouteEnhancer {

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    $operation = &$defaults['operations'];
    $extensions = $operation->getOriginalInput('extensions');

    if (empty($operation->queryId) && !empty($extensions['persistedQuery'])) {
      $persistedQuery = $extensions['persistedQuery'];
      $defaults['operations']->queryId = "{$persistedQuery['version']}:{$persistedQuery['sha256Hash']}";
      $defaults['operations']->originalQuery = $defaults['operations']->query;
      // Current GraphQL implementation understands "queryId" and "query"
      // as exlusive parameters.
      $defaults['operations']->query = NULL;
    }

    return $defaults;
  }

}
