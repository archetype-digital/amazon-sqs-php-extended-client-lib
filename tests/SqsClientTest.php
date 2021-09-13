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
//        $this->assertInternalType('AWS/ResultInterface', $sendMessageResult);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
        $this->assertMatchesRegularExpression('[[0-9a-zA-Z]{8}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{12}]', $sendMessageResult['MessageId']);

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

        //sqsClientに渡すconfigの組み立て
        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);

        $params['MessageBody'] = json_encode('this is short message');
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
        //送信
        $params['QueueUrl'] = env('SQS_URL');
        $sendMessageResult = $sqsClient->sendMessage($params);
//        $this->assertInternalType('AWS/ResultInterface', $sendMessageResult);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
        $this->assertMatchesRegularExpression('[[0-9a-zA-Z]{8}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{12}]', $sendMessageResult['MessageId']);
    }

    /**
     * @covers ::sendMessage
     */
    public function testSendMessageBatch()
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

        //sqsClientに渡すconfigの組み立て
        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);

        $entry = [];
        for ($i = 0; $i <= 3; $i++) {
            $entry[$i]['Id'] =  Uuid::uuid4()->toString();
            $entry[$i]['MessageBody'] = json_encode('this is short message_' . $i);
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
//        $this->assertInternalType('AWS/ResultInterface', $sendMessageResult);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
    }


    /**
     * @covers ::receiveMessage
     */
    public function testRecieveMessage()
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

        //sqsClientに渡すconfigの組み立て
        $configuration = new Config($awsConfig, $bucketName, $sendToS3);
        $sqsClient = new SqsClient($configuration);
        $params = ['MaxNumberOfMessages' => 9,
                    'QueueUrl' => env('SQS_URL'),
                    'VisibilityTimeout' => 9,
                    'WaitTimeSeconds' => 9];

        $receiveMessageResult = $sqsClient->receiveMessage($params);
        $this->assertEquals(200, $receiveMessageResult['@metadata']['statusCode']);
//        $this->assertEquals([
//            'QueueUrl' => 'bar',
//            'MessageBody' => '[[{"Lorem":"lorem","Ipsum":"1234-fake-uuid.json"},"fake_object_url"],{"s3BucketName":"lorem","s3Key":"1234-fake-uuid.json"}]',
//        ], $receiveMessageResult);
    }

    /**
     * @covers ::deleteMessage
     */
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
        $sqsUrl = env('SQS_URL');
        $sendToS3 = 'IF_NEEDED';

        //sqsClientに渡すconfigの組み立て
        $configuration = new Config($awsConfig, $bucketName, $sqsUrl, $sendToS3);
        $sqsClient = new SqsClient($configuration);
        $params = ['MaxNumberOfMessages' => 9,
            'QueueUrl' => env('SQS_URL'),
            'VisibilityTimeout' => 9,
            'WaitTimeSeconds' => 9];

        $receiveMessageResult = $sqsClient->receiveMessage($params);


        $deleteMessageResult = $sqsClient->deleteMessage($receiveMessageResult);
        $this->assertEquals(200, $receiveMessageResult['@metadata']['statusCode']);
//        $this->assertEquals([
//            'QueueUrl' => 'bar',
//            'MessageBody' => '[[{"Lorem":"lorem","Ipsum":"1234-fake-uuid.json"},"fake_object_url"],{"s3BucketName":"lorem","s3Key":"1234-fake-uuid.json"}]',
//        ], $deleteMessageResult);
    }
//
//    /**
//     * @param ConfigInterface $config
//     *   The configuration for the client.
//     *
//     * @return \PHPUnit_Framework_MockObject_MockObject
//     */
//    protected function getClientMock(ConfigInterface $config)
//    {
//        // Mock the AWS clients.
//        $client = $this->getMockBuilder(SqsClient::class)
//            ->setMethods(['getS3Client', 'getSqsClient', 'generateUuid'])
//            ->setConstructorArgs([$config])
//            ->getMock();
//
//        $client->method('generateUuid')->willReturn('1234-fake-uuid');
//        $s3_client = $this->prophesize(S3Client::class);
//        $client->method('getS3Client')->willReturn($s3_client->reveal());
//        $s3_client->upload(Argument::type('string'), Argument::type('string'), Argument::type('string'))
//            ->will(function ($args) {
//                return new Result([
//                    '@metadata' => ['Lorem' => $args[0], 'Ipsum' => $args[1]],
//                    'ObjectUrl' => 'fake_object_url',
//                ]);
//            });
//        $sqs_client = $this->prophesize(AwsSqsClient::class);
//        $sqs_client->sendMessage(Argument::type('array'))->willReturnArgument(0);
//        $client->method('getSqsClient')->willReturn($sqs_client->reveal());
//
//        return $client;
//    }
//
//    /**
//     * @covers ::isTooBig
//     * @dataProvider isTooBigProvider
//     */
//    public function testIsTooBig($message, $is_too_big)
//    {
//        $client = new SqsClient(new Config(
//            [],
//            'lorem',
//            'ipsum',
//            ConfigInterface::NEVER
//        ));
//        // Data with more than 2 bytes is considered too big.
//        $this->assertSame($is_too_big, $client->isTooBig($message, 2 / 1024));
//    }
//
//    /**
//     * Test data for the isTooBig test.
//     *
//     * @return array
//     *   The data for the test method.
//     */
//    public function isTooBigProvider()
//    {
//        return [
//            ['', FALSE],
//            [NULL, FALSE],
//            ['a', FALSE],
//            ['aa', FALSE],
//            ['aaa', TRUE],
//            [TRUE, FALSE],
//            [FALSE, FALSE],
//            // Multi byte single characters.
//            ['😱', TRUE], // 3 bytes character.
//            ['añ', TRUE],  // 2 bytes character.
//        ];
//    }

}
