<?php

declare(strict_types=1);

namespace Wizhippo\WizhippoDeferredVisibilityBundle\EventListener;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\Events\Content\PublishVersionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wizhippo\WizhippoDeferredVisibilityBundle\Service\DeferredVisibilityService;

class PublishVersionListener implements EventSubscriberInterface
{
    /**
     * @var ContentService
     */
    private $contentService;

    /**
     * @var \Wizhippo\WizhippoDeferredVisibilityBundle\Service\DeferredVisibilityService
     */
    private $deferredVisibilityService;

    public function __construct(ContentService $contentService, DeferredVisibilityService $deferredVisibilityService)
    {
        $this->contentService = $contentService;
        $this->deferredVisibilityService = $deferredVisibilityService;
    }

    public static function getSubscribedEvents()
    {
        return [PublishVersionEvent::class => 'publishVersion'];
    }

    public function publishVersion(PublishVersionEvent $event)
    {
        $this->deferredVisibilityService->updateContentState($event->getContent());
    }
}
