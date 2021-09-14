<?php

namespace AwsExtended;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Sdk;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
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
    public function sendMessage(array $params): ResultInterface
    {
        $useSqs = $this->isNeedSqs($params['MessageBody']) || !$this->config->getBucketName();
        if (!$useSqs) {
            // First send the object to S3. The modify the message to store an S3
            // pointer to the message contents.
            $key = $this->generateUuid() . '.json';
            $receipt = $this->getS3Client()->upload(
                $this->config->getBucketName(),
                $key,
                $params['MessageBody'],
            );
            // Swap the message for a pointer to the actual message in S3.
            $s3Pointer = (string)(new S3Pointer($this->config->getBucketName(), $key, $receipt));
            $params['MessageBody'] = $s3Pointer;
            $params['MessageAttributes'] = $params['MessageAttributes'] + ["S3Pointer" => [
                    'DataType' => "String",
                    'StringValue' => $s3Pointer
                ]];
        }
        try {
            $sendMessageResult = $this->getSqsClient()->sendMessage($params);
        } catch (AwsException $e) {
            // output error message if fails
            \Log::error($e->getMessage());
            \Log::error($e->getTraceAsString());
        }
        return $sendMessageResult;
    }

    /**
     * {@inheritdoc}
     */
    public function sendMessageBatch(array $params): ResultInterface
    {
        foreach ($params['Entries'] as $key => $value) {
            $useSqs = $this->isNeedSqs($value['MessageBody']) || !$this->config->getBucketName();
            if (!$useSqs) {
                // First send the object to S3. The modify the message to store an S3
                // pointer to the message contents.
                $s3Key = $this->generateUuid() . '.json';
                $receipt = $this->getS3Client()->upload(
                    $this->config->getBucketName(),
                    $s3Key,
                    $value['MessageBody'],
                );
                // Swap the message for a pointer to the actual message in S3.
                $s3Pointer = (string)(new S3Pointer($this->config->getBucketName(), $s3Key, $receipt));
                $params['Entries'][$key]['MessageBody'] = $s3Pointer;
                $params['Entries'][$key]['MessageAttributes'] = $params['Entries'][$key]['MessageAttributes'] + [
                        "S3Pointer" => [
                            'DataType' => "String",
                            'StringValue' => $s3Pointer
                        ]
                    ];
            } else {
                continue;
            }
        }
        try {
            $sendMessageResults = $this->getSqsClient()->sendMessageBatch($params);
        } catch (AwsException $e) {
            \Log::error($e->getMessage());
            \Log::error($e->getTraceAsString());
            throw new \Exception($e->getMessage());
        }
        return $sendMessageResults;
    }

    /**
     * {@inheritdoc}
     */
    public function receiveMessage(array $params): ResultInterface
    {
        // Get the message from the SQS queue.
        $receiveMessageResults = $this->getSqsClient()->receiveMessage($params);
        if (empty($receiveMessageResults)) {
            throw new \Exception('receiveMessageResults must be contains objects');
        }

        foreach ($receiveMessageResults['Messages'] as $key => $value) {
            //Fetch data from s3 if reference information for s3 is contained in MessageAttributes
            if (isset($value['MessageAttributes']) && S3Pointer::isS3Pointer($value['MessageAttributes'])) {
                $pointerInfo = json_decode($value['MessageAttributes']['S3Pointer']['StringValue'], true);
                $args = $pointerInfo[1];
                try {
                    $s3GetObjectResult = $this->getS3Client()->getObject([
                        'Bucket' => $args['s3BucketName'],
                        'Key' => $args['s3Key']
                    ])['Body']->getContents();
                } catch (S3Exception $e) {
                    \Log::error($e->getMessage());
                    \Log::error($e->getTraceAsString());
                    throw new \Exception($e->getMessage());
                }
                $receiveMessageResults['Messages'][$key]['Body'] = $s3GetObjectResult;
                $receiveMessageResults['Messages'][$key]['ReceiptHandle'] = S3Pointer::S3_BUCKET_NAME_MARKER . $args['s3BucketName'] .
                    S3Pointer::S3_KEY_MARKER . $args['s3Key'] .
                    $receiveMessageResults['Messages'][$key]['ReceiptHandle'];
            }
        }
        return $receiveMessageResults;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMessage(string $queueUrl, string $receiptHandle): ResultInterface
    {
        if (S3Pointer::containsS3Pointer($receiptHandle)) {
            $args = S3Pointer::getS3PointerFromReceiptHandle($receiptHandle);
            // Get the S3 document with the message and return it.
            try {
                $deleteS3Result = $this->getS3Client()->deleteObject([
                    'Bucket' => $args['s3BucketName'],
                    'Key' => $args['s3Key']
                ]);
            } catch (S3Exception $e) {
                \Log::error($e->getMessage());
                \Log::error($e->getTraceAsString());
                throw new \Exception($e->getMessage());
            }
            //Remove S3 information from reciptHandle
            $receiptHandle = S3Pointer::removeS3Pointer($receiptHandle);
        }
        try {
            // Delete the message from the SQS queue.
            $deleteMessageResult = $this->getSqsClient()->deleteMessage([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $receiptHandle
            ]);
        } catch (AWSException $e) {
            \Log::error($e->getMessage());
            \Log::error($e->getTraceAsString());
            throw new \Exception($e->getMessage());
        }
        return $deleteMessageResult;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMessageBatch(array $params): ResultInterface
    {
        //Delete s3 data first
        foreach ($params['Entries'] as $key => $value) {
            if (S3Pointer::containsS3Pointer($value['ReceiptHandle'])) {
                $args = S3Pointer::getS3PointerFromReceiptHandle($value['ReceiptHandle']);
                // Get the S3 document with the message and return it.
                try {
                    $deleteS3Result = $this->getS3Client()->deleteObject([
                        'Bucket' => $args['s3BucketName'],
                        'Key' => $args['s3Key']
                    ]);
                } catch (S3Exception $e) {
                    \Log::error($e->getMessage());
                    \Log::error($e->getTraceAsString());
                    throw new \Exception($e->getMessage());
                }
                //Remove S3 information from reciptHandle
                $params['Entries'][$key]['ReceiptHandle'] = S3Pointer::removeS3Pointer($value['ReceiptHandle']);
            }
        }

        try {
            // Delete the message from the SQS queue.
            $deleteMessageResult = $this->getSqsClient()->deleteMessageBatch($params);
        } catch (AWSException $e) {
            \Log::error($e->getMessage());
            \Log::error($e->getTraceAsString());
            throw new \Exception($e->getMessage());
        }
        return $deleteMessageResult;
    }

    /**
     * {@inheritdoc}
     */
    public function isTooBig($message, $maxSize = null)
    {
        // The number of bytes as the number of characters. Notice that we are not
        // using mb_strlen() on purpose.
        $maxSize = $maxSize ?: static::MAX_SQS_SIZE_KB;
        return strlen($message) > $maxSize * 1024;
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
    public function __call($name, $arguments)
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
     * Determines whether only sqs is used
     *
     * @return bool
     * 　Returns true if only Sqs are used
     */
    protected function isNeedSqs($messageBody)
    {
        switch ($this->config->getSendToS3()) {
            case ConfigInterface::ALWAYS:
                $useSqs = false;
                break;

            case ConfigInterface::NEVER:
                $useSqs = true;
                break;

            case ConfigInterface::IF_NEEDED:
                $useSqs = !$this->isTooBig($messageBody);
                break;

            default:
                $useSqs = true;
                break;
        }
        return $useSqs;
    }
}
