<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class HandleHelpTest extends TestCase
{

    use ReflectionTrait;

    /**
     * Test handleHelp runs with arg --help
     * Exit code 0 success
     */
    public function testHandleHelpIsRun()
    {
        $argv = ['scriptname', '--help'];
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        try {
            $this->setOutputCallback(function($output) {
                $this->assertRegexp('/Usage:/', $output);
            });

            $toopt->handleHelp();
        }
        catch(\Exception $e) {
            $this->assertEquals((int)$e->getMessage(), 0);
        }
    }

    /**
     * Test handleHelp does not run without arg --help
     * No exit code
     */
    public function testHandleHelpIsNotRun()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);
        $toopt->parseargs($argv);

        $this->setOutputCallback(function($output) {
            $this->assertEquals(null, $output);
        });

        $toopt->handleHelp();
    }
}