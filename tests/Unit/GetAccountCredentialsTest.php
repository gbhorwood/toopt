<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

class GetAccountCredentialsTest extends TestCase
{
    use ReflectionTrait;

    /**
     * The path to the config file in the 'home' directory
     */
    private String $configFileLocation = ".config/toopt/toopt.json";


    /**
     * Test getAccountCredentials returns default
     *
     */
    public function testGetAccountCredentialsDefault()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $expectedInstance = "mastodon.social";
        $expectedClientId = "someclientid";
        $expectedClientSecret = "someclientsecret";
        $expectedAccessToken = "someaccesstoken";

        $tooptFileContents =<<<JSON
        {
            "default": "@ghorwood@mastodon.social",
            "accounts": {
                "@ghorwood@mastodon.social": {
                    "instance": "$expectedInstance",
                    "client_id": "$expectedClientId",
                    "client_secret": "$expectedClientSecret",
                    "access_token": "$expectedAccessToken"
                },
                "@toopt@example.ca": {
                    "instance": "NOT$expectedInstance",
                    "client_id": "NOT$expectedClientId",
                    "client_secret": "NOT$expectedClientSecret",
                    "access_token": "NOT$expectedAccessToken"
                }
            }
        }
        JSON;

        /**
         * Build config directory structure
         * missing toopt.json
         */
        $structure = [
            '.config' => [
                'toopt' => [
                    'toopt.json' => $tooptFileContents
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


        /**
         * Set config file in toopt
         */
        $tooptStub->setConfigFile();

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $getAccountCredentials = $tooptReflection->getMethod('getAccountCredentials');
        $getAccountCredentials->setAccessible(true);

        $credentialsResult = $getAccountCredentials->invoke($tooptStub);

        $expected = [
            "instance" => $expectedInstance,
            "client_id" => $expectedClientId,
            "client_secret" => $expectedClientSecret,
            "access_token" => $expectedAccessToken
        ];

        $this->assertEquals($credentialsResult, $expected);
    }

    /**
     * Test getAccountCredentials returns account specified by arg
     *
     */
    public function testGetAccountCredentialsArgument()
    {
        $argv = ['scriptname', '--account=@toopt@example.ca'];
        include_once('toopt.php');

        $expectedInstance = "mastodon.social";
        $expectedClientId = "someclientid";
        $expectedClientSecret = "someclientsecret";
        $expectedAccessToken = "someaccesstoken";

        $tooptFileContents =<<<JSON
        {
            "default": "@ghorwood@mastodon.social",
            "accounts": {
                "@ghorwood@mastodon.social": {
                    "instance": "NOT$expectedInstance",
                    "client_id": "NOT$expectedClientId",
                    "client_secret": "NOT$expectedClientSecret",
                    "access_token": "NOT$expectedAccessToken"
                },
                "@toopt@example.ca": {
                    "instance": "$expectedInstance",
                    "client_id": "$expectedClientId",
                    "client_secret": "$expectedClientSecret",
                    "access_token": "$expectedAccessToken"
                }
            }
        }
        JSON;

        /**
         * Build config directory structure
         * missing toopt.json
         */
        $structure = [
            '.config' => [
                'toopt' => [
                    'toopt.json' => $tooptFileContents
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


        /**
         * Set config file in toopt
         */
        $tooptStub->parseArgs($argv);
        $tooptStub->setConfigFile();

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $getAccountCredentials = $tooptReflection->getMethod('getAccountCredentials');
        $getAccountCredentials->setAccessible(true);

        $credentialsResult = $getAccountCredentials->invoke($tooptStub);

        $expected = [
            "instance" => $expectedInstance,
            "client_id" => $expectedClientId,
            "client_secret" => $expectedClientSecret,
            "access_token" => $expectedAccessToken
        ];

        $this->assertEquals($credentialsResult, $expected);
    }

    /**
     * Test getAccountCredentials errors on null config
     *
     */
    public function testGetAccountCredentialsConfigIsNull()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * missing toopt.json
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


        /**
         * Set config file in toopt
         */
        $tooptStub->parseArgs($argv);
        $tooptStub->setConfigFile();

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $getAccountCredentials = $tooptReflection->getMethod('getAccountCredentials');
        $getAccountCredentials->setAccessible(true);


        try {
            $this->setOutputCallback(function($output) {
                $this->assertRegexp('/No accounts available/', $output);
            });

            $credentialsResult = $getAccountCredentials->invoke($tooptStub);
        }
        catch(\Exception $e) {
            $this->assertEquals((int)$e->getMessage(), 1);
        }
    }

    /**
     * Test getAccountCredentials errors on malformed config
     *
     */
    public function testGetAccountCredentialsConfigIsMalformed()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * missing toopt.json
         */
        $structure = [
            '.config' => [
                'toopt' => [
                    'toopt.json' => '{"not":"proper","key":"set"}'
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


        /**
         * Set config file in toopt
         */
        $tooptStub->parseArgs($argv);
        $tooptStub->setConfigFile();

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $getAccountCredentials = $tooptReflection->getMethod('getAccountCredentials');
        $getAccountCredentials->setAccessible(true);


        try {
            $this->setOutputCallback(function($output) {
                $this->assertRegexp('/is malformed/', $output);
            });

            $credentialsResult = $getAccountCredentials->invoke($tooptStub);
        }
        catch(\Exception $e) {
            $this->assertEquals((int)$e->getMessage(), 1);
        }
    }

    /**
     * Test getAccountCredentials errors on account not exists
     *
     */
    public function testGetAccountCredentialsAccountNotExists()
    {
        $argv = ['scriptname', '--account=@nottoopt@example.ca'];
        include_once('toopt.php');

        $expectedInstance = "mastodon.social";
        $expectedClientId = "someclientid";
        $expectedClientSecret = "someclientsecret";
        $expectedAccessToken = "someaccesstoken";

        $tooptFileContents =<<<JSON
        {
            "default": "@ghorwood@mastodon.social",
            "accounts": {
                "@ghorwood@mastodon.social": {
                    "instance": "$expectedInstance",
                    "client_id": "$expectedClientId",
                    "client_secret": "$expectedClientSecret",
                    "access_token": "$expectedAccessToken"
                },
                "@toopt@example.ca": {
                    "instance": "$expectedInstance",
                    "client_id": "$expectedClientId",
                    "client_secret": "$expectedClientSecret",
                    "access_token": "$expectedAccessToken"
                }
            }
        }
        JSON;

        /**
         * Build config directory structure
         * missing toopt.json
         */
        $structure = [
            '.config' => [
                'toopt' => [
                    'toopt.json' => $tooptFileContents
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


        /**
         * Set config file in toopt
         */
        $tooptStub->parseArgs($argv);
        $tooptStub->setConfigFile();

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $getAccountCredentials = $tooptReflection->getMethod('getAccountCredentials');
        $getAccountCredentials->setAccessible(true);


        try {
            $this->setOutputCallback(function($output) {
                $this->assertRegexp('/Try a different account/', $output);
            });

            $credentialsResult = $getAccountCredentials->invoke($tooptStub);
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