<?php

declare(strict_types=1);

namespace Mj\Member\Tests\Value;

use Mj\Member\Classes\Value\EventData;
use PHPUnit\Framework\TestCase;

final class EventDataTest extends TestCase
{
    // -------------------------------------------------------------------------
    // fromArray
    // -------------------------------------------------------------------------

    public function testFromArrayStoresKnownKeysAsAttributes(): void
    {
        $data = EventData::fromArray([
            'id'     => 10,
            'title'  => 'Atelier Codex',
            'status' => 'published',
        ]);

        $this->assertSame(10, $data->id);
        $this->assertSame('Atelier Codex', $data->title);
        $this->assertSame('published', $data->status);
    }

    public function testFromArrayStoresUnknownKeysAsExtras(): void
    {
        $data = EventData::fromArray(['id' => 1, 'computed_registrations' => 42]);

        $this->assertSame(42, $data->get('computed_registrations'));
    }

    public function testFromArrayWithEmptyArrayReturnsEmptyObject(): void
    {
        $data = EventData::fromArray([]);

        $this->assertNull($data->id);
        $this->assertSame([], $data->toArray());
    }

    // -------------------------------------------------------------------------
    // fromRow
    // -------------------------------------------------------------------------

    public function testFromRowAcceptsStdObject(): void
    {
        $row = (object) ['id' => 3, 'title' => 'Camp été'];
        $data = EventData::fromRow($row);

        $this->assertSame(3, $data->id);
        $this->assertSame('Camp été', $data->title);
    }

    public function testFromRowAcceptsArray(): void
    {
        $data = EventData::fromRow(['id' => 7, 'type' => 'atelier']);

        $this->assertSame(7, $data->id);
        $this->assertSame('atelier', $data->type);
    }

    public function testFromRowReturnsSameInstanceWhenAlreadyEventData(): void
    {
        $original = EventData::fromArray(['id' => 5]);
        $result = EventData::fromRow($original);

        $this->assertSame($original, $result);
    }

    public function testFromRowWithNullReturnsEmptyObject(): void
    {
        $data = EventData::fromRow(null);

        $this->assertNull($data->id);
        $this->assertSame([], $data->toArray());
    }

    // -------------------------------------------------------------------------
    // toArray / toDatabaseArray
    // -------------------------------------------------------------------------

    public function testToArrayIncludesExtrasWhenFlagIsTrue(): void
    {
        $data = EventData::fromArray(['id' => 1, 'extra_count' => 5]);

        $array = $data->toArray(true);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('extra_count', $array);
    }

    public function testToArrayExcludesExtrasWhenFlagIsFalse(): void
    {
        $data = EventData::fromArray(['id' => 1, 'extra_count' => 5]);

        $array = $data->toArray(false);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayNotHasKey('extra_count', $array);
    }

    public function testToDatabaseArrayExcludesExtras(): void
    {
        $data = EventData::fromArray([
            'id'             => 2,
            'title'          => 'Sortie',
            'runtime_extra'  => 'should_be_excluded',
        ]);

        $db = $data->toDatabaseArray();

        $this->assertArrayHasKey('id', $db);
        $this->assertArrayHasKey('title', $db);
        $this->assertArrayNotHasKey('runtime_extra', $db);
    }

    // -------------------------------------------------------------------------
    // with — immutabilité
    // -------------------------------------------------------------------------

    public function testWithReturnsNewInstanceWithAppliedChanges(): void
    {
        $original = EventData::fromArray(['id' => 1, 'title' => 'Original']);
        $modified = $original->with(['title' => 'Modifié', 'status' => 'draft']);

        $this->assertNotSame($original, $modified);
        $this->assertSame('Original', $original->title, 'L\'original ne doit pas être muté');
        $this->assertSame('Modifié', $modified->title);
        $this->assertSame('draft', $modified->status);
    }

    public function testWithPreservesExtras(): void
    {
        $original = EventData::fromArray(['id' => 1, 'computed_field' => 'bar']);
        $modified = $original->with(['id' => 2]);

        $this->assertSame('bar', $modified->get('computed_field'));
    }

    // -------------------------------------------------------------------------
    // get / has / __get / __isset
    // -------------------------------------------------------------------------

    public function testGetReturnsNullForMissingKey(): void
    {
        $data = EventData::fromArray(['id' => 1]);

        $this->assertNull($data->get('nonexistent'));
    }

    public function testGetReturnsProvidedDefault(): void
    {
        $data = EventData::fromArray([]);

        $this->assertSame(0, $data->get('capacity_total', 0));
    }

    public function testHasReturnsTrueForKnownKey(): void
    {
        $data = EventData::fromArray(['title' => 'Test']);

        $this->assertTrue($data->has('title'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $data = EventData::fromArray(['id' => 1]);

        $this->assertFalse($data->has('nonexistent'));
    }

    public function testMagicGetDelegatesToGet(): void
    {
        $data = EventData::fromArray(['slug' => 'mon-evenement']);

        $this->assertSame('mon-evenement', $data->slug);
    }

    public function testMagicIssetDelegatesToHas(): void
    {
        $data = EventData::fromArray(['date_debut' => '2025-06-01']);

        $this->assertTrue(isset($data->date_debut));
        $this->assertFalse(isset($data->nonexistent_prop));
    }

    // -------------------------------------------------------------------------
    // jsonSerialize
    // -------------------------------------------------------------------------

    public function testJsonSerializeReturnsFullArrayIncludingExtras(): void
    {
        $data = EventData::fromArray(['id' => 5, 'extra_seats' => 3]);

        $json = $data->jsonSerialize();

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('extra_seats', $json);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $data = EventData::fromArray(['id' => 8, 'title' => 'Test event']);
        $json = json_encode($data);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame(8, $decoded['id']);
        $this->assertSame('Test event', $decoded['title']);
    }
}
