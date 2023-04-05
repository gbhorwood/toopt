<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

class WriteConfigTest extends TestCase
{
    use ReflectionTrait;

    /**
     * The path to the config file in the 'home' directory
     */
    private String $configFileLocation = ".config/toopt/toopt.json";


    public function testWriteConfig()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * and config json
         */
        $structure = [
            '.config' => [
                'toopt' => [
                    'toopt.json' => null
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

        $argv = ['scriptname'];
        $tooptStub->parseargs($argv);
        $tooptStub->setConfigFile();

        $tooptReflection = new \ReflectionObject($tooptStub);
        $writeConfig = $tooptReflection->getMethod('writeConfig');
        $writeConfig->setAccessible(true);

        $writeConfig->invokeArgs($tooptStub, ['@gbhorwood@example.ca', 'example.ca', 'clientid', 'clientsecret', 'accesstoken']);

        $configContentsObject = json_decode(file_get_contents($configFilePath));
        $this->assertEquals($configContentsObject->default, '@gbhorwood@example.ca');

        $configContentsArray = (array)$configContentsObject->accounts;
        $this->assertEquals($configContentsArray['@gbhorwood@example.ca']->instance, 'example.ca');
        $this->assertEquals($configContentsArray['@gbhorwood@example.ca']->client_id, 'clientid');
        $this->assertEquals($configContentsArray['@gbhorwood@example.ca']->client_secret, 'clientsecret');
        $this->assertEquals($configContentsArray['@gbhorwood@example.ca']->access_token, 'accesstoken');
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