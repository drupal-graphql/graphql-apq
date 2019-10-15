<?php

namespace Drupal\graphql_apq\GraphQL\Execution;

use Drupal\Core\Cache\Cache;
use GraphQL\Language\Parser;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\OperationParams;
use GraphQL\Language\AST\DocumentNode;
use Drupal\graphql\GraphQL\Execution\QueryResult;
use Drupal\graphql\GraphQL\Execution\QueryProcessor;
use GraphQL\Validator\DocumentValidator;

class APQQueryProcessor extends QueryProcessor {

  /**
   * @inheritDoc
   */
  public function processQuery($schema, $params) {
    // Load the plugin from the schema manager.
    $plugin = $this->pluginManager->createInstance($schema);
    $config = $plugin->getServer();

    // Store query when present.
    if (!empty($params->getOriginalInput('query'))) {
      // Validate query before storing.
      $errors = $this->validateAPQQuery($config, $params);
      if (is_array($errors)) {
        return new QueryResult(NULL, $errors);
      }

      $this->storeAPQQuery($params);
    }

    if (is_array($params)) {
      return $this->executeBatch($config, $params);
    }

    return $this->executeSingle($config, $params);
  }

  /**
   * Store APQ Query.
   *
   * @param \GraphQL\Server\OperationParams $params
   *
   * @throws \GraphQL\Error\SyntaxError
   * @throws \GraphQL\Server\RequestError
   */
  private function storeAPQQuery(OperationParams $params) {
    $persistedQuery = $this->persistedQuery($params);
    $storage = \Drupal::entityTypeManager()->getStorage('apq_query_map');

    // Query is already stored.
    if (
      !empty(
        $storage->loadByProperties([
          'version' => $persistedQuery['version'],
          'hash' => $persistedQuery['sha256Hash'],
        ])
      )
    ) {
      return;
    }

    // We can now store valid query.
    $apq = $storage->create([
      'version' => $persistedQuery['version'],
      'query' => $params->getOriginalInput('query'),
      'hash' => $persistedQuery['sha256Hash'],
    ]);
    $apq->save();

    // Invalidate previous not found response.
    Cache::invalidateTags([$this->getCacheTag($persistedQuery['sha256Hash'])]);
  }

  /**
   * Check if is APQ Query valid.
   *
   * @param \GraphQL\Server\ServerConfig $config
   * @param \GraphQL\Server\OperationParams $params
   *
   * @return bool|array
   * @throws \GraphQL\Error\SyntaxError
   * @throws \GraphQL\Server\RequestError
   */
  private function validateAPQQuery(ServerConfig $config, OperationParams $params) {
    $document = $this->getDocumentFromQuery($config, $params);

    // Add default validation rules.
    $config->setValidationRules(DocumentValidator::defaultRules());

    $paramsErrors = $this->validateOperationParams($params);
    if (!empty($paramsErrors)) {
      return $paramsErrors;
    }

    $operationErrors = $this->validateOperation($config, $params, $document);
    if (!empty($operationErrors)) {
      return $operationErrors;
    }

    return TRUE;
  }

  /**
   * Get DocumentNode from query.
   *
   * @param \GraphQL\Server\ServerConfig $config
   * @param \GraphQL\Server\OperationParams $params
   *
   * @return \GraphQL\Language\AST\DocumentNode
   * @throws \GraphQL\Error\SyntaxError
   * @throws \GraphQL\Server\RequestError
   */
  private function getDocumentFromQuery(ServerConfig $config, OperationParams $params): DocumentNode {
    $document = $params->queryId ? $this->loadPersistedQuery($config, $params) : $params->query;
    if (!$document instanceof DocumentNode) {
      $document = Parser::parse($document);
    }
    return $document;
  }

  /**
   * Return persisted query extension or false.
   *
   * @param \GraphQL\Server\OperationParams $params
   *
   * @return bool | array
   */
  private function persistedQuery(OperationParams $params) {
    $extensions = $params->getOriginalInput('extensions');
    return empty($extensions['persistedQuery']) ? false : $extensions['persistedQuery'];
  }

  /**
   * Get query's hash cache-tag.
   *
   * @param String $hash
   *   Hash from GraphQL Query.
   *
   * @return String
   *   Cache tag form query's hash.
   */
  public function getCacheTag($hash) {
    return 'apq:' . substr($hash, 0, 9);
  }

}
