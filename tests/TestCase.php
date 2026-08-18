<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->mockSendsay();

        $this->partialMock(\App\Services\UtmService::class, function ($mock) {
            $mock->shouldReceive('detectUtms')->andReturnNull();
            $mock->shouldIgnoreMissing();
        });

        $this->partialMock(\App\Services\ReferralService::class, function ($mock) {
            $mock->shouldReceive('detectReferral')->andReturnNull();
            $mock->shouldIgnoreMissing();
        });

        $this->partialMock(\App\Services\UserPageService::class, function ($mock) {
            $mock->shouldReceive('detectTotalVisits')->andReturnNull();
            $mock->shouldIgnoreMissing();
        });

        $this->partialMock(\App\Services\ClickHouseService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
            $mock->shouldReceive('getReadCountsForLastThirtyDays')->andReturn(0);
            $mock->shouldReceive('getSubscribersWithPaymentCount')->andReturn(0);
        });
    }

    protected function mockSendsay(): void
    {
        $this->mock(\App\Services\SendsayService::class, function ($mock) {
            $mock->shouldReceive('createGroup')->andReturn(['result' => 'ok', 'id' => 'test_group_id']);
            $mock->shouldReceive('addToGroups')->andReturn(['result' => 'ok']);
            $mock->shouldReceive('deleteFromGroups')->andReturn(['result' => 'ok']);
            $mock->shouldReceive('sendIssueToGroup')->andReturn(['result' => 'ok']);
            $mock->shouldReceive('sendIssuePersonal')->andReturn(['result' => 'ok']);
            $mock->shouldReceive('getIssueId')->andReturn(['result' => 'ok']);
            $mock->shouldReceive('getIssueStats')->andReturn(['result' => 'ok']);
        });
    }
}
