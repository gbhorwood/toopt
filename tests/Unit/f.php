<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

class f extends TestCase
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
        $api = new \Api();
        $this->makeAccessible(\Toopt::class, 'getConfigFilePath');
        $tooptStub = $this->getMockBuilder(\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(["getConfigFilePath"])
                        ->getMock();

        print "---->".get_class($tooptStub).PHP_EOL;
        $this->makeAccessible('\\'.get_class($tooptStub), 'getConfigFilePath');


        $tooptStub->method('getConfigFilePath')
                ->will($this->onConsecutiveCalls($configFilePath));


        $tooptStub->setConfigFile();
        $this->assertEquals('', file_get_contents($configFilePath));
        $this->assertTrue(file_exists($configFilePath));
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
        return $fileSystem->url().'/'.$this->configFileLocation;
    }
}