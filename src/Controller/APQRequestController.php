<?php

namespace Drupal\graphql_apq\Controller;

use Drupal\graphql\Controller\RequestController;
use Drupal\graphql_apq\GraphQL\Execution\APQQueryProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles APQ GraphQL requests.
 */
class APQRequestController extends RequestController {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('graphql_apq.query_processor'),
      $container->getParameter('graphql.config')
    );
  }

  /**
   * @inheritDoc
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(APQQueryProcessor $processor, array $parameters) {
    parent::__construct($processor, $parameters);
  }

}
