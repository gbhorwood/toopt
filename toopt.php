#!/usr/bin/env php
<?php
namespace gbhorwood\toopt;

/**
 * Version
 */
define('VERSION', 'beta-2.3');

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
define('CLOSE_ANSI', ESC."[0m");
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
        $toopt->handleDeleteAccount();
        $toopt->handleSetDefaultAccount();
        $toopt->handleAddAccount();
        $toopt->handleCw();
        $toopt->toot();
        Toopt::exit(0);
    }
    // On exception exit with exit code passed in exception
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
    private Int $maxchars = 500;

    /**
     * File extensions identifying files to be treated as media
     */
    private Array $mediaExtensions = [
        'jpg' => "image/jpg",
        'jpeg' => "image/jpg",
        'gif' => "image/gif",
        'png' => "image/png",
    ];

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
        $this->args['positional'] = [];
        $args = array_slice($args, 1);
        for ($i=0;$i<count($args);$i++) {

            switch (substr_count($args[$i], "-", 0, 2)) {
                case 1:
                    foreach (str_split(ltrim($args[$i], "-")) as $a) {
                        $this->args[$a] = isset($this->args[$a]) ? $this->args[$a] + 1 : 1;
                    }
                    break;

                case 2:
                    $this->args[ltrim(preg_replace("/=.*/", '', $args[$i]), '-')][] = strpos($args[$i], '=') !== false ? substr($args[$i], strpos($args[$i], '=') + 1) : 1;
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
     * @throws Exception Terminates script
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

        // on validation fail, dump errors and kill script
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
          or:  toopt.php --interactive
          or:  toopt.php /path/to/image.jpg --description="STRING"
          or:  toopt.php "STRING" /path/to/image.jpg --description="STRING"

        {$boldAnsi}Arguments:{$closeAnsi}
          --list-accounts               Show all accounts available. Default account highlighted.
          --account=ADDRESS             Explicitly use an account
          --add-account                 Log into mastodon and add account to list of available accounts.
          --delete-account=ADDRESS      Show all accounts available. Default account highlighted.
          --set-default-account=ADDRESS Set an account already added as the default
          -i, --interactive             Compose toot content interactively
          --help                        Show this page
          --version                     Show version
          --cw=WARNING                  Set content warning

        {$boldAnsi}Examples:{$closeAnsi}
          $ toopt.php "some toot"
          Toot as the default account.

          $ toopt.php --account=@user@instance.tld "some toot"
          Toot as a specific account as listed in the output from --list-accounts.

          $ echo "some toot" | toopt.php
          Toot content piped in from STDIN

          $ toopt.php /path/to/file
          Toot content in textfile

          $ toopt.php /path/to/toot1.txt /path/to/toot2
          Toot content in textfiles as a thread

          $ toopt.php "some toot" "next toot"
          Toot strings as a thread

          $ toopt.php "some toot" /path/to/secondtoot "final toot"
          Toot strings and files as a thread

          $ toopt.php --description="some image" /path/to/image.jpg
          Toot a media file with a description

          $ toopt.php "some toot" --description="some image" /path/to/image.jpg
          Toot string with attached media file
        TXT;

        if(isset($this->args['help'])) {
            $this->writeOut($helpOutput);
            throw new \Exception(0); // script exit with 0
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
            throw new \Exception(0); // script exit with 0
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
     * Outputs the list of accounts in the config file if the --list-accounts argument has been
     * parsed into the $args array.
     * Indicates last-used account.
     * Handles errors for missing or empty config.
     * Terminates script
     *
     * @return void
     * @throws Exception Terminates script
     */
    public function handleListAccounts():void
    {
        if(isset($this->args['list-accounts'])) {
            $this->outputListAccount();
            throw new \Exception(0); // script exit with 0
        }
    }

    /**
     * Updates one account to being the default in the config file if
     * the --set-default-account argument has been parsed into the $args array
     *
     * @return void
     * @throws Exception Terminates script
     */
    public function handleSetDefaultAccount():void
    {
        if(isset($this->args['set-default-account'])) {
            $account = $this->args['set-default-account'][0];

            // read in existing config if any
            $configArray = json_decode(file_get_contents($this->configFile), true) ?? [];

            // handle invalid account
            if(!in_array($account, array_keys($configArray['accounts'] ?? []))) {
                $this->error("Account $account does not exist");
                $this->outputListAccount();
                throw new \Exception(1);
            }

            // update and write config data
            $configArray['default'] = $account;
            $fp = fopen($this->configFile, 'w');
            fwrite($fp, json_encode($configArray, JSON_PRETTY_PRINT));
            fclose($fp);

            $this->ok("Updated $account to default");
            $this->outputListAccount();

            throw new \Exception(0); // script exit with 0
        }
    }

    /**
     * Deletes one account in the config file if the --delete-account argument
     * has been parsed into the $args array
     *
     * @return void
     * @throws Exception Terminates script
     */
    public function handleDeleteAccount():void
    {
        if(isset($this->args['delete-account'])) {
            $account = $this->args['delete-account'][0];

            // read in existing config if any
            $configArray = json_decode(file_get_contents($this->configFile), true) ?? [];

            // error on trying to delete default account
            if(($configArray['default'] ?? null) == $account) {
                $this->error("Cannot delete default account. Change default first");
                $this->outputListAccount();
                throw new \Exception(1);
            }

            // if account exists, delete it and rewrite config file
            if(in_array($account, array_keys($configArray['accounts'] ?? []))) {
                unset($configArray['accounts'][$account]);
                $fp = fopen($this->configFile, 'w');
                fwrite($fp, json_encode($configArray, JSON_PRETTY_PRINT));
                fclose($fp);
                $this->ok("Deleted $account");
            }
            // error on account does not exist
            else {
                $this->info("Account $account does not exist");
                $this->outputListAccount();
                throw new \Exception(1);
            }

            // output new account list
            $this->outputListAccount();

            throw new \Exception(0); // script exit with 0
        }
    }

    /**
     * Polls user for account credentials, creates app and does login, verifies
     * the account and writes results as an account in the config file. Sets new
     * account as 'default'. Outputs account list.
     * Terminates script
     *
     * @return void
     * @throws Exception Terminates script
     */
    public function handleAddAccount():void
    {
        if(isset($this->args['add-account'])) {
            // get user input for account
            $instance = $this->pollForInstance();
            $username = $this->pollForEmail();
            $password = $this->pollForPassword();

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

            throw new \Exception(0); // script exit with 0
        }
    }

    /**
     * Set the content warning if the --cw argument has been parsed into
     * the args array
     *
     * @return void
     */
    public function handleCw():void
    {
        $this->cw = isset($this->args['cw']) ? $this->args['cw'][0] : null;
    }

    /**
     * Validates credentials, builds toot content with optional threading,
     * posts toot
     *
     * @return void
     */
    public function toot():void
    {
        /**
         * Get user access token
         */
        $credentials = $this->getAccountCredentials();

        /**
         * Call verify credentials on mastodon instance
         */
        $this->doVerifyCredentials($credentials['instance'], $credentials['access_token']);

        /**
         * Get array of tootable text content
         */
        $contentArray = $this->getContent();

        /**
         * Single toot content threaded if necessary
         */
        $contentArray = count($contentArray) == 1 ? $this->threadify($contentArray[0]) : $contentArray;

        /**
         * Add page footers to each toot
         */
        $contentArray = $this->addPageFooters($contentArray);
        
        /**
         * Upload all media, if any, and get ids
         */
        $mediaIds = $this->doMedia($credentials['instance'], $credentials['access_token']);

        /**
         * Post toot
         */
        $toot = array_shift($contentArray);
        $response = $this->doToot($credentials['instance'],
            $toot,
            $mediaIds,
            $this->cw,
            null,
            $credentials['access_token']);

        /**
         * Post rest of toots in thread if necessary
         */
        if(count($contentArray)) {
            array_map(fn($t) => 
                $this->doToot($credentials['instance'],
                    $t,
                    null,
                    $this->cw,
                    $response->id,
                    $credentials['access_token']),
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

        // array of characters of the password
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
    protected function createApp(String $instance):Object
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
    protected function doVerifyCredentials(String $instance, String $accessToken):object
    {
        $endpoint = "https://$instance/api/v1/accounts/verify_credentials";
        try {
            return $this->api->get($endpoint, $accessToken);
        }
        catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->error("Could not verify account");
            throw new \Exception(1);
        }
    }

    /**
     * Post toot to instance.
     *
     * @param  String  $instance The instance fqdn the toot is posted to, ie mastodon.social
     * @param  ?String  $content The text content of the toot, if any
     * @param  ?Array  $mediaIds Optional array of ids of uploaded media
     * @param  ?String $cw The content warning/spoiler, if any
     * @param  ?Int    $inReplyToId The id of the toot this toot is in reply to, if any
     * @param  ?String $accessToken The access token of the user
     * @return stdClass
     * @throws Exception Terminates script
     */
    private function doToot(String $instance, ?String $content, ?Array $mediaIds, ?String $cw, ?Int $inReplyToId, String $accessToken):\stdClass
    {
        $endpoint = "https://$instance/api/v1/statuses";

        $payload = [
            'status' => $content,
        ];

        // handle content warning, if any
        if($cw !== null) {
            $payload['spoiler_text'] = trim($cw).PHP_EOL;
        }

        // handle media attachments, if any
        if($mediaIds !== null) {
            $payload['media_ids'] = array_values($mediaIds);
        }
        
        // set follow-up toots in thread to visibility 'unlisted'
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
     * Do Oauth login
     * Calls instance identified by $instance using app identified with $clientId and
     * $clientSecret to log in user using credentials $username and $password. Returns
     * results as object, including $access_token
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
    protected function doOauth(String $instance, String $username, String $password, String $clientId, String $clientSecret):object
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
    protected function writeConfig(String $userAddress, String $instance, String $clientId, String $clientSecret, String $accessToken):void
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
     * @return Array
     * @throws Exception Terminates script
     */
    protected function getContent():Array
    {
        $content = $this->readInteractiveContent();
        $content = !is_null($content) ? $content : $this->readPipeContent();
        $content = !is_null($content) ? $content : $this->readArgContent();

        if(!$content) {
            $this->error("No content");
            throw new \Exception(1);
        }

        return array_values(array_filter($content));
    }

    /**
     * Parses all media arguments and uploads to instance, returning
     * an array of media ids.
     *
     * @param  String $instance The instance, ie mastodon.social
     * @param  String $accessToken The access token of the account
     * @return ?Array The array of media ids
     */
    protected function doMedia(String $instance, String $accessToken):?Array
    {
        $endpoint = "https://$instance/api/v1/media";

        // get media paths and descriptions from args, if any.
        $mediaArgsArray = $this->handleMediaArgs();

        return match ($mediaArgsArray) {
            // no media
            null => null,

            // upload all media, return ids
            default => array_map(function($m) use($endpoint, $accessToken) {
                            $mediaResult = $this->api->postMedia($endpoint, $m, $accessToken);
                            $this->ok("Media file ".$m['name']." uploaded as ".$mediaResult->id);
                            return $mediaResult->id;
                        }, $mediaArgsArray)
        };
    }

    /**
     * Parse positional arguments for media and return array of the
     * file path to the media file and its description, if any, for 
     * each media file.
     *
     * Only the first four media files will be processed.
     *
     * @return ?Array
     */
    protected function handleMediaArgs():?Array
    {
        // function to handle one positional media file arg and it's optional --description arg
        $parseOneMediaArg = function($p) {
            if(is_file($p)) {
                $extension = @pathinfo($p)['extension'];
                if(!is_readable($p)) {
                    $this->warning("Can't read media file at $p. Check permissions.");
                    return null;
                }
                return in_array(strtolower($extension), array_keys($this->mediaExtensions)) ?
                    [
                        "path" => realpath($p),
                        "name" => basename($p),
                        "mime" => $this->mediaExtensions[$extension],
                        "description" => array_key_exists('description', $this->args) ? array_shift($this->args['description']) : null,
                    ] :
                    null;
            }
        };

        // apply $parseOneMediaArg to all media file args (up to four), if any
        if(count($this->args['positional'])) {
            $argMediaArray = array_filter(
                array_map(fn($p) => $parseOneMediaArg($p), $this->args['positional'])
            );

            // enforce max four media files
            if(count($argMediaArray) > 4) {
                $this->warning("Only the first four media files will be uploaded");
                $argMediaArray = array_slice(array_values($argMediaArray), 0, 4);
            }

            return count($argMediaArray) ? array_values($argMediaArray) : null;
        }

        return null;
    }

    /**
     * Read toot content from STDIN if exists. Returns null if no STDIN content.
     *
     * @return ?Array
     */
    protected function readPipeContent():?Array
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
            return [(string)$pipedContent];
        }

        return null;
    }

    /**
     * Read toot content interactively if the --interactive or -i argument(s)
     * have been parsed into the $args array
     *
     * @return ?Array
     */
    protected function readInteractiveContent():?Array
    {
        /**
         * Function to read multiline input
         */
        $readInput = function() {
            while (true) {
                // read the line
                $line = readline();

                // test for ^D and break loop if we get it
                if ($line === false) {
                    break;
                }

                // add line to history file for navigation
                readline_add_history($line);
            }

            // return lines as string. clear the history.
            $readlineListHistory =  join(PHP_EOL, readline_list_history());
            readline_clear_history();
            return $readlineListHistory;
        };

        /**
         * Poll for interactive input until user approves
         */
        if(isset($this->args['interactive']) || isset($this->args['i'])) {
            while(true){
                // get multiline input
                $this->writeOut(BOLD_ANSI."Enter content. When done, hit ^D on a new line to continue.".CLOSE_ANSI.PHP_EOL);
                $input = $readInput();

                // poll user for confirmation
                $this->writeOut(PHP_EOL.BOLD_ANSI."The content is:".CLOSE_ANSI.PHP_EOL.$input.PHP_EOL);
                if($this->promptMenu('Is this good?') == 'y') {
                    $this->writeOut(PHP_EOL);
                    break;
                }
                $this->writeOut(PHP_EOL);
            }
            return [$input];
        }
        return null;
    }

    /**
     * Read toot content from positional argument(s), either the content of a text file(s)
     * given as the argument or the string literal(s) passed as the argument.
     *
     * If mutliple arguments are given with content they are concatenated.
     *
     * @return ?Array
     */
    protected function readArgContent():?Array
    {
        if(count($this->args['positional'])) {
            $argContentArray = array_map(function($p) {
                if(is_file($p)) {
                    $extension = @pathinfo($p)['extension'];
                    return in_array(strtolower($extension), ['txt', null]) ? file_get_contents($p) : null;
                }
                return $p;
            }, $this->args['positional']);

            return $argContentArray;
        }

        return null;
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

        // build printable list of accounts
        $outputArray = array_map(function($a) use($default) {
            $lead = $a == $default ? GREEN_ANSI."* ".CLOSE_ANSI : "  ";
            return $lead.$a.PHP_EOL;
        }, array_keys($configArray['accounts'] ?? []));

        match(count($outputArray)) {
            0 => $this->error("There are no accounts. Please add an account with --add-account"),
            default => $this->writeOut(BOLD_ANSI."Available accounts:".CLOSE_ANSI.PHP_EOL.join($outputArray))
        };
    }

    /**
     * Add the page footers to each toot in the contentArray if
     * required.
     *
     * @param  Array $contentArray
     * @return Array
     */
    private function addPageFooters(Array $contentArray):Array
    {
        if(count($contentArray) > 1) {
            array_walk($contentArray, function(&$v, $k) use($contentArray) {
                $v = $v.PHP_EOL.($k+1)."/".count($contentArray);
            });
        }
        return $contentArray;
    }

    /**
     * Takes the toot content string as $t and returns array of toots to be threaded.
     * 
     * @param  String $t The content to post
     * @return Array
     */
    protected function threadify(String $t):Array
    {
        /**
         * Tail recurse to build array of toots
         */
        $threadify = function($str, $thread = []) use ( &$threadify ):Array {
            $str = trim($str);
            
            // post too short for threading. return.
            if(strlen($str) < $this->maxchars) {
                $thread[] = $str;
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
                substr($str, 0, $pos)
            ];

            // tail call
            return $threadify(substr($str, $pos), $thread);
        };

        return $threadify($t);
    }

    /**
     * Returns the number of cols to wrap output on.
     * This is 75% of the total columns or 80, whichever is higher.
     *
     * @return Int
     * @note On systems without stty, this returns 80.
     */
    function getColWidth():int
    {
        $ph = popen("/usr/bin/env stty size 2> /dev/null", 'r');
        $size = fread($ph, 2096);
        pclose($ph);
        $sizeArray = explode(' ', $size);

        if(count($sizeArray) != 2) {
            return 80;
        }

        $columns = $sizeArray[1];

        if(filter_var($columns, FILTER_VALIDATE_INT) === false) {
            return 80;
        }
        return $columns*.75 > 80 ? (int)floor($columns*.75) : 80;
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
     * @throws Exception Terminates script
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

        // handle --account=
        if(isset($this->args['account'])) {
            if(!array_key_exists($this->args['account'][0], $configArray['accounts'])) {
                $this->error("The account '".$this->args['account'][0]."' does not exist. Try a different account, or adding an account with --add-account");
                $this->outputListAccount();
                throw new \Exception(1);
            }
            $accountConfig = $configArray['accounts'][$this->args['account'][0]];
        }
        // use default
        else {
            $accountConfig = $configArray['accounts'][$configArray['default']];
        }

        return $accountConfig;
    }

    /**
     * Prompt user to choose from a menu made from the $options array
     * with default
     *
     * @param  String  $prompt
     * @param  Array   $options
     * @param  String  $default
     * @return String  The keystroke from the user, one char
     */
    private function promptMenu($prompt = "Choose One", $options = ['y', 'n'], $default = 'y'):String
    {
        // Create array of valid options and array of options formatted for display
        $options = array_merge([$default], array_diff($options, [$default]));
        $displayOptions = join(',', array_merge([$default], array_diff($options, [$default])));

        /**
         * Prompt user to choose an option until they select either a valid value
         * or accept the default by hitting <RETURN>
         */
        while (true) {

            // Read one keystroke from the user
            $this->writeOut(PHP_EOL.BOLD_ANSI.$prompt.'['.$displayOptions.']'.CLOSE_ANSI);
            readline_callback_handler_install(null, function() {});
            $keystroke = stream_get_contents(STDIN, 1);

            // Return selected value if valid
            if (in_array($keystroke, $options)) {
                readline_callback_handler_remove();
                return $keystroke;
            }

            // Return default if keystroke <RETURN>
            if (ord($keystroke) == 10) {
                readline_callback_handler_remove();
                return $default;
            }

            // No valid choice. Show menu again
            print PHP_EOL;
        }
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
     * Output $message as INFO to STDOUT
     *
     * @param  String $message
     * @return void
     * @note   Uses print() if TESTENVIRONMENT is set as phpunit relies on output buffering
     */
    private function info(String $message):void
    {
        if(getenv('TESTENVIRONMENT')) {
            print(INFO.wordwrap($message, $this->getColWidth()).PHP_EOL);
        }
        else {
            fwrite(STDOUT, INFO.wordwrap($message, $this->getColWidth()).PHP_EOL);
        }
    }

    /**
     * Output $message as WARNING to STDOUT
     *
     * @param  String $message
     * @return void
     * @note   Uses print() if TESTENVIRONMENT is set as phpunit relies on output buffering
     */
    private function warning(String $message):void
    {
        if(getenv('TESTENVIRONMENT')) {
            print(WARNING.wordwrap($message, $this->getColWidth()).PHP_EOL);
        }
        else {
            fwrite(STDOUT, WARNING.wordwrap($message, $this->getColWidth()).PHP_EOL);
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
     *
     * @param  Int $exitCode The exit code for the script. 0 for success. All others are error.
     * @return void
     */
    public static function exit(Int $exitCode = 1):void
    {
        exit($exitCode);
    }
} // class Toopt


/**
 * Api
 *
 * Class containing methods to curl instance
 */
Class Api {
    
    /**
     * POST to instance via curl
     *
     * @param  String $url The full url to POST to
     * @param  Array  $payload The payload to send as an array
     * @param  String $accessToken The access token of the account, if any
     * @return Object The api response object
     * @throws Exception
     */
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

        $result = curl_exec($ch);
        $header = curl_getinfo($ch,  CURLINFO_RESPONSE_CODE);

        if($header !== 201 && $header !== 200) {
            curl_close($ch);
            throw new \Exception("Call to $url returned $header");
        }

        curl_close($ch);

        return json_decode($result);
    }

    /**
     * POST meadia to instance via curl
     *
     * @param  String $url The full url to POST to
     * @param  Array  $payload The payload to send as an array
     * @param  String $accessToken The access token of the account
     * @return Object The api response object
     * @throws Exception
     */
    public function postMedia(String $url, Array $payload, String $accessToken):Object
    {
        $curlFile = curl_file_create($payload['path'], $payload['mime'], $payload['name']);
        $body = [
            'file' => $curlFile,
            'description' => $payload['description']
        ];

        $headers = [
            'Content-Type: multipart/form-data',
            'Accept: application/json',
            'Authorization: Bearer '.$accessToken,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $result = curl_exec($ch);
        $header = curl_getinfo($ch,  CURLINFO_RESPONSE_CODE);

        if($header !== 201 && $header !== 200) {
            curl_close($ch);
            throw new \Exception("Call to $url returned $header");
        }

        curl_close($ch);

        return json_decode($result);
    }

    /**
     * GET from instance via curl
     *
     * @param  String $url The full url to GET from
     * @param  String $accessToken The access token of the account, if any
     * @return Object The api response object
     * @throws Exception
     */
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

        $result = curl_exec($ch);
        $header = curl_getinfo($ch,  CURLINFO_RESPONSE_CODE);

	if($result == false) {
            curl_close($ch);
            throw new \Exception("Call to $url returned nothing");
	}

        if($header !== 201 && $header !== 200) {
            curl_close($ch);
            throw new \Exception("Call to $url returned $header");
        }

        curl_close($ch);

        return json_decode($result);
    }
} // class Api



