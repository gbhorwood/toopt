#!/usr/bin/env php
<?php

define('VERSION', 'beta');
define('PHP_MIN_VERSION', 8.1);
define('CONFIG_DIR', '.config');
define('CONFIG_SUBDIR', 'toopt');
define('DEFAULT_CONFIG_FILE', 'toopt.json');

/**
 * Mastodon configurations
 */
define('MASTODON_CLIENT_NAME', 'Toopt');
define('MASTODON_SCOPES', 'read write follow');
define('MASTODON_CLIENT_WEBSITE', 'https://fruitbat.studio');
define('MASTODON_REDIRECT_URIS', 'urn:ietf:wg:oauth:2.0:oob');

/**
 * Convenience defines of meta characters
 */
define('BACKSPACE', chr(8));
define('ESC', "\033"); // for use with ANSI codes
define('ERASE_TO_END_OF_LINE', "\033[0K");

/**
 * ANSI color codes for output styling. Background colors are calculated from these foreground codes.
 */
define('BLACK', '30');
define('RED', '31');
define('GREEN', '32');
define('YELLOW', '33');
define('BLUE', '34');
define('MAGENTA', '35');
define('CYAN', '36');
define('WHITE', '37');

/**
 * ANSI styling codes.
 */
define('NORMAL', '0');
define('BOLD', '1');
define('ITALIC', '3'); // limited terminal support. ymmv.
define('UNDERLINE', '4');
define('STRIKETHROUGH', '9');
define('REVERSE', '7');

/**
 * Convenience ANSI codes
 */
define('CLOSE_ANSI', ESC."[0m"); // termination code to revert to default styling
define('BOLD_ANSI', ESC."[1m");
define('GREEN_ANSI', ESC."[32m");
define('RED_ANSI', ESC."[31m");

/**
 * Colorized output tags for PSR-2/RFC-5424 levels.
 */
define('OK', "[".ESC."[".GREEN."mOK".CLOSE_ANSI."] "); // non-standard
define('DEBUG', "[".ESC."[".YELLOW."mDEBUG".CLOSE_ANSI."] ");
define('INFO', "[".ESC."[".YELLOW."mINFO".CLOSE_ANSI."] ");
define('NOTICE', "[".ESC."[".YELLOW."mNOTICE".CLOSE_ANSI."] ");
define('WARNING', "[".ESC."[".YELLOW."mWARNING".CLOSE_ANSI."] ");
define('ERROR', "[".ESC."[".RED."mERROR".CLOSE_ANSI."] ");
define('CRITICAL', "[".ESC."[".RED."mCRITICAL".CLOSE_ANSI."] ");
define('ALERT', "[".ESC."[".RED."mALERT".CLOSE_ANSI."] ");
define('EMERGENCY', "[".ESC."[".RED."mEMERGENCY".CLOSE_ANSI."] ");


/**
 * Set the title of our script that ps(1) sees
 */
cli_set_process_title("toopt");

$api = new Api();
$toopt = new Toopt($api);

$toopt->parseargs($argv);
$toopt->preflight();
$toopt->handleHelp();
$toopt->handleVersion();
$toopt->handleAccounts();
$toopt->authenticate();
//$tpt->toot();

die();

$s = $tpt->threadify($str);
print_r($s);

class Toopt
{
    private Int $maxchars = 140;

    private Array $args = [];

    private String $configPath;

    private Api $api;

    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * Validate the environment has everything this script needs to run.
     *
     * @return void
     */
    public function preflight():void
    {
        $preflightFailures = [];

        // validate PHP is PHP_MIN_VERSION or better
        $minVersion = (int)join(explode('.', PHP_MIN_VERSION));
        $phpversion_array = explode('.', phpversion());
        if ((int)$phpversion_array[0].$phpversion_array[1] < $minVersion) {
            $preflightFailures[] = 'Minimum php required is '.PHP_MIN_VERSION;
        }

        // validate posix exists
        if(!extension_loaded('posix')) {
            $preflightFailures[] = "Extention 'posix' is required";
        }

        // validate curl exists
        if(!extension_loaded('curl')) {
            $preflightFailures[] = "Extention 'curl' is required";
        }

        // dump errors and kill script
        if(count($preflightFailures) > 0) {
            array_map(fn($f) => $this->error($f), $preflightFailures);
            fwrite(STDERR, 'exiting'.PHP_EOL);
            die();
        }
    }

    /**
     * Parse command line arguments and switches into $args
     *
     * @param  Array $args The content of $argv
     * @return void
     */
    public function parseargs(Array $args):void
    {
        $args = array_slice($args, 1);
        for ($i=0;$i<count($args);$i++) {

            switch (substr_count($args[$i], "-", 0, 2)) {
                case 1:
                    foreach (str_split(ltrim($args[$i], "-")) as $a) {
                        $this->args[$a] = isset($parsed_args[$a]) ? $parsed_args[$a] + 1 : 1;
                    }
                    break;

                case 2:
                    $this->args[ltrim(preg_replace("/=.*/", '', $args[$i]), '-')] = strpos($args[$i], '=') !== false ? substr($args[$i], strpos($args[$i], '=') + 1) : 1;
                    break;

                default:
                    $this->args['positional'][] = $args[$i];
            }
        }
    }

    /**
     * Output help if the --help argument has been parsed into the $args array
     *
     * @return void
     */
    public function handleHelp():void
    {
        $helpOutput =<<<TXT
        help.
        TXT;
        if(isset($this->args['help'])) {
            fwrite(STDOUT, $helpOutput);
            die();
        }
    }

    /**
     * Output version if the --version argument has been parsed into the $args array
     *
     * @return void
     */
    public function handleVersion():void
    {
        if(isset($this->args['version'])) {
            fwrite(STDOUT, VERSION);
            die();
        }
    }


    /**
     * Outputs the list of accounts in the config file if the --accounts argument has been
     * parsed into the $args array.
     * Indicates last-used account.
     * Handles errors for missing or empty config.
     *
     * @return void
     */
    public function handleAccounts():void
    {
        if(isset($this->args['accounts'])) {
            if($this->setConfigFilePath()) {
                $configArray = json_decode(file_get_contents($this->configPath), true);
                $last = @$configArray['last'] ?? null;

                $outputArray = array_map(function($a) use($last) {
                    $lead = $a == $last ? GREEN_ANSI."* ".CLOSE_ANSI : "  ";
                    return $lead.$a.PHP_EOL;
                }, array_keys($configArray['accounts'] ?? []));

                $output = count($outputArray) > 0 ? join($outputArray) : "There are no accounts. Please login";
                fwrite(STDOUT, $output);
            }
            die();
        }
    }


    public function authenticate():void
    {
        $configSet = $this->setConfigFilePath();

        // if there is no config file or if the login argument is passed, do login
        if(!$configSet || isset($this->args['login'])) {

            // get credentials from user: instance, username and password
            $credentials = $this->pollForLogin();

            // create app with mastodon instance
            $app = $this->createApp($credentials->instance);

            // doOauth
            $oauth = (object)[];
            $oauth->access_token = "notaan accesstoken";

            // update the config
            $this->writeConfig($credentials->instance, $credentials->username, $app->client_id, $app->client_secret, $oauth->access_token);
        }

    }

    /**
     * Write the account to the config file. Set the 'last' account used to this
     *
     * @param  String $instance
     * @param  String $username
     * @param  String $clientId
     * @param  String $clientSecret
     * @param  String $accessToken
     * @return void
     */
    private function writeConfig(String $instance, String $username, String $clientId, String $clientSecret, String $accessToken):void
    {
        $userAddress = "@$username@$instance";

        // read in existing config if any
        $configArray = json_decode(file_get_contents($this->configPath) ?? [], true);

        // add account, clobbering with new if required
        $configArray['last'] = $userAddress;
        $configArray ['accounts'][$userAddress] = [
            'instance' => $instance,
            'username' => $username,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'access_token' => $accessToken,
        ];

        // overwrite
        $fp = fopen($this->configPath, 'w');
        fwrite($fp, json_encode($configArray, JSON_PRETTY_PRINT));
        fclose($fp);
    }

    /**
     * Polls for user address and password and returns
     * array with instance, username and password.
     *
     * @return Array
     */
    private function pollForLogin():Object
    {
        $userAddress = $this->pollForUserAddresss();
        $password = $this->pollForPassword();

        return (object)[
            'instance' => $userAddress->instance,
            'username' => $userAddress->username,
            'password' => $password,
        ];
    }

    private function createApp(String $instance):Object
    {
        $endpoint = "https://$instance/api/v1/apps";
        $data = [
            'client_name' => MASTODON_CLIENT_NAME,
            'redirect_uris' => MASTODON_REDIRECT_URIS,
            'scopes' => MASTODON_SCOPES,
            'website' => MASTODON_CLIENT_WEBSITE,
        ];

        try {
            return $this->api->post($endpoint, $data);
            //{"id":"1732666","name":"Toopt","website":"https://fruitbat.studio","redirect_uri":"urn:ietf:wg:oauth:2.0:oob","client_id":"EDsOc0G89lkV8t_7unv1D_W7QHyS-tG0rue2_t6OU9s","client_secret":"xloIt6mGfcc8betgdcO5UHKtF9PQV51-w_lo-EWYFBw","vapid_key":"BCk-QqERU0q-CfYZjcuB6lnyyOYfJ2AifKqfeGIm7Z-HiTU5T9eTG5GxVA0_OH5mMlI4UkkDTpaZwozy0TzdZ2M="}
        }
        catch (Exception $e) {
            $this->error($e->getMessage());
            die();
        }
    }

    /**
     * Poll user for input of password. Dots echo. Backspace handled.
     *
     * @return String The password entered
     */
    private function pollForPassword():String
    {
        // suppress echo
        readline_callback_handler_install("", function(){});

        $passwordCharArray = [];

        // output 'password:' prompt
        $prompt = BOLD_ANSI."password: ".CLOSE_ANSI;
        fwrite(STDOUT, $prompt);

        // accept and handle each user keystroke until <RETURN>
        while(true) {
            $keystroke = stream_get_contents(STDIN, 1);

            // handle <return>
            if (ord($keystroke) == 10) {
                fwrite(STDOUT, PHP_EOL);
                break;
            }
            // handle <backspace>
            elseif (ord($keystroke) == 127) {
                array_pop($passwordCharArray);
                fwrite(STDOUT, BACKSPACE);
                fwrite(STDOUT, ERASE_TO_END_OF_LINE);
            }
            // log char, echo dot.
            else {
                $passwordCharArray[] = $keystroke;
                fwrite(STDOUT, "*");
            }
        }

        return join($passwordCharArray);
    }


    /**
     * Poll user for input of user address. Return user address as array keyed with 
     * 'username' and 'instance'.
     *
     * Input user address validated to be in format '@username@instance'
     *
     * @return Object The 'username' and 'instance' in an object
     */
    private function pollForUserAddresss():Object
    {
        $prompt = "username"." [@username@instance]: ";

        // poll for input until validation passes
        do {
            $userAddress = trim(readline($prompt));
            $validate = preg_match('!@[a-zA-Z0-9_\-]+@[a-z_\-]+\.[a-z]+!',$userAddress);
            if($validate == 0) {
                $this->error("Must be in format @username@instance");
            }
            // handle up-arrow history scrolling
            readline_add_history($userAddress);
        }
        while($validate < 1);

        // break user address into array of username and instance
        $userAddressParts = array_values(array_filter(explode('@', $userAddress)));

        return (object)[
            'username' => $userAddressParts[0],
            'instance' => end($userAddressParts),
        ];
    }

    /**
     * Sets the config file path as $configPath; either user-supplied or default.
     * If file exists but is not readable, dies.
     * Returns false on error.
     *
     * @return bool
     */
    private function setConfigFilePath():bool
    {
        $this->configPath = $this->getDefaultConfigFilePath();
        
        if(!file_exists($this->configPath)) {
            $this->error("Config at {$this->configPath} does not exist. Please login.");
            return false;
        }

        if(!is_readable($this->configPath)) {
            $this->error("Config at {$this->configPath} exists but is not readable. Please adjust permissions.");
            die();
        }

        return true;
    }



    public function toot():void
    {
        $content = $this->getContent();
        $contentArray = $this->threadify($content);
        print_r($contentArray);
    }

    /**
     * Returns the path to the default config file. 
     * Creates directories and tests permissions.
     *
     * @return String
     */
    private function getDefaultConfigFilePath():String
    {
        $configDir = posix_getpwuid(posix_getuid())['dir'].'/'.CONFIG_DIR;
        $configSubDir = $configDir.'/'.CONFIG_SUBDIR;
        $configFilePath = $configSubDir.'/'.DEFAULT_CONFIG_FILE;

        // create config dir if not exists
        if(!file_exists($configDir)){
            $this->ok("Making configuration directory at $configDir");
            if(!mkdir($configDir, 0755)) {
                $this-error("Could not make configuration directory at $configDir");
                die();
            }
        }

        // validate config dir is a directory and is writeable
        if(!is_writeable($configDir) || !is_dir($configDir)) {
            $this->error("Cannot write to config dir at $configDir");
            die();
        }

        // create config sub dir if not exists
        if(!file_exists($configSubDir)){
            $this->ok("Making configuration directory at $configSubDir");
            if(!mkdir($configSubDir, 0755)) {
                $this-error("Could not make configuration directory at $configDir");
                die();
            }
        }

        // validate config sub dir is a directory and is writeable
        if(!is_writeable($configSubDir) || !is_dir($configSubDir)) {
            $this->error("Cannot write to config dir at $configSubDir");
            die();
        }

        return $configFilePath;
    }

    private function getContent():?String
    {
        $content = $this->readPipeContent();
        $content = $content ? $content : $this->readArgContent();
        $content = trim($content);

        if(strlen($content) == 0) {
            $this->error("No content");
            die();
        }

        return $content;
    }

    private function readArgContent():?String
    {
        $argContent = isset($this->args['positional'][0]) ? $this->args['positional'][0] : null;

        if(is_file($argContent)) {
            $extension ??= @pathinfo($argContent)['extension'];
            if(in_array($extension, ['txt', null])) {
                return file_get_contents($argContent);
            }
            return null;
        }

        return $argContent;
    }


    private function readPipeContent():?String
    {
        $streams = [STDIN];
        $write_array = [];
        $except_array = [];
        $seconds = 0;
        $pipeWaiting = (bool)@stream_select($streams, $write_array, $except_array, $seconds);

        if($pipeWaiting) {
            $pipedContent = null;
            while ($line = fgets(STDIN)) {
                $pipedContent .= $line;
            }
            return (string)$pipedContent;
        }

        return null;
    }





    /**
     * 
     * @param  String $t    The content to post
     * @return Array
     */
    public function threadify(String $t):Array
    {
        // calculate number of posts in thread
        $pageCount = ceil(strlen($t)/ $this->maxchars);

        /**
         *
         */
        $threadify = function($str, $pageCount, $thread, $page = 1) use ( &$threadify ):Array {
            $str = trim($str);
            
            // build page tag to put at bottom of post, ie. 1/2
            $pageTag = $pageCount > 1 ? PHP_EOL.$page++.'/'.$pageCount : null;

            // post too short for threading. return.
            if(strlen($str) < $this->maxchars) {
                $thread[] = $str.$pageTag;
                return $thread;
            }

            // split on newline if newline in last half of thread string, split on space otherwise
            $threadEndChar = ' ';
            if(strpos(substr($str, floor($this->maxchars/2), floor($this->maxchars / 2)), PHP_EOL) !== false) {
                $threadEndChar = PHP_EOL;
            }

            // add head to thread array
            $pos = strrpos(substr($str, 0, $this->maxchars), $threadEndChar);
            $thread[] = substr($str, 0, $pos).$pageTag;

            return $threadify(substr($str, $pos), $pageCount, $thread, $page);
        };

        return $threadify($t, $pageCount, []);
    }


    private function error(String $message):void
    {
        fwrite(STDERR, ERROR.$message.PHP_EOL);
    }

    private function ok(String $message):void
    {
        fwrite(STDOUT, OK.$message.PHP_EOL);
    }
}

Class api {
    
    public function post(String $url, Array $payload):Object
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Accept:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

return (object)json_decode('{"id":"1732666","name":"Toopt","website":"https://fruitbat.studio","redirect_uri":"urn:ietf:wg:oauth:2.0:oob","client_id":"EDsOc0G89lkV8t_7unv1D_W7QHyS-tG0rue2_t6OU9s","client_secret":"xloIt6mGfcc8betgdcO5UHKtF9PQV51-w_lo-EWYFBw","vapid_key":"BCk-QqERU0q-CfYZjcuB6lnyyOYfJ2AifKqfeGIm7Z-HiTU5T9eTG5GxVA0_OH5mMlI4UkkDTpaZwozy0TzdZ2M="}');

        $result = curl_exec($ch);
        $header = curl_getinfo($ch,  CURLINFO_RESPONSE_CODE);
        if($header !== 201 && $header !== 200) {
            print_r($result);
            print_r($header);
            print_r(curl_error($ch));
            throw new Exception("Call to $url returned $header");
        }
        curl_close($ch);
        return json_decode($result);
    }
}



