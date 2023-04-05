<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class GetContentTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;


    /**
     * Test getContent returns content piped in
     *
     */
    public function testGetContentPipeContent()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $validPipeContent = "i am piped content";

        $api = new \gbhorwood\toopt\Api();

        /**
         * Mock readPipeContent
         */
        $readPipeContent = $this->setAccessible('readPipeContent');
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(['readPipeContent'])
                        ->getMock();
        $tooptStub->method('readPipeContent')
                ->will($this->onConsecutiveCalls([$validPipeContent]));

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $getContent = $tooptReflection->getMethod('getContent');
        $getContent->setAccessible(true);

        $pipeContent = $getContent->invoke($tooptStub);

        $this->assertEquals($pipeContent[0], $validPipeContent);
    }

    /**
     * Test getContent returns content from positional arg
     *
     */
    public function testGetContentArgContent()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $validArgContent = "i am arg content";

        $api = new \gbhorwood\toopt\Api();

        /**
         * Mock read methods
         */
        $readPipeContent = $this->setAccessible('readPipeContent');
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(['readPipeContent', 'readArgContent', 'readInteractiveContent'])
                        ->getMock();
        $tooptStub->method('readPipeContent')
                ->will($this->onConsecutiveCalls(null));
        $tooptStub->method('readInteractiveContent')
                ->will($this->onConsecutiveCalls(null));
        $tooptStub->method('readArgContent')
                ->will($this->onConsecutiveCalls([$validArgContent]));

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $getContent = $tooptReflection->getMethod('getContent');
        $getContent->setAccessible(true);

        $argContent = $getContent->invoke($tooptStub);

        $this->assertEquals($argContent[0], $validArgContent);
    }

    /**
     * Test getContent returns content from interactive input
     *
     */
    public function testGetContentInteractiveContent()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $validInteractiveContent = "i am interactive content";

        $api = new \gbhorwood\toopt\Api();

        /**
         * Mock read methods
         */
        $readPipeContent = $this->setAccessible('readPipeContent');
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(['readPipeContent', 'readArgContent', 'readInteractiveContent'])
                        ->getMock();
        $tooptStub->method('readPipeContent')
                ->will($this->onConsecutiveCalls(null));
        $tooptStub->method('readInteractiveContent')
                ->will($this->onConsecutiveCalls([$validInteractiveContent]));
        $tooptStub->method('readArgContent')
                ->will($this->onConsecutiveCalls(null));

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $getContent = $tooptReflection->getMethod('getContent');
        $getContent->setAccessible(true);

        $interactiveContent = $getContent->invoke($tooptStub);

        $this->assertEquals($interactiveContent[0], $validInteractiveContent);
    }

    /**
     * Test getContent errors with exit(1) on no content
     *
     */
    public function testGetContentNoContent()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();

        /**
         * Mock read methods
         */
        $readPipeContent = $this->setAccessible('readPipeContent');
        $tooptStub = $this->getMockBuilder(\gbhorwood\toopt\Toopt::class)
                        ->setConstructorArgs([$api])
                        ->setMethods(['readPipeContent', 'readArgContent', 'readInteractiveContent'])
                        ->getMock();
        $tooptStub->method('readPipeContent')
                ->will($this->onConsecutiveCalls(null));
        $tooptStub->method('readInteractiveContent')
                ->will($this->onConsecutiveCalls(null));
        $tooptStub->method('readArgContent')
                ->will($this->onConsecutiveCalls(null));

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($tooptStub);
        $getContent = $tooptReflection->getMethod('getContent');
        $getContent->setAccessible(true);

        try {
            $this->setOutputCallback(function($output) {
                $this->assertRegexp('/No content/', $output);
            });

            $noContent = $getContent->invoke($tooptStub);
            $this->assertEquals($content, null);
        }
        catch(\Exception $e) {
            $this->assertEquals((int)$e->getMessage(), 1);
        }

    }
}