<?php

declare(strict_types=1);

namespace Wizhippo\WizhippoDeferredVisibilityBundle\Tests\Service;

use eZ\Publish\API\Repository\Tests\BaseTest;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateCreateStruct;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroupCreateStruct;
use Wizhippo\WizhippoDeferredVisibilityBundle\Service\DeferredVisibilityService;

class DeferredVisibilityServiceTest extends BaseTest
{
    /**
     * @var DeferredVisibilityService
     */
    protected $deferredVisibilityService;

    /**
     * @var ContentType
     */
    protected $contentType;

    /**
     * @var \DateTime
     */
    protected $now;

    /**
     * @var \DateTime
     */
    protected $past;

    /**
     * @var \DateTime
     */
    protected $future;

    /** @var \eZ\Publish\API\Repository\PermissionResolver */
    private $permissionResolver;

    /** @var \eZ\Publish\API\Repository\ContentService */
    private $contentService;

    /** @var \eZ\Publish\API\Repository\LocationService */
    private $locationService;

    /**
     * @var \eZ\Publish\API\Repository\ObjectStateService
     */
    private $objectStateService;

    /**
     * @var \eZ\Publish\API\Repository\ContentTypeService
     */
    private $contentTypeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new \DateTime();
        $this->past = clone $this->now;
        $this->past->sub(new \DateInterval('P1D'));
        $this->future = clone $this->now;
        $this->future->add(new \DateInterval('P1D'));

        $repository = $this->getRepository();
        $this->permissionResolver = $repository->getPermissionResolver();
        $this->contentService = $repository->getContentService();
        $this->locationService = $repository->getLocationService();
        $this->objectStateService = $repository->getObjectStateService();
        $this->contentTypeService = $repository->getContentTypeService();

        $states = [
            [DeferredVisibilityService::OBJECT_STATE_NONE, 'None'],
            [DeferredVisibilityService::OBJECT_STATE_DEFERRED, 'Deferred'],
            [DeferredVisibilityService::OBJECT_STATE_VISIBLE, 'Visible'],
            [DeferredVisibilityService::OBJECT_STATE_EXPIRED, 'Expired'],
        ];

        $objectStateGroupCreateStruct = new ObjectStateGroupCreateStruct();
        $objectStateGroupCreateStruct->identifier = DeferredVisibilityService::OBJECT_STATE_GROUP;
        $objectStateGroupCreateStruct->defaultLanguageCode = "eng-GB";
        $objectStateGroupCreateStruct->names = [
            "eng-GB" => "Deferred Visibility",
        ];
        $objectStateGroupCreateStruct->descriptions = [
            "eng-GB" => "",
        ];

        $objectStateGroupDeferred = $this->objectStateService->createObjectStateGroup($objectStateGroupCreateStruct);

        foreach ($states as $state) {
            $objectStateCreateStruct = new ObjectStateCreateStruct();
            $objectStateCreateStruct->identifier = $state[0];
            $objectStateCreateStruct->priority = 0;
            $objectStateCreateStruct->defaultLanguageCode = "eng-GB";
            $objectStateCreateStruct->names = [
                "eng-GB" => $state[1],
            ];
            $objectStateCreateStruct->descriptions = [
                "eng-GB" => "",
            ];

            $this->objectStateService->createObjectState($objectStateGroupDeferred,
                $objectStateCreateStruct);
        }

        $contentTypeCreateStruct = $this->contentTypeService->newContentTypeCreateStruct('blog-post');
        $contentTypeCreateStruct->mainLanguageCode = 'eng-GB';
        $contentTypeCreateStruct->names = array(
            'eng-GB' => 'Blog post'
        );
        $contentTypeCreateStruct->descriptions = array(
            'eng-GB' => 'A blog post'
        );
        $contentTypeCreateStruct->creatorId = $this->generateId('user', $this->permissionResolver->getCurrentUserReference()->getUserId());
        $contentTypeCreateStruct->creationDate = $this->createDateTime();

        $deferFieldCreate = $this->contentTypeService->newFieldDefinitionCreateStruct(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE,
            'ezdatetime');
        $deferFieldCreate->position = 1;
        $deferFieldCreate->names = array(
            'eng-GB' => 'Defer',
        );
        $deferFieldCreate->isTranslatable = true;
        $deferFieldCreate->isRequired = false;

        $contentTypeCreateStruct->addFieldDefinition($deferFieldCreate);

        $expireFieldCreate = $this->contentTypeService->newFieldDefinitionCreateStruct(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE,
            'ezdatetime');
        $expireFieldCreate->position = 2;
        $expireFieldCreate->names = array(
            'eng-GB' => 'Expire',
        );
        $expireFieldCreate->isTranslatable = true;
        $expireFieldCreate->isRequired = false;

        $contentTypeCreateStruct->addFieldDefinition($expireFieldCreate);

        $groups = array(
            $this->contentTypeService->loadContentTypeGroupByIdentifier('Media'),
            $this->contentTypeService->loadContentTypeGroupByIdentifier('Setup'),
        );

        $contentTypeDraft = $this->contentTypeService->createContentType(
            $contentTypeCreateStruct,
            $groups
        );

        $this->contentTypeService->publishContentTypeDraft($contentTypeDraft);
        $this->contentType = $this->contentTypeService->loadContentTypeByIdentifier('blog-post');

        $this->deferredVisibilityService = new DeferredVisibilityService(
            $repository,
            [$this->contentType->id]
        );
    }

    public function testDeferEmptyExpiredEmpty()
    {
        $contentCreateStruct = $this->contentService->newContentCreateStruct($this->contentType, 'eng-GB');

        $locationCreateStruct = $this->locationService->newLocationCreateStruct(2);
        $draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $this->contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);
    }

    public function testDeferEmptyExpiredPast()
    {
        $contentCreateStruct = $this->contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->past);

        $locationCreateStruct = $this->locationService->newLocationCreateStruct(2);
        $draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $this->contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferEmptyExpiredFuture()
    {
        $contentCreateStruct = $this->contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->future);

        $locationCreateStruct = $this->locationService->newLocationCreateStruct(2);
        $draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $this->contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferPastExpiredEmpty()
    {
        $contentCreateStruct = $this->contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->past);

        $locationCreateStruct = $this->locationService->newLocationCreateStruct(2);
        $draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $this->contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);
    }

    public function testDeferFutureExpiredEmpty()
    {
        $contentCreateStruct = $this->contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->future);

        $locationCreateStruct = $this->locationService->newLocationCreateStruct(2);
        $draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $this->contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);
    }

    public function testDeferFutureExpireFuture()
    {
        $contentCreateStruct = $this->contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->future);
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->future);

        $locationCreateStruct = $this->locationService->newLocationCreateStruct(2);
        $draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $this->contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferPastExpirePast()
    {
        $contentCreateStruct = $this->contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->past);
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->past);

        $locationCreateStruct = $this->locationService->newLocationCreateStruct(2);
        $draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $this->contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferPastExpireFuture()
    {
        $contentCreateStruct = $this->contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->past);
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->future);

        $locationCreateStruct = $this->locationService->newLocationCreateStruct(2);
        $draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $this->contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferFutureExpirePast()
    {
        $contentCreateStruct = $this->contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->future);
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->past);

        $locationCreateStruct = $this->locationService->newLocationCreateStruct(2);
        $draft = $this->contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $this->contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);
        $location = $this->locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }
}
