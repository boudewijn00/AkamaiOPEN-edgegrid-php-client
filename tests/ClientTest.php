<?php
/**
 *
 * Original Author: Davey Shafik <dshafik@akamai.com>
 *
 * For more information visit https://developer.akamai.com
 *
 * Copyright 2014 Akamai Technologies, Inc. All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use Akamai\Open\EdgeGrid\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Test for Akamai\Open\EdgeGrid\Client
 *
 * @author Davey Shafik <dshafik@akamai.com>
 * @since PHP 5.6
 * @version 1.0
 */
class ClientTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param $name
     * @param $options
     * @param $request
     * @param $result
     * @dataProvider makeAuthHeaderProvider
     */
    public function testMakeAuthHeader($name, $options, $request, $result)
    {
//        $this->setName($name);
        
        // Mock the response, we don't care about it
        $mock = new MockHandler([new Response(200)]);
        $container = [];
        $history = Middleware::history($container);
        
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        
        $timestamp = $this->prophesize('\Akamai\Open\EdgeGrid\Client\Timestamp');
        $timestamp->__toString()->willReturn($options['timestamp']);
        $nonce = $this->prophesize('\Akamai\Open\EdgeGrid\Client\Nonce');
        $nonce->__toString()->willReturn($options['nonce']);
        
        $client = new Client(
            [
                'base_uri' => $options['base_url'], 
                'handler' => $handler,
            ],
            $timestamp->reveal(),
            $nonce->reveal()
        );
        
        /* @var \GuzzleHttp\Client $client */
        $client->setAuth($options['client_token'], $options['client_secret'], $options['access_token']);
        $client->setMaxBodySize($options['max_body']);

        if (isset($options['headers_to_sign'])) {
            $client->setHeadersToSign($options['headers_to_sign']);
        }

        $headers = array();
        if (isset($request['headers'])) {
            array_walk_recursive($request['headers'], function ($value, $key) use (&$headers) {
                $headers[$key] = $value;
            });
        }
        
        $client->request(
            $request['method'],
            $request['path'],
            [
                'headers' => $headers,
                'body' => $request['data'],
            ]
        );
        
        $this->assertEquals(1, sizeof($container));
        $request = $container[0]['request'];
        $headers = $request->getHeaders();
        
        $this->assertEquals(1, sizeof($headers['Authorization']));
        $this->assertEquals($result, $headers['Authorization'][0]);
    }

    public function testMakeNonce()
    {
        $nonce = new Client\Nonce;
        
        $nonces = [];
        for ($i = 0; $i < 100; $i++) {
            $nonces[] = (string) $nonce;
        }

        $this->assertEquals(100, count(array_unique($nonces)));
    }
    
    public function testMakeNonceRandomBytes()
    {
        if (!function_exists('random_bytes')) {
            function random_bytes($size)
            {
                return 'random_bytes';
            }
        }
        
        $nonce = new Client\Nonce;
        $closure = function() {
            return $this->function;
        };
        $tester = $closure->bindTo($nonce, $nonce);

        $this->assertEquals('random_bytes', $tester());
    }
    
    public function testCreateFromEdgeRcDefault()
    {
        $_SERVER['HOME'] = __DIR__ .'/edgerc';
        $client = \Akamai\Open\EdgeGrid\Client::createFromEdgeRcFile();
        $closure = function($what) {
            return $this->{$what};
        };
        $tester = $closure->bindTo($client, $client);
        
        $this->assertInstanceOf('\Akamai\Open\EdgeGrid\Client', $client);
        $this->assertEquals([
            'client_token' => "akab-client-token-xxx-xxxxxxxxxxxxxxxx",
            'client_secret' => "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=",
            'access_token' => "akab-access-token-xxx-xxxxxxxxxxxxxxxx"
        ], $tester('auth'));
        $this->assertEquals('akaa-baseurl-xxxxxxxxxxx-xxxxxxxxxxxxx.luna.akamaiapis.net', $tester('host'));
        $this->assertEquals(2048, $tester('max_body_size'));
    }

    public function testCreateFromEdgeRcTestingSection()
    {
        $client = \Akamai\Open\EdgeGrid\Client::createFromEdgeRcFile('testing', __DIR__ . '/edgerc/.edgerc.testing');
        $closure = function($what) {
            return $this->{$what};
        };
        $tester = $closure->bindTo($client, $client);
        
        $this->assertInstanceOf('\Akamai\Open\EdgeGrid\Client', $client);
        $this->assertEquals([
            'client_token' => "akab-client-token-xxx-xxxxxxxxxxxxxxxx",
            'client_secret' => "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=", 
            'access_token' => "akab-access-token-xxx-xxxxxxxxxxxxxxxx"
        ], $tester('auth'));
        $this->assertEquals('akaa-baseurl-xxxxxxxxxxx-xxxxxxxxxxxxx.luna.akamaiapis.net', $tester('host'));
        $this->assertEquals(2048, $tester('max_body_size'));
    }
    
    public function testCreateFromEdgeRcMultiSection()
    {
        $client = \Akamai\Open\EdgeGrid\Client::createFromEdgeRcFile('testing', __DIR__ . '/edgerc/.edgerc.default-testing');
        $closure = function($what) {
            return $this->{$what};
        };
        $tester = $closure->bindTo($client, $client);

        $this->assertInstanceOf('\Akamai\Open\EdgeGrid\Client', $client);
        $this->assertEquals([
            'client_token' => "akab-client-token-xxx-xxxxxxxxxxxxxxxx",
            'client_secret' => "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=",
            'access_token' => "akab-access-token-xxx-xxxxxxxxxxxxxxxx"
        ], $tester('auth'));
        $this->assertEquals('akaa-baseurl-xxxxxxxxxxx-xxxxxxxxxxxxx.luna.akamaiapis.net', $tester('host'));
        $this->assertEquals(2048, $tester('max_body_size'));
    }

    public function testGetEdgeGridTimestamp() {
        $timestamp = new Client\Timestamp;
        
        $this->assertRegExp('/\A\d{4}[0-1][0-9][0-3][0-9]T[0-2][0-9]:[0-5][0-9]:[0-5][0-9][+]0000\z/', (string) $timestamp);
    }
    
    public function makeAuthHeaderProvider()
    {
        $testdata = json_decode(file_get_contents(__DIR__ . '/testdata.json'), true);
        $tests = $testdata['tests'];
        unset($testdata['tests']);
            
        foreach ($tests as $test) {
            yield [
                'name' => $test['testName'],
                'options' => $testdata,
                'request' => array_merge(['data' => ""], $test['request']),
                'result' => $test['expectedAuthorization'],
            ];
        }
    }
}
