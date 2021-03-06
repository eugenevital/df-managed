<?php namespace DreamFactory\Managed\Support;

use DreamFactory\Managed\Enums\AuditLevels;
use DreamFactory\Managed\Enums\ManagedDefaults;
use Psr\Log\LoggerInterface;

/**
 * Simple GELF message sender
 */
class GelfLogger implements LoggerInterface
{
    //**************************************************************************
    //* Constants
    //**************************************************************************

    /**
     * @var string Logstash target of GELF messages
     */
    const DEFAULT_HOST = 'localhost';
    /**
     * @const integer Port that logstash listens on
     */
    const DEFAULT_PORT = 12202;
    /**
     * @const integer Maximum message size before splitting into chunks
     */
    const MAX_CHUNK_SIZE = 8154;
    /**
     * @const integer Maximum number of chunks allowed by GELF
     */
    const MAX_CHUNKS_ALLOWED = 128;

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type string The default log host
     */
    static protected $host = self::DEFAULT_HOST;
    /**
     * @type int
     */
    static protected $port = self::DEFAULT_PORT;

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function log($level, $message, array $context = [])
    {
        $_message = new GelfMessage($context);
        $_message->setLevel($level)->setFullMessage($message);

        return $this->send($_message);
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function emergency($message, array $context = [])
    {
        $this->log(AuditLevels::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function alert($message, array $context = [])
    {
        $this->log(AuditLevels::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function critical($message, array $context = [])
    {
        $this->log(AuditLevels::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function error($message, array $context = [])
    {
        $this->log(AuditLevels::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function warning($message, array $context = [])
    {
        $this->log(AuditLevels::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function notice($message, array $context = [])
    {
        $this->log(AuditLevels::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function info($message, array $context = [])
    {
        $this->log(AuditLevels::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function debug($message, array $context = [])
    {
        $this->log(AuditLevels::DEBUG, $message, $context);
    }

    /**
     * @param GelfMessage $message The message to send
     *
     * @return bool
     */
    public function send(GelfMessage $message)
    {
        try {
            if (false === ($_chunks = $this->prepareMessage($message))) {
                return false;
            }

            $_url = 'udp://' . static::$host . ':' . static::$port;
            $_sock = stream_socket_client($_url);

            foreach ($_chunks as $_chunk) {
                if (!fwrite($_sock, $_chunk)) {
                    return false;
                }
            }
        } catch (\Exception $_ex) {
            //  Failure is not an option
            return false;
        }

        return true;
    }

    /**
     * Static method for preparing a GELF message to be sent
     *
     * @param GelfMessage $message
     *
     * @return array
     */
    protected function prepareMessage(GelfMessage $message)
    {
        try {
            if (false === ($_gzJson = gzcompress($message->toJson()))) {
                return false;
            }

            //  If we are less than the max chunk size, we're done
            if (strlen($_gzJson) <= static::MAX_CHUNK_SIZE) {
                return [$_gzJson];
            }
        } catch (\Exception $_ex) {
            //  Eschew failure
            return false;
        }

        return $this->prepareChunks(str_split($_gzJson, static::MAX_CHUNK_SIZE));
    }

    /**
     * Static method for packing a chunk of GELF data
     *
     * @param array  $chunks The array of chunks of gzipped JSON GELF data to prepare
     * @param string $msgId  The 8-byte message id, same for entire chunk set
     *
     * @return string[] An array of packed chunks ready to send
     */
    protected function prepareChunks($chunks, $msgId = null)
    {
        try {
            $msgId = $msgId ?: hash(ManagedDefaults::DEFAULT_ALGORITHM, microtime(true) . rand(10000, 99999), true);

            $_sequence = 0;
            $_count = count($chunks);

            if ($_count > static::MAX_CHUNKS_ALLOWED) {
                return false;
            }

            $_prepared = [];

            foreach ($chunks as $_chunk) {
                $_prepared[] = pack('CC', 30, 15) . $msgId . pack('nn', $_sequence++, $_count) . $_chunk;
            }

            return $_prepared;
        } catch (\Exception $_ex) {
            //  Failure is not an option
            return false;
        }
    }

    /**
     * @return string
     */
    public static function getHost()
    {
        return static::$host;
    }

    /**
     * @param string $host
     */
    public static function setHost($host)
    {
        static::$host = $host;
    }

    /**
     * @return int
     */
    public static function getPort()
    {
        return static::$port;
    }

    /**
     * @param int $port
     */
    public static function setPort($port)
    {
        static::$port = $port;
    }
}
