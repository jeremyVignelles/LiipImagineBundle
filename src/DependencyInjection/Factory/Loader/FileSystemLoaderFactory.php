<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\DependencyInjection\Factory\Loader;

use Liip\ImagineBundle\Exception\InvalidArgumentException;
use Liip\ImagineBundle\Utility\Framework\SymfonyFramework;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FileSystemLoaderFactory extends AbstractLoaderFactory
{
    /**
     * {@inheritdoc}
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        $locatorDefinition = new ChildDefinition(sprintf('liip_imagine.binary.locator.%s', $config['locator']));
        $locatorDefinition->replaceArgument(0, $this->resolveDataRoots($config['data_root'], $config['bundle_resources'], $container));
        $locatorDefinition->replaceArgument(1, $config['allow_unresolvable_data_roots']);

        $definition = $this->getChildLoaderDefinition();

        $definition->replaceArgument(2, $locatorDefinition);

        return $this->setTaggedLoaderDefinition($name, $definition, $container);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'filesystem';
    }

    /**
     * {@inheritdoc}
     */
    public function addConfiguration(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
                ->enumNode('locator')
                    ->values(['filesystem', 'filesystem_insecure'])
                    ->info('Using the "filesystem_insecure" locator is not recommended due to a less secure resolver mechanism, but is provided for those using heavily symlinked projects.')
                    ->defaultValue('filesystem')
                ->end()
                ->arrayNode('data_root')
                    ->beforeNormalization()
                    ->ifString()
                        ->then(function ($value) {
                            return [$value];
                        })
                    ->end()
                    ->treatNullLike([])
                    ->treatFalseLike([])
                    ->defaultValue([SymfonyFramework::getContainerResolvableRootWebPath()])
                    ->prototype('scalar')
                        ->cannotBeEmpty()
                    ->end()
                ->end()
                ->booleanNode('allow_unresolvable_data_roots')
                    ->defaultFalse()
                ->end()
                ->arrayNode('bundle_resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                        ->end()
                        ->enumNode('access_control_type')
                            ->values(['blacklist', 'whitelist'])
                            ->info('Sets the access control method applied to bundle names in "access_control_list" into a blacklist or whitelist.')
                            ->defaultValue('blacklist')
                        ->end()
                        ->arrayNode('access_control_list')
                            ->defaultValue([])
                            ->prototype('scalar')
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param string[] $staticPaths
     *
     * @return string[]
     */
    private function resolveDataRoots(array $staticPaths, array $config, ContainerBuilder $container): array
    {
        if (false === $config['enabled']) {
            return $staticPaths;
        }

        $resourcePaths = [];

        foreach ($this->getBundleResourcePaths($container) as $name => $path) {
            if (('whitelist' === $config['access_control_type']) === \in_array($name, $config['access_control_list'], true) && is_dir($path)) {
                $resourcePaths[$name] = $path;
            }
        }

        return array_merge($staticPaths, $resourcePaths);
    }

    /**
     * @return string[]
     */
    private function getBundleResourcePaths(ContainerBuilder $container): array
    {
        if ($container->hasParameter('kernel.bundles_metadata')) {
            $paths = $this->getBundlePathsUsingMetadata($container->getParameter('kernel.bundles_metadata'));
        } else {
            $paths = $this->getBundlePathsUsingNamedObj($container->getParameter('kernel.bundles'));
        }

        return array_map(function (string $path): string {
            return $path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'public';
        }, $paths);
    }

    /**
     * @param array[] $metadata
     *
     * @return string[]
     */
    private function getBundlePathsUsingMetadata(array $metadata): array
    {
        return array_combine(array_keys($metadata), array_map(function (array $data) {
            return $data['path'];
        }, $metadata));
    }

    /**
     * @param string[] $classes
     *
     * @return string[]
     */
    private function getBundlePathsUsingNamedObj(array $classes): array
    {
        $paths = [];

        foreach ($classes as $c) {
            try {
                $r = new \ReflectionClass($c);
            } catch (\ReflectionException $exception) {
                throw new InvalidArgumentException(sprintf('Unable to resolve bundle "%s" while auto-registering bundle resource paths.', $c), $exception->getCode(), $exception);
            }

            $paths[$r->getShortName()] = \dirname($r->getFileName());
        }

        return $paths;
    }
}