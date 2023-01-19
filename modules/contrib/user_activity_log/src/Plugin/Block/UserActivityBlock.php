<?php

namespace Drupal\user_activity_log\plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides a 'userinfo' block.
 *
 * @Block(
 *  id = "user_block",
 *  admin_label = @Translation(" User Info"),
 * )
 */
class UserActivityBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $connection;
  protected $currentUser;

  /**
   * Desc it is a constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */

  /**
   * Create function for Constructor.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('current_user')
    );
  }

  /**
   * Desc it is a constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Database\Connection $connection
   */

  /**
   * Create function Construct cantainer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection , AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connection = $connection;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}.
   */
  public function build() {

    // Total node created by user.
    $query = $this->connection->select('node_field_data', 'n');
    $query->addTag('node_access');
    $query->condition('n.uid', $this->currentUser->id());
    $query->condition('n.status', 1);
    $query->addExpression('COUNT(*)');
    $nodes = $query->execute()->fetchField();

    // Total comments by user.
    $query = $this->connection->select('comment_field_data', 'c');
    $query->addTag('node_access');
    $query->condition('c.uid', $this->currentUser->id());
    $query->condition('c.status', 1);
    $query->addExpression('COUNT(*)');
    $comments = $query->execute()->fetchField();

    // Latest node created by user.
    $query = $this->connection->select('node_field_data', 'n');
    $query->Join('users_field_data', 'u', 'n.uid = u.uid');
    $query->fields('n', ['title', 'nid']);
    $query->fields('u', ['uid']);
    $query->condition('n.uid', $this->currentUser->id());
    $query->orderBy('n.created', 'DESC');
    $query->condition('n.status', 1);
    $query->range(0, 3);
    $result = $query->execute()->fetchAll();
    $list_of_nodes = [];
    foreach ($result as $key => $value) {
      $options = ['absolute' => TRUE];
      $url = Url::fromRoute('entity.node.canonical', ['node' => $value->nid], $options);
      $path_list = $url->toString();
      $list_of_nodes[$key]['title'] = $value->title;
      $list_of_nodes[$key]['url'] = $path_list;
    }

    // Latest comment by user.
    $query = $this->connection->select('comment_field_data', 'c');
    $query->join('node_field_data', 'n', 'c.uid = n.uid');
    $query->fields('c', ['subject', 'uid']);
    $query->fields('n', ['nid', 'title']);
    $query->condition('c.uid', $this->currentUser->id());
    $query->orderBy('n.created', 'DESC');
    $query->condition('c.status', 1);
    $query->range(0, 3);
    $result = $query->execute()->fetchAll();
    $list_of_created_nodes = [];
    foreach ($result as $key => $value) {
      $options = ['absolute' => TRUE];
      $url = Url::fromRoute('entity.node.canonical', ['node' => $value->nid], $options);
      $path_list = $url->toString();
      $list_of_created_nodes[$key]['subject'] = $value->subject;
      $list_of_created_nodes[$key]['uri'] = $path_list;
      $list_of_created_nodes[$key]['title'] = $value->title;
    }
    return [
      '#theme' => 'user_activity_log_tpl',
      '#nodes_count' => $nodes,
      '#comment_count' => $comments,
      '#list_nodes' => $list_of_nodes,
      '#list_comments' => $list_of_created_nodes,
      '#cache' => ['max-age' => 0,
      ],
    ];
  }

}
