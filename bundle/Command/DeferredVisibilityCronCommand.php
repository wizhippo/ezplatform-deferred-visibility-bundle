<?php

namespace Wizhippo\Bundle\DeferredVisibilityBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeferredVisibilityCronCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName("deferred-visibility:cron");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get("ezpublish.api.repository");
        $user = $repository->getUserService()->loadUser(14);
        $repository->setCurrentUser($user);

        $deferredPublishService = $this->getContainer()->get("wizhippo_deferred_visibility_bundle.service.deferred_visibility");
        $deferredPublishService->updateContentStatePeriodic();
    }
}
