<?php

/**
 * @file
 * Contains of \Drupal\taxonomy\TermBreadcrumbBuilder.
 */

namespace Drupal\civicrm;

use Drupal\civicrm\CivicrmPageState;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a custom taxonomy breadcrumb builder that uses the term hierarchy.
 */
class CivicrmBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  protected $civicrmPageState;

  public function __construct(CivicrmPageState $civicrmPageState) {
    $this->civicrmPageState = $civicrmPageState;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route_object = $route_match->getRouteObject();
    if ($route_object) {
      $content = $route_object->getDefault('_content');
      if (isset($content) && $content == 'Drupal\civicrm\Controller\CivicrmController::main') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumbs = array();
    $breadcrumbs[] = l(t('Home'), '<front>');

    foreach ($this->civicrmPageState->getBreadcrumbs() as $name => $url) {
      // We expect all urls to have already been passed through the url helper, and therefore
      // be valid Drupal urls.
      $breadcrumbs[] = "<a href=\"{$url}\">{$name}</a>";
    }
    return $breadcrumbs;
  }
}