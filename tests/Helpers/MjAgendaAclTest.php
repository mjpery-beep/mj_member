<?php

declare(strict_types=1);

namespace Mj\Member\Tests\Helpers;

use Mj\Member\Classes\MjAgendaAcl;
use Mj\Member\Classes\MjRoles;
use PHPUnit\Framework\TestCase;

final class MjAgendaAclTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__mj_test_options'] = array();
        $GLOBALS['__mj_test_caps'] = array();
        require_once dirname(__DIR__, 2) . '/includes/core/Config.php';
        require_once dirname(__DIR__, 2) . '/includes/classes/MjAgendaAcl.php';
    }

    public function testDefaultsGrantCoordinatorEverything(): void
    {
        foreach (MjAgendaAcl::LAYERS as $layer) {
            foreach (MjAgendaAcl::ACTIONS as $action) {
                $this->assertTrue(
                    MjAgendaAcl::can(MjRoles::COORDINATEUR, $layer, $action, true),
                    "coordinator should have {$action} on {$layer}"
                );
            }
        }
    }

    public function testYoungRoleCannotEditOccurrences(): void
    {
        $this->assertTrue(MjAgendaAcl::can(MjRoles::JEUNE, 'event_occurrences', 'view'));
        $this->assertFalse(MjAgendaAcl::can(MjRoles::JEUNE, 'event_occurrences', 'edit_own', true));
        $this->assertFalse(MjAgendaAcl::can(MjRoles::JEUNE, 'worked_hours', 'view'));
    }

    public function testMoveRequiresMatchingEditGrant(): void
    {
        // Animator: worked_hours has create/edit_own/move/delete but NOT edit_others.
        $this->assertTrue(MjAgendaAcl::can(MjRoles::ANIMATEUR, 'worked_hours', 'move', true));
        $this->assertFalse(MjAgendaAcl::can(MjRoles::ANIMATEUR, 'worked_hours', 'move', false));
    }

    public function testUnknownRoleWithCapabilityIsTreatedAsCoordinator(): void
    {
        $GLOBALS['__mj_test_caps']['mj_manage_members'] = true;
        $this->assertSame(MjRoles::COORDINATEUR, MjAgendaAcl::normalizeRole('wizard'));
        $this->assertTrue(MjAgendaAcl::can('wizard', 'leave_requests', 'delete', false));
    }

    public function testUnknownRoleWithoutCapabilityIsYoung(): void
    {
        $this->assertSame(MjRoles::JEUNE, MjAgendaAcl::normalizeRole('stranger'));
    }

    public function testSaveThenAllRoundTrips(): void
    {
        $matrix = MjAgendaAcl::defaults();
        $matrix[MjRoles::BENEVOLE]['todos'] = array('view', 'view_others', 'create', 'edit_own');
        MjAgendaAcl::save($matrix);

        $this->assertContains('create', MjAgendaAcl::all()[MjRoles::BENEVOLE]['todos']);
        $this->assertTrue(MjAgendaAcl::can(MjRoles::BENEVOLE, 'todos', 'create'));
        // Persisted under the 'agenda' domain key.
        $this->assertArrayHasKey('agenda', $GLOBALS['__mj_test_options'][MjAgendaAcl::OPTION]);
    }

    public function testAssertThrowsWhenForbidden(): void
    {
        $this->expectException(\RuntimeException::class);
        MjAgendaAcl::assert(MjRoles::JEUNE, 'worked_hours', 'create');
    }
}
