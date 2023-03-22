<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class CreateAppTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * Test createApp wrapper to Api.post()
     *
     */
    public function testCreateAppSuccess()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $mockedCreateAppApiReturnJson = '{"id":"1732666","name":"Toopt","website":"https://fruitbat.studio","redirect_uri":"urn:ietf:wg:oauth:2.0:oob","client_id":"EDsOc0G89lkV8t_7unv1D_W7QHyS-tG0rue2_t6OU9s","client_secret":"xloIt6mGfcc8betgdcO5UHKtF9PQV51-w_lo-EWYFBw","vapid_key":"BCk-QqERU0q-CfYZjcuB6lnyyOYfJ2AifKqfeGIm7Z-HiTU5T9eTG5GxVA0_OH5mMlI4UkkDTpaZwozy0TzdZ2M="}';
        $mockedCreateAppApiReturnObject = (object)json_decode($mockedCreateAppApiReturnJson);
        $apiStub = $this->getMockBuilder(\gbhorwood\toopt\Api::class)
                        ->setMethods(["post"])
                        ->getMock();
        $apiStub->method('post')
                ->will($this->onConsecutiveCalls($mockedCreateAppApiReturnObject));

        $createApp = $this->setAccessible('createApp');

        $toopt = new \gbhorwood\toopt\Toopt($apiStub);

        $createAppReturn = $createApp->invokeArgs($toopt, ['someinstance']);

        $this->assertEquals($mockedCreateAppApiReturnObject, $createAppReturn);
    }

}