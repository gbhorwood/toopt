<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class HandleVersionTest extends TestCase
{

    use ReflectionTrait;

    /**
     * Test handleVersion runs with arg --version
     * Exit code 0 success
     */
    public function testHandleVersionIsRun()
    {
        include_once('toopt.php');
        $argv = ['toopt.php', '--version'];

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        try {
            $this->setOutputCallback(function($output) {
                $this->assertEquals(VERSION, $output);
            });

            $toopt->handleVersion();
        }
        catch(\Exception $e) {
            $this->assertEquals((int)$e->getMessage(), 0);
        }
    }

    /**
     * Test handleVersion does not run without arg --version
     * No exit code
     */
    public function testHandleVersionIsNotRun()
    {
        include_once('toopt.php');
        $argv = ['toopt.php'];

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        $this->setOutputCallback(function($output) {
            $this->assertEquals(null, $output);
        });

        $toopt->handleVersion();
    }
}