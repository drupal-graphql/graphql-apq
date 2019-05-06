<?php

namespace Drupal\graphql_apq\GraphQL\Execution;

use Drupal\graphql\GraphQL\Execution\QueryProcessor;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Server\OperationParams;
use GraphQL\Server\ServerConfig;
use Drupal\Core\Cache\Cache;

class APQQueryProcessor extends QueryProcessor {

  /**
   * @inheritDoc
   */
  public function processQuery($schema, $params) {
    // Load the plugin from the schema manager.
    $plugin = $this->pluginManager->createInstance($schema);
    $config = $plugin->getServer();

    $this->storeAPQQuery($config, $params);

    if (is_array($params)) {
      return $this->executeBatch($config, $params);
    }

    return $this->executeSingle($config, $params);
  }

  /**
   * Store APQ Query.
   *
   * @param \GraphQL\Server\ServerConfig $config
   * @param \GraphQL\Server\OperationParams $params
   *
   * @throws \GraphQL\Error\SyntaxError
   * @throws \GraphQL\Server\RequestError
   */
  private function storeAPQQuery(ServerConfig $config, OperationParams $params) {
    // Request without query.
    if (empty($params->getOriginalInput('query'))) {
      return;
    }

    // Query is invalid.
    if (!$this->validateAPQQuery($config, $params)) {
      return;
    }

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
   * @return bool
   * @throws \GraphQL\Error\SyntaxError
   * @throws \GraphQL\Server\RequestError
   */
  private function validateAPQQuery(ServerConfig $config, OperationParams $params): bool {
    $document = $this->getDocumentFromQuery($config, $params);
    return $this->operationParamsValid($params) && $this->operationValid($config, $params, $document);
  }

  /**
   * Check if operation params are valid.
   *
   * @param \GraphQL\Server\OperationParams $params
   *
   * @return bool
   */
  private function operationParamsValid(OperationParams $params) {
    return count($this->validateOperationParams($params)) === 0;
  }

  /**
   * Check if operation is valid.
   *
   * @param \GraphQL\Server\ServerConfig $config
   * @param \GraphQL\Server\OperationParams $params
   * @param \GraphQL\Language\AST\DocumentNode $document
   *
   * @return bool
   * @throws \Exception
   */
  private function operationValid(ServerConfig $config, OperationParams $params, DocumentNode $document) {
    return count($this->validateOperation($config, $params, $document)) === 0;
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
