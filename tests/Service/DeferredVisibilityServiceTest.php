<?php

namespace Wizhippo\Bundle\DeferredVisibilityBundle\Tests\Service;

use eZ\Publish\API\Repository\Tests\BaseTest;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateCreateStruct;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroupCreateStruct;
use eZ\Publish\Core\Repository\Values\Content\Content;
use eZ\Publish\Core\Repository\Values\Content\VersionInfo;
use eZ\Publish\Core\Repository\Values\User\User;
use Wizhippo\Bundle\DeferredVisibilityBundle\Helper\ObjectStateHelper;
use Wizhippo\Bundle\DeferredVisibilityBundle\Service\DeferredVisibilityService;

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

    protected function setUp()
    {
        parent::setUp();

        $this->now = new \DateTime();
        $this->past = clone $this->now;
        $this->past->sub(new \DateInterval('P1D'));
        $this->future = clone $this->now;
        $this->future->add(new \DateInterval('P1D'));

        $repository = $this->getRepository();
        $repository->setCurrentUser($this->getStubbedUser(14));
        $locationService = $repository->getLocationService();
        $contentTypeService = $repository->getContentTypeService();
        $objectStateService = $repository->getObjectStateService();
        $searchService = $repository->getSearchService();
        $objectStateHelper = new ObjectStateHelper($objectStateService);

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

        $objectStateGroupDeferred = $objectStateService->createObjectStateGroup($objectStateGroupCreateStruct);

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

            $objectStateService->createObjectState($objectStateGroupDeferred,
                $objectStateCreateStruct);
        }

        $typeCreate = $contentTypeService->newContentTypeCreateStruct('blog-post');
        $typeCreate->mainLanguageCode = 'eng-GB';
        $typeCreate->names = array(
            'eng-GB' => 'Blog post'
        );
        $typeCreate->descriptions = array(
            'eng-GB' => 'A blog post'
        );
        $typeCreate->creatorId = $this->generateId('user', $repository->getCurrentUser()->id);
        $typeCreate->creationDate = $this->createDateTime();

        $deferFieldCreate = $contentTypeService->newFieldDefinitionCreateStruct(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE,
            'ezdatetime');
        $deferFieldCreate->position = 1;
        $deferFieldCreate->names = array(
            'eng-GB' => 'Defer',
        );
        $deferFieldCreate->isTranslatable = true;
        $deferFieldCreate->isRequired = false;

        $typeCreate->addFieldDefinition($deferFieldCreate);

        $expireFieldCreate = $contentTypeService->newFieldDefinitionCreateStruct(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE,
            'ezdatetime');
        $expireFieldCreate->position = 2;
        $expireFieldCreate->names = array(
            'eng-GB' => 'Expire',
        );
        $expireFieldCreate->isTranslatable = true;
        $expireFieldCreate->isRequired = false;

        $typeCreate->addFieldDefinition($expireFieldCreate);

        $groups = array(
            $contentTypeService->loadContentTypeGroupByIdentifier('Media'),
            $contentTypeService->loadContentTypeGroupByIdentifier('Setup'),
        );

        $contentTypeDraft = $contentTypeService->createContentType(
            $typeCreate,
            $groups
        );

        $contentTypeService->publishContentTypeDraft($contentTypeDraft);
        $this->contentType = $contentTypeService->loadContentTypeByIdentifier('blog-post');

        $this->deferredVisibilityService = new DeferredVisibilityService(
            $locationService,
            $objectStateService,
            $searchService,
            $objectStateHelper,
            [$this->contentType->id]
        );
    }

    public function testDeferEmptyExpiredEmpty()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $contentCreateStruct = $contentService->newContentCreateStruct($this->contentType, 'eng-GB');

        $locationCreateStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);
    }

    public function testDeferEmptyExpiredPast()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $contentCreateStruct = $contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->past);

        $locationCreateStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferEmptyExpiredFuture()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $now = new \DateTime();
        $past = clone $now;
        $past->sub(new \DateInterval('P1D'));
        $future = clone $now;
        $future->add(new \DateInterval('P1D'));

        $contentCreateStruct = $contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->future);

        $locationCreateStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferPastExpiredEmpty()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $now = new \DateTime();
        $past = clone $now;
        $past->sub(new \DateInterval('P1D'));
        $future = clone $now;
        $future->add(new \DateInterval('P1D'));

        $contentCreateStruct = $contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->past);

        $locationCreateStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);
    }

    public function testDeferFutureExpiredEmpty()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $contentCreateStruct = $contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->future);

        $locationCreateStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);
    }

    public function testDeferFutureExpireFuture()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $contentCreateStruct = $contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->future);
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->future);

        $locationCreateStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferPastExpirePast()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $contentCreateStruct = $contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->past);
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->past);

        $locationCreateStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferPastExpireFuture()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $contentCreateStruct = $contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->past);
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->future);

        $locationCreateStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(false, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    public function testDeferFutureExpirePast()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $contentCreateStruct = $contentService->newContentCreateStruct($this->contentType, 'eng-GB');
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_DEFER_VISIBILITY_DATE, $this->future);
        $contentCreateStruct->setField(DeferredVisibilityService::FIELD_EXPIRE_VISIBILITY_DATE, $this->past);

        $locationCreateStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->deferredVisibilityService->updateContentState($content, $this->now);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);

        $this->deferredVisibilityService->updateContentStatePeriodic($this->future);

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $this->assertEquals(true, $location->hidden);
    }

    /**
     * Returns User stub with $id as User/Content id.
     *
     * @param int $id
     *
     * @return \eZ\Publish\API\Repository\Values\User\User
     */
    protected function getStubbedUser($id)
    {
        return new User(
            array(
                'content' => new Content(
                    array(
                        'versionInfo' => new VersionInfo(
                            array(
                                'contentInfo' => new ContentInfo(array('id' => $id)),
                            )
                        ),
                        'internalFields' => array(),
                    )
                ),
            )
        );
    }
}
