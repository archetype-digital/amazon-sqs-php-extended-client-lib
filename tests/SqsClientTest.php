<?php

namespace AwsExtended;

use Aws\Result;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient as AwsSqsClient;
use Prophecy\Argument;
use Mockery;
use Ramsey\Uuid\Uuid;

/**
 * Class SqsClientTest.
 *
 * @package AwsExtended
 *
 * @coversDefaultClass \AwsExtended\SqsClient
 */
class SqsClientTest extends \Tests\TestCase
{

    /**
     * @var \AwsExtended\SqsClientInterface
     */
    protected $client;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @covers ::sendMessage
     */
    public function testSendMessage()
    {
        $awsConfig = ['credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => env('AWS_SDK_VERSION'),
        ];
        $bucketName = env('S3_BUCKET_NAME');
        $sendToS3 = 'ALWAYS';
        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);

        $params['MessageBody'] = json_encode(range(1, 257 * 1024));
        $params['MessageAttributes'] = [
            "Title" => [
                'DataType' => "String",
                'StringValue' => "The Hitchhiker's Guide to the Galaxy"
            ],
            "Author" => [
                'DataType' => "String",
                'StringValue' => "Douglas Adams."
            ],
            "WeeksOn" => [
                'DataType' => "Number",
                'StringValue' => "6"
            ]
        ];
        $params['QueueUrl'] = env('SQS_URL');
        $sendMessageResult = $sqsClient->sendMessage($params);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
        $this->assertMatchesRegularExpression('[[0-9a-zA-Z]{8}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{12}]', $sendMessageResult['MessageId']);
    }

    /**
     * @covers ::sendMessage
     */
    public function testSendMessageUseMock()
    {

        $config = $this->getMockBuilder(Config::class)
            ->onlyMethods(['getSendToS3', 'getBucketName'])
            ->setConstructorArgs([[], ''])
            ->getMock();

        $config->method('getSendToS3')->willReturn(ConfigInterface::NEVER);
        $config->method('getBucketName')->willReturn(null);

        $client = $this->getMockBuilder(SqsClient::class)
            ->onlyMethods(['getSqsClient'])
            ->setConstructorArgs([$config])
            ->getMock();

        $awsSqsClient = $this->prophesize(AwsSqsClient::class);

        $client->method('getSqsClient')->willReturn($awsSqsClient);

    }

    /**
     * @covers ::sendMessage
     */
    public function testSendMessageNoUseS3()
    {
        //$Config = \Mockery::mock(AwsExtended\Config::class);
        //$configuration = new $Config($config, $bucketName, $sqsUrl, $sendToS3);
        $awsConfig = ['credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => env('AWS_SDK_VERSION'),
        ];
        $bucketName = env('S3_BUCKET_NAME');
        $sendToS3 = 'IF_NEEDED';

        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);

        $params['MessageBody'] = json_encode('this is short message aaaaa');
        $params['MessageAttributes'] = [
            "Title" => [
                'DataType' => "String",
                'StringValue' => "The Hitchhiker's Guide to the Galaxy"
            ],
            "Author" =>[
                'DataType' => "String",
                'StringValue' => "Douglas Adams."
            ],
            "WeeksOn" => [
                'DataType' => "Number",
                'StringValue' => "6"
            ]
        ];
        $params['QueueUrl'] = env('SQS_URL');
        $sendMessageResult = $sqsClient->sendMessage($params);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
        $this->assertMatchesRegularExpression('[[0-9a-zA-Z]{8}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{12}]', $sendMessageResult['MessageId']);
    }
    /**
     * @covers ::sendMessage
     */
    public function testSendMessageNoUseS3LimitValue()
    {
        $awsConfig = ['credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => env('AWS_SDK_VERSION'),
        ];
        $bucketName = env('S3_BUCKET_NAME');
        $sendToS3 = 'IF_NEEDED';

        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);
        $messageLength = ($sqsClient::MAX_SQS_SIZE_KB*1024);

        $params['MessageAttributes'] = [
            "Title" => [
                'DataType' => "String",
                'StringValue' => "The Hitchhiker's Guide to the Galaxy"
            ],
        ];

        $params['MessageBody'] = str_repeat('b', $messageLength-strlen(json_encode($params['MessageAttributes'])));

        $params['QueueUrl'] = env('SQS_URL');
        $sendMessageResult = $sqsClient->sendMessage($params);
//        $this->assertInternalType('AWS/ResultInterface', $sendMessageResult);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
        $this->assertMatchesRegularExpression('[[0-9a-zA-Z]{8}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{12}]', $sendMessageResult['MessageId']);
    }

    /**
     * @covers ::sendMessage
     */
    public function testSendMessageUseS3LimitValue()
    {
        $awsConfig = ['credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => env('AWS_SDK_VERSION'),
        ];
        $bucketName = env('S3_BUCKET_NAME');
        $sendToS3 = 'IF_NEEDED';

        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);
        $messageLength = ($sqsClient::MAX_SQS_SIZE_KB*1024)+1;

        $params['MessageAttributes'] = [
            "Title" => [
                'DataType' => "String",
                'StringValue' => "The Hitchhiker's Guide to the Galaxy"
            ],
        ];

        $params['MessageBody'] = str_repeat('z', $messageLength-strlen(json_encode($params['MessageAttributes'])));

        $params['QueueUrl'] = env('SQS_URL');
        $sendMessageResult = $sqsClient->sendMessage($params);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
        $this->assertMatchesRegularExpression('[[0-9a-zA-Z]{8}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{12}]', $sendMessageResult['MessageId']);
    }

    /**
     * @covers ::sendMessage
     */
    public function testSendMessageBatch()
    {
        $awsConfig = ['credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => env('AWS_SDK_VERSION'),
        ];
        $bucketName = env('S3_BUCKET_NAME');
        $sendToS3 = 'IF_NEEDED';

        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);

        $entry = [];
        for ($i = 0; $i <= 5; $i++) {
            //Create two types of data with and without s3
            $entry[$i]['Id'] = Uuid::uuid4()->toString();
            if ($i % 2 == 0) {
                $entry[$i]['MessageBody'] = json_encode(range(1, 257 * 1024));
            } else {
                $entry[$i]['MessageBody'] = json_encode('this is short message_' . $i);
            }

            $entry[$i]['MessageAttributes'] = [
                "Title" => [
                    'DataType' => "String",
                    'StringValue' => "The Hitchhiker's Guide to the Galaxy"
                ],
                "Author" => [
                    'DataType' => "String",
                    'StringValue' => "Douglas Adams."
                ],
                "WeeksOn" => [
                    'DataType' => "Number",
                    'StringValue' => "6"
                ]
            ];

        }
        $params['Entries'] = $entry;
        $params['QueueUrl'] = env('SQS_URL');
        $sendMessageResult = $sqsClient->sendMessageBatch($params);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
    }


    /**
     * @covers ::receiveMessage
     */
    public function testRecieveMessage()
    {
        $awsConfig = ['credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => env('AWS_SDK_VERSION'),
        ];
        $bucketName = env('S3_BUCKET_NAME');
        $sendToS3 = 'IF_NEEDED';

        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);
        $params = ['MaxNumberOfMessages' => 10,
            'QueueUrl' => env('SQS_URL'),
            'VisibilityTimeout' => 9,
            'MessageAttributeNames' => [S3Pointer::RESERVED_ATTRIBUTE_NAME],
            'WaitTimeSeconds' => 20];

        $receiveMessageResult = $sqsClient->receiveMessage($params);
        $this->assertEquals(200, $receiveMessageResult['@metadata']['statusCode']);
    }

    public function testDeleteMessage()
    {
        $awsConfig = ['credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => env('AWS_SDK_VERSION'),
        ];
        $bucketName = env('S3_BUCKET_NAME');
        $sendToS3 = 'IF_NEEDED';

        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);
        $queueUrl = env('SQS_URL');

        $receiptHandle = 'longvalue';
        $params = ['QueueUrl'=>$queueUrl,'ReceiptHandle'=>$receiptHandle];

        $deleteMessageResult = $sqsClient->deleteMessage($params);
        $this->assertEquals(200, $deleteMessageResult['@metadata']['statusCode']);
    }

    public function testDeleteMessageNoUseS3()
    {
        $awsConfig = ['credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => env('AWS_SDK_VERSION'),
        ];
        $bucketName = env('S3_BUCKET_NAME');
        $sendToS3 = 'IF_NEEDED';

        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);
        $queueUrl = env('SQS_URL');

        $receiptHandle = 'long value';
        $deleteMessageResult = $sqsClient->deleteMessage($queueUrl, $receiptHandle);
        $this->assertEquals(200, $deleteMessageResult['@metadata']['statusCode']);
    }


    /**
     * @covers ::deleteMessageBatch
     */
    public function testDeleteMessageBatch()
    {
        $awsConfig = ['credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => env('AWS_SDK_VERSION'),
        ];
        $bucketName = env('S3_BUCKET_NAME');
        $sendToS3 = 'IF_NEEDED';

        $entries = [
            [
                'Id' => '2b22d04d-cefa-4484-b0e5-9edada7c9a79',
                'ReceiptHandle' => 'long value',
            ],
            [
                'Id' => '4f90ffe4-36e3-4d43-b31f-46dce859f679',
                'ReceiptHandle' => 'long value',
            ],
        ];
        $queueUrl = env('SQS_URL');

        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);
        $params = ['Entries' => $entries, 'QueueUrl' => $queueUrl];
        $deleteMessageResult = $sqsClient->deleteMessageBatch($params);
        $this->assertEquals(200, $deleteMessageResult['@metadata']['statusCode']);
    }
}
