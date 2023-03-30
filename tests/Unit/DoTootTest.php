<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class DoTootTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * Test doToot() 
     *
     * @dataProvider doTootProvider
     */
    public function testDoTootSuccess($instance, $content, $mediaIds, $cw, $replyToId, $accessToken)
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $postReturn = (object)[
            'id' => 7
        ];
        $apiStub = $this->getMockBuilder(\gbhorwood\toopt\Api::class)
                        ->setMethods(["post"])
                        ->getMock();
        $apiStub->method('post')
                ->will($this->onConsecutiveCalls($postReturn));

        $doToot = $this->setAccessible('doToot');

        $toopt = new \gbhorwood\toopt\Toopt($apiStub);

        $this->setOutputCallback(function($output) {
            $this->assertRegexp('/Toot 7/', $output);
        });
           
        $doTootReturn = $doToot->invokeArgs($toopt, [$instance, $content, $mediaIds, $cw, (int)$replyToId, $accessToken]);

        $this->assertEquals($postReturn, $doTootReturn);
    }


    /**
     *
     * @return Array
     */
    public function doTootProvider():Array
    {
        return [
            ['example.ca', 'test content', null, null, null, 'accesstoken'],
            ['example.ca', 'test content', null, 'content warning', null, 'accesstoken'],
            ['example.ca', 'test content', null, null, 3, 'accesstoken'],
            ['example.ca', 'test content', null, 'content warning', 3, 'accesstoken'],
        ];
    }
}