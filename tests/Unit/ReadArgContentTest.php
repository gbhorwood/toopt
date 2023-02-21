<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

class ReadArgContentTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * Test readArgContent accepts positional arg content
     *
     */
    public function testReadArgContentPositional()
    {
        $validArgContent = "This is the test toot";
        $argv = ['scriptname', "$validArgContent"];

        include_once('toopt.php');

        /**
         * Set private method to accessible
         */
        $readArgContent = $this->setAccessible('readArgContent');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        $argContent = $readArgContent->invoke($toopt);

        $this->assertEquals($argContent, $validArgContent);
    }

    /**
     * Test readArgContent ignores missing positional arg content
     *
     */
    public function testReadArgContentPositionalNull()
    {
        $argv = ['scriptname'];

        include_once('toopt.php');

        /**
         * Set private method to accessible
         */
        $readArgContent = $this->setAccessible('readArgContent');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        $argContent = $readArgContent->invoke($toopt);

        $this->assertEquals($argContent, null);
    }

    /**
     * Test readArgContent accepts positional file argument
     *
     */
    public function testReadArgContentPositionalFile()
    {
        $validArgContent = "i am file content";
        $structure = [
            'directory' => [ 
                'file.txt' => $validArgContent,
                'other' => $validArgContent,
                'jpgfile.jpg' => $validArgContent,
            ]
        ];
        $contentFilePath = $this->buildFileSystem($structure);

        $argv = ['scriptname'];
        include_once('toopt.php');

        /**
         * Set private method to accessible
         */
        $readArgContent = $this->setAccessible('readArgContent');


        /**
         * Test file with txt extension
         */
        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $argv = ['scriptname', $contentFilePath.DIRECTORY_SEPARATOR."directory".DIRECTORY_SEPARATOR."file.txt"];
        $toopt->parseargs($argv);
        $argContent = $readArgContent->invoke($toopt);
        $this->assertEquals($argContent, $validArgContent);

        /**
         * Test file with no extension
         */
        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $argv = ['scriptname', $contentFilePath.DIRECTORY_SEPARATOR."directory".DIRECTORY_SEPARATOR."other"];
        $toopt->parseargs($argv);
        $argContent = $readArgContent->invoke($toopt);
        $this->assertEquals($argContent, $validArgContent);

        /**
         * Test file with no extension
         */
        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $argv = ['scriptname', $contentFilePath.DIRECTORY_SEPARATOR."directory".DIRECTORY_SEPARATOR."jpgfile.jpg"];
        $toopt->parseargs($argv);
        $argContent = $readArgContent->invoke($toopt);
        $this->assertEquals($argContent, null);
    }

    /**
     * Scaffold fake filesystem
     *
     * @param  Array  The array that defines the file strcture in vfsstream
     */
    private function buildFileSystem(Array $structure)
    {
        $fileSystem =  vfsStream::setup('home');
        vfsStream::create($structure, $fileSystem);
        return $fileSystem->url();
    }
}