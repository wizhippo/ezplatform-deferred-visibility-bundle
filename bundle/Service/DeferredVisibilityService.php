<?php

namespace Wizhippo\Bundle\DeferredVisibilityBundle\Service;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\ObjectStateService;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use Wizhippo\Bundle\DeferredVisibilityBundle\Helper\ObjectStateHelper;

class DeferredVisibilityService
{
    const OBJECT_STATE_GROUP = "wizhippo_deferred_visibility_state";

    const OBJECT_STATE_NONE = "none";
    const OBJECT_STATE_DEFERRED = "deferred";
    const OBJECT_STATE_VISIBLE = "visible";
    const OBJECT_STATE_EXPIRED = "expired";

    const FIELD_DEFER_VISIBILITY_DATE = "defer_visibility_date";
    const FIELD_EXPIRE_VISIBILITY_DATE = "expire_visibility_date";

    /**
     * @var \eZ\Publish\API\Repository\LocationService
     */
    private $locationService;

    /**
     * @var \eZ\Publish\API\Repository\ObjectStateService
     */
    private $objectStateService;

    /**
     * @var \eZ\Publish\API\Repository\SearchService
     */
    private $searchService;

    /**
     * @var \Wizhippo\Bundle\DeferredVisibilityBundle\Helper\ObjectStateHelper
     */
    private $objectStateHelper;

    /**
     * @var int[]
     */
    private $supportedTypeIds;

    /**
     * DeferredPublishService constructor.
     * @param \eZ\Publish\API\Repository\LocationService $locationService
     * @param \eZ\Publish\API\Repository\ObjectStateService $objectStateService
     * @param SearchService $searchService
     * @param ObjectStateHelper $objectStateHelper
     * @param \int[] $supportedTypeIds
     */
    public function __construct(
        LocationService $locationService,
        ObjectStateService $objectStateService,
        SearchService $searchService,
        ObjectStateHelper $objectStateHelper,
        array $supportedTypeIds
    ) {
        $this->locationService = $locationService;
        $this->objectStateService = $objectStateService;
        $this->searchService = $searchService;
        $this->objectStateHelper = $objectStateHelper;
        $this->supportedTypeIds = $supportedTypeIds;
    }

    public function updateContentState(Content $content, $now = null)
    {
        if (!in_array($content->contentInfo->contentTypeId, $this->supportedTypeIds)) {
            return;
        }

        $now = ($now !== null) ? $now : new \DateTime();

        $locations = $this->locationService->loadLocations($content->contentInfo);
        $objectStateGroup = $this->objectStateHelper->loadObjectStateGroupByIdentifier(self::OBJECT_STATE_GROUP);

        $expiryDateField = $content->getField(self::FIELD_EXPIRE_VISIBILITY_DATE);
        $expiryDate = $expiryDateField !== null ? $expiryDateField->value->value : null;

        $visibleDateField = $content->getField(self::FIELD_DEFER_VISIBILITY_DATE);
        $visibleDate = $visibleDateField !== null ? $visibleDateField->value->value : null;

        if ($expiryDate !== null && $expiryDate <= $now) {
            foreach ($locations as $location) {
                if (!$location->hidden) {
                    $this->locationService->hideLocation($location);
                }
            }
            $expiredObjectState = $this->objectStateHelper->loadObjectStateByIdentifier(
                $objectStateGroup,
                self::OBJECT_STATE_EXPIRED
            );
            $this->objectStateService->setContentState(
                $content->contentInfo,
                $objectStateGroup,
                $expiredObjectState
            );
            return;
        }

        if ($visibleDate !== null && $visibleDate > $now) {
            foreach ($locations as $location) {
                if (!$location->hidden) {
                    $this->locationService->hideLocation($location);
                }
            }
            $deferredObjectState = $this->objectStateHelper->loadObjectStateByIdentifier(
                $objectStateGroup,
                self::OBJECT_STATE_DEFERRED
            );
            $this->objectStateService->setContentState(
                $content->contentInfo,
                $objectStateGroup,
                $deferredObjectState
            );
            return;
        }

        foreach ($locations as $location) {
            if ($location->hidden) {
                $this->locationService->unhideLocation($location);
            }
        }
        $visibleObjectState = $this->objectStateHelper->loadObjectStateByIdentifier(
            $objectStateGroup,
            self::OBJECT_STATE_VISIBLE
        );
        $this->objectStateService->setContentState(
            $content->contentInfo,
            $objectStateGroup,
            $visibleObjectState
        );
    }

    public function updateContentStatePeriodic($now = null)
    {
        $now = ($now !== null) ? $now : new \DateTime();

        $objectStateGroup = $this->objectStateHelper->loadObjectStateGroupByIdentifier(self::OBJECT_STATE_GROUP);
        $deferredObjectState = $this->objectStateHelper->loadObjectStateByIdentifier(
            $objectStateGroup,
            self::OBJECT_STATE_DEFERRED
        );
        $visibleObjectState = $this->objectStateHelper->loadObjectStateByIdentifier(
            $objectStateGroup,
            self::OBJECT_STATE_VISIBLE
        );

        $query = new Query();
        $query->query = new Query\Criterion\LogicalOr([
            new Query\Criterion\LogicalAnd([
                new Query\Criterion\ObjectStateId($deferredObjectState->id),
                new Query\Criterion\LogicalNot(new Query\Criterion\Field(self::FIELD_DEFER_VISIBILITY_DATE,
                    Operator::EQ, '')),
                new Query\Criterion\Field(self::FIELD_DEFER_VISIBILITY_DATE, Operator::LTE, $now->getTimestamp()),
                new Query\Criterion\ContentTypeId($this->supportedTypeIds),
            ]),
            new Query\Criterion\LogicalAnd([
                new Query\Criterion\ObjectStateId($visibleObjectState->id),
                new Query\Criterion\LogicalNot(new Query\Criterion\Field(self::FIELD_EXPIRE_VISIBILITY_DATE,
                    Operator::EQ, '')),
                new Query\Criterion\Field(self::FIELD_EXPIRE_VISIBILITY_DATE, Operator::LTE, $now->getTimestamp()),
                new Query\Criterion\ContentTypeId($this->supportedTypeIds),
            ])
        ]);

        do {
            $searchResults = $this->searchService->findContent($query);
            foreach ($searchResults->searchHits as $searchHit) {
                $this->updateContentState($searchHit->valueObject, $now);
            }
        } while($query->offset += $query->limit < $searchResults->totalCount);
    }
}
