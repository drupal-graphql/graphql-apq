<?php

namespace Drupal\graphql_apq\GraphQL\QueryProvider;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use GraphQL\Server\OperationParams;
use Drupal\graphql\GraphQL\Cache\CacheableRequestError;
use Drupal\graphql\GraphQL\QueryProvider\QueryProviderInterface;

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
    $extensions = $operation->getOriginalInput('extensions');

    // Early skip if no persistedQuery protocol implemented in operation.
    if (empty($extensions['persistedQuery'])) {
      return NULL;
    }

    list($version, $hash) = explode(':', $id);

    // Check that the hash is properly formatted.
    if (empty($version) || empty($hash)) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('apq_query_map');

    $apqs = $storage->loadByProperties([
      'version' => $version,
      'hash' => $hash,
    ]);

    $apq = empty($apqs) ? NULL : reset($apqs);
    
    // Let default handler work in case query is already cached.
    if (!empty($operation->originalQuery) && !empty($apq)) {
      return $operation->originalQuery;
    }

    // Retrieve query in case we have it cached.
    if (empty($operation->originalQuery) && !empty($apq)) {
      return $apq->getQuery();
    }

    // Add the query to the cache in case we don't have it yet.
    if (!empty($operation->originalQuery) && empty($apq)) {
      $apq = $storage->create([
        'version' => $version,
        'query' => $operation->originalQuery,
        'hash' => $hash,
      ]);

      $apq->save();

      // Invalidate previous not found response.
      Cache::invalidateTags([$this->getCacheTag($hash)]);

      return $operation->originalQuery;
    }

    // In case no query is set after all tries,
    // respond with PersistedQueryNotFound to allow fulfilling.
    if (empty($operation->originalQuery)) {
      throw (new CacheableRequestError('PersistedQueryNotFound'))
        ->addCacheTags([$this->getCacheTag($hash)]);
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

}
