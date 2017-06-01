<?php

namespace Wizhippo\Bundle\DeferredVisibilityBundle\Command;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateCreateStruct;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroupCreateStruct;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wizhippo\Bundle\DeferredVisibilityBundle\Service\DeferredVisibilityService;

class DeferredVisibilityInitCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName("deferred-visibility:init");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectStateHelper = $this->getContainer()->get("wizhippo_deferred_visibility_bundle.object_state");
        $repository = $this->getContainer()->get("ezpublish.api.repository");
        $objectStateService = $repository->getObjectStateService();
        $user = $repository->getUserService()->loadUser(14);
        $repository->setCurrentUser($user);
        $states = [
            [DeferredVisibilityService::OBJECT_STATE_NONE, 'None'],
            [DeferredVisibilityService::OBJECT_STATE_DEFERRED, 'Deferred'],
            [DeferredVisibilityService::OBJECT_STATE_VISIBLE, 'Visible'],
            [DeferredVisibilityService::OBJECT_STATE_EXPIRED, 'Expired'],
        ];

        try {
            $objectStateGroupDeferred = $objectStateHelper->loadObjectStateGroupByIdentifier(DeferredVisibilityService::OBJECT_STATE_GROUP);
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

            $objectStateGroupDeferred = $objectStateService->createObjectStateGroup($objectStateGroupCreateStruct);
        }

        if ($output->getVerbosity() >= $output::VERBOSITY_VERBOSE) {
            $output->writeln("ObjectStateGroup " . DeferredVisibilityService::OBJECT_STATE_GROUP . ": $objectStateGroupDeferred->id");
        }

        foreach ($states as $state) {
            try {
                $objectState = $objectStateHelper->loadObjectStateByIdentifier($objectStateGroupDeferred, $state[0]);
            } catch (NotFoundException $e) {
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

                $objectState = $objectStateService->createObjectState($objectStateGroupDeferred,
                    $objectStateCreateStruct);
            }

            if ($output->getVerbosity() >= $output::VERBOSITY_VERBOSE) {
                $output->writeln("ObjectState {$state[1]}: $objectState->id");
            }
        }
    }
}
