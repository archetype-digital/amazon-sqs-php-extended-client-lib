<?php

namespace AwsExtended;

use Aws\ResultInterface;

class S3Pointer
{
    /**
     * Marker to store s3BucketName
     */
    public const S3_BUCKET_NAME_MARKER = '-..s3BucketName..-';

    /**
     * Marker to store s3Key
     */
    public const S3_KEY_MARKER = '-..s3Key..-';

    /**
     * Regular expressions that SQS can accept.
     */
    public const RECEIPT_HANDLER_MATCHER = '/^' . S3Pointer::S3_BUCKET_NAME_MARKER . '(.*)' . S3Pointer::S3_BUCKET_NAME_MARKER . S3Pointer::S3_KEY_MARKER . '(.*)' . S3Pointer::S3_KEY_MARKER . '/';

    /**
     * MessageAttribute title for sqs message set S3 infomation
     */
    public const RESERVED_ATTRIBUTE_NAME = 'ExtendedPayloadSize';

    /**
     * The name of the bucket.
     *
     * @var string
     */
    protected $bucketName;

    /**
     * The ID of the S3 document.
     *
     * @var
     */
    protected $key;

    /**
     * The transaction response.
     *
     * @var ResultInterface
     */
    protected $s3Result;


    /**
     * S3Pointer constructor.
     *
     * @param $bucketName
     *   The name of the bucket to point to.
     * @param $key
     *   The name of the document in S3.
     * @param ResultInterface $s3Result
     *   The response from the S3 operation that saved to object.
     */
    public function __construct($bucketName, $key, ResultInterface $s3Result = null)
    {
        $this->bucketName = $bucketName;
        $this->key = $key;
        $this->s3Result = $s3Result;
    }

    /**
     * Checks if a result response is an S3 pointer.
     *
     * @param array $message
     *   The result from the SQS request.
     *
     * @return bool
     *   TRUE if the result corresponds to an S3 pointer. FALSE otherwise.
     */
    public static function isS3Pointer(array $message): bool
    {
        if (!isset($message['MessageAttributes'])) {
            return false;
        }
        $messageAttributes = $message['MessageAttributes'];
        // Check that the second element of the 2 position array has the expected
        if (isset($messageAttributes[S3Pointer::RESERVED_ATTRIBUTE_NAME])) {
            $body = json_decode($message['Body'], true);
            if (empty($body)) {
                return false;
            }
            return array_key_exists('s3BucketName', $body) && array_key_exists('s3Key', $body);
        }

        return false;
    }

    /**
     * Checks if a receiptHandle contain an S3 pointer.
     *
     * @param string $receiptHandle
     *   The result from the SQS request.
     *
     * @return bool
     *   TRUE if the result corresponds to an S3 pointer. FALSE otherwise.
     */
    public static function containsS3Pointer(string $receiptHandle): bool
    {
        return preg_match(S3Pointer::RECEIPT_HANDLER_MATCHER, $receiptHandle);
    }

    /**
     * get S3 pointer from receiptHandle.
     *
     * @param string $receiptHandle
     *   The result from the SQS request.
     *
     * @return array
     *   S3 pointer. contains Bucket,Key
     */
    public static function getS3PointerFromReceiptHandle(string $receiptHandle): array
    {
        preg_match(S3Pointer::RECEIPT_HANDLER_MATCHER, $receiptHandle, $s3Pointer);
        $s3Pointer = ['s3BucketName' => $s3Pointer['1'], 's3Key' => $s3Pointer['2']];
        return $s3Pointer;
    }

    /**
     * remove S3 pointer from receiptHandle.
     *
     * @param string $receiptHandle
     *   The result from the SQS request.
     *
     * @return string
     *   receiptHandle without S3 pointer.
     */
    public static function removeS3Pointer(string $receiptHandle): string
    {
        if (preg_match(S3Pointer::RECEIPT_HANDLER_MATCHER, $receiptHandle)) {
            return preg_replace(S3Pointer::RECEIPT_HANDLER_MATCHER, '', $receiptHandle);
        } else {
            return $receiptHandle;
        }
    }

    /**
     * Generates a JSON serialization of the pointer.
     *
     * @return string
     *   The string version of the pointer.
     */
    public function __toString()
    {
        $infoKeys = ['@metadata', 'ObjectUrl'];
        $metadata = $this->s3Result ?
            array_map([$this->s3Result, 'get'], $infoKeys) :
            [];
        $pointer = ['s3BucketName' => $this->bucketName, 's3Key' => $this->key];
        return json_encode([$metadata, $pointer]);
    }
}
