<?php

namespace Tests\Unit;

use App\Models\OAuthAlertRule;
use App\Models\OAuthNotification;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OAuthAlertRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_health_check_rule()
    {
        $recipients = ['admin@test.com', 'ops@test.com'];
        $rule = OAuthAlertRule::createHealthCheckRule($recipients);

        $this->assertInstanceOf(OAuthAlertRule::class, $rule);
        $this->assertEquals('Health Check Failure Alert', $rule->name);
        $this->assertEquals('health_check_failure', $rule->trigger_type);
        $this->assertEquals($recipients, $rule->recipients);
        $this->assertEquals(['email', 'slack'], $rule->notification_channels);
        $this->assertEquals(30, $rule->cooldown_minutes);
        $this->assertTrue($rule->is_active);
    }

    public function test_can_create_high_error_rate_rule()
    {
        $recipients = ['admin@test.com'];
        $rule = OAuthAlertRule::createHighErrorRateRule($recipients);

        $this->assertInstanceOf(OAuthAlertRule::class, $rule);
        $this->assertEquals('High Error Rate Alert', $rule->name);
        $this->assertEquals('high_error_rate', $rule->trigger_type);
        $this->assertEquals($recipients, $rule->recipients);
        $this->assertEquals(['email', 'in_app'], $rule->notification_channels);
        $this->assertEquals(15, $rule->cooldown_minutes);
        $this->assertTrue($rule->is_active);
    }

    public function test_can_trigger_when_conditions_met()
    {
        $rule = new OAuthAlertRule([
            'name' => 'Test Rule',
            'trigger_type' => 'health_check_failure',
            'conditions' => [
                ['field' => 'consecutive_failures', 'operator' => '>=', 'threshold' => 3]
            ],
            'is_active' => true,
            'cooldown_minutes' => 30,
            'last_triggered_at' => null,
        ]);

        $this->assertTrue($rule->canTrigger());
    }

    public function test_cannot_trigger_when_inactive()
    {
        $rule = new OAuthAlertRule([
            'name' => 'Test Rule',
            'trigger_type' => 'health_check_failure',
            'conditions' => [],
            'is_active' => false,
            'cooldown_minutes' => 30,
            'last_triggered_at' => null,
        ]);

        $this->assertFalse($rule->canTrigger());
    }

    public function test_cannot_trigger_during_cooldown()
    {
        $rule = new OAuthAlertRule([
            'name' => 'Test Rule',
            'trigger_type' => 'health_check_failure',
            'conditions' => [],
            'is_active' => true,
            'cooldown_minutes' => 30,
            'last_triggered_at' => now()->subMinutes(15), // 15 minutes ago, still in cooldown
        ]);

        $this->assertFalse($rule->canTrigger());
    }

    public function test_can_trigger_after_cooldown()
    {
        $rule = new OAuthAlertRule([
            'name' => 'Test Rule',
            'trigger_type' => 'health_check_failure',
            'conditions' => [],
            'is_active' => true,
            'cooldown_minutes' => 30,
            'last_triggered_at' => now()->subMinutes(45), // 45 minutes ago, cooldown expired
        ]);

        $this->assertTrue($rule->canTrigger());
    }

    public function test_evaluates_conditions_correctly()
    {
        $rule = new OAuthAlertRule([
            'conditions' => [
                ['field' => 'consecutive_failures', 'operator' => '>=', 'threshold' => 3],
                ['field' => 'error_rate', 'operator' => '>', 'threshold' => 10],
            ]
        ]);

        // Should pass when both conditions are met
        $data = [
            'consecutive_failures' => 5,
            'error_rate' => 15,
        ];
        $this->assertTrue($rule->evaluateConditions($data));

        // Should fail when first condition fails
        $data = [
            'consecutive_failures' => 2,
            'error_rate' => 15,
        ];
        $this->assertFalse($rule->evaluateConditions($data));
    }

    public function test_evaluates_different_operators()
    {
        $rule = new OAuthAlertRule([
            'conditions' => [
                ['field' => 'value1', 'operator' => '>', 'threshold' => 10],
                ['field' => 'value2', 'operator' => '<', 'threshold' => 5],
                ['field' => 'value3', 'operator' => '==', 'threshold' => 100],
                ['field' => 'value4', 'operator' => 'contains', 'threshold' => 'error'],
                ['field' => 'value5', 'operator' => 'in', 'threshold' => ['critical', 'high']],
            ]
        ]);

        $data = [
            'value1' => 15,     // > 10 ✓
            'value2' => 3,      // < 5 ✓
            'value3' => 100,    // == 100 ✓
            'value4' => 'Error message', // contains 'error' ✓
            'value5' => 'critical',      // in ['critical', 'high'] ✓
        ];

        $this->assertTrue($rule->evaluateConditions($data));

        // Test failure case
        $data['value1'] = 5; // <= 10, should fail
        $this->assertFalse($rule->evaluateConditions($data));
    }
}