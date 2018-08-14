<?php

namespace Drupal\qa_shot\Cli;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Exception\ClientException;
use Bunny\Message;

/**
 * Class BunnyLib.
 *
 * @package Drupal\qa_shot\Cli
 */
class BunnyLib {

  const MAX_CONNECTION_RETRIES = 5;

  protected $connectionRetries = 0;

  /**
   * Bunny client.
   *
   * @var \Bunny\Client
   */
  protected $client;

  /**
   * The configs of the channels.
   *
   * @var array
   */
  protected $channelConfigs = [];

  /**
   * The channel instances.
   *
   * @var \Bunny\Channel[]
   */
  protected $channels = [];

  /**
   * The received messages.
   *
   * @var array
   */
  protected $receivedMessages = [];

  /**
   * BunnyLib constructor.
   *
   * @param array $connection
   *   The connection config array.
   * @param array $channels
   *   The channel config array.
   *
   * @throws \Exception
   */
  public function __construct(array $connection, array $channels) {
    $this->client = new Client($connection);
    if (FALSE === $this->connect()) {
      throw new ClientException('Could not connect to the messaging queue after ' . self::MAX_CONNECTION_RETRIES . " retries.\n");
    }

    $this->channelConfigs = $channels;

    foreach ($channels as $key => $channel) {
      $this->channels[$key] = $this->client->channel();
      $this->channels[$key]->queueDeclare($channel['queue']);
      $this->channels[$key]->exchangeDeclare($channel['exchange'], 'direct');
      $this->channels[$key]->queueBind($channel['queue'], $channel['exchange'], $channel['routing_key']);
      $this->channels[$key]->qos(0, $channel['prefetch']);
    }
  }

  /**
   * Try to connect to the message queue a set amount of times.
   *
   * @return bool
   *   TRUE, if we could connect, FALSE otherwise.
   */
  protected function connect(): bool {
    try {
      $this->client->connect();
    }
    catch (\Exception $exception) {
      echo 'Connection error. ' . $exception->getMessage() . "\n";
      if ($this->connectionRetries < self::MAX_CONNECTION_RETRIES) {
        ++$this->connectionRetries;
        $waitTime = 5;
        echo "Waiting $waitTime seconds, then trying to reconnect.\n";
        sleep($waitTime);
        $this->connect();
      }
      return FALSE;
    }

    $this->connectionRetries = 0;
    return TRUE;
  }

  /**
   * Publish a message to the MQ.
   *
   * @param string $message
   *   The message.
   * @param string $channel
   *   The channel name.
   */
  public function publish($message, $channel) {
    $this->channels[$channel]->publish(
      $message,
      [],
      $this->channelConfigs[$channel]['exchange'],
      $this->channelConfigs[$channel]['routing_key']
    );
  }

  /**
   * Consume multiple messages until a timeout.
   *
   * @param int $timeout
   *   How long the client should run.
   *
   * @return array
   *   The received messages.
   */
  public function consumeMultiple(int $timeout = 10): array {
    foreach ($this->channels as $name => $channel) {
      $this->channels[$name]->consume(function (Message $message, Channel $channel) {
        echo 'A(n) ' . $message->routingKey . " message has been consumed.\n";
        $this->receivedMessages[] = $message->content;
        $channel->ack($message);
      }, $this->channelConfigs[$name]['queue']);
    }

    $this->client->run($timeout);
    return $this->receivedMessages;
  }

}
