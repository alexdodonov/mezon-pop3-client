<?php

/**
 * Class POP3Client
 *
 * @package     Mezon
 * @subpackage  POP3Client
 * @author      Dodonov A.A.
 * @version     v.1.0 (2019/08/13)
 * @copyright   Copyright (c) 2019, aeon.org
 */

/**
 * POP3 protocol client.
 */
class Pop3Client
{

    /**
     * Connection.
     */
    protected $Connection = false;

    /**
     * Method connects to server
     *
     * @param string $Server
     *            Server domain
     * @param string $Login
     *            Login
     * @param string $Password
     *            Password
     * @param int $TimeOut
     *            Timeout
     * @param int $Port
     *            Port number
     */
    public function connect(string $Server, string $Login, string $Password, int $TimeOut = 5, int $Port = 110)
    {
        $ErrorCode = $ErrorMessage = '';

        $Context = stream_context_create([
            'ssl' => [
                'verify_peer' => false
            ]
        ]);

        $this->Connection = stream_socket_client(
            $Server . ":$Port",
            $ErrorCode,
            $ErrorMessage,
            $TimeOut,
            STREAM_CLIENT_CONNECT,
            $Context);

        $Result = fgets($this->Connection, 1024);

        if (substr($Result, 0, 3) !== '+OK') {
            throw (new Exception('Connection. ' . $Result, 0));
        }

        fputs($this->Connection, "USER $Login\r\n");

        $Result = fgets($this->Connection, 1024);

        if (substr($Result, 0, 3) !== '+OK') {
            throw (new Exception("USER $Login " . $Result, 0));
        }

        fputs($this->Connection, "PASS $Password\r\n");

        $Result = fgets($this->Connection, 1024);

        if (substr($Result, 0, 3) !== '+OK') {
            throw (new Exception("PASS " . $Result . $Login, 0));
        }
    }

    /**
     * Constructor
     *
     * @param string $Server
     *            Server domain
     * @param string $Login
     *            Login
     * @param string $Password
     *            Password
     * @param int $TimeOut
     *            Timeout
     * @param int $Port
     *            Port number
     */
    public function __construct(string $Server, string $Login, string $Password, int $TimeOut = 5, int $Port = 110)
    {
        $this->connect($Server, $Login, $Password, $TimeOut, $Port);
    }

    /**
     * Method returns emails count.
     */
    public function get_count(): int
    {
        fputs($this->Connection, "STAT\r\n");

        $Result = fgets($this->Connection, 1024);

        if (substr($Result, 0, 3) !== '+OK') {
            throw (new Exception("STAT " . $Result, 0));
        }

        $Result = explode(' ', $Result);

        return (intval($Result[1]));
    }

    /**
     * Method returns data from connection
     *
     * @return string Fetched data
     */
    protected function get_data(): string
    {
        $Data = '';

        while (! feof($this->Connection)) {
            $Buffer = chop(fgets($this->Connection, 1024));

            if (strpos($Buffer, '-ERR') === 0) {
                throw (new Exception(str_replace('-ERR ', '', $Buffer), 0));
            }

            $Data .= "$Buffer\r\n";

            if (trim($Buffer) == '.') {
                break;
            }
        }

        return ($Data);
    }

    /**
     * Method returns email's headers
     *
     * @param int $i
     *            Number of the message
     * @return string Headers
     */
    public function get_message_headers(int $i): string
    {
        fputs($this->Connection, "TOP $i 3\r\n");

        return ($this->get_data());
    }

    /**
     * Method deletes email
     *
     * @param int $i
     *            Number of the message
     * @return string Result of the deletion
     */
    public function delete_message($i): string
    {
        fputs($this->Connection, "DELE $i\r\n");

        return (fgets($this->Connection));
    }

    /**
     * Method terminates session
     */
    public function quit()
    {
        fputs($this->Connection, "QUIT\r\n");
    }

    /**
     * Method parses subject with any prefix
     *
     * @param string $Line
     *            Line of the email
     * @param int $i
     *            Line cursor
     * @param array $Headers
     *            Email headers
     * @param string $Type
     *            Mime type
     * @return string Decoded data
     */
    protected function parse_any_type(string $Line, int $i, array $Headers, string $Type): string
    {
        $Subject = substr($Line, 0, strlen($Line) - 2);

        for ($j = $i + 1; $j < count($Headers); $j ++) {
            if (substr($Headers[$j], 0, 1) == ' ') {
                $Subject .= str_replace([
                    ' ' . $Type,
                    '?='
                ], [
                    '',
                    ''
                ], $Headers[$j]);
            } else {
                return (str_replace('Subject: ', '', iconv_mime_decode($Subject . "?=\r\n", 0, "UTF-8")));
            }
        }
    }

    /**
     * Method returns message's subject
     *
     * @param int $i
     *            Line number
     * @return string Decoded data
     */
    public function get_message_subject(int $i): string
    {
        $Headers = $this->get_message_headers($i);

        $Headers = explode("\r\n", $Headers);

        foreach ($Headers as $i => $Line) {
            if (strpos($Line, 'Subject: ') === 0) {
                if (strpos($Line, '=?UTF-8?Q?') !== false) {
                    return ($this->parse_any_type($Line, $i, $Headers, '=?UTF-8?Q?'));
                } elseif (strpos($Line, '=?UTF-8?B?') !== false) {
                    return ($this->parse_any_type($Line, $i, $Headers, '=?UTF-8?B?'));
                }
            }
        }
    }

    /**
     * Method returns true if the mail with the specified subject exists
     *
     * @param string $Subject
     *            Searching subject
     * @return bool Email exists
     */
    public function message_with_subject_exists(string $Subject): bool
    {
        $Count = $this->get_count();

        for ($i = 1; $i <= $Count; $i ++) {
            $MailSubject = $this->get_message_subject($i);

            if ($Subject == $MailSubject) {
                return (true);
            }
        }

        return (false);
    }

    /**
     * Method removes all the mails with the specified subject
     *
     * @param string $Subject
     *            subject of emails to be deleted
     */
    public function delete_messages_with_subject(string $Subject)
    {
        $Count = $this->get_count();

        for ($i = 1; $i <= $Count; $i ++) {
            $MailSubject = $this->get_message_subject($i);

            if ($Subject == $MailSubject) {
                $this->delete_message($i);
            }
        }
    }

    /**
     * Method returns Message-ID
     *
     * @param string $Headers
     *            email headers
     * @return string Message-ID
     */
    public static function get_message_id(string $Headers): string
    {
        $Matches = [];

        preg_match('/Message-ID: <([0-9a-zA-Z\.@\-]*)>/m', $Headers, $Matches);

        return ($Matches[1]);
    }
}
