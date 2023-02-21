#!/usr/bin/env php
<?php
namespace gbhorwood\toopt;

/**
 * Version
 */
define('VERSION', 'beta');

/**
 * Minimum php version required
 */
define('PHP_MIN_VERSION', 8.1);

/**
 * Path to config file in user's home directory
 */
define('CONFIG_FILE', '.config'.DIRECTORY_SEPARATOR.'toopt'.DIRECTORY_SEPARATOR.'toopt.json');

/**
 * Mastodon client app configurations
 */
define('MASTODON_CLIENT_NAME', 'Toopt');
define('MASTODON_SCOPES', 'read write follow');
define('MASTODON_CLIENT_WEBSITE', 'https://fruitbat.studio');
define('MASTODON_REDIRECT_URIS', 'urn:ietf:wg:oauth:2.0:oob');

/**
 * Tell phpunit when using processIsolation what STDIN is
 */
if(!defined('STDIN')) define('STDIN', fopen("php://stdin","r"));

/**
 * Convenience defines of meta characters for ANSI
 */
define('BACKSPACE', chr(8));
define('ESC', "\033"); // for use with ANSI codes
define('ERASE_TO_END_OF_LINE', "\033[0K");

/**
 * ANSI color codes for output styling.
 * Background colors are calculated from these foreground codes.
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

/**
 * Script start
 * Run only if executed on cli
 */
if(basename($argv[0]) == basename(__FILE__)) {
    try {
        $api = new Api();
        $toopt = new Toopt($api);
        $toopt->parseargs($argv);
        $toopt->preflight();
        $toopt->handleHelp();
        $toopt->handleVersion();
        $toopt->setConfigFile();
        $toopt->handleListAccounts();
        $toopt->handleAddAccount();
        $toopt->handleCw();
        $toopt->toot();
        Toopt::exit(0);
    }
    catch(\Exception $e) {
        Toopt::exit((int)$e->getMessage());
    }
}

/**
 * Toopt
 *
 * Main class containing toopt functionality
 */
class Toopt
{
    /**
     * Maximum chars of one toot. Content longer will be threaded.
     */
    private Int $maxchars = 140;

    /**
     * Parsed command line arguments
     */
    private Array $args = [];

    /**
     * Full path to configuration file
     */
    private String $configFile;

    /**
     * The content warning for all toots in thread
     */
    private ?String $cw;

    /**
     * Api object
     */
    private Api $api;

    /**
     * Constructor
     *
     * @param  Api $api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;
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
                        $this->args[$a] = isset($this->args[$a]) ? $this->args[$a] + 1 : 1;
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
     * Validate the environment has everything this script needs to run.
     * Terminate on failure
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

        // dump errors, if any, and kill script
        if(count($preflightFailures) > 0) {
            array_map(fn($f) => $this->error($f), $preflightFailures);
            $this->error('exiting');
            throw new \Exception(1);
        }
    }

    /**
     * Output help if the --help argument has been parsed into the $args array
     * Terminates script
     *
     * @return void
     * @throws Exception Terminates script
     */
    public function handleHelp():void
    {
        // ansi stylings as args to use in heredoc
        $boldAnsi = BOLD_ANSI;
        $closeAnsi = CLOSE_ANSI;

        // help text
        $helpOutput =<<<TXT
        {$boldAnsi}Usage:{$closeAnsi}
               toopt.php "STRING"
          or:  toopt.php /path/to/text/file
          or:  echo "STRING" | toopt.php
          or:  cat /path/to/text/file | toopt.php

        {$boldAnsi}Arguments:{$closeAnsi}
          --list-accounts    Show all accounts available. Default account highlighted.
          --add-account      Log into mastodon and add account to list of available accounts.
          --account=ADDRESS  Explicitly use an account
          --help             Show this page
          --version          Show version
          --cw=WARNING       Set content warning

        {$boldAnsi}Examples:{$closeAnsi}
          $ toopt.php "some toot"
          Toot as the default account.

          $ toopt.php --address=@user@instance.tld "some toot"
          Toot as a specific account as listed in the output from --list-accounts.

          $ echo "some toot" | toopt.php
          Toot content piped in from STDIN

          $ toopt.php /path/to/file
          Toot content in textfile
        TXT;

        if(isset($this->args['help'])) {
            $this->writeOut($helpOutput);
            throw new \Exception(0);
        }
    }


    /**
     * Output version if the --version argument has been parsed into the $args array
     * Terminates script
     *
     * @return void
     * @throws Exception Terminates script
     */
    public function handleVersion():void
    {
        if(isset($this->args['version'])) {
            $this->writeOut(VERSION);
            throw new \Exception(0);
        }
    }

    /**
     * Set path to config file. Make directories and files if necessary.
     * Validate permissions
     *
     * @return void
     * @throws Exception Terminates script
     */
    public function setConfigFile():void
    {
        $configFile = $this->getConfigFilePath();
        $dotConfigToopt = (dirname($configFile));
        $dotConfig = dirname($dotConfigToopt);

        $handleDirectory = function(String $path) {
            if(!file_exists($path)) {
                if(!mkdir($path, 0755)) {
                    $this->error("Could not make directory $path");
                    throw new \Exception(1);
                }
            }

            if(!is_dir($path)) {
                $this->error("File $path exists but is not a directory");
                throw new \Exception(1);
            }

            if(!is_writeable($path)) {
                $this->error("File $path is not writeable by this user");
                throw new \Exception(1);
            }
        };

        $handleDirectory($dotConfig);
        $handleDirectory($dotConfigToopt);

        if(!file_exists($configFile)) {
            touch($configFile);
        }

        if(!is_readable($configFile)) {
            $this->error("Configuration file at $configFile is not readable");
            throw new \Exception(1);
        }

        $this->configFile = $configFile;
    }

    /**
     * Outputs the list of accounts in the config file if the --accounts argument has been
     * parsed into the $args array.
     * Indicates last-used account.
     * Handles errors for missing or empty config.
     * Terminates script
     *
     * @return void
     */
    public function handleListAccounts():void
    {
        if(isset($this->args['list-accounts'])) {
            if(isset($this->configFile)) {
                $this->outputListAccount();
            }
            throw new \Exception(0);
        }
    }

    /**
     * Polls user for account credentials, creates app and does login, verifies
     * the account and writes results as an account in the config file. Sets new
     * account as 'default'. Outputs account list.
     * Terminates script
     *
     * @return void
     */
    public function handleAddAccount():void
    {
        if(isset($this->args['add-account'])) {
            // get user input for account
            $instance = $this->pollForInstance();
            $username = $this->pollForEmail();
            $password = $this->pollForPassword();
            print "got == $instance $username $password".PHP_EOL;die();

            // create app
            $app = $this->createApp($instance);

            // log user in
            $session = $this->doOauth($instance, $username, $password, $app->client_id, $app->client_secret);

            // verify account and build the address for it, ie @username@instance.tld
            $verification = $this->doVerifyCredentials($instance, $session->access_token);
            $accountAddress = '@'.$verification->acct.'@'.$instance;

            // write to config file
            $this->writeConfig($accountAddress, $instance, $app->client_id, $app->client_secret, $session->access_token);

            // dump list of accounts
            $this->outputListAccount();

            throw new \Exception(0);
        }
    }

    /**
     * Set the content warning if any.
     *
     * @return void
     */
    public function handleCw():void
    {
        $this->cw = isset($this->args['cw']) ? $this->args['cw'] : null;
    }


    public function toot():void
    {
        // get user access token
        $credentials = $this->getAccountCredentials();

        // call verify credentials on mastodon instance
        $this->doVerifyCredentials($credentials['instance'], $credentials['access_token']);

        // get the tootable content for this thread
        $content = $this->getContent();

        // convert to a thread
        $contentArray = $this->threadify($content);


        $toot = array_shift($contentArray);
        $response = $this->doToot($credentials['instance'], $toot['text'], $this->cw, null, $credentials['access_token']);

        if(count($contentArray)) {
            array_map(fn($t) => 
                $this->doToot($credentials['instance'], $t['text'], $this->cw, $response->id, $credentials['access_token']),
                $contentArray);
        }
    }

    /**
     * Polls user for their mastodon instance and returns
     *
     * @return String  The mastodon instance
     */
    protected function pollForInstance():String
    {
        $prompt = "instance: ";

        $validate = 1;
        do {
            $instance = trim(readline($prompt));
            $validate = preg_match('![0-9a-zA-Z0-9_\-]+\.[0-9A-Za-z_\-]+!',$instance);
            if($validate == 0) {
                $this->error("Must be in format <domain>.<tld>");
            }
            // handle up-arrow history scrolling
            readline_add_history($instance);
        }
        while($validate < 1);

        return $instance;
    }

    /**
     * Polls user for the email address used for login username and returns
     *
     * @return String The email
     */
    protected function pollForEmail():String
    {
        $prompt = "email: ";

        $validate = 1;
        do {
            $email = trim(readline($prompt));
            $validate = filter_var($email, FILTER_VALIDATE_EMAIL);
            if($validate == 0) {
                $this->error("Must be a valid email");
            }
            // handle up-arrow history scrolling
            readline_add_history($email);
        }
        while($validate < 1);

        return $email;
    }

    /**
     * Poll user for input of password. Dots echo. Backspace handled.
     *
     * @return String The password entered
     */
    protected function pollForPassword():String
    {
        // suppress echo
        readline_callback_handler_install("", function(){});

        $passwordCharArray = [];

        // output 'password:' prompt
        $prompt = "password: ";
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
     * Create the app on the mastodon instance.
     * Calls POST to mastodon instance to create the app which is used for all
     * future api calls. Returns instance details as object, notably:
     *  - client_id
     *  - client_secret
     * Terminates on error.
     *
     * @param  String $instance The instance, ie mastodon.social
     * @return Object The instance details
     * @throws Exception Terminates script
     */
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
        }
        catch (Exception $e) {
            $this->error("Could not create app. ".$e->getMessage());
            throw new Exception(1);
        }
    }

    /**
     * Calls instance identified by $instance to verify credentials of account
     * identified by $accessToken.
     * Terminates on error.
     *
     * @param  String $instance
     * @param  String $accessToken
     * @return Object
     * @throws Exception Terminates script
     */
    private function doVerifyCredentials(String $instance, String $accessToken):object
    {
        $endpoint = "https://$instance/api/v1/accounts/verify_credentials";
        try {
            return  $this->api->get($endpoint, $accessToken);
        }
        catch (Exception $e) {
            $this->error("Could not verify account. ".$e->getMessage());
            throw new \Exception(1);
        }
    }

    private function doToot(String $instance, String $content, ?String $cw, ?Int $inReplyToId, String $accessToken)
    {
        $endpoint = "https://$instance/api/v1/statuses";

        $payload = [
            'status' => $content,
        ];

        if($cw !== null) {
            $payload['spoiler_text'] = trim($cw).PHP_EOL;
        }
        
        $payload['visibility'] = 'public';
        if($inReplyToId !== null) {
            $payload['in_reply_to_id'] = $inReplyToId;
            $payload['visibility'] = 'unlisted';
        }

        try {
            $response = $this->api->post($endpoint, $payload, $accessToken);
            $this->ok("Toot ".$response->id." sent");
            return $response;
        }
        catch (Exception $e) {
            $this->error("Could not post status. ".$e->getMessage());
            throw new \Exception(1);
        }
    }

    /**
     * Calls instance identified by $instance using app identified iwth $clientId and
     * $clientSecret to log in user using credentials $username and $password. Returns
     * results as object, including * $access_token
     * Terminates on error.
     *
     * @param  String $instance
     * @param  String $username
     * @param  String $password
     * @param  String $clientId
     * @param  String $clientSecret
     * @return Object
     * @throws Exception Terminates script
     */
    private function doOauth(String $instance, String $username, String $password, String $clientId, String $clientSecret):object
    {
        $endpoint = "https://$instance/oauth/token";
        $data = [
            'grant_type' => 'password',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'username' => $username,
            'password' => $password,
            'scope' => MASTODON_SCOPES,
        ];
        
        try {
            return $this->api->post($endpoint, $data);
        }
        catch (Exception $e) {
            $this->error("Could not log in. ".$e->getMessage());
            throw new \Exception(1);
        }
    }

    /**
     * Write the account to the config file. Sets the 'default' to the accounted
     * keyed by $userAddress
     *
     * @param  String $userAddress
     * @param  String $instance
     * @param  String $clientId
     * @param  String $clientSecret
     * @param  String $accessToken
     * @return void
     */
    private function writeConfig(String $userAddress, String $instance, String $clientId, String $clientSecret, String $accessToken):void
    {

        // read in existing config if any
        $configArray = json_decode(file_get_contents($this->configFile) ?? [], true);

        // add account, clobbering with new if required
        $configArray['default'] = $userAddress;
        $configArray ['accounts'][$userAddress] = [
            'instance' => $instance,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'access_token' => $accessToken,
        ];

        // overwrite
        $fp = fopen($this->configFile, 'w');
        fwrite($fp, json_encode($configArray, JSON_PRETTY_PRINT));
        fclose($fp);
    }

    /**
     * Reads the content of the toot from one of several sources in this
     * order of priority:
     *   - STDIN
     *   - positional file
     *   - positional string
     *
     * @return String
     */
    protected function getContent():String
    {
        $content = $this->readPipeContent();
        $content = $content ? $content : $this->readArgContent();
        $content = trim($content);

        if(strlen($content) == 0) {
            $this->error("No content");
            throw new \Exception(1);
        }

        return $content;
    }

    /**
     * Read toot content from STDIN if exists. Returns null if no STDIN content.
     *
     * @return ?String
     */
    protected function readPipeContent():?String
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
     * Read toot content from positional argument, either the content of a text file
     * given as the argument or the string literal passed as the argument.
     *
     * @return ?String
     */
    protected function readArgContent():?String
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

    /**
     * Outputs the list of accounts in the CONFIG_FILE. Default account is 
     * preceeded by a green star.
     *
     * @return void
     */
    private function outputListAccount():void
    {
        $configArray = json_decode(file_get_contents($this->configFile), true);
        $default = @$configArray['default'] ?? null;

        $outputArray = array_map(function($a) use($default) {
            $lead = $a == $default ? GREEN_ANSI."* ".CLOSE_ANSI : "  ";
            return $lead.$a.PHP_EOL;
        }, array_keys($configArray['accounts'] ?? []));

        $output = count($outputArray) > 0 ?
            BOLD_ANSI."Available accounts:".CLOSE_ANSI.PHP_EOL.join($outputArray) :
            "There are no accounts. Please add an account with --add-account";

        //fwrite(STDOUT, $output);
        $this->writeOut($output);
    }

    /**
     * Takes the toot content string as $t and returns array of toots to be threaded.
     * 
     * @param  String $t The content to post
     * @return Array
     */
    private function threadify(String $t):Array
    {
        // calculate number of posts in thread
        $pageCount = ceil(strlen($t)/ $this->maxchars);

        /**
         * Tail recurse to build array of toots
         */
        $threadify = function($str, $pageCount, $thread = [], $page = 1) use ( &$threadify ):Array {
            $str = trim($str);
            
            // build page tag to put at bottom of post, ie. 1/2
            $pageTag = $pageCount > 1 ? PHP_EOL.$page++.'/'.$pageCount : null;

            // post too short for threading. return.
            if(strlen($str) < $this->maxchars) {
                $thread[] = [
                    'text' => $str.$pageTag,
                    'uploads' => [],
                ];
                return $thread;
            }

            /**
             * Function to get the character to split the thread post on.
             * - If a line break exists in the last 30% of the thread substring, use that
             * - If a sentence break (.?!) exists in the last 20% of the thread substring, use that
             * - Otherwise, use a space.
             */
            $getThreadEndChar = function($str) {
                // if there is a line break in the last 30% of the string that is the maximum thread length, split on line break
                if(strpos(substr($str, floor($this->maxchars*0.7), floor($this->maxchars - ($this->maxchars*0.7))), PHP_EOL) !== false) {
                    return PHP_EOL;
                }

                // get positions for final ocurrence of all sentence breaks
                // if the last of these is in the last 20% of the substring, split on that
                $charPositions = [
                    '.' => strrpos($str, '. '),
                    '!' => strrpos($str, '! '),
                    '?' => strrpos($str, '? '),
                ];
                arsort($charPositions);
                if(array_values($charPositions)[0] >= $this->maxchars * 0.8) {
                    return array_key_first($charPositions);
                }

                // default: split on space.
                return ' ';
            };

            // get the char we split this post on
            $threadEndChar = $getThreadEndChar(substr($str, 0, $this->maxchars));

            // substring for this toot is head. add to accumulator.
            $pos = strrpos(substr($str, 0, $this->maxchars), $threadEndChar)+1;
            $thread[] = [
                'text' => substr($str, 0, $pos).$pageTag,
                'uploads' => [],
            ];

            // tail call
            return $threadify(substr($str, $pos), $pageCount, $thread, $page);
        };

        return $threadify($t, $pageCount);
    }

    /**
     * Returns the number of cols to wrap output on.
     * This is one half of the total columns or 80, whichever is higher.
     *
     * @return Int
     * @note On systems without stty or awk, this returns 80.
     */
    private function getColWidth():int {
        $ph = popen("/usr/bin/env stty -a 2> /dev/null | awk -F'[ ;]' '/columns/ { print $9 }'", 'r');
        $columns = fread($ph, 2096);
        pclose($ph);
        if(filter_var($columns, FILTER_VALIDATE_INT) === false) {
            return 80;
        }
        return $columns/2 > 80 ? (int)floor($columns/2) : 80;
    }

    /**
     * Return path to the config file
     *
     * @return String
     * @note   posix required.
     */
    protected function getConfigFilePath():String
    {
        return posix_getpwuid(posix_getuid())['dir'].DIRECTORY_SEPARATOR.CONFIG_FILE;
    }

    /**
     * Gets user credentials from config file and returns as array.
     * Terminates on error
     *
     * @return Array
     */
    private function getAccountCredentials():Array
    {
        $accountConfig = null;
        $configArray = json_decode(file_get_contents($this->configFile) ?? [], true);

        // config is empty. error and terminate
        if(is_null($configArray)) {
            $this->error("No accounts available. Run with --add-account");
            throw new \Exception(1);
        }

        // config is malformed. error and terminate
        if(['default', 'accounts'] != array_keys($configArray)) {
            $this->error("Configuration file is malformed. Try running with --add-account.");
            throw new \Exception(1);
        }

        // handle --account= arg
        if(isset($this->args['account'])) {
            if(!array_key_exists($this->args['account'], $configArray['accounts'])) {
                $this->error("The account '".$this->args['account']."' does not exist. Try a different account, or adding an account with --add-account");
                $this->outputListAccount();
                throw new \Exception(1);
            }
            $accountConfig = $configArray['accounts'][$this->args['account']];
        }
        // use default
        else {
            $accountConfig = $configArray['accounts'][$configArray['default']];
        }

        return $accountConfig;
    }

    /**
     * Output $message as ERROR to STDERR
     *
     * @param  String $message
     * @return void
     * @note   Uses print() if TESTENVIRONMENT is set as phpunit relies on output buffering
     */
    private function error(String $message):void
    {
        if(getenv('TESTENVIRONMENT')) {
            print(ERROR.wordwrap($message, $this->getColWidth()).PHP_EOL);
        }
        else {
            fwrite(STDERR, ERROR.wordwrap($message, $this->getColWidth()).PHP_EOL);
        }
    }

    /**
     * Output $message as OK to STDOUT
     *
     * @param  String $message
     * @return void
     * @note   Uses print() if TESTENVIRONMENT is set as phpunit relies on output buffering
     */
    private function ok(String $message):void
    {
        if(getenv('TESTENVIRONMENT')) {
            print(OK.wordwrap($message, $this->getColWidth()).PHP_EOL);
        }
        else {
            fwrite(STDOUT, OK.wordwrap($message, $this->getColWidth()).PHP_EOL);
        }
    }

    /**
     * Outputs text with wordwrapping
     *
     * @param  String $message
     * @return void
     * @note   Uses print() if TESTENVIRONMENT is set as phpunit relies on output buffering
     */
    private function writeOut($message):void
    {
        if(getenv('TESTENVIRONMENT')) {
            print(wordwrap($message, $this->getColWidth(), PHP_EOL));
        }
        else {
            fwrite(STDOUT, wordwrap($message, $this->getColWidth(), PHP_EOL));
        }
    }

    /**
     * Terminates the script with exit code $exitCode
     */
    public static function exit(Int $exitCode = 1):void
    {
        exit($exitCode);
    }
}

Class Api {
    
    public function post(String $url, Array $payload, String $accessToken = null):Object
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if($accessToken) {
            $headers[] = "Authorization: Bearer ".$accessToken;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

//return (object)json_decode('{"id":"1732666","name":"Toopt","website":"https://fruitbat.studio","redirect_uri":"urn:ietf:wg:oauth:2.0:oob","client_id":"EDsOc0G89lkV8t_7unv1D_W7QHyS-tG0rue2_t6OU9s","client_secret":"xloIt6mGfcc8betgdcO5UHKtF9PQV51-w_lo-EWYFBw","vapid_key":"BCk-QqERU0q-CfYZjcuB6lnyyOYfJ2AifKqfeGIm7Z-HiTU5T9eTG5GxVA0_OH5mMlI4UkkDTpaZwozy0TzdZ2M="}');

        $result = curl_exec($ch);
        $header = curl_getinfo($ch,  CURLINFO_RESPONSE_CODE);
        if($header !== 201 && $header !== 200) {
            print curl_error($ch);
            curl_close($ch);
            throw new Exception("Call to $url returned $header");
        }
        curl_close($ch);
        return json_decode($result);
    }

    public function get(String $url, String $accessToken = null):Object
    {
        $headers = [
            'Accept: application/json',
        ];
        if($accessToken) {
            $headers[] = "Authorization: Bearer ".$accessToken;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET' );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

//return (object)json_decode('{"id":"1732666","name":"Toopt","website":"https://fruitbat.studio","redirect_uri":"urn:ietf:wg:oauth:2.0:oob","client_id":"EDsOc0G89lkV8t_7unv1D_W7QHyS-tG0rue2_t6OU9s","client_secret":"xloIt6mGfcc8betgdcO5UHKtF9PQV51-w_lo-EWYFBw","vapid_key":"BCk-QqERU0q-CfYZjcuB6lnyyOYfJ2AifKqfeGIm7Z-HiTU5T9eTG5GxVA0_OH5mMlI4UkkDTpaZwozy0TzdZ2M="}');

        $result = curl_exec($ch);
        $header = curl_getinfo($ch,  CURLINFO_RESPONSE_CODE);
        if($header !== 201 && $header !== 200) {
            curl_close($ch);
            throw new \Exception("Call to $url returned $header");
        }
        curl_close($ch);
        return json_decode($result);
    }
}



