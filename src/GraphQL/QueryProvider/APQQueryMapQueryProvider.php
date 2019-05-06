<?php

namespace Drupal\graphql_apq\GraphQL\QueryProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\graphql\GraphQL\QueryProvider\QueryProviderInterface;
use GraphQL\Server\OperationParams;
use Drupal\graphql\GraphQL\Cache\CacheableRequestError;

class APQQueryMapQueryProvider implements QueryProviderInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * QueryProvider constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery($id, OperationParams $operation) {
    // Early skip if no persistedQuery protocol implemented in operation.
    $persistedQuery = $this->persistedQuery($operation);
    if (!$persistedQuery) {
      return NULL;
    }

    // Retrieve query in case we have it cached.
    $storage = $this->entityTypeManager->getStorage('apq_query_map');
    $apqs = $storage->loadByProperties([
      'version' => $persistedQuery['version'],
      'hash' => $persistedQuery['sha256Hash'],
    ]);
    $apq = empty($apqs) ? NULL : reset($apqs);
    if (!empty($apq)) {
      return $apq->getQuery();
    }

    // Send original query if is present.
    $originalQuery = $operation->getOriginalInput('query');
    if (empty($apq) && !empty($originalQuery)) {
      return $originalQuery;
    }

    // In case no query is cached, respond with PersistedQueryNotFound to
    // allow fulfilling.
    if (empty($operation->originalQuery)) {
      throw (new CacheableRequestError('PersistedQueryNotFound'))
        ->addCacheTags([$this->getCacheTag($persistedQuery['sha256Hash'])]);
    }

    return NULL;
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

  private function persistedQuery(OperationParams $operation) {
    $extensions = $operation->getOriginalInput('extensions');
    return empty($extensions['persistedQuery']) ? false : $extensions['persistedQuery'];
  }
}
