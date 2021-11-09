<?php
namespace Mezon\Pop3;

/**
 * Class Client
 *
 * @package Mezon
 * @subpackage Pop3Client
 * @author Dodonov A.A.
 * @version v.1.0 (2019/08/13)
 * @copyright Copyright (c) 2019, aeon.org
 */

/**
 * POP3 protocol client.
 */
class Client
{

    /**
     * Connection
     *
     * @var ?resource
     */
    private $connection = null;

    /**
     * Method returns connection
     *
     * @return resource connection to server
     */
    private function getConnection()
    {
        if ($this->connection === null) {
            throw (new \Exception('Connection was not establshed', - 1));
        }

        return $this->connection;
    }

    /**
     * Method returns connection
     *
     * @param string $server
     *            server domain
     * @param int $timeOut
     *            timeout
     * @param int $port
     *            port number
     * @return resource connection
     */
    protected function initConnection(string $server, int $timeOut = 5, int $port = 110)
    {
        $errorMessage = '';
        $errorCode = 0;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false
            ]
        ]);

        $connection = stream_socket_client(
            $server . ":$port",
            $errorCode,
            $errorMessage,
            $timeOut,
            STREAM_CLIENT_CONNECT,
            $context);

        if ($connection === false) {
            throw (new \Exception('Connection was not established', - 1));
        }

        return $connection;
    }

    /**
     * Method connects to server
     *
     * @param string $server
     *            Server domain
     * @param string $login
     *            Login
     * @param string $password
     *            Password
     * @param int $timeOut
     *            Timeout
     * @param int $port
     *            Port number
     */
    public function connect(string $server, string $login, string $password, int $timeOut = 5, int $port = 110): void
    {
        $this->connection = $this->initConnection($server, $timeOut, $port);

        $result = fgets($this->getConnection(), 1024);

        if (substr($result, 0, 3) !== '+OK') {
            throw (new \Exception('Connection. ' . $result, 0));
        }

        fputs($this->getConnection(), "USER $login\r\n");

        $result = fgets($this->getConnection(), 1024);

        if (substr($result, 0, 3) !== '+OK') {
            throw (new \Exception("USER $login " . $result, 0));
        }

        fputs($this->getConnection(), "PASS $password\r\n");

        $result = fgets($this->getConnection(), 1024);

        if (substr($result, 0, 3) !== '+OK') {
            throw (new \Exception("PASS " . $result . $login, 0));
        }
    }

    /**
     * Constructor
     *
     * @param string $server
     *            Server domain
     * @param string $login
     *            Login
     * @param string $password
     *            Password
     * @param int $timeOut
     *            Timeout
     * @param int $port
     *            Port number
     */
    public function __construct(
        string $server = '',
        string $login = '',
        string $password = '',
        int $timeOut = 5,
        int $port = 110)
    {
        if ($server !== '') {
            $this->connect($server, $login, $password, $timeOut, $port);
        }
    }

    /**
     * Method returns emails count.
     */
    public function getCount(): int
    {
        fputs($this->getConnection(), "STAT\r\n");

        $result = fgets($this->getConnection(), 1024);

        if (substr($result, 0, 3) !== '+OK') {
            throw (new \Exception("STAT " . $result, 0));
        }

        $result = explode(' ', $result);

        return intval($result[1]);
    }

    /**
     * Method returns data from connection
     *
     * @return string Fetched data
     */
    protected function getData(): string
    {
        $data = '';

        while (! feof($this->getConnection())) {
            $buffer = chop(fgets($this->getConnection(), 1024));

            if (strpos($buffer, '-ERR') === 0) {
                throw (new \Exception(str_replace('-ERR ', '', $buffer), 0));
            }

            $data .= "$buffer\r\n";

            if (trim($buffer) == '.') {
                break;
            }
        }

        return $data;
    }

    /**
     * Method returns email's headers
     *
     * @param int $i
     *            Number of the message. Note that numbering is starting from 0
     * @return string Headers
     */
    public function getMessageHeaders(int $i): string
    {
        fputs($this->getConnection(), "TOP $i 3\r\n");

        return $this->getData();
    }

    /**
     * Method deletes email
     *
     * @param int $i
     *            Number of the message
     * @return string Result of the deletion
     */
    public function deleteMessage($i): string
    {
        fputs($this->getConnection(), "DELE $i\r\n");

        return fgets($this->getConnection());
    }

    /**
     * Method terminates session
     */
    public function quit(): void
    {
        fputs($this->getConnection(), "QUIT\r\n");
    }

    /**
     * Method parses subject with any prefix
     *
     * @param string $line
     *            Line of the email
     * @param int $i
     *            Line cursor
     * @param string[] $headers
     *            Email headers
     * @param string $type
     *            Mime type
     * @return string Decoded data
     */
    protected function parseAnyType(string $line, int $i, array $headers, string $type): string
    {
        $subject = substr($line, 0, strlen($line) - 2);

        $count = count($headers);
        for ($j = $i + 1; $j < $count; $j ++) {
            if (substr($headers[$j], 0, 1) == ' ') {
                $subject .= str_ireplace([
                    ' ' . $type,
                    '?='
                ], [
                    '',
                    ''
                ], $headers[$j]);
            } else {
                return str_replace('Subject: ', '', iconv_mime_decode($subject . "?=\r\n", 0, "UTF-8"));
            }
        }

        return '';
    }

    /**
     * Method returns message's subject
     *
     * @param int $i
     *            Line number
     * @return string Decoded data
     */
    public function getMessageSubject(int $i): string
    {
        $headers = $this->getMessageHeaders($i);

        $headers = explode("\r\n", $headers);

        foreach ($headers as $i => $line) {
            if (strpos($line, 'Subject: ') === 0) {
                if (stripos($line, '=?UTF-8?Q?') !== false) {
                    return $this->parseAnyType($line, $i, $headers, '=?UTF-8?Q?');
                } elseif (stripos($line, '=?UTF-8?B?') !== false) {
                    return $this->parseAnyType($line, $i, $headers, '=?UTF-8?B?');
                } elseif (strpos($line, '=?') === false) {
                    // subject is not encoded
                    return $line;
                } else {
                    throw (new \Exception('Subject encoding is not supported yet : ' . $line, - 1));
                }
            }
        }

        return '';
    }

    /**
     * Method returns true if the mail with the specified subject exists
     *
     * @param string $subject
     *            Searching subject
     * @return bool Email exists
     */
    public function messageWithSubjectExists(string $subject): bool
    {
        $count = $this->getCount();

        for ($i = 1; $i <= $count; $i ++) {
            $mailSubject = $this->getMessageSubject($i);

            if ($subject == $mailSubject) {
                return true;
            }
        }

        return false;
    }

    /**
     * Method removes all the mails with the specified subject
     *
     * @param string $subject
     *            subject of emails to be deleted
     */
    public function deleteMessagesWithSubject(string $subject): void
    {
        $count = $this->getCount();

        for ($i = 1; $i <= $count; $i ++) {
            $mailSubject = $this->getMessageSubject($i);

            if ($subject == $mailSubject) {
                $this->deleteMessage($i);
            }
        }
    }

    /**
     * Method returns Message-ID
     *
     * @param string $headers
     *            email headers
     * @return string Message-ID
     */
    public static function getMessageId(string $headers): string
    {
        $matches = [];

        preg_match('/Message-ID: <([0-9a-zA-Z\.@\-]*)>/mi', $headers, $matches);

        return $matches[1];
    }
}
