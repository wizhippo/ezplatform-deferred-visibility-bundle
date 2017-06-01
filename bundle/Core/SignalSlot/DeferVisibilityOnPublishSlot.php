<?php

namespace Wizhippo\Bundle\DeferredVisibilityBundle\Core\SignalSlot;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\Core\SignalSlot\Signal;
use eZ\Publish\Core\SignalSlot\Slot as BaseSlot;
use Wizhippo\Bundle\DeferredVisibilityBundle\Service\DeferredVisibilityService;

class DeferVisibilityOnPublishSlot extends BaseSlot
{
    /**
     * @var ContentService
     */
    private $contentService;

    /**
     * @var \Wizhippo\Bundle\DeferredVisibilityBundle\Service\DeferredVisibilityService
     */
    private $deferredVisibilityService;

    /**
     * DeferVisibilityOnPublishSlot constructor.
     * @param ContentService $contentService
     * @param DeferredVisibilityService $deferredVisibilityService
     */
    public function __construct(ContentService $contentService, DeferredVisibilityService $deferredVisibilityService)
    {
        $this->contentService = $contentService;
        $this->deferredVisibilityService = $deferredVisibilityService;
    }

    /**
     * @param Signal $signal
     */
    public function receive(Signal $signal)
    {
        if (!$signal instanceof Signal\ContentService\PublishVersionSignal) {
            return;
        }

        $content = $this->contentService->loadContent($signal->contentId, null, $signal->versionNo);
        $this->deferredVisibilityService->updateContentState($content);
    }
}
