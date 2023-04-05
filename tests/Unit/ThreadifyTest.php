<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

class ThreadifyTest extends TestCase
{
    use ReflectionTrait;

    /**
     * Test threading less than char threshold
     *
     */
    public function testThreadifyNoThread()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $content =<<<TXT
        One line of content
        TXT;

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($toopt);
        $threadify = $tooptReflection->getMethod('threadify');
        $threadify->setAccessible(true);

        $threadedContent = $threadify->invokeArgs($toopt, [$content]);

        $this->assertEquals(count($threadedContent),1);
    }

    /**
     * Test threading more than char threshold
     *
     */
    public function testThreadifyTwoThread()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $c = join(array_fill(0, 60, "Content!! "));

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($toopt);
        $threadify = $tooptReflection->getMethod('threadify');
        $threadify->setAccessible(true);

        $threadedContent = $threadify->invokeArgs($toopt, [$c]);

        $this->assertEquals(count($threadedContent),2);
    }

    /**
     * Test threading more than char threshold, split on space not punctuation
     *
     */
    public function testThreadifyTwoThreadSpace()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $c = join(array_fill(0, 60, "Any Content"));

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($toopt);
        $threadify = $tooptReflection->getMethod('threadify');
        $threadify->setAccessible(true);

        $threadedContent = $threadify->invokeArgs($toopt, [$c]);

        $this->assertEquals(count($threadedContent),2);
    }

    /**
     * Test threading more than char threshold, split on linebreak
     *
     */
    public function testThreadifyTwoThreadLinebreak()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $c = join(array_fill(0, 60, "Any Content".PHP_EOL));

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        /**
         * Set getContent to accessible
         */
        $tooptReflection = new \ReflectionObject($toopt);
        $threadify = $tooptReflection->getMethod('threadify');
        $threadify->setAccessible(true);

        $threadedContent = $threadify->invokeArgs($toopt, [$c]);

        $this->assertEquals(count($threadedContent),2);
    }
}
