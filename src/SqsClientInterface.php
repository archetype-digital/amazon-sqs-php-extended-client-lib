<?php

namespace AwsExtended;

use Aws\S3\S3Client;
use Aws\ResultInterface;
use Aws\AwsException;
use Aws\Sqs\SqsClient as AwsSqsClient;
use Ramsey\Uuid\Uuid;

interface SqsClientInterface
{
    /**
     * The maximum size that SQS can accept.
     */
    public const MAX_SQS_SIZE_KB = 256;

    /**
     * Sends the message to SQS.
     *
     * If the message cannot fit SQS, it gets stored in S3 and a pointer is sent
     * to SQS.
     *
     * @param array $params containing a [Message, MessageAttributes]
     *
     * @return \Aws\ResultInterface
     *   The result of the transaction.
     */
    public function sendMessage(array $params): ResultInterface;

    /**
     * Sends the message to SQS.
     *
     * Delivers up to ten messages to the specified queue. This is a batch
     * version of SendMessage. The result of the send action on each message is
     * reported individually in the response. Uploads message payloads to Amazon
     * S3 when necessary.
     *
     * IMPORTANT:Because the batch request can result in a combination
     * of successful and unsuccessful actions, you should check for batch errors
     * even when the call returns an HTTP status code of 200.
     *
     * @param array $params contains array Entries ,string QueueUrl
     *   The SQS queue. Defaults to the one configured in the client.
     *
     * @return array \Aws\ResultInterface
     *   The result of the transaction.
     */

    public function sendMessageBatch(array $params): ResultInterface;


    /**
     * Gets a message from the queue.
     *
     * @param array $params containing a ['AttributeNames',
     *                                  'MessageAttributeNames',
     *                                  'MaxNumberOfMessages',
     *                                  'VisibilityTimeout',
     *                                  'WaitTimeSeconds',
     *                                  'QueueUrl'];
     *   The SQS queue. Defaults to the one configured in the client.
     *
     * @return \Aws\ResultInterface
     *   The message
     */
    public function receiveMessage(array $params): ResultInterface;

    /**
     * Delete a message from the queue.
     *
     * @param string $queueUrl
     *   The SQS queue. Defaults to the one configured in the client.
     *
     * @param string $receiptHandle
     *   ReceiptHandle is associated with a specific instance of receiving a message
     * @return \Aws\ResultInterface
     *   The message
     */
    public function deleteMessage(string $queueUrl, string $receiptHandle): ResultInterface;

    /**
     * Delete a message from the queue.
     *
     * @param array $params containing array $entries, string $queueUrl
     *   The SQS queue. Defaults to the one configured in the client.
     *
     *
     * @return \Aws\ResultInterface
     *   The message
     */
    public function deleteMessageBatch(array $params): ResultInterface;

    /**
     * Checks if a message is too big to be sent to SQS.
     *
     * @param $message
     *   The message to check.
     * @param int $maxSize
     *   The (optional) max considered for SQS.
     *
     * @return bool
     *   TRUE if the message is too big.
     */
    public function isTooBig($message, $maxSize = null);

    /**
     * Gets the underlying SQS client.
     *
     * @return \Aws\Sqs\SqsClient
     *   The underlying SQS client.
     */
    public function getSqsClient();

    /**
     * Gets the underlying S3 client.
     *
     * @return \Aws\S3\S3Client
     *   The underlying S3 client.
     */
    public function getS3Client();

    /**
     * Sets the config.
     *
     * @param \AwsExtended\ConfigInterface $config
     */
    public function setConfig($config);
}
