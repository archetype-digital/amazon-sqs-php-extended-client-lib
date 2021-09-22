<?php

namespace AwsExtended;

class Config implements ConfigInterface
{

    /**
     * The configuration array to send to the Aws client.
     *
     * @var array
     */
    protected $config;

    /**
     * The name of the alternative S3 bucket.
     *
     * @var string
     */
    protected $bucketName;

    /**
     * Indicates when to send messages to S3.
     *
     * Allowed values are: ALWAYS, NEVER, IF_NEEDED.
     *
     * @var null|string
     */
    protected $sendToS3 = self::IF_NEEDED;

    /**
     * Config constructor.
     *
     * @param array $config
     *   Client configuration arguments. The same arguments passed to AwsClient.
     * @param string $bucketName
     *   The SQS url to push messages to.
     * @param string $sendToS3
     *   Code that indicates when to send data to S3. Allowed values are:
     *     - IF_NEEDED
     *     - ALWAYS
     *     - NEVER
     *
     * @throws \InvalidArgumentException if any required options are missing or
     * the service is not supported.
     */
    public function __construct(array $config, $bucketName, $sendToS3 = null)
    {
        $this->config = $config;
        $this->bucketName = $bucketName;
        if ($sendToS3) {
            if (!in_array($sendToS3, [self::ALWAYS, self::IF_NEEDED, self::NEVER])) {
                throw new \InvalidArgumentException(sprintf('Invalid code for send_to_s3: %s.', $sendToS3));
            }
            $this->sendToS3 = $sendToS3;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function getBucketName()
    {
        return $this->bucketName;
    }

    /**
     * {@inheritdoc}
     */
    public function getSendToS3()
    {
        return $this->sendToS3;
    }
}
