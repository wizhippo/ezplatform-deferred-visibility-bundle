<?php

declare(strict_types=1);

namespace Wizhippo\WizhippoDeferredVisibilityBundle\Helper;

use eZ\Publish\API\Repository\ObjectStateService;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;

class ObjectStateHelper
{
    /**
     * @var ObjectStateService
     */
    private $objectStateService;

    /**
     * ObjectStateHelper constructor.
     *
     * @param ObjectStateService $objectStateService
     */
    public function __construct(ObjectStateService $objectStateService)
    {
        $this->objectStateService = $objectStateService;
    }

    public function loadObjectStateGroupByIdentifier($identifier)
    {
        $objectStateGroups = $this->objectStateService->loadObjectStateGroups();
        foreach ($objectStateGroups as $objectStateGroup) {
            if ($objectStateGroup->identifier === $identifier) {
                return $objectStateGroup;
            }
        }

        throw new NotFoundException('ObjectStateGroup', $identifier);
    }


    public function loadObjectStateByIdentifier($objectStateGroup, $identifier)
    {
        $objectStates = $this->objectStateService->loadObjectStates($objectStateGroup);
        foreach ($objectStates as $objectState) {
            if ($objectState->identifier === $identifier) {
                return $objectState;
            }
        }

        throw new NotFoundException('ObjectState', $identifier);
    }
}
