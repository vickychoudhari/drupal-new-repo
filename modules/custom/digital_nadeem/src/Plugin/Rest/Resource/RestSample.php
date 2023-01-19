<?php

namespace Drupal\digital_nadeem\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "rest_sample",
 *   label = @Translation("Rest sample"),
 *   uri_paths = {
 *     "canonical" = "/rest/digitalnadeem/api/get/node/{type}"
 *   }
 * )
 */
class RestSample extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($type = NULL) {

   // You must to implement the logic of your REST Resource here.
    $data = ['message' => 'Hello, this is a rest service and parameter is: '.$type];
	    
    $response = new ResourceResponse($data);
    // In order to generate fresh result every time (without clearing 
    // the cache), you need to invalidate the cache.
    $response->addCacheableDependency($data);
    return $response;
  }

}