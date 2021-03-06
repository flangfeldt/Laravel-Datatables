<?php

class MongoDriverTest extends DatatablesTestCase {
    
    public function setUp()
    {
        parent::setUp();
        
        $this->config = $this->app['config']->get("datatables::database.connections.mongo");
        
        $mock = Mockery::mock("Daveawb\Datatables\Drivers\Mongo[createConnection,getDsn]")->shouldAllowMockingProtectedMethods();
        
        $collectionClass = new stdClass();
        $collectionClass->test = true;
        
        $connection = new stdClass();
        $connection->database = new stdClass();
        $connection->database->testcollection = $collectionClass;
        
        $mock->shouldReceive("createConnection")->once()->andReturn($connection);
        $mock->shouldReceive("getDsn")->once()->andReturn(array());
                
        $mock->config($this->config);
        
        $this->driver = $mock;
    }
    
    public function testConfigIsSetCorrectlyAndCreatesConnection()
    {
        $this->assertEquals($this->config, $this->getProperty($this->driver, 'config'));
        $this->assertInstanceOf("stdClass", $this->getProperty($this->driver, 'db'));
    }
	
	/**
	 * @expectedException MongoConnectionException
	 * @expectedExceptionMessage Authentication failed on database 'database' with username 'thisShouldNeverBeAUserName'
	 */
	public function testConfigOptionsAreAppliedToOptions()
	{
		$this->config['username'] = "thisShouldNeverBeAUserName";
		$this->config['password'] = "password";
		
		$driver = new Daveawb\Datatables\Drivers\Mongo;
		
		$mongoClient = $driver->config($this->config);
	}
    
    public function testCollectionAppliesCollectionToDbObject()
    {
        $method = $this->getMethod($this->driver, "collection");
        
        $method->invoke($this->driver, "testcollection");
        
        $this->assertTrue($this->getProperty($this->driver, "db")->test);
    }
    
    public function testQueryAcceptsCollectionAndAppliesIt()
    {
        $this->driver->query("testcollection");
        
        $this->assertTrue($this->getProperty($this->driver, "db")->test);
    }
    
    public function testQueryAcceptsArrayWithClosureAsSecondArg()
    {
        $this->driver->query(array("testcollection", function()
        {
            return array(
                '$or' => array(
                    array("first_name" => "Simon")
                )
            );
        }));
        
        $this->assertEquals(array(array("first_name" => "Simon")), $this->getProperty($this->driver, "searchTerms")['$or']);
    }
    
    /**
     * @expectedException Daveawb\Datatables\DatatablesException
     */
    public function testQueryThrowsExceptionIfNoClosureIsPassed()
    {
        $this->driver->query(array("testcollection", "not callable"));
    }
    
    /**
     * @expectedException Daveawb\Datatables\DatatablesException
     */
    public function testQueryThrowsExceptionIfMoreThanTwoArrayIndexesArePassed()
    {
        $this->driver->query(array("testcollection", "not callable", function($db) {}));
    }
    
    /**
     * @expectedException Daveawb\Datatables\DatatablesException
     */
    public function testQueryThrowsExceptionIfLessThanTwoArrayIndexesArePassed()
    {
        $this->driver->query(array(function($db) {}));
    }
    
    public function testQueryGetsCorrectData()
    {
        $this->seedMongo();
        
        $this->app['config']->set("datatables::database.connections.mongo.database", "datatablestests");
        
        $this->app['request']->replace($this->testData);
        
        $driver = new Daveawb\Datatables\Drivers\Mongo();
        
        $driver->config($this->app['config']->get("datatables::database.connections.mongo"));
        
        $driver->factory(new Daveawb\Datatables\Columns\Factory(
            new Daveawb\Datatables\Columns\Input\OneNineInput($this->app['request']),
            $this->app['validator']
        ));
        
        $driver->query("users");
        
        $result = $driver->get();
        
        $this->assertTrue(is_array($result));
        $this->assertTrue(is_array($result[0]));
    }
    
    public function testDriverFiltersDataUsingSearch()
    {
        $this->seedMongo();
        
        $this->app['config']->set("datatables::database.connections.mongo.database", "datatablestests");
        
        $this->app['request']->replace(array_merge($this->testData, array("bSearchable_0" => true, "sSearch" => "David")));
        
        $driver = new Daveawb\Datatables\Drivers\Mongo();
        
        $driver->config($this->app['config']->get("datatables::database.connections.mongo"));
        
        $factory = new Daveawb\Datatables\Columns\Factory(
            new Daveawb\Datatables\Columns\Input\OneNineInput($this->app['request']),
            $this->app['validator']
        );
        
        $factory->create("first_name", 0);
        $factory->create("last_name", 1);
        
        $driver->factory($factory);
        
        $driver->query("users");
        
        $result = $driver->get();
        
        $this->assertEquals(4, $driver->getTotalRecords());
        $this->assertEquals(1, $driver->getDisplayRecords());
        
        foreach($result as $value)
        {
            $this->assertEquals("David", $value['first_name']);
            $this->assertEquals("Barker", $value['last_name']);
        }
    }

    public function testDriverOrdersDataAsc()
    {
        $this->seedMongo();
        
        $this->app['config']->set("datatables::database.connections.mongo.database", "datatablestests");
        
        $this->app['request']->replace(array_merge($this->testData, array("bSortable_0" => true, "iSortCol_0" => 0, "sSortDir_0" => "asc")));
        
        $driver = new Daveawb\Datatables\Drivers\Mongo();
        
        $driver->config($this->app['config']->get("datatables::database.connections.mongo"));
        
        $factory = new Daveawb\Datatables\Columns\Factory(
            new Daveawb\Datatables\Columns\Input\OneNineInput($this->app['request']),
            $this->app['validator']
        );
        
        $factory->create("first_name", 0);
        $factory->create("last_name", 1);
        
        $driver->factory($factory);
        
        $driver->query("users");
        
        $result = $driver->get();
        
        $this->assertEquals(4, $driver->getTotalRecords());
        $this->assertEquals(4, $driver->getDisplayRecords());
        
        $value = $result[0];
		
        $this->assertEquals("David", $value['first_name']);
        $this->assertEquals("Barker", $value['last_name']);
    }
    
    public function testDriverOrdersDataDesc()
    {
        $this->seedMongo();
        
        $this->app['config']->set("datatables::database.connections.mongo.database", "datatablestests");
        
        $this->app['request']->replace(array_merge($this->testData, array("bSortable_0" => true, "iSortCol_0" => 0, "sSortDir_0" => "desc")));
        
        $driver = new Daveawb\Datatables\Drivers\Mongo();
        
        $driver->config($this->app['config']->get("datatables::database.connections.mongo"));
        
        $factory = new Daveawb\Datatables\Columns\Factory(
            new Daveawb\Datatables\Columns\Input\OneNineInput($this->app['request']),
            $this->app['validator']
        );
        
        $factory->create("first_name", 0);
        $factory->create("last_name", 1);
        
        $driver->factory($factory);
        
        $driver->query("users");
        
        $result = $driver->get();
        
        $this->assertEquals(4, $driver->getTotalRecords());
        $this->assertEquals(4, $driver->getDisplayRecords());
        
        $value = $result[0];
        
        $this->assertEquals("Simon", $value['first_name']);
        $this->assertEquals("Holloway", $value['last_name']);
    }

    public function testDriverUsesClosureDataInQuery()
    {
        $this->seedMongo();
        
        $this->app['config']->set("datatables::database.connections.mongo.database", "datatablestests");
        
        $this->app['request']->replace($this->testData);
        
        $driver = new Daveawb\Datatables\Drivers\Mongo();
        
        $driver->config($this->app['config']->get("datatables::database.connections.mongo"));
        
        $factory = new Daveawb\Datatables\Columns\Factory(
            new Daveawb\Datatables\Columns\Input\OneNineInput($this->app['request']),
            $this->app['validator']
        );
        
        $factory->create("first_name", 0);
        $factory->create("last_name", 1);
        
        $driver->factory($factory);
        
        $driver->query(array("users", function() {
            return array(
                "first_name" => "Simon"
            );
        }));
        
        $result = $driver->get();
        
        $this->assertEquals(3, $driver->getTotalRecords());
        $this->assertEquals(3, $driver->getDisplayRecords());
        
        $value = $result[0];
        
        $this->assertEquals("Simon", $value['first_name']);
        $this->assertEquals("Holloway", $value['last_name']);
    }

    public function testDriverInEndToEndScenario()
    {
        $this->seedMongo();
        
        $this->app['config']->set("datatables::database.connections.mongo.database", "datatablestests");
        
        $this->app['request']->replace($this->testData);
        
        $datatable = new Daveawb\Datatables\Datatable(
            new Daveawb\Datatables\Columns\Factory(
                new Daveawb\Datatables\Columns\Input\OneNineInput($this->app['request']),
                $this->app['validator']
            ),
            new Daveawb\Datatables\Drivers\Laravel,
            new Illuminate\Http\JsonResponse,
            $this->app['config']
        );
        
        $datatable->driver($this->app->make("Daveawb\Datatables\Drivers\Mongo"));
        
        $datatable->query("users");
        
        $datatable->columns(array(
            array("first_name", array("append" => "%", "prepend" => "Mr, ", "combine" => "first_name,last_name, ")),
            array("_id", function($field, $data) {
                return (string)$data[$field];
            })
        ));
        
        $result = $datatable->result();
        
        $data = json_decode($result->getContent(), true);
        
        $this->assertEquals($data['aaData'][0][0], "Mr David% Barker");
    }
}
