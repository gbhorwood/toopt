<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class PollForInstanceTest extends TestCase
{

    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * Test valid data entered on STDIN is accepted and returned.
     *
     * @dataProvider instanceProvider
     */
    public function testPollForInstanceSuccess($validInstance)
    {
        include_once('toopt.php');

        /**
         * Override readline() to return $validInstance
         */
        $readline = $this->getFunctionMock('gbhorwood\toopt' , "readline");
        $readline->expects($this->once())->willReturn($validInstance);

        /**
         * Set protected method to accessible
         */
        $pollForInstanceMethod = $this->setAccessible('pollForInstance');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);

        $polledInstance = $pollForInstanceMethod->invoke($toopt);

        $this->assertEquals($polledInstance, $validInstance);
    }

    /**
     * Test invalid data entered on STDIN is ignored until valid data
     * 
     */
    public function testPollForInstanceValidation()
    {
        include_once('toopt.php');

        $validInstance = 'mastodon.social';

        $instanceCandidates = [
            'invalidemail',
            '@invalidemail',
            '@invalid@email',
            null,
            7,
            $validInstance,
        ];

        /**
         * Override readline() to return $emailCandidates as input
         */
        $readline = $this->getFunctionMock('gbhorwood\toopt' , "readline");
        $readline->expects($this->exactly(count($instanceCandidates)))
                 ->willReturnOnConsecutiveCalls(...$instanceCandidates);

        /**
         * Set protected method to accessible
         */
        $tooptClass = new \ReflectionClass('gbhorwood\toopt\Toopt');
        $pollForInstanceMethod = $tooptClass->getMethod('pollForInstance');
        $pollForInstanceMethod->setAccessible(true);

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);


        $this->setOutputCallback(function($output) {
            $this->assertRegexp('/Must be in format/', $output);
        });

        $polledInstance = $pollForInstanceMethod->invoke($toopt);

        $this->assertEquals($polledInstance, $validInstance);
    }


    /**
     *
     * @return Array
     */
    public function instanceProvider():Array
    {
        return [
            [ 'mastodon.social' ],
            [ 'phpc.social' ],
        ];
    } // argvProvider
}