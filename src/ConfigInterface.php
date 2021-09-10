<?php

namespace AwsExtended;

interface ConfigInterface
{

    public const IF_NEEDED = 'IF_NEEDED';
    public const ALWAYS = 'ALWAYS';
    public const NEVER = 'NEVER';

  /**
   * Gets the config.
   *
   * @return array
   */
    public function getConfig();

  /**
   * Gets the bucketName.
   *
   * @return string
   */
    public function getBucketName();

  /**
   * Gets the sqsUrl.
   *
   * @return null|string
   */
    public function getSqsUrl();

  /**
   * Gets the sendToS3.
   *
   * @return null|string
   */
    public function getSendToS3();
}
