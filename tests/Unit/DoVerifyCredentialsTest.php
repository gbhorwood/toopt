<?php
namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use \Tests\Traits\ReflectionTrait;

class DoVerifyCredentialsTest extends TestCase
{
    use ReflectionTrait;
    use \phpmock\phpunit\PHPMock;

    /**
     * Test doVerifyCredentials
     *
     */
    public function testDoVerifyCredentialsSuccess()
    {
        $argv = ['scriptname'];
        include_once('toopt.php');

        $mockedPost = (object) [
            "id" => 7132,
            "username" => "ghorwood",
            "acct" => "ghorwood",
            "display_name" => "ghorwood↙↙↙",
            "locked" => "",
            "bot" => "",
            "discoverable" => 1,
            "group" => "",
            "created_at" => "2016-11-01T00:00:00.000Z",
            "note" => "<p>in 1996 i tore my rotator cuff trying to throw my kick drum off the stage and into the audience at some all-ages show in winnipeg. <br />i also write software.</p>",
            "url" => "https://mastodon.social/@ghorwood",
            "avatar" => "https://files.mastodon.social/accounts/avatars/000/007/132/original/avatar_400x400.jpg",
            "avatar_static" => "https://files.mastodon.social/accounts/avatars/000/007/132/original/avatar_400x400.jpg",
            "header" => "https://files.mastodon.social/accounts/headers/000/007/132/original/0c471f893e5b02f7.jpeg",
            "header_static" => "https://files.mastodon.social/accounts/headers/000/007/132/original/0c471f893e5b02f7.jpeg",
            "followers_count" => 624,
            "following_count" => 643,
            "statuses_count" => 1190,
            "last_status_at" => "2023-02-02",
            "noindex" => "",
            "source" => (object)[
                "privacy" => "public",
                "sensitive" => "",
                "language" => "",
                "note" => "in 1996 i tore my rotator cuff trying to throw my kick drum off the stage and into the audience at some all-ages show in winnipeg.  i also write software.",
                "fields" => [
                        (object) [
                                "name" => "dev.to",
                                "value" => "https://dev.to/gbhorwood",
                                "verified_at" => "2023-02-02T20:59:13.056+00:00",
                        ],
                    ],

                "follow_requests_count" => 0
            ],
            "emojis" => [],
            "fields" => [
                (object)[
                            "name" => "dev.to",
                            "value" => '<a href="https://dev.to/gbhorwood" target="_blank" rel="nofollow noopener noreferrer me"><span class="invisible">https://</span><span class="">dev.to/gbhorwood</span><span class="invisible"></span></a>',
                            "verified_at" => "2023-02-02T20:59:13.056+00:00",
                ],
            ],
            "role" => (object)[
                    "id" => -99,
                    "name" => "",
                    "permissions" => 65536,
                    "color" => "",
                    "highlighted" => "",
            ]
        ];

        $apiStub = $this->getMockBuilder(\gbhorwood\toopt\Api::class)
                        ->setMethods(["get"])
                        ->getMock();
        $apiStub->method('get')
                ->will($this->onConsecutiveCalls($mockedPost));

        $doVerifyCredentials = $this->setAccessible('doVerifyCredentials');

        $toopt = new \gbhorwood\toopt\Toopt($apiStub);

        $doVerifyCredentialsReturn = $doVerifyCredentials->invokeArgs($toopt, ['someinstance', 'someaccesstoken']);

        $this->assertEquals($mockedPost, $doVerifyCredentialsReturn);
    }

}