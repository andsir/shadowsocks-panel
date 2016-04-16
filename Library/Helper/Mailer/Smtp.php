<?php
/**
 * Project: shadowsocks-panel
 * Author: Sendya <18x@loacg.com>
 * Time: 2016/3/31 22:58
 */


namespace Helper\Mailer;


use Contactable\IMailer;
use Core\Error;
use Helper\Option;
use Model\Mail as MMail;

/**
 * Class Smtp
 * @description SMTP 方式发送邮件，需要指定SMTP服务器以及账户密码
 * @package Helper\Mailer
 */
class Smtp implements IMailer {

    private $smtpServer = '';
    private $port = '25';
    private $timeout = '45';
    private $username = '';
    private $password = '';
    private $address = '';
    private $newline = "\r\n";
    private $localdomain = 'localhost';
    private $charset = 'utf-8';
    private $contentTransferEncoding = false;
    private $debug = true;

    private $smtpConnect = false;
    private $to = false;
    private $subject = false;
    private $message = false;
    private $headers = false;
    private $logArray = array();
    public $Error = '';

    private $config;

    public function send(MMail $mail) {
        $this->to = $mail->address;
        $this->subject = $mail->subject;
        $this->message = $mail->content;

        if(!$this->Connect2Server()) {
            if(!$this->debug) return false;
            //echo $this->Error.$this->newline.'<!-- '.$this->newline;
            //print_r($this->logArray);
            //echo $this->newline.'-->'.$this->newline;
            return $this->logArray;
        }
        return true;
    }

    public function __construct() {
        /*
        $config = Option::get("MAIL_" . __CLASS__);
        if(!$config)
            throw new Error("邮件模块：" . __CLASS__ . " 配置不完整，无法使用。");

        $this->config = json_decode($config, true);
        */

        $this->config = array(
            "server" => "smtp.exmail.qq.com",
            "address" => "h@loacg.com",
            "smtp_name" => "h@loacg.com",
            "smtp_pass" => "yinliang1994"
        );

        $this->smtpServer = $this->config['server'];
        $this->address = $this->config['address'];
        $this->username = $this->config['smtp_name'];
        $this->password = $this->config['smtp_pass'];
    }

    private function Connect2Server() {
        $this->smtpConnect = fsockopen($this->smtpServer, $this->port, $errno, $error, $this->timeout);
        $this->logArray['CONNECT_RESPONSE'] = $this->readResponse();
        if (!is_resource($this->smtpConnect)) return false;
        $this->logArray['connection'] = 'Connection accepted: '.$this->readResponse();
        $this->sendCommand("EHLO {$this->localdomain}");
        $this->logArray['EHLO'] = $this->readResponse();
        $this->sendCommand('AUTH LOGIN');
        $this->logArray['AUTH_REQUEST'] = $this->readResponse();
        $this->sendCommand(base64_encode($this->username));
        $this->logArray['REQUEST_USER'] = $this->readResponse();
        $this->sendCommand(base64_encode($this->password));
        $this->logArray['REQUEST_PASSWD'] = $this->readResponse();
        if (substr($this->logArray['REQUEST_PASSWD'], 0, 3)!='235') {
            $this->Error .= 'Authorization error! '.$this->logArray['REQUEST_PASSWD'].$this->newline;
            return false;
        }
        $this->sendCommand("MAIL FROM: {$this->address}");
        $this->logArray['MAIL_FROM_RESPONSE'] = $this->readResponse();
        if (substr($this->logArray['MAIL_FROM_RESPONSE'], 0, 3)!='250') {
            $this->Error .= 'Mistake in sender\'s address! '.$this->logArray['MAIL_FROM_RESPONSE'].$this->newline;
            return false;
        }
        $this->sendCommand("RCPT TO: {$this->to}");
        $this->logArray['RCPT_TO_RESPONCE'] = $this->readResponse();
        if (substr($this->logArray['RCPT_TO_RESPONCE'], 0, 3)!='250') {
            $this->Error .= 'Mistake in reciepent address! '.$this->logArray['RCPT_TO_RESPONCE'].$this->newline;
        }
        $this->sendCommand('DATA');
        $this->logArray['DATA_RESPONSE'] = $this->readResponse();
        if (!$this->sendMail()) return false;
        $this->sendCommand('QUIT');
        $this->logArray['QUIT_RESPONSE'] = $this->readResponse();
        fclose($this->smtpConnect);
        return true;
    }

    private function sendMail() {
        $this->sendHeaders();
        $this->sendCommand($this->message);
        $this->sendCommand('.');
        $this->logArray['SEND_DATA_RESPONSE'] = $this->readResponse();
        if(substr($this->logArray['SEND_DATA_RESPONSE'], 0, 3)!='250') {
            $this->Error .= 'Mistake in sending data! '.$this->logArray['SEND_DATA_RESPONSE'].$this->newline;
            return false;
        }
        return true;
    }

    private function readResponse() {
        $data = '';
        while($str = fgets($this->smtpConnect, 4096)) {
            $data .= $str;
            if(substr($str, 3, 1) == " ") { break; }
        }
        return $data;
    }

    private function sendCommand($string) {
        fputs($this->smtpConnect, $string.$this->newline);
        return ;
    }

    private function sendHeaders() {
        $this->sendCommand('Date: '.date('D, j M Y G:i:s').' +0700');
        $this->sendCommand("From: <{$this->address}>");
        $this->sendCommand("Reply-To: <{$this->address}>");
        $this->sendCommand("To: <{$this->to}>");
        $this->sendCommand("Subject: {$this->subject}");
        $this->sendCommand('MIME-Version: 1.0');
        $this->sendCommand("Content-Type: text/html; charset={$this->charset}");
        if ($this->contentTransferEncoding) $this->sendCommand("Content-Transfer-Encoding: {$this->contentTransferEncoding}");
        $this->sendCommand($this->newline);
        return ;
    }

    public function __destruct() {
        if (is_resource($this->smtpConnect)) fclose($this->smtpConnect);
    }
}