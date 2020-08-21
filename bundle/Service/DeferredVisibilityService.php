<?php

declare(strict_types=1);

namespace Wizhippo\WizhippoDeferredVisibilityBundle\Service;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use Wizhippo\WizhippoDeferredVisibilityBundle\Helper\ObjectStateHelper;

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
     * @var \eZ\Publish\API\Repository\Repository
     */
    private $repository;

    /**
     * @var int[]
     */
    private $supportedTypeIds;

    public function __construct(
        Repository $repository,
        array $supportedTypeIds
    )
    {
        $this->repository = $repository;
        $this->supportedTypeIds = $supportedTypeIds;
    }

    public function updateContentState(Content $content, $now = null)
    {
        if (!in_array($content->contentInfo->contentTypeId, $this->supportedTypeIds)) {
            return;
        }

        $this->repository->sudo(
            function (Repository $repo) use ($content, $now) {
                $locationService = $repo->getLocationService();
                $objectStateService = $repo->getObjectStateService();
                $objectStateHelper = new ObjectStateHelper($objectStateService);

                $now = ($now !== null) ? $now : new \DateTime();

                $locations = $locationService->loadLocations($content->contentInfo);
                $objectStateGroup = $objectStateHelper->loadObjectStateGroupByIdentifier(self::OBJECT_STATE_GROUP);

                $expiryDateField = $content->getField(self::FIELD_EXPIRE_VISIBILITY_DATE);
                $expiryDate = $expiryDateField !== null ? $expiryDateField->value->value : null;

                $visibleDateField = $content->getField(self::FIELD_DEFER_VISIBILITY_DATE);
                $visibleDate = $visibleDateField !== null ? $visibleDateField->value->value : null;

                if ($expiryDate !== null && $expiryDate <= $now) {
                    foreach ($locations as $location) {
                        if (!$location->hidden) {
                            $locationService->hideLocation($location);
                        }
                    }
                    $expiredObjectState = $objectStateHelper->loadObjectStateByIdentifier(
                        $objectStateGroup,
                        self::OBJECT_STATE_EXPIRED
                    );
                    $objectStateService->setContentState(
                        $content->contentInfo,
                        $objectStateGroup,
                        $expiredObjectState
                    );

                    return;
                }

                if ($visibleDate !== null && $visibleDate > $now) {
                    foreach ($locations as $location) {
                        if (!$location->hidden) {
                            $locationService->hideLocation($location);
                        }
                    }
                    $deferredObjectState = $objectStateHelper->loadObjectStateByIdentifier(
                        $objectStateGroup,
                        self::OBJECT_STATE_DEFERRED
                    );
                    $objectStateService->setContentState(
                        $content->contentInfo,
                        $objectStateGroup,
                        $deferredObjectState
                    );

                    return;
                }

                foreach ($locations as $location) {
                    if ($location->hidden) {
                        $locationService->unhideLocation($location);
                    }
                }
                $visibleObjectState = $objectStateHelper->loadObjectStateByIdentifier(
                    $objectStateGroup,
                    self::OBJECT_STATE_VISIBLE
                );
                $objectStateService->setContentState(
                    $content->contentInfo,
                    $objectStateGroup,
                    $visibleObjectState
                );
            }
        );
    }

    public function updateContentStatePeriodic($now = null)
    {
        $this->repository->sudo(
            function (Repository $repo) use ($now) {
                $searchService = $repo->getSearchService();
                $objectStateHelper = new ObjectStateHelper($repo->getObjectStateService());

                $now = ($now !== null) ? $now : new \DateTime();

                $objectStateGroup = $objectStateHelper->loadObjectStateGroupByIdentifier(self::OBJECT_STATE_GROUP);
                $deferredObjectState = $objectStateHelper->loadObjectStateByIdentifier(
                    $objectStateGroup,
                    self::OBJECT_STATE_DEFERRED
                );
                $visibleObjectState = $objectStateHelper->loadObjectStateByIdentifier(
                    $objectStateGroup,
                    self::OBJECT_STATE_VISIBLE
                );

                $query = new Query();
                $query->query = new Query\Criterion\LogicalOr([
                    new Query\Criterion\LogicalAnd([
                        new Query\Criterion\ObjectStateId($deferredObjectState->id),
                        new Query\Criterion\LogicalNot(new Query\Criterion\Field(
                            self::FIELD_DEFER_VISIBILITY_DATE,
                            Operator::EQ,
                            ''
                        )),
                        new Query\Criterion\Field(self::FIELD_DEFER_VISIBILITY_DATE, Operator::LTE,
                            $now->getTimestamp()),
                        new Query\Criterion\ContentTypeId($this->supportedTypeIds),
                    ]),
                    new Query\Criterion\LogicalAnd([
                        new Query\Criterion\ObjectStateId($visibleObjectState->id),
                        new Query\Criterion\LogicalNot(new Query\Criterion\Field(
                            self::FIELD_EXPIRE_VISIBILITY_DATE,
                            Operator::EQ,
                            ''
                        )),
                        new Query\Criterion\Field(self::FIELD_EXPIRE_VISIBILITY_DATE, Operator::LTE,
                            $now->getTimestamp()),
                        new Query\Criterion\ContentTypeId($this->supportedTypeIds),
                    ])
                ]);

                do {
                    $searchResults = $searchService->findContent($query);
                    foreach ($searchResults->searchHits as $searchHit) {
                        $this->updateContentState($searchHit->valueObject, $now);
                    }
                } while ($query->offset += $query->limit < $searchResults->totalCount);
            }
        );
    }
}
