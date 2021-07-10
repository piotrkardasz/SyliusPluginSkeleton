<?php

declare(strict_types=1);

namespace Tests\PiotrKardasz\SyliusPluginSkeleton\Application;

use PSS\SymfonyMockerContainer\DependencyInjection\MockerContainer;
use Sylius\Bundle\CoreBundle\Application\Kernel as SyliusKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Tests\Acme\SyliusExamplePlugin\Application\Kernel as BaseSyliusSkeletonKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    public function registerBundles(): iterable
    {
        foreach ($this->getConfigurationDirectories() as $confDir) {
            $bundlesFile = $confDir . '/bundles.php';
            if (false === is_file($bundlesFile)) {
                continue;
            }
            yield from $this->registerBundlesFromFile($bundlesFile);
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        foreach ($this->getConfigurationDirectories() as $confDir) {
            $bundlesFile = $confDir . '/bundles.php';
            if (false === is_file($bundlesFile)) {
                continue;
            }
            $container->addResource(new FileResource($bundlesFile));
        }

        $container->setParameter('container.dumper.inline_class_loader', true);

        foreach ($this->getConfigurationDirectories() as $confDir) {
            $this->loadContainerConfiguration($loader, $confDir);
        }
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        foreach ($this->getConfigurationDirectories() as $confDir) {
            $this->loadRoutesConfiguration($routes, $confDir);
        }
    }

    protected function getContainerBaseClass(): string
    {
        if ($this->isTestEnvironment()) {
            return MockerContainer::class;
        }

        return parent::getContainerBaseClass();
    }

    private function isTestEnvironment(): bool
    {
        return 0 === strpos($this->getEnvironment(), 'test');
    }

    private function loadContainerConfiguration(LoaderInterface $loader, string $confDir): void
    {
        $loader->load($confDir . '/{packages}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{packages}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}_' . $this->environment . self::CONFIG_EXTS, 'glob');
    }

    private function loadRoutesConfiguration(RouteCollectionBuilder $routes, string $confDir): void
    {
        $routes->import($confDir . '/{routes}/*' . self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/{routes}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, '/', 'glob');

        if (strpos($confDir, 'vendor')){
            return;
        }
        $routes->import($confDir . '/{routes}' . self::CONFIG_EXTS, '/', 'glob');
    }

    /**
     * @return BundleInterface[]
     */
    private function registerBundlesFromFile(string $bundlesFile): iterable
    {
        $excludedBundlesFile = require $this->getProjectDir() . '/config/bundles_excluded.php';
        $contents = require $bundlesFile;
        foreach ($contents as $class => $envs) {
            if (in_array($class, $excludedBundlesFile, true)) {
                continue;
            }

            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    public static function getBasePluginSkeletonPath(): string
    {
        $syliusKernel = (new \ReflectionClass(BaseSyliusSkeletonKernel::class))->getFileName();
        if (!$syliusKernel) {
            throw new \InvalidArgumentException(
                sprintf('Sylius skeleton kernel: "%s" not found!', BaseSyliusSkeletonKernel::class)
            );
        }
        return dirname($syliusKernel);
    }

    /**
     * @return string[]
     */
    private function getConfigurationDirectories(): iterable
    {
        yield self::getBasePluginSkeletonPath() . '/config';
        $syliusConfigDir = self::getBasePluginSkeletonPath(
            ) . '/config/sylius/' . SyliusKernel::MAJOR_VERSION . '.' . SyliusKernel::MINOR_VERSION;
        if (is_dir($syliusConfigDir)) {
            yield $syliusConfigDir;
        }
        $symfonyConfigDir = self::getBasePluginSkeletonPath(
            ) . '/config/symfony/' . BaseKernel::MAJOR_VERSION . '.' . BaseKernel::MINOR_VERSION;
        if (is_dir($symfonyConfigDir)) {
            yield $symfonyConfigDir;
        }
        yield $this->getProjectDir() . '/config';
    }
}

