<?php

namespace Tooly\Tests\Script\Processor;

use Composer\IO\ConsoleIO;
use org\bovigo\vfs\vfsStream;
use Tooly\Factory\ToolFactory;
use Tooly\Model\Tool;
use Tooly\Script\Configuration;
use Tooly\Script\Helper;
use Tooly\Script\Helper\Downloader;
use Tooly\Script\Helper\Filesystem;
use Tooly\Script\Processor;

/**
 * @package Tooly\Tests\Scrip\Processor
 */
class ProcessTest extends \PHPUnit_Framework_TestCase
{
    private $io;

    private $helper;

    private $configuration;

    public function setUp()
    {
        $this->io = $this
            ->getMockBuilder(ConsoleIO::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->helper = $this
            ->getMockBuilder(Helper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configuration = $this
            ->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testCantProceedOnlyDevToolInNonDevMode()
    {
        $tool = ToolFactory::createTool('tool', __DIR__, []);

        $this->configuration
            ->method('isDevMode')
            ->willReturn(false);

        $this->io
            ->expects($this->exactly(2))
            ->method('write');

        $processor = new Processor($this->io, $this->helper, $this->configuration);
        $processor->process($tool);
    }

    public function testCantProceedToolWithUnAccessibleUrl()
    {
        $tool = ToolFactory::createTool('tool', __DIR__, ['url' => false]);

        $this->helper
            ->method('getDownloader')
            ->willReturn(new Downloader);

        $this->io
            ->expects($this->exactly(2))
            ->method('write');

        $processor = new Processor($this->io, $this->helper, $this->configuration);
        $processor->process($tool);
    }

    public function testCanSuccessfullyDownloadATool()
    {
        vfsStream::setup('bin');

        $downloader = $this
            ->getMockBuilder(Downloader::class)
            ->getMock();

        $downloader
            ->method('isAccessible')
            ->willReturn(true);

        $filesystem = $this
            ->getMockBuilder(Filesystem::class)
            ->getMock();

        $filesystem
            ->method('isFileAlreadyExist')
            ->willReturn(false);

        $this->helper
            ->method('getFilesystem')
            ->willReturn($filesystem);

        $this->helper
            ->method('getDownloader')
            ->willReturn($downloader);

        $this->helper
            ->method('isFileAlreadyExist')
            ->willReturn(false);

        $this->io
            ->expects($this->exactly(2))
            ->method('write');

        $tool = $this
            ->getMockBuilder(Tool::class)
            ->disableOriginalConstructor()
            ->getMock();

        $tool
            ->method('getFilename')
            ->willReturn(vfsStream::url('bin/tool.phar'));

        $processor = new Processor($this->io, $this->helper, $this->configuration);
        $processor->process($tool);
    }

    public function testCantCleanUpNonExistingDirectory()
    {
        ToolFactory::createTool('tool', __DIR__, ['url' => false]);

        $filesystem = $this
            ->getMockBuilder(Filesystem::class)
            ->getMock();

        $this->configuration
            ->method('getBinDirectory')
            ->willReturn(sprintf('%s/missed', __DIR__));

        $this->configuration
            ->method('getTools')
            ->willReturn([]);

        $this->helper
            ->method('getFileSystem')
            ->willReturn($filesystem);

        $processor = new Processor($this->io, $this->helper, $this->configuration);

        $processor->cleanUp();
    }
}
