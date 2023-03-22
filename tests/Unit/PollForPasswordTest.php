<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class PollForPasswordTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * Test valid data entered on STDIN is accepted and returned.
     *
     * @dataProvider passwordProvider
     */
    public function testPollForPasswordSuccess($passwordAsEntered, $validPassword)
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Split passwordAsEntered into array of chars as passwords are entered
         * one char at a time. Append <RETURN> char to terminate entry.
         */
        $passwordChars = str_split($passwordAsEntered);
        $passwordChars[] = chr(10);

        /**
         * Override stream_get_contents() to return $validPassword one char
         * at a time
         */
        $streamGetContents = $this->getFunctionMock('gbhorwood\toopt' , "stream_get_contents");
        $streamGetContents->expects($this->any())
                 ->willReturnOnConsecutiveCalls(...$passwordChars);

        /**
         * Override fwrite() to suppress dots echo to STDOUT
         */
        $fwrite = $this->getFunctionMock('gbhorwood\toopt' , "fwrite");
        $fwrite->expects($this->any())
                 ->willReturnOnConsecutiveCalls(null);

        /**
         * Set private method to accessible
         */
        $pollForPasswordMethod = $this->setAccessible('pollForPassword');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);

        $polledPassword = $pollForPasswordMethod->invoke($toopt);

        $this->assertEquals($polledPassword, $validPassword);
    }

    /**
     * Password test data
     *
     * @return Array
     */
    public function passwordProvider():Array
    {
        return [
            [ "123456", "123456"],
            [ "1234".chr(127).chr(127)."56", "1256" ],
            [ "123".chr(127)."456".chr(127), "1245"],
        ];
    } // passwordProvider
}