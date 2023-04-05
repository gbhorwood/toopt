<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

class HandleMediaArgsTest extends TestCase
{
    use ReflectionTrait;


    /**
     * Test handeMediaArgs with one file
     *
     */
    public function testHandleMediaArgsOneFile()
    {
        /**
         * Build directory structure
         */
        $mediaFilePaths = $this->buildMediaFileSystem();

        $argv = ['scriptname', $mediaFilePaths[0]];
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        /**
         * Set accessible
         */
        $tooptReflection = new \ReflectionObject($toopt);
        $handleMediaArgs = $tooptReflection->getMethod('handleMediaArgs');
        $handleMediaArgs->setAccessible(true);

        $mediaArgs = $handleMediaArgs->invoke($toopt);

        $this->assertEquals(count($mediaArgs), 1);
        $this->assertEquals($mediaArgs[0]['name'], basename($mediaFilePaths[0]));
    }

    /**
     * Test handeMediaArgs with one file and one description
     *
     */
    public function testHandleMediaArgsOneFileWithDescription()
    {
        /**
         * Build directory structure
         */
        $mediaFilePaths = $this->buildMediaFileSystem();

        $desc = "some description";
        $argv = ['scriptname', $mediaFilePaths[0], "--description=$desc"];
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        /**
         * Set accessible
         */
        $tooptReflection = new \ReflectionObject($toopt);
        $handleMediaArgs = $tooptReflection->getMethod('handleMediaArgs');
        $handleMediaArgs->setAccessible(true);

        $mediaArgs = $handleMediaArgs->invoke($toopt);

        $this->assertEquals(count($mediaArgs), 1);
        $this->assertEquals($mediaArgs[0]['name'], basename($mediaFilePaths[0]));
        $this->assertEquals($mediaArgs[0]['description'], $desc);
    }

    /**
     * Test handeMediaArgs with three files and two descriptions
     *
     */
    public function testHandleMediaArgsThreeFilesWithTwoDescription()
    {
        /**
         * Build directory structure
         */
        $mediaFilePaths = $this->buildMediaFileSystem();

        $desc1 = "desc1";
        $desc2 = "desc2";
        $argv = [
            'scriptname',
            $mediaFilePaths[0],
            $mediaFilePaths[1],
            $mediaFilePaths[2],
            "--description=$desc1",
            "--description=$desc2",
        ];
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        /**
         * Set accessible
         */
        $tooptReflection = new \ReflectionObject($toopt);
        $handleMediaArgs = $tooptReflection->getMethod('handleMediaArgs');
        $handleMediaArgs->setAccessible(true);

        $mediaArgs = $handleMediaArgs->invoke($toopt);

        $this->assertEquals(count($mediaArgs), 3);
        $this->assertEquals($mediaArgs[0]['name'], basename($mediaFilePaths[0]));
        $this->assertEquals($mediaArgs[1]['name'], basename($mediaFilePaths[1]));
        $this->assertEquals($mediaArgs[2]['name'], basename($mediaFilePaths[2]));
        $this->assertEquals($mediaArgs[0]['description'], $desc1);
        $this->assertEquals($mediaArgs[1]['description'], $desc2);
    }

    /**
     * Test handeMediaArgs with five files
     *
     */
    public function testHandleMediaArgsFiveFiles()
    {
        /**
         * Build directory structure
         */
        $mediaFilePaths = $this->buildMediaFileSystem();

        $desc1 = "desc1";
        $desc2 = "desc2";
        $argv = [
            'scriptname',
            $mediaFilePaths[0],
            $mediaFilePaths[1],
            $mediaFilePaths[2],
            $mediaFilePaths[3],
            $mediaFilePaths[4],
        ];
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        /**
         * Set accessible
         */
        $tooptReflection = new \ReflectionObject($toopt);
        $handleMediaArgs = $tooptReflection->getMethod('handleMediaArgs');
        $handleMediaArgs->setAccessible(true);

        $this->setOutputCallback(function($output) {
            $this->assertRegexp('/Only the first four media/', $output);
        });

        $mediaArgs = $handleMediaArgs->invoke($toopt);

        $this->assertEquals(count($mediaArgs), 4);
        $this->assertEquals($mediaArgs[0]['name'], basename($mediaFilePaths[0]));
        $this->assertEquals($mediaArgs[1]['name'], basename($mediaFilePaths[1]));
        $this->assertEquals($mediaArgs[2]['name'], basename($mediaFilePaths[2]));
        $this->assertEquals($mediaArgs[3]['name'], basename($mediaFilePaths[3]));
    }

    /**
     * Test handeMediaArgs with null
     *
     */
    public function testHandleMediaArgsNull()
    {
        /**
         * Build directory structure
         */
        $mediaFilePaths = $this->buildMediaFileSystem();

        $argv = [
            'scriptname',
        ];
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        /**
         * Set accessible
         */
        $tooptReflection = new \ReflectionObject($toopt);
        $handleMediaArgs = $tooptReflection->getMethod('handleMediaArgs');
        $handleMediaArgs->setAccessible(true);

        $mediaArgs = $handleMediaArgs->invoke($toopt);

        $this->assertEquals($mediaArgs, null);
    }

    /**
     * Scaffold fake filesystem for media
     *
     * @return Array All file paths
     */
    private function buildMediaFileSystem():Array
    {
        $fileSystem =  vfsStream::setup('path');
        $structure = [
            'img1.jpg' => 'dummy content',
            'img2.jpg' => 'dummy content',
            'img3.jpg' => 'dummy content',
            'img4.jpg' => 'dummy content',
            'img5.jpg' => 'dummy content',
        ];
        vfsStream::create($structure, $fileSystem);
        return array_map(fn($f) => $fileSystem->url().DIRECTORY_SEPARATOR.$f, array_keys($structure));
    }
}