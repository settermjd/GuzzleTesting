<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Simple test class for showing how to test with Guzzle
 */

namespace Importer\Tests\Adapter\FreeAgent;

use Guzzle\Tests\GuzzleTestCase,
    Guzzle\Plugin\Mock\MockPlugin,
    Guzzle\Http\Message\Response,
    Guzzle\Http\Client as HttpClient,
    Guzzle\Service\Client as ServiceClient,
    Guzzle\Http\EntityBody;

class AdapterProjectImporter extends GuzzleTestCase
{
    protected $_client;

    public function setUp()
    {
        $this->_client = new ServiceClient();
        $this->setMockBasePath('./mock/responses');
        $this->setMockResponse($this->_client, array('response1'));

        $this->getServer()->enqueue(array());
    }

    public function testRequests()
    {
        // The following request will get the mock response from the plugin in FIFO order
        $request = $this->_client->get('https://api.freeagent.com/v2/invoices');
        $request->getQuery()->set('view', 'recent_open_or_overdue');
        $response = $request->send();

        $this->assertContainsOnly($request, $this->getMockedRequests());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('AmazonS3', $response->getServer());
        $this->assertEquals('application/xml', $response->getContentType());
    }


    public function testAnotherRequest()
    {
        $mockResponse = new Response(200);
        $mockResponseBody = EntityBody::factory(fopen('./mock/bodies/body1.txt', 'r+'));
        $mockResponse->setBody($mockResponseBody);
        $mockResponse->setHeaders(array(
            "Host" => "httpbin.org",
            "User-Agent" => "curl/7.19.7 (universal-apple-darwin10.0) libcurl/7.19.7 OpenSSL/0.9.8l zlib/1.2.3",
            "Accept" => "application/json",
            "Content-Type" => "application/json"
        ));
        $plugin = new MockPlugin();
        $plugin->addResponse($mockResponse);
        $client = new HttpClient();
        $client->addSubscriber($plugin);

        $request = $client->get('https://api.freeagent.com/v2/invoices');
        $response = $request->send();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(in_array('Host', array_keys($response->getHeaders()->toArray())));
        $this->assertTrue($response->hasHeader("User-Agent"));
        $this->assertCount(4, $response->getHeaders());
        $this->assertSame($mockResponseBody->getSize(), $response->getBody()->getSize());
        $this->assertSame(1, count(json_decode($response->getBody(true))->invoices));
    }

    public function testWithRemoteServer()
    {
        $mockProperties = array(
            array(
                'header' => './mock/headers/header1.txt',
                'body' => './mock/bodies/body1.txt',
                'status' => 200
            )
        );
        $mockResponses = array();

        foreach($mockProperties as $property) {
            $mockResponse = new Response($property['status']);
            $mockResponseBody = EntityBody::factory(fopen($property['body'], 'r+'));
            $mockResponse->setBody($mockResponseBody);
            $headers = explode("\n", file_get_contents($property['header'], true));
            foreach($headers as $header) {
                list($key, $value) = explode(': ', $header);
                $mockResponse->addHeader($key, $value);
            }
            $mockResponses[] = $mockResponse;
        }

        $this->getServer()->enqueue($mockResponses);

        $client = new HttpClient();
        $client->setBaseUrl($this->getServer()->getUrl());
        $request = $client->get();
        $request->getQuery()->set('view', 'recent_open_or_overdue');
        $response = $request->send();

        $this->assertCount(5, $response->getHeaders());
        $this->assertEmpty($response->getContentDisposition());
        $this->assertSame('HTTP', $response->getProtocol());
    }

    public function tearDown()
    {

    }
}