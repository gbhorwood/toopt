<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class DoMediaTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * Test doMedia wrapper to Api.postMedia()
     *
     */
    public function testDoMedia()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $id = 8;
        $apiStub = $this->getMockBuilder(\gbhorwood\toopt\Api::class)
                        ->setMethods(["postMedia"])
                        ->getMock();
        $apiStub->method('postMedia')
                ->will($this->onConsecutiveCalls((object)['id' => $id]));

        $mediaArgs = [
            [
                'path' => '/path/to/img1.jpg',
                'name' => 'img1.jpg',
                'mime' => 'img/jpeg',
                'description' => 'a description1',
            ],
        ];

        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$apiStub])
                        ->setMethods(["handleMediaArgs"])
                        ->getMock();
        $tooptStub->method('handleMediaArgs')
                ->will($this->onConsecutiveCalls($mediaArgs));

        /**
         * Set accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $doMedia = $tooptReflection->getMethod('doMedia');
        $doMedia->setAccessible(true);

        $this->setOutputCallback(function($output) { });

        $doMediaResponse = $doMedia->invokeArgs($tooptStub, ['someinstance', 'someaccesstoken']);

        $this->assertEquals([$id], $doMediaResponse);

    }

}