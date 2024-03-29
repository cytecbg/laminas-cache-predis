<?php

namespace CytecTest\Cache\Storage\Adapter;

use Laminas\Cache\ConfigProvider;
use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ServiceManager\ServiceManager;

use Cytec\Cache\Storage\Adapter\Predis;

class PredisTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Predis
     */
    private $storage;

    public function setUp(): void
    {
        $config = (new ConfigAggregator([
            ConfigProvider::class,
            Predis\ConfigProvider::class,
        ]))->getMergedConfig();

        $dependencies = $config['dependencies'];
        
        $container = new ServiceManager($dependencies);

        /** @var StorageAdapterFactoryInterface $storageFactory */
        $storageFactory = $container->get(StorageAdapterFactoryInterface::class);
        
        $this->storage = $storageFactory->createFromArrayConfiguration([
            'adapter' => Predis::class,
            'options' => ['ttl' => 3600],
            'plugins' => [
                ['name' => 'serializer']
            ],
        ]);

        parent::setUp();
    }

    public function tearDown(): void
    {
        if ($this->storage) {
            $this->storage->flush();
        }

        parent::tearDown();
    }

    public function testGetNonExistent()
    {
        $this->assertNull($this->storage->getItem('key'));
    }

    public function testPredisSerializer()
    {
        $value = ['test', 'of', 'array'];
        $this->storage->setItem('key', $value);
        $this->assertCount(count($value), $this->storage->getItem('key'), 'Problem with Redis serialization');
    }

    public function testPredisSetInt()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 123));
        $this->assertEquals('123', $this->storage->getItem($key), 'Integer should be cast to string');
    }

    public function testPredisSetDouble()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 123.12));
        $this->assertEquals('123.12', $this->storage->getItem($key), 'Integer should be cast to string');
    }

    public function testPredisSetNull()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, null));
        $this->assertEquals('', $this->storage->getItem($key), 'Null should be cast to string');
    }

    public function testPredisSetBoolean()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, true));
        $this->assertEquals('1', $this->storage->getItem($key), 'Boolean should be cast to string');
        $this->assertTrue($this->storage->setItem($key, false));
        $this->assertEquals('', $this->storage->getItem($key), 'Boolean should be cast to string');
    }

    public function testTouchItem()
    {
        $key = 'key';

        // no TTL
        $this->storage->getOptions()->setTtl(0);
        $this->storage->setItem($key, 'val');
        $this->assertEquals(0, $this->storage->getMetadata($key)['ttl']);
        $this->assertEquals('val', $this->storage->getItem($key));

        // touch with a specific TTL will add this TTL

        $ttl = 1000;
        $this->storage->getOptions()->setTtl($ttl);
        $this->assertTrue($this->storage->touchItem($key));
        $this->assertEquals($ttl, ceil($this->storage->getMetadata($key)['ttl']));
    }

    public function testGetItems()
    {
        $this->assertTrue($this->storage->setItem('key1', 1));
        $this->assertTrue($this->storage->setItem('key2', 2));

        $items = $this->storage->getItems(['key1', 'key2']);

        $this->assertArrayHasKey('key1', $items);
        $this->assertArrayHasKey('key2', $items);

        $this->assertEquals(1, $items['key1']);
        $this->assertEquals(2, $items['key2']);
    }

    public function testHasItem()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, true));
        $this->assertTrue($this->storage->hasItem($key));
        $this->assertFalse($this->storage->hasItem('non-existent-key'));
    }

    public function testSetMultipleItems()
    {
        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
            'key4' => 'value4',
            'key5' => 'value5'
        ];

        $this->storage->setItems($items);

        foreach($items as $key=>$value)
        {
            $this->assertEquals($value, $this->storage->getItem($key));
        }
    }

    public function testDeleteItem()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 'val'));
        $this->assertTrue($this->storage->removeItem($key));
        $this->assertFalse($this->storage->hasItem($key));
    }

    public function testDeleteItems()
    {
        $this->assertTrue($this->storage->setItem('key1', 1));
        $this->assertTrue($this->storage->setItem('key2', 2));

        $this->storage->removeItems(['key1', 'key2']);

        $this->assertFalse($this->storage->hasItem('key1'));
        $this->assertFalse($this->storage->hasItem('key2'));
    }

    public function testDeleteItemClearsTags()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 'val'));
        $this->assertTrue($this->storage->setTags($key, ['tag']));
        $this->assertTrue($this->storage->removeItem($key));
        $this->assertFalse($this->storage->hasItem($key));
        $this->assertFalse($this->storage->getTags($key));
    }

    public function testDeleteItemsClearsTags()
    {
        $this->assertTrue($this->storage->setItem('key1', 'val1'));
        $this->assertTrue($this->storage->setTags('key1', ['tag']));

        $this->assertTrue($this->storage->setItem('key2', 'val2'));
        $this->assertTrue($this->storage->setTags('key2', ['tag']));

        $this->assertEquals(2, $this->storage->removeItems(['key1','key2']));

        $this->assertFalse($this->storage->hasItem('key1'));
        $this->assertFalse($this->storage->getTags('key1'));

        $this->assertFalse($this->storage->hasItem('key2'));
        $this->assertFalse($this->storage->getTags('key2'));
    }

    public function testIncrementItem()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 100));
        $this->assertEquals(200, $this->storage->incrementItem($key, 100));
    }

    public function testDecrementItem()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 100));
        $this->assertEquals(0, $this->storage->decrementItem($key, 100));
    }

    public function testTotalSpace()
    {
        $this->assertGreaterThan(0, $this->storage->getTotalSpace());
    }

    public function testSetTagsOnNonExistentItemsReturnsFalse()
    {
        $this->assertFalse($this->storage->setTags('key', ['tag1', 'tag2', 'tag3']));
    }

    public function testSetGetTags()
    {
        $key = 'key';
        $tags = ['tag1', 'tag2', 'tag3'];
        $this->assertTrue($this->storage->setItem($key, 100));
        $this->assertTrue($this->storage->setTags($key, $tags));

        $res_tags = $this->storage->getTags($key);

        foreach($tags as $tag)
        {
            $this->assertTrue(in_array($tag, $res_tags));
        }
    }

    public function testSetEmptyArrayClearsTags()
    {
        $key = 'key';
        $tags = ['tag1', 'tag2', 'tag3'];
        $this->assertTrue($this->storage->setItem($key, 100));
        $this->assertTrue($this->storage->setTags($key, $tags));

        $res_tags = $this->storage->getTags($key);

        foreach($tags as $tag)
        {
            $this->assertTrue(in_array($tag, $res_tags));
        }

        $this->storage->setTags($key, []);

        $res_tags = $this->storage->getTags($key);

        $this->assertEmpty($res_tags);
    }

    public function testClearByTagWhenOnlyOneOfTheGivenTagsMustMatch()
    {
        $key_delete = 'key_delete';
        $key_keep = 'key_keep';
        $tags = ['tag1', 'tag2', 'tag3'];
        $this->assertTrue($this->storage->setItem($key_delete, 100));
        $this->assertTrue($this->storage->setItem($key_keep, 100));
        $this->assertTrue($this->storage->setTags($key_delete, $tags));
        $this->assertTrue($this->storage->setTags($key_keep, ['tag3']));

        $this->storage->clearByTags(['tag1','tag2'], true);

        $this->assertFalse($this->storage->hasItem($key_delete));
        $this->assertTrue($this->storage->hasItem($key_keep));
    }

    public function testClearByTagWhenAllGivenTagsMustMatch()
    {
        $key_delete = 'key_delete';
        $key_keep = 'key_keep';

        $this->assertTrue($this->storage->setItem($key_delete, 100));
        $this->assertTrue($this->storage->setItem($key_keep, 100));

        $this->assertTrue($this->storage->setTags($key_delete, ['tag1', 'tag2', 'tag3']));
        $this->assertTrue($this->storage->setTags($key_keep, ['tag1', 'tag2']));

        $this->storage->clearByTags(['tag1', 'tag2', 'tag3']);

        $this->assertFalse($this->storage->hasItem($key_delete));
        $this->assertTrue($this->storage->hasItem($key_keep));
    }

    public function testSetTagsUpdatesTags()
    {
        $key = 'key';
        $tags = ['tag1', 'tag2', 'tag3'];
        $new_tags = ['new_tag2', 'new_tag1'];
        $this->assertTrue($this->storage->setItem($key, 100));
        $this->assertTrue($this->storage->setTags($key, $tags));
        $this->assertTrue($this->storage->setTags($key, $new_tags));

        $res_tags = $this->storage->getTags($key);

        $this->assertEquals(sort($new_tags), sort($res_tags));
    }

    public function testClearByNamespace()
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 100));

        $this->storage->clearByNamespace('laminascache');

        $this->assertFalse($this->storage->hasItem($key));
    }

    public function testClearByPrefix()
    {
        $this->assertTrue($this->storage->setItem('prefixed_key', 100));
        $this->assertTrue($this->storage->setItem('key', 200));

        $this->storage->clearByPrefix('prefixed_');

        $this->assertFalse($this->storage->hasItem('prefixed_key'));
        $this->assertTrue($this->storage->hasItem('key'));
    }
}
