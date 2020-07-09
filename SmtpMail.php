<?php

//use Mail;
//use PEAR;

class SmtpMail
{
    public $host;
    public $port;
    public $username;
    public $password;

    public $error;

    public function __construct($host, $port, $username, $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function send($to, $subject, $message, $from = '', $replyTo = '')
    {
        $subject = "=?utf-8?B?" . base64_encode($subject) . "?=";

        if (!$from) {
            $from = $this->username;
        }

        $headers = [
            'From' => $from,
            'To' => $to,
            'Subject' => $subject,
        ];

        if (!empty($replyTo)) {
            $headers['Reply-To'] = $replyTo;
        }

        $headers["MIME-Version"] = "1.0";
        $headers["Content-type"] = "text/html; charset=utf-8;";

        $smtp = Mail::factory('smtp',
            [
                'host' => $this->host,
                'port' => $this->port,
                'auth' => true,
                'username' => $this->username,
                'password' => $this->password,
                //'auth' => true,   //"PLAIN",
                'socket_options' => [
                    'ssl' => [
                        'verify_peer_name' => false,
                        'verify_peer' => false,
                        'allow_self_signed' => true,
                    ],
                ],
            ]);

        $mail = $smtp->send($to, $headers, $message);

        if (PEAR::isError($mail)) {
            $this->error = $mail->getMessage();
            return false;
        } else {
            return true;
        }
    }

}