<?php

declare(strict_types=1);

namespace Wizhippo\WizhippoDeferredVisibilityBundle\DependencyInjection;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\SiteAccessAware\Configuration as SiteAccessConfiguration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Configuration extends SiteAccessConfiguration
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('wizhippo');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
