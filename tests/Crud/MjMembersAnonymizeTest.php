<?php

declare(strict_types=1);

namespace Mj\Member\Tests\Crud;

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class MjMembersAnonymizeTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($GLOBALS['wpdb']);
    }

    public function testRejectsInvalidIdentifier(): void
    {
        $result = MjMembers::anonymizePersonalData(0);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mj_member_invalid_id', $result->get_error_code());
    }

    public function testFailsWhenMemberMissing(): void
    {
        $GLOBALS['wpdb'] = $this->createWpdbStub(null);

        $result = MjMembers::anonymizePersonalData(42);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mj_member_missing', $result->get_error_code());
    }

    public function testFailsWhenUpdateReturnsFalse(): void
    {
        $member = (object) array('id' => 51, 'role' => MjRoles::JEUNE);
        $wpdb = $this->createWpdbStub($member);
        $wpdb->updateResult = false;
        $GLOBALS['wpdb'] = $wpdb;

        $result = MjMembers::anonymizePersonalData(51);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mj_member_anonymize_failed', $result->get_error_code());
    }

    public function testAnonymizePersonalDataPersistsSanitizedValues(): void
    {
        $member_id = 73;
        $member = (object) array('id' => $member_id, 'role' => MjRoles::JEUNE);
        $wpdb = $this->createWpdbStub($member);
        $GLOBALS['wpdb'] = $wpdb;
        $GLOBALS['__mj_current_time'] = strtotime('2025-12-05 09:10:11');

        $result = MjMembers::anonymizePersonalData($member_id);
        $this->assertTrue($result);

        $this->assertNotNull($wpdb->lastUpdate, 'La mise à jour devrait être exécutée.');
        $this->assertSame('wp_mj_members', $wpdb->lastUpdate['table']);
        $this->assertSame(array('id' => $member_id), $wpdb->lastUpdate['where']);

        $data = $wpdb->lastUpdate['data'];
        $this->assertSame('Anonymized', $data['first_name']);
        $this->assertSame('anonymized-' . $member_id, $data['last_name']);
        $this->assertSame('anonymized-' . $member_id . '@example.com', $data['email']);
        $this->assertSame('2025-12-05 09:10:11', $data['anonymized_at']);
        $this->assertSame(MjMembers::STATUS_INACTIVE, $data['status']);
    }

    private function createWpdbStub($member)
    {
        return new class($member) {
            public $prefix = 'wp_';
            public $storedMember;
            public $lastUpdate;
            public $updateResult = 1;

            public function __construct($member)
            {
                $this->storedMember = $member;
            }

            public function esc_like($text)
            {
                return addcslashes((string) $text, '%_');
            }

            public function prepare($query, ...$args)
            {
                return $query;
            }

            public function get_results($query, $output = ARRAY_A)
            {
                return array(
                    array('Field' => 'id'),
                    array('Field' => 'first_name'),
                    array('Field' => 'last_name'),
                    array('Field' => 'email'),
                    array('Field' => 'status'),
                    array('Field' => 'anonymized_at'),
                );
            }

            public function get_row($query)
            {
                return $this->storedMember;
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                $this->lastUpdate = compact('table', 'data', 'where', 'format', 'where_format');
                return $this->updateResult;
            }
        };
    }
}
