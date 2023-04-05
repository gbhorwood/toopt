<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

class HandleDeleteAccountTest extends TestCase
{
    use ReflectionTrait;

    /**
     * The path to the config file in the 'home' directory
     */
    private String $configFileLocation = ".config/toopt/toopt.json";


    /**
     * Delete account success
     *
     */
    public function testHandleDeleteAccount()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * and config json
         */
        $configJson = $this->getConfigJson();
        $structure = [
            '.config' => [
                'toopt' => [
                    'toopt.json' => $configJson
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

        $argv = ['scriptname', '--delete-account=@one@phpc.social'];
        $tooptStub->parseargs($argv);
        $tooptStub->setConfigFile();

        try {
            $this->setOutputCallback(function($output) {
                $this->assertRegexp('/Available accounts/', $output);
                $this->assertRegexp('/@ghorwood@mastodon.social/', $output);
                $this->assertRegexp('/@toopt@phpc.social/', $output);

            });

            $tooptStub->handleDeleteAccount();
        }
        catch(\Exception $e) {
            $this->assertEquals((int)$e->getMessage(), 0);
        }
    }

    /**
     * Delete account default fail
     *
     */
    public function testHandleDeleteAccountFailOnDefault()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * and config json
         */
        $configJson = $this->getConfigJson();
        $structure = [
            '.config' => [
                'toopt' => [
                    'toopt.json' => $configJson
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

        $argv = ['scriptname', '--delete-account=@ghorwood@mastodon.social'];
        $tooptStub->parseargs($argv);
        $tooptStub->setConfigFile();

        try {
            $this->setOutputCallback(function($output) {
                $this->assertRegexp('/Cannot delete default account/', $output);
                $this->assertRegexp('/Available accounts/', $output);
                $this->assertRegexp('/@ghorwood@mastodon.social/', $output);
                $this->assertRegexp('/@toopt@phpc.social/', $output);

            });

            $tooptStub->handleDeleteAccount();
        }
        catch(\Exception $e) {
            $this->assertEquals((int)$e->getMessage(), 1);
        }
    }

    /**
     * Delete account not exists fail
     *
     */
    public function testHandleDeleteAccountFailOnDoesNotExist()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Build config directory structure
         * and config json
         */
        $configJson = $this->getConfigJson();
        $structure = [
            '.config' => [
                'toopt' => [
                    'toopt.json' => $configJson
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

        $argv = ['scriptname', '--delete-account=@notexists@mastodon.social'];
        $tooptStub->parseargs($argv);
        $tooptStub->setConfigFile();

        try {
            $this->setOutputCallback(function($output) {
                $this->assertRegexp('/does not exist/', $output);
                $this->assertRegexp('/Available accounts/', $output);
                $this->assertRegexp('/@ghorwood@mastodon.social/', $output);
                $this->assertRegexp('/@toopt@phpc.social/', $output);

            });

            $tooptStub->handleDeleteAccount();
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
        return $fileSystem->url().'/'.$this->configFileLocation;
    }

    /**
     * Return the test config.json contents
     */
    private function getConfigJson():String
    {
        $configJson =<<<JSON
        {
            "default": "@ghorwood@mastodon.social",
            "accounts": {
                "@ghorwood@mastodon.social": {
                    "instance": "mastodon.social",
                    "client_id": "ghorwoodclientid",
                    "client_secret": "ghorwoodclientsecret",
                    "access_token": "ghorwoodaccesstoken"
                },
                "@toopt@phpc.social": {
                    "instance": "phpc.social",
                    "client_id": "tooptclientid",
                    "client_secret": "tooptclientsecret",
                    "access_token": "tooptaccesstoken"
                },
                "@one@phpc.social": {
                    "instance": "phpc.social",
                    "client_id": "tooptclientid",
                    "client_secret": "tooptclientsecret",
                    "access_token": "tooptaccesstoken"
                },
                "@two@phpc.social": {
                    "instance": "phpc.social",
                    "client_id": "tooptclientid",
                    "client_secret": "tooptclientsecret",
                    "access_token": "tooptaccesstoken"
                }
            }
        }
        JSON;
        return $configJson;
    }
}
