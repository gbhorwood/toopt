<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use \Tests\Traits\ReflectionTrait;

class HandleCwTest extends TestCase
{
    use ReflectionTrait;


    /**
     * Test --cw argument
     *
     */
    public function testHandleCwSuccess()
    {
        $contentWarning = "some content warning";
        
        $argv = ['scriptname', "--cw=\"$contentWarning\""];
        include_once('toopt.php');
        $api = new \gbhorwood\toopt\Api();

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);
        $toopt->handleCw();

        $cw = $this->getInaccessibleProperty($toopt, 'cw');

        $this->assertEquals(trim($cw, '"'), $contentWarning);
    }
}
