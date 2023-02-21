<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

class SetConfigFileTest extends TestCase
{
    use ReflectionTrait;

    /**
     * The path to the config file in the 'home' directory
     */
    private String $configFileLocation = ".config/toopt/toopt.json";

    /**
     * Test valid config account
     *
     */
    public function testSetConfigFile()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * empty toopt.json
         */
        $structure = [
            '.config' => [
                'toopt' => [
                    'toopt.json' => ''
                ]
            ]
        ];
        $configFilePath = $this->buildFileSystem($structure);

        /**
         * Mock getConfigFilePath()
         */
        $api = new \gbhorwood\toopt\Api();
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(["getConfigFilePath"])
                        ->getMock();
        $tooptStub->method('getConfigFilePath')
                ->will($this->onConsecutiveCalls($configFilePath));

        $tooptStub->setConfigFile();
        $this->assertEquals('', file_get_contents($configFilePath));
        $this->assertTrue(file_exists($configFilePath));
    }

    /**
     * Test missing config file
     *
     */
    public function testSetConfigFileFileIsMissing()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * missing toopt.json
         */
        $structure = [
            '.config' => [
                'toopt' => [ ]
            ]
        ];
        $configFilePath = $this->buildFileSystem($structure);

        /**
         * Mock getConfigFilePath()
         */
        $api = new \gbhorwood\toopt\Api();
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(["getConfigFilePath"])
                        ->getMock();
        $tooptStub->method('getConfigFilePath')
                ->will($this->onConsecutiveCalls($configFilePath));

        $tooptStub->setConfigFile();
        $this->assertEquals('', file_get_contents($configFilePath));
        $this->assertTrue(file_exists($configFilePath));
    }

    /**
     * Test missing toopt directory
     *
     */
    public function testSetConfigFileTooptDirIsMissing()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * missing toopt dir
         */
        $structure = [
            '.config' => [ ]
        ];
        $configFilePath = $this->buildFileSystem($structure);

        /**
         * Mock getConfigFilePath()
         */
        $api = new \gbhorwood\toopt\Api();
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(["getConfigFilePath"])
                        ->getMock();
        $tooptStub->method('getConfigFilePath')
                ->will($this->onConsecutiveCalls($configFilePath));

        $tooptStub->setConfigFile();
        $this->assertEquals('', file_get_contents($configFilePath));
        $this->assertTrue(file_exists($configFilePath));
    }

    /**
     * Test missing .config directory
     *
     */
    public function testSetConfigFileConfigDirIsMissing()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * missing .config dir
         */
        $structure = [ ];
        $configFilePath = $this->buildFileSystem($structure);

        /**
         * Mock getConfigFilePath()
         */
        $api = new \gbhorwood\toopt\Api();
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(["getConfigFilePath"])
                        ->getMock();
        $tooptStub->method('getConfigFilePath')
                ->will($this->onConsecutiveCalls($configFilePath));

        $tooptStubClass =  get_class($tooptStub);

        $tooptStub->method('getConfigFilePath')
                ->will($this->onConsecutiveCalls($configFilePath));

        $tooptStub->setConfigFile();
        $this->assertEquals('', file_get_contents($configFilePath));
        $this->assertTrue(file_exists($configFilePath));
    }

    /**
     * Test toopt is file not dir
     *
     */
    public function testSetConfigFileErrorTooptIsFile()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $structure = [
            '.config' => [ 
                'toopt' => "i am a file",
            ]
        ];
        $configFilePath = $this->buildFileSystem($structure);

        /**
         * Mock getConfigFilePath()
         */
        $api = new \gbhorwood\toopt\Api();
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(["getConfigFilePath"])
                        ->getMock();
        $tooptStub->method('getConfigFilePath')
                ->will($this->onConsecutiveCalls($configFilePath));

        try {
            $this->setOutputCallback(function($output) {
                $this->assertEquals(substr(trim($output), strlen("but is not a directory") * -1), "but is not a directory");
            });
            $tooptStub->setConfigFile();
        }
        catch(\Exception $e) {
            $this->assertEquals((int)$e->getMessage(), 1);
        }
    }

    /**
     * Scaffold fake filesystem
     *
     * @param  Array  The array that defines the file strcture in vfsstream
     * @return String The path to the config file we expect
     */
    private function buildFileSystem(Array $structure):String
    {
        $fileSystem =  vfsStream::setup('home');
        vfsStream::create($structure, $fileSystem);
        return $fileSystem->url().DIRECTORY_SEPARATOR.$this->configFileLocation;
    }
}