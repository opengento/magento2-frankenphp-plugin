<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\FrankenPhpPlugin\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use ErrorException;
use MagentoHackathon\Composer\Magento\Deploy\Manager\Entry;
use MagentoHackathon\Composer\Magento\DeployManager;
use MagentoHackathon\Composer\Magento\Installer;
use MagentoHackathon\Composer\Magento\ProjectConfig;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private Installer $installer;
    private DeployManager $deployManager;

    /**
     * @throws ErrorException
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->installer = new Installer($io, $composer);
        $this->deployManager = new DeployManager($io);
        $this->deployManager->setSortPriority(
            $composer->getPackage()->getExtra()['magento-deploy-sort-priority'] ?? []
        );
        $this->installer->setDeployManager($this->deployManager);
        $this->installer->setConfig(new ProjectConfig($composer->getPackage()->getExtra()));
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => ['deployFrankenPhpBase', 10],
            ScriptEvents::POST_UPDATE_CMD => ['deployFrankenPhpBase', 10],
        ];
    }

    /**
     * This plugin will redeploy the content of `opengento/magento2-frankenphp-base` wich is a magento2-component.
     * The content of this package type is only updated to the Magento base if the package itself have been updated.
     * So if the `magento/magento2-base` package is updated (likely at each Magento release) it will overrides the
     * `opengento/magento2-frankenphp-base` specific entry points.
     * We couldn't either use @see \MagentoHackathon\Composer\Magento\Command\DeployCommand because this script command
     * only re-deploy extra map for the `magento2-module` package type.
     *
     * @see ComposerPlugin::getSubscribedEvents
     * @throws ErrorException
     */
    public function deployFrankenPhpBase(): void
    {
        $package = $this->composer->getRepositoryManager()
            ->getLocalRepository()
            ->findPackage('opengento/magento2-frankenphp-base', '*');

        if ($package !== null && isset($package->getExtra()['map'])) {
            $strategy = $this->installer->getDeployStrategy($package);
            $strategy->setMappings($this->installer->getParser($package)->getMappings());
            $deployManagerEntry = new Entry();
            $deployManagerEntry->setPackageName($package->getName());
            $deployManagerEntry->setDeployStrategy($strategy);
            $this->deployManager->addPackage($deployManagerEntry);
        }

        $this->deployManager->doDeploy();
    }
}
