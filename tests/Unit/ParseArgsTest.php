<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class ParseArgsTest extends TestCase
{

    use ReflectionTrait;

    /**
     * Test parseArgs()
     *
     * @dataProvider argvProvider
     */
    public function testParseArgs($argv, $expected)
    {
        include_once('toopt.php');

        $api = new \gbhorwood\toopt\Api();
        $toopt = new \gbhorwood\toopt\Toopt($api);

        $toopt->parseargs($argv);

        $selectedArgs = $this->getInaccessibleProperty($toopt, 'args');

        $this->assertEquals($selectedArgs, $expected);

    }

    /**
     * Provide $argv and expeted content of toopt->args
     *
     * @return Array
     */
    public function argvProvider():Array
    {
        return [
            [ ['scriptname', '--one', 'positional1'], ['one' => 1, 'positional' => ['positional1']] ],
            [ ['scriptname', '-vvv', 'positional1'], ['v' => 3, 'positional' => ['positional1']] ],
            [ ['scriptname', '-v', '-v', '-v', 'positional1'], ['v' => 3, 'positional' => ['positional1']] ],
            [ ['scriptname', '--foo=bar', 'positional1'], ['foo' => 'bar', 'positional' => ['positional1']] ],
            [ ['scriptname', '--foo=bar', '--foo=baz', 'positional1'], ['foo' => 'baz', 'positional' => ['positional1']] ],
            [ ['scriptname', '--foo="multi word arg"', 'positional1'], ['foo' => '"multi word arg"', 'positional' => ['positional1']] ],
            [ ['scriptname', '-vvv', 'positional1', 'positional2'], ['v' => 3, 'positional' => ['positional1', 'positional2']] ],
            [ ['scriptname', 'positional1', '-v', 'positional2'], ['v' => 1, 'positional' => ['positional1', 'positional2']] ],
            [ ['scriptname', 'positional1', '-v', '"multi word positional2"'], ['v' => 1, 'positional' => ['positional1', '"multi word positional2"']] ],
        ];
    } // argvProvider

}