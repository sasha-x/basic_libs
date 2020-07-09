<?php

/**
 * Read email via IMAP
 * Tested on Ms Exchange server
 */
class IMAP
{

    public $server;
    public $connect;

    protected $readOption;

    protected $htmlmsg;
    protected $plainmsg;
    protected $charset;
    protected $attachments;

    public function __construct($server, $user, $pass, $folder = 'INBOX')
    {
        $this->server = "{" . $server . "/novalidate-cert}";
        return $this->connect($user, $pass, $folder);
    }

    public function connect($user, $pass, $folder)
    {
        $this->connect = imap_open($this->server . $folder, $user, $pass);        //OP_HALFOPEN|OP_DEBUG

        if ($this->connect) {
            echo("Connect successfull");
        } else {
            echo("Connect failed");
            print_r(imap_errors());
            return false;
        }
        return $this->connect;
    }

    public function __destruct()
    {
        imap_close($this->connect);
    }

    //ищет письма в ящике по условию
    //'UNSEEN' - непрочитанные, 'ALL' - все
    //возвращает список их номеров
    public function search($criteria)
    {
        $mails = imap_search($this->connect, $criteria);        //, SE_UID
        return $mails;
    }


    // input $mbox = IMAP stream, $mid = message id
    // output all the following:
    public function read($mid, $setSeen = 1)
    {
        $mbox = $this->connect;


        if ($setSeen == 0) {                //помечать прочтенным или нет
            $this->readOption = FT_PEEK;
        } else {    //TODO
            $this->readOption = 0;
        }

        $htmlmsg = &$this->htmlmsg = '';
        $plainmsg = &$this->plainmsg = '';
        $charset = &$this->charset = '';
        $attachments = &$this->attachments = [];

        // HEADER
        $header = $this->getHeader($mbox, $mid);

        // BODY
        $s = imap_fetchstructure($mbox, $mid);
        if (!$s->parts) {  // simple
            $this->getPart($mbox, $mid, $s, 0); // pass 0 as part-number
        } else {  // multipart: cycle through each part
            foreach ($s->parts as $partno0 => $p) {
                $this->getPart($mbox, $mid, $p, $partno0 + 1);
            }
        }

        $body = [
            'charset' => $charset,
            'body' => !empty($htmlmsg) ? $htmlmsg : $plainmsg,
        ];

        foreach ($attachments as $fn => $fb) {
            $body['files'][] = ['filename' => $fn, 'content' => $fb, 'size' => strlen($fb)];
        }

        $mail = array_merge($header, $body);

        return $mail;
    }

    protected function getPart($mbox, $mid, $p, $partno)
    {
        // $partno = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple

        $htmlmsg = &$this->htmlmsg;
        $plainmsg = &$this->plainmsg;
        $charset = &$this->charset;
        $attachments = &$this->attachments;

        // DECODE DATA
        $data = ($partno) ?
            imap_fetchbody($mbox, $mid, $partno, $this->readOption) :  // multipart
            imap_body($mbox, $mid, $this->readOption);  // simple
        // Any part may be encoded, even plain text messages, so check everything.
        if ($p->encoding == 4) {
            $data = quoted_printable_decode($data);
        } elseif ($p->encoding == 3) {
            $data = base64_decode($data);
        }

        // PARAMETERS
        // get all parameters, like charset, filenames of attachments, etc.
        $params = [];
        if ($p->parameters) {
            foreach ($p->parameters as $x) {
                $params[strtolower($x->attribute)] = $x->value;
            }
        }
        if ($p->dparameters) {
            foreach ($p->dparameters as $x) {
                $params[strtolower($x->attribute)] = $x->value;
            }
        }

        // ATTACHMENT
        // Any part with a filename is an attachment,
        // so an attached text file (type 0) is not mistaken as the message.
        if ($params['filename'] || $params['name']) {
            // filename may be given as 'Filename' or 'Name' or both
            $filename = ($params['filename']) ? $params['filename'] : $params['name'];
            // filename may be encoded, so see imap_mime_header_decode()
            $filename = imap_utf8($filename);
            $attachments[$filename] = $data;  // this is a problem if two files have same name
        }

        // TEXT
        if ($p->type == 0 && $data) {
            // Messages may be split in different parts because of inline attachments,
            // so append parts together with blank row.
            if (strtolower($p->subtype) == 'plain') {
                $plainmsg .= trim($data) . "\n\n";
            } else {
                $htmlmsg .= $data . "<br><br>";
            }
            $charset = $params['charset'];  // assume all parts are same charset
        }

        // EMBEDDED MESSAGE
        // Many bounce notifications embed the original message as type 2,
        // but AOL uses type 1 (multipart), which is not handled here.
        // There are no PHP functions to parse embedded messages,
        // so this just appends the raw source to the main message.
        elseif ($p->type == 2 && $data) {
            $plainmsg .= $data . "\n\n";
        }

        // SUBPART RECURSION
        if ($p->parts) {
            foreach ($p->parts as $partno0 => $p2) {
                $this->getPart($mbox, $mid, $p2, $partno . '.' . ($partno0 + 1));
            }  // 1.2, 1.2.1, etc.
        }
    }


    private function getHeader($connect, $msg_number)
    {
        $header = imap_rfc822_parse_headers(imap_fetchheader($connect, $msg_number));

        $mail['subject'] = imap_utf8($header->subject);

        if (isset($header->to[0]->personal)) {
            $mail['to']['personal'] = imap_utf8($header->to[0]->personal);
        } else {
            $mail['to']['personal'] = '';
        }
        $mail['to']['mailbox'] = imap_utf8($header->to[0]->mailbox);
        $mail['to']['host'] = imap_utf8($header->to[0]->host);

        if (isset($header->from[0]->personal)) {
            $mail['from']['personal'] = imap_utf8($header->from[0]->personal);
        } else {
            $mail['from']['personal'] = '';
        }
        $mail['from']['mailbox'] = imap_utf8($header->from[0]->mailbox);
        $mail['from']['host'] = imap_utf8($header->from[0]->host);
        $mail['date'] = date("Y-m-d H:i:s", strtotime(imap_utf8($header->date)));
        $mail['size'] = imap_utf8($header->Size);
        $mail['id'] = md5($header->message_id);

        return $mail;
    }

    //установка флагов на сообщения
    //$msg_numbers - список/диапазон номеров
    //$flags - список флагов: \\Seen \\Answered  \\Flagged  \\Deleted  \\Draft  \\Recent
    function setflag($msg_numbers, $flags)
    {
        return imap_setflag_full($this->connect, $msg_numbers, $flags);
    }
}
