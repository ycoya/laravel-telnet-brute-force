<?php

namespace Ycoya\LaravelTelnetBruteForce\Telnet;

use Borisuu\Telnet\TelnetClient;

class TelnetCore extends TelnetClient
{
    protected $tmpBuffer;
    protected $debugThis;

    public function waitForLoginPrompt($login_prompt = 'login:')
    {
        $prompt = $this->getRegexPrompt();
        try {
            $this->setPrompt($login_prompt);
            $this->tmpBuffer =  implode(" ", $this->waitPrompt($this->getDoGetRemainingData()));
            $this->logDebug("waitForLoginPrompt " . $this->tmpBuffer);
            //Reset prompt
            $this->setPrompt(str_replace("\\", "", $prompt));
        } catch (\Exception $e) {
            $this->setPrompt(str_replace("\\", "", $prompt));
            if(str_contains(strtolower($e->getMessage()), "timed out")) {
                throw new \Exception("not prompt $prompt found");
            }
            throw new \Exception($e->getMessage(),0, $e);

        }

        return self::TELNET_OK;
    }

    public function sendUsername($username)
    {
        $this->write($username);
    }

    public function waitForPasswordPromptAndSend($password, $password_prompt = "Password")
    {
        $prompt = $this->getRegexPrompt();
        try {
            $this->setPrompt($password_prompt);
            $response = $this->waitPrompt($this->getDoGetRemainingData());
            $this->logDebug("waitForPasswordPromptAndSend response: " . implode(" ", $response));
            $this->logDebug("waitForPasswordPromptAndSend sending password");
            $this->write($password);
            $this->setPrompt(str_replace("\\", "", $prompt));

        } catch (\Exception $e) {
            $this->setPrompt(str_replace("\\", "", $prompt));
            if(str_contains(strtolower($e->getMessage()), "timed out")) {
                throw new \Exception("Not prompt $prompt found");
            }
            throw new \Exception($e->getMessage(),0, $e);
        }
        try {
            $this->getDataReceived();
        }
        catch (\Exception $e) {
            throw new \Exception($e->getMessage(),0, $e);
        }
    }

    /**
     * @return mixed
     */
    public function getTmpBuffer()
    {
        return $this->tmpBuffer;
    }

    /**
     * @param mixed $tmpBuffer
     */
    public function clearTmpBuffer(): void
    {
        $this->tmpBuffer = "";
    }

    protected function getDataReceived(): void
    {
        $count =0;
        $matchesPrompt = false;
        do {
            $this->tmpBuffer = rtrim($this->getLine($matchesPrompt));
            $count++;
            $this->logDebug("getDataReceived tmpBufferLine $count" . $this->tmpBuffer);
        } while (!$matchesPrompt || $count > 100);

    }

    private function logDebug($text)
    {
        $this->debugThis ? \Log::debug(class_basename($this) . "::$text") : null;
    }

    /**
     * @return mixed
     */
    public function getDebugThis()
    {
        return $this->debugThis;
    }

    /**
     * @param mixed $debugThis
     */
    public function setDebugThis($debugThis): void
    {
        $this->debugThis = $debugThis;
    }
}