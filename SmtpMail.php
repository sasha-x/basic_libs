<?php

class SmtpMail
{
    protected $host;
    protected $port;
    protected $username;
    protected $password;

    public function __construct($host, $port, $username, $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function send($to, $subject, $message, $from = '', $reply_to = '')
    {
        $subject = "=?utf-8?B?" . base64_encode($subject) . "?=";

        $headers = [
            'From' => $from,
            'To' => $to,
            'Subject' => $subject,
        ];

        if (!empty($reply_to)) {
            $headers['Reply-To'] = $reply_to;
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
            echo $mail->getMessage();
            return false;
        } else {
            return true;
        }
    }

}