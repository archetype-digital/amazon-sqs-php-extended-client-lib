<?php

namespace AwsExtended;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Sdk;
use Aws\Sqs\SqsClient as AwsSqsClient;
use Ramsey\Uuid\Uuid;

/**
 * Class SqsClient.
 *
 * @package AwsExtended
 */
class SqsClient implements SqsClientInterface
{

    /**
     * The AWS client to push messages to SQS.
     *
     * @var \Aws\Sqs\SqsClient
     */
    protected $sqsClient;

    /**
     * The S3 client to interact with AWS.
     *
     * @var \Aws\S3\S3Client
     */
    protected $s3Client;

    /**
     * The configuration object containing all the options.
     *
     * @var \AwsExtended\ConfigInterface
     */
    protected $config;

    /**
     * The client factory.
     *
     * @var \Aws\Sdk
     */
    protected $clientFactory;

    /**
     * SqsClient constructor.
     *
     * @param ConfigInterface $configuration
     *   The configuration object.
     *
     * @throws \InvalidArgumentException if any required options are missing or
     * the service is not supported.
     */
    public function __construct(ConfigInterface $configuration)
    {
        $this->config = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function sendMessage($message, $queue_url = null)
    {
        $use_sqs = $this->isNeedSqs($message) || !$this->config->getBucketName();
        if (!$use_sqs) {
            // First send the object to S3. The modify the message to store an S3
            // pointer to the message contents.
            $key = $this->generateUuid() . '.json';
            $receipt = $this->getS3Client()->upload(
                $this->config->getBucketName(),
                $key,
                $message['Message'],
            );
            // Swap the message for a pointer to the actual message in S3.
            $s3pointer = (string)(new S3Pointer($this->config->getBucketName(), $key, $receipt));
        }
        $queue_url = $queue_url ?: $this->config->getSqsUrl();
        if(!$use_sqs){
            $message['Message'] = $s3pointer;
        }
        return $this->getSqsClient()->sendMessage([
            'QueueUrl' => $queue_url,
            'MessageBody' => $message['Message'],
            'MessageAttributes' => $message['MessageAttributes'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function sendMessageBatch(array $messages = [], $queue_url = null)
    {
        $entries = [];
        foreach ($messages as $message) {

            $use_sqs = $this->isNeedSqs($message) || !$this->config->getBucketName();
            if (!$use_sqs) {
                // First send the object to S3. The modify the message to store an S3
                // pointer to the message contents.
                $key = $this->generateUuid() . '.json';
                $receipt = $this->getS3Client()->upload(
                    $this->config->getBucketName(),
                    $key,
                    $message['Message'],
                );
                // Swap the message for a pointer to the actual message in S3.
                $s3pointer = (string)(new S3Pointer($this->config->getBucketName(), $key, $receipt));
            }
            if(!$use_sqs){
                $message['Message'] = $s3pointer;
            }
            // Id is required
            // MessageBody is required
            $entry['Id'] = $this->generateUuid();
            $entry['MessageBody'] = $message['Message'];
            $entry['DelaySeconds'] = 9;// TODO:うえから;
            // Associative array of custom 'String' key name
            // DataType is required TODO; MessageAttributes
            $entry['MessageAttributes'] = $message['MessageAttributes'];
            array_push($entries, $entry);
        }
        //batch用リクエストの組み立て
        $queue_url = $queue_url ?: $this->config->getSqsUrl();
        $sendMessageBatchRequest['QueueUrl'] = $queue_url;
        $sendMessageBatchRequest['Entries'] = $entries;
        try {
            $receiveMessageResults = $this->getSqsClient()->sendMessageBatch($sendMessageBatchRequest);
            var_dump($receiveMessageResults);
            error_log('成功しました！');
        } catch (AwsException $e) {
            // output error message if fails
            error_log('失敗');
            error_log($e->getMessage());
        }
        return $sendMessageBatchResults;
    }

    /**
     * {@inheritdoc}
     */
    public function receiveMessage($queue_url = NULL)
    {
        $queue_url = $queue_url ?: $this->config->getSqsUrl();
        // Get the message from the SQS queue.
        $result = $this->getSqsClient()->receiveMessage([
            'QueueUrl' => $queue_url
        ]);
        // Detect if this is an S3 pointer message.
        if (S3Pointer::isS3Pointer($result)) {
            $pointerInfo = json_decode($result['Messages'][0]['Body'], true);
            $args = $pointerInfo[1];
            // Get the S3 document with the message and return it.
            //$this->getS3Client()->registerStreamWrapper();
            try {
                //$result = file_get_contents('s3://' . $args['s3BucketName'] .'/'. $args['s3Key']);
                $result = $this->getS3Client()->getObject([
                    'Bucket' => $args['s3BucketName'],
                    'Key' => $args['s3Key']
                ]);
            } catch (S3Exception $e) {
                error_log('失敗');
                error_log($e->getMessage());
            }
            return $result;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMessage($receiveMessageResult = null)
    {
        if (S3Pointer::isS3Pointer($receiveMessageResult['Messages'][0]['Body'])) {
            $pointerInfo = json_decode($result['Messages'][0]['Body'], true);
            $args = $pointerInfo[1];
            // Get the S3 document with the message and return it.
            return $this->getS3Client()->deleteObject([
                'Bucket' => $args['s3BucketName'],
                'Key' => $args['s3Key']
            ]);
        }

        $receiptHandle = $receiveMessageResult['Messages'][0]['ReceiptHandle'];
        $queue_url = $this->config->getSqsUrl();

        // Delete the message from the SQS queue.
        $result = $this->getSqsClient()->deleteMessage([
            'QueueUrl' => $queue_url,
            'ReceiptHandle' => $receiptHandle
        ]);
        return $result;
    }


    /**
     * {@inheritdoc}
     */
    public function isTooBig($message, $max_size = NULL)
    {
        // The number of bytes as the number of characters. Notice that we are not
        // using mb_strlen() on purpose.
        $max_size = $max_size ?: static::MAX_SQS_SIZE_KB;
        return strlen($message) > $max_size * 1024;
    }

    /**
     * {@inheritdoc}
     */
    public function getSqsClient()
    {
        if (!$this->sqsClient) {
            $this->sqsClient = $this->getClientFactory()->createSqs();
        }
        return $this->sqsClient;
    }

    /**
     * {@inheritdoc}
     */
    public function getS3Client()
    {
        if (!$this->s3Client) {
            $this->s3Client = $this->getClientFactory()->createS3();
        }
        return $this->s3Client;
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Routes all unknown calls to the sqsClient.
     *
     * @param $name
     *   The name of the method to call.
     * @param $arguments
     *   The arguments to use.
     *
     * @return mixed
     *   The return of the call.
     */
    function __call($name, $arguments)
    {
        // Send any unknown method calls to the SQS client.
        return call_user_func_array([$this->getSqsClient(), $name], $arguments);
    }

    /**
     * Generate a UUID v4.
     *
     * @return string
     *   The uuid.
     */
    protected function generateUuid()
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Initialize and return the SDK client factory.
     *
     * @return \Aws\Sdk
     *   The client factory.
     */
    protected function getClientFactory()
    {
        if ($this->clientFactory) {
            return $this->clientFactory;
        }
        $this->clientFactory = new Sdk($this->config->getConfig());
        return $this->clientFactory;
    }

    /**
     * sqsのみ使用するかどうか判定します
     *
     * @return bool Sqsのみ使用する場合 true を返します
     *
     */
    protected function isNeedSqs($message){
        //s3に送信する場合
        switch ($this->config->getSendToS3()) {
            case ConfigInterface::ALWAYS:
                $use_sqs = false;
                break;

            case ConfigInterface::NEVER:
                $use_sqs = true;
                break;

            case ConfigInterface::IF_NEEDED:
                $use_sqs = !$this->isTooBig($message['Message']);
                break;

            default:
                $use_sqs = true;
                break;
        }
        return $use_sqs;

    }
    /**
     * messageAttribute書式チェック
     *
     * @return bool 書式が正しい場合 true を返します
     *
     */
//    protected function checkFormatMessageAttribute($message){
//        array_key_exists('DataType', $message);
//
//        return $use_sqs;
//
//    }

}
