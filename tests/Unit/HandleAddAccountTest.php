<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class HandleAddAccountTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * 
     *
     */
    public function testHandleAddAccountSuccess()
    {
        $argv = ['scriptname', "--add-account"];
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();

        $instance = "foo.bar";
        $email = "ghorwood@example.ca";
        $password = "secret";
        $app = (object)['client_id' => 'id', 'client_secret' => 'secret'];
        $session = (object)["access_token" => "session"];
        $verifiedCredentials = (object)["acct" => "someacct"];

        /**
         * Mocks
         */
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(["pollForInstance", "pollForEmail", "pollForPassword", "createApp", "doOauth", "doVerifyCredentials"])
                        ->getMock();
        $tooptStub->method('pollForInstance')
                ->will($this->onConsecutiveCalls($instance));
        $tooptStub->method('pollForEmail')
                ->will($this->onConsecutiveCalls($email));
        $tooptStub->method('pollForPassword')
                ->will($this->onConsecutiveCalls($password));
        $tooptStub->method('createApp')
                ->will($this->onConsecutiveCalls($app));
        $tooptStub->method('doOauth')
                ->will($this->onConsecutiveCalls($session));
        $tooptStub->method('doVerifyCredentials')
                ->will($this->onConsecutiveCalls($verifiedCredentials));

        try {

            $tooptStub->parseArgs($argv);
            $tooptStub->setConfigFile();
            $this->setOutputCallback(function($output) {
                //$this->assertRegexp('/'.$verifiedCredentials->acct.'/', $output);
                //$this->assertRegexp('/@'.$verifiedCredentials->acct.'@'.$instance.'/', $output);
            });
            $tooptStub->handleAddAccount();
        }
        catch(\Exception $e) {
            $this->assertEquals((int)$e->getMessage(), 0);
        }

        $this->assertTrue(true);
    }

}