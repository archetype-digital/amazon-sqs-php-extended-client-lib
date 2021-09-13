<?php

namespace AwsExtended;

use Aws\ResultInterface;

class S3Pointer
{

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
     * @var \Aws\ResultInterface
     */
    protected $s3Result;


    /**
     * S3Pointer constructor.
     *
     * @param $bucketName
     *   The name of the bucket to point to.
     * @param $key
     *   The name of the document in S3.
     * @param \Aws\ResultInterface $s3Result
     *   The response from the S3 operation that saved to object.
     */
    public function __construct($bucketName, $key, ResultInterface $s3Result = null)
    {
        $this->bucketName = $bucketName;
        $this->key = $key;
        $this->s3Result = $s3Result;
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
        // Check that the second element of the 2 position array has the expected
        // keys (and no more)
        if (count(json_decode($message['Body'], true)) == 2) {
            $pointerInfo = json_decode($message['Body'], true);
            return array_key_exists('s3BucketName', $pointerInfo[1]) && array_key_exists('s3Key', $pointerInfo[1]);
        } else {
            return false;
        }
    }
}
