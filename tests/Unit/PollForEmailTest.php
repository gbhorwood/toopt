<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class PollForEmailTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * Test valid data entered on STDIN is accepted and returned.
     *
     * @dataProvider instanceProvider
     */
    public function testPollForEmailSuccess($validEmail)
    {
        include_once('toopt.php');

        /**
         * Override readline() to return $validEmail
         */
        $readline = $this->getFunctionMock('gbhorwood\toopt' , "readline");
        $readline->expects($this->once())->willReturn($validEmail);

        /**
         * Set private method to accessible
         */
        $pollForEmailMethod = $this->setAccessible('pollForEmail');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);

        $polledEmail = $pollForEmailMethod->invoke($toopt);

        $this->assertEquals($polledEmail, $validEmail);
    }

    /**
     * Test invalid data entered on STDIN is ignored until valid data
     * 
     */
    public function testPollForEmailValidation()
    {
        include_once('toopt.php');

        $validEmail = 'gbhorwood@example.ca';

        $emailCandidates = [
            'invalidemail',
            '@invalidemail',
            '@invalid@email',
            null,
            7,
            $validEmail,
        ];

        /**
         * Override readline() to return $emailCandidates as input
         */
        $readline = $this->getFunctionMock('gbhorwood\toopt' , "readline");
        $readline->expects($this->exactly(count($emailCandidates)))
                 ->willReturnOnConsecutiveCalls(...$emailCandidates);

        /**
         * Set private method to accessible
         */
        $pollForEmailMethod = $this->setAccessible('pollForEmail');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);

        /**
         * Trap and test output on STDOUT
         */
        $this->setOutputCallback(function($output) {
            $this->assertRegexp('/Must be a valid email/', $output);
        });

        $polledEmail = $pollForEmailMethod->invoke($toopt);

        $this->assertEquals($polledEmail, $validEmail);
    }


    /**
     *
     * @return Array
     */
    public function instanceProvider():Array
    {
        return [
            [ 'ghorwood@example.ca' ],
            [ 'toopt@example.ca' ],
        ];
    } // argvProvider
}