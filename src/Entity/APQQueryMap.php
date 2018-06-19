<?php

/**
 * @file
 * Contains \Drupal\graphql_apq\Entity\Advertiser.
 */

namespace Drupal\graphql_apq\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the Advertiser entity.
 *
 * @ingroup advertiser
 *
 * @ContentEntityType(
 *   id = "apq_query_map",
 *   label = @Translation("APQ Query Map"),
 *   base_table = "apq_query_map",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "hash",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class APQQueryMap extends ContentEntityBase implements ContentEntityInterface {

  /**
   * Retrieve the APQ's query.
   */
  public function getQuery () {
    return $this->get('query')->value;
  }

  /**
   * Determines the schema for the base_table property defined above.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['version'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Version'));

    $fields['hash'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hash'))
      ->setDescription(t('The unique hash identifying the query.'));
    
    $fields['query'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Query'))
      ->setDescription(t('The GraphQL query.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ));
    
    return $fields;
  }
}