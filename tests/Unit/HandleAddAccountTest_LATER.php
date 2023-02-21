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

        /**
         * Mock pollForInstance
         */
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(["pollForInstance", "pollForEmail", "pollForPassword"])
                        ->getMock();
        $tooptStub->method('pollForInstance')
                ->will($this->onConsecutiveCalls($instance));
        $tooptStub->method('pollForEmail')
                ->will($this->onConsecutiveCalls($email));
        $tooptStub->method('pollForPassword')
                ->will($this->onConsecutiveCalls($password));

        $tooptStub->parseArgs($argv);
        $tooptStub->handleAddAccount();


        $this->assertTrue(true);
    }

}