<?php

declare(strict_types=1);

namespace Wizhippo\WizhippoDeferredVisibilityBundle\Command;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateCreateStruct;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroupCreateStruct;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Wizhippo\WizhippoDeferredVisibilityBundle\Service\DeferredVisibilityService;

class DeferredVisibilityInitCommand extends Command
{
    use ContainerAwareTrait;

    static $defaultName = 'deferred-visibility:init';

    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    private $repository;

    public function __construct(Repository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->repository->sudo(
            function (Repository $repo) use ($input, $output) {
                $states = [
                    ['identifier' => DeferredVisibilityService::OBJECT_STATE_NONE, 'name' => 'None'],
                    ['identifier' => DeferredVisibilityService::OBJECT_STATE_DEFERRED, 'name' => 'Deferred'],
                    ['identifier' => DeferredVisibilityService::OBJECT_STATE_VISIBLE, 'name' => 'Visible'],
                    ['identifier' => DeferredVisibilityService::OBJECT_STATE_EXPIRED, 'name' => 'Expired'],
                ];

                $objectStateService = $repo->getObjectStateService();

                try {
                    $objectStateGroupDeferred = $objectStateService->loadObjectStateGroupByIdentifier(
                        DeferredVisibilityService::OBJECT_STATE_GROUP
                    );
                } catch (NotFoundException $e) {
                    $objectStateGroupCreateStruct = new ObjectStateGroupCreateStruct();
                    $objectStateGroupCreateStruct->identifier = DeferredVisibilityService::OBJECT_STATE_GROUP;
                    $objectStateGroupCreateStruct->defaultLanguageCode = "eng-GB";
                    $objectStateGroupCreateStruct->names = [
                        "eng-GB" => "Deferred Visibility",
                    ];
                    $objectStateGroupCreateStruct->descriptions = [
                        "eng-GB" => "",
                    ];

                    $objectStateGroupDeferred = $objectStateService->createObjectStateGroup(
                        $objectStateGroupCreateStruct
                    );
                }

                if ($output->getVerbosity() >= $output::VERBOSITY_VERBOSE) {
                    $output->writeln(
                        "ObjectStateGroup " . DeferredVisibilityService::OBJECT_STATE_GROUP . ": $objectStateGroupDeferred->id"
                    );
                }

                foreach ($states as $state) {
                    try {
                        $objectState = $objectStateService->loadObjectStateByIdentifier(
                            $objectStateGroupDeferred,
                            $state['identifier']
                        );
                    } catch (NotFoundException $e) {
                        $objectStateCreateStruct = new ObjectStateCreateStruct();
                        $objectStateCreateStruct->identifier = $state['identifier'];
                        $objectStateCreateStruct->priority = 0;
                        $objectStateCreateStruct->defaultLanguageCode = "eng-GB";
                        $objectStateCreateStruct->names = [
                            "eng-GB" => $state['name'],
                        ];
                        $objectStateCreateStruct->descriptions = [
                            "eng-GB" => "",
                        ];

                        $objectState = $objectStateService->createObjectState(
                            $objectStateGroupDeferred,
                            $objectStateCreateStruct
                        );
                    }

                    if ($output->getVerbosity() >= $output::VERBOSITY_VERBOSE) {
                        $output->writeln("ObjectState {$state['name']}: $objectState->id");
                    }
                }
            }
        );

        return self::SUCCESS;
    }
}
