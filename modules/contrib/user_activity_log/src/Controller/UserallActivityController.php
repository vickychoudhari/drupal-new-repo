<?php

namespace Drupal\user_activity_log\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Defines UserallActivityController class.
 */
class UserallActivityController extends ControllerBase {

  protected  $connection;
  protected $currentUser;

  /**
   * Class constructor.
   */
  public  function __construct(Connection $connection , AccountProxyInterface $current_user) {
    $this->connection = $connection;
    $this->currentUser = $current_user;
  }

  /**
   * Create function Constructor.
   */
  public static function create(ContainerInterface $container) {
    return new static (
       $container->get('database'),
       $container->get('current_user')
    );
  }

  /**
   * Create function List of nodes.
   */
  public function listOfNodes() {

    // Latest node created by user.
    $query = $this->connection->select('node_field_data', 'n');
    $query->Join('users_field_data', 'u', 'n.uid = u.uid');
    $query->fields('n', ['title', 'nid']);
    $query->condition('n.uid', $this->currentUser->id());
    $query->condition('n.status', 1);
    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);
    $results = $pager->execute()->fetchAll();

    // Create the header.
    $header = array('Nid', 'Title');
    $rows = [];

    // get the rows.
    foreach ($results as $key =>$value) {
      $rows[$key]['nid'] = $value->nid;
      $rows[$key]['title'] = $value->title;
    }

    $output['table'] = [
      '#theme' => ['table'],
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No results found'),
  ];

    //Add the pager.
    $output['pager'] = ['#type' => 'pager'];
    return $output;
  }


  /**
   * Generates an user bulk upload page.
   */
  public function listOfComments() {
    // Show all comment by user.
    $query = $this->connection->select('comment_field_data', 'c');
    $query->join('node_field_data', 'n', 'c.uid = n.uid');
    $query->fields('c', ['subject']);
    $query->fields('n', ['nid']);
    $query->condition('c.uid', $this->currentUser->id());
    $query->condition('c.status', 1);
    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);
    $results = $pager->execute()->fetchAll();

    // Create the header.
    $header = array('Nid', 'Comment');

    // Initialize an empty array
    $rows = [];

    // get the rows.
    foreach ($results as $key =>$value) {
      $rows[$key]['nid'] = $value->nid;
      $rows[$key]['subject'] = $value->subject;

    }
    $output['table'] = [
      '#theme' => ['table'],
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No results found'),
  ];

    //Add the pager.
    $output['pager'] = ['#type' => 'pager'];
    return $output;
  }

}

