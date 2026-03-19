<?php

declare(strict_types=1);

namespace Mj\Member\Tests\Value;

use Mj\Member\Classes\Value\MemberData;
use PHPUnit\Framework\TestCase;

final class MemberDataTest extends TestCase
{
    // -------------------------------------------------------------------------
    // fromArray
    // -------------------------------------------------------------------------

    public function testFromArrayStoresKnownKeysAsAttributes(): void
    {
        $data = MemberData::fromArray(['id' => 42, 'first_name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertSame(42, $data->id);
        $this->assertSame('Alice', $data->first_name);
        $this->assertSame('alice@example.com', $data->email);
    }

    public function testFromArrayStoresUnknownKeysAsExtras(): void
    {
        $data = MemberData::fromArray(['id' => 1, 'custom_field' => 'hello']);

        $this->assertSame('hello', $data->get('custom_field'));
    }

    public function testFromArrayWithEmptyArrayReturnsEmptyObject(): void
    {
        $data = MemberData::fromArray([]);

        $this->assertNull($data->id);
        $this->assertSame([], $data->toArray());
    }

    // -------------------------------------------------------------------------
    // fromRow
    // -------------------------------------------------------------------------

    public function testFromRowAcceptsStdObject(): void
    {
        $row = (object) ['id' => 7, 'first_name' => 'Bob'];
        $data = MemberData::fromRow($row);

        $this->assertSame(7, $data->id);
        $this->assertSame('Bob', $data->first_name);
    }

    public function testFromRowAcceptsArray(): void
    {
        $data = MemberData::fromRow(['id' => 3, 'role' => 'animateur']);

        $this->assertSame(3, $data->id);
        $this->assertSame('animateur', $data->role);
    }

    public function testFromRowReturnsSameInstanceWhenAlreadyMemberData(): void
    {
        $original = MemberData::fromArray(['id' => 5]);
        $result = MemberData::fromRow($original);

        $this->assertSame($original, $result);
    }

    public function testFromRowWithNullReturnsEmptyObject(): void
    {
        $data = MemberData::fromRow(null);

        $this->assertNull($data->id);
        $this->assertSame([], $data->toArray());
    }

    // -------------------------------------------------------------------------
    // toArray
    // -------------------------------------------------------------------------

    public function testToArrayIncludesExtrasWhenFlagIsTrue(): void
    {
        $data = MemberData::fromArray(['id' => 1, 'unknown_key' => 'value']);

        $array = $data->toArray(true);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('unknown_key', $array);
    }

    public function testToArrayExcludesExtrasWhenFlagIsFalse(): void
    {
        $data = MemberData::fromArray(['id' => 1, 'unknown_key' => 'value']);

        $array = $data->toArray(false);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayNotHasKey('unknown_key', $array);
    }

    // -------------------------------------------------------------------------
    // with — immutabilité
    // -------------------------------------------------------------------------

    public function testWithReturnsNewInstanceWithAppliedChanges(): void
    {
        $original = MemberData::fromArray(['id' => 1, 'first_name' => 'Alice']);
        $modified = $original->with(['first_name' => 'Carol', 'email' => 'carol@example.com']);

        $this->assertNotSame($original, $modified);
        $this->assertSame('Alice', $original->first_name, 'L\'original ne doit pas être muté');
        $this->assertSame('Carol', $modified->first_name);
        $this->assertSame('carol@example.com', $modified->email);
    }

    public function testWithPreservesExistingAttributes(): void
    {
        $original = MemberData::fromArray(['id' => 99, 'role' => 'animateur', 'status' => 'active']);
        $modified = $original->with(['status' => 'inactive']);

        $this->assertSame(99, $modified->id);
        $this->assertSame('animateur', $modified->role);
        $this->assertSame('inactive', $modified->status);
    }

    public function testWithPreservesExtras(): void
    {
        $original = MemberData::fromArray(['id' => 1, 'extra_computed' => 'foo']);
        $modified = $original->with(['id' => 2]);

        $this->assertSame('foo', $modified->get('extra_computed'));
    }

    // -------------------------------------------------------------------------
    // get / has / __get / __isset
    // -------------------------------------------------------------------------

    public function testGetReturnsNullDefaultForMissingKey(): void
    {
        $data = MemberData::fromArray(['id' => 1]);

        $this->assertNull($data->get('nonexistent'));
    }

    public function testGetReturnsProvidedDefaultForMissingKey(): void
    {
        $data = MemberData::fromArray([]);

        $this->assertSame('default_value', $data->get('missing', 'default_value'));
    }

    public function testGetReturnsNullAttributeWhenExplicitlySet(): void
    {
        $data = MemberData::fromArray(['guardian_id' => null]);

        // La clé est présente mais null — get() doit retourner null, pas le default
        $this->assertNull($data->get('guardian_id', 'should_not_appear'));
    }

    public function testHasReturnsTrueForKnownKey(): void
    {
        $data = MemberData::fromArray(['id' => 5]);

        $this->assertTrue($data->has('id'));
    }

    public function testHasReturnsTrueForExtraKey(): void
    {
        $data = MemberData::fromArray(['extra_badge_count' => 3]);

        $this->assertTrue($data->has('extra_badge_count'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $data = MemberData::fromArray(['id' => 1]);

        $this->assertFalse($data->has('nonexistent'));
    }

    public function testMagicGetDelegatesToGet(): void
    {
        $data = MemberData::fromArray(['nickname' => 'Batman']);

        $this->assertSame('Batman', $data->nickname);
    }

    public function testMagicIssetDelegatesToHas(): void
    {
        $data = MemberData::fromArray(['xp_total' => 150]);

        $this->assertTrue(isset($data->xp_total));
        $this->assertFalse(isset($data->nonexistent_prop));
    }

    // -------------------------------------------------------------------------
    // jsonSerialize
    // -------------------------------------------------------------------------

    public function testJsonSerializeReturnsFullArrayIncludingExtras(): void
    {
        $data = MemberData::fromArray(['id' => 2, 'extra_level' => 'gold']);

        $json = $data->jsonSerialize();

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('extra_level', $json);
        $this->assertSame(2, $json['id']);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $data = MemberData::fromArray(['id' => 10, 'first_name' => 'Test']);
        $json = json_encode($data);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame(10, $decoded['id']);
        $this->assertSame('Test', $decoded['first_name']);
    }
}
