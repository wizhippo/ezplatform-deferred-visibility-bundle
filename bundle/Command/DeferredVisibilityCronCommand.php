<?php

declare(strict_types=1);

namespace Wizhippo\WizhippoDeferredVisibilityBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wizhippo\WizhippoDeferredVisibilityBundle\Service\DeferredVisibilityService;

class DeferredVisibilityCronCommand extends Command
{
    static $defaultName = 'deferred-visibility:cron';

    /**
     * @var \Wizhippo\WizhippoDeferredVisibilityBundle\Service\DeferredVisibilityService
     */
    private $deferredVisibilityService;

    public function __construct(DeferredVisibilityService $deferredVisibilityService)
    {
        parent::__construct();
        $this->deferredVisibilityService = $deferredVisibilityService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->deferredVisibilityService->updateContentStatePeriodic();

        return self::SUCCESS;
    }
}
