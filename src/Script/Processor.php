<?php

namespace Tooly\Script;

use Composer\IO\IOInterface;
use Tooly\Script\Decision\DoReplaceDecision;
use Tooly\Script\Decision\FileAlreadyExistDecision;
use Tooly\Script\Decision\IsAccessibleDecision;
use Tooly\Script\Decision\IsVerifiedDecision;
use Tooly\Script\Decision\OnlyDevDecision;
use Tooly\Script\Helper;
use Tooly\Model\Tool;

/**
 * @package Tooly\Script
 */
class Processor
{
    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @param IOInterface   $io
     * @param Helper        $helper
     * @param Configuration $configuration
     */
    public function __construct(IOInterface $io, Helper $helper, Configuration $configuration)
    {
        $this->io = $io;
        $this->helper = $helper;
        $this->configuration = $configuration;
    }

    /**
     * Removes symlinks from composer's bin-dir and old phar's
     * from own bin-dir.
     */
    public function cleanUp()
    {
        $composerBinDirectory = $this->configuration->getComposerBinDirectory();

        if (false === is_dir($composerBinDirectory)) {
            $this->helper->getFilesystem()->createDirectory($composerBinDirectory);
        }

        $this->removeFromDir($composerBinDirectory);
        $this->removeFromDir(
            $this->configuration->getBinDirectory(),
            array_keys($this->configuration->getTools())
        );
    }

    /**
     * @param Tool $tool
     */
    public function process(Tool $tool)
    {
        $this->io->write(sprintf('<comment>Process tool "%s" ...</comment>', $tool->getName()));

        /* @var $decision \Tooly\Script\Decision\DecisionInterface */
        foreach ($this->getDecisions() as $decision) {
            if (true === $decision->canProceed($tool)) {
                continue;
            }

            $this->io->write($decision->getReason());
            return;
        }

        $data = $this->helper->getDownloader()->download($tool->getUrl());
        $filename = $tool->getFilename();

        $this->helper->getFilesystem()->createFile($filename, $data);

        $this->io->write(sprintf(
            '<info>File "%s" successfully downloaded!</info>',
            basename($filename)
        ));
    }

    /**
     * @param Tool $tool
     */
    public function symlink(Tool $tool)
    {
        if (true === $tool->isOnlyDev() && false === $this->configuration->isDevMode()) {
            return;
        }

        $filename = $tool->getFilename();
        $composerDir = $this->configuration->getComposerBinDirectory();
        $composerPath = $composerDir . DIRECTORY_SEPARATOR . basename($filename);

        $this->helper->getFilesystem()->symlinkFile($filename, $composerPath);
    }

    /**
     * Each decision can interrupt the download of a tool.
     *
     * @return array
     */
    private function getDecisions()
    {
        return [
            new OnlyDevDecision($this->configuration, $this->helper),
            new IsAccessibleDecision($this->configuration, $this->helper),
            new FileAlreadyExistDecision($this->configuration, $this->helper),
            new IsVerifiedDecision($this->configuration, $this->helper),
            new DoReplaceDecision($this->configuration, $this->helper, $this->io),
        ];
    }

    /**
     * @param string $dir
     * @param array  $excludeToolNames
     */
    private function removeFromDir($dir, array $excludeToolNames = [])
    {
        if (false === is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            if (false === strpos($path, '.phar')) {
                continue;
            }

            if (true === in_array(basename($entry, '.phar'), $excludeToolNames)) {
                continue;
            }

            $this->helper->getFilesystem()->remove($path);
        }
    }
}
