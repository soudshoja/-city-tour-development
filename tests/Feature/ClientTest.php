<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    public function test_client_index()
    {

        $response = $this->get('/clients');

        $response->assertStatus(200);
    }

    public function test_return_list_of_client()
    {
        $response = $this->get('/clients/list');

        $response->assertStatus(200);
        $response->assertViewHas('clients');
    }

    public function test_return_list_of_client_by_agent()
    {
        $response = $this->get('/clients/agent/1');

        $response->assertStatus(200);
        $response->assertViewHas('clients');

        $clients = $response->viewData('clients');
        foreach ($clients as $client) {
            $this->assertEquals(1, $client->agent_id);
        }
    }
}
