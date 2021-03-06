<?php

declare(strict_types=1);

/*
 * This file is part of the ekino Drupal Debug project.
 *
 * (c) ekino
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ekino\Drupal\Debug\Action\WatchRoutingDefinitions;

use Drupal\Core\Routing\RouteBuilderInterface;
use Ekino\Drupal\Debug\Action\ActionWithOptionsInterface;
use Ekino\Drupal\Debug\Action\EventSubscriberActionInterface;
use Ekino\Drupal\Debug\Exception\NotSupportedException;
use Ekino\Drupal\Debug\Kernel\Event\AfterRequestPreHandleEvent;
use Ekino\Drupal\Debug\Kernel\Event\DebugKernelEvents;
use Ekino\Drupal\Debug\Resource\ResourcesFreshnessChecker;

class WatchRoutingDefinitionsAction implements EventSubscriberActionInterface, ActionWithOptionsInterface
{
    /**
     * @var WatchRoutingDefinitionsOptions
     */
    private $options;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return array(
            DebugKernelEvents::AFTER_REQUEST_PRE_HANDLE => 'process',
        );
    }

    /**
     * @param WatchRoutingDefinitionsOptions $options
     */
    public function __construct(WatchRoutingDefinitionsOptions $options)
    {
        $this->options = $options;
    }

    /**
     * @param AfterRequestPreHandleEvent $event
     */
    public function process(AfterRequestPreHandleEvent $event): void
    {
        $resourcesFreshnessChecker = new ResourcesFreshnessChecker($this->options->getCacheFilePath(), $this->options->getFilteredResourcesCollection($event->getEnabledModules(), $event->getEnabledThemes()));
        if ($resourcesFreshnessChecker->isFresh()) {
            return;
        }

        $container = $event->getContainer();
        if (!$container->has('router.builder')) {
            throw new NotSupportedException('The "router.builder" service should already be set in the container.');
        }

        $routerBuilder = $container->get('router.builder');
        if (!$routerBuilder instanceof RouteBuilderInterface) {
            throw new NotSupportedException(\sprintf('The "router.builder" service class should implement the "%s" interface.', RouteBuilderInterface::class));
        }

        $routerBuilder->rebuild();

        $resourcesFreshnessChecker->commit();
    }

    /**
     * {@inheritdoc}
     */
    public static function getOptionsClass(): string
    {
        return WatchRoutingDefinitionsOptions::class;
    }
}
