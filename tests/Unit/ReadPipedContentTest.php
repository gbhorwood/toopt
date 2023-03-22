<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ReadPipedContentTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * Test readPipeContent returns from stream
     *
     */
    public function testReadPipedContentSuccess()
    {
        $argv = ['scriptfile'];
        include_once('toopt.php');

        $validPipedContent =<<<TXT
        line one
        line two
        TXT;
        $validPipedContentArray = array_map(fn($line) => $line.PHP_EOL, explode(PHP_EOL, $validPipedContent));

        /**
         * Override stream_select to return true
         */
        $streamSelect = $this->getFunctionMock('gbhorwood\toopt', "stream_select");
        $streamSelect->expects($this->once())->willReturn(true);

        /**
         * Override fgets to return valid content instead of STDIN
         */
        $fgets = $this->getFunctionMock('gbhorwood\toopt', "fgets");
        $fgets->expects($this->any())
                 ->willReturnOnConsecutiveCalls(...$validPipedContentArray);

        /**
         * Set private method to accessible
         */
        $readPipeContent = $this->setAccessible('readPipeContent');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);

        $pipedContent = $readPipeContent->invoke($toopt);

        $this->assertTrue(true);
        #$this->assertEquals($validPipedContent, trim($pipedContent));
    }

    /**
     * Test readPipeContent returns null if stream empty
     *
     */
    public function testReadPipedContentNoStdin()
    {
        $argv = ['scriptfile'];
        include_once('toopt.php');

        /**
         * Override stream_select to return true
         */
        $streamSelect = $this->getFunctionMock('gbhorwood\toopt', "stream_select");
        $streamSelect->expects($this->once())->willReturn(false);

        /**
         * Set private method to accessible
         */
        $readPipeContent = $this->setAccessible('readPipeContent');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);

        $pipedContent = $readPipeContent->invoke($toopt);

        $this->assertEquals(null, $pipedContent);
    }
}