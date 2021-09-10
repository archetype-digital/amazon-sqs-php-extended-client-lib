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
    public function sendMessage(array $params)
    {
        //TODO 要質問 sendMessageの中にbucketName入れてもらうか別立てでconfigから取ってくるか
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
            $s3pointer = (string)(new S3Pointer($this->config->getBucketName(), $key, $receipt));
        }
        if (!$useSqs) {
            $params['MessageBody'] = $s3pointer;
        }
        try {
            $sendMessageResult = $this->getSqsClient()->sendMessage($params);
        } catch (AwsException $e) {
            // output error message if fails
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
        }
        return $sendMessageResult;
    }

    /**
     * {@inheritdoc}
     */
    public function sendMessageBatch(array $param)
    {
        $entries = [];
        foreach ($param['Entries'] as $entry) {
            //TODO 要質問 sendMessageの中にbucketName入れてもらうか別立てでconfigから取ってくるか
            $useSqs = $this->isNeedSqs($params['MessageBody']) || !$this->config->getBucketName();
            if (!$useSqs) {
                // First send the object to S3. The modify the message to store an S3
                // pointer to the message contents.
                $key = $this->generateUuid() . '.json';
                $receipt = $this->getS3Client()->upload(
                    $this->config->getBucketName(),
                    $key,
                    $entry['MessageBody'],
                );
                // Swap the message for a pointer to the actual message in S3.
                $s3pointer = (string)(new S3Pointer($this->config->getBucketName(), $key, $receipt));
            }
            if (!$useSqs) {
                $entry['MessageBody'] = $s3pointer;
            }
            array_push($entries, $entry);
        }
        //batch用リクエストの組み立て
        $queueUrl = $param['QueueUrl'] ?: $this->config->getSqsUrl();
        $sendMessageBatchRequest['QueueUrl'] = $queueUrl;
        $sendMessageBatchRequest['Entries'] = $entries;
        try {
            $sendMessageResults = $this->getSqsClient()->sendMessageBatch($sendMessageBatchRequest);
        } catch (AwsException $e) {
            // output error message if fails
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
        }
        return $sendMessageResults;
    }

    /**
     * {@inheritdoc}
     */
    //TODO 実際に10個とかパラメータ渡して外部結合試験が必要
    public function receiveMessage(array $param)
    {
        // Get the message from the SQS queue.
        $receiveMessageResult = $this->getSqsClient()->receiveMessage($param);
        // Detect if this is an S3 pointer message.
        if (S3Pointer::isS3Pointer($receiveMessageResult)) {
            $pointerInfo = json_decode($receiveMessageResult['Messages'][0]['Body'], true);
            $args = $pointerInfo[1];
            try {
                $s3GetObjectResult = $this->getS3Client()->getObject([
                    'Bucket' => $args['s3BucketName'],
                    'Key' => $args['s3Key']
                ])['Body']->getContents();
            } catch (S3Exception $e) {
                Log::error($e->getMessage());
                Log::error($e->getTraceAsString());
            }
            //TODO getContentsせずに返したほうが良いか質問
            return $s3GetObjectResult;
        }
        return $receiveMessageResult;
    }

    /**
     * {@inheritdoc}
     */
//TODO 要相談 S3オブジェクトをDELETEするには本文を見てS3ポインタかどうか判断する必要がある
//TODO S3オブジェクトは削除せずS3の設定（保存期間）に任すのも一つの手
//AWS標準のDeleteMessageは以下
//https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sqs-2012-11-05.html#deletemessage
//'QueueUrl' => '<string>', // REQUIRED
//'ReceiptHandle' => '<string>', // REQUIRED
    public function deleteMessage($receiveMessageResult)
    {
        if (S3Pointer::isS3Pointer($receiveMessageResult['Messages'][0]['Body'])) {
            $pointerInfo = json_decode($result['Messages'][0]['Body'], true);
            $args = $pointerInfo[1];
            // Get the S3 document with the message and return it.
            try {
                return $this->getS3Client()->deleteObject([
                    'Bucket' => $args['s3BucketName'],
                    'Key' => $args['s3Key']
                ]);
            } catch (S3Exception $e) {
                Log::error($e->getMessage());
                Log::error($e->getTraceAsString());
            }
        }

        $receiptHandle = $receiveMessageResult['Messages'][0]['ReceiptHandle'];
        $queueUrl = $this->config->getSqsUrl();

        try {
            // Delete the message from the SQS queue.
            $deleteMessageResult = $this->getSqsClient()->deleteMessage([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $receiptHandle
            ]);
        } catch (AWSException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
        }
        return $deleteMessageResult;
    }
    /**
     * {@inheritdoc}
     */

    //ここも要相談
    //    public function DeleteMessageBatch($receiveMessageResults = null)
//$result = $client->deleteMessageBatch([
//'Entries' => [ // REQUIRED
//[
//'Id' => '<string>', // REQUIRED
//'ReceiptHandle' => '<string>', // REQUIRED
//],
//    // ...
//],
//'QueueUrl' => '<string>', // REQUIRED
//]);
//    {
//        //deletebatch
//        //https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sqs-2012-11-05.html#shape-deletemessagebatchrequestentry
//        if (S3Pointer::isS3Pointer($receiveMessageResult['Messages'][0]['Body'])) {
//            $pointerInfo = json_decode($receiveMessageResult['Messages'][0]['Body'], true);
//            $args = $pointerInfo[1];
//            // Get the S3 document with the message and return it.
//            try {
//                return $this->getS3Client()->deleteObject([
//                    'Bucket' => $args['s3BucketName'],
//                    'Key' => $args['s3Key']
//                ]);
//            } catch (S3Exception $e) {
//                Log::error($e->getMessage());
//                Log::error($e->getTraceAsString());
//            }
//        }
//
//        $receiptHandle = $receiveMessageResult['Messages'][0]['ReceiptHandle'];
//        $queueUrl = $this->config->getSqsUrl();
//
//        try {
//            // Delete the message from the SQS queue.
//            $result = $this->getSqsClient()->deleteMessage([
//                'QueueUrl' => $queueUrl,
//                'ReceiptHandle' => $receiptHandle
//            ]);
//        } catch (AWSException $e) {
//            Log::error($e->getMessage());
//            Log::error($e->getTraceAsString());
//        }
//        return $result;
//    }


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
     * sqsのみ使用するかどうか判定します
     *
     * @return bool Sqsのみ使用する場合 true を返します
     *
     */
    protected function isNeedSqs($message)
    {
        //s3に送信する場合
        switch ($this->config->getSendToS3()) {
            case ConfigInterface::ALWAYS:
                $useSqs = false;
                break;

            case ConfigInterface::NEVER:
                $useSqs = true;
                break;

            case ConfigInterface::IF_NEEDED:
                $useSqs = !$this->isTooBig($message['Message']);
                break;

            default:
                $useSqs = true;
                break;
        }
        return $useSqs;
    }
}
