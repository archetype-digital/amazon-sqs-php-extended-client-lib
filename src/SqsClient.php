<?php

namespace AwsExtended;

use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\Sdk;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
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
        $useS3 = $this->isNeedS3($params);
        if ($useS3) {
            if (empty($this->config->getBucketName())) {
                throw new \Exception('The bucket name is required when using S3');
            }
            // First send the object to S3. The modify the message to store an S3
            // pointer to the message contents.
            $s3Pointer = $this->uploadToS3($params['MessageBody']);
            $params['MessageBody'] = $s3Pointer;
            if (empty($params['MessageAttributes'])) {
                $params['MessageAttributes'] = [];
            }
            $params['MessageAttributes'] = $params['MessageAttributes'] + [S3Pointer::RESERVED_ATTRIBUTE_NAME => [
                    'DataType' => "String",
                    'StringValue' => $s3Pointer
                ]];
        }

        $sendMessageResult = $this->getSqsClient()->sendMessage($params);

        return $sendMessageResult;
    }

    /**
     * {@inheritdoc}
     */
    public function sendMessageBatch(array $params): ResultInterface
    {
        foreach ($params['Entries'] as $key => $value) {
            $useS3 = $this->isNeedS3($value);
            if ($useS3) {
                if (empty($this->config->getBucketName())) {
                    throw new \Exception('The bucket name is required when using S3');
                }
                // First send the object to S3. The modify the message to store an S3
                $s3Pointer = $this->uploadToS3($value['MessageBody']);
                $params['Entries'][$key]['MessageBody'] = $s3Pointer;
                if (empty($params['Entries'][$key]['MessageAttributes'])) {
                    $params['Entries'][$key]['MessageAttributes'] = [];
                }
                $params['Entries'][$key]['MessageAttributes'] = $params['Entries'][$key]['MessageAttributes'] + [
                        S3Pointer::RESERVED_ATTRIBUTE_NAME => [
                            'DataType' => "String",
                            'StringValue' => $s3Pointer
                        ]
                    ];
            }
        }
        try {
            $sendMessageResults = $this->getSqsClient()->sendMessageBatch($params);
        } catch (AwsException $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
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
        $results = $this->getSqsClient()->receiveMessage($params);
        if (!isset($results['Messages'])) {
            return $results;
        }

        $receiveMessageResults = $results->toArray();

        foreach ($receiveMessageResults['Messages'] as $key => $value) {
            //Fetch data from s3 if reference information for s3 is contained in MessageAttributes
            if (isset($value['MessageAttributes']) && S3Pointer::isS3Pointer($value['MessageAttributes'])) {

                $pointerInfo = json_decode($value['MessageAttributes'][S3Pointer::RESERVED_ATTRIBUTE_NAME]['StringValue'], true);
                $args = $pointerInfo[1];
                try {
                    $s3Result = $this->getS3Client()->getObject([
                        'Bucket' => $args['s3BucketName'],
                        'Key' => $args['s3Key']
                    ]);

                    $s3GetObjectResult = $s3Result['Body']->getContents();

                } catch (S3Exception $e) {
                    $s3GetObjectResult = '';
                }
                $receiveMessageResults['Messages'][$key]['Body'] = $s3GetObjectResult;
                $receiveMessageResults['Messages'][$key]['ReceiptHandle'] = S3Pointer::S3_BUCKET_NAME_MARKER . $args['s3BucketName'] . S3Pointer::S3_BUCKET_NAME_MARKER .
                    S3Pointer::S3_KEY_MARKER . $args['s3Key'] . S3Pointer::S3_KEY_MARKER .
                    $receiveMessageResults['Messages'][$key]['ReceiptHandle'];
            }
        }
        return new Result($receiveMessageResults);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMessage(array $params): ResultInterface
    {
        //Delete s3 data first
        if (S3Pointer::containsS3Pointer($params['ReceiptHandle'])) {
            $receiptHandle = $this->deleteFromS3($params['ReceiptHandle']);
        } else {
            $receiptHandle = $params['ReceiptHandle'];
        }
        // Delete the message from the SQS queue.
        try {
            $deleteMessageResult = $this->getSqsClient()->deleteMessage([
                'QueueUrl' => $params['QueueUrl'],
                'ReceiptHandle' => $receiptHandle
            ]);
        } catch (AWSException $e) {
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
                $receiptHandleWithoutS3Pointer = $this->deleteFromS3($value['ReceiptHandle']);
                $params['Entries'][$key]['ReceiptHandle'] = $receiptHandleWithoutS3Pointer;
            }
        }
        // Delete the message from the SQS queue.
        try {
            $deleteMessageResult = $this->getSqsClient()->deleteMessageBatch($params);
        } catch (AWSException $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
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
    protected function isNeedS3($message)
    {
        switch ($this->config->getSendToS3()) {
            case ConfigInterface::ALWAYS:
                $useS3 = true;
                break;

            case ConfigInterface::IF_NEEDED:
                $attributeJson = !isset($message['MessageAttributes']) ? '': json_encode($message['MessageAttributes']);
                $useS3 = $this->isTooBig($message['MessageBody'] . $attributeJson);
                break;

            default:
                $useS3 = false;
                break;
        }
        return $useS3;
    }

    /**
     * upload MessageBody to S3
     *
     * @param $name
     *   The name of the method to call.
     *
     * @return string
     * 　Returns S3Pointer
     */
    protected function uploadToS3(string $messageBody): string
    {
        $s3Key = $this->generateUuid() . '.json';
        try {
            $receipt = $this->getS3Client()->upload(
                $this->config->getBucketName(),
                $s3Key,
                $messageBody,
            );
        } catch (S3Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            throw new \Exception($e->getMessage());
        }
        // Swap the message for a pointer to the actual message in S3.
        return (string)(new S3Pointer($this->config->getBucketName(), $s3Key, $receipt));
    }

    /**
     * delete File from S3
     *
     * @param $receiptHandleWithS3Pointer
     *   receiptHandle With S3Pointer
     *
     * @return string
     * 　Returns receiptHandle WithOut S3Pointer
     */
    protected function deleteFromS3($receiptHandleWithS3Pointer): string
    {
        $args = S3Pointer::getS3PointerFromReceiptHandle($receiptHandleWithS3Pointer);
        // Get the S3 document with the message and return it.
        try {
            $deleteS3Result = $this->getS3Client()->deleteObject([
                'Bucket' => $args['s3BucketName'],
                'Key' => $args['s3Key']
            ]);
        } catch (S3Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            throw new \Exception($e->getMessage());
        }
        //Remove S3 information from reciptHandle
        return S3Pointer::removeS3Pointer($receiptHandleWithS3Pointer);
    }
}
