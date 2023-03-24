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
     * 
     *
     */
    public function testHandleListAccounts()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

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

        /**
         * Build config directory structure
         * empty toopt.json
         */
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
